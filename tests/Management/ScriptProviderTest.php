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
use c975L\PilotBundle\Management\ScriptProvider;
use PHPUnit\Framework\TestCase;

class ScriptProviderTest extends TestCase
{
    // The script loaded on the dashboard is the one whose importmap entry is declared, the front-office getting none
    public function testTheAdminScriptHasItsImportmapEntry(): void
    {
        $entries = new ImportmapProvider()->getAdminImportmapEntries();

        $this->assertSame(['@c975l/pilot-bundle/controllers-admin.js'], new ScriptProvider()->getAdminScripts());
        $this->assertSame(['path' => 'assets/controllers-admin.js', 'entrypoint' => true], $entries['@c975l/pilot-bundle/controllers-admin.js']);
        $this->assertSame([], new ImportmapProvider()->getImportmapEntries());
    }
}
