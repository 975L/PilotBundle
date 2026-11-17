<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Controller\Management\PilotController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided project, running the 12000 block GuidedProjectProviderInterface reserves it. Only the opening step carries an url, the next ones highlight what to use
class PilotGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            [
                'slug' => 'pilot-schedule',
                'label' => 'label.guided_project_pilot_schedule',
                'description' => 'description.guided_project_pilot_schedule',
                'translation_domain' => 'pilot',
                'order' => 12010,
                'role' => (string) $this->configService->get('site-role-admin'),
                'steps' => [
                    [
                        'label' => 'label.guided_step_pilot_schedule_search',
                        'description' => 'description.guided_step_pilot_schedule_search',
                        'narration' => 'narration.guided_step_pilot_schedule_search',
                        'url' => $this->urlGenerator->generate(PilotController::SEARCH_ROUTE),
                    ],
                    [
                        'label' => 'label.guided_step_pilot_schedule_open',
                        'description' => 'description.guided_step_pilot_schedule_open',
                        'narration' => 'narration.guided_step_pilot_schedule_open',
                        'highlight' => '.pilot-card-open',
                    ],
                    [
                        'label' => 'label.guided_step_pilot_schedule_due',
                        'description' => 'description.guided_step_pilot_schedule_due',
                        'narration' => 'narration.guided_step_pilot_schedule_due',
                        'highlight' => '#pilot-due',
                    ],
                    [
                        'label' => 'label.guided_step_pilot_schedule_save',
                        'description' => 'description.guided_step_pilot_schedule_save',
                        'narration' => 'narration.guided_step_pilot_schedule_save',
                        'highlight' => '.pilot-schedule',
                    ],
                ],
            ],
        ];
    }
}
