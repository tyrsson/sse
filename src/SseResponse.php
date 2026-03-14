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

use Generator;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;

/**
 * A PSR-7 response tailored for Server-Sent Events.
 *
 * SseResponse wraps a PHP Generator that yields {@see EventInterface}
 * instances (or null for heartbeat signals).  The body stream itself is
 * intentionally empty — the {@see SseEmitter} iterates the generator and
 * writes directly to the output buffer, bypassing the PSR-7 body entirely.
 *
 * Default SSE headers are merged with any caller-supplied headers:
 *   - Content-Type: text/event-stream
 *   - Cache-Control: no-cache
 *   - X-Accel-Buffering: no  (disables Nginx proxy buffering)
 */
final class SseResponse extends Response
{
    private readonly Generator $eventStream;

    /**
     * @param Generator $eventStream Generator that yields EventInterface|null
     * @param int $status HTTP status code (default 200)
     * @param array<non-empty-string, array<string>|string> $headers Additional response headers
     */
    public function __construct(Generator $eventStream, int $status = 200, array $headers = [])
    {
        $this->eventStream = $eventStream;

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
     * Returns the generator that produces SSE events.
     *
     * The generator yields {@see EventInterface} instances to send an event,
     * or null to signal the emitter that a heartbeat may be due.
     */
    public function getEventStream(): Generator
    {
        return $this->eventStream;
    }
}
