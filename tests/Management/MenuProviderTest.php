<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    private function provider(): MenuProvider
    {
        return new MenuProvider();
    }

    // The planning first, then the settings, each carrying what the onboarding tour reads
    public function testMenusAreThePlanningThenTheSettings(): void
    {
        $menus = $this->provider()->getMenus();

        $this->assertSame(['pilot_week', 'pilot_projects', 'pilot_templates', 'pilot_actions', 'pilot_machines', 'pilot_credits'], array_keys($menus));
        $this->assertSame(PilotController::class, $menus['pilot_week']['controller']);
        $this->assertSame('week', $menus['pilot_week']['action']);
        $this->assertTrue($menus['pilot_week']['first'] ?? false);
        foreach ($menus as $slug => $entry) {
            $this->assertStringStartsWith('label.info_', $entry['description'], $slug);
        }
    }

    // The projects stay out of the sidebar, listed inside the piloting pages instead
    public function testNoLinkInTheSidebar(): void
    {
        $this->assertSame([], $this->provider()->getLinks());
    }
}
