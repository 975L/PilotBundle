<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\PilotBundle\Twig\PilotStylesheetExtension;
use PHPUnit\Framework\TestCase;

class PilotStylesheetExtensionTest extends TestCase
{
    // The compiled stylesheet ships with the bundle and its version is the time it was compiled
    public function testTheVersionIsTheStylesheetsTime(): void
    {
        $this->assertFileExists(PilotStylesheetExtension::FILE);
        $this->assertSame(filemtime(PilotStylesheetExtension::FILE), new PilotStylesheetExtension()->version());
    }
}
