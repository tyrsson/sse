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
 *           'retry' => 5000, // ms, for use in Event objects
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
     * @return array{'delegators': array<string, array<int, class-string>>, 'factories': array<string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'delegators' => [
                EmitterInterface::class => [
                    EmitterStackDelegatorFactory::class,
                ],
            ],
            'factories'  => [
                SseEmitter::class    => SseEmitterFactory::class,
                SseMiddleware::class => SseMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * Returns the default SSE configuration values.
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
            'retry' => 3000,
        ];
    }
}
