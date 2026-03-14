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

#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    public function testFormatSimpleEvent(): void
    {
        $event = new Event(data: 'hello world');

        $this->assertSame("data: hello world\n\n", $event->format());
    }

    public function testFormatWithId(): void
    {
        $event = new Event(data: 'payload', id: '42');

        $this->assertStringContainsString("id: 42\n", $event->format());
    }

    public function testFormatWithNamedEvent(): void
    {
        $event = new Event(data: 'payload', event: 'update');

        $this->assertStringContainsString("event: update\n", $event->format());
    }

    public function testFormatWithRetry(): void
    {
        $event = new Event(data: 'payload', retry: 5000);

        $this->assertStringContainsString("retry: 5000\n", $event->format());
    }

    public function testFormatWithComment(): void
    {
        $event = new Event(data: 'payload', comment: 'keep-alive');

        $this->assertStringContainsString(": keep-alive\n", $event->format());
    }

    public function testFormatMultiLineData(): void
    {
        $event = new Event(data: "line one\nline two\nline three");

        $expected = "data: line one\ndata: line two\ndata: line three\n\n";
        $this->assertSame($expected, $event->format());
    }

    public function testFormatFieldOrder(): void
    {
        $event = new Event(
            data: 'payload',
            id: '1',
            event: 'update',
            retry: 3000,
            comment: 'keep',
        );

        $formatted = $event->format();

        $commentPos = strpos($formatted, ': keep');
        $retryPos   = strpos($formatted, 'retry:');
        $idPos      = strpos($formatted, 'id:');
        $eventPos   = strpos($formatted, 'event:');
        $dataPos    = strpos($formatted, 'data:');

        $this->assertNotFalse($commentPos);
        $this->assertNotFalse($retryPos);
        $this->assertNotFalse($idPos);
        $this->assertNotFalse($eventPos);
        $this->assertNotFalse($dataPos);

        // comment → retry → id → event → data
        $this->assertLessThan($retryPos, $commentPos);
        $this->assertLessThan($idPos, $retryPos);
        $this->assertLessThan($eventPos, $idPos);
        $this->assertLessThan($dataPos, $eventPos);
    }

    public function testFormatEndsWithDoubleNewline(): void
    {
        $event = new Event(data: 'test');

        $this->assertStringEndsWith("\n\n", $event->format());
    }

    public function testCommentOnlyFormatIsValid(): void
    {
        $event = new Event(data: '', comment: 'heartbeat');

        $formatted = $event->format();
        $this->assertStringContainsString(": heartbeat\n", $formatted);
        $this->assertStringEndsWith("\n\n", $formatted);
    }

    public function testGettersReturnConstructorValues(): void
    {
        $event = new Event(
            data: 'data',
            id: 'abc',
            event: 'tick',
            retry: 1000,
            comment: 'note',
        );

        $this->assertSame('data', $event->getData());
        $this->assertSame('abc', $event->getId());
        $this->assertSame('tick', $event->getEvent());
        $this->assertSame(1000, $event->getRetry());
        $this->assertSame('note', $event->getComment());
    }

    public function testGettersReturnNullWhenNotProvided(): void
    {
        $event = new Event(data: 'data');

        $this->assertNull($event->getId());
        $this->assertNull($event->getEvent());
        $this->assertNull($event->getRetry());
        $this->assertNull($event->getComment());
    }
}
