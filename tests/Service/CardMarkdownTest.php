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
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\CardMarkdown;
use PHPUnit\Framework\TestCase;

class CardMarkdownTest extends TestCase
{
    private const string FULL = <<<'MD'
        # item-1

        - Project: Catalog
        - Status: ready
        - Due: 2026-11-20
        - Waiting on: someone

        note-1

        ## Steps

        - [x] step-1
        - [ ] step-2

        ## Reference

        # File

        ## L'essentiel

        summary-1

        detail-1

        MD;

    // The whole card: header, note, steps ticked or not, the reference with both floors
    public function testWrite(): void
    {
        $this->assertSame(self::FULL, $this->markdown()->write($this->card()));
    }

    // A bare card still writes its date and awaited answer, empty, so that clearing them reads back
    public function testWriteBare(): void
    {
        $this->assertSame("# item-1\n\n- Project: Catalog\n- Status: ready\n- Due: \n- Waiting on: \n", $this->markdown()->write(new Card(new Project('catalog', 'Catalog'), 'item-1')));
    }

    // What write() gives reads back to the same card
    public function testRoundTrip(): void
    {
        $card = $this->card();
        $this->markdown()->read($card, self::FULL, new \DateTimeImmutable('2026-11-21'));

        $this->assertSame(self::FULL, $this->markdown()->write($card));
    }

    // Each part rewritten: title, date cleared, wait, note, a step ticked today keeping its cost, a new step, the reference; project and status left alone
    public function testRead(): void
    {
        $card = $this->card();
        $card->setChecklist([['t' => 'step-1', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'step-2', 'cost' => ['credit' => 'item-1', 'amount' => 3], 'action' => 'build']]);
        $this->assertStringContainsString("- [ ] step-2 `build`\n", $this->markdown()->write($card));
        $markdown = "# item-2\n\n- Project: Other\n- Status: done\n- Due:\n- Waiting on: other\n\nnote-2\n\n## Steps\n\n- [x] step-1\n- [x] step-2 `build`\n- [ ] step-3\n\n## Reference\n\n# File 2\n\ndetail-2\n";

        $this->markdown()->read($card, $markdown, new \DateTimeImmutable('2026-11-21'));

        $this->assertSame('item-2', $card->getTitle());
        $this->assertSame('Catalog', $card->getProject()->getName());
        $this->assertSame('ready', $card->getStatus()->value);
        $this->assertNull($card->getDue());
        $this->assertSame('other', $card->getWaitingOn());
        $this->assertSame('note-2', $card->getNote());
        $this->assertSame([['t' => 'step-1', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'step-2', 'cost' => ['credit' => 'item-1', 'amount' => 3], 'action' => 'build', 'done' => true, 'doneAt' => '2026-11-21'], ['t' => 'step-3']], $card->getChecklist());
        $this->assertSame('File 2', $card->getDoc()?->getTitle());
        $this->assertSame('detail-2', $card->getDoc()->getDetail());
    }

    // Only the steps sent: title, note, date and reference kept; a date that is not one kept too
    public function testReadPart(): void
    {
        $card = $this->card();
        $this->markdown()->read($card, "## Steps\n\n- [ ] step-9\n", new \DateTimeImmutable('2026-11-21'));
        $this->markdown()->read($card, "# item-1\n\n- Due: soon\n\nnote-1", new \DateTimeImmutable('2026-11-21'));

        $this->assertSame([['t' => 'step-9']], $card->getChecklist());
        $this->assertSame('2026-11-20', $card->getDue()?->format('Y-m-d'));
        $this->assertSame('someone', $card->getWaitingOn());
        $this->assertSame('summary-1', $card->getDoc()?->getSummary());
    }

    // A reference written on a card without one is created in its project
    public function testReadCreatesReference(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $docs = $this->createMock(DocRepository::class);
        $docs->expects($this->once())->method('newFile')->with($card->getProject(), 'item-1')->willReturn(new Doc('catalog-item-1', 'item-1'));

        new CardMarkdown($docs)->read($card, "## Reference\n\n# File\n\ndetail-1", new \DateTimeImmutable('2026-11-21'));

        $this->assertSame('detail-1', $card->getDoc()?->getDetail());
    }

    // A note carrying a heading and a field line reads back as the note, the title and the date left as they were
    public function testNoteKeepsItsHeadingsAndFields(): void
    {
        $card = $this->card()->setNote("# Context\n\n- Due: 2026-12-01\nnote-1");
        $markdown = $this->markdown()->write($card);

        $this->markdown()->read($card, $markdown, new \DateTimeImmutable('2026-11-21'));
        $this->assertSame('item-1', $card->getTitle());
        $this->assertSame('2026-11-20', $card->getDue()?->format('Y-m-d'));
        $this->assertSame("# Context\n\n- Due: 2026-12-01\nnote-1", $card->getNote());
        $this->assertSame($markdown, $this->markdown()->write($card));
    }

    // Steps of the same text are each kept once, in order, with their tick, and the runs follow the step they do
    public function testReadStepsOfTheSameText(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'review', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'build', 'action' => 'build'], ['t' => 'review']]);
        $run = new Run($card, 'build', [], 1);

        $this->markdown()->read($card, "## Steps\n\n- [x] review\n- [ ] review\n- [ ] build `build`\n", new \DateTimeImmutable('2026-11-21'));
        $this->assertSame([['t' => 'review', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'review'], ['t' => 'build', 'action' => 'build']], $card->getChecklist());
        $this->assertSame(2, $run->getStep());
    }

    private function card(): Card
    {
        return new Card(new Project('catalog', 'Catalog'), 'item-1')
            ->setDue(new \DateTimeImmutable('2026-11-20'))
            ->setWaitingOn('someone')
            ->setNote('note-1')
            ->setChecklist([['t' => 'step-1', 'done' => true], ['t' => 'step-2']])
            ->setDoc(new Doc('catalog-file', 'File')->write('summary-1', 'detail-1'));
    }

    private function markdown(): CardMarkdown
    {
        return new CardMarkdown($this->createStub(DocRepository::class));
    }
}
