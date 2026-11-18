<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanTemplate>
 */
class PlanTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanTemplate::class);
    }

    // Templates offered on a project's page, by name
    /** @return list<PlanTemplate> */
    public function findOfferedTo(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.projects', 'p')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->orderBy('t.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult()
        ;
    }
}
