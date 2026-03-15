# webware/sse

A Server-Sent Events (SSE) component for [Mezzio](https://docs.mezzio.dev/) applications built on the Laminas ecosystem.

- **PHP Fiber-powered** — each SSE handler runs inside a `\Fiber`; `$send(new Event(...))` suspends it, the emitter writes the event, then resumes — zero blocking, zero threads
- **PSR-7 native** — `SseResponse` extends `Laminas\Diactoros\Response`
- **EmitterStack-aware** — `SseEmitter` returns `false` for non-SSE responses, letting `SapiEmitter` handle everything else
- **PSR-15 ready** — abstract handler base class and a preprocessor middleware
- **HTMX 2 compatible** — named events (`event:` field) map directly to `sse-swap="<eventName>"` on any element; raw HTML fragments are sent as-is without extra encoding
- **Reconnect support** — `Last-Event-ID` header extracted and forwarded to every handler automatically
- **PSR-11 container** — `ConfigProvider` registers all services; `laminas-servicemanager` is optional

---

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- `laminas/laminas-diactoros` ^3.0
- `laminas/laminas-httphandlerrunner` ^2.0
- `psr/http-message` ^2.0
- `psr/http-server-handler` ^1.0
- `psr/http-server-middleware` ^1.0

---

## Installation

```bash
composer require webware/sse
```

---

## Quick start

### 1. Register the ConfigProvider

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\SSE\ConfigProvider;

return (new ConfigAggregator([
    ConfigProvider::class,
    // your other providers …
]))->getMergedConfig();
```

`ConfigProvider` registers `SseEmitter` and `SseMiddleware` as invokable services and attaches a delegator that pushes `SseEmitter` onto the `EmitterStack` automatically.

### 2. Bootstrap the emitter stack

```php
// public/index.php
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// The delegator factory registered by ConfigProvider pushes SseEmitter on top.
// SapiEmitter must remain at the bottom to handle all non-SSE responses.
$stack = $container->get(EmitterStack::class);
$stack->push(new SapiEmitter());
```

### 3. Add the middleware (optional but recommended)

`SseMiddleware` validates the `Accept: text/event-stream` header and returns `406 Not Acceptable` for any request that does not include it. Wire it as a route-level or pipeline middleware.

```php
// config/pipeline.php  — applies globally, or scope it per-route
use Webware\SSE\SseMiddleware;

$app->pipe(SseMiddleware::class);
```

Or per route:

```php
// config/routes.php
use App\Handler\NotificationHandler;
use Webware\SSE\SseMiddleware;

$app->get('/events/notifications', [SseMiddleware::class, NotificationHandler::class], 'events.notifications');
```

---

## HTMX 2 notification example

This end-to-end example builds a live notification feed in a Mezzio application using the [htmx `sse` extension](https://htmx.org/extensions/sse/).

### The notification payload

Create a value object that implements `\JsonSerializable` for structured data, **or** pass a raw HTML string directly — htmx swaps HTML fragments without any client-side JavaScript.

```php
// src/Notification/Notification.php
namespace App\Notification;

use JsonSerializable;

final readonly class Notification implements JsonSerializable
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $level = 'info', // 'info' | 'warning' | 'error'
    ) {}

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'level'   => $this->level,
        ];
    }
}
```

### The SSE handler

Extend `AbstractSseHandler` and implement `stream()`. Call `$send(new Event(...))` for each push. The `event:` field becomes the htmx swap target name.

```php
// src/Handler/NotificationHandler.php
namespace App\Handler;

use App\Notification\Notification;
use App\Notification\NotificationQueue;
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;

use function connection_aborted;
use function sleep;

final class NotificationHandler extends AbstractSseHandler
{
    public function __construct(private readonly NotificationQueue $queue) {}

    protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void {
        // $lastEventId is non-null on reconnect — use it to replay missed events.
        if ($lastEventId !== null) {
            foreach ($this->queue->since($lastEventId) as $notification) {
                $send(new Event(
                    data:  $notification,
                    event: 'notification',
                    id:    $notification->id,
                ));
            }
        }

        // Stream live notifications until the client disconnects.
        while (true) {
            if (connection_aborted()) {
                break;
            }

            foreach ($this->queue->poll() as $notification) {
                $send(new Event(
                    data:  $notification,           // JsonSerializable — auto-encoded to JSON
                    event: 'notification',          // htmx listens on this event name
                    id:    $notification->id,       // enables Last-Event-ID reconnect
                    retry: 3000,                    // tell the browser to reconnect after 3s
                ));
            }

            sleep(1);
        }
    }
}
```

**Sending HTML fragments instead of JSON**

If you prefer to render server-side HTML and let htmx swap it directly, pass a string:

```php
$send(new Event(
    data:  '<li class="notification info"><strong>Deploy complete</strong></li>',
    event: 'notification',
));
```

### The handler factory

```php
// src/Handler/NotificationHandlerFactory.php
namespace App\Handler;

