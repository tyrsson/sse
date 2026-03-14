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

use Closure;
use Generator;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Webware\SSE\AbstractSseHandler;
use Webware\SSE\Event;
use Webware\SSE\EventInterface;
use Webware\SSE\SseResponse;

#[CoversClass(AbstractSseHandler::class)]
final class AbstractSseHandlerTest extends TestCase
{
    public function testHandleReturnsSseResponse(): void
    {
        $handler  = $this->makeConcreteHandler();
        $request  = new ServerRequest();
        $response = $handler->handle($request);

        $this->assertInstanceOf(SseResponse::class, $response);
    }

    public function testLastEventIdParsedFromHeader(): void
    {
        $receivedId = null;

        $handler = $this->makeConcreteHandler(
            function (ServerRequestInterface $req, ?string $lastEventId) use (&$receivedId): Generator {
                $receivedId = $lastEventId;

                yield new Event(data: 'ok');
            },
        );

        $request  = (new ServerRequest())->withHeader('Last-Event-ID', '99');
        $response = $handler->handle($request);
        // Advance the generator so its body executes up to the first yield.
        $this->assertInstanceOf(SseResponse::class, $response);
        $response->getEventStream()->current();

        $this->assertSame('99', $receivedId);
    }

    public function testLastEventIdIsNullWhenHeaderAbsent(): void
    {
        $receivedId = 'NOT_NULL';

        $handler = $this->makeConcreteHandler(
            function (ServerRequestInterface $req, ?string $lastEventId) use (&$receivedId): Generator {
                $receivedId = $lastEventId;

                yield new Event(data: 'ok');
            },
        );

        $response = $handler->handle(new ServerRequest());
        // Advance the generator so its body executes up to the first yield.
        $this->assertInstanceOf(SseResponse::class, $response);
        $response->getEventStream()->current();

        $this->assertNull($receivedId);
    }

    public function testEmptyLastEventIdHeaderNormalisedToNull(): void
    {
        $receivedId = 'NOT_NULL';

        $handler = $this->makeConcreteHandler(
            function (ServerRequestInterface $req, ?string $lastEventId) use (&$receivedId): Generator {
                $receivedId = $lastEventId;

                yield new Event(data: 'ok');
            },
        );

        $request  = (new ServerRequest())->withHeader('Last-Event-ID', '');
        $response = $handler->handle($request);
        // Advance the generator so its body executes up to the first yield.
        $this->assertInstanceOf(SseResponse::class, $response);
        $response->getEventStream()->current();

        $this->assertNull($receivedId);
    }

    public function testResponseContainsEventStreamContentType(): void
    {
        $handler  = $this->makeConcreteHandler();
        $response = $handler->handle(new ServerRequest());

        $this->assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
    }

    private function makeConcreteHandler(?Closure $streamFn = null): AbstractSseHandler
    {
        return new class($streamFn) extends AbstractSseHandler {
            public function __construct(private readonly ?Closure $fn) {}

            protected function stream(
                ServerRequestInterface $request,
                ?string $lastEventId,
            ): Generator {
                if ($this->fn !== null) {
                    /** @var Generator<mixed, EventInterface|null, mixed, mixed> $gen */
                    $gen = ($this->fn)($request, $lastEventId);

                    yield from $gen;
                } else {
                    yield new Event(data: 'ok');
                }
            }
        };
    }
}
