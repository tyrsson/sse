# SseEmitter

## Overview

`Webware\SSE\SseEmitter` implements
`Laminas\HttpHandlerRunner\Emitter\EmitterInterface` and is designed to sit on a
`Laminas\HttpHandlerRunner\Emitter\EmitterStack` alongside the standard
`SapiEmitter`.

When the application pipeline returns a regular PSR-7 response, `SseEmitter`
returns `false` immediately and the `EmitterStack` falls through to the next
emitter (`SapiEmitter`).  When the pipeline returns an `SseResponse`,
`SseEmitter` takes full ownership: it emits headers, invokes the stream
callable, flushes each event to the client, and returns `true`.

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

## Keep-alive

Network proxies and load balancers can close idle HTTP connections if no data
is sent for a while.  Because `SseEmitter` simply invokes your stream callable
and flushes each `EventInterface` as you call `$send()`, keeping the connection
alive is your responsibility.

The recommended pattern is to send an SSE comment frame periodically when
there are no real events.  Comment frames are invisible to JavaScript
`EventSource` event listeners:

```php
use Webware\SSE\Event;
use Webware\SSE\RawEvent;

protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    $lastPing = time();

    while (true) {
        $events = $this->poll();

        foreach ($events as $e) {
            $send(new Event(data: $e->payload, id: $e->id));
            $lastPing = time();
        }

        // Send a comment frame every 15 seconds when idle
        if (time() - $lastPing >= 15) {
            echo ": heartbeat\n\n";
            flush();
            $lastPing = time();
        }

        if (connection_aborted()) {
            break;
        }

        sleep(1);
    }
}
```

---

## Connection abort detection

`SseEmitter` calls `ignore_user_abort(true)` so that PHP does not throw an
exception when the client disconnects.  Instead, your `stream()` implementation
should check `connection_aborted()` after each event (or each poll cycle) and
return cleanly:

```php
protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    $this->lock->acquire();

    try {
        while (true) {
            $send(new Event(data: $this->poll()));

            if (connection_aborted()) {
                break;
            }

            sleep(1);
        }
    } finally {
        $this->lock->release();   // always runs, even on disconnect
    }
}
```

The `try/finally` block guarantees that cleanup code (releasing locks, closing
cursors, etc.) runs regardless of how `stream()` exits.

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
public function __construct()
```

`SseEmitter` takes no constructor arguments.  Resolve it from the container or
construct it directly:

```php
// From the container (recommended):
$emitter = $container->get(\Webware\SSE\SseEmitter::class);

// Manual construction (e.g. in tests):
$emitter = new SseEmitter();
```

---

← [Back to README](../README.md)
