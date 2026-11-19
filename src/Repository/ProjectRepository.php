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
use c975L\PilotBundle\Security\PilotScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    // The spaces the account sees in the order chosen, then by name, every one without a scope
    /** @return list<Project> */
    public function findOrdered(?PilotScope $scope = null): array
    {
        if (null === $scope || $scope->all) {
            return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
        }

        return [] === $scope->projectIds() ? [] : $this->findBy(['id' => $scope->projectIds()], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
