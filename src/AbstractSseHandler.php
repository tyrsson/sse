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
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base class for PSR-15 request handlers that produce Server-Sent Event
 * streams.
 *
 * Extend this class and implement the {@see stream()} method.  Invoke
 * $send with each {@see EventInterface} you want to push to the client:
 *
 *   final class StockTickerHandler extends AbstractSseHandler
 *   {
 *       protected function stream(
 *           ServerRequestInterface $request,
 *           callable $send,
 *           ?string $lastEventId,
 *       ): void {
 *           $cursor = $lastEventId ?? '0';
 *           while (true) {
 *               $events = $this->stockService->getEventsSince($cursor);
 *               foreach ($events as $e) {
 *                   $cursor = $e->id;
 *                   $send(new Event(data: json_encode($e), id: $cursor));
 *               }
 *               if (connection_aborted()) break;
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
     * Build and return an SseResponse wrapping the event stream.
     *
     * The Last-Event-ID header is extracted from the request and forwarded to
     * the stream() implementation.  An empty header value is normalised to null.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $lastEventId = $request->getHeaderLine('Last-Event-ID');
        $lastEventId = $lastEventId !== '' ? $lastEventId : null;

        return new SseResponse(
            fn (callable $send) => $this->stream($request, $send, $lastEventId),
        );
    }

    /**
     * Produce the event stream.
     *
     * Call $send with each {@see EventInterface} to push it to the connected
     * client.  Return (or let execution fall off the end) to close the stream.
     * Check connection_aborted() to detect a disconnected client inside loops.
     *
     * @param ServerRequestInterface $request The current PSR-7 request.
     * @param callable(EventInterface): void $send Push an event to the client.
     * @param string|null $lastEventId The last event id the client
     *                                 received, or null on first connect.
     */
    abstract protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void;
}
