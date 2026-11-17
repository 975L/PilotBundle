<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Management\MenuProvider;
use c975L\PilotBundle\Repository\ProjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MenuProviderTest extends TestCase
{
    private function provider(): MenuProvider
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findBy')->willReturn([new Project('catalog', 'Catalog'), new Project('item-1', 'Item 1')]);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/' . $route . '/' . $parameters['slug']);

        return new MenuProvider($projects, $urls);
    }

    // This week and the search, each on an action of the pilot controller, carrying what the onboarding tour reads
    public function testMenusAreTheWeekAndTheSearch(): void
    {
        $menus = $this->provider()->getMenus();

        $this->assertSame(['pilot_week', 'pilot_search'], array_keys($menus));
        foreach ($menus as $slug => $entry) {
            $this->assertSame(PilotController::class, $entry['controller'], $slug);
            $this->assertStringStartsWith('label.info_', $entry['description'], $slug);
        }
    }

    // One link per project, to its page, with no target so it stays in this section of the sidebar, and the page's own text for the tour
    public function testOneLinkPerProject(): void
    {
        $links = $this->provider()->getLinks();

        $this->assertSame(['pilot_project_catalog', 'pilot_project_item-1'], array_keys($links));
        $this->assertSame('/' . PilotController::PROJECT_ROUTE . '/catalog', $links['pilot_project_catalog']['url']);
        $this->assertSame('Catalog', $links['pilot_project_catalog']['label']);
        $this->assertArrayNotHasKey('target', $links['pilot_project_catalog']);
        $this->assertSame('label.info_project', $links['pilot_project_catalog']['description']);
    }
}