use App\Notification\NotificationQueue;
use Psr\Container\ContainerInterface;

final class NotificationHandlerFactory
{
    public function __invoke(ContainerInterface $container): NotificationHandler
    {
        return new NotificationHandler(
            $container->get(NotificationQueue::class),
        );
    }
}
```

Register it in your `ConfigProvider`:

```php
'factories' => [
    NotificationHandler::class => NotificationHandlerFactory::class,
],
```

### Route registration

```php
// config/routes.php
use App\Handler\NotificationHandler;
use Webware\SSE\SseMiddleware;

$app->get(
    '/events/notifications',
    [SseMiddleware::class, NotificationHandler::class],
    'events.notifications',
);
```

### The HTML page

Load the htmx `sse` extension, open a connection, and declare swap targets using the named event.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Notifications</title>
    <!-- htmx core -->
    <script src="https://unpkg.com/htmx.org@2"></script>
    <!-- htmx SSE extension -->
    <script src="https://unpkg.com/htmx-ext-sse@2"></script>
</head>
<body>

<!-- Open the SSE connection on this element -->
<div hx-ext="sse"
     sse-connect="/events/notifications">

    <!--
        sse-swap="notification" tells htmx to listen for SSE events
        named "notification" and swap their data into this element.

        If the server sends raw HTML, it is injected as-is (innerHTML by default).
        If the server sends JSON, use hx-on or a small script to render it.
    -->
    <ul id="notification-feed"
        sse-swap="notification"
        hx-swap="beforeend">
    </ul>

</div>

</body>
</html>
```

**How the swap works**

| Server sends | `data:` field | htmx behaviour |
|---|---|---|
| Raw HTML string | `<li>…</li>` | Injected directly into `#notification-feed` via `hx-swap="beforeend"` |
| JSON (via `JsonSerializable`) | `{"title":"…","level":"info","message":"…"}` | Available as the event body; use `hx-on::sse-message` to render |

**Rendering JSON payloads with `hx-on`**

```html
<ul id="notification-feed"
    sse-swap="notification"
    hx-swap="none"
    hx-on::sse-message="
        const n = JSON.parse(event.detail.data);
        const li = document.createElement('li');
        li.className = 'notification ' + n.level;
        li.innerHTML = '<strong>' + n.title + '</strong>: ' + n.message;
        document.getElementById('notification-feed').prepend(li);
    ">
</ul>
```

---

## Architecture

### `Event`

```
Event(
    data:  string|JsonSerializable,  // raw HTML or auto-JSON-encoded object
    event: ?string,                  // SSE "event:" field — htmx swap target name
    id:    ?string,                  // SSE "id:" field — sent back as Last-Event-ID on reconnect
    retry: ?int,                     // SSE "retry:" field in ms — browser reconnect delay
)
```

Wire format emitted for each event:

```
id: <id>
event: <event>
data: <data>
retry: <retry>

```

### `AbstractSseHandler`

`handle()` creates a `\Fiber` that runs `stream()`. The `$send` callable wraps `Fiber::suspend()`, so every call to `$send(new Event(...))` yields control to `SseEmitter` to write and flush before resuming your code. No blocking or output buffering tricks required.

### `SseEmitter`

Sits on top of the `EmitterStack`. For `SseResponse` instances it:

1. Flushes any open output buffers
2. Emits response headers via `header()`
3. Calls `$fiber->start()` then loops `$fiber->resume()` until termination
4. Writes each yielded `Event` in wire format and calls `flush()` immediately
5. Stops early on `connection_aborted()`

Returns `false` for every other response type, passing control down to `SapiEmitter`.

### `SseMiddleware`

Parses the `Accept` header (respects `;q=` quality factors and comma-separated lists). Returns `406 Not Acceptable` when `text/event-stream` is absent, protecting SSE routes from being hit by regular browser navigation.

### `ConfigProvider`

Registers with any PSR-11 container that understands the Laminas dependency config format:

| Key | Service | Registration |
|---|---|---|
| `invokables` | `SseEmitter` | No constructor dependencies |
| `invokables` | `SseMiddleware` | No constructor dependencies |
| `delegators` | `EmitterStack` | `SseEmitterDelegatorFactory` pushes `SseEmitter` on top |

---

## License

BSD-3-Clause — see [LICENSE](LICENSE).
