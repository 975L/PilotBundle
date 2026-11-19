<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\PilotBundle\Controller\PilotController;

// The piloting section of the sidebar: a link to the piloting pages, which list the search, the spaces and the settings in their own column
class MenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.pilot',
            'translation_domain' => 'pilot',
            'icon' => 'fas fa-compass',
        ];
    }

    // The settings live on the piloting pages themselves, none in the back office
    public function getMenus(): array
    {
        return [];
    }

    // The piloting pages themselves, on their own shell outside the back office, opened from its section
    public function getLinks(): array
    {
        return [
            'pilot_dashboard' => [
                'name' => PilotController::DASHBOARD_ROUTE,
                'label' => 'label.dashboard',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-gauge-high',
                'description' => 'label.info_pilot',
            ],
        ];
    }
}
