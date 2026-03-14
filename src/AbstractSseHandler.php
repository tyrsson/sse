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

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base class for PSR-15 request handlers that produce Server-Sent Event
 * streams.
 *
 * Extend this class and implement the {@see stream()} method, which should be
 * a PHP generator yielding {@see EventInterface} instances (or null for an
 * explicit heartbeat signal):
 *
 *   final class StockTickerHandler extends AbstractSseHandler
 *   {
 *       protected function stream(
 *           ServerRequestInterface $request,
 *           ?string $lastEventId,
 *       ): Generator {
 *           $cursor = $lastEventId ?? '0';
 *           while (true) {
 *               $events = $this->stockService->getEventsSince($cursor);
 *               foreach ($events as $e) {
 *                   $cursor = $e->id;
 *                   yield new Event(data: json_encode($e), id: $cursor);
 *               }
 *               yield null; // heartbeat signal when there is nothing new
 *               sleep(1);
 *           }
 *       }
 *   }
 *
 * Reconnection is handled transparently: the browser's EventSource API sends
 * the last received event id in the "Last-Event-ID" request header on
 * reconnect.  This class extracts that value and passes it to stream() so
 * that application code can resume from the correct position.
 */
abstract class AbstractSseHandler implements RequestHandlerInterface
{
    /**
     * Build and return an SseResponse wrapping the event generator.
     *
     * The Last-Event-ID header is extracted from the request and forwarded to
     * the stream() implementation.  An empty header value is normalised to null.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $lastEventId = $request->getHeaderLine('Last-Event-ID');
        $lastEventId = $lastEventId !== '' ? $lastEventId : null;

        return new SseResponse($this->stream($request, $lastEventId));
    }

    /**
     * Produce the event stream.
     *
     * Implementations MUST be generators (use yield).  The generator may:
     *   - yield an {@see EventInterface} to send an event to the client
     *   - yield null to hint that a heartbeat may be sent if the interval has
     *     elapsed (the emitter decides; the generator just needs to keep going)
     *   - return (or exhaust) to close the stream
     *
     * @param ServerRequestInterface $request The current PSR-7 request.
     * @param string|null $lastEventId The last event id the client
     *                                 received, or null on first connect.
     * @return Generator<mixed, EventInterface|null, mixed, mixed>
     */
    abstract protected function stream(
        ServerRequestInterface $request,
        ?string $lastEventId,
    ): Generator;
}
