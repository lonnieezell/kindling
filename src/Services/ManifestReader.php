<?php

declare(strict_types=1);

namespace Myth\Kindling\Services;

use Myth\Kindling\Exceptions\KindlingManifestException;

/**
 * Parses Vite's manifest.json and resolves an entry point into a ResolvedEntry DTO.
 *
 * Performs depth-first traversal of the imports graph, collecting CSS paths from
 * all visited nodes. Caches the decoded manifest for the lifetime of the instance.
 */
class ManifestReader
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $manifest = null;

    public function __construct(private readonly string $manifestPath)
    {
    }

    /**
     * Resolves a manifest key into a ResolvedEntry with its file, CSS, and chunk imports.
     *
     * @throws KindlingManifestException if the manifest is missing, invalid, or the key is absent.
     */
    public function resolve(string $manifestKey): ResolvedEntry
    {
        $manifest = $this->loadManifest();

        if (! array_key_exists($manifestKey, $manifest)) {
            throw KindlingManifestException::forMissingEntry($manifestKey, array_keys($manifest));
        }

        $visited = [];
        $css     = [];
        $imports = [];

        $this->traverse($manifestKey, $manifest, $visited, $css, $imports);

        /** @var array{file: string} $entryNode */
        $entryNode = $manifest[$manifestKey];
        $file      = $entryNode['file'];

        return new ResolvedEntry($file, $css, $imports);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, bool>  $visited
     * @param list<string>         $css
     * @param list<string>         $imports
     */
    private function traverse(
        string $key,
        array $manifest,
        array &$visited,
        array &$css,
        array &$imports,
    ): void {
        if (isset($visited[$key])) {
            return;
        }

        $visited[$key] = true;
        /** @var array{file?: string, imports?: list<string>, css?: list<string>} $node */
        $node = $manifest[$key];

        foreach ($node['imports'] ?? [] as $chunkKey) {
            if (! array_key_exists($chunkKey, $manifest)) {
                throw KindlingManifestException::forMissingEntry($chunkKey, array_keys($manifest));
            }

            $alreadyVisited = isset($visited[$chunkKey]);
            $this->traverse($chunkKey, $manifest, $visited, $css, $imports);

            /** @var array{file?: string} $chunkNode */
            $chunkNode = $manifest[$chunkKey];

            if (! $alreadyVisited && isset($chunkNode['file'])) {
                $imports[] = $chunkNode['file'];
            }
        }

        foreach ($node['css'] ?? [] as $cssFile) {
            $css[] = $cssFile;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (! file_exists($this->manifestPath)) {
            throw KindlingManifestException::forMissingManifest($this->manifestPath);
        }

        $decoded = json_decode(file_get_contents($this->manifestPath), true);

        if (! is_array($decoded)) {
            throw KindlingManifestException::forInvalidManifest($this->manifestPath);
        }

        return $this->manifest = $decoded;
    }
}
