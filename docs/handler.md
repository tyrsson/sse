# AbstractSseHandler

## Overview

`Webware\SSE\AbstractSseHandler` is a PSR-15 `RequestHandlerInterface`
base class that makes it easy to build SSE endpoints as dedicated handler
classes.

Extend it, implement the `stream()` method, and return it from a route.  The
base class takes care of:

- Extracting the `Last-Event-ID` request header (reconnection support)
- Wrapping your stream callable in an `SseResponse`
- Normalising an empty `Last-Event-ID` value to `null`

---

## Class synopsis

```php
namespace Webware\SSE;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractSseHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;

    abstract protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void;
}
```

---

## Implementing a handler

Create a final class that extends `AbstractSseHandler` and implement the
`stream()` method.  Call `$send` with each `EventInterface` instance to push
it to the connected client.  Return (or let execution fall off the end) to
close the stream.  Check `connection_aborted()` inside loops to detect a
disconnected client.

```php
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;

final class LiveScoreHandler extends AbstractSseHandler
{
    public function __construct(
        private readonly ScoreRepository $scores,
    ) {}

    protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void {
        $cursor = $lastEventId ?? '0';

        while (true) {
            $updates = $this->scores->getUpdatesSince($cursor);

            foreach ($updates as $update) {
                $cursor = $update->id;
                $send(new Event(
                    data:  json_encode($update, JSON_THROW_ON_ERROR),
                    event: 'score-update',
                    id:    $cursor,
                ));
            }

            if (connection_aborted()) {
                break;
            }

            sleep(1);
        }
    }
}
```

## Reconnection and `Last-Event-ID`

The SSE specification defines a reconnection mechanism:

1. The server assigns an `id` to each event.
2. The browser's `EventSource` stores the last received id.
3. When the connection drops, `EventSource` automatically reconnects and sends
   the stored id in a `Last-Event-ID` HTTP request header.
4. The server uses that id to resume from where it left off — no events are
   missed.

`AbstractSseHandler::handle()` extracts the `Last-Event-ID` header and
normalises an empty string to `null`:

```php
$lastEventId = $request->getHeaderLine('Last-Event-ID');
$lastEventId = $lastEventId !== '' ? $lastEventId : null;
```

The normalised value is forwarded to your `stream()` implementation as
`$lastEventId`.  **Use it as a cursor** into your data source:

```php
protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    // On first connect $lastEventId is null — start from the beginning.
    // On reconnect $lastEventId holds the id the client last saw.
    $cursor = $lastEventId ?? '0';

    while (true) {
        foreach ($this->repo->getEventsSince($cursor) as $event) {
            $cursor = $event->id;
            $send(new Event(data: $event->payload, id: $cursor));
        }

        if (connection_aborted()) {
            break;
        }

        sleep(1);
    }
}
```

Assign meaningful, **monotonically increasing** ids (database auto-increment
values, UUIDs ordered by creation time, or millisecond timestamps) so that
"events since `$lastEventId`" is an unambiguous query.

---

## Injecting dependencies

`AbstractSseHandler` has no constructor of its own, so subclasses can declare
any constructor they need.  Register the handler with the container via a
factory so its dependencies are injected:

```php
// src/LiveScoreHandlerFactory.php
use Psr\Container\ContainerInterface;

final class LiveScoreHandlerFactory
{
    public function __invoke(ContainerInterface $container): LiveScoreHandler
    {
        return new LiveScoreHandler(
            $container->get(ScoreRepository::class),
        );
    }
}
```

```php
// config/autoload/dependencies.global.php
return [
    'dependencies' => [
        'factories' => [
            LiveScoreHandler::class => LiveScoreHandlerFactory::class,
        ],
    ],
];
```

---

## Accessing request data

The full PSR-7 `ServerRequestInterface` is passed to `stream()`.  Use it to
read route parameters, query string values, or request attributes set by
upstream middleware:

```php
protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    // Read a route parameter (e.g. /events/match/{matchId})
    $matchId = $request->getAttribute('matchId');

    // Read a query parameter (e.g. /events?filter=live)
    $filter = $request->getQueryParams()['filter'] ?? 'all';

    // Read an attribute set by SseMiddleware preprocessor
    // (same as $lastEventId but accessed from the attribute bag)
    $fromId = $request->getAttribute(\Webware\SSE\SseMiddleware::LAST_EVENT_ID);

    while (true) {
        $send(new Event(data: $this->query($matchId, $filter, $fromId)));

        if (connection_aborted()) {
            break;
        }

        sleep(1);
    }
}
```

---

## Ending the stream

Returning from `stream()` closes the PHP connection cleanly.  However, the
browser's `EventSource` will automatically reconnect after the `retry` timeout
— it always does unless client JavaScript explicitly calls `source.close()`.

To tell the client not to reconnect, send a `CloseEvent` before returning.  It
transmits an SSE block with the named event `stream-close`.  Add a single
listener in your client JavaScript that calls `source.close()` when it receives
that event:

```js
// Add this once when you open the connection
source.addEventListener('stream-close', () => source.close());
```

```php
use Webware\SSE\CloseEvent;
use Webware\SSE\Event;

protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    // Finite stream: send a fixed number of events then close
    for ($i = 1; $i <= 10; $i++) {
        $send(new Event(data: "step $i of 10", id: (string) $i, event: 'progress'));
        sleep(1);
    }

    $send(new Event(data: 'done', event: 'complete'));

    // Signal the client to close and stop reconnecting
    $send(new CloseEvent());
    // stream() returns here — PHP closes the connection
}
```

If the stream is infinite (e.g. a live feed) and ends only because the client
disconnected, there is no need to send `CloseEvent` — `connection_aborted()`
will be `true` and there is no client to notify.

---

## Error handling inside the stream

Unhandled exceptions that bubble out of `stream()` will propagate into
`SseEmitter::streamEvents()` and ultimately into the PHP error handler.
Because headers have already been sent at that point, a regular error response
cannot be issued.

Recommended approach: catch exceptions inside `stream()` and send an error
event, then return:

```php
protected function stream(
    ServerRequestInterface $request,
    callable $send,
    ?string $lastEventId,
): void {
    try {
        while (true) {
            $send(new Event(data: $this->fetch(), id: $this->cursor));

            if (connection_aborted()) {
                break;
            }

            sleep(1);
        }
    } catch (\Throwable $e) {
        $send(new Event(
            data:  json_encode(['error' => $e->getMessage()]),
            event: 'stream-error',
        ));
        // stream() returns — stream ends gracefully
    }
}
```

---

← [Back to README](../README.md)
