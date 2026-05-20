<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Config\Kindling;
use Myth\Kindling\Services\ViteService;

/**
 * @internal
 */
final class ViteServiceTest extends CIUnitTestCase
{
    private string $sentinelPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sentinelPath = sys_get_temp_dir() . '/vite-test-sentinel-' . uniqid();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->sentinelPath)) {
            unlink($this->sentinelPath);
        }
    }

    private function makeConfig(array $overrides = []): Kindling
    {
        $config               = new Kindling();
        $config->sentinelPath = $this->sentinelPath;

        foreach ($overrides as $key => $value) {
            $config->{$key} = $value;
        }

        return $config;
    }

    public function testIsDevModeReturnsTrueWhenSentinelExists(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig());

        $this->assertTrue($service->isDevMode());
    }

    public function testIsDevModeReturnsFalseWhenSentinelAbsent(): void
    {
        $service = new ViteService($this->makeConfig());

        $this->assertFalse($service->isDevMode());
    }

    public function testForceModeDevOverridesSentinel(): void
    {
        $service = new ViteService($this->makeConfig(['forceMode' => 'dev']));

        $this->assertTrue($service->isDevMode());
    }

    public function testForceModeProdOverridesSentinel(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig(['forceMode' => 'prod']));

        $this->assertFalse($service->isDevMode());
    }
}
