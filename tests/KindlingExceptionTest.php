<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Exceptions\KindlingException;
use Myth\Kindling\Exceptions\KindlingManifestException;

/**
 * @internal
 */
final class KindlingExceptionTest extends CIUnitTestCase
{
    public function testForMissingManifestContainsPathAndInstruction(): void
    {
        $e = KindlingManifestException::forMissingManifest('/var/www/public/build/.vite/manifest.json');

        $this->assertStringContainsString('Kindle:', $e->getMessage());
        $this->assertStringContainsString('/var/www/public/build/.vite/manifest.json', $e->getMessage());
        $this->assertStringContainsString('npm run build', $e->getMessage());
    }

    public function testForMissingEntryContainsKeyAndValidList(): void
    {
        $e = KindlingManifestException::forMissingEntry('admin', ['app', 'dashboard']);

        $this->assertStringContainsString('Kindle:', $e->getMessage());
        $this->assertStringContainsString('admin', $e->getMessage());
        $this->assertStringContainsString('app', $e->getMessage());
        $this->assertStringContainsString('dashboard', $e->getMessage());
    }

    public function testForNoViteRunningMessage(): void
    {
        $e = KindlingException::forNoViteRunning();

        $this->assertStringContainsString('Kindle:', $e->getMessage());
        $this->assertStringContainsString('npm run dev', $e->getMessage());
        $this->assertStringContainsString('npm run build', $e->getMessage());
    }

    public function testForUnknownEntryContainsNameAndValidList(): void
    {
        $e = KindlingException::forUnknownEntry('checkout', ['app', 'admin']);

        $this->assertStringContainsString('Kindle:', $e->getMessage());
        $this->assertStringContainsString('checkout', $e->getMessage());
        $this->assertStringContainsString('app', $e->getMessage());
        $this->assertStringContainsString('admin', $e->getMessage());
    }
}
