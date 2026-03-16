# webware/sse

A Server-Sent Events (SSE) notification component for [Mezzio](https://docs.mezzio.dev/) applications built on the Laminas ecosystem.

- **PSR-7 native** — `SseResponse` extends `Laminas\Diactoros\Response`
- **EmitterStack-aware** — `SseEmitter` implements `EmitterInterface` and returns `false` for non-SSE responses, letting `SapiEmitter` handle the rest
- **PSR-15 request handler** — `NotificationHandler` reads queued messages and streams them as named SSE events
- **Laminas View integration** — each message is rendered through a configurable `.phtml` partial before being sent
- **htmx v2 compatible** — named events map directly to `sse-swap` targets in htmx's `sse` extension
- **Mezzio router integration** — `RouteProvider` registers `/notifications` and its middleware pipeline automatically
- **PSR-11 container** — `ConfigProvider` wires all services; `laminas-servicemanager` is required at runtime

---

## Requirements

- PHP 8.2–8.5
- `laminas/laminas-diactoros` ^3.0
- `laminas/laminas-httphandlerrunner` ^2.0
- `laminas/laminas-servicemanager` ^4.0
- `laminas/laminas-view`
- `axleus/message` (for `SystemMessengerInterface` and `MessageMiddleware`)
- `mezzio/mezzio` (for `RouteProviderInterface`)

---

## Quick Start

### 1. Install

```bash
composer require webware/sse
```

### 2. Register `ConfigProvider`

Add `Webware\SSE\ConfigProvider` to your config aggregator. This registers all factories, the `EmitterStack` delegator, the route provider, and the view templates.

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\SSE\ConfigProvider;

return (new ConfigAggregator([
    ConfigProvider::class,
    // your other providers …
]))->getMergedConfig();
```

### 3. Ensure `SapiEmitter` is on the stack (optional advanced usage)

`ConfigProvider` registers a delegator that pushes `SseEmitter` onto the `EmitterStack`. You must still push `SapiEmitter` below it so that ordinary responses are handled:

```php
// register the delegator factory in ConfigProvider
    public function getDependencies(): array
    {
        return [
            'delegators' => [
                EmitterInterface::class => [
                    Container\EmitterStackDelegatorFactory::class,
                ],
            ],
            //....
        ];
    }
```

### 4. Register routes

`ConfigProvider` declares `RouteProvider` under `router.route-providers`. If your Mezzio bootstrap processes that key automatically, no extra step is needed.  
For manual registration:

```php
// config/routes.php
use Webware\SSE\RouteProvider;

(new RouteProvider())->registerRoutes($app, $factory);
```

This registers `GET /notifications` → `SessionMiddleware → MessageMiddleware → NotificationHandler`.

### 5. Wire up the HTML page with htmx

Include the htmx `sse` extension and connect to the `/notifications` endpoint. Each incoming SSE event is swapped into the matching `sse-swap` target:

```html
<script src="https://unpkg.com/htmx.org@2"></script>
<script src="https://unpkg.com/htmx-ext-sse@2"></script>

<div hx-ext="sse" sse-connect="/notifications">
    <div id="notifications-info"    sse-swap="info"    hx-swap="beforeend"></div>
    <div id="notifications-warning" sse-swap="warning" hx-swap="beforeend"></div>
    <div id="notifications-error"   sse-swap="error"   hx-swap="beforeend"></div>
    <div id="notifications-message" sse-swap="message" hx-swap="beforeend"></div>
</div>
```

When `NotificationHandler` processes a request it renders each queued message through its corresponding `sse::<level>` partial and emits the HTML fragment as a named SSE event. htmx routes the event to the matching swap target.

### 6. (Optional) Emit an event manually

If you need to emit a one-off SSE response from your own handler:

```php
use Webware\SSE\Event;
use Webware\SSE\SseResponse;

$event = new Event(
    data:  '<article class="notification info">Deploy complete.</article>',
    event: 'info',
    id:    'deploy-123',
);

return new SseResponse($event);
```

---

## Documentation

| Document | Description |
|---|---|
| [docs/event.md](docs/event.md) | `EventInterface` contract and `Event` value object — constructor, constants, wire-format output |
| [docs/sse-response.md](docs/sse-response.md) | `SseResponse` — accepted body types, default headers |
| [docs/sse-emitter.md](docs/sse-emitter.md) | `SseEmitter`, `SseEmitterFactory`, `EmitterStackDelegatorFactory`, stack ordering |
| [docs/notification-handler.md](docs/notification-handler.md) | `NotificationHandler` request flow, request attributes, `NotificationHandlerFactory` |
| [docs/route-provider.md](docs/route-provider.md) | `RouteProvider` middleware pipeline, `RouteProviderFactory` |
| [docs/config-provider.md](docs/config-provider.md) | `ConfigProvider` — full config key reference for dependencies, router, and templates |
| [docs/templates.md](docs/templates.md) | Template keys, available variables, overriding defaults |

---

## License

BSD-3-Clause. See [LICENSE](LICENSE).
