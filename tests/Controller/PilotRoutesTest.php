<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\CardImageController;
use c975L\PilotBundle\Controller\PilotController;
use c975L\PilotBundle\Controller\SettingsController;
use c975L\PilotBundle\Entity\Project;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

// The piloting pages' addresses follow their levels, the pages of their own matched first and their words kept out of the spaces' slugs
class PilotRoutesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function paths(): iterable
    {
        yield 'dashboard' => ['/pilot', 'pilot_dashboard'];
        yield 'search' => ['/pilot/search', 'pilot_search'];
        yield 'space' => ['/pilot/catalog', 'pilot_project'];
        yield 'plan' => ['/pilot/catalog/plan', 'pilot_plan_new'];
        yield 'done cards of a tab' => ['/pilot/space/catalog/done/0', 'pilot_project_done'];
        yield 'card of the space' => ['/pilot/catalog/12', 'pilot_space_card'];
        yield 'settings' => ['/pilot/settings', 'pilot_settings'];
        yield 'settings of a kind' => ['/pilot/settings/template', 'pilot_settings_list'];
        yield 'setting edited' => ['/pilot/settings/template/3', 'pilot_settings_edit'];
        yield 'card by its number' => ['/pilot/card/12', 'pilot_card'];
        yield 'file' => ['/pilot/doc/catalog-file', 'pilot_doc'];
        yield 'card as markdown' => ['/pilot/card/12.md', 'pilot_card_markdown'];
        yield 'feedback screenshot' => ['/pilot/share/image/' . str_repeat('a', 32) . '.png', CardImageController::FEEDBACK_ROUTE];
    }

    // Each address reaches its own page
    #[DataProvider('paths')]
    public function testPathReachesItsPage(string $path, string $route): void
    {
        $this->assertSame($route, $this->matcher()->match($path)['_route']);
    }

    // A question to Donovan is posted, never sent by an address a reload would ask again
    public function testAskIsPostedOnly(): void
    {
        $matcher = $this->matcher();
        $matcher->setContext(new RequestContext(method: 'POST'));

        $this->assertSame(PilotController::ASK_ROUTE, $matcher->match('/pilot/search/ask')['_route']);
        $this->expectException(MethodNotAllowedException::class);
        $this->matcher()->match('/pilot/search/ask');
    }

    // The queue's new order is posted only, at an address no space can take
    public function testRunsOrderIsPostedOnly(): void
    {
        $matcher = $this->matcher();
        $matcher->setContext(new RequestContext(method: 'POST'));

        $this->assertSame(PilotController::RUNS_ORDER_ROUTE, $matcher->match('/pilot/run/order')['_route']);
        $this->expectException(MethodNotAllowedException::class);
        $this->matcher()->match('/pilot/run/order');
    }

    // A space never takes the name of one of the piloting pages' own words
    public function testReservedSlugs(): void
    {
        foreach (['search', 'share', 'card', 'cards', 'projects', 'credit', 'report', 'doc', 'run', 'space', 'settings'] as $word) {
            $this->assertSame(0, preg_match(Project::SLUG, $word), $word);
        }
        $this->assertSame(1, preg_match(Project::SLUG, 'catalog-2'));
    }

    private function matcher(): UrlMatcher
    {
        $loader = new AttributeRouteControllerLoader();
        $routes = new RouteCollection();
        foreach ([PilotController::class, SettingsController::class, CardImageController::class] as $controller) {
            $routes->addCollection($loader->load($controller));
        }

        return new UrlMatcher($routes, new RequestContext());
    }
}
