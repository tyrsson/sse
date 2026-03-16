# SseEmitter API

`Webware\SSE\SseEmitter` · `Webware\SSE\SseEmitterFactory` · `Webware\SSE\EmitterStackDelegatorFactory`

---

## `SseEmitter`

```
namespace Webware\SSE;

final class SseEmitter implements Laminas\HttpHandlerRunner\Emitter\EmitterInterface
```

Uses `SapiEmitterTrait` (from `laminas-httphandlerrunner`) to emit headers and body.

### Constructor

```php
public function __construct()
```

No dependencies.

### `emit(ResponseInterface $response): bool`

| Condition | Return value |
|---|---|
| `$response` is **not** an `SseResponse` | `false` — the `EmitterStack` falls through to the next emitter |
| `$response` **is** an `SseResponse` | `true` — the full SSE response is emitted and no further emitters run |

When `$response` is an `SseResponse`:

1. Asserts no previous output has been sent (throws `RuntimeException` otherwise).
2. Emits all response headers via `header()`.
3. Emits the HTTP status line.
4. Echoes the full response body (the formatted SSE stream).

---

## `SseEmitterFactory`

```
namespace Webware\SSE;

final class SseEmitterFactory
```

PSR-11 factory. Registered automatically by `ConfigProvider`.

### `__invoke(ContainerInterface $container, string $requestedName, ?array $options = null): SseEmitter`

Returns `new SseEmitter()`. The `$container` argument is accepted for forwards-compatibility with future configuration needs.

---

## `EmitterStackDelegatorFactory`

```
namespace Webware\SSE;

final readonly class EmitterStackDelegatorFactory
```

A laminas-servicemanager delegator factory. Registered automatically by `ConfigProvider`.  
Pushes `SseEmitter` onto the `EmitterStack` immediately after the stack is created from its own factory.

### `__invoke(ContainerInterface $container, string $name, callable $callback, ?array $options = null): EmitterInterface`

1. Invokes `$callback()` to obtain the existing `EmitterStack` instance.
2. Validates that the result is an `EmitterStack`; throws `RuntimeException` otherwise.
3. Retrieves `SseEmitter` from the container.
4. Calls `$stack->push($sseEmitter)` — placing `SseEmitter` on top of the stack.
5. Returns the modified stack.

> **Stack order matters.** `SapiEmitter` must be pushed **before** the delegator runs so it sits below `SseEmitter`. `SseEmitter` intercepts `SseResponse` instances first; everything else falls through to `SapiEmitter`.

---

## EmitterStack setup

### Via `ConfigProvider` (recommended)

`ConfigProvider` registers both the factory and the delegator. All that is needed in the bootstrap is to push `SapiEmitter` onto the stack before handing it to the runner:

```php
// public/index.php
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$stack = $container->get(EmitterStack::class);
$stack->push(new SapiEmitter());
```

### Manual setup (without `ConfigProvider`)

```php
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Webware\SSE\SseEmitter;

$stack = new EmitterStack();
$stack->push(new SapiEmitter()); // bottom — handles all non-SSE responses
$stack->push(new SseEmitter());  // top    — intercepts SseResponse
```

---

## Notes

- `SseEmitter` does **not** manage output buffering. If your web server or PHP configuration enables output buffering, configure it separately (`ob_implicit_flush(true)` or `zlib.output_compression = Off`) to ensure real-time delivery.
- The `X-Accel-Buffering: no` header should be added to your `SseResponse` (or via middleware) when running behind Nginx.
