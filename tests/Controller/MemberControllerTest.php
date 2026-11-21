<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\MemberController;
use c975L\PilotBundle\Entity\Member;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\MemberRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use c975L\PilotBundle\Tests\Fixtures\UserFixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MemberControllerTest extends TestCase
{
    use PilotTestStubs;

    // An email with an account and one without get the same message, only the first one made a member
    public function testSameMessageWhetherTheEmailHasAnAccountOrNot(): void
    {
        foreach ([true, false] as $known) {
            $session = new Session(new MockArraySessionStorage());
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $users = $this->createStub(EntityRepository::class);
            $users->method('findOneBy')->willReturn($known ? new UserFixture() : null);
            $entityManager->method('getRepository')->willReturn($users);
            $entityManager->expects($known ? $this->once() : $this->never())->method('persist')->with($this->isInstanceOf(Member::class));

            $response = $this->controller($entityManager, $session)->add(new Project('catalog', 'Catalog'), Request::create('/', 'POST', ['role' => 'editor', 'email' => 'item-1@example.com', '_token' => 't']));

            $this->assertSame('/pilot/space/catalog/members', $response->getTargetUrl());
            $this->assertSame(['label.member_maybe_added'], $session->getFlashBag()->peek('success'));
            $this->assertSame([], $session->getFlashBag()->peek('danger'));
        }
    }

    // The controller for the space's owner, its session given and every token valid
    private function controller(EntityManagerInterface $entityManager, Session $session): MemberController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $access = $this->createStub(PilotAccess::class);
        $access->method('can')->willReturn(true);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/pilot/space/catalog/members');
        $controller = new MemberController($access, $this->createStub(ProjectRepository::class), $this->createStub(CardRepository::class), $this->createStub(MemberRepository::class), $entityManager, $translator);
        $request = new Request();
        $request->setSession($session);
        $container = $this->grantedContainer($router);
        $container->set('request_stack', new RequestStack([$request]));
        $controller->setContainer($container);

        return $controller;
    }
}
