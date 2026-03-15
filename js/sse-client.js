/**
 * webware/sse — JavaScript SSE Client (ESM)
 *
 * A zero-dependency, plain-JavaScript client for Server-Sent Events that wraps
 * the native EventSource API with additional features:
 *
 *   - Exponential back-off reconnect with jitter
 *   - Connection state machine (Connecting / Open / Closed)
 *   - Named-event subscription via on/off/once
 *   - Lifecycle callbacks: onOpen, onError, onClose
 *   - Automatic JSON.parse of event data
 *   - withCredentials / CORS support
 *   - Fetch-based streaming polyfill for environments without EventSource
 *   - Server-controlled retry interval (honours the SSE "retry:" field)
 *   - Last-Event-ID tracking and forwarding on reconnect
 *
 * Usage (ESM):
 *
 *   import { SseClient, ConnectionState } from './js/sse-client.js';
 *
 *   const client = new SseClient('/events', {
 *     onOpen:  ()    => console.log('connected'),
 *     onClose: ()    => console.log('closed'),
 *     onError: (err) => console.error('error', err),
 *   });
 *
 *   client.on('tick', (data) => console.log('tick', data));
 *   client.on('message', (data) => console.log('message', data));
 *
 *   // Later:
 *   client.close();
 */

// ---------------------------------------------------------------------------
// ConnectionState
// ---------------------------------------------------------------------------

/**
 * Enumeration of possible connection states.
 *
 * @typedef {'connecting'|'open'|'closed'} ConnectionStateValue
 */

/**
 * Frozen constant object whose values mirror the connection state machine.
 *
 * @type {{ CONNECTING: 'connecting', OPEN: 'open', CLOSED: 'closed' }}
 */
export const ConnectionState = Object.freeze({
  CONNECTING: /** @type {'connecting'} */ ('connecting'),
  OPEN:       /** @type {'open'}       */ ('open'),
  CLOSED:     /** @type {'closed'}     */ ('closed'),
});

// ---------------------------------------------------------------------------
// JSDoc typedefs
// ---------------------------------------------------------------------------

/**
 * @typedef {Object} SseClientOptions
 * @property {boolean}        [withCredentials=false]       Include credentials on cross-origin requests.
 * @property {boolean}        [autoReconnect=true]          Reconnect on unexpected connection loss.
 * @property {number}         [reconnectBaseDelay=1000]     Initial back-off delay in milliseconds.
 * @property {number}         [reconnectMaxDelay=30000]     Maximum back-off delay in milliseconds.
 * @property {number|null}    [reconnectMaxAttempts=null]   Maximum reconnect attempts; null = unlimited.
 * @property {boolean}        [parseJson=true]              Automatically JSON.parse event data.
 * @property {function|null}  [onOpen=null]                 Called when the connection is opened.
 * @property {function|null}  [onError=null]                Called when a connection error occurs.
 * @property {function|null}  [onClose=null]                Called when the connection is permanently closed.
 * @property {boolean}        [usePolyfill=true]            Use the Fetch-based polyfill when EventSource is unavailable.
 */

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Parses a raw SSE text block (the lines between blank lines) into an object
 * with the fields defined by the SSE specification.
 *
 * @param {string[]} lines
 * @returns {{ event: string, data: string, id: string|null, retry: number|null }}
 */
function parseSseBlock(lines) {
  let event = 'message';
  const dataParts = [];
  let id = null;
  let retry = null;

  for (const line of lines) {
    if (line.startsWith(':')) {
      // Comment line — ignore
      continue;
    }

    const colonIndex = line.indexOf(':');
    let field, value;

    if (colonIndex === -1) {
      field = line;
      value = '';
    } else {
      field = line.slice(0, colonIndex);
      // A single space after the colon is stripped per the spec
      value = line.slice(colonIndex + 1).replace(/^ /, '');
    }

    switch (field) {
      case 'event':
        event = value;
        break;
      case 'data':
        dataParts.push(value);
        break;
      case 'id':
        // An id field with value containing U+0000 is ignored
        if (!value.includes('\0')) {
          id = value;
        }
        break;
      case 'retry':
        if (/^\d+$/.test(value)) {
          retry = parseInt(value, 10);
        }
        break;
      default:
        // Unknown fields are ignored
        break;
    }
  }

  return {
    event,
    data: dataParts.join('\n'),
    id,
    retry,
  };
}

