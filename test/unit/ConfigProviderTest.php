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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\SSE\ConfigProvider;
use Webware\SSE\SseEmitter;
use Webware\SSE\SseEmitterFactory;
use Webware\SSE\SseMiddleware;
use Webware\SSE\SseMiddlewareFactory;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    public function testInvokeReturnsDependenciesKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('dependencies', $config);
    }

    public function testInvokeReturnsWestwareSseKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('webware_sse', $config);
    }

    public function testDependenciesContainsFactoriesKey(): void
    {
        $deps = $this->provider->getDependencies();

        $this->assertArrayHasKey('factories', $deps);
    }

    public function testSseEmitterFactoryIsRegistered(): void
    {
        $factories = $this->provider->getDependencies()['factories'];

        $this->assertArrayHasKey(SseEmitter::class, $factories);
        $this->assertSame(SseEmitterFactory::class, $factories[SseEmitter::class]);
    }

    public function testSseMiddlewareFactoryIsRegistered(): void
    {
        $factories = $this->provider->getDependencies()['factories'];

        $this->assertArrayHasKey(SseMiddleware::class, $factories);
        $this->assertSame(SseMiddlewareFactory::class, $factories[SseMiddleware::class]);
    }

    public function testSseConfigContainsRetry(): void
    {
        $sseConfig = $this->provider->getSseConfig();

        $this->assertArrayHasKey('retry', $sseConfig);
        $this->assertIsInt($sseConfig['retry']);
        $this->assertGreaterThan(0, $sseConfig['retry']);
    }
}
