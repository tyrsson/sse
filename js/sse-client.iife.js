/**
 * webware/sse — JavaScript SSE Client (IIFE browser global)
 *
 * Exposes `SseClient` and `ConnectionState` as properties of the global scope.
 * This build is intended for use via a plain <script> tag in browsers that do
 * not support ES modules (or when a bundler is not in use).
 *
 * Usage:
 *
 *   <script src="js/sse-client.iife.js"></script>
 *   <script>
 *     const client = new SseClient('/events', {
 *       onOpen: () => console.log('connected'),
 *     });
 *     client.on('message', (data) => console.log(data));
 *   </script>
 */
(function (globalScope) {
  'use strict';

  // -------------------------------------------------------------------------
  // ConnectionState
  // -------------------------------------------------------------------------

  /**
   * @typedef {'connecting'|'open'|'closed'} ConnectionStateValue
   */

  /**
   * @type {{ CONNECTING: 'connecting', OPEN: 'open', CLOSED: 'closed' }}
   */
  var ConnectionState = Object.freeze({
    CONNECTING: 'connecting',
    OPEN:       'open',
    CLOSED:     'closed',
  });

  // -------------------------------------------------------------------------
  // Internal helpers
  // -------------------------------------------------------------------------

  /**
   * @param {string[]} lines
   * @returns {{ event: string, data: string, id: string|null, retry: number|null }}
   */
  function parseSseBlock(lines) {
    var event = 'message';
    var dataParts = [];
    var id = null;
    var retry = null;

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];

      if (line.charAt(0) === ':') {
        continue; // Comment
      }

      var colonIndex = line.indexOf(':');
      var field, value;

      if (colonIndex === -1) {
        field = line;
        value = '';
      } else {
        field = line.slice(0, colonIndex);
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
          if (value.indexOf('\0') === -1) {
            id = value;
          }
          break;
        case 'retry':
          if (/^\d+$/.test(value)) {
            retry = parseInt(value, 10);
          }
          break;
        default:
          break;
      }
    }

    return {
      event:  event,
      data:   dataParts.join('\n'),
      id:     id,
      retry:  retry,
    };
  }

  /**
   * @param {number} baseDelay
   * @param {number} maxDelay
   * @param {number} attempt
   * @returns {number}
   */
  function backOffDelay(baseDelay, maxDelay, attempt) {
    var exponential = baseDelay * Math.pow(2, attempt);
    var jitter = Math.random() * 500;
    return Math.min(exponential + jitter, maxDelay);
  }

  // -------------------------------------------------------------------------
  // SseClient
  // -------------------------------------------------------------------------

  /**
   * @typedef {Object} SseClientOptions
   * @property {boolean}       [withCredentials=false]
   * @property {boolean}       [autoReconnect=true]
   * @property {number}        [reconnectBaseDelay=1000]
   * @property {number}        [reconnectMaxDelay=30000]
   * @property {number|null}   [reconnectMaxAttempts=null]
   * @property {boolean}       [parseJson=true]
   * @property {function|null} [onOpen=null]
   * @property {function|null} [onError=null]
   * @property {function|null} [onClose=null]
   * @property {boolean}       [usePolyfill=true]
   */

  /**
   * @param {string}          url
   * @param {SseClientOptions} [options={}]
   * @constructor
   */
  function SseClient(url, options) {
    options = options || {};

    this._url    = url;
    this._source = null;
    this._fetchAbort = null;
    this._state  = ConnectionState.CLOSED;
    this._listeners = {};
    this._lastEventId = null;
    this._reconnectAttempts = 0;
    this._reconnectTimer = null;
    this._serverRetryMs = 0;
    this._permanentlyClosed = false;

    this._options = {
      withCredentials:      options.withCredentials      !== undefined ? options.withCredentials      : false,
      autoReconnect:        options.autoReconnect        !== undefined ? options.autoReconnect        : true,
      reconnectBaseDelay:   options.reconnectBaseDelay   !== undefined ? options.reconnectBaseDelay   : 1000,
      reconnectMaxDelay:    options.reconnectMaxDelay    !== undefined ? options.reconnectMaxDelay    : 30000,
      reconnectMaxAttempts: options.reconnectMaxAttempts !== undefined ? options.reconnectMaxAttempts : null,
      parseJson:            options.parseJson            !== undefined ? options.parseJson            : true,
      onOpen:               options.onOpen               !== undefined ? options.onOpen               : null,
      onError:              options.onError              !== undefined ? options.onError              : null,
      onClose:              options.onClose              !== undefined ? options.onClose              : null,
      usePolyfill:          options.usePolyfill          !== undefined ? options.usePolyfill          : true,
    };

    this.connect();
  }

  // -------------------------------------------------------------------------
  // Public API
  // -------------------------------------------------------------------------

  /**
   * Opens the SSE connection.
   */
  SseClient.prototype.connect = function () {
    if (this._permanentlyClosed) return;

    if (this._reconnectTimer !== null) {
      clearTimeout(this._reconnectTimer);
      this._reconnectTimer = null;
    }

    this._setState(ConnectionState.CONNECTING);

    if (this._options.usePolyfill && typeof EventSource === 'undefined') {
      this._connectWithFetch();
    } else {
      this._connectWithEventSource();
    }
  };

  /**
   * Permanently closes the SSE connection.
   */
  SseClient.prototype.close = function () {
    this._permanentlyClosed = true;
    this._cleanup();
    this._setState(ConnectionState.CLOSED);
    if (this._options.onClose) {
      this._options.onClose();
    }
  };

  /**
   * @param {string}   eventType
   * @param {Function} handler
   * @returns {SseClient}
   */
  SseClient.prototype.on = function (eventType, handler) {
    if (!this._listeners[eventType]) {
      this._listeners[eventType] = [];
    }
    this._listeners[eventType].push(handler);
    return this;
  };

  /**
   * @param {string}   eventType
   * @param {Function} handler
   * @returns {SseClient}
   */
  SseClient.prototype.off = function (eventType, handler) {
    if (!this._listeners[eventType]) return this;
    this._listeners[eventType] = this._listeners[eventType].filter(function (h) {
      return h !== handler;
    });
    return this;
  };

  /**
   * @param {string}   eventType
   * @param {Function} handler
   * @returns {SseClient}
   */
  SseClient.prototype.once = function (eventType, handler) {
    var self = this;
    var wrapper = function (data, raw) {
      handler(data, raw);
      self.off(eventType, wrapper);
    };
    return this.on(eventType, wrapper);
  };

  /**
   * @returns {ConnectionStateValue}
   */
  SseClient.prototype.getState = function () {
    return this._state;
  };

  /**
   * @returns {string|null}
   */
  SseClient.prototype.getLastEventId = function () {
    return this._lastEventId;
  };

  // -------------------------------------------------------------------------
  // Native EventSource path
  // -------------------------------------------------------------------------

  SseClient.prototype._connectWithEventSource = function () {
    var self = this;
    var src = new EventSource(this._url, {
      withCredentials: this._options.withCredentials,
    });

    this._source = src;

    src.addEventListener('open', function () {
      self._onConnected();
    });

    src.addEventListener('error', function (evt) {
      if (self._permanentlyClosed) return;
      if (src.readyState === EventSource.CLOSED) {
        self._onDisconnected(evt);
      } else {
        if (self._options.onError) {
          self._options.onError(evt);
        }
      }
    });

    src.addEventListener('message', function (evt) {
      self._handleNativeEvent(evt, 'message');
    });

    var types = Object.keys(this._listeners);
    for (var i = 0; i < types.length; i++) {
      (function (type) {
        if (type !== 'message') {
          src.addEventListener(type, function (evt) {
            self._handleNativeEvent(evt, type);
          });
        }
      })(types[i]);
    }
  };

  /**
   * @param {MessageEvent} evt
   * @param {string}       eventType
   */
  SseClient.prototype._handleNativeEvent = function (evt, eventType) {
    if (evt.lastEventId) {
      this._lastEventId = evt.lastEventId;
    }
    var data = this._parseData(evt.data);
    this._dispatch(eventType, data, evt);
  };

  // -------------------------------------------------------------------------
  // Fetch polyfill path
  // -------------------------------------------------------------------------

  SseClient.prototype._connectWithFetch = function () {
    var self = this;
    var abort = new AbortController();
    this._fetchAbort = abort;

    var headers = {
      'Accept': 'text/event-stream',
      'Cache-Control': 'no-cache',
    };

    if (this._lastEventId !== null) {
      headers['Last-Event-ID'] = this._lastEventId;
    }

    fetch(this._url, {
      headers: headers,
      credentials: this._options.withCredentials ? 'include' : 'same-origin',
      signal: abort.signal,
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('SSE: HTTP ' + response.status + ' ' + response.statusText);
        }
        if (!response.body) {
          throw new Error('SSE: Response body is not readable (ReadableStream not supported).');
        }
        self._onConnected();
        return self._readFetchStream(response.body);
      })
      .catch(function (err) {
        if (self._permanentlyClosed || abort.signal.aborted) return;
        if (self._options.onError) {
          self._options.onError(err);
        }
        self._onDisconnected(err);
      });
  };

  /**
   * @param {ReadableStream} body
   * @returns {Promise<void>}
   */
  SseClient.prototype._readFetchStream = function (body) {
    var self = this;
    var reader = body.getReader();
    var decoder = new TextDecoder();
    var block = [];
    var overflow = '';

    function pump() {
      if (self._permanentlyClosed) {
        reader.releaseLock();
        return Promise.resolve();
      }

      return reader.read().then(function (result) {
        if (result.done) {
          reader.releaseLock();
          if (!self._permanentlyClosed) {
            self._onDisconnected(null);
          }
          return;
        }

        var chunk = overflow + decoder.decode(result.value, { stream: true });
        var rawLines = chunk.split(/\r\n|\r|\n/);
        overflow = rawLines.pop() || '';

        for (var i = 0; i < rawLines.length; i++) {
          var line = rawLines[i];
          if (line === '') {
            if (block.length > 0) {
              self._dispatchFetchBlock(block);
              block = [];
            }
          } else {
            block.push(line);
          }
        }

        return pump();
      });
    }

    return pump().catch(function (err) {
      reader.releaseLock();
      if (!self._permanentlyClosed) {
        self._onDisconnected(err);
      }
    });
  };

  /**
   * @param {string[]} lines
   */
  SseClient.prototype._dispatchFetchBlock = function (lines) {
    var parsed = parseSseBlock(lines);

    if (parsed.retry !== null) {
      this._serverRetryMs = parsed.retry;
    }
    if (parsed.id !== null) {
      this._lastEventId = parsed.id;
    }
    if (parsed.data === '') return;

    var data = this._parseData(parsed.data);
    this._dispatch(parsed.event, data, { data: parsed.data, lastEventId: parsed.id, type: parsed.event });
  };

  // -------------------------------------------------------------------------
  // Shared connection lifecycle
  // -------------------------------------------------------------------------

  SseClient.prototype._onConnected = function () {
    this._reconnectAttempts = 0;
    this._setState(ConnectionState.OPEN);
    if (this._options.onOpen) {
      this._options.onOpen();
    }
  };

  SseClient.prototype._onDisconnected = function (reason) {
    this._cleanup();

    if (this._permanentlyClosed) return;

    if (!this._options.autoReconnect) {
      this._setState(ConnectionState.CLOSED);
      if (this._options.onClose) this._options.onClose();
      return;
    }

    var max = this._options.reconnectMaxAttempts;
    if (max !== null && this._reconnectAttempts >= max) {
      this._setState(ConnectionState.CLOSED);
      if (this._options.onClose) this._options.onClose();
      return;
    }

    var baseDelay = this._serverRetryMs > 0
      ? this._serverRetryMs
      : this._options.reconnectBaseDelay;

    var delay = backOffDelay(baseDelay, this._options.reconnectMaxDelay, this._reconnectAttempts);
    this._reconnectAttempts++;

    var self = this;
    this._reconnectTimer = setTimeout(function () {
      self._reconnectTimer = null;
      self.connect();
    }, delay);
  };

  // -------------------------------------------------------------------------
  // Internal helpers
  // -------------------------------------------------------------------------

  SseClient.prototype._dispatch = function (eventType, data, raw) {
    var handlers = this._listeners[eventType];
    if (!handlers || handlers.length === 0) return;

    for (var i = 0; i < handlers.length; i++) {
      try {
        handlers[i](data, raw);
      } catch (err) {
        console.error('[SseClient] Uncaught error in \'' + eventType + '\' handler:', err);
      }
    }
  };

  SseClient.prototype._parseData = function (raw) {
    if (!this._options.parseJson) return raw;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return raw;
    }
  };

  SseClient.prototype._setState = function (state) {
    this._state = state;
  };

  SseClient.prototype._cleanup = function () {
    if (this._source) {
      this._source.close();
      this._source = null;
    }
    if (this._fetchAbort) {
      this._fetchAbort.abort();
      this._fetchAbort = null;
    }
    if (this._reconnectTimer !== null) {
      clearTimeout(this._reconnectTimer);
      this._reconnectTimer = null;
    }
  };

  // -------------------------------------------------------------------------
  // Expose globals
  // -------------------------------------------------------------------------

  globalScope.SseClient      = SseClient;
  globalScope.ConnectionState = ConnectionState;

}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this));
