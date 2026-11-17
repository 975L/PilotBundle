<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Action>
 */
class ActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Action::class);
    }

    // Records what a machine declares, a new action being offered nowhere until its projects are chosen
    /** @param array<string, array{label: string, params: list<string>, card?: bool}> $capabilities */
    public function declare(array $capabilities): void
    {
        $known = [];
        foreach ($this->findBy(['key' => array_keys($capabilities)]) as $action) {
            $known[$action->getKey()] = $action;
        }
        foreach ($capabilities as $key => $capability) {
            $action = $known[$key] ?? new Action((string) $key);
            $action->declare($capability['label'], $capability['params'], $capability['card'] ?? false);
            $this->getEntityManager()->persist($action);
        }
    }

    // Actions offered on a project's cards among those a machine online declares
    /**
     * @param list<string> $keys
     *
     * @return list<Action>
     */
    public function findOffered(Project $project, array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->join('a.projects', 'p')
            ->where('p = :project')
            ->andWhere('a.key IN (:keys)')
            ->setParameter('project', $project)
            ->setParameter('keys', $keys)
            ->orderBy('a.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
