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
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Security\PilotScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @extends ServiceEntityRepository<Doc>
 */
class DocRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doc::class);
    }

    // Files whose title or text holds the words, among those of the spaces the account works in whole
    /**
     * @return list<Doc>
     */
    public function search(string $words, PilotScope $scope, int $limit = 20): array
    {
        return $scope->restrictProjects($this->createQueryBuilder('d'), 'IDENTITY(d.project)')
            ->andWhere('d.title LIKE :words OR d.summary LIKE :words OR d.detail LIKE :words')
            ->setParameter('words', '%' . addcslashes($words, '%_') . '%')
            ->orderBy('d.title', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // A project's references common to its work: its files but the one that belongs to a single open card (shown on that card), by title
    /**
     * @return list<Doc>
     */
    public function findReferences(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.project = :project')
            ->andWhere('d.id NOT IN (SELECT IDENTITY(c.doc) FROM ' . Card::class . ' c WHERE c.doc IS NOT NULL GROUP BY c.doc HAVING COUNT(c.id) = 1 AND SUM(CASE WHEN c.status = :done THEN 0 ELSE 1 END) = 1)')
            ->setParameter('done', CardStatus::Done)
            ->setParameter('project', $project)
            ->orderBy('d.title', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    // Files of a project a card can be attached to, by title
    /**
     * @return list<Doc>
     */
    public function findFiles(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.title', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    // Whether another card than the one given cites the file, a reference of the space rather than of that card alone
    public function isCitedByAnotherCard(Doc $doc, Card $card): bool
    {
        return null !== $this->getEntityManager()->createQueryBuilder()
            ->select('c.id')
            ->from(Card::class, 'c')
            ->where('c.doc = :doc')
            ->andWhere('c <> :card')
            ->setParameter('doc', $doc)
            ->setParameter('card', $card)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // A new file of a project, its slug made from the project and the title and kept unique by a number
    public function newFile(Project $project, string $title): Doc
    {
        $base = mb_substr($project->getSlug() . '-' . new AsciiSlugger()->slug($title)->lower()->toString(), 0, 90);
        $slug = $base;
        for ($number = 2; null !== $this->findOneBy(['slug' => $slug]); ++$number) {
            $slug = $base . '-' . $number;
        }
        $doc = new Doc($slug, mb_substr($title, 0, 200))->setProject($project);
        $this->getEntityManager()->persist($doc);

        return $doc;
    }
}
