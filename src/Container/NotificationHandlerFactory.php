<?php

declare(strict_types=1);

/**
 * This file is part of the Tyrsson Webinertia package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\SSE\Container;

use Laminas\View\HelperPluginManager;
use Laminas\View\Helper\Partial;
use Psr\Container\ContainerInterface;
use Webware\SSE\NotificationHandler;

final class NotificationHandlerFactory
{
    public function __invoke(ContainerInterface $container): NotificationHandler
    {
        return new NotificationHandler(
            $container->get(HelperPluginManager::class)->get(Partial::class),
        );
    }
}
