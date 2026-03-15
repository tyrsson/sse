# SseResponse

## Overview

`Webware\SSE\SseResponse` is the PSR-7 response object that carries an SSE
event stream.  It extends `Laminas\Diactoros\Response` so it is fully
interoperable with any code that works with `Psr\Http\Message\ResponseInterface`.

The body of the response is intentionally **empty** — `SseEmitter` bypasses
the PSR-7 body entirely and writes directly to PHP's output buffer as it
invokes the stream callable.  This avoids loading the entire event stream into
memory.

---

## Class synopsis

```php
namespace Webware\SSE;

use Laminas\Diactoros\Response;

final class SseResponse extends Response
{
    public function __construct(
        callable $stream,
        int $status = 200,
        array $headers = [],
    );

    public function getStream(): callable;
}
```

---

## Constructor

### `$stream`

A callable with the signature `function (callable $send): void`.

`$send` is a callback provided by `SseEmitter` that writes a formatted event
frame to the output buffer.  Your callable should loop, call `$send(new Event(...))`
for each event, and return when the stream is finished.

```php
$stream = static function (callable $send): void {
    $send(new Event(data: 'first'));
    $send(new Event(data: 'second'));
};

$response = new SseResponse($stream);
```

The callable is stored as-is; it is not invoked until `SseEmitter` emits the
response.

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

## `getStream()`

Returns the stream callable stored inside the response.  `SseEmitter` calls
this to obtain and invoke the stream.  You rarely need to call it in
application code.

```php
$stream = $response->getStream();
// callable(callable(EventInterface): void): void

// SseEmitter does roughly this:
$send = static function (EventInterface $event): void {
    echo $event->format();
    flush();
};
($response->getStream())($send);
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
the stream callable stored by `getStream()`.  `SseEmitter` ignores the PSR-7
body and always reads from `getStream()`.

---

## Creating from a handler vs from middleware

Both approaches produce the same `SseResponse`:

**From `AbstractSseHandler`** — the base class wraps the stream callable automatically:

```php
final class MyHandler extends AbstractSseHandler
{
    protected function stream(
        ServerRequestInterface $req,
        callable $send,
        ?string $lastId,
    ): void {
        $send(new Event(data: 'hello'));
    }
    // handle() returns new SseResponse(fn(callable $send) => $this->stream($req, $send, $lastId)) for you
}
```

**Directly** — useful in `SseMiddleware` terminal factory mode or custom handlers:

```php
return new SseResponse(
    static function (callable $send): void {
        $send(new Event(data: 'hello'));
    },
);
```

---

← [Back to README](../README.md)
