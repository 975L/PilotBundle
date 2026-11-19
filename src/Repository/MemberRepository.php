<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PilotBundle\Entity\Member;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\MemberRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    // The role of an account in every space it is a member of, by space id, in one query
    /** @return array<int, MemberRole> */
    public function rolesOf(UserInterface $user): array
    {
        $roles = [];
        foreach ($this->findBy(['user' => $user]) as $member) {
            $roles[(int) $member->getProject()->getId()] = $member->getRole();
        }

        return $roles;
    }

    // The members of a space, the owners first
    /** @return list<Member> */
    public function findOfProject(Project $project): array
    {
        $members = $this->findBy(['project' => $project]);
        usort($members, static fn (Member $a, Member $b): int => [$b->getRole()->rank(), $a->getUser()->getUserIdentifier()] <=> [$a->getRole()->rank(), $b->getUser()->getUserIdentifier()]);

        return $members;
    }
}
