<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PilotBundle\Enum\MemberRole;
use c975L\PilotBundle\Repository\MemberRepository;
use Doctrine\ORM\Mapping as ORM;

// An account given a role in a space, which then shows on its piloting pages
#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Table(name: 'pilot_member')]
#[ORM\UniqueConstraint(columns: ['project_id', 'user_id'])]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private UserInterface $user,
        #[ORM\Column(enumType: MemberRole::class)]
        private MemberRole $role,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getRole(): MemberRole
    {
        return $this->role;
    }

    public function setRole(MemberRole $role): static
    {
        $this->role = $role;

        return $this;
    }
}
