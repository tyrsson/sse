# SseResponse API

`Webware\SSE\SseResponse`

---

```
namespace Webware\SSE;

final class SseResponse extends Laminas\Diactoros\Response
```

A PSR-7 `ResponseInterface` implementation for Server-Sent Events.  
Extends `Laminas\Diactoros\Response` and pre-configures the three headers required by the SSE specification.

---

## Constructor

```php
public function __construct(
    StreamInterface|EventInterface|string $event,
    int $status = 200,
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$event` | `StreamInterface\|EventInterface\|string` | — | The response body. See [Body resolution](#body-resolution) below. |
| `$status` | `int` | `200` | HTTP status code. |

### Default headers

The following headers are always set on construction:

| Header | Value |
|---|---|
| `Content-Type` | `text/event-stream` |
| `Cache-Control` | `no-cache` |
| `Connection` | `keep-alive` |

### Body resolution

| Argument type | Behaviour |
|---|---|
| `StreamInterface` | Used as-is as the response body stream. |
| `EventInterface` | Cast to string via `__toString()` (calls `format()`), then written to a `php://temp` stream. |
| `string` | Written directly to a `php://temp` stream. |

---

## Examples

**From a single `Event`**

```php
use Webware\SSE\Event;
use Webware\SSE\SseResponse;

$event = new Event(data: '<p>Hello</p>', event: 'message');
return new SseResponse($event);
```

**From multiple events concatenated as a string**

```php
$stream = '';
$stream .= new Event(data: $html1, event: 'notification');
$stream .= new Event(data: $html2, event: 'notification');

return new SseResponse($stream);
```

**From a custom stream**

```php
use Laminas\Diactoros\Stream;

$body = new Stream('php://temp', 'wb+');
$body->write((string) new Event(data: 'hello'));
$body->rewind();

return new SseResponse($body);
```

---

## Notes

- `SseResponse` is the signal type that `SseEmitter` checks for. Any response returned from your handler that is **not** an `SseResponse` will be passed through to `SapiEmitter` by the `EmitterStack`.
- Adding or overriding headers after construction follows the standard PSR-7 immutability contract via `withHeader()` / `withAddedHeader()`.
- The `X-Accel-Buffering: no` header (needed to disable Nginx proxy buffering) is **not** set by default. Add it via the `EmitterStack` pipeline or a middleware if required.
