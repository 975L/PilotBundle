<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    // Open cards dated up to a day, every project, the oldest date first, with their project in one query
    /**
     * @return list<Card>
     */
    public function findDatedUntil(\DateTimeImmutable $last): array
    {
        return $this->dated()
            ->andWhere('c.due <= :last')
            ->setParameter('last', $last->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    // Open dated cards after a day, the ones « Plus tard » folds
    /** @return list<Card> */
    public function findDatedAfter(\DateTimeImmutable $first): array
    {
        return $this->dated()
            ->andWhere('c.due > :first')
            ->setParameter('first', $first->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    // Open dated cards with their project, by date
    private function dated(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->join('c.project', 'p')
            ->where('c.due IS NOT NULL')
            ->andWhere('c.status != :done')
            ->setParameter('done', CardStatus::Done)
            ->orderBy('c.due', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->addOrderBy('c.position', 'ASC');
    }

    // Open cards of a project: the dated ones first by date, then the backlog in its own order
    /**
     * @return list<Card>
     */
    public function findOpen(Project $project): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.project = :project')
            ->andWhere('c.status != :done')
            ->setParameter('project', $project)
            ->setParameter('done', CardStatus::Done)
            ->addOrderBy('CASE WHEN c.due IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('c.due', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Open cards waiting for an answer, the oldest first
    /**
     * @return list<Card>
     */
    public function findWaiting(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.waitingOn IS NOT NULL')
            ->andWhere('c.status != :done')
            ->setParameter('done', CardStatus::Done)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Done cards of a project, the latest first
    /**
     * @return list<Card>
     */
    public function findDone(Project $project, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.project = :project')
            ->andWhere('c.status = :done')
            ->setParameter('project', $project)
            ->setParameter('done', CardStatus::Done)
            ->orderBy('c.doneAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Cards whose title or note holds the words, done ones included, open ones first
    /**
     * @return list<Card>
     */
    public function search(string $words, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->join('c.project', 'p')
            ->where('c.title LIKE :words OR c.note LIKE :words')
            ->setParameter('words', '%' . addcslashes($words, '%_') . '%')
            ->addOrderBy('CASE WHEN c.status = :done THEN 1 ELSE 0 END', 'ASC')
            ->setParameter('done', CardStatus::Done)
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Open cards of every project, by project id
    /** @return array<int, int> */
    public function countOpenByProject(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.project) AS project, COUNT(c.id) AS cards')
            ->where('c.status != :done')
            ->setParameter('done', CardStatus::Done)
            ->groupBy('c.project')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => ['project' => (int) $row['project'], 'cards' => (int) $row['cards']], $rows), 'cards', 'project');
    }
}
