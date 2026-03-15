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

/**
 * A PSR-7 response tailored for Server-Sent Events.
 *
 * SseResponse wraps a callable stream that accepts a $send callable and is
 * responsible for pushing {@see EventInterface} instances to it.  The PSR-7
 * body stream is intentionally empty — the {@see SseEmitter} invokes the
 * stream callable and writes directly to the output buffer.
 *
 * Default SSE headers are merged with any caller-supplied headers:
 *   - Content-Type: text/event-stream
 *   - Cache-Control: no-cache
 *   - X-Accel-Buffering: no  (disables Nginx proxy buffering)
 */
final class SseResponse extends Response
{
    /** @var callable(callable(EventInterface): void): void */
    private $stream;

    /**
     * @param callable(callable(EventInterface): void): void $stream Callable
     *                                                               that receives a $send callable and pushes events through it.
     * @param int $status HTTP status code (default 200)
     * @param array<non-empty-string, array<string>|string> $headers Additional response headers
     */
    public function __construct(callable $stream, int $status = 200, array $headers = [])
    {
        $this->stream = $stream;

        /** @var array<non-empty-string, array<string>|string> $sseHeaders */
        $sseHeaders = [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];

        /** @var array<non-empty-string, array<string>|string> $mergedHeaders */
        // Caller-supplied headers take precedence over defaults
        $mergedHeaders = array_merge($sseHeaders, $headers);

        parent::__construct(
            body: new Stream('php://temp', 'wb+'),
            status: $status,
            headers: $mergedHeaders,
        );
    }

    /**
     * Returns the stream callable.
     *
     * The callable signature is:
     *   function (callable(EventInterface): void $send): void
     *
     * The emitter invokes this with a $send callback that writes each event
     * to the output buffer.
     *
     * @return callable(callable(EventInterface): void): void
     */
    public function getStream(): callable
    {
        return $this->stream;
    }
}
