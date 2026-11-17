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
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Repository\ProjectRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Its own section in the sidebar: this week, the search, then one entry per project in the order chosen
class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

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
                'icon' => 'fas fa-calendar-week',
                'description' => 'label.info_week',
            ],
            'pilot_search' => [
                'controller' => PilotController::class,
                'action' => 'search',
                'label' => 'label.search',
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-magnifying-glass',
                'description' => 'label.info_search',
            ],
        ];
    }

    // One back-office link per project, drawn inside this section since it names no target
    public function getLinks(): array
    {
        $links = [];
        foreach ($this->projectRepository->findBy([], ['position' => 'ASC', 'name' => 'ASC']) as $project) {
            $links['pilot_project_' . $project->getSlug()] = [
                'url' => $this->urlGenerator->generate(PilotController::PROJECT_ROUTE, ['slug' => $project->getSlug()]),
                'label' => $project->getName(),
                'translation_domain' => 'pilot',
                'icon' => 'fas fa-folder',
                'description' => 'label.info_project',
            ];
        }

        return $links;
    }
}
