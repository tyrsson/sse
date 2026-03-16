# NotificationHandler API

`Webware\SSE\NotificationHandler` · `Webware\SSE\Container\NotificationHandlerFactory`

---

## `NotificationHandler`

```
namespace Webware\SSE;

final class NotificationHandler implements Psr\Http\Server\RequestHandlerInterface
```

A concrete PSR-15 request handler that converts a queued message set (provided by `Axleus\Message\SystemMessengerInterface`) into an SSE stream of HTML-fragment events.

Each message is rendered through a Laminas View `Partial` template and emitted as a named `Event` whose `event:` field equals the message level.

### Constructor

```php
public function __construct(
    private readonly Laminas\View\Helper\Partial $partialHelper,
)
```

| Parameter | Type | Description |
|---|---|---|
| `$partialHelper` | `Laminas\View\Helper\Partial` | Laminas View partial helper used to render each notification into an HTML fragment. |

### `handle(ServerRequestInterface $request): ResponseInterface`

1. Reads `Last-Event-ID` from the request header (`ID`).
2. Retrieves the `SystemMessengerInterface` instance from the request attribute keyed by `SystemMessengerInterface::class`.
3. Iterates `$messenger->getMessages()`, keyed by message level.
4. For each message, renders the partial `sse::<level>` (e.g. `sse::info`, `sse::warning`, `sse::error`) with `['level' => $level, 'message' => $message]`.
5. Concatenates each rendered fragment as a named `Event` (event type = level).
6. Returns an `SseResponse` containing the full concatenated stream string.

#### Request attribute

| Attribute key | Type | Description |
|---|---|---|
| `SystemMessengerInterface::class` | `SystemMessengerInterface` | The message bag populated by upstream middleware (e.g. `MessageMiddleware`). |

#### Response

Returns `SseResponse` with `Content-Type: text/event-stream`.  
Each event block in the body has the form:

```
event: <level>
data: <rendered HTML fragment>

```

---

## Template contract

The partial helper renders view scripts registered under the `sse` template namespace.  
See [templates.md](templates.md) for the full variable reference.

| Template key | Rendered for |
|---|---|
| `sse::info` | Messages with level `info` |
| `sse::message` | Messages with level `message` |
| `sse::warning` | Messages with level `warning` |
| `sse::error` | Messages with level `error` |

---

## `NotificationHandlerFactory`

```
namespace Webware\SSE\Container;

final class NotificationHandlerFactory
```

PSR-11 / laminas-servicemanager factory. Registered automatically by `ConfigProvider`.

### `__invoke(ContainerInterface $container): NotificationHandler`

1. Retrieves `Laminas\View\HelperPluginManager` from the container.
2. Calls `->get(Partial::class)` on the plugin manager.
3. Returns `new NotificationHandler($partial)`.

---

## HTMX integration

The `/notifications` route wired by `RouteProvider` is designed to be polled by an htmx `sse-connect` attribute.

```html
<div hx-ext="sse" sse-connect="/notifications">
    <div sse-swap="info"    hx-swap="beforeend"></div>
    <div sse-swap="warning" hx-swap="beforeend"></div>
    <div sse-swap="error"   hx-swap="beforeend"></div>
</div>
```

htmx routes each arriving event to the swap target whose `sse-swap` value matches the event's `event:` field.
