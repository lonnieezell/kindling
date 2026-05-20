<?php

declare(strict_types=1);

namespace Myth\Kindling\Services;

use Myth\Kindling\Config\Kindling;
use Myth\Kindling\Exceptions\KindlingException;

/**
 * Main service for emitting Vite asset tags in CI4 views.
 *
 * Registered as a shared singleton via Config\Services::vite(). Detects dev vs prod
 * using a sentinel file, then emits the appropriate script/link tags. Deduplicates
 * the HMR client and shared chunk preloads across multiple tags() calls per request.
 */
class ViteService
{
    private bool $hmrClientEmitted = false;
    private array $emittedChunks   = [];

    public function __construct(private readonly Kindling $config)
    {
    }

    /**
     * Emits dev-mode script tags for HMR client and the entry source file.
     *
     * @param string      $entry     Named entry point key.
     * @param string|null $nonceAttr Full nonce attribute string (e.g. from csp_script_nonce()) or a CI4 CSP
     *                               placeholder (e.g. '{csp-script-nonce}'). Applied to all script tags.
     */
    private function devTags(string $entry, ?string $nonceAttr = null): string
    {
        $base      = rtrim($this->config->devServerUrl, '/');
        $nonceAttr = $nonceAttr !== null ? ' ' . $nonceAttr : '';
        $tags      = '';

        if (! $this->hmrClientEmitted) {
            $tags .= '<script type="module" src="' . $base . '/@vite/client"' . $nonceAttr . "></script>\n";
            $this->hmrClientEmitted = true;
        }

        return $tags . '<script type="module" src="' . $base . '/' . $this->config->entryPoints[$entry] . '"' . $nonceAttr . "></script>\n";
    }

    /**
     * Returns true when the Vite dev server is running.
     *
     * Checks $forceMode first; falls back to the presence of the sentinel file.
     */
    public function isDevMode(): bool
    {
        if ($this->config->forceMode !== null) {
            return $this->config->forceMode === 'dev';
        }

        return file_exists($this->config->sentinelPath);
    }

    /**
     * Emits script and link tags for the given named entry point.
     *
     * @param string      $entry     Named entry point key from Config\Kindling::$entryPoints.
     * @param string|null $nonceAttr Full nonce attribute string (e.g. from csp_script_nonce()) or a CI4 CSP
     *                               placeholder (e.g. '{csp-script-nonce}'). Applied to all script tags.
     *
     * @throws KindlingException if the entry name is unknown or no Vite output is available.
     */
    public function tags(string $entry, ?string $nonceAttr = null): string
    {
        if (! array_key_exists($entry, $this->config->entryPoints)) {
            throw KindlingException::forUnknownEntry($entry, array_keys($this->config->entryPoints));
        }

        if ($this->config->forceMode === null
            && ! file_exists($this->config->sentinelPath)
            && ! file_exists($this->config->manifestPath)
        ) {
            throw KindlingException::forNoViteRunning();
        }

        if ($this->isDevMode()) {
            return $this->devTags($entry, $nonceAttr);
        }

        $manifestKey   = $this->config->entryPoints[$entry];
        $reader        = new ManifestReader($this->config->manifestPath);
        $resolved      = $reader->resolve($manifestKey);
        $build         = rtrim($this->config->buildPath, '/');
        $tags          = '';
        $nonceAttrStr  = $nonceAttr !== null ? ' ' . $nonceAttr : '';

        foreach ($resolved->imports as $chunk) {
            if (! in_array($chunk, $this->emittedChunks, true)) {
                $this->emittedChunks[] = $chunk;
                $tags .= '<link rel="modulepreload" href="' . $build . '/' . $chunk . '">' . "\n";
            }
        }

        foreach ($resolved->css as $css) {
            $tags .= '<link rel="stylesheet" href="' . $build . '/' . $css . '">' . "\n";
        }

        return $tags . ('<script type="module" src="' . $build . '/' . $resolved->file . '"' . $nonceAttrStr . "></script>\n");
    }
}
