<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Config\Kindling;
use Myth\Kindling\Exceptions\KindlingException;
use Myth\Kindling\Services\ViteService;

/**
 * @internal
 */
final class ViteServiceTest extends CIUnitTestCase
{
    private string $sentinelPath;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

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

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function makeTempManifest(array $data): string
    {
        $path = sys_get_temp_dir() . '/manifest-' . uniqid() . '.json';
        file_put_contents($path, json_encode($data));
        $this->tempFiles[] = $path;

        return $path;
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

    public function testProdTagsWhenManifestExistsAndSentinelAbsent(): void
    {
        $manifest = $this->makeTempManifest([
            'resources/js/app.js' => ['file' => 'assets/app-abc.js', 'isEntry' => true],
        ]);
        // sentinel absent (not touched), manifest exists
        $service = new ViteService($this->makeConfig([
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app');

        $this->assertStringContainsString('<script type="module"', $output);
        $this->assertStringContainsString('/build/assets/app-abc.js', $output);
    }

    public function testForceModeProdReturnsProdTags(): void
    {
        $manifest = $this->makeTempManifest([
            'resources/js/app.js' => ['file' => 'assets/app-abc.js', 'isEntry' => true],
        ]);
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'prod',
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app');

        $this->assertStringContainsString('<script type="module"', $output);
        $this->assertStringContainsString('/build/assets/app-abc.js', $output);
    }

    public function testProdOutputOrderIsModulepreloadThenStylesheetThenScript(): void
    {
        $manifest = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'css'     => ['assets/app-def.css'],
                'imports' => ['_chunk.js'],
            ],
            '_chunk.js' => ['file' => 'assets/chunk-xyz.js'],
        ]);
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'prod',
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app');

        $preloadPos    = strpos($output, 'modulepreload');
        $stylesheetPos = strpos($output, 'stylesheet');
        $scriptPos     = strpos($output, '<script');

        $this->assertNotFalse($preloadPos);
        $this->assertNotFalse($stylesheetPos);
        $this->assertNotFalse($scriptPos);
        $this->assertLessThan($stylesheetPos, $preloadPos);
        $this->assertLessThan($scriptPos, $stylesheetPos);
    }

    public function testSharedChunkModulepreloadNotDuplicated(): void
    {
        $manifest = $this->makeTempManifest([
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
            '_shared.js' => ['file' => 'assets/shared-ghi.js'],
        ]);
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'prod',
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => [
                'app'   => 'resources/js/app.js',
                'admin' => 'resources/js/admin.js',
            ],
        ]));

        $combined = $service->tags('app') . $service->tags('admin');

        $this->assertSame(1, substr_count($combined, 'assets/shared-ghi.js'));
    }

    public function testDevModeTagsWhenSentinelExists(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig([
            'devServerUrl' => 'http://localhost:5173',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app');

        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/@vite/client">', $output);
        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/resources/js/app.js">', $output);
        $this->assertStringNotContainsString('modulepreload', $output);
        $this->assertStringNotContainsString('stylesheet', $output);
    }

    public function testForceModDevReturnDevTags(): void
    {
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'dev',
            'devServerUrl' => 'http://localhost:5173',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app');

        $this->assertStringContainsString('/@vite/client', $output);
        $this->assertStringContainsString('resources/js/app.js', $output);
    }

    public function testHmrClientEmittedOnlyOnceAcrossMultipleTagsCalls(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig([
            'devServerUrl' => 'http://localhost:5173',
            'entryPoints'  => [
                'app'   => 'resources/js/app.js',
                'admin' => 'resources/js/admin.js',
            ],
        ]));

        $combined = $service->tags('app') . $service->tags('admin');

        $this->assertSame(1, substr_count($combined, '/@vite/client'));
        $this->assertStringContainsString('resources/js/app.js', $combined);
        $this->assertStringContainsString('resources/js/admin.js', $combined);
    }

    public function testThrowsWhenNeitherSentinelNorManifestExists(): void
    {
        $service = new ViteService($this->makeConfig([
            'manifestPath' => '/nonexistent/manifest.json',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $this->expectException(KindlingException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');
        $this->expectExceptionMessageMatches('/npm run dev/');

        $service->tags('app');
    }

    public function testNonceAppearsOnAllScriptTagsInDevMode(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig([
            'devServerUrl' => 'http://localhost:5173',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app', 'nonce="abc123"');

        $this->assertStringContainsString('nonce="abc123"', $output);
        $this->assertSame(2, substr_count($output, 'nonce="abc123"'));
    }

    public function testNonceAppearsOnScriptTagInProdMode(): void
    {
        $manifest = $this->makeTempManifest([
            'resources/js/app.js' => ['file' => 'assets/app-abc.js', 'isEntry' => true],
        ]);
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'prod',
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app', 'nonce="abc123"');

        $this->assertStringContainsString('nonce="abc123"', $output);
    }

    public function testNullNonceProducesNoNonceAttribute(): void
    {
        touch($this->sentinelPath);
        $service = new ViteService($this->makeConfig([
            'devServerUrl' => 'http://localhost:5173',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app', null);

        $this->assertStringNotContainsString('nonce', $output);
    }

    public function testNonceDoesNotAppearOnLinkTags(): void
    {
        $manifest = $this->makeTempManifest([
            'resources/js/app.js' => [
                'file'    => 'assets/app-abc.js',
                'isEntry' => true,
                'css'     => ['assets/app-def.css'],
                'imports' => ['_chunk.js'],
            ],
            '_chunk.js' => ['file' => 'assets/chunk-xyz.js'],
        ]);
        $service = new ViteService($this->makeConfig([
            'forceMode'    => 'prod',
            'manifestPath' => $manifest,
            'buildPath'    => '/build',
            'entryPoints'  => ['app' => 'resources/js/app.js'],
        ]));

        $output = $service->tags('app', 'nonce="abc123"');

        preg_match_all('/<link[^>]+nonce[^>]*>/', $output, $matches);
        $this->assertEmpty($matches[0], 'No <link> tag should have a nonce attribute');
    }

    public function testUnknownEntryThrowsWithValidNamesList(): void
    {
        $service = new ViteService($this->makeConfig([
            'entryPoints' => ['app' => 'resources/js/app.js', 'admin' => 'resources/js/admin.js'],
        ]));

        $this->expectException(KindlingException::class);
        $this->expectExceptionMessageMatches('/Kindle:/');
        $this->expectExceptionMessageMatches('/nope/');
        $this->expectExceptionMessageMatches('/app/');
        $this->expectExceptionMessageMatches('/admin/');

        $service->tags('nope');
    }
}
