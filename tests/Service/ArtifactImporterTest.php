<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Service\ArtifactImporter;
use c975L\PilotBundle\Service\ArtifactReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ArtifactImporterTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    // Importer over a reader returning fixed rows, the repositories finding what the test hands them
    private function importer(?Card $existing = null): ArtifactImporter
    {
        $reader = $this->createStub(ArtifactReader::class);
        $reader->method('read')->willReturn([
            'projects' => [['slug' => 'catalog', 'name' => 'Catalog', 'color' => null, 'sites' => [], 'position' => 0]],
            'docs' => [['slug' => 'catalog-file', 'title' => 'File', 'project' => 'catalog', 'kind' => 'file', 'summary' => 'Short', 'detail' => 'Long']],
            'cards' => [
                ['id' => 'item-1', 'project' => 'catalog', 'title' => 'Item 1', 'note' => null, 'due' => '2026-12-01', 'doc' => 'catalog-file', 'doneAt' => null, 'checklist' => []],
                ['id' => 'item-2', 'project' => 'catalog', 'title' => 'Item 2', 'note' => 'Note', 'due' => null, 'doc' => null, 'doneAt' => '2026-11-01', 'checklist' => []],
            ],
        ]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Card => 'catalog:item-1' === $criteria['sourceId'] ? $existing : null);

        return new ArtifactImporter($reader, $entityManager, $this->createStub(ProjectRepository::class), $this->createStub(DocRepository::class), $cards);
    }

    // Projects, files and cards are written, the card linked to its file, the done one done on its day
    public function testImportWritesEverything(): void
    {
        $counts = $this->importer()->import('/any');

        $this->assertSame(['projects' => 1, 'docs' => 1, 'cards' => 2], $counts);
        $cards = array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof Card));
        $this->assertCount(2, $cards);
        $this->assertSame('catalog-file', $cards[0]->getDoc()?->getSlug());
        $this->assertSame('2026-12-01', $cards[0]->getDue()?->format('Y-m-d'));
        $this->assertSame(CardStatus::Ready, $cards[0]->getStatus());
        $this->assertSame(CardStatus::Done, $cards[1]->getStatus());
        $this->assertSame('catalog:item-2', $cards[1]->getSourceId());
        $doc = array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof Doc))[0];
        $this->assertSame('Short', $doc->getSummary());
    }

    // A card imported before is updated in place, not duplicated
    public function testSecondImportUpdatesTheCard(): void
    {
        $existing = new Card(new Project('catalog', 'Catalog'), 'Old title');

        $this->importer($existing)->import('/any');

        $this->assertSame('Item 1', $existing->getTitle());
        $this->assertContains($existing, $this->persisted);
    }
}
