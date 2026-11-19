<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Security;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\MemberRole;
use c975L\PilotBundle\Repository\MemberRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Tests\Fixtures\UserFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class PilotAccessTest extends TestCase
{
    private Project $space;

    private Project $other;

    private UserFixture $user;

    protected function setUp(): void
    {
        $this->space = $this->project(1, 'catalog');
        $this->other = $this->project(2, 'other');
        $this->user = new UserFixture();
    }

    // Each role holds the ones below it
    public function testRolesHoldTheOnesBelow(): void
    {
        $this->assertTrue(MemberRole::Owner->holds(MemberRole::Guest));
        $this->assertTrue(MemberRole::Editor->holds(MemberRole::Editor));
        $this->assertFalse(MemberRole::Contributor->holds(MemberRole::Editor));
    }

    // A contributor works on every card of its space, launches no machine, sets nothing up; another space it does not see
    public function testContributor(): void
    {
        $access = $this->access(false, [1 => MemberRole::Contributor]);
        $card = new Card($this->space, 'item-1');

        $this->assertTrue($access->can($card, PilotAccess::VIEW));
        $this->assertTrue($access->can($card, PilotAccess::EDIT));
        $this->assertFalse($access->can($card, PilotAccess::RUN));
        $this->assertFalse($access->can($card, PilotAccess::DELETE));
        $this->assertFalse($access->can($this->space, PilotAccess::MANAGE));
        $this->assertFalse($access->can($this->other, PilotAccess::VIEW));
    }

    // A guest sees its space and the cards given to it, ticks them, and writes nothing else
    public function testGuest(): void
    {
        $access = $this->access(false, [1 => MemberRole::Guest]);
        $given = new Card($this->space, 'item-1')->addAssignee($this->user);
        $other = new Card($this->space, 'item-2');

        $this->assertTrue($access->can($this->space, PilotAccess::VIEW));
        $this->assertTrue($access->can($given, PilotAccess::WORK));
        $this->assertFalse($access->can($given, PilotAccess::EDIT));
        $this->assertFalse($access->can($other, PilotAccess::VIEW));
    }

    // An editor launches the machines and deletes cards, an owner also sets the space up; the admin does everything everywhere
    public function testEditorOwnerAndAdmin(): void
    {
        $this->assertTrue($this->access(false, [1 => MemberRole::Editor])->can($this->space, PilotAccess::RUN));
        $this->assertTrue($this->access(false, [1 => MemberRole::Editor])->can(new Card($this->space, 'item-1'), PilotAccess::DELETE));
        $this->assertTrue($this->access(false, [1 => MemberRole::Owner])->can($this->space, PilotAccess::MANAGE));
        $this->assertTrue($this->access(true, [])->can($this->other, PilotAccess::MANAGE));
    }

    // The admin's scope is everything, a member's the spaces it works in whole apart from those it is a guest of
    public function testScope(): void
    {
        $this->assertTrue($this->access(true, [])->scope()->all);
        $scope = $this->access(false, [1 => MemberRole::Contributor, 2 => MemberRole::Guest])->scope();

        $this->assertFalse($scope->all);
        $this->assertSame([1], $scope->full);
        $this->assertSame([2], $scope->guest);
        $this->assertSame($this->user, $scope->user);
    }

    // Whether the account is the admin is asked once per request, however many checks the page makes
    public function testAdminReadOnce(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->willReturn(true);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');
        $access = new PilotAccess($security, $config, $this->createStub(MemberRepository::class));

        $this->assertTrue($access->can($this->space, PilotAccess::RUN));
        $this->assertTrue($access->can($this->other, PilotAccess::MANAGE));
        $this->assertTrue($access->scope()->all);
    }

    /** @param array<int, MemberRole> $roles */
    private function access(bool $admin, array $roles): PilotAccess
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($admin);
        $security->method('getUser')->willReturn($this->user);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');
        $members = $this->createStub(MemberRepository::class);
        $members->method('rolesOf')->willReturn($roles);

        return new PilotAccess($security, $config, $members);
    }

    private function project(int $id, string $slug): Project
    {
        $project = new Project($slug, ucfirst($slug));
        new \ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
