<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Doc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Doc>
 */
class DocRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doc::class);
    }

    // Files whose title or text holds the words
    /**
     * @return list<Doc>
     */
    public function search(string $words, int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.title LIKE :words OR d.summary LIKE :words OR d.detail LIKE :words')
            ->setParameter('words', '%' . addcslashes($words, '%_') . '%')
            ->orderBy('d.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
