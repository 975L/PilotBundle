<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Controller\SettingsController;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\Credit\ElevenLabsReader;
use c975L\PilotBundle\Service\CreditRefresher;
use c975L\PilotBundle\Service\SettingsKinds;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsControllerTest extends TestCase
{
    use PilotTestStubs;

    // A credit naming a service is read as it is saved, its balance written with it
    public function testCreditReadOnSave(): void
    {
        [$session, $credit] = $this->save(new JsonMockResponse(['character_limit' => 100, 'character_count' => 40]));

        $this->assertSame(60, $credit->getRemaining());
        $this->assertSame([], $session->getFlashBag()->peek('danger'));
    }

    // A service that cannot be read says so at once, the credit still saved
    public function testUnreadCreditFlagged(): void
    {
        [$session, $credit] = $this->save(new JsonMockResponse(['detail' => 'invalid key'], ['http_code' => 401]));

        $this->assertNull($credit->getRemaining());
        $this->assertSame(['flash.credit_unread'], $session->getFlashBag()->peek('danger'));
    }

    // A space saved goes back to its own page, the settings no longer listing the spaces
    public function testSpaceSavedBackToItsPage(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->with('pilot_project', ['slug' => 'catalog'])->willReturn('/pilot/catalog');
        $controller = $this->controller($this->createStub(EntityManagerInterface::class), $router, new Session(new MockArraySessionStorage()));

        new \ReflectionMethod($controller, 'saved')->invoke($controller, 'space', new Project('catalog', 'Catalog'));
        $this->assertArrayNotHasKey('space', new SettingsKinds()->listed());
        $this->assertArrayHasKey('credit', new SettingsKinds()->listed());
    }

    // A space deleted goes with what it holds, back to the dashboard
    public function testSpaceDeletedBackToTheDashboard(): void
    {
        $project = new Project('catalog', 'Catalog');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($project);
        $entityManager->expects($this->once())->method('remove')->with($project);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects($this->once())->method('generate')->with('pilot_dashboard')->willReturn('/pilot');
        $controller = $this->controller($entityManager, $router, new Session(new MockArraySessionStorage()));

        $this->assertSame('/pilot', $controller->delete('space', 1, Request::create('/', 'POST', ['_token' => 't']))->getTargetUrl());
    }

    // The credit saved through the settings' own redirect, the service answering as given
    /** @return array{Session, Credit} */
    private function save(JsonMockResponse $answer): array
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/pilot/settings/credit');
        $session = new Session(new MockArraySessionStorage());
        $vaultEncryptor = new VaultEncryptor('test-vault-key');
        $credit = new Credit('item-1')->setProvider('elevenlabs')->setApiKey($vaultEncryptor->encrypt('key-1'));
        $controller = $this->controller($this->createStub(EntityManagerInterface::class), $router, $session, new CreditRefresher([new ElevenLabsReader(new MockHttpClient($answer))], $vaultEncryptor, new MockClock(), new NullLogger(), $this->createStub(CreditRepository::class)));

        new \ReflectionMethod($controller, 'saved')->invoke($controller, 'credit', $credit);

        return [$session, $credit];
    }

    // The controller for the admin, its session, router and token checks given
    private function controller(EntityManagerInterface $entityManager, UrlGeneratorInterface $router, Session $session, ?CreditRefresher $creditRefresher = null): SettingsController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $access = $this->createStub(PilotAccess::class);
        $access->method('isAdmin')->willReturn(true);
        $controller = new SettingsController($access, $this->createStub(ProjectRepository::class), $this->createStub(CardRepository::class), $entityManager, new SettingsKinds(), $translator, $creditRefresher ?? $this->createStub(CreditRefresher::class));
        $request = new Request();
        $request->setSession($session);
        $container = $this->grantedContainer($router);
        $container->set('request_stack', new RequestStack([$request]));
        $controller->setContainer($container);

        return $controller;
    }
}
