<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    // Reports a page shows, by label: those of the project or matching its sites, those marked for the Planning when no project is given
    /** @return list<Report> */
    public function findShownOn(?Project $project): array
    {
        $builder = $this->createQueryBuilder('r')->orderBy('r.label', 'ASC');
        if (null === $project) {
            $builder->where('r.home = true');
        } else {
            $builder->where('r.project = :project OR r.match IS NOT NULL')->setParameter('project', $project);
        }

        return array_values(array_filter($builder->getQuery()->getResult(), static fn (Report $report): bool => null === $project || [] !== $report->rowsFor($project)));
    }
}
