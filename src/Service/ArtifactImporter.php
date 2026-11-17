<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\DocKind;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

// Writes an export of the Pilotage artifact into the bundle's tables; a second import updates what the first one wrote instead of duplicating it
class ArtifactImporter
{
    public function __construct(
        private readonly ArtifactReader $artifactReader,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly DocRepository $docRepository,
        private readonly CardRepository $cardRepository,
    ) {
    }

    // Imports the export folder and returns how many projects, files and cards it holds
    /**
     * @return array{projects: int, docs: int, cards: int}
     */
    public function import(string $dir): array
    {
        $rows = $this->artifactReader->read($dir);

        $projects = [];
        foreach ($rows['projects'] as $row) {
            $projects[$row['slug']] = $this->project($row);
        }
        $docs = [];
        foreach ($rows['docs'] as $row) {
            $docs[$row['slug']] = $this->doc($row, $projects);
        }
        foreach ($rows['cards'] as $row) {
            $this->card($row, $projects[$row['project']], $docs);
        }
        $this->entityManager->flush();

        return ['projects' => \count($rows['projects']), 'docs' => \count($rows['docs']), 'cards' => \count($rows['cards'])];
    }

    // Project found by its slug or created, then given the export's name, colour, sites and place
    /**
     * @param array{slug: string, name: string, color: ?string, sites: list<string>, position: int} $row
     */
    private function project(array $row): Project
    {
        $project = $this->projectRepository->findOneBy(['slug' => $row['slug']]) ?? new Project($row['slug'], $row['name']);
        $project->setName($row['name'])->setSites($row['sites'])->setPosition($row['position']);
        if (null !== $row['color']) {
            $project->setColor($row['color']);
        }
        $this->entityManager->persist($project);

        return $project;
    }

    // File found by its slug or created, with both floors rewritten
    /**
     * @param array{slug: string, title: string, project: ?string, kind: string, summary: ?string, detail: ?string} $row
     * @param array<string, Project>                                                                                $projects
     */
    private function doc(array $row, array $projects): Doc
    {
        $doc = $this->docRepository->findOneBy(['slug' => $row['slug']]) ?? new Doc($row['slug'], $row['title']);
        $doc->setTitle($row['title'])->setProject($projects[$row['project'] ?? ''] ?? null)->setKind(DocKind::from($row['kind']))->write($row['summary'], $row['detail']);
        $this->entityManager->persist($doc);

        return $doc;
    }

    // Card found by its id in the artifact or created, open or done as it was there
    /**
     * @param array{id: string, project: string, title: string, note: ?string, due: ?string, doc: ?string, doneAt: ?string, checklist: list<array{t: string, done?: bool, doneAt?: string}>} $row
     * @param array<string, Doc>                                                                                                                                                             $docs
     */
    private function card(array $row, Project $project, array $docs): void
    {
        // Ids only being unique inside a project in the artifact, the project is part of the key
        $sourceId = $row['project'] . ':' . $row['id'];
        $card = $this->cardRepository->findOneBy(['sourceId' => $sourceId]) ?? new Card($project, $row['title']);
        $card->setTitle($row['title'])
            ->setSourceId($sourceId)
            ->setNote($row['note'])
            ->setDue(null === $row['due'] ? null : new \DateTimeImmutable($row['due']))
            ->setDoc($docs[$row['doc'] ?? ''] ?? null)
            ->setChecklist($row['checklist'])
            ->markDone(null === $row['doneAt'] ? null : new \DateTimeImmutable($row['doneAt']));
        $this->entityManager->persist($card);
    }
}