/**
 * Computes the next reconnect delay using exponential back-off with jitter.
 *
 * @param {number} baseDelay
 * @param {number} maxDelay
 * @param {number} attempt  — zero-based attempt counter
 * @returns {number}        — delay in milliseconds
 */
function backOffDelay(baseDelay, maxDelay, attempt) {
  const exponential = baseDelay * Math.pow(2, attempt);
  const jitter = Math.random() * 500;
  return Math.min(exponential + jitter, maxDelay);
}

// ---------------------------------------------------------------------------
// SseClient
// ---------------------------------------------------------------------------

/**
 * A feature-rich SSE client that wraps the native EventSource API.
 *
 * @example
 * const client = new SseClient('/events/notifications', {
 *   parseJson: true,
 *   onOpen: () => console.log('connected'),
 * });
 *
 * client.on('notification', (payload) => {
 *   console.log(payload.message);
 * });
 */
export class SseClient {
  // Private fields
  /** @type {EventSource|null} */
  #source = null;

  /** @type {AbortController|null} */
  #fetchAbort = null;

  /** @type {ConnectionStateValue} */
  #state = ConnectionState.CLOSED;

  /** @type {Map<string, Set<Function>>} */
  #listeners = new Map();

  /** @type {string|null} */
  #lastEventId = null;

  /** @type {number} */
  #reconnectAttempts = 0;

  /** @type {number|null} */
  #reconnectTimer = null;

  /** @type {number} */
  #serverRetryMs = 0;

  /** @type {boolean} */
  #permanentlyClosed = false;

  /** @type {string} */
  #url;

  /** @type {Required<SseClientOptions>} */
  #options;

  /**
   * @param {string}          url     The SSE endpoint URL.
   * @param {SseClientOptions} [options={}]
   */
  constructor(url, options = {}) {
    this.#url = url;
    this.#options = {
      withCredentials:        options.withCredentials        ?? false,
      autoReconnect:          options.autoReconnect          ?? true,
      reconnectBaseDelay:     options.reconnectBaseDelay     ?? 1000,
      reconnectMaxDelay:      options.reconnectMaxDelay      ?? 30000,
      reconnectMaxAttempts:   options.reconnectMaxAttempts   ?? null,
      parseJson:              options.parseJson              ?? true,
      onOpen:                 options.onOpen                 ?? null,
      onError:                options.onError                ?? null,
      onClose:                options.onClose                ?? null,
      usePolyfill:            options.usePolyfill            ?? true,
    };

