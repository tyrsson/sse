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

namespace WebwareTest\SSE;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;
use Webware\SSE\SseResponse;

#[CoversClass(AbstractSseHandler::class)]
final class AbstractSseHandlerTest extends TestCase
{
    #[Test]
    public function handleReturnsSseResponse(): void
    {
        $handler = new class() extends AbstractSseHandler {
            protected function stream(
                ServerRequestInterface $request,
                callable $send,
                ?string $lastEventId,
            ): void {
                // no-op
            }
        };

        $request  = new ServerRequest();
        $response = $handler->handle($request);

        self::assertInstanceOf(SseResponse::class, $response);
    }

    #[Test]
    public function handleExtractsLastEventIdFromHeader(): void
    {
        $spy     = new LastEventIdSpy();
        $handler = new LastEventIdCapturingHandler($spy);

        $request  = (new ServerRequest())->withHeader('Last-Event-ID', '99');
        $response = $handler->handle($request);

        self::assertInstanceOf(SseResponse::class, $response);
        $response->getFiber()->start();

        self::assertSame('99', $spy->lastEventId);
    }

    #[Test]
    public function handlePassesNullLastEventIdWhenHeaderAbsent(): void
    {
        $spy              = new LastEventIdSpy();
        $spy->lastEventId = 'initial';
        $handler          = new LastEventIdCapturingHandler($spy);

        $request  = new ServerRequest();
        $response = $handler->handle($request);

        self::assertInstanceOf(SseResponse::class, $response);
        $response->getFiber()->start();

        self::assertNull($spy->lastEventId);
    }

    #[Test]
    public function sendCallableSuspendsTheFiberWithEvent(): void
    {
        $handler = new class() extends AbstractSseHandler {
            protected function stream(
                ServerRequestInterface $request,
                callable $send,
                ?string $lastEventId,
            ): void {
                $send(new Event(data: 'tick', event: 'timer'));
            }
        };

        $request  = new ServerRequest();
        $response = $handler->handle($request);

        self::assertInstanceOf(SseResponse::class, $response);

        $yielded = $response->getFiber()->start();

        self::assertInstanceOf(Event::class, $yielded);
        self::assertSame('tick', $yielded->data);
        self::assertSame('timer', $yielded->event);
    }
}

final class LastEventIdSpy
{
    public ?string $lastEventId = null;
}

final class LastEventIdCapturingHandler extends AbstractSseHandler
{
    public function __construct(private readonly LastEventIdSpy $spy) {}

    protected function stream(
        ServerRequestInterface $request,
        callable $send,
        ?string $lastEventId,
    ): void {
        $this->spy->lastEventId = $lastEventId;
    }
}
