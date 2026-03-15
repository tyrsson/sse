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

use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * @template-implements DelegatorFactoryInterface<EmitterStack>
 */
final class SseEmitterDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Push SseEmitter onto the top of the EmitterStack so it is checked first.
     *
     * SseEmitter returns false for non-SseResponse instances and lets the
     * next emitter (SapiEmitter) handle them.
     *
     * @param array<mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null,
    ): EmitterStack {
        /** @var EmitterStack $stack */
        $stack = $callback();

        $stack->push($container->get(SseEmitter::class));

        return $stack;
    }
}
