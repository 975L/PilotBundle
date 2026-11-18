<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Machine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Machine>
 */
class MachineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Machine::class);
    }

    // The machine holding this token, null for an unknown one
    public function findOneByToken(string $token): ?Machine
    {
        return '' === $token ? null : $this->findOneBy(['tokenHash' => hash('sha256', $token)]);
    }

    // Machines that called recently, with the actions they declared
    /** @return list<Machine> */
    public function findOnline(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.lastSeenAt >= :since')
            ->setParameter('since', $now->modify('-' . Machine::ONLINE_SECONDS . ' seconds'))
            ->orderBy('m.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
