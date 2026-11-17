<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\PilotBundle\Twig\PilotMarkdownExtension;
use PHPUnit\Framework\TestCase;

class PilotMarkdownExtensionTest extends TestCase
{
    // A table becomes a table, typed HTML stays text and a javascript link is dropped
    public function testTablesRenderAndHtmlIsEscaped(): void
    {
        $html = new PilotMarkdownExtension()->convert("| Item | Value |\n| --- | --- |\n| catalog | 1 |\n\n<script>x</script> [link](javascript:alert(1))");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<td>catalog</td>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }
}
