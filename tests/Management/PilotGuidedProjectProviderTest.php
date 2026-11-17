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
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Management\PilotGuidedProjectProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PilotGuidedProjectProviderTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function projects(): array
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/' . $route);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');

        return new PilotGuidedProjectProvider($urls, $config)->getGuidedProjects();
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

    // Only the opening step carries an url, the search; the others highlight what the templates still declare
    public function testFirstStepOpensTheSearchAndTheOthersHighlightDeclaredElements(): void
    {
        $templates = implode('', array_map(file_get_contents(...), glob(\dirname(__DIR__, 2) . '/templates/management/*.twig') ?: []));
        foreach ($this->projects() as $project) {
            $this->assertSame('/' . PilotController::SEARCH_ROUTE, $project['steps'][0]['url']);
            foreach (\array_slice($project['steps'], 1) as $step) {
                $this->assertArrayNotHasKey('url', $step);
                $needle = str_starts_with($step['highlight'], '#') ? 'id="' . substr($step['highlight'], 1) . '"' : substr($step['highlight'], 1);
                $this->assertStringContainsString($needle, $templates, $step['highlight']);
            }
        }
    }
}
