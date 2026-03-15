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

use Fiber;
use Laminas\Diactoros\Response\TextResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\SSE\Event;
use Webware\SSE\SseEmitter;
use Webware\SSE\SseResponse;

#[CoversClass(SseEmitter::class)]
final class SseEmitterTest extends TestCase
{
    private SseEmitter $emitter;

    protected function setUp(): void
    {
        $this->emitter = new SseEmitter();
    }

    #[Test]
    public function emitReturnsFalseForNonSseResponse(): void
    {
        $response = new TextResponse('hello');

        self::assertFalse($this->emitter->emit($response));
    }

    #[Test]
    public function emitReturnsTrueForSseResponse(): void
    {
        $fiber = new Fiber(static function (): void {
            // Fiber terminates immediately without yielding.
        });

        $response = new SseResponse($fiber);

        ob_start();
        $result = $this->emitter->emit($response);
        ob_end_clean();

        self::assertTrue($result);
    }

    #[Test]
    public function emitWritesEventInWireFormat(): void
    {
        $fiber = new Fiber(static function (): void {
            Fiber::suspend(new Event(data: '<p>Hello</p>', event: 'msg'));
        });

        $response = new SseResponse($fiber);
        $output   = $this->captureEmit($response);

        self::assertSame("event: msg\ndata: <p>Hello</p>\n\n", $output);
    }

    #[Test]
    public function emitWritesMultipleEvents(): void
    {
        $fiber = new Fiber(static function (): void {
            Fiber::suspend(new Event(data: 'first', event: 'a'));
            Fiber::suspend(new Event(data: 'second', event: 'b'));
        });

        $response = new SseResponse($fiber);
        $output   = $this->captureEmit($response);

        self::assertStringContainsString("event: a\ndata: first\n\n", $output);
        self::assertStringContainsString("event: b\ndata: second\n\n", $output);
    }

    #[Test]
    public function emitIgnoresNonEventFiberYields(): void
    {
        $fiber = new Fiber(static function (): void {
            Fiber::suspend(null); // heartbeat / non-event suspend
            Fiber::suspend(new Event(data: 'hello'));
        });

        $response = new SseResponse($fiber);
        $output   = $this->captureEmit($response);

        self::assertSame("data: hello\n\n", $output);
    }

    /**
     * Run emit() while capturing stdout.
     *
     * SseEmitter flushes the ob stack internally; to avoid breaking PHPUnit's
     * own output buffer we wrap the whole call in ob_start with PHP_OUTPUT_HANDLER_CLEANABLE
     * and rely on ob_get_clean() to retrieve what was echo'd.
     */
    private function captureEmit(SseResponse $response): string
    {
        ob_start();
        $this->emitter->emit($response);

        return (string) ob_get_clean();
    }
}
