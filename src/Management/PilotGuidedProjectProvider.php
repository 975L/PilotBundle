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
use c975L\PilotBundle\Controller\PilotController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided projects, in the 12000 block GuidedProjectProviderInterface reserves them, walked on the piloting pages where the layout mounts the panel. Only the opening step carries an url: the space and the card are then the ones the user clicks, a project never guessing which
class PilotGuidedProjectProvider implements GuidedProjectProviderInterface
{
    // The way into a space from the column of the spaces, its first link standing for whichever the user picks
    private const string SPACE = '.pilot-project-link';

    // A card's tile, opening its dialog; never its stretched title link, which the highlight's own positioning would shrink to its text
    private const string CARD = '.pilot-card';

    // The save button of a settings form
    private const string SAVE = '.pilot-settings-form .pilot-btn-primary';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGuidedProjects(): array
    {
        $admin = (string) $this->configService->get('site-role-admin');

        return [
            self::project('pilot_space', 12010, $admin, [
                ['key' => 'open', 'url' => $this->urlGenerator->generate('pilot_settings_new', ['kind' => 'space'])],
                ['key' => 'name', 'highlight' => '#project_name'],
                ['key' => 'slug', 'highlight' => '#project_slug'],
                ['key' => 'color', 'highlight' => '#project_color'],
                ['key' => 'done', 'highlight' => self::SAVE],
            ]),
            self::project('pilot_card', 12020, null, [
                ['key' => 'open', 'url' => $this->dashboard()],
                ['key' => 'space', 'highlight' => self::SPACE],
                ['key' => 'new', 'highlight' => 'details.pilot-card-new:has(form[action$="/pilot/card/new"]) > summary'],
                ['key' => 'title', 'highlight' => 'form[action$="/pilot/card/new"] input[name="title"]'],
            ]),
            self::project('pilot_steps', 12030, null, [
                ['key' => 'open', 'url' => $this->dashboard()],
                ['key' => 'space', 'highlight' => self::SPACE],
                ['key' => 'card', 'highlight' => self::CARD],
                ['key' => 'step', 'highlight' => '.pilot-step-add'],
                ['key' => 'fold', 'highlight' => '[data-action="pilot-fold-all#toggle"]'],
                ['key' => 'feedback', 'highlight' => '.pilot-feedback-add'],
            ]),
            self::project('pilot_share', 12040, null, [
                ['key' => 'open', 'url' => $this->dashboard()],
                ['key' => 'space', 'highlight' => self::SPACE],
                ['key' => 'card', 'highlight' => self::CARD],
                ['key' => 'link', 'highlight' => '.pilot-share'],
            ]),
            self::project('pilot_machine', 12050, $admin, [
                ['key' => 'open', 'url' => $this->urlGenerator->generate('pilot_settings_new', ['kind' => 'machine'])],
                ['key' => 'name', 'highlight' => '#machine_name'],
                ['key' => 'parallel', 'highlight' => '#machine_parallel'],
                ['key' => 'projects', 'highlight' => '#machine_projects'],
                ['key' => 'done', 'highlight' => self::SAVE],
            ]),
            self::project('pilot_template', 12060, $admin, [
                ['key' => 'open', 'url' => $this->urlGenerator->generate('pilot_settings_new', ['kind' => 'template'])],
                ['key' => 'name', 'highlight' => '#plan_template_name'],
                ['key' => 'projects', 'highlight' => '#plan_template_projects'],
                ['key' => 'checklist', 'highlight' => '#plan_template_checklist'],
                ['key' => 'steps', 'highlight' => '[data-action="pilot-collection#add"]'],
                ['key' => 'done', 'highlight' => self::SAVE],
            ]),
            self::project('pilot_run', 12070, null, [
                ['key' => 'open', 'url' => $this->dashboard()],
                ['key' => 'space', 'highlight' => self::SPACE],
                ['key' => 'card', 'highlight' => self::CARD],
                ['key' => 'run', 'highlight' => '.pilot-step-run'],
                ['key' => 'feedback', 'highlight' => '.pilot-feedback-run'],
            ]),
            self::project('pilot_credits', 12080, $admin, [
                ['key' => 'open', 'url' => $this->urlGenerator->generate('pilot_settings_new', ['kind' => 'credit'])],
                ['key' => 'label', 'highlight' => '#credit_label'],
                ['key' => 'key', 'highlight' => '#credit_key'],
                ['key' => 'provider', 'highlight' => '#credit_provider'],
                ['key' => 'api_key', 'highlight' => '#credit_apiKey'],
                ['key' => 'remaining', 'highlight' => '#credit_remaining'],
                ['key' => 'done', 'highlight' => self::SAVE],
                ['key' => 'dashboard', 'highlight' => '.pilot-brand'],
                ['key' => 'refresh', 'highlight' => '.pilot-credits-refresh button'],
            ]),
            // A step handed to an AI agent through the runner, which works on the card through the site's MCP endpoint and asks on it what it misses
            self::project('pilot_agent', 12090, null, [
                ['key' => 'open', 'url' => $this->dashboard()],
                ['key' => 'space', 'highlight' => self::SPACE],
                ['key' => 'card', 'highlight' => self::CARD],
                ['key' => 'run', 'highlight' => '.pilot-step-run'],
                ['key' => 'watch', 'highlight' => '.pilot-checklist'],
                ['key' => 'feedback', 'highlight' => '.pilot-feedback-add'],
            ]),
        ];
    }

    // The dashboard, where the column of the spaces leads to the one the user works in
    private function dashboard(): string
    {
        return $this->urlGenerator->generate(PilotController::DASHBOARD_ROUTE);
    }

    // A project and its steps, every key named after its slug, a null role offering it to whoever opens the projects' page
    /** @param list<array<string, string>> $steps */
    private static function project(string $name, int $order, ?string $role, array $steps): array
    {
        return [
            'slug' => str_replace('_', '-', $name),
            'label' => 'label.guided_project_' . $name,
            'description' => 'description.guided_project_' . $name,
            'translation_domain' => 'pilot',
            'order' => $order,
            'role' => $role,
            'steps' => array_map(static function (array $step) use ($name): array {
                $key = $step['key'];
                unset($step['key']);

                return [
                    'label' => 'label.guided_step_' . $name . '_' . $key,
                    'description' => 'description.guided_step_' . $name . '_' . $key,
                    'narration' => 'narration.guided_step_' . $name . '_' . $key,
                    ...$step,
                ];
            }, $steps),
        ];
    }
}