    this.connect();
  }

  // -------------------------------------------------------------------------
  // Public API
  // -------------------------------------------------------------------------

  /**
   * Opens the SSE connection.
   * Called automatically from the constructor; call again only after a
   * deliberate `close()` if you need to reuse the client instance.
   */
  connect() {
    if (this.#permanentlyClosed) {
      return;
    }

    if (this.#reconnectTimer !== null) {
      clearTimeout(this.#reconnectTimer);
      this.#reconnectTimer = null;
    }

    this.#setState(ConnectionState.CONNECTING);

    if (this.#options.usePolyfill && typeof EventSource === 'undefined') {
      this.#connectWithFetch();
    } else {
      this.#connectWithEventSource();
    }
  }

  /**
   * Permanently closes the SSE connection.
   * No further reconnection attempts will be made.
   */
  close() {
    this.#permanentlyClosed = true;
    this.#cleanup();
    this.#setState(ConnectionState.CLOSED);
    if (this.#options.onClose) {
      this.#options.onClose();
    }
  }

  /**
   * Subscribes to a named SSE event.
   * Use `'message'` for events sent without an explicit `event:` field.
   *
   * @param {string}   eventType
   * @param {Function} handler    Receives the parsed (or raw) event data.
   * @returns {this}
   */
  on(eventType, handler) {
    if (!this.#listeners.has(eventType)) {
      this.#listeners.set(eventType, new Set());
    }
    this.#listeners.get(eventType).add(handler);
    return this;
  }

  /**
   * Unsubscribes a handler from a named SSE event.
   *
   * @param {string}   eventType
   * @param {Function} handler
   * @returns {this}
   */
  off(eventType, handler) {
    this.#listeners.get(eventType)?.delete(handler);
    return this;
  }

  /**
   * Subscribes a handler that fires only once, then removes itself.
   *
   * @param {string}   eventType
   * @param {Function} handler
   * @returns {this}
   */
  once(eventType, handler) {
    const wrapper = (data, raw) => {
      handler(data, raw);
      this.off(eventType, wrapper);
    };
    return this.on(eventType, wrapper);
  }

  /**
   * Returns the current connection state.
   *
   * @returns {ConnectionStateValue}
   */
  getState() {
    return this.#state;
  }

  /**
   * Returns the id of the last event received from the server, or null if no
   * event with an id has been received yet.
   *
   * @returns {string|null}
   */
  getLastEventId() {
    return this.#lastEventId;
  }

  // -------------------------------------------------------------------------
  // Native EventSource path
  // -------------------------------------------------------------------------

  #connectWithEventSource() {
    const src = new EventSource(this.#url, {
      withCredentials: this.#options.withCredentials,
    });

    this.#source = src;

    src.addEventListener('open', () => {
      this.#onConnected();
    });

    src.addEventListener('error', (evt) => {
      if (this.#permanentlyClosed) return;

      if (src.readyState === EventSource.CLOSED) {
        // Connection was closed by the server or network
        this.#onDisconnected(evt);
      } else {
        // Transient error; EventSource will handle its own reconnect for the
        // current open state, but we surface the error to the caller
        if (this.#options.onError) {
          this.#options.onError(evt);
        }
      }
    });

    // Subscribe to all registered event types, plus 'message' as a catch-all
    // for events dispatched without an explicit type.
    src.addEventListener('message', (evt) => {
      this.#handleNativeEvent(evt, 'message');
    });

    // Mirror named listeners onto the native EventSource
    for (const [type] of this.#listeners) {
      if (type !== 'message') {
        src.addEventListener(type, (evt) => {
          this.#handleNativeEvent(evt, type);
        });
      }
    }
  }

  /**
   * @param {MessageEvent} evt
   * @param {string}       eventType
   */
  #handleNativeEvent(evt, eventType) {
    if (evt.lastEventId) {
      this.#lastEventId = evt.lastEventId;
    }

    const data = this.#parseData(evt.data);
    this.#dispatch(eventType, data, evt);
  }

  // -------------------------------------------------------------------------
  // Fetch polyfill path
  // -------------------------------------------------------------------------

  #connectWithFetch() {
    const abort = new AbortController();
    this.#fetchAbort = abort;

    const headers = {
      'Accept': 'text/event-stream',
      'Cache-Control': 'no-cache',
    };

    if (this.#lastEventId !== null) {
      headers['Last-Event-ID'] = this.#lastEventId;
    }

    fetch(this.#url, {
      headers,
      credentials: this.#options.withCredentials ? 'include' : 'same-origin',
      signal: abort.signal,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`SSE: HTTP ${response.status} ${response.statusText}`);
        }
        if (!response.body) {
          throw new Error('SSE: Response body is not readable (ReadableStream not supported).');
        }

        this.#onConnected();
        return this.#readFetchStream(response.body);
      })
      .catch((err) => {
        if (this.#permanentlyClosed || abort.signal.aborted) return;
        if (this.#options.onError) {
          this.#options.onError(err);
        }
        this.#onDisconnected(err);
      });
  }

  /**
   * Reads a ReadableStream line-by-line and dispatches SSE events.
   *
   * @param {ReadableStream<Uint8Array>} body
   * @returns {Promise<void>}
   */
  async #readFetchStream(body) {
    const reader = body.getReader();
    const decoder = new TextDecoder();

    /** @type {string[]}  — lines of the current event block */
    let block = [];

    /** @type {string}   — incomplete line carried between chunks */
    let overflow = '';

    try {
      while (true) {
        if (this.#permanentlyClosed) break;

        const { done, value } = await reader.read();
        if (done) break;

        const chunk = overflow + decoder.decode(value, { stream: true });
        // Split on any combination of CR, LF, CRLF
        const rawLines = chunk.split(/\r\n|\r|\n/);

        // The last element may be an incomplete line — carry it forward
        overflow = rawLines.pop() ?? '';

        for (const line of rawLines) {
          if (line === '') {
            // Blank line = end of event block
            if (block.length > 0) {
              this.#dispatchFetchBlock(block);
              block = [];
            }
          } else {
            block.push(line);
          }
        }
      }
    } finally {
      reader.releaseLock();
    }

    // Stream ended normally — schedule reconnect unless permanently closed
    if (!this.#permanentlyClosed) {
      this.#onDisconnected(null);
    }
  }

  /**
   * Parses and dispatches a completed SSE block from the Fetch path.
   *
   * @param {string[]} lines
   */
  #dispatchFetchBlock(lines) {
    const { event, data, id, retry } = parseSseBlock(lines);

    if (retry !== null) {
      this.#serverRetryMs = retry;
    }

    if (id !== null) {
      this.#lastEventId = id;
    }

    // Do not dispatch if data is empty (spec §9.2.6 step 6)
    if (data === '') return;

    const parsed = this.#parseData(data);
    this.#dispatch(event, parsed, { data, lastEventId: id, type: event });
  }

  // -------------------------------------------------------------------------
  // Shared connection lifecycle
  // -------------------------------------------------------------------------

  #onConnected() {
    this.#reconnectAttempts = 0;
    this.#setState(ConnectionState.OPEN);
    if (this.#options.onOpen) {
      this.#options.onOpen();
    }
  }

  /**
   * @param {Event|Error|null} reason
   */
  #onDisconnected(reason) {
    this.#cleanup();

    if (this.#permanentlyClosed) return;

    if (!this.#options.autoReconnect) {
      this.#setState(ConnectionState.CLOSED);
      if (this.#options.onClose) {
        this.#options.onClose();
      }
      return;
    }

    const max = this.#options.reconnectMaxAttempts;
    if (max !== null && this.#reconnectAttempts >= max) {
      this.#setState(ConnectionState.CLOSED);
      if (this.#options.onClose) {
        this.#options.onClose();
      }
      return;
    }

    const baseDelay = this.#serverRetryMs > 0
      ? this.#serverRetryMs
      : this.#options.reconnectBaseDelay;

    const delay = backOffDelay(baseDelay, this.#options.reconnectMaxDelay, this.#reconnectAttempts);
    this.#reconnectAttempts++;

    this.#reconnectTimer = setTimeout(() => {
      this.#reconnectTimer = null;
      this.connect();
    }, delay);
  }

  // -------------------------------------------------------------------------
  // Internal helpers
  // -------------------------------------------------------------------------

  /**
   * Dispatches an event to all registered handlers.
   *
   * @param {string} eventType
   * @param {unknown} data      — already parsed (if parseJson is true)
   * @param {object}  raw       — original event object
   */
  #dispatch(eventType, data, raw) {
    const handlers = this.#listeners.get(eventType);
    if (!handlers || handlers.size === 0) return;

    for (const handler of handlers) {
      try {
        handler(data, raw);
      } catch (err) {
        // Isolate handler errors so one bad handler doesn't block the others
        console.error(`[SseClient] Uncaught error in '${eventType}' handler:`, err);
      }
    }
  }

  /**
   * Attempts to JSON.parse the raw string if parseJson is enabled.
   * Falls back to the raw string on parse failure.
   *
   * @param {string} raw
   * @returns {unknown}
   */
  #parseData(raw) {
    if (!this.#options.parseJson) return raw;
    try {
      return JSON.parse(raw);
    } catch {
      return raw;
    }
  }

  /**
   * @param {ConnectionStateValue} state
   */
  #setState(state) {
    this.#state = state;
  }

  /**
   * Tears down the active connection without marking it as permanently closed.
   */
  #cleanup() {
    if (this.#source) {
      this.#source.close();
      this.#source = null;
    }
    if (this.#fetchAbort) {
      this.#fetchAbort.abort();
      this.#fetchAbort = null;
    }
    if (this.#reconnectTimer !== null) {
      clearTimeout(this.#reconnectTimer);
      this.#reconnectTimer = null;
    }
  }
}
