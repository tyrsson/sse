<?php

declare(strict_types=1);

namespace Webware\SSE;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 / laminas-servicemanager factory for {@see SseEmitter}.
 *
 * Reads the full "config" service from the container (if present) and passes
 * it to the SseEmitter constructor so that it can resolve "webware_sse"
 * configuration such as the heartbeat interval.
 */
final class SseEmitterFactory implements FactoryInterface
{
    /**
     * @param array<mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SseEmitter {
        /** @var array<string, mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        return new SseEmitter($config);
    }
}
