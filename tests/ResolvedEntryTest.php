<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Kindling\Services\ResolvedEntry;

/**
 * @internal
 */
final class ResolvedEntryTest extends CIUnitTestCase
{
    public function testExposesProperties(): void
    {
        $entry = new ResolvedEntry(
            file: 'assets/app-BfSu3lAp.js',
            css: ['assets/app-Cx3lAp.css'],
            imports: ['assets/chunk-DxY1.js'],
        );

        $this->assertSame('assets/app-BfSu3lAp.js', $entry->file);
        $this->assertSame(['assets/app-Cx3lAp.css'], $entry->css);
        $this->assertSame(['assets/chunk-DxY1.js'], $entry->imports);
    }
}
