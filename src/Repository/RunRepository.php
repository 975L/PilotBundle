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
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Run>
 */
class RunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Run::class);
    }

    // Runs waiting in the queue or held by a machine, of one project or all, oldest first
    /** @return list<Run> */
    public function findActive(?Project $project = null): array
    {
        $builder = $this->createQueryBuilder('r')
            ->addSelect('c')
            ->join('r.card', 'c')
            ->where('r.status IN (:active)')
            ->setParameter('active', [RunStatus::Queued, RunStatus::Claimed])
            ->orderBy('r.id', \SortDirection::Ascending);
        if (null !== $project) {
            $builder->andWhere('c.project = :project')->setParameter('project', $project);
        }

        return $builder->getQuery()->getResult();
    }

    // Claimed runs whose lease ended before now
    /** @return list<Run> */
    public function findExpired(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :claimed')
            ->andWhere('r.leaseUntil < :now')
            ->setParameter('claimed', RunStatus::Claimed)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    // Oldest queued run ids whose action the machine declared, candidates for a claim: one run of a card at a time, the next one waiting until the one taken and those queued before it are gone
    /**
     * @param list<string> $actions
     *
     * @return list<int>
     */
    public function queuedIds(array $actions, int $limit = 5): array
    {
        if ([] === $actions) {
            return [];
        }
        $before = $this->createQueryBuilder('o')
            ->select('1')
            ->where('o.card = r.card')
            ->andWhere('o.status = :claimed OR (o.status = :queued AND o.id < r.id)');

        return array_map(intval(...), $this->createQueryBuilder('r')
            ->select('r.id')
            ->join('r.card', 'c')
            ->where('r.status = :queued')
            ->andWhere('COALESCE(r.action, c.action) IN (:actions)')
            ->andWhere('NOT EXISTS (' . $before->getDQL() . ')')
            ->setParameter('queued', RunStatus::Queued)
            ->setParameter('claimed', RunStatus::Claimed)
            ->setParameter('actions', $actions)
            ->orderBy('r.id', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult());
    }

    // Takes a queued run for a machine in one statement, false when another machine was faster
    public function take(int $id, Machine $machine, \DateTimeImmutable $now): bool
    {
        return 1 === $this->createQueryBuilder('r')
            ->update()
            ->set('r.status', ':claimed')
            ->set('r.machine', ':machine')
            ->set('r.leaseUntil', ':lease')
            ->set('r.progress', 0)
            ->where('r.id = :id')
            ->andWhere('r.status = :queued')
            ->setParameter('claimed', RunStatus::Claimed)
            ->setParameter('machine', $machine)
            ->setParameter('lease', $now->modify('+' . Run::LEASE_SECONDS . ' seconds'))
            ->setParameter('id', $id)
            ->setParameter('queued', RunStatus::Queued)
            ->getQuery()
            ->execute();
    }
}
