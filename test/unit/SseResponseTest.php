<?php

declare(strict_types=1);

namespace WebwareTest\SSE;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\SSE\Event;
use Webware\SSE\SseResponse;

#[CoversClass(SseResponse::class)]
final class SseResponseTest extends TestCase
{
    private function makeGenerator(): Generator
    {
        yield new Event(data: 'test');
    }

    public function testStatusCodeDefaultsTo200(): void
    {
        $response = new SseResponse($this->makeGenerator());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCustomStatusCode(): void
    {
        $response = new SseResponse($this->makeGenerator(), 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testContentTypeHeaderIsTextEventStream(): void
    {
        $response = new SseResponse($this->makeGenerator());

        $this->assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
    }

    public function testCacheControlHeaderIsNoCache(): void
    {
        $response = new SseResponse($this->makeGenerator());

        $this->assertSame('no-cache', $response->getHeaderLine('Cache-Control'));
    }

    public function testXAccelBufferingHeaderIsNo(): void
    {
        $response = new SseResponse($this->makeGenerator());

        $this->assertSame('no', $response->getHeaderLine('X-Accel-Buffering'));
    }

    public function testCallerHeadersMergedAndTakePrecedence(): void
    {
        $response = new SseResponse(
            $this->makeGenerator(),
            headers: ['X-Custom' => 'yes', 'Cache-Control' => 'no-store'],
        );

        $this->assertSame('yes', $response->getHeaderLine('X-Custom'));
        // Caller-supplied Cache-Control takes precedence over default
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testGetEventStreamReturnsSameGenerator(): void
    {
        $generator = $this->makeGenerator();
        $response  = new SseResponse($generator);

        $this->assertSame($generator, $response->getEventStream());
    }

    public function testBodyIsEmptyStream(): void
    {
        $response = new SseResponse($this->makeGenerator());

        $this->assertSame('', (string) $response->getBody());
    }
}
