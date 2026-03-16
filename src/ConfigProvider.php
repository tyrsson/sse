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

final readonly class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'router'       => $this->getRouteProviders(),
            'templates'    => $this->getTemplates(),
        ];
    }

    /**
     * Returns the container dependencies.
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                NotificationHandler::class => Container\NotificationHandlerFactory::class,
                RouteProvider::class       => Container\RouteProviderFactory::class,
            ],
        ];
    }

    public function getRouteProviders(): array
    {
        return [
            'route-providers' => [
                RouteProvider::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            'map'   => [
                'sse::info'    => __DIR__ . '/../templates/sse/info.phtml',
                'sse::message' => __DIR__ . '/../templates/sse/message.phtml',
            ],
            'paths' => [
                'sse'   => [__DIR__ . '/../templates/sse'],
                'error' => [__DIR__ . '/../templates/error'],
            ],
        ];
    }
}
