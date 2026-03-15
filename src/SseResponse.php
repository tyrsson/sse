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

use Fiber;
use Laminas\Diactoros\Response;

/**
 * A PSR-7 response that carries a Fiber for deferred SSE streaming.
 *
 * The body stream is intentionally left empty; SseEmitter drives the Fiber
 * and writes events directly to stdout.
 */
final class SseResponse extends Response
{
    /** @param Fiber<mixed,mixed,mixed,mixed> $fiber */
    public function __construct(private readonly Fiber $fiber)
    {
        parent::__construct(
            body: 'php://memory',
            status: 200,
            headers: [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'Connection'        => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /** @return Fiber<mixed,mixed,mixed,mixed> */
    public function getFiber(): Fiber
    {
        return $this->fiber;
    }
}
