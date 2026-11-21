<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\PilotBundle\Twig\PilotIconExtension;
use PHPUnit\Framework\TestCase;

class PilotIconExtensionTest extends TestCase
{
    // An icon comes out as inline SVG with its classes, its drawing kept and the file's comment left out
    public function testAnIconIsInlineSvg(): void
    {
        $svg = new PilotIconExtension()->icon('trash', 'item-1 "bad"');

        $this->assertStringStartsWith('<svg class="pilot-icon pilot-icon-trash item-1 bad" aria-hidden="true" focusable="false" viewBox="0 0 448 512">', $svg);
        $this->assertStringContainsString('<path d="', $svg);
        $this->assertStringNotContainsString('<!--', $svg);
    }

    // Every icon of the folder comes out as a single opening tag, with or without a viewBox in its file
    public function testEveryIconIsOneTag(): void
    {
        foreach (glob(PilotIconExtension::DIRECTORY . '/*.svg') ?: [] as $file) {
            $svg = new PilotIconExtension()->icon(basename($file, '.svg'));
            $this->assertSame(1, substr_count($svg, '<svg'), basename($file));
            $this->assertMatchesRegularExpression('/^<svg class="[^"]*" aria-hidden="true" focusable="false"( viewBox="[^"]*")?>/', $svg);
        }
    }

    // A name that is not a file of the folder is refused, a path included
    public function testAnUnknownIconIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PilotIconExtension()->icon('../icons/trash');
    }

    // Every icon the templates, the scripts and the services name is a file of the folder
    public function testEveryIconNamedExists(): void
    {
        $root = \dirname(__DIR__, 2);
        $names = [];
        foreach ([...glob($root . '/templates/*/*.twig') ?: [], ...glob($root . '/templates/*/*/*.twig') ?: []] as $template) {
            preg_match_all("/pilot_icon\\('([a-z0-9-]+)'/", (string) file_get_contents($template), $found);
            $names = [...$names, ...$found[1]];
        }
        preg_match_all("/'icon' => '([a-z0-9-]+)'/", (string) file_get_contents($root . '/src/Service/SettingsKinds.php'), $found);
        $names = array_unique([...$names, ...$found[1]]);

        $this->assertNotEmpty($names);
        foreach ($names as $name) {
            $this->assertFileExists(PilotIconExtension::DIRECTORY . '/' . $name . '.svg');
        }
    }
}
