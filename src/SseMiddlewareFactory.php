<?php

declare(strict_types=1);

namespace Webware\SSE;

use Psr\Container\ContainerInterface;

/**
 * PSR-11 / laminas-servicemanager factory for {@see SseMiddleware}.
 *
 * Produces an instance in *preprocessor mode* (no event-source factory).
 *
 * If you need the terminal factory mode, construct SseMiddleware directly with
 * your generator-producing callable — that mode is intentionally not wired
 * through the container because the event-source callable is application-
 * specific:
 *
 *   $app->get('/events', new SseMiddleware(
 *       fn (ServerRequestInterface $req, ?string $id): Generator => myStream($req, $id)
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
