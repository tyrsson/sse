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
yield new Event(data: json_encode($payload, JSON_PRETTY_PRINT));
```

---

## The heartbeat signal: `yield null`

Yielding `null` from a generator tells the `SseEmitter` that the generator is
alive but has no event to send right now.  The emitter checks whether the
configured heartbeat interval has elapsed; if so, it sends an automatic
`: heartbeat` comment frame.

```php
protected function stream(ServerRequestInterface $request, ?string $lastEventId): Generator
{
    while (true) {
        $events = $this->fetchNewEvents();

        if (empty($events)) {
            yield null;   // nothing to send — let emitter decide about heartbeat
            sleep(1);
            continue;
        }

        foreach ($events as $ev) {
            yield new Event(data: $ev->toJson(), id: $ev->id);
        }
    }
}
```

> **Tip:** You do not need to send `yield null` at a precise interval.  Yield it
> whenever the generator loops without producing a real event, and the emitter
> handles the timing.

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
yield new JsonEvent($dto, 'order-update', id: $dto->id);
```

---

← [Back to README](../README.md)
