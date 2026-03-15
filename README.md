# webware/sse

A Server-Sent Events (SSE) component for [Mezzio](https://docs.mezzio.dev/) applications built on the Laminas ecosystem.

- **PSR-7 native** — `SseResponse` extends `Laminas\Diactoros\Response`
- **EmitterStack-aware** — `SseEmitter` implements `EmitterInterface` and returns `false` for non-SSE responses, letting `SapiEmitter` handle the rest
- **PSR-15 ready** — abstract handler base class and dual-mode middleware
- **Callback-based streaming** — implement `stream(ServerRequestInterface $request, callable $send, ?string $lastEventId): void` and call `$send(new Event(...))` to push events
- **Reconnection support** — `Last-Event-ID` header extracted and forwarded automatically
- **htmx v2 compatible** — wire format and named events work directly with htmx's `sse` extension
- **PSR-11 container** — `ConfigProvider` registers services with any PSR-11 container; `laminas-servicemanager` is optional

---

## Quick Start

### 1. Install

```bash
composer require webware/sse
```

### 2. Register the ConfigProvider

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\SSE\ConfigProvider;

$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // your other providers …
]);
```

### 3. Push SseEmitter onto the EmitterStack

```php
// public/index.php (or wherever your Mezzio bootstrap lives)
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Webware\SSE\SseEmitter;

$stack = new EmitterStack();
$stack->push(new SapiEmitter());                    // handles regular responses
$stack->push($container->get(SseEmitter::class));   // handles SseResponse first
```

### 4. Create an event handler

```php
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;

final class TimeHandler extends AbstractSseHandler
{
    protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void {
        while (true) {
            $send(new Event(data: date('H:i:s'), event: 'tick'));
            if (connection_aborted()) {
                break;
            }
            sleep(1);
        }
    }
}
```

### 5. Route it

```php
// config/routes.php
$app->get('/events/time', TimeHandler::class);
```

## Documentation

| Topic | Description |
|---|---|
| [Installation](docs/installation.md) | Requirements, Composer install, optional packages |
| [Configuration](docs/configuration.md) | `ConfigProvider`, `webware_sse` config keys, overriding defaults |
| [Events](docs/events.md) | `Event`, `EventInterface`, named events, multi-line data, retry |
| [SseResponse](docs/sse-response.md) | PSR-7 response object, default headers, custom headers |
| [SseEmitter](docs/emitter.md) | `EmitterStack` setup, heartbeat, connection abort handling |
| [AbstractSseHandler](docs/handler.md) | PSR-15 handler base class, `Last-Event-ID`, reconnection |
| [SseMiddleware](docs/middleware.md) | Preprocessor mode, terminal factory mode |
| [Mezzio Integration](docs/mezzio-integration.md) | Full end-to-end guide for Mezzio applications |
| [JavaScript Client](docs/js-client.md) | Browser / Node SSE client with reconnect, typed events, and polyfill |

---

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- `laminas/laminas-diactoros` ^3.0
- `laminas/laminas-httphandlerrunner` ^2.0

## License

BSD-3-Clause. See [LICENSE](LICENSE).
