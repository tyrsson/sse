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

use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\SSE\Event;

#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    #[Test]
    public function constructorStoresAllProperties(): void
    {
        $data  = 'hello';
        $event = new Event(data: $data, event: 'tick', id: '42', retry: 3000);

        self::assertSame($data, $event->data);
        self::assertSame('tick', $event->event);
        self::assertSame('42', $event->id);
        self::assertSame(3000, $event->retry);
    }

    #[Test]
    public function constructorDefaultsNullableFieldsToNull(): void
    {
        $event = new Event(data: 'hello');

        self::assertNull($event->event);
        self::assertNull($event->id);
        self::assertNull($event->retry);
    }

    #[Test]
    public function toWireFormatWithStringData(): void
    {
        $event = new Event(data: '<p>Hello</p>', event: 'update', id: '1', retry: 1000);

        $expected = "id: 1\nevent: update\ndata: <p>Hello</p>\nretry: 1000\n\n";

        self::assertSame($expected, $event->toWireFormat());
    }

    #[Test]
    public function toWireFormatWithJsonSerializableData(): void
    {
        $payload = new class() implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['foo' => 'bar'];
            }
        };

        $event = new Event(data: $payload, event: 'data');

        $expected = "event: data\ndata: {\"foo\":\"bar\"}\n\n";

        self::assertSame($expected, $event->toWireFormat());
    }

    #[Test]
    public function toWireFormatOmitsNullFields(): void
    {
        $event = new Event(data: 'hello');

        self::assertSame("data: hello\n\n", $event->toWireFormat());
    }

    #[Test]
    public function toWireFormatAlwaysEndsWithDoubleNewline(): void
    {
        $event = new Event(data: 'x');

        self::assertStringEndsWith("\n\n", $event->toWireFormat());
    }
}
