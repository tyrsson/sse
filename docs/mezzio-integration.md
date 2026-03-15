# Mezzio Integration

This guide walks through a complete integration of `webware/sse` into a
standard [Mezzio skeleton](https://github.com/mezzio/mezzio-skeleton)
application.

---

## Prerequisites

- A working Mezzio skeleton project
- `webware/sse` installed (`composer require webware/sse`)
- `laminas/laminas-servicemanager` (installed by the Mezzio skeleton)

---

## Step 1 — Register the ConfigProvider

Add `Webware\SSE\ConfigProvider` to the aggregator in `config/config.php`.
Place it **before** your application providers so that your local config can
override its defaults:

```php
// config/config.php
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Webware\SSE\ConfigProvider as SseConfigProvider;

$aggregator = new ConfigAggregator([
    \Laminas\Diactoros\ConfigProvider::class,
    \Mezzio\ConfigProvider::class,
    \Mezzio\Router\FastRoute\ConfigProvider::class,

    // SSE package — registers SseEmitter and SseMiddleware services
    SseConfigProvider::class,

    new PhpFileProvider('config/autoload/*.global.php'),
    new PhpFileProvider('config/autoload/*.local.php'),
    new ArrayProvider([ConfigAggregator::ENABLE_CACHE => true]),
], 'data/config-cache.php');

return $aggregator->getMergedConfig();
```

---

## Step 2 — Override SSE configuration (optional)

Create a global config file to tune the package defaults:

```php
// config/autoload/sse.global.php
return [
    'webware_sse' => [
        'retry' => 5000,  // ms before browser reconnects
    ],
];
```

---

## Step 3 — Update `public/index.php`

Replace the standard single-emitter setup with an `EmitterStack` that puts
`SseEmitter` first:

```php
<?php

declare(strict_types=1);

use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Webware\SSE\SseEmitter;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

/**
 * Self-called anonymous function to avoid polluting the global scope.
 */
(static function (): void {
    $container = require 'config/container.php';

    /** @var \Mezzio\Application $app */
    $app = $container->get(\Mezzio\Application::class);

    (require 'config/pipeline.php')($app, $container);
    (require 'config/routes.php')($app, $container);

    $request = $container->get(\Psr\Http\Message\ServerRequestInterface::class);

    $stack = new EmitterStack();
    $stack->push(new SapiEmitter());
    $stack->push($container->get(SseEmitter::class));

    $stack->emit($app->handle($request));
})();
```

---

## Step 4 — Add the middleware to the pipeline (optional)

If you want `Last-Event-ID` injected as a request attribute across *all* SSE
routes, pipe `SseMiddleware` in `config/pipeline.php`.  It passes non-SSE
requests straight through, so it is safe to pipe globally:

```php
// config/pipeline.php
use Webware\SSE\SseMiddleware;

return static function (\Mezzio\Application $app, \Psr\Container\ContainerInterface $container): void {
    $app->pipe(\Mezzio\Handler\NotFoundHandler::class);
    $app->pipe(\Mezzio\Router\Middleware\RouteMiddleware::class);

    // Inject Last-Event-ID attribute for downstream SSE handlers
    $app->pipe(SseMiddleware::class);

    $app->pipe(\Mezzio\Router\Middleware\DispatchMiddleware::class);
};
```

---

## Step 5 — Create a handler

```php
// src/App/Handler/NotificationHandler.php
<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;

final class NotificationHandler extends AbstractSseHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly int $retryMs,
    ) {}

    protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void {
        $userId = $request->getAttribute('userId');  // set by auth middleware
        $cursor = $lastEventId ?? '0';

        while (true) {
            $items = $this->notifications->unreadSince($cursor, $userId);

            foreach ($items as $item) {
                $cursor = $item->id;
                $send(new Event(
                    data:  json_encode($item, JSON_THROW_ON_ERROR),
                    event: 'notification',
                    id:    $cursor,
                    retry: $this->retryMs,
                ));
            }

            if (connection_aborted()) {
                break;
            }

            sleep(2);
        }
    }
}
```

---

## Step 6 — Register the handler's factory

```php
// src/App/Handler/NotificationHandlerFactory.php
<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Container\ContainerInterface;

final class NotificationHandlerFactory
{
    public function __invoke(ContainerInterface $container): NotificationHandler
    {
        return new NotificationHandler(
            $container->get(NotificationRepository::class),
            $container->get('config')['webware_sse']['retry'],
        );
    }
}
```

```php
// config/autoload/dependencies.global.php
return [
    'dependencies' => [
        'factories' => [
            \App\Handler\NotificationHandler::class
                => \App\Handler\NotificationHandlerFactory::class,
        ],
    ],
];
```

---

## Step 7 — Register the route

```php
// config/routes.php
return static function (\Mezzio\Application $app, \Psr\Container\ContainerInterface $container): void {
    $app->get('/events/notifications', \App\Handler\NotificationHandler::class);
};
```

---

## Step 8 — Subscribe from the browser

```html
<!DOCTYPE html>
<html>
<head><title>Notifications</title></head>
<body>
<ul id="feed"></ul>
<script>
const source = new EventSource('/events/notifications');

source.addEventListener('notification', (e) => {
    const item = JSON.parse(e.data);
    const li   = document.createElement('li');
    li.textContent = item.message;
    document.getElementById('feed').prepend(li);
});

source.addEventListener('error', () => {
    console.warn('SSE connection lost, browser will reconnect automatically');
});
</script>
</body>
</html>
```

The browser's `EventSource` automatically reconnects after a dropped connection
and sends the id of the last event it received (`Last-Event-ID` header).
`AbstractSseHandler` extracts this value and passes it to `stream()` as
`$lastEventId`, so the handler can resume from where it left off.

---

## Quick-reference: files changed

| File | Change |
|---|---|
| `config/config.php` | Add `Webware\SSE\ConfigProvider::class` to aggregator |
| `config/autoload/sse.global.php` | *(new)* Optional config overrides |
| `public/index.php` | Replace single `SapiEmitter` with `EmitterStack` + `SseEmitter` |
| `config/pipeline.php` | *(optional)* Pipe `SseMiddleware::class` |
| `config/routes.php` | Add SSE route(s) |
| `src/App/Handler/NotificationHandler.php` | *(new)* Your `AbstractSseHandler` subclass |
| `src/App/Handler/NotificationHandlerFactory.php` | *(new)* Factory for the handler |
| `config/autoload/dependencies.global.php` | Register handler factory |

---

## CORS

If your Mezzio app and your front-end are on different origins, the browser
will block `EventSource` connections unless the server sends appropriate CORS
headers.  Add the header by overriding `handle()` in your handler:

```php
// In your handler:
public function handle(ServerRequestInterface $request): ResponseInterface
{
    return parent::handle($request)
        ->withHeader('Access-Control-Allow-Origin', 'https://your-frontend.example.com');
}
```

Or create a shared base class to apply CORS across all SSE handlers:

```php
abstract class AppSseHandler extends AbstractSseHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return parent::handle($request)
            ->withHeader('Access-Control-Allow-Origin', '*');
    }
}
```

---

## Authentication

SSE connections are regular HTTP GET requests, so your existing authentication
middleware works without modification.  If the middleware returns a redirect or
`401` before the SSE handler runs, the stream never starts and the browser
receives a normal HTTP response.

> **Note:** The browser's `EventSource` does not send cookies cross-origin by
> default.  For authenticated SSE endpoints on a different origin, use a
> signed-query-string token or the `withCredentials: true` option (which
> requires `Access-Control-Allow-Credentials: true` and a non-wildcard
> `Access-Control-Allow-Origin`).

---

← [Back to README](../README.md)
