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
use c975L\PilotBundle\Controller\Management\MachineCrudController;
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Controller\Management\PlanTemplateCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided projects, running the 12000 block GuidedProjectProviderInterface reserves it. Only the opening step carries an url, the next ones highlight what to use
class PilotGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [$this->schedule(), $this->runner(), $this->plan(), $this->machine(), $this->doc(), $this->objectives()];
    }

    // A card found, opened, then dated: it leaves the backlog for the planning
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function schedule(): array
    {
        return [
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
        ];
    }

    // A card handed to a machine, then its result validated or sent back
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function runner(): array
    {
        return [
            'slug' => 'pilot-runner',
            'label' => 'label.guided_project_pilot_runner',
            'description' => 'description.guided_project_pilot_runner',
            'translation_domain' => 'pilot',
            'order' => 12020,
            'role' => (string) $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_pilot_schedule_search',
                    'description' => 'description.guided_step_pilot_schedule_search',
                    'narration' => 'narration.guided_step_pilot_runner_search',
                    'url' => $this->urlGenerator->generate(PilotController::SEARCH_ROUTE),
                ],
                [
                    'label' => 'label.guided_step_pilot_schedule_open',
                    'description' => 'description.guided_step_pilot_schedule_open',
                    'narration' => 'narration.guided_step_pilot_schedule_open',
                    'highlight' => '.pilot-card-open',
                ],
                [
                    'label' => 'label.guided_step_pilot_runner_entrust',
                    'description' => 'description.guided_step_pilot_runner_entrust',
                    'narration' => 'narration.guided_step_pilot_runner_entrust',
                    'highlight' => '.pilot-entrust',
                ],
                [
                    'label' => 'label.guided_step_pilot_runner_validate',
                    'description' => 'description.guided_step_pilot_runner_validate',
                    'narration' => 'narration.guided_step_pilot_runner_validate',
                    'highlight' => '.pilot-validate',
                ],
            ],
        ];
    }

    // A plan template written, offered on its projects, then started from a project's page
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function plan(): array
    {
        return [
            'slug' => 'pilot-plan',
            'label' => 'label.guided_project_pilot_plan',
            'description' => 'description.guided_project_pilot_plan',
            'translation_domain' => 'pilot',
            'order' => 12030,
            'role' => (string) $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_pilot_plan_template',
                    'description' => 'description.guided_step_pilot_plan_template',
                    'narration' => 'narration.guided_step_pilot_plan_template',
                    'url' => $this->indexUrl(PlanTemplateCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_pilot_plan_new',
                    'description' => 'description.guided_step_pilot_plan_new',
                    'narration' => 'narration.guided_step_pilot_plan_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_pilot_plan_projects',
                    'description' => 'description.guided_step_pilot_plan_projects',
                    'narration' => 'narration.guided_step_pilot_plan_projects',
                    'highlight' => '#PlanTemplate_projects',
                ],
                [
                    'label' => 'label.guided_step_pilot_plan_start',
                    'description' => 'description.guided_step_pilot_plan_start',
                    'narration' => 'narration.guided_step_pilot_plan_start',
                    'highlight' => '.pilot-plan-start',
                ],
            ],
        ];
    }

    // A machine registered, its actions offered on projects, then run from a project's page
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function machine(): array
    {
        return [
            'slug' => 'pilot-machine',
            'label' => 'label.guided_project_pilot_machine',
            'description' => 'description.guided_project_pilot_machine',
            'translation_domain' => 'pilot',
            'order' => 12040,
            'role' => (string) $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_pilot_machine_list',
                    'description' => 'description.guided_step_pilot_machine_list',
                    'narration' => 'narration.guided_step_pilot_machine_list',
                    'url' => $this->indexUrl(MachineCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_pilot_machine_new',
                    'description' => 'description.guided_step_pilot_machine_new',
                    'narration' => 'narration.guided_step_pilot_machine_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_pilot_machine_projects',
                    'description' => 'description.guided_step_pilot_machine_projects',
                    'narration' => 'narration.guided_step_pilot_machine_projects',
                    'highlight' => '#Action_projects',
                ],
                [
                    'label' => 'label.guided_step_pilot_machine_run',
                    'description' => 'description.guided_step_pilot_machine_run',
                    'narration' => 'narration.guided_step_pilot_machine_run',
                    'highlight' => '.pilot-project-actions',
                ],
            ],
        ];
    }

    // A file found, opened on its own page, then its title and two floors rewritten
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function doc(): array
    {
        return [
            'slug' => 'pilot-doc',
            'label' => 'label.guided_project_pilot_doc',
            'description' => 'description.guided_project_pilot_doc',
            'translation_domain' => 'pilot',
            'order' => 12050,
            'role' => (string) $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_pilot_doc_search',
                    'description' => 'description.guided_step_pilot_schedule_search',
                    'narration' => 'narration.guided_step_pilot_doc_search',
                    'url' => $this->urlGenerator->generate(PilotController::SEARCH_ROUTE),
                ],
                [
                    'label' => 'label.guided_step_pilot_doc_open',
                    'description' => 'description.guided_step_pilot_doc_open',
                    'narration' => 'narration.guided_step_pilot_doc_open',
                    'highlight' => '.pilot-doc-edit',
                ],
                [
                    'label' => 'label.guided_step_pilot_doc_write',
                    'description' => 'description.guided_step_pilot_doc_write',
                    'narration' => 'narration.guided_step_pilot_doc_write',
                    'highlight' => '.pilot-doc-form',
                ],
            ],
        ];
    }

    // A project opened, then its objectives written in the dialog, their document created the first time
    /** @return array{slug: string, label: string, description: string, translation_domain: string, order: int, role: string, steps: list<array{label: string, description: string, narration: string, url?: string, highlight?: string}>} */
    private function objectives(): array
    {
        return [
            'slug' => 'pilot-objectives',
            'label' => 'label.guided_project_pilot_objectives',
            'description' => 'description.guided_project_pilot_objectives',
            'translation_domain' => 'pilot',
            'order' => 12060,
            'role' => (string) $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_pilot_objectives_project',
                    'description' => 'description.guided_step_pilot_objectives_project',
                    'narration' => 'narration.guided_step_pilot_objectives_project',
                    'url' => $this->urlGenerator->generate(PilotController::WEEK_ROUTE),
                    'highlight' => '.pilot-project-link',
                ],
                [
                    'label' => 'label.guided_step_pilot_objectives_open',
                    'description' => 'description.guided_step_pilot_objectives_open',
                    'narration' => 'narration.guided_step_pilot_objectives_open',
                    'highlight' => '.pilot-objectives',
                ],
                [
                    'label' => 'label.guided_step_pilot_doc_write',
                    'description' => 'description.guided_step_pilot_doc_write',
                    'narration' => 'narration.guided_step_pilot_doc_write',
                    'highlight' => '.pilot-doc-form',
                ],
            ],
        ];
    }

    // Index url of a settings screen
    private function indexUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
