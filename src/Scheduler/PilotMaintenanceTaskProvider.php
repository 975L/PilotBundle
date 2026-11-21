<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

// The commands the piloting needs run on a cadence, scheduled by the site without anything to edit in its MaintenanceSchedule
class PilotMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // The paid services' balances, every morning, the dashboard's button reading them any time
            new MaintenanceTask('# #(5-7) * * *', 'c975l:pilot:credits-refresh'),
        ];
    }
}
