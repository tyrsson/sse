<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Sse package.
 *
 * Copyright (c) 2026 Joey (aka Tyrsson) Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\SSE;

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
 * ($request, callable $send, ?string $lastEventId), delegating full control
 * of event emission to the factory.  Returns an {@see SseResponse} — no
 * further handler is invoked.  Use this mode to attach a stream directly to
 * a route without a dedicated handler class:
 *
 *   $app->get('/events', new SseMiddleware(
 *       function (ServerRequestInterface $req, callable $send, ?string $lastId): void {
 *           while (true) {
 *               $send(new Event(data: date('H:i:s')));
 *               if (connection_aborted()) break;
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
     * @param callable(ServerRequestInterface, callable(EventInterface): void, string|null): void|null $eventSourceFactory
     *                                                                                                                     Callable that receives the request, a $send callback, and the
     *                                                                                                                     last event id, then pushes events via $send.  Pass null (default)
     *                                                                                                                     to run in preprocessor mode.
     */
    public function __construct(
        private readonly mixed $eventSourceFactory = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $lastEventId = $request->getHeaderLine('Last-Event-ID');
        $lastEventId = $lastEventId !== '' ? $lastEventId : null;

        if ($this->eventSourceFactory !== null) {
            // Terminal factory mode: wrap the factory in a stream callable.
            $factory = $this->eventSourceFactory;

            return new SseResponse(
                fn (callable $send) => $factory($request, $send, $lastEventId),
            );
        }

        // Preprocessor mode: enrich the request and delegate.
        $request = $request->withAttribute(self::LAST_EVENT_ID, $lastEventId);

        return $handler->handle($request);
    }
}
