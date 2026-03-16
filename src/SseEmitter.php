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
 * ownership, invokes the response's stream callable, and returns true.
 */
final class SseEmitter implements EmitterInterface
{
    use SapiEmitterTrait;

    public function __construct() {}

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

        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        $this->emitBody($response);

        return true;
    }

    private function emitBody(ResponseInterface $response): void
    {
        echo $response->getBody();
    }
}
