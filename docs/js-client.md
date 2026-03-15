# JavaScript SSE Client

`webware/sse` ships two JavaScript files in the `js/` directory that provide a feature-rich SSE client for browsers and modern JavaScript environments.

| File | Module format | Use when |
|---|---|---|
| `js/sse-client.js` | ES module (`export`) | Modern browsers, bundlers (Vite, Rollup, webpack) |
| `js/sse-client.iife.js` | IIFE global (`window.SseClient`) | Plain `<script>` tag, legacy browsers |

---

## Quick Example

```html
<!-- ESM -->
<script type="module">
  import { SseClient, ConnectionState } from './js/sse-client.js';

  const client = new SseClient('/events/notifications', {
    onOpen:  () => console.log('connected'),
    onError: (err) => console.warn('SSE error', err),
    onClose: () => console.log('connection closed'),
  });

  client.on('notification', (payload) => {
    console.log(payload.title, payload.body);
  });

  console.log(client.getState()); // 'connecting'
</script>
```

---

## Constructor

```js
new SseClient(url, options?)
```

The constructor immediately calls `connect()` — no extra setup needed.

### Parameters

| Parameter | Type | Description |
|---|---|---|
| `url` | `string` | The SSE endpoint to connect to. |
| `options` | `SseClientOptions` | Optional configuration object (see below). |

---

## Options

| Option | Type | Default | Description |
|---|---|---|---|
| `withCredentials` | `boolean` | `false` | Include cookies / auth headers on cross-origin requests (sets `credentials: 'include'` on the Fetch polyfill). |
| `autoReconnect` | `boolean` | `true` | Reconnect automatically on unexpected connection loss. |
| `reconnectBaseDelay` | `number` (ms) | `1000` | Starting delay for the exponential back-off. Overridden by a server `retry:` field. |
| `reconnectMaxDelay` | `number` (ms) | `30000` | Hard cap on the reconnect delay. |
| `reconnectMaxAttempts` | `number\|null` | `null` | Maximum attempts before giving up. `null` = unlimited. |
| `parseJson` | `boolean` | `true` | Automatically `JSON.parse` event data. Falls back to the raw string on parse failure. |
| `onOpen` | `function\|null` | `null` | Called when the connection opens. |
| `onError` | `function\|null` | `null` | Called when a connection error occurs. |
| `onClose` | `function\|null` | `null` | Called when the connection is permanently closed (no further reconnects). |
| `usePolyfill` | `boolean` | `true` | Use the Fetch-based polyfill when `EventSource` is not available in the global scope. |

---

## Public API

### `connect()`

Opens (or reopens) the SSE connection. Called automatically by the constructor. You only need to call this if you previously called `close()` and want to reconnect from the same instance.

```js
client.connect();
```

### `close()`

Permanently closes the connection. No future reconnection attempts will be made. Fires the `onClose` callback.

```js
client.close();
```

### `on(eventType, handler)`

Subscribes `handler` to a named event type. Use `'message'` for events sent without an explicit `event:` field.

```js
client.on('message', (data) => console.log(data));
client.on('order.shipped', (order) => updateUI(order));
```

The handler receives two arguments:

| Argument | Description |
|---|---|
| `data` | Parsed event data (`JSON.parse` result when `parseJson: true`, otherwise the raw string). |
| `raw` | The original `MessageEvent` (native path) or a plain object `{ data, lastEventId, type }` (Fetch path). |

Returns `this` for chaining.

### `off(eventType, handler)`

Removes a previously registered handler.

```js
client.off('message', myHandler);
```

Returns `this` for chaining.

### `once(eventType, handler)`

Subscribes a handler that fires exactly once, then removes itself.

```js
client.once('auth.ready', () => startApp());
```

Returns `this` for chaining.

### `getState()`

Returns the current `ConnectionStateValue`.

```js
import { ConnectionState } from './js/sse-client.js';

if (client.getState() === ConnectionState.OPEN) {
  // safe to expect events
}
```

### `getLastEventId()`

Returns the `id` of the last received event, or `null` if none has been received yet. This value is automatically forwarded as the `Last-Event-ID` header on reconnect when the Fetch polyfill is active.

```js
const id = client.getLastEventId(); // '42' | null
```

---

## ConnectionState

A frozen object with three string constants:

| Constant | Value | Meaning |
|---|---|---|
| `ConnectionState.CONNECTING` | `'connecting'` | Connection attempt in progress. |
| `ConnectionState.OPEN` | `'open'` | Connection established; events are flowing. |
| `ConnectionState.CLOSED` | `'closed'` | Connection permanently closed. |

---

## Reconnection & Back-off

When the connection drops and `autoReconnect: true`, the client schedules the next attempt using exponential back-off with random jitter:

```
delay = min(baseDelay × 2^attempt, maxDelay) + random(0–500 ms)
```

A `retry:` field sent by the server overrides `reconnectBaseDelay` for subsequent calculations.

The attempt counter resets to `0` on every successful connection.

---

## Fetch Polyfill (no native EventSource)

In environments where `EventSource` is not available (some Node.js versions, older embedded browsers), the client transparently falls back to a `fetch()`-based implementation that:

1. Issues a `GET` request with `Accept: text/event-stream` and `Last-Event-ID` header.
2. Reads the response body as a `ReadableStream`.
3. Decodes chunks incrementally and parses the SSE line protocol.
4. Dispatches events identically to the native path.
5. Uses `AbortController` to cancel the stream on `close()`.

Disable the polyfill with `usePolyfill: false` if you know `EventSource` is always present.

---

## IIFE Global Build

Include the IIFE file via a plain `<script>` tag. It exposes `SseClient` and `ConnectionState` on `globalThis`:

```html
<script src="js/sse-client.iife.js"></script>
<script>
  const client = new SseClient('/events', {
    parseJson: true,
    onOpen: () => document.getElementById('status').textContent = 'Live',
  });

  client.on('price', (quote) => {
    document.getElementById('price').textContent = quote.value;
  });
</script>
```

---

## Usage with a Bundler

Copy `js/sse-client.js` into your project source or install the package, then import normally:

```js
import { SseClient, ConnectionState } from 'webware/sse/js/sse-client.js';
```

No build configuration is required — the file is plain ES2022 with no external dependencies.
