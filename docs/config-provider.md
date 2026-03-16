# ConfigProvider API

`Webware\SSE\ConfigProvider`

---

```
namespace Webware\SSE;

final readonly class ConfigProvider
```

The single entry point for wiring `webware/sse` into a Mezzio application.  
Returns a merged configuration array understood by `laminas/laminas-config-aggregator` and any PSR-11 container that speaks the Laminas dependency config format.

---

## `__invoke(): array`

Returns the complete package configuration:

```php
[
    'dependencies' => [...],   // PSR-11 container service definitions
    'router'       => [...],   // route-provider declarations
    'templates'    => [...],   // Laminas View template paths and map entries
]
```

Register it via `laminas-config-aggregator`:

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\SSE\ConfigProvider;

return (new ConfigAggregator([
    ConfigProvider::class,
    // your other providers …
]))->getMergedConfig();
```

---

## `getDependencies(): array`

### Factories

| Service | Factory |
|---|---|
| `NotificationHandler::class` | `Container\NotificationHandlerFactory` |
| `RouteProvider::class` | `Container\RouteProviderFactory` |

### Delegators

| Service | Delegator |
|---|---|
| `Laminas\HttpHandlerRunner\Emitter\EmitterStack` | `EmitterStackDelegatorFactory` |

The delegator pushes `SseEmitter` onto the top of the `EmitterStack` so that `SseResponse` instances are intercepted before `SapiEmitter` processes them.

> **Note:** `SseEmitter` itself must be available in the container. Register it as an invokable or add a factory entry for `SseEmitter::class → SseEmitterFactory::class` to your application's own config.

---

## `getRouteProviders(): array`

```php
[
    'route-providers' => [
        RouteProvider::class,
    ],
]
```

Declares `RouteProvider` so that `RouteProviderInterface`-aware bootstrap code can auto-register the `/notifications` route. See [route-provider.md](route-provider.md).

---

## `getTemplates(): array`

### Map entries

| Key | File |
|---|---|
| `sse::info` | `templates/sse/info.phtml` |
| `sse::message` | `templates/sse/message.phtml` |

### Path entries

| Namespace | Directory |
|---|---|
| `sse` | `templates/sse/` |
| `error` | `templates/error/` |

Template files are resolved relative to the package root (`__DIR__ . '/../templates/…'`).

See [templates.md](templates.md) for template variable reference.
