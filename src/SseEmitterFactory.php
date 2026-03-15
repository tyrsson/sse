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

use Psr\Container\ContainerInterface;

/**
 * PSR-11 / laminas-servicemanager factory for {@see SseEmitter}.
 *
 * Reads the full "config" service from the container (if present) and passes
 * it to the SseEmitter constructor so that it can resolve "webware_sse"
 * configuration such as the heartbeat interval.
 */
final class SseEmitterFactory
{
    /**
     * @param array<mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SseEmitter {
        return new SseEmitter();
    }
}
