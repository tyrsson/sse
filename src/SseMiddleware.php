<?php

declare(strict_types=1);

namespace Webware\SSE;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dual-mode PSR-15 middleware for Server-Sent Events.
 *
 * ---
 * MODE 1 — Preprocessor (no $eventSourceFactory supplied)
 * ---
 * Reads the "Last-Event-ID" request header, stores its value as the request
 * attribute defined by {@see SseMiddleware::LAST_EVENT_ID}, and passes the
 * enriched request to the next handler.  Use this mode in a Mezzio pipeline
 * that leads to an {@see AbstractSseHandler}:
 *
 *   $app->pipe(SseMiddleware::class);          // reads Last-Event-ID
 *   $app->get('/events', MyEventHandler::class); // extends AbstractSseHandler
 *
 * ---
 * MODE 2 — Terminal factory (an $eventSourceFactory callable is supplied)
 * ---
 * Reads "Last-Event-ID" and calls the factory with
 * ($request, ?string $lastEventId) to obtain a Generator, then wraps it in
 * an {@see SseResponse} and returns it — no further handler is invoked.
 * Use this mode to attach a stream directly to a route without a dedicated
 * handler class:
 *
 *   $app->get('/events', new SseMiddleware(
 *       function (ServerRequestInterface $req, ?string $lastId): Generator {
 *           while (true) {
 *               yield new Event(data: date('H:i:s'));
 *               sleep(1);
 *           }
 *       }
 *   ));
 */
final class SseMiddleware implements MiddlewareInterface
{
    /**
     * The request attribute name under which the Last-Event-ID value is stored
     * in preprocessor mode.
     */
    public const LAST_EVENT_ID = 'SSE_LAST_EVENT_ID';

    /**
     * @param callable(ServerRequestInterface, string|null): Generator<mixed, EventInterface|null, mixed, mixed>|null $eventSourceFactory
     *     Callable that produces the event generator.  Pass null (default) to
     *     run in preprocessor mode.
     */
    public function __construct(
        private readonly mixed $eventSourceFactory = null,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $lastEventId = $request->getHeaderLine('Last-Event-ID');
        $lastEventId = $lastEventId !== '' ? $lastEventId : null;

        if ($this->eventSourceFactory !== null) {
            // Terminal factory mode: produce and stream events directly.
            /** @var Generator<mixed, EventInterface|null, mixed, mixed> $generator */
            $generator = ($this->eventSourceFactory)($request, $lastEventId);
            return new SseResponse($generator);
        }

        // Preprocessor mode: enrich the request and delegate.
        $request = $request->withAttribute(self::LAST_EVENT_ID, $lastEventId);
        return $handler->handle($request);
    }
}
