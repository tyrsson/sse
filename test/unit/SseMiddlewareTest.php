<?php

declare(strict_types=1);

namespace WebwareTest\SSE;

use Generator;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\SSE\Event;
use Webware\SSE\SseMiddleware;
use Webware\SSE\SseResponse;

#[CoversClass(SseMiddleware::class)]
final class SseMiddlewareTest extends TestCase
{
    /**
     * @phpstan-return RequestHandlerInterface&object{capturedRequest: ServerRequestInterface|null}
     */
    private function makePassthroughHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $capturedRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return new TextResponse('ok');
            }
        };
    }

    // -------------------------------------------------------------------------
    // Preprocessor mode (no factory)
    // -------------------------------------------------------------------------

    public function testPreprocessorModePassesRequestToNextHandler(): void
    {
        $middleware = new SseMiddleware();
        $handler    = $this->makePassthroughHandler();

        $middleware->process(new ServerRequest(), $handler);

        $this->assertNotNull($handler->capturedRequest);
    }

    public function testPreprocessorModeInjectsLastEventIdAttribute(): void
    {
        $middleware = new SseMiddleware();
        $handler    = $this->makePassthroughHandler();

        $request = (new ServerRequest())->withHeader('Last-Event-ID', '7');
        $middleware->process($request, $handler);

        $this->assertSame('7', $handler->capturedRequest?->getAttribute(SseMiddleware::LAST_EVENT_ID));
    }

    public function testPreprocessorModeLastEventIdNullWhenHeaderAbsent(): void
    {
        $middleware = new SseMiddleware();
        $handler    = $this->makePassthroughHandler();

        $middleware->process(new ServerRequest(), $handler);

        $this->assertNull($handler->capturedRequest?->getAttribute(SseMiddleware::LAST_EVENT_ID));
    }

    public function testPreprocessorModeReturnsHandlerResponse(): void
    {
        $middleware = new SseMiddleware();
        $handler    = $this->makePassthroughHandler();

        $response = $middleware->process(new ServerRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }

    // -------------------------------------------------------------------------
    // Terminal factory mode
    // -------------------------------------------------------------------------

    public function testTerminalModeReturnsSseResponse(): void
    {
        $middleware = new SseMiddleware(
            static function (ServerRequestInterface $req, ?string $id): Generator {
                yield new Event(data: 'hello');
            },
        );

        $response = $middleware->process(new ServerRequest(), $this->makePassthroughHandler());

        $this->assertInstanceOf(SseResponse::class, $response);
    }

    public function testTerminalModeDoesNotCallNextHandler(): void
    {
        $handler = $this->makePassthroughHandler();

        $middleware = new SseMiddleware(
            static function (ServerRequestInterface $req, ?string $id): Generator {
                yield new Event(data: 'test');
            },
        );

        $middleware->process(new ServerRequest(), $handler);

        $this->assertNull($handler->capturedRequest);
    }

    public function testTerminalModePassesLastEventIdToFactory(): void
    {
        $receivedId = null;

        $middleware = new SseMiddleware(
            static function (ServerRequestInterface $req, ?string $id) use (&$receivedId): Generator {
                $receivedId = $id;
                yield new Event(data: 'ok');
            },
        );

        $request = (new ServerRequest())->withHeader('Last-Event-ID', '42');
        $response = $middleware->process($request, $this->makePassthroughHandler());
        // Advance the generator so its body executes up to the first yield.
        assert($response instanceof SseResponse);
        $response->getEventStream()->current();

        $this->assertSame('42', $receivedId);
    }

    public function testTerminalModePassesNullLastEventIdWhenHeaderAbsent(): void
    {
        $receivedId = 'NOT_NULL';

        $middleware = new SseMiddleware(
            static function (ServerRequestInterface $req, ?string $id) use (&$receivedId): Generator {
                $receivedId = $id;
                yield new Event(data: 'ok');
            },
        );

        $response = $middleware->process(new ServerRequest(), $this->makePassthroughHandler());
        // Advance the generator so its body executes up to the first yield.
        assert($response instanceof SseResponse);
        $response->getEventStream()->current();

        $this->assertNull($receivedId);
    }

    public function testLastEventIdConstantValue(): void
    {
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertSame('SSE_LAST_EVENT_ID', SseMiddleware::LAST_EVENT_ID);
    }
}
