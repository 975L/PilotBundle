<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\CardSetController;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\PlanTemplateRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CardSetControllerTest extends TestCase
{
    use PilotTestStubs;

    // The space's page comes back on the collection's tab, or on its first tab without one
    public function testBackOpensTheTab(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/' . $parameters['slug'] . (isset($parameters['_fragment']) ? '#' . $parameters['_fragment'] : ''));
        $container = new Container();
        $container->set('router', $router);
        $controller = new CardSetController(
            $this->createStub(PilotAccess::class),
            $this->createStub(ProjectRepository::class),
            $this->createStub(CardRepository::class),
            $this->createStub(CardSetRepository::class),
            $this->createStub(PlanTemplateRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(TranslatorInterface::class),
        );
        $controller->setContainer($container);
        $project = new Project('catalog', 'Catalog');
        $cardSet = new CardSet($project, 'item-1');
        new \ReflectionProperty(CardSet::class, 'id')->setValue($cardSet, 12);
        $back = new \ReflectionMethod($controller, 'back');

        $this->assertSame('/catalog#collection-12', $back->invoke($controller, $project, $cardSet)->getTargetUrl());
        $this->assertSame('/catalog', $back->invoke($controller, $project)->getTargetUrl());
    }

    // A card dropped on the + goes into the new collection, a card of another space stays where it is
    public function testNewFilesTheDroppedCard(): void
    {
        $project = new Project('catalog', 'Catalog');
        $card = new Card($project, 'item-1');
        $other = new Card(new Project('other', 'Other'), 'item-2');
        $cards = $this->createStub(CardRepository::class);
        $cards->method('find')->willReturnMap([[1, null, null, $card], [2, null, null, $other]]);
        $access = $this->createStub(PilotAccess::class);
        $access->method('can')->willReturn(true);
        $controller = $this->controller($access, $cards);

        $controller->new($project, new Request(request: ['name' => 'item-set', 'card' => '1', '_token' => 't']));
        $controller->new($project, new Request(request: ['name' => 'item-set', 'card' => '2', '_token' => 't']));

        $this->assertSame('item-set', $card->getCardSet()?->getName());
        $this->assertNull($other->getCardSet());
    }

    // The tabs take the order they were dragged into, a collection of another space left as it was; a rename keeps the name trimmed
    public function testOrderAndRename(): void
    {
        $project = new Project('catalog', 'Catalog');
        $first = new CardSet($project, 'item-1');
        $second = new CardSet($project, 'item-2');
        new \ReflectionProperty(CardSet::class, 'id')->setValue($first, 1);
        new \ReflectionProperty(CardSet::class, 'id')->setValue($second, 2);
        $sets = $this->createStub(CardSetRepository::class);
        $sets->method('findOfProject')->willReturn([$first, $second]);
        $access = $this->createStub(PilotAccess::class);
        $access->method('can')->willReturn(true);
        $controller = $this->controller($access, $this->createStub(CardRepository::class), $sets);

        $request = new Request(request: ['ids' => ['2', '9', '1']]);
        $request->headers->set('X-CSRF-Token', 't');
        $this->assertSame(204, $controller->order($project, $request)->getStatusCode());
        $this->assertSame(2, $first->getPosition());
        $this->assertSame(0, $second->getPosition());

        $controller->rename($first, new Request(request: ['name' => ' item-3 ', '_token' => 't']));
        $this->assertSame('item-3', $first->getName());
    }

    private function controller(PilotAccess $access, CardRepository $cards, ?CardSetRepository $sets = null): CardSetController
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/catalog');
        $container = $this->grantedContainer($router);
        $controller = new CardSetController($access, $this->createStub(ProjectRepository::class), $cards, $sets ?? $this->createStub(CardSetRepository::class), $this->createStub(PlanTemplateRepository::class), $this->createStub(EntityManagerInterface::class), $this->createStub(TranslatorInterface::class));
        $controller->setContainer($container);

        return $controller;
    }
}
