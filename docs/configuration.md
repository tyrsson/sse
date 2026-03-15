# Configuration

## ConfigProvider

`Webware\SSE\ConfigProvider` is a standard Mezzio / laminas-config-aggregator
config provider.  Register it once in your config aggregator and all services
are available in the container automatically.

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\SSE\ConfigProvider;

$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // other providers …
    new PhpFileProvider('config/autoload/*.global.php'),
    new PhpFileProvider('config/autoload/*.local.php'),
]);

return $aggregator->getMergedConfig();
```

### What the ConfigProvider registers

| Service | Factory | Description |
|---|---|---|
| `Webware\SSE\SseEmitter` | `SseEmitterFactory` | The SSE-aware emitter; reads `webware_sse` config |
| `Webware\SSE\SseMiddleware` | `SseMiddlewareFactory` | Preprocessor-mode middleware |

---

## `webware_sse` configuration keys

All package configuration lives under the top-level `webware_sse` key.

```php
// config/autoload/sse.global.php
return [
    'webware_sse' => [
        'retry' => 3000,  // milliseconds — default
    ],
];
```

### `retry`

| Type | Default | Unit |
|---|---|---|
| `int` (> 0) | `3000` | milliseconds |

This value is **informational only** — the package itself does not use it
directly.  It is intended to be read by application code and passed to
`Event` objects so the browser's `EventSource` knows how long to wait before
reconnecting after a dropped connection:

```php
$retry = $config['webware_sse']['retry'];   // 3000 ms

$send(new Event(data: $payload, retry: $retry));
```

**Override example** — shorten reconnect time to 1 second for a real-time feed:

```php
return [
    'webware_sse' => [
        'retry' => 1000,
    ],
];
```

---

## Registering services without laminas-servicemanager

If your application uses a PSR-11 container other than laminas-servicemanager,
register the two services manually:

```php
// Using any PSR-11 container that understands a factory map
$container->bind(SseEmitter::class, function ($c) {
    return new SseEmitter();
});

$container->bind(SseMiddleware::class, function ($c) {
    return new SseMiddleware(); // preprocessor mode
});
```

The shape of the `dependencies.factories` array returned by
`ConfigProvider::getDependencies()` is the standard Mezzio convention and is
understood by any compatible container (e.g. Aura.Di, PHP-DI with a bridge).

---

## Full configuration reference

```php
return [
    'webware_sse' => [

        // Milliseconds the browser EventSource waits before reconnecting.
        // Informational — read this in your handler and pass to Event::$retry.
        'retry' => 3000,

    ],
];
```

---

← [Back to README](../README.md)
