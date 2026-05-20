<?php

declare(strict_types=1);

namespace Myth\Kindling\Exceptions;

/**
 * Exception for Vite manifest-specific errors.
 *
 * Thrown when the manifest file is missing or an entry key cannot be resolved.
 */
class KindlingManifestException extends KindlingException
{
    /**
     * Thrown when the manifest.json file does not exist at the configured path.
     */
    public static function forMissingManifest(string $path): self
    {
        return new self("Kindle: No manifest found at '{$path}'. Run 'npm run build' to generate it.");
    }

    /**
     * Thrown when a requested entry key is absent from the parsed manifest.
     *
     * @param list<string> $valid
     */
    public static function forMissingEntry(string $key, array $valid): self
    {
        return new self("Kindle: Entry point '{$key}' not found in manifest. Defined entry points are: " . implode(', ', $valid) . '.');
    }
}
