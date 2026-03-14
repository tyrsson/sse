# SseEmitter

## Overview

`Webware\SSE\SseEmitter` implements
`Laminas\HttpHandlerRunner\Emitter\EmitterInterface` and is designed to sit on a
`Laminas\HttpHandlerRunner\Emitter\EmitterStack` alongside the standard
`SapiEmitter`.

When the application pipeline returns a regular PSR-7 response, `SseEmitter`
returns `false` immediately and the `EmitterStack` falls through to the next
emitter (`SapiEmitter`).  When the pipeline returns an `SseResponse`,
`SseEmitter` takes full ownership: it emits headers, iterates the generator,
flushes each event to the client, and returns `true`.

---

## Setting up the EmitterStack

The recommended stack has `SapiEmitter` at the bottom and `SseEmitter` pushed
on top so it is tried first:

```php
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Webware\SSE\SseEmitter;

$stack = new EmitterStack();
$stack->push(new SapiEmitter());                    // tried second (fallback)
$stack->push($container->get(SseEmitter::class));   // tried first
```

`EmitterStack::emit()` calls each emitter in LIFO order until one returns
`true`.  `SseEmitter` returns `false` for anything that is not an
`SseResponse`, so normal responses are still handled by `SapiEmitter`.

### Wiring in `public/index.php`

A typical Mezzio `public/index.php` looks like this once SSE is added:

```php
<?php

declare(strict_types=1);

use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Webware\SSE\SseEmitter;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$container = require 'config/container.php';
$app       = $container->get(\Mezzio\Application::class);

(require 'config/pipeline.php')($app, $container);
(require 'config/routes.php')($app, $container);

$stack = new EmitterStack();
$stack->push(new SapiEmitter());
$stack->push($container->get(SseEmitter::class));

$stack->emit(
    $app->handle($container->get(\Psr\Http\Message\ServerRequestInterface::class))
);
```

---

## Heartbeat / keep-alive

Network proxies, load balancers, and some browsers close idle HTTP connections
after a timeout.  `SseEmitter` addresses this by sending an SSE comment frame
whenever the configured `heartbeat_interval` elapses without a real event:

```
: heartbeat

```

Comment frames are part of the SSE specification and are invisible to
JavaScript `EventSource` event listeners.  They exist solely to keep the TCP
connection alive.

### How the heartbeat is triggered

The generator signals that it has no new event by yielding `null`:

```php
while (true) {
    $events = $this->poll();

    if (empty($events)) {
        yield null;     // <-- emitter checks the heartbeat interval here
        sleep(1);
        continue;
    }

    foreach ($events as $e) {
        yield new Event(data: $e->payload, id: $e->id);
    }
}
```

The emitter tracks the time of the last flushed frame.  When a `null` is
received and `time() - $lastActivity >= $heartbeatInterval`, it sends the
comment and resets the timer.

### Configuring the interval

Set `heartbeat_interval` under the `webware_sse` config key:

```php
// config/autoload/sse.global.php
return [
    'webware_sse' => [
        'heartbeat_interval' => 20,  // seconds (default: 15)
    ],
];
```

See [Configuration](configuration.md) for the full reference.

---

## Connection abort detection

`SseEmitter` calls `ignore_user_abort(true)` so that PHP does not throw an
exception when the client disconnects.  Instead, it checks
`connection_aborted()` after each frame and breaks out of the loop cleanly.

This means the generator's `finally` block — if any — **is** executed when the
client disconnects:

```php
protected function stream(ServerRequestInterface $request, ?string $lastId): Generator
{
    $this->lock->acquire();

    try {
        while (true) {
            yield new Event(data: $this->poll());
            yield null;
            sleep(1);
        }
    } finally {
        $this->lock->release();   // always runs, even on disconnect
    }
}
```

---

## Output buffering

Before emitting headers or events, `SseEmitter` drains all active output
buffers:

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

This ensures that no buffered output (e.g. from debug toolbars or framework
internals) is inadvertently sent before — or mixed with — the SSE stream.

---

## Error: "headers already sent"

If any output has been sent before `SseEmitter::emit()` is called, it throws a
`\RuntimeException`:

```
Unable to emit SSE response: headers already sent in /path/to/file.php on line N.
```

Common causes:

- A `var_dump()`, `echo`, or whitespace before `<?php` in a config/bootstrap file
- A debug toolbar or error handler that writes to output before the emitter runs
- `output_buffering = On` is **not** the culprit here — `SseEmitter` drains buffers
  cleanly; the issue is a `headers_sent()` state caused by actual byte output

---

## Constructor

```php
public function __construct(array $config = [])
```

`$config` is the full application config array — the same value returned by
`$container->get('config')`.  `SseEmitterFactory` handles this automatically
when the service is resolved from the container.

Manual construction (e.g. in tests):

```php
$emitter = new SseEmitter([
    'webware_sse' => ['heartbeat_interval' => 30],
]);
```

---

← [Back to README](../README.md)
