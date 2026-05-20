<?php

declare(strict_types=1);

namespace Myth\Kindling\Services;

/**
 * Typed DTO representing a fully resolved Vite manifest entry point.
 *
 * All paths are raw manifest paths relative to the build root (e.g. assets/app-BfSu3lAp.js).
 * ViteService prepends $buildPath when emitting URLs.
 */
readonly class ResolvedEntry
{
    /**
     * @param string       $file    Primary JS file path from the manifest.
     * @param list<string> $css     Resolved CSS file paths (from all visited chunks).
     * @param list<string> $imports Resolved chunk file paths in depth-first order.
     */
    public function __construct(
        public string $file,
        public array $css,
        public array $imports,
    ) {
    }
}
