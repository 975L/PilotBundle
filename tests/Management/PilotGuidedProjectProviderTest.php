<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Controller\Management\MachineCrudController;
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Controller\Management\PlanTemplateCrudController;
use c975L\PilotBundle\Management\PilotGuidedProjectProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PilotGuidedProjectProviderTest extends TestCase
{
    private string $controller = '';

    /**
     * @return list<array<string, mixed>>
     */
    private function projects(): array
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/' . $route);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');

        $admin = $this->createStub(AdminUrlGeneratorInterface::class);
        $admin->method('unsetAll')->willReturnSelf();
        $admin->method('setAction')->willReturnSelf();
        $admin->method('setController')->willReturnCallback(function (string $controller) use ($admin): AdminUrlGeneratorInterface {
            $this->controller = $controller;

            return $admin;
        });
        $admin->method('generateUrl')->willReturnCallback(fn (): string => '/crud/' . $this->controller);

        return new PilotGuidedProjectProvider($urls, $admin, $config)->getGuidedProjects();
    }

    // The bundle's own block, 12000, behind the admin role, in the bundle's domain
    public function testProjectRunsTheBundleBlock(): void
    {
        foreach ($this->projects() as $project) {
            $this->assertGreaterThan(12000, $project['order']);
            $this->assertLessThan(13000, $project['order']);
            $this->assertSame('ROLE_ADMIN', $project['role']);
            $this->assertSame('pilot', $project['translation_domain']);
        }
    }

    // The six projects in their order, each opening on its own screen
    public function testProjectsOpenOnTheirScreen(): void
    {
        $projects = $this->projects();

        $this->assertSame(['pilot-schedule', 'pilot-runner', 'pilot-plan', 'pilot-machine', 'pilot-doc', 'pilot-objectives', 'pilot-share', 'pilot-card-edit'], array_column($projects, 'slug'));
        $this->assertSame([12010, 12020, 12030, 12040, 12050, 12060, 12070, 12080], array_column($projects, 'order'));
        $this->assertSame(['/' . PilotController::SEARCH_ROUTE, '/' . PilotController::SEARCH_ROUTE, '/crud/' . PlanTemplateCrudController::class, '/crud/' . MachineCrudController::class, '/' . PilotController::SEARCH_ROUTE, '/' . PilotController::WEEK_ROUTE, '/' . PilotController::SEARCH_ROUTE, '/' . PilotController::SEARCH_ROUTE], array_map(static fn (array $project): ?string => $project['steps'][0]['url'] ?? null, $projects));
    }

    // Only the opening step carries an url; the others highlight what the templates still declare, an EasyAdmin button or a form field
    public function testOtherStepsHighlightDeclaredElements(): void
    {
        $templates = implode('', array_map(file_get_contents(...), glob(\dirname(__DIR__, 2) . '/templates/management/*.twig') ?: []));
        foreach ($this->projects() as $project) {
            foreach (\array_slice($project['steps'], 1) as $step) {
                $this->assertArrayNotHasKey('url', $step);
                if (\in_array($step['highlight'], ['.action-new', '#PlanTemplate_projects', '#Action_projects'], true)) {
                    continue;
                }
                $needle = str_starts_with($step['highlight'], '#') ? 'id="' . substr($step['highlight'], 1) . '"' : substr($step['highlight'], 1);
                $this->assertStringContainsString($needle, $templates, $step['highlight']);
            }
        }
    }

    // The form fields highlighted are still the ones the settings screens show
    public function testHighlightedFieldsAreOnTheirForms(): void
    {
        $this->assertStringContainsString("AssociationField::new('projects'", (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/PlanTemplateCrudController.php'));
        $this->assertStringContainsString("AssociationField::new('projects'", (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/ActionCrudController.php'));
    }
}
