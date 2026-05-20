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
        '--alpine'      => 'Include Alpine.js',
        '--no-alpine'   => 'Skip Alpine.js',
        '--htmx'        => 'Include HTMX v2',
        '--no-htmx'     => 'Skip HTMX v2',
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
     * @param array<int|string, bool|string|null> $params
     */
    public function run(array $params): void
    {
        $entries  = $this->resolveEntryNames($params);
        $tailwind = $this->resolveTailwind($params);
        $alpine   = $this->resolveAlpine($params);
        $htmx     = $this->resolveHtmx($params);

        $this->writeViteConfig($entries, $tailwind);
        $this->handlePackageJson($tailwind, $alpine, $htmx);
        $this->writeEntryFiles($entries, $tailwind, $alpine, $htmx);
        $this->writeAppConfig($entries);
        $this->updateGitignore();

        CLI::newLine();
        CLI::write('Kindling install complete. Run `npm install` then `npm run dev` to start.', 'green');
    }

    /**
     * Resolve the list of entry point names from params or interactive prompt.
     *
     * @param array<int|string, bool|string|null> $params
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
     * Resolve whether to include Alpine.js from flags or interactive prompt.
     *
     * @param array<int|string, bool|string|null> $params
     */
    private function resolveAlpine(array $params): bool
    {
        if (isset($params['alpine'])) {
            return (bool) $params['alpine'];
        }

        if ($params['no-alpine'] ?? CLI::getOption('no-alpine')) {
            return false;
        }

        if (CLI::getOption('alpine')) {
            return true;
        }

        return CLI::prompt('Include Alpine.js?', ['y', 'n']) === 'y';
    }

    /**
     * Resolve whether to include HTMX v2 from flags or interactive prompt.
     *
     * @param array<int|string, bool|string|null> $params
     */
    private function resolveHtmx(array $params): bool
    {
        if (isset($params['htmx'])) {
            return (bool) $params['htmx'];
        }

        if ($params['no-htmx'] ?? CLI::getOption('no-htmx')) {
            return false;
        }

        if (CLI::getOption('htmx')) {
            return true;
        }

        return CLI::prompt('Include HTMX v2?', ['y', 'n']) === 'y';
    }

    /**
     * Resolve whether to include Tailwind CSS v4 from flags or interactive prompt.
     *
     * @param array<int|string, bool|string|null> $params
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
     * Write package.json dynamically, or skip with npm install instructions if it already exists.
     */
    private function handlePackageJson(bool $tailwind, bool $alpine, bool $htmx): void
    {
        $target = $this->rootPath . '/package.json';

        $devDeps = ['vite' => '^6.0'];

        if ($tailwind) {
            $devDeps['@tailwindcss/vite'] = '^4.0';
        }

        if ($alpine) {
            $devDeps['alpinejs'] = '^3.0';
        }

        if ($htmx) {
            $devDeps['htmx.org'] = '^2.0';
        }

        if (file_exists($target)) {
            $packageList = implode(' ', array_keys($devDeps));
            CLI::write("  Skipped  package.json (already exists). Run: npm install --save-dev {$packageList}", 'yellow');

            return;
        }

        $json = json_encode([
            'private'         => true,
            'type'            => 'module',
            'scripts'         => ['dev' => 'vite', 'build' => 'vite build'],
            'devDependencies' => $devDeps,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($target, $json);
        CLI::write('  Created  package.json', 'green');
        CLI::write('           Run: npm install', 'green');
    }

    /**
     * Write one JS and one CSS stub per entry point. Skips files that exist.
     *
     * @param list<string> $entries
     */
    private function writeEntryFiles(array $entries, bool $tailwind, bool $alpine, bool $htmx): void
    {
        $jsLines = [];

        if ($alpine) {
            $jsLines[] = "import Alpine from 'alpinejs'";
            $jsLines[] = 'window.Alpine = Alpine';
            $jsLines[] = 'Alpine.start()';
        }

        if ($htmx) {
            $jsLines[] = "import 'htmx.org'";
        }

        $jsContent = $jsLines !== [] ? implode("\n", $jsLines) . "\n" : '';
        $cssStub   = (string) file_get_contents(
            $this->stubsPath . '/resources/css/' . ($tailwind ? 'entry.tailwind.css.stub' : 'entry.css.stub'),
        );

        foreach ($entries as $name) {
            $this->writeFile("resources/js/{$name}.js", $jsContent);
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
