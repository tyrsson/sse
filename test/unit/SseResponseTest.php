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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\SSE\SseResponse;

#[CoversClass(SseResponse::class)]
final class SseResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsCorrectHeaders(): void
    {
        $fiber    = new Fiber(static function (): void {});
        $response = new SseResponse($fiber);

        self::assertSame(['text/event-stream'], $response->getHeader('Content-Type'));
        self::assertSame(['no-cache'], $response->getHeader('Cache-Control'));
        self::assertSame(['keep-alive'], $response->getHeader('Connection'));
        self::assertSame(['no'], $response->getHeader('X-Accel-Buffering'));
    }

    #[Test]
    public function constructorSets200StatusCode(): void
    {
        $fiber    = new Fiber(static function (): void {});
        $response = new SseResponse($fiber);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getFiberReturnsTheSameFiber(): void
    {
        $fiber    = new Fiber(static function (): void {});
        $response = new SseResponse($fiber);

        self::assertSame($fiber, $response->getFiber());
    }
}
