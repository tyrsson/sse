<?php

declare(strict_types=1);

namespace WebwareTest\SSE;

use Generator;
use Laminas\Diactoros\Response\JsonResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

    public function testHeartbeatIntervalDefaultsTo15(): void
    {
        $emitter = new SseEmitter();

        // Access private property via reflection to verify the default.
        $ref      = new \ReflectionClass($emitter);
        $property = $ref->getProperty('heartbeatInterval');
        $property->setAccessible(true);

        $this->assertSame(15, $property->getValue($emitter));
    }

    public function testHeartbeatIntervalFromConfig(): void
    {
        $emitter = new SseEmitter(['webware_sse' => ['heartbeat_interval' => 30]]);

        $ref      = new \ReflectionClass($emitter);
        $property = $ref->getProperty('heartbeatInterval');
        $property->setAccessible(true);

        $this->assertSame(30, $property->getValue($emitter));
    }

    public function testInvalidHeartbeatIntervalFallsBackToDefault(): void
    {
        $emitter = new SseEmitter(['webware_sse' => ['heartbeat_interval' => -5]]);

        $ref      = new \ReflectionClass($emitter);
        $property = $ref->getProperty('heartbeatInterval');
        $property->setAccessible(true);

        $this->assertSame(15, $property->getValue($emitter));
    }

    public function testEmitterImplementsEmitterInterface(): void
    {
        $emitter = new SseEmitter();

        // Verify via interface list to avoid PHPStan's "always true" narrowing warning.
        $this->assertContains(
            \Laminas\HttpHandlerRunner\Emitter\EmitterInterface::class,
            array_keys(class_implements($emitter) ?: []),
        );
    }

    /**
     * Verify that the emitter iterates the generator.
     * We run the "emit" logic with output buffering so we can capture output.
     * The generator yields one event and then completes.
     */
    public function testEmitsCapturedOutput(): void
    {
        // We need headers to not be sent yet — in PHPUnit CLI this is normally
        // fine; assertNoPreviousOutput will pass.  We reflectively call the
        // private streamEvents method to avoid the header-emission path.
        $emitter = new SseEmitter();

        $generator = (static function (): Generator {
            yield new Event(data: 'hello', id: '1');
        })();

        $response = new SseResponse($generator);

        $ref    = new \ReflectionClass($emitter);
        $method = $ref->getMethod('streamEvents');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($emitter, $response->getEventStream());
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $this->assertStringContainsString("data: hello\n", (string) $output);
        $this->assertStringContainsString("id: 1\n", (string) $output);
    }

    public function testNullYieldDoesNotOutputWhenBelowInterval(): void
    {
        // With heartbeat_interval = 9999, a null yield should not emit anything.
        $emitter = new SseEmitter(['webware_sse' => ['heartbeat_interval' => 9999]]);

        $generator = (static function (): Generator {
            yield null;
        })();

        $response = new SseResponse($generator);

        $ref    = new \ReflectionClass($emitter);
        $method = $ref->getMethod('streamEvents');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($emitter, $response->getEventStream());
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $this->assertSame('', (string) $output);
    }
}
