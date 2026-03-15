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

use JsonException;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

use function connection_aborted;
use function flush;
use function header;
use function ob_end_flush;
use function ob_get_level;
use function sprintf;

final class SseEmitter implements EmitterInterface
{
    /**
     * Emit the response.
     *
     * Returns false for any non-SseResponse so that the next emitter in the
     * EmitterStack (e.g. SapiEmitter) can handle it.
     *
     * For SseResponse, the embedded Fiber is started and resumed until it
     * terminates, with each yielded Event written to stdout in SSE wire format.
     *
     * @throws JsonException
     */
    public function emit(ResponseInterface $response): bool
    {
        if (! $response instanceof SseResponse) {
            return false;
        }

        // Flush any output buffers without closing them, so content reaches the
        // client immediately. We intentionally avoid ob_end_flush() here to
        // avoid closing buffers owned by the calling environment (e.g. web
        // servers or test harnesses).
        while (ob_get_level() > 0) {
            if (ob_get_length() !== false) {
                ob_flush();
            }

            break;
        }

        // Send SSE headers.
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        header(
            sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase() !== '' ? ' ' . $response->getReasonPhrase() : ''
            ),
            true,
            $response->getStatusCode(),
        );

        $fiber = $response->getFiber();

        // Start the fiber; it runs until it first suspends with an Event (or terminates).
        $event = $fiber->start();

        while (! $fiber->isTerminated()) {
            if ($event instanceof Event) {
                echo $event->toWireFormat();
                flush();
            }

            if (connection_aborted()) {
                break;
            }

            $event = $fiber->resume();
        }

        // Emit the final value if the fiber returned without terminating via break.
        if ($fiber->isTerminated() && $event instanceof Event) {
            echo $event->toWireFormat();
            flush();
        }

        return true;
    }
}
