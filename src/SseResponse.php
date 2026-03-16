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

namespace Webware\SSE;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\StreamInterface;

final class SseResponse extends Response
{
    public function __construct(
        StreamInterface|EventInterface|string $event,
        int $status = 200,
    ) {
        parent::__construct(
            body: $this->createBody($event),
            status: $status,
            headers: [
                'Content-Type'  => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection'    => 'keep-alive',
            ]
        );
    }

    private function createBody(StreamInterface|EventInterface|string $event): StreamInterface
    {
        if ($event instanceof StreamInterface) {
            return $event;
        }

        if ($event instanceof EventInterface) {
            $event = (string) $event;
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($event);
        $body->rewind();

        return $body;
    }
}
