# Events

## Overview

An SSE event is a small block of text flushed to the client in a defined wire
format.  In `webware/sse` an event is represented by the `Event` value object
which implements `EventInterface`.

---

## `EventInterface`

```php
namespace Webware\SSE;

interface EventInterface
{
    public function getId(): ?string;
    public function getEvent(): ?string;
    public function getData(): string;
    public function getRetry(): ?int;
    public function getComment(): ?string;
    public function format(): string;
}
```

`format()` serialises the event into the SSE wire format ready to be flushed.
You rarely call it yourself — `SseEmitter` calls it automatically as it
iterates the generator.

---

## `Event`

`Event` is a `final`, **immutable** value object.  All properties are set in
the constructor using named arguments.

```php
use Webware\SSE\Event;

$event = new Event(
    data:    'The payload',      // required
    id:      '42',               // optional — enables Last-Event-ID tracking
    event:   'price-update',     // optional — named event type
    retry:   5000,               // optional — reconnect timeout in ms
    comment: 'internal note',    // optional — invisible to JS listener
);
```

### Constructor parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$data` | `string` | — | **Required.** The event payload. Multi-line strings are supported (see below). |
| `$id` | `?string` | `null` | Event identifier. When set, the browser sends this value back as `Last-Event-ID` on reconnect. |
| `$event` | `?string` | `null` | Named event type. JS listens with `addEventListener('event-type', …)`. When `null`, the JS `message` event fires. |
| `$retry` | `?int` | `null` | Milliseconds to wait before the browser reconnects after losing the connection. |
| `$comment` | `?string` | `null` | Inline SSE comment. Sent as `: comment` — invisible to `EventSource` listeners but useful for keep-alive and debugging. |

---

## Wire format

`Event::format()` produces a block of SSE-formatted lines ending with a
mandatory blank line.  Field order:

```
: <comment>
retry: <ms>
id: <id>
event: <type>
data: <line>
<blank line>
```

Only fields that are set are emitted.

### Examples

**Minimal event**
```
data: hello world

```

**Named event with id**
```
id: 42
event: price-update
data: {"symbol":"AAPL","price":189.50}

```

**Event with retry**
```
retry: 5000
id: 43
event: price-update
data: {"symbol":"AAPL","price":190.00}

```

**With comment**
```
: price snapshot
id: 44
event: price-update
data: {"symbol":"AAPL","price":191.25}

```

---

## Multi-line data

A single `\n` character in `$data` produces multiple `data:` lines in the
wire format.  The browser's `EventSource` joins them back into a single string
with `\n`:

```php
yield new Event(data: "line one\nline two\nline three");
```

Wire output:
```
data: line one
data: line two
data: line three

```

This is the correct way to send JSON with embedded newlines — just encode it
normally and let `Event` split it:

```php
$send(new Event(data: json_encode($payload, JSON_PRETTY_PRINT)));
```

---

## Polling without events

When your poll loop finds nothing to send, simply `sleep()` and loop again.
To keep the connection alive during idle periods, send a comment frame
periodically (see [SseEmitter — Keep-alive](emitter.md#keep-alive)):

```php
protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    while (true) {
        $events = $this->fetchNewEvents();

        foreach ($events as $ev) {
            $send(new Event(data: $ev->toJson(), id: $ev->id));
        }

        if (connection_aborted()) {
            break;
        }

        sleep(1);
    }
}
```

---

## Custom event implementations

You can implement `EventInterface` directly to create specialised event types:

```php
use Webware\SSE\EventInterface;

final class JsonEvent implements EventInterface
{
    public function __construct(
        private readonly mixed $payload,
        private readonly string $type,
        private readonly ?string $id = null,
    ) {}

    public function getId(): ?string    { return $this->id; }
    public function getEvent(): ?string { return $this->type; }
    public function getData(): string   { return json_encode($this->payload, JSON_THROW_ON_ERROR); }
    public function getRetry(): ?int    { return null; }
    public function getComment(): ?string { return null; }

    public function format(): string
    {
        $out = '';
        if ($this->id !== null)    $out .= 'id: '    . $this->id    . "\n";
        if ($this->type !== null)  $out .= 'event: ' . $this->type  . "\n";
        $out .= 'data: ' . $this->getData() . "\n";
        return $out . "\n";
    }
}
```

`SseEmitter` accepts any `EventInterface` implementation — it calls `format()`
and writes the result:

```php
$send(new JsonEvent($dto, 'order-update', id: $dto->id));
```

---

## Closing the stream: `CloseEvent`

When a finite stream ends, the server closes the connection by returning from
`stream()`.  The browser's `EventSource` will then automatically reconnect
after the `retry` timeout.  To prevent reconnection, send a `CloseEvent`
before returning:

```php
use Webware\SSE\CloseEvent;

$send(new CloseEvent());
return;
```

`CloseEvent` sends the following SSE block:

```
event: stream-close
data:

```

In client JavaScript, add one listener that calls `source.close()` when it
receives this named event:

```js
const source = new EventSource('/events/progress');

source.addEventListener('stream-close', () => source.close());
```

The named-event string is available as the constant `CloseEvent::EVENT_NAME`
(`'stream-close'`) if you need to reference it in PHP.

---

← [Back to README](../README.md)
