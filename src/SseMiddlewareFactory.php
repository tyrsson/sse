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
 * PSR-11 / laminas-servicemanager factory for {@see SseMiddleware}.
 *
 * Produces an instance in *preprocessor mode* (no event-source factory).
 *
 * If you need the terminal factory mode, construct SseMiddleware directly with
 * your stream callable — that mode is intentionally not wired through the
 * container because the event-source callable is application-specific:
 *
 *   $app->get('/events', new SseMiddleware(
 *       fn (ServerRequestInterface $req, callable $send, ?string $id): void => myStream($req, $send, $id)
 *   ));
 */
final class SseMiddlewareFactory
{
    /**
     * @param array<mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SseMiddleware {
        return new SseMiddleware();
    }
}
