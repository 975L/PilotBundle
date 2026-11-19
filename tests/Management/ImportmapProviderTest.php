<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\PilotBundle\Management\ImportmapProvider;
use PHPUnit\Framework\TestCase;

class ImportmapProviderTest extends TestCase
{
    // The script of the piloting pages, loaded by their own shell, has its importmap entry declared, the site's pages getting none
    public function testThePilotScriptHasItsImportmapEntry(): void
    {
        $entries = new ImportmapProvider()->getAdminImportmapEntries();

        $this->assertSame(['path' => 'assets/controllers-admin.js', 'entrypoint' => true], $entries['@c975l/pilot-bundle/controllers-admin.js']);
        $this->assertSame([], new ImportmapProvider()->getImportmapEntries());
    }
}
