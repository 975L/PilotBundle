<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Scheduler;

use c975L\PilotBundle\Scheduler\PilotMaintenanceTaskProvider;
use PHPUnit\Framework\TestCase;

class PilotMaintenanceTaskProviderTest extends TestCase
{
    // The balances are read again every morning, at a time drawn per site
    public function testCreditsReadEveryMorning(): void
    {
        $tasks = new PilotMaintenanceTaskProvider()->getMaintenanceTasks();

        $this->assertCount(1, $tasks);
        $this->assertSame(['# #(5-7) * * *', 'c975l:pilot:credits-refresh'], [$tasks[0]->expression, $tasks[0]->command]);
    }
}
