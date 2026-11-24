<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\PilotBundle\Controller\PilotController;
use c975L\PilotBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    private function provider(): MenuProvider
    {
        return new MenuProvider();
    }

    // No back-office screen: the settings live on the piloting pages
    public function testNoMenu(): void
    {
        $this->assertSame([], $this->provider()->getMenus());
    }

    // The piloting pages, outside the back office, opened in their own tab
    public function testThePlanningIsALink(): void
    {
        $links = $this->provider()->getLinks();

        $this->assertSame(['pilot_dashboard'], array_keys($links));
        $this->assertSame(PilotController::DASHBOARD_ROUTE, $links['pilot_dashboard']['name']);
        $this->assertSame('_blank', $links['pilot_dashboard']['target']);
    }
}
