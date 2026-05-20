<?php

declare(strict_types=1);

namespace Myth\Kindling\Config;

use CodeIgniter\Config\BaseService;
use Myth\Kindling\Services\ViteService;

/**
 * Registers myth/kindling services with the CI4 service container.
 *
 * CI4 discovers this class automatically via namespace scanning — no manual registration needed.
 */
class Services extends BaseService
{
    /**
     * Returns the shared ViteService singleton.
     *
     * @param bool $getShared Whether to return the shared instance (default true).
     */
    public static function vite(bool $getShared = true): ViteService
    {
        if ($getShared) {
            return static::getSharedInstance('vite');
        }

        return new ViteService(config('Kindling'));
    }
}
