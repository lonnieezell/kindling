<?php

declare(strict_types=1);

namespace Myth\Kindling\Exceptions;

use RuntimeException;

/**
 * Base exception for all myth/kindling errors.
 *
 * All messages are prefixed with "Kindle:" and include actionable instructions.
 */
class KindlingException extends RuntimeException
{
    /**
     * Thrown when neither a Vite dev server sentinel nor a built manifest is found.
     */
    public static function forNoViteRunning(): self
    {
        return new self("Kindle: No Vite dev server running and no manifest found. Run 'npm run dev' or 'npm run build'.");
    }

    /**
     * Thrown when an unknown entry point name is passed to vite_tags().
     *
     * @param list<string> $valid
     */
    public static function forUnknownEntry(string $name, array $valid): self
    {
        return new self("Kindle: Unknown entry point '{$name}'. Defined entry points are: " . implode(', ', $valid) . '.');
    }
}
