<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Config\Kindling;

/**
 * @internal
 */
final class KindlingConfigTest extends CIUnitTestCase
{
    public function testDefaultValues(): void
    {
        $config = new Kindling();

        $this->assertSame('http://localhost:5173', $config->devServerUrl);
        $this->assertSame(FCPATH . 'build/.vite/manifest.json', $config->manifestPath);
        $this->assertSame('/build', $config->buildPath);
        $this->assertNull($config->forceMode);
        $this->assertSame([], $config->entryPoints);
    }
}
