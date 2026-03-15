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

namespace WebwareTest\SSE;

use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\SSE\ConfigProvider;
use Webware\SSE\SseEmitter;
use Webware\SSE\SseEmitterDelegatorFactory;
use Webware\SSE\SseMiddleware;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    #[Test]
    public function invokeReturnsDependenciesKey(): void
    {
        $config = ($this->provider)();

        self::assertArrayHasKey('dependencies', $config);
    }

    #[Test]
    public function dependenciesRegistersInvokableServices(): void
    {
        $deps = $this->provider->getDependencies();

        self::assertArrayHasKey(SseEmitter::class, $deps['invokables']);
        self::assertArrayHasKey(SseMiddleware::class, $deps['invokables']);
    }

    #[Test]
    public function dependenciesRegistersEmitterStackDelegator(): void
    {
        $deps = $this->provider->getDependencies();

        self::assertArrayHasKey(EmitterStack::class, $deps['delegators']);
        self::assertContains(SseEmitterDelegatorFactory::class, $deps['delegators'][EmitterStack::class]);
    }
}
