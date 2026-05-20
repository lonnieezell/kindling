<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Exceptions\KindlingManifestException;
use Myth\Kindling\Services\ManifestReader;

/**
 * @internal
 */
final class ManifestReaderTest extends CIUnitTestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Writes $data as JSON to a temp file and registers it for cleanup.
     */
    private function makeTempManifest(array $data): string
    {
        $path = sys_get_temp_dir() . '/manifest-' . uniqid() . '.json';
        file_put_contents($path, json_encode($data));
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testInvalidJsonThrows(): void
    {
        $path = sys_get_temp_dir() . '/manifest-' . uniqid() . '.json';
        file_put_contents($path, 'not valid json {{{');
        $this->tempFiles[] = $path;

        $this->expectException(KindlingManifestException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');

        $reader = new ManifestReader($path);
        $reader->resolve('any');
    }

    public function testMissingManifestFileThrows(): void
    {
        $this->expectException(KindlingManifestException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');

        $reader = new ManifestReader('/tmp/nonexistent-manifest-' . uniqid() . '.json');
        $reader->resolve('any');
    }

    public function testMissingEntryKeyThrows(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => ['file' => 'assets/app-abc.js', 'isEntry' => true],
        ]);

        $this->expectException(KindlingManifestException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');

        $reader = new ManifestReader($path);
        $reader->resolve('resources/js/nonexistent.js');
    }

    public function testDanglingChunkReferenceThrows(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_missing-chunk.js'],
            ],
        ]);

        $this->expectException(KindlingManifestException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');

        $reader = new ManifestReader($path);
        $reader->resolve('resources/js/app.js');
    }

    public function testCssOnlyChunkIsSkippedInImportsButCssIsCollected(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_css-only.js'],
            ],
            '_css-only.js' => [
                // no 'file' key — CSS-only chunk
                'css' => ['assets/styles-xyz.css'],
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertSame([], $entry->imports);
        $this->assertSame(['assets/styles-xyz.css'], $entry->css);
    }

    public function testCircularImportDoesNotInfiniteLoop(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_chunk-a.js'],
            ],
            '_chunk-a.js' => [
                'file'    => 'assets/chunk-a.js',
                'imports' => ['_chunk-b.js'],
            ],
            '_chunk-b.js' => [
                'file'    => 'assets/chunk-b.js',
                'imports' => ['_chunk-a.js'], // cycle back to a
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertContains('assets/chunk-a.js', $entry->imports);
        $this->assertContains('assets/chunk-b.js', $entry->imports);
        $this->assertCount(2, $entry->imports);
    }

    public function testTwoEntryPointsSharingAChunkResolveIndependently(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_shared.js'],
            ],
            'resources/js/admin.js' => [
                'file'    => 'assets/admin-def.js',
                'isEntry' => true,
                'imports' => ['_shared.js'],
            ],
            '_shared.js' => [
                'file' => 'assets/shared-ghi.js',
            ],
        ]);

        $reader = new ManifestReader($path);
        $app    = $reader->resolve('resources/js/app.js');
        $admin  = $reader->resolve('resources/js/admin.js');

        $this->assertSame(['assets/shared-ghi.js'], $app->imports);
        $this->assertSame(['assets/shared-ghi.js'], $admin->imports);
    }

    public function testCollectsCssFromChunkNode(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_chunk-xyz.js'],
            ],
            '_chunk-xyz.js' => [
                'file' => 'assets/chunk-xyz.js',
                'css'  => ['assets/chunk-xyz.css'],
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertSame(['assets/chunk-xyz.css'], $entry->css);
    }

    public function testCollectsCssFromRootEntry(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'css'     => ['assets/app-def.css'],
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertSame(['assets/app-def.css'], $entry->css);
    }

    public function testResolvesRecursiveChunkImportsDeptFirst(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_chunk-b.js'],
            ],
            '_chunk-b.js' => [
                'file'    => 'assets/chunk-b.js',
                'imports' => ['_chunk-c.js'],
            ],
            '_chunk-c.js' => [
                'file' => 'assets/chunk-c.js',
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        // depth-first: c (dep of b) before b (dep of app)
        $this->assertSame(['assets/chunk-c.js', 'assets/chunk-b.js'], $entry->imports);
    }

    public function testResolvesEntryWithOneChunkImport(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'imports' => ['_chunk-xyz.js'],
            ],
            '_chunk-xyz.js' => [
                'file' => 'assets/chunk-xyz.js',
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertSame('assets/app-abc.js', $entry->file);
        $this->assertSame(['assets/chunk-xyz.js'], $entry->imports);
        $this->assertSame([], $entry->css);
    }

    public function testResolvesSimpleEntryWithNoChunks(): void
    {
        $path = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc123.js',
                'isEntry' => true,
            ],
        ]);

        $reader = new ManifestReader($path);
        $entry  = $reader->resolve('resources/js/app.js');

        $this->assertSame('assets/app-abc123.js', $entry->file);
        $this->assertSame([], $entry->css);
        $this->assertSame([], $entry->imports);
    }
}
