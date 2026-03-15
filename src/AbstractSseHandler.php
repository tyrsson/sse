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

use Fiber;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractSseHandler implements RequestHandlerInterface
{
    final public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $lastEventId = $request->getHeaderLine('Last-Event-ID') ?: null;

        $send = static function (Event $event): void {
            Fiber::suspend($event);
        };

        /** @var Fiber<mixed,mixed,mixed,mixed> $fiber */
        $fiber = new Fiber(function () use ($request, $send, $lastEventId): void {
            $this->stream($request, $send, $lastEventId);
        });

        return new SseResponse($fiber);
    }

    /**
     * Implement this method to produce SSE events.
     *
     * Call $send(new Event(...)) to push an event to the client.
     * The call to $send() suspends the Fiber, giving the SseEmitter a chance
     * to write and flush the event before resuming.
     *
     * Example:
     *
     *   protected function stream(
     *       ServerRequestInterface $request,
     *       callable $send,
     *       ?string $lastEventId,
     *   ): void {
     *       while (true) {
     *           $send(new Event(data: date('H:i:s'), event: 'tick'));
     *           if (connection_aborted()) break;
     *           sleep(1);
     *       }
     *   }
     */
    abstract protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void;
}
