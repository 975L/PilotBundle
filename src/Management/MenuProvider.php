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
use c975L\PilotBundle\Controller\Management\ActionCrudController;
use c975L\PilotBundle\Controller\Management\CreditCrudController;
use c975L\PilotBundle\Controller\Management\MachineCrudController;
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Controller\Management\PlanTemplateCrudController;
use c975L\PilotBundle\Controller\Management\ProjectCrudController;

// The piloting section of the sidebar: the planning, whose pages list the search and the projects in their own column, then the settings
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

    public function getMenus(): array
    {
        return [
            'pilot_week' => [
                'controller' => PilotController::class,
                'action' => 'week',
                'label' => 'label.week',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-calendar-days',
                'description' => 'label.info_pilot',
                'first' => true,
            ],
            'pilot_projects' => [
                'controller' => ProjectCrudController::class,
                'label' => 'label.projects',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-folder',
                'description' => 'label.info_projects',
            ],
            'pilot_templates' => [
                'controller' => PlanTemplateCrudController::class,
                'label' => 'label.templates',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-list-ol',
                'description' => 'label.info_templates',
            ],
            'pilot_actions' => [
                'controller' => ActionCrudController::class,
                'label' => 'label.actions',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-bolt',
                'description' => 'label.info_actions',
            ],
            'pilot_machines' => [
                'controller' => MachineCrudController::class,
                'label' => 'label.machines',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-desktop',
                'description' => 'label.info_machines',
            ],
            'pilot_credits' => [
                'controller' => CreditCrudController::class,
                'label' => 'label.credits',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-coins',
                'description' => 'label.info_credits',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
