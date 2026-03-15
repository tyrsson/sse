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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\SSE\Event;
use Webware\SSE\EventInterface;
use Webware\SSE\SseResponse;

#[CoversClass(SseResponse::class)]
final class SseResponseTest extends TestCase
{
    public function testStatusCodeDefaultsTo200(): void
    {
        $response = new SseResponse($this->makeStream());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCustomStatusCode(): void
    {
        $response = new SseResponse($this->makeStream(), 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testContentTypeHeaderIsTextEventStream(): void
    {
        $response = new SseResponse($this->makeStream());

        $this->assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
    }

    public function testCacheControlHeaderIsNoCache(): void
    {
        $response = new SseResponse($this->makeStream());

        $this->assertSame('no-cache', $response->getHeaderLine('Cache-Control'));
    }

    public function testXAccelBufferingHeaderIsNo(): void
    {
        $response = new SseResponse($this->makeStream());

        $this->assertSame('no', $response->getHeaderLine('X-Accel-Buffering'));
    }

    public function testCallerHeadersMergedAndTakePrecedence(): void
    {
        $response = new SseResponse(
            $this->makeStream(),
            headers: ['X-Custom' => 'yes', 'Cache-Control' => 'no-store'],
        );

        $this->assertSame('yes', $response->getHeaderLine('X-Custom'));
        // Caller-supplied Cache-Control takes precedence over default
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testGetStreamReturnsCallable(): void
    {
        $stream   = $this->makeStream();
        $response = new SseResponse($stream);

        $this->assertSame($stream, $response->getStream());
    }

    public function testBodyIsEmptyStream(): void
    {
        $response = new SseResponse($this->makeStream());

        $this->assertSame('', (string) $response->getBody());
    }

    /** @return callable(callable(EventInterface): void): void */
    private function makeStream(): callable
    {
        return static function (callable $send): void {
            $send(new Event(data: 'test'));
        };
    }
}
