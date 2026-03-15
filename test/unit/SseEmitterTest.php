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

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webware\SSE\Event;
use Webware\SSE\SseEmitter;
use Webware\SSE\SseResponse;

#[CoversClass(SseEmitter::class)]
final class SseEmitterTest extends TestCase
{
    public function testReturnsFalseForNonSseResponse(): void
    {
        $emitter  = new SseEmitter();
        $response = new JsonResponse(['ok' => true]);

        $this->assertFalse($emitter->emit($response));
    }

    public function testEmitterImplementsEmitterInterface(): void
    {
        $emitter = new SseEmitter();

        // Verify via interface list to avoid PHPStan's "always true" narrowing warning.
        $this->assertContains(
            EmitterInterface::class,
            array_keys(class_implements($emitter) ?: []),
        );
    }

    /**
     * Verify that streamEvents flushes the formatted event to output.
     */
    public function testEmitsCapturedOutput(): void
    {
        $emitter = new SseEmitter();

        $response = new SseResponse(
            static function (callable $send): void {
                $send(new Event(data: 'hello', id: '1'));
            },
        );

        $ref    = new ReflectionClass($emitter);
        $method = $ref->getMethod('streamEvents');

        ob_start();
        $method->invoke($emitter, $response);
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $this->assertStringContainsString("data: hello\n", (string) $output);
        $this->assertStringContainsString("id: 1\n", (string) $output);
    }

    public function testStreamCallableIsInvoked(): void
    {
        $emitter = new SseEmitter();
        $invoked = false;

        $response = new SseResponse(
            static function (callable $send) use (&$invoked): void {
                $invoked = true;
            },
        );

        $ref    = new ReflectionClass($emitter);
        $method = $ref->getMethod('streamEvents');

        ob_start();
        $method->invoke($emitter, $response);
        ob_get_clean();

        $this->assertTrue($invoked);
    }
}
