# SseResponse

## Overview

`Webware\SSE\SseResponse` is the PSR-7 response object that carries an SSE
event stream.  It extends `Laminas\Diactoros\Response` so it is fully
interoperable with any code that works with `Psr\Http\Message\ResponseInterface`.

The body of the response is intentionally **empty** — `SseEmitter` bypasses
the PSR-7 body entirely and writes directly to PHP's output buffer as it
iterates the generator.  This avoids loading the entire event stream into
memory.

---

## Class synopsis

```php
namespace Webware\SSE;

use Generator;
use Laminas\Diactoros\Response;

final class SseResponse extends Response
{
    public function __construct(
        Generator $eventStream,
        int $status = 200,
        array $headers = [],
    );

    public function getEventStream(): Generator;
}
```

---

## Constructor

### `$eventStream`

A PHP `Generator` that `yield`s `EventInterface` instances or `null`.

```php
$stream = (function (): Generator {
    yield new Event(data: 'first');
    yield new Event(data: 'second');
})();

$response = new SseResponse($stream);
```

The generator is stored as-is; it is not started until `SseEmitter` iterates
it.

### `$status`

HTTP status code.  Defaults to `200`.  You may pass a different code if your
application logic requires it (for example `202 Accepted` for an endpoint that
initiates an async process and then streams progress).

```php
$response = new SseResponse($stream, status: 202);
```

### `$headers`

Additional response headers as an associative array.  These are merged with
the default SSE headers; **caller-supplied values take precedence** over the
defaults.

```php
$response = new SseResponse($stream, headers: [
    'Access-Control-Allow-Origin' => '*',
    'Cache-Control'               => 'no-store',  // overrides default "no-cache"
]);
```

---

## Default headers

`SseResponse` sets three headers automatically on every response:

| Header | Value | Purpose |
|---|---|---|
| `Content-Type` | `text/event-stream` | Tells the browser to interpret the body as an SSE stream |
| `Cache-Control` | `no-cache` | Prevents intermediate caching of the stream |
| `X-Accel-Buffering` | `no` | Disables Nginx proxy buffering so bytes reach the client immediately |

---

## `getEventStream()`

Returns the `Generator` stored inside the response.  `SseEmitter` calls this
to obtain the stream.  You rarely need to call it in application code.

```php
$generator = $response->getEventStream();

// SseEmitter does roughly this:
while ($generator->valid()) {
    $event = $generator->current();
    if ($event instanceof EventInterface) {
        echo $event->format();
        flush();
    }
    $generator->next();
}
```

---

## PSR-7 immutability

`SseResponse` honours PSR-7's immutability contract.  All `with*` methods
inherited from `Laminas\Diactoros\Response` return a new instance.

```php
$response = new SseResponse($stream);
$withCors = $response->withHeader('Access-Control-Allow-Origin', '*');

// $response and $withCors are different objects
```

Note that `withBody()` replaces the PSR-7 body stream — it does **not** affect
the `Generator` stored by `getEventStream()`.  `SseEmitter` ignores the PSR-7
body and always reads from `getEventStream()`.

---

## Creating from a handler vs from middleware

Both approaches produce the same `SseResponse`:

**From `AbstractSseHandler`** — the base class wraps the generator automatically:

```php
final class MyHandler extends AbstractSseHandler
{
    protected function stream(ServerRequestInterface $req, ?string $lastId): Generator
    {
        yield new Event(data: 'hello');
    }
    // handle() returns new SseResponse($this->stream($req, $lastId)) for you
}
```

**Directly** — useful in `SseMiddleware` terminal factory mode or custom handlers:

```php
return new SseResponse(
    (function (): Generator {
        yield new Event(data: 'hello');
    })(),
);
```

---

← [Back to README](../README.md)
