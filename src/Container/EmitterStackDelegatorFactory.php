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

namespace Webware\SSE\Container;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webware\SSE\SseEmitter;

/**
 * @internal
 */
final readonly class EmitterStackDelegatorFactory
{
    /**
     * @phpstan-param null|array<mixed> $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null,
    ): EmitterInterface {
        /** @var EmitterStack $stack */
        $stack = $callback();

        if (! $stack instanceof EmitterStack) {
            throw new RuntimeException(sprintf('Expected the service "%s" to be an instance of %s; received %s', $name, EmitterStack::class, is_object($stack) ? get_class($stack) : gettype($stack)));
        }

        $sseEmitter = $container->get(SseEmitter::class);

        // Add our SseEmitter to the stack.
        $stack->push($sseEmitter);

        return $stack;
    }
}
