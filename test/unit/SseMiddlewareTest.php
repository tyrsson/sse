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

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Webware\SSE\SseMiddleware;

#[CoversClass(SseMiddleware::class)]
final class SseMiddlewareTest extends TestCase
{
    private SseMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new SseMiddleware();
    }

    #[Test]
    public function processReturns406WhenAcceptHeaderIsMissing(): void
    {
        $request  = new ServerRequest();
        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(406, $response->getStatusCode());
    }

    #[Test]
    public function processReturns406WhenAcceptIsWrongType(): void
    {
        $request  = (new ServerRequest())->withHeader('Accept', 'text/html');
        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(406, $response->getStatusCode());
    }

    #[Test]
    public function processDelegatesToHandlerWhenAcceptIsTextEventStream(): void
    {
        $request  = (new ServerRequest())->withHeader('Accept', 'text/event-stream');
        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function processDelegatesToHandlerWhenAcceptContainsTextEventStream(): void
    {
        $request  = (new ServerRequest())->withHeader('Accept', 'text/html, text/event-stream;q=0.9');
        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    private function makeHandler(): RequestHandlerInterface
    {
        return new class() extends stdClass implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };
    }
}
