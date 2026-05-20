<?php

declare(strict_types=1);

namespace Myth\Kindling\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Base configuration for myth/kindling.
 *
 * Extend this class in app/Config/Kindling.php to override defaults.
 * $buildPath and $manifestPath must be kept in sync with build.outDir in vite.config.js.
 */
class Kindling extends BaseConfig
{
    public string $devServerUrl = 'http://localhost:5173';
    public string $manifestPath = FCPATH . 'build/.vite/manifest.json';
    public string $sentinelPath = FCPATH . 'build/.vite-dev-running';
    public string $buildPath    = '/build';
    public ?string $forceMode   = null;
    public array $entryPoints   = [];
}
