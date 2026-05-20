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
    private array $emittedChunks = [];

    public function __construct(private readonly Kindling $config)
    {
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
     * @param string      $entry Named entry point key from Config\Kindling::$entryPoints.
     * @param string|null $nonce Optional CSP nonce applied to all script tags.
     *
     * @throws KindlingException if the entry name is unknown or no Vite output is available.
     */
    public function tags(string $entry, ?string $nonce = null): string
    {
        if (! array_key_exists($entry, $this->config->entryPoints)) {
            throw KindlingException::forUnknownEntry($entry, array_keys($this->config->entryPoints));
        }

        $manifestKey = $this->config->entryPoints[$entry];
        $reader      = new ManifestReader($this->config->manifestPath);
        $resolved    = $reader->resolve($manifestKey);
        $build       = rtrim($this->config->buildPath, '/');
        $tags        = '';

        foreach ($resolved->imports as $chunk) {
            if (! in_array($chunk, $this->emittedChunks, true)) {
                $this->emittedChunks[] = $chunk;
                $tags .= '<link rel="modulepreload" href="' . $build . '/' . $chunk . '">' . "\n";
            }
        }

        foreach ($resolved->css as $css) {
            $tags .= '<link rel="stylesheet" href="' . $build . '/' . $css . '">' . "\n";
        }

        $nonceAttr = $nonce !== null ? ' nonce="' . $nonce . '"' : '';

        return $tags . ('<script type="module" src="' . $build . '/' . $resolved->file . '"' . $nonceAttr . "></script>\n");
    }
}
