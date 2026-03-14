# SseMiddleware

## Overview

`Webware\SSE\SseMiddleware` is a PSR-15 `MiddlewareInterface` that operates in
one of two modes depending on how it is constructed.

| Mode | Constructor | Role |
| --- | --- | --- |
| **Preprocessor** | `new SseMiddleware()` — no factory | Reads `Last-Event-ID`, injects it as a request attribute, and delegates to the next handler |
| **Terminal factory** | `new SseMiddleware($callable)` — callable provided | Reads `Last-Event-ID`, calls the factory to produce a generator, and returns an `SseResponse` directly |

---

## Mode 1 — Preprocessor

Use this mode when you have one or more `AbstractSseHandler` subclasses on
specific routes and you want the `Last-Event-ID` to be available as a
normalised request attribute across all of them, without each handler having to
repeat the header-extraction logic.

### What it does

1. Reads the `Last-Event-ID` request header.
2. Normalises an empty string to `null`.
3. Stores the value as the request attribute `SseMiddleware::LAST_EVENT_ID`
   (`"SSE_LAST_EVENT_ID"`).
4. Calls `$handler->handle($enrichedRequest)` and returns its response.

### Registration

Register the middleware in the application pipeline (not on a specific route)
so it runs for all requests:

```php
// config/pipeline.php
$app->pipe(\Webware\SSE\SseMiddleware::class);
```

Or use it on route-group level:

```php
$app->route('/events[/.*]', [
    \Webware\SSE\SseMiddleware::class,
    // route-specific middleware …
]);
```

Because `SseMiddlewareFactory` constructs `new SseMiddleware()` (no factory
argument), the container-resolved instance is always in preprocessor mode.

### Reading the attribute in a handler

```php
use Webware\SSE\SseMiddleware;

protected function stream(ServerRequestInterface $request, ?string $lastEventId): Generator
{
    // Option A: use the $lastEventId parameter — AbstractSseHandler sets this
    //           from the request header automatically
    $cursor = $lastEventId ?? '0';

    // Option B: read directly from the request attribute set by the middleware
    $cursor = $request->getAttribute(SseMiddleware::LAST_EVENT_ID) ?? '0';

    // Both are equivalent when SseMiddleware preprocessor is in the pipeline
    // and AbstractSseHandler is the terminal handler.
}
```

---

## Mode 2 — Terminal factory

Use this mode when you want to attach an event stream to a specific route
without creating a dedicated handler class.  The middleware itself becomes the
terminal request handler for that route.

### What it does

1. Reads the `Last-Event-ID` request header (normalised to `null` if empty).
2. Calls the provided callable with `($request, ?string $lastEventId)`.
3. Wraps the returned `Generator` in an `SseResponse` and returns it.
4. The next `$handler` in the pipeline is **never called**.

### Usage

Construct `SseMiddleware` with a callable directly in your route config:

```php
// config/routes.php
use Generator;
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\Event;
use Webware\SSE\SseMiddleware;

$app->get('/events/clock', new SseMiddleware(
    static function (ServerRequestInterface $request, ?string $lastEventId): Generator {
        while (true) {
            yield new Event(data: date('H:i:s'), event: 'tick');
            sleep(1);
            yield null;
        }
    }
));
```

### Injecting dependencies into a terminal factory

For production code that requires services, wrap the factory in a closure that
closes over container-resolved dependencies:

```php
// config/routes.php
$notifier = $container->get(NotificationService::class);

$app->get('/events/notifications', new SseMiddleware(
    static function (ServerRequestInterface $req, ?string $lastId) use ($notifier): Generator {
        $cursor = $lastId ?? '0';
        while (true) {
            foreach ($notifier->getNewSince($cursor) as $n) {
                $cursor = $n->id;
                yield new Event(data: $n->toJson(), id: $cursor, event: 'notification');
            }
            yield null;
            sleep(2);
        }
    }
));
```

Or, for a cleaner separation, create an invokable class:

```php
final class NotificationStream
{
    public function __construct(
        private readonly NotificationService $notifier,
    ) {}

    public function __invoke(ServerRequestInterface $request, ?string $lastId): Generator
    {
        $cursor = $lastId ?? '0';
        while (true) {
            foreach ($this->notifier->getNewSince($cursor) as $n) {
                $cursor = $n->id;
                yield new Event(data: $n->toJson(), id: $cursor, event: 'notification');
            }
            yield null;
            sleep(2);
        }
    }
}

// Register + route:
$app->get('/events/notifications', new SseMiddleware(
    new NotificationStream($container->get(NotificationService::class))
));
```

---

## `LAST_EVENT_ID` constant

```php
SseMiddleware::LAST_EVENT_ID === 'SSE_LAST_EVENT_ID'
```

This constant names the request attribute under which the middleware stores the
last event id in preprocessor mode.  Use it to read the value in any downstream
handler or middleware without hard-coding the string:

```php
$lastId = $request->getAttribute(\Webware\SSE\SseMiddleware::LAST_EVENT_ID);
```

---

## Choosing between the two modes

| Scenario | Recommended mode |
| --- | --- |
| Multiple SSE endpoints, each as an `AbstractSseHandler` subclass | Preprocessor — pipe once, `Last-Event-ID` available to all handlers |
| Single or few ad-hoc SSE endpoints, simple logic | Terminal factory — attach callable directly to route, no extra class needed |
| Complex stream logic with many injected services | Terminal factory with an invokable class, or a dedicated `AbstractSseHandler` subclass |
| Mixed pipeline where some routes are SSE, some are not | Preprocessor — it passes through non-SSE responses untouched |

---

← [Back to README](../README.md)
