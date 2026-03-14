<?php

declare(strict_types=1);

namespace Webware\SSE;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Psr\Container\ContainerInterface;

final class EmitterStackDelegatorFactory
{
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null
    ): EmitterInterface {
        /** @var EmitterStack $stack */
        $stack = $callback();

        if (! $stack instanceof EmitterStack) {
            throw new \RuntimeException(sprintf(
                'Expected the service "%s" to be an instance of %s; received %s',
                $name,
                EmitterStack::class,
                is_object($stack) ? get_class($stack) : gettype($stack)
            ));
        }

        $sseEmitter = $container->get(SseEmitter::class);

        // Add our SseEmitter to the stack.
        $stack->push($sseEmitter);

        return $stack;
    }
}
