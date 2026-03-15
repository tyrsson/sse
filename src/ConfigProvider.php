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

final class ConfigProvider
{
    /**
     * Return configuration for use with laminas-servicemanager (or any
     * container that understands the same dependency config format).
     *
     * @return array{
     *   dependencies: array{
     *     invokables: array<class-string, class-string>,
     *     delegators: array<class-string, list<class-string>>
     *   }
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array{
     *   invokables: array<class-string, class-string>,
     *   delegators: array<class-string, list<class-string>>
     * }
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [
                SseEmitter::class    => SseEmitter::class,
                SseMiddleware::class => SseMiddleware::class,
            ],
            'delegators' => [
                EmitterStack::class => [
                    SseEmitterDelegatorFactory::class,
                ],
            ],
        ];
    }
}
