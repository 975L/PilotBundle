<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Repository;

use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\DocKind;
use c975L\PilotBundle\Repository\DocRepository;
use PHPUnit\Framework\TestCase;

class DocRepositoryTest extends TestCase
{
    // A project's objectives already written are the ones handed back, nothing created
    public function testObjectivesOfKeepsTheExistingDocument(): void
    {
        $project = new Project('catalog', 'Catalog');
        $existing = new Doc('catalog-objectives', 'Objectives')->setProject($project)->setKind(DocKind::Objectives);
        $repository = $this->createPartialMock(DocRepository::class, ['findOneBy', 'newFile']);
        $repository->expects($this->once())->method('findOneBy')->with(['project' => $project, 'kind' => DocKind::Objectives])->willReturn($existing);
        $repository->expects($this->never())->method('newFile');

        $this->assertSame($existing, $repository->objectivesOf($project, 'Objectives'));
    }

    // The first time, a new document of the project is made and marked as its objectives
    public function testObjectivesOfCreatesTheDocumentTheFirstTime(): void
    {
        $project = new Project('catalog', 'Catalog');
        $created = new Doc('catalog-objectives', 'Objectives')->setProject($project);
        $repository = $this->createPartialMock(DocRepository::class, ['findOneBy', 'newFile']);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects($this->once())->method('newFile')->with($project, 'Objectives')->willReturn($created);

        $doc = $repository->objectivesOf($project, 'Objectives');

        $this->assertSame($created, $doc);
        $this->assertSame(DocKind::Objectives, $doc->getKind());
    }
}
