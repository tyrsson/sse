# RouteProvider API

`Webware\SSE\RouteProvider` · `Webware\SSE\Container\RouteProviderFactory`

---

## `RouteProvider`

```
namespace Webware\SSE;

final class RouteProvider implements Mezzio\Router\RouteProviderInterface
```

Registers the SSE notification endpoint with the Mezzio router.

### `registerRoutes(RouteCollectorInterface $routeCollector, MiddlewareFactoryInterface $middlewareFactory): void`

Registers the following route:

| Method | Path | Name |
|---|---|---|
| `GET` | `/notifications` | `notifications` |

**Middleware pipeline for this route (in order):**

| Position | Middleware | Purpose |
|---|---|---|
| 1 | `Mezzio\Session\SessionMiddleware` | Initialises the session so that message middleware can read session-stored messages. |
| 2 | `Axleus\Message\Middleware\MessageMiddleware` | Populates a `SystemMessengerInterface` instance and attaches it to the request as an attribute. |
| 3 | `NotificationHandler` | Reads messages, renders partials, returns the `SseResponse`. |

---

## `RouteProviderFactory`

```
namespace Webware\SSE\Container;

final readonly class RouteProviderFactory
```

PSR-11 factory. Registered automatically by `ConfigProvider`.

### `__invoke(ContainerInterface $container): RouteProvider`

Returns `new RouteProvider()`. `RouteProvider` has no constructor dependencies.

---

## Registering route providers in Mezzio

`ConfigProvider` declares `RouteProvider::class` under the `router.route-providers` config key.  
If your Mezzio application uses `RouteProviderInterface`-aware bootstrapping, no further action is needed.

Manual registration:

```php
// config/routes.php
use Webware\SSE\RouteProvider;

/** @var Mezzio\Application $app */
/** @var Mezzio\MiddlewareFactoryInterface $factory */
(new RouteProvider())->registerRoutes($app, $factory);
```
