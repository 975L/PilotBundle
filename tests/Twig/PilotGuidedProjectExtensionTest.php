<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\ConfigBundle\Management\GuidedProjectMountBuilder;
use c975L\PilotBundle\Twig\PilotGuidedProjectExtension;
use PHPUnit\Framework\TestCase;

class PilotGuidedProjectExtensionTest extends TestCase
{
    // The piloting pages carry the very element the back office mounts the panel on, nothing of their own
    public function testTheMountIsConfigBundles(): void
    {
        $builder = $this->createStub(GuidedProjectMountBuilder::class);
        $builder->method('getHtml')->willReturn('<div data-controller="guided-project"></div>');

        $this->assertSame('<div data-controller="guided-project"></div>', new PilotGuidedProjectExtension($builder)->mount());
    }

    // The layout ends on the mount and loads ConfigBundle's script beside its own, the bundle's stylesheet drawing the panel, or it has nothing to come back on
    public function testTheLayoutMountsThePanel(): void
    {
        $layout = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/pilot/_layout.html.twig');

        $this->assertStringContainsString('{{ pilot_guided_project_mount() }}', $layout);
        $this->assertStringContainsString("'@c975l/config-bundle/controllers-admin.js'", $layout);
        $this->assertStringContainsString('.guided-project-panel {', (string) file_get_contents(\dirname(__DIR__, 2) . '/sass/_pages.scss'));
    }

    // Turbo Drive stays out of the piloting pages: it fails to merge their nonced <style>, a card's own page then never showing, which a guided project walks to
    public function testTheLayoutLeavesTurboOut(): void
    {
        $layout = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/pilot/_layout.html.twig');

        $this->assertStringContainsString('<body data-turbo="false">', $layout);
    }
}
