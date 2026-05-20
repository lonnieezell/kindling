<?php

declare(strict_types=1);
if (! function_exists('vite_tags')) {
    function vite_tags(string $entry, ?string $nonceAttr = null): string
    {
        return service('vite')->tags($entry, $nonceAttr);
    }
}
