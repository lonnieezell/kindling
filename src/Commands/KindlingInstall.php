<?php

declare(strict_types=1);

namespace Myth\Kindling\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Spark command that scaffolds a complete Vite asset pipeline for a CI4 project.
 *
 * Generates vite.config.js, entry point stubs, app/Config/Kindling.php, and
 * updates .gitignore. All file writes are idempotent — existing files are skipped
 * with a warning rather than overwritten.
 */
class KindlingInstall extends BaseCommand
{
    protected $group       = 'Kindling';
    protected $name        = 'kindling:install';
    protected $usage       = 'kindling:install [options]';
    protected $description = 'Scaffold a complete Vite asset pipeline for CodeIgniter 4.';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--entry'       => 'Comma-separated entry point names (default: app)',
        '--tailwind'    => 'Include Tailwind CSS v4 integration',
        '--no-tailwind' => 'Skip Tailwind CSS v4 integration',
    ];

    private string $rootPath;
    private readonly string $stubsPath;

    public function __construct($logger, $commands)
    {
        parent::__construct($logger, $commands);
        $this->rootPath  = ROOTPATH;
        $this->stubsPath = dirname(__DIR__, 2) . '/stubs';
    }

    /**
     * Override the project root used for file generation.
     *
     * Intended for use in tests only.
     */
    public function setRootPath(string $path): void
    {
        $this->rootPath = rtrim($path, '/\\');
    }

    /**
     * Execute the install command.
     *
     * @param array<int|string, string|bool|null> $params
     */
    public function run(array $params): void
    {
        $entries  = $this->resolveEntryNames($params);
        $tailwind = $this->resolveTailwind($params);

        $this->writeViteConfig($entries, $tailwind);
        $this->handlePackageJson($tailwind);
        $this->writeEntryFiles($entries, $tailwind);
        $this->writeAppConfig($entries);
        $this->updateGitignore();

        CLI::newLine();
        CLI::write('Kindling install complete. Run `npm install` then `npm run dev` to start.', 'green');
    }

    /**
     * Resolve the list of entry point names from params or interactive prompt.
     *
     * @param array<int|string, string|bool|null> $params
     *
     * @return list<string>
     */
    private function resolveEntryNames(array $params): array
    {
        $option = $params['entry'] ?? CLI::getOption('entry');

        if ($option !== null && $option !== true) {
            $raw = (string) $option;
        } else {
            $raw = CLI::prompt('Entry points (comma-separated)', ['app']);
        }

        $names = array_filter(array_map(trim(...), explode(',', $raw)));

        return $names !== [] ? array_values($names) : ['app'];
    }

    /**
     * Resolve whether to include Tailwind CSS v4 from flags or interactive prompt.
     *
     * @param array<int|string, string|bool|null> $params
     */
    private function resolveTailwind(array $params): bool
    {
        if (isset($params['tailwind'])) {
            return (bool) $params['tailwind'];
        }

        $noTailwind = $params['no-tailwind'] ?? CLI::getOption('no-tailwind');

        if ($noTailwind !== null) {
            return false;
        }

        $withTailwind = CLI::getOption('tailwind');

        if ($withTailwind !== null) {
            return true;
        }

        return CLI::prompt('Include Tailwind CSS v4?', ['y', 'n']) === 'y';
    }

    /**
     * Write vite.config.js from stub. Skipped if the file already exists.
     *
     * @param list<string> $entries
     */
    private function writeViteConfig(array $entries, bool $tailwind): void
    {
        $target = $this->rootPath . '/vite.config.js';

        if (file_exists($target)) {
            CLI::write('  Skipped  vite.config.js (already exists)', 'yellow');

            return;
        }

        $inputs = implode(', ', array_map(
            static fn (string $e) => "'{$e}'",
            array_map(static fn (string $n) => "resources/js/{$n}.js", $entries),
        ));

        $stubFile = $tailwind ? 'vite.config.tailwind.js.stub' : 'vite.config.js.stub';
        $stub     = (string) file_get_contents($this->stubsPath . '/' . $stubFile);
        $contents = str_replace('{entry_inputs}', $inputs, $stub);

        file_put_contents($target, $contents);
        CLI::write('  Created  vite.config.js', 'green');
    }

    /**
     * Write package.json from stub, or skip with npm install instructions if it already exists.
     */
    private function handlePackageJson(bool $tailwind): void
    {
        $target   = $this->rootPath . '/package.json';
        $packages = $tailwind ? 'vite @tailwindcss/vite' : 'vite';

        if (file_exists($target)) {
            CLI::write("  Skipped  package.json (already exists). Run: npm install --save-dev {$packages}", 'yellow');

            return;
        }

        $stubFile = $tailwind ? 'package.tailwind.json.stub' : 'package.json.stub';
        file_put_contents($target, file_get_contents($this->stubsPath . '/' . $stubFile));
        CLI::write('  Created  package.json', 'green');
        CLI::write("           Run: npm install", 'green');
    }

    /**
     * Write one JS and one CSS stub per entry point. Skips files that exist.
     *
     * @param list<string> $entries
     */
    private function writeEntryFiles(array $entries, bool $tailwind): void
    {
        $jsStub  = (string) file_get_contents($this->stubsPath . '/resources/js/entry.js.stub');
        $cssStub = (string) file_get_contents(
            $this->stubsPath . '/resources/css/' . ($tailwind ? 'entry.tailwind.css.stub' : 'entry.css.stub'),
        );

        foreach ($entries as $name) {
            $this->writeFile("resources/js/{$name}.js", $jsStub);
            $this->writeFile("resources/css/{$name}.css", $cssStub);
        }
    }

    /**
     * Write app/Config/Kindling.php from stub. Skipped if the file already exists.
     *
     * @param list<string> $entries
     */
    private function writeAppConfig(array $entries): void
    {
        $target = $this->rootPath . '/app/Config/Kindling.php';

        if (file_exists($target)) {
            CLI::write('  Skipped  app/Config/Kindling.php (already exists)', 'yellow');

            return;
        }

        $lines = array_map(
            static fn (string $n) => "        '{$n}' => 'resources/js/{$n}.js',",
            $entries,
        );

        $stub     = (string) file_get_contents($this->stubsPath . '/Config/Kindling.php.stub');
        $contents = str_replace('{entry_map}', implode("\n", $lines), $stub);

        $this->ensureDir(dirname($target));
        file_put_contents($target, $contents);
        CLI::write('  Created  app/Config/Kindling.php', 'green');
    }

    /**
     * Append node_modules/ and public/build/ to .gitignore if not already present.
     */
    private function updateGitignore(): void
    {
        $target = $this->rootPath . '/.gitignore';

        $existing = file_exists($target) ? file_get_contents($target) : '';
        $lines    = $existing !== false ? explode("\n", $existing) : [];

        $toAppend = [];

        foreach (['node_modules/', 'public/build/'] as $entry) {
            if (! in_array($entry, $lines, true)) {
                $toAppend[] = $entry;
            }
        }

        if ($toAppend === []) {
            return;
        }

        $prefix = ($existing !== '' && ! str_ends_with((string) $existing, "\n")) ? "\n" : '';
        file_put_contents($target, $existing . $prefix . implode("\n", $toAppend) . "\n", FILE_APPEND);
        CLI::write('  Updated  .gitignore', 'green');
    }

    /**
     * Write a file relative to the root path. Skips if it already exists.
     */
    private function writeFile(string $relativePath, string $contents): void
    {
        $target = $this->rootPath . '/' . $relativePath;

        if (file_exists($target)) {
            CLI::write("  Skipped  {$relativePath} (already exists)", 'yellow');

            return;
        }

        $this->ensureDir(dirname($target));
        file_put_contents($target, $contents);
        CLI::write("  Created  {$relativePath}", 'green');
    }

    /**
     * Create a directory and all parents if they do not exist.
     */
    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
    }
}
