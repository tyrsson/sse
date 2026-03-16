# Event API

`Webware\SSE\EventInterface` · `Webware\SSE\Event`

---

## `EventInterface`

```
namespace Webware\SSE;

interface EventInterface extends Stringable
```

`EventInterface` extends `Stringable`, so any value object that implements it can be cast to a string to obtain the complete SSE wire-format block.

### Methods

#### `getId(): ?string`

Returns the event identifier, or `null` when none was set.  
The browser's `EventSource` remembers this value and sends it back as the `Last-Event-ID` HTTP header on reconnection.

---

#### `getEvent(): ?string`

Returns the named event type, or `null` for a generic `message` event.  
The browser fires `addEventListener('<event>', handler)` for named events, and the default `onmessage` handler for `null`.  
The htmx `sse` extension maps this value directly to `sse-swap="<event>"`.

---

#### `getData(): string`

Returns the event payload as a plain string.  
Multi-line strings are supported; `format()` will emit one `data:` line per newline.

---

#### `getRetry(): ?int`

Returns the reconnection delay in milliseconds, or `null` when not set.  
When set, the browser uses this value as the delay before reopening the connection after a disconnect.

---

#### `getComment(): ?string`

Returns the SSE comment text, or `null` when not set.  
SSE comments are emitted as `: <comment>` and are invisible to `EventSource` listeners.  
They are commonly used for keep-alive pings or debugging.

---

#### `format(): string`

Serialises the event into the SSE wire format ready to be flushed to the client.

Field order:
1. `event: <event>` — omitted when `null`
2. `data: <line>` — one line per newline in `getData()`
3. `id: <id>` — omitted when `null`
4. `retry: <retry>` — omitted when `null`
5. `: <comment>` — omitted when `null`
6. Blank line terminator (`\n`)

The returned string always ends with a blank line (`"\n\n"` minimum) to dispatch the event to the client.

---

## `Event`

```
namespace Webware\SSE;

final class Event implements EventInterface
```

Immutable value object representing a single SSE event.

### Constructor

```php
public function __construct(
    private readonly string  $data,
    private readonly ?string $event   = null,
    private readonly ?string $id      = null,
    private readonly ?int    $retry   = null,
    private readonly ?string $comment = null,
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$data` | `string` | — | Event payload. Newlines produce multiple `data:` lines. |
| `$event` | `?string` | `null` | Named event type. Maps to `sse-swap` in htmx. |
| `$id` | `?string` | `null` | Event identifier for reconnect tracking. |
| `$retry` | `?int` | `null` | Browser reconnect delay in milliseconds. |
| `$comment` | `?string` | `null` | Inline SSE comment for keep-alive or debugging. |

### Constants

| Constant | Value | SSE field prefix |
|---|---|---|
| `Event::FIELD_EVENT` | `'event: '` | Named event type |
| `Event::FIELD_DATA` | `'data: '` | Data payload line |
| `Event::FIELD_ID` | `'id: '` | Event identifier |
| `Event::FIELD_RETRY` | `'retry: '` | Reconnect delay |
| `Event::FIELD_COMMENT` | `': '` | SSE comment |

### Methods

All methods are inherited from `EventInterface` (see above).  
`Event` additionally implements `__toString()` as an alias of `format()`.

#### `__toString(): string`

Equivalent to calling `format()`. Enables direct string casting and concatenation:

```php
$stream = '';
$stream .= new Event(data: $html, event: 'notification');
$stream .= new Event(data: ': keep-alive', comment: 'ping');
```

### Examples

**Named event with HTML data (htmx swap)**

```php
use Webware\SSE\Event;

$event = new Event(
    data:  '<li class="notification info">Deploy complete</li>',
    event: 'notification',
    id:    'evt-001',
);

echo $event->format();
// event: notification
// data: <li class="notification info">Deploy complete</li>
// id: evt-001
//
```

**Multi-line data**

```php
$event = new Event(
    data: "Line one\nLine two\nLine three",
);

echo $event->format();
// data: Line one
// data: Line two
// data: Line three
//
```

**Keep-alive comment**

```php
$heartbeat = new Event(data: '', comment: 'ping');

echo $heartbeat->format();
// data:
// : ping
//
```

**Retry hint**

```php
$event = new Event(
    data:  'reconnect in 5 s',
    retry: 5000,
);
```

**Wire format reference**

```
event: <event>\n
data: <data line 1>\n
data: <data line 2>\n
id: <id>\n
retry: <retry>\n
: <comment>\n
\n
```
