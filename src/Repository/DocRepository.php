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
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\DocKind;
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

    // Files of a project a card can be attached to, characters and objectives left out, by title
    /**
     * @return list<Doc>
     */
    public function findFiles(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.project = :project')
            ->andWhere('d.kind NOT IN (:hidden)')
            ->setParameter('project', $project)
            ->setParameter('hidden', [DocKind::Character, DocKind::Objectives])
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // The objectives of a project, written in a document of their own, created empty the first time
    public function objectivesOf(Project $project, string $title): Doc
    {
        return $this->findOneBy(['project' => $project, 'kind' => DocKind::Objectives]) ?? $this->newFile($project, $title)->setKind(DocKind::Objectives);
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
