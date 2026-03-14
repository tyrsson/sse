<?php

declare(strict_types=1);

namespace Webware\SSE;

/**
 * Mezzio / PSR-11 configuration provider for the webware/sse package.
 *
 * Register this provider in your Mezzio application config aggregator:
 *
 *   // config/config.php
 *   $aggregator = new ConfigAggregator([
 *       \Webware\SSE\ConfigProvider::class,
 *       // ...
 *   ]);
 *
 * This registers:
 *   - {@see SseEmitter} via {@see SseEmitterFactory}
 *   - {@see SseMiddleware} via {@see SseMiddlewareFactory} (preprocessor mode)
 *
 * Default SSE configuration can be overridden by merging your own
 * "webware_sse" key in any subsequent config provider or local config file:
 *
 *   return [
 *       'webware_sse' => [
 *           'heartbeat_interval' => 30, // seconds
 *           'retry'              => 5000, // ms, for use in Event objects
 *       ],
 *   ];
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'webware_sse'  => $this->getSseConfig(),
        ];
    }

    /**
     * Returns the container dependency configuration.
     *
     * The "factories" key is understood by laminas-servicemanager and by any
     * other PSR-11 container that follows the standard Mezzio config shape.
     *
     * @return array<string, array<class-string, class-string>>
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                SseEmitter::class    => SseEmitterFactory::class,
                SseMiddleware::class => SseMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * Returns the default SSE configuration values.
     *
     * heartbeat_interval: seconds between keep-alive comment frames when the
     *     generator has not yielded a real event.
     *
     * retry: milliseconds that the browser's EventSource should wait before
     *     reconnecting after a dropped connection.  This value is informational
     *     for application code; use it when constructing Event objects:
     *     new Event(data: '...', retry: $config['webware_sse']['retry'])
     *
     * @return array<string, int>
     */
    public function getSseConfig(): array
    {
        return [
            'heartbeat_interval' => 15,
            'retry'              => 3000,
        ];
    }
}
