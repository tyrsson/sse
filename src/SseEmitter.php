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
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitterTrait;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * SSE-aware emitter that integrates with the laminas-httphandlerrunner
 * {@see EmitterInterface} and {@see EmitterStack}.
 *
 * Place this emitter *before* the standard SapiEmitter on the stack:
 *
 *   $stack = new EmitterStack();
 *   $stack->push(new SapiEmitter());
 *   $stack->push(new SseEmitter($config));
 *
 * When the pipeline returns a regular (non-SSE) response, emit() returns
 * false and the EmitterStack falls through to the next emitter (SapiEmitter).
 * When the pipeline returns an {@see SseResponse}, this emitter takes full
 * ownership, streams events until the generator is exhausted or the client
 * disconnects, then returns true.
 *
 * Configuration is read from the top-level array key "webware_sse":
 *
 *   'webware_sse' => [
 *       'heartbeat_interval' => 15,  // seconds between keep-alive comments
 *   ]
 */
final class SseEmitter implements EmitterInterface
{
    use SapiEmitterTrait;

    private readonly int $heartbeatInterval;

    /**
     * @param array<string, mixed> $config Application config array (the full
     *                                     container "config" service value).
     */
    public function __construct(array $config = [])
    {
        /** @var array<string, mixed> $sseConfig */
        $sseConfig = $config['webware_sse'] ?? [];

        $interval                = $sseConfig['heartbeat_interval'] ?? 15;
        $this->heartbeatInterval = is_int($interval) && $interval > 0 ? $interval : 15;
    }

    /**
     * Emit the response.
     *
     * Returns false immediately for any response that is not an SseResponse,
     * allowing the EmitterStack to delegate to the next emitter.
     *
     * @throws RuntimeException When headers have already been sent.
     */
    public function emit(ResponseInterface $response): bool
    {
        if (! $response instanceof SseResponse) {
            return false;
        }

        $this->assertNoPreviousOutput();

        // Allow the script to run indefinitely and do not abort silently when
        // the client disconnects (we check connection_aborted() ourselves).
        set_time_limit(0);
        ignore_user_abort(true);

        // Drain all active output buffers so nothing is held in memory.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $this->emitStatusLine($response);
        $this->emitHeaders($response);

        $this->streamEvents($response->getEventStream());

        return true;
    }

    // -------------------------------------------------------------------------

    // private function emitStatusLine(ResponseInterface $response): void
    // {
    //     $reasonPhrase = $response->getReasonPhrase();
    //     $statusCode   = $response->getStatusCode();
    //     $protocolVersion = $response->getProtocolVersion();

    //     header(sprintf(
    //         'HTTP/%s %d%s',
    //         $protocolVersion,
    //         $statusCode,
    //         ($reasonPhrase !== '' ? ' ' . $reasonPhrase : ''),
    //     ), true, $statusCode);
    // }

    // private function emitHeaders(ResponseInterface $response): void
    // {
    //     foreach ($response->getHeaders() as $name => $values) {
    //         $name  = (string) $name;
    //         $first = true;
    //         foreach ($values as $value) {
    //             header($name . ': ' . $value, $first);
    //             $first = false;
    //         }
    //     }
    // }

    /**
     * Iterate the generator and write SSE frames to the output buffer.
     *
     * - A yielded {@see EventInterface} is formatted and flushed immediately.
     * - A yielded null acts as an explicit heartbeat signal; the emitter also
     *   sends an automatic keep-alive comment when the heartbeat interval
     *   elapses between events.
     *
     * @param Generator<mixed, EventInterface|null, mixed, mixed> $stream
     */
    private function streamEvents(Generator $stream): void
    {
        $lastActivity = time();

        while ($stream->valid()) {
            /** @var EventInterface|null $event */
            $event = $stream->current();

            if ($event instanceof EventInterface) {
                echo $event->format();
                flush();
                $lastActivity = time();
            } elseif ($event === null) {
                // Explicit heartbeat signal or keep-alive check.
                if ((time() - $lastActivity) >= $this->heartbeatInterval) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastActivity = time();
                }
            }

            if (connection_aborted()) {
                break;
            }

            $stream->next();
        }
    }

    // @throws RuntimeException
    // private function assertNoPreviousOutput(): void
    // {
    //     if (headers_sent($file, $line)) {
    //         throw new RuntimeException(sprintf(
    //             'Unable to emit SSE response: headers already sent in %s on line %d.',
    //             $file,
    //             $line,
    //         ));
    //     }
    // }
}
