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
use Webware\SSE\CloseEvent;

#[CoversClass(CloseEvent::class)]
final class CloseEventTest extends TestCase
{
    public function testGetEventReturnsEventName(): void
    {
        $this->assertSame(CloseEvent::EVENT_NAME, (new CloseEvent())->getEvent());
    }

    public function testGetIdReturnsNull(): void
    {
        $this->assertNull((new CloseEvent())->getId());
    }

    public function testGetDataReturnsEmptyString(): void
    {
        $this->assertSame('', (new CloseEvent())->getData());
    }

    public function testGetRetryReturnsNull(): void
    {
        $this->assertNull((new CloseEvent())->getRetry());
    }

    public function testGetCommentReturnsNull(): void
    {
        $this->assertNull((new CloseEvent())->getComment());
    }

    public function testFormat(): void
    {
        $this->assertSame("event: stream-close\ndata: \n\n", (new CloseEvent())->format());
    }
}
