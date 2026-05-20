<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Myth\Kindling\Commands\KindlingInstall;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tests for the kindling:install Spark command.
 *
 * Each test uses a fresh temporary directory as the project root to avoid
 * touching real filesystem paths.
 *
 * @internal
 */
final class KindlingInstallCommandTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private string $tmpDir;
    private KindlingInstall $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpStreamFilterTrait();

        $this->tmpDir = sys_get_temp_dir() . '/kindling-install-test-' . uniqid();
        mkdir($this->tmpDir, 0o755, true);

        $this->command = new KindlingInstall($this->createStub(LoggerInterface::class), $this->createStub(Commands::class));
        $this->command->setRootPath($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->tearDownStreamFilterTrait();
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Tracer bullet: clean project with single entry point
    // -------------------------------------------------------------------------

    public function testGeneratesAllExpectedFilesOnCleanProject(): void
    {
        $this->command->run(['entry' => 'app']);

        $this->assertFileExists($this->tmpDir . '/vite.config.js');
        $this->assertFileExists($this->tmpDir . '/resources/js/app.js');
        $this->assertFileExists($this->tmpDir . '/resources/css/app.css');
        $this->assertFileExists($this->tmpDir . '/app/Config/Kindling.php');
        $this->assertFileExists($this->tmpDir . '/.gitignore');
    }

    // -------------------------------------------------------------------------
    // Multiple entry points
    // -------------------------------------------------------------------------

    public function testMultipleEntryPointsProduceMultipleStubs(): void
    {
        $this->command->run(['entry' => 'app,admin']);

        $this->assertFileExists($this->tmpDir . '/resources/js/app.js');
        $this->assertFileExists($this->tmpDir . '/resources/js/admin.js');
        $this->assertFileExists($this->tmpDir . '/resources/css/app.css');
        $this->assertFileExists($this->tmpDir . '/resources/css/admin.css');
    }

    // -------------------------------------------------------------------------
    // App config entry point map
    // -------------------------------------------------------------------------

    public function testAppConfigContainsEntryPointsFromFlag(): void
    {
        $this->command->run(['entry' => 'app,admin']);

        $contents = file_get_contents($this->tmpDir . '/app/Config/Kindling.php');

        $this->assertStringContainsString("'app' => 'resources/js/app.js'", (string) $contents);
        $this->assertStringContainsString("'admin' => 'resources/js/admin.js'", (string) $contents);
    }

    // -------------------------------------------------------------------------
    // Idempotency: existing files skipped with warning
    // -------------------------------------------------------------------------

    public function testExistingViteConfigNotOverwrittenOnSecondRun(): void
    {
        $this->command->run(['entry' => 'app']);

        file_put_contents($this->tmpDir . '/vite.config.js', '// custom');

        $this->resetStreamFilterBuffer();
        $this->command->run(['entry' => 'app']);

        $this->assertSame('// custom', file_get_contents($this->tmpDir . '/vite.config.js'));
        $this->assertStringContainsString('vite.config.js', $this->getStreamFilterBuffer());
    }

    // -------------------------------------------------------------------------
    // .gitignore deduplication
    // -------------------------------------------------------------------------

    public function testGitignoreEntriesNotDuplicatedOnSecondRun(): void
    {
        $this->command->run(['entry' => 'app']);
        $this->command->run(['entry' => 'app']);

        $contents = file_get_contents($this->tmpDir . '/.gitignore');

        $this->assertSame(1, substr_count($contents, 'node_modules/'));
        $this->assertSame(1, substr_count($contents, 'public/build/'));
    }

    // -------------------------------------------------------------------------
    // package.json generation
    // -------------------------------------------------------------------------

    public function testPackageJsonWrittenOnCleanProject(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true]);

        $this->assertFileExists($this->tmpDir . '/package.json');
    }

    public function testPackageJsonContainsTailwindDependencyWhenTailwindSelected(): void
    {
        $this->command->run(['entry' => 'app', 'tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/package.json');

        $this->assertStringContainsString('@tailwindcss/vite', (string) $contents);
    }

    public function testPackageJsonOmitsTailwindDependencyWhenNoTailwindSelected(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/package.json');

        $this->assertStringNotContainsString('@tailwindcss/vite', (string) $contents);
    }

    // -------------------------------------------------------------------------
    // package.json skipped with instructions
    // -------------------------------------------------------------------------

    public function testPackageJsonSkippedWithNpmInstructionsWhenItAlreadyExists(): void
    {
        file_put_contents($this->tmpDir . '/package.json', '{}');

        $this->command->run(['entry' => 'app']);

        $this->assertSame('{}', file_get_contents($this->tmpDir . '/package.json'));
        $this->assertStringContainsString('npm install --save-dev vite', $this->getStreamFilterBuffer());
    }

    // -------------------------------------------------------------------------
    // Tailwind: --tailwind flag
    // -------------------------------------------------------------------------

    public function testViteConfigIncludesTailwindPluginWhenTailwindFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/vite.config.js');

        $this->assertStringContainsString("import tailwindcss from '@tailwindcss/vite'", (string) $contents);
        $this->assertStringContainsString('tailwindcss()', (string) $contents);
    }

    public function testCssEntryContainsTailwindImportWhenTailwindFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/resources/css/app.css');

        $this->assertStringContainsString('@import "tailwindcss"', (string) $contents);
    }

    public function testNpmInstructionsIncludeTailwindPackageWhenTailwindFlagSet(): void
    {
        file_put_contents($this->tmpDir . '/package.json', '{}');

        $this->command->run(['entry' => 'app', 'tailwind' => true]);

        $this->assertStringContainsString('@tailwindcss/vite', $this->getStreamFilterBuffer());
    }

    // -------------------------------------------------------------------------
    // Tailwind: --no-tailwind flag
    // -------------------------------------------------------------------------

    public function testViteConfigOmitsTailwindPluginWhenNoTailwindFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/vite.config.js');

        $this->assertStringNotContainsString('tailwindcss', (string) $contents);
    }

    public function testCssEntryIsEmptyWhenNoTailwindFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true]);

        $contents = file_get_contents($this->tmpDir . '/resources/css/app.css');

        $this->assertStringNotContainsString('@import', (string) $contents);
    }

    // -------------------------------------------------------------------------
    // Alpine: --alpine flag
    // -------------------------------------------------------------------------

    public function testPackageJsonContainsAlpineDependencyWhenAlpineFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'alpine' => true, 'no-htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/package.json');

        $this->assertStringContainsString('alpinejs', $contents);
    }

    public function testJsEntryContainsAlpineImportsWhenAlpineFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'alpine' => true, 'no-htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/resources/js/app.js');

        $this->assertStringContainsString("import Alpine from 'alpinejs'", $contents);
        $this->assertStringContainsString('window.Alpine = Alpine', $contents);
        $this->assertStringContainsString('Alpine.start()', $contents);
    }

    // -------------------------------------------------------------------------
    // HTMX: --htmx flag
    // -------------------------------------------------------------------------

    public function testJsEntryContainsHtmxImportWhenHtmxFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'no-alpine' => true, 'htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/resources/js/app.js');

        $this->assertStringContainsString("import 'htmx.org'", $contents);
    }

    public function testPackageJsonContainsHtmxDependencyWhenHtmxFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'no-alpine' => true, 'htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/package.json');

        $this->assertStringContainsString('htmx.org', $contents);
    }

    // -------------------------------------------------------------------------
    // --no-alpine / --no-htmx flags
    // -------------------------------------------------------------------------

    public function testJsEntryHasNoAlpineWhenNoAlpineFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'no-alpine' => true, 'no-htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/resources/js/app.js');

        $this->assertStringNotContainsString('alpinejs', $contents);
    }

    public function testJsEntryHasNoHtmxWhenNoHtmxFlagSet(): void
    {
        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'no-alpine' => true, 'no-htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/resources/js/app.js');

        $this->assertStringNotContainsString('htmx', $contents);
    }

    // -------------------------------------------------------------------------
    // Combinations
    // -------------------------------------------------------------------------

    public function testAllThreeFlagsCombineCorrectlyInPackageJson(): void
    {
        $this->command->run(['entry' => 'app', 'tailwind' => true, 'alpine' => true, 'htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/package.json');

        $this->assertStringContainsString('@tailwindcss/vite', $contents);
        $this->assertStringContainsString('alpinejs', $contents);
        $this->assertStringContainsString('htmx.org', $contents);
    }

    public function testAllThreeFlagsCombineCorrectlyInJsEntry(): void
    {
        $this->command->run(['entry' => 'app', 'tailwind' => true, 'alpine' => true, 'htmx' => true]);

        $contents = (string) file_get_contents($this->tmpDir . '/resources/js/app.js');

        $this->assertStringContainsString("import Alpine from 'alpinejs'", $contents);
        $this->assertStringContainsString("import 'htmx.org'", $contents);
    }

    // -------------------------------------------------------------------------
    // npm hint with dynamic packages
    // -------------------------------------------------------------------------

    public function testNpmHintListsOnlySelectedPackagesWhenPackageJsonExists(): void
    {
        file_put_contents($this->tmpDir . '/package.json', '{}');

        $this->command->run(['entry' => 'app', 'no-tailwind' => true, 'alpine' => true, 'htmx' => true]);

        $output = $this->getStreamFilterBuffer();

        $this->assertStringContainsString('alpinejs', $output);
        $this->assertStringContainsString('htmx.org', $output);
        $this->assertStringNotContainsString('@tailwindcss/vite', $output);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
