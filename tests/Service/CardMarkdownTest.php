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

    // The feedback after the steps, a question still to decide written with « ? »: read back the same, a list left out keeping what the card had
    public function testFeedback(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'done' => true]])->addFeedback('feedback-1')->addFeedback('? feedback-2');
        $this->assertWrittenAndReadBack($card, "## Steps\n\n- [x] step-1\n\n## Feedback\n\n- [ ] feedback-1\n- [ ] ? feedback-2\n");

        $this->read($card, "## Feedback\n\n- [x] ? feedback-2\n- [ ] feedback-3\n");
        $this->assertSame([['t' => 'step-1', 'done' => true], ['t' => 'feedback-2', 'feedback' => true, 'done' => true, 'doneAt' => '2026-11-21', 'ask' => true], ['t' => 'feedback-3', 'feedback' => true]], $card->getChecklist());

        $this->read($card, "## Steps\n\n- [ ] step-1\n");
        $this->assertSame(['step-1', 'feedback-2', 'feedback-3'], array_column($card->getChecklist(), 't'));
        $this->assertFalse($card->getChecklist()[0]['done'] ?? false);
    }

    // What write() gives reads back to the same card
    public function testRoundTrip(): void
    {
        $card = $this->card();
        $this->read($card, self::FULL);

        $this->assertSame(self::FULL, $this->markdown()->write($card));
    }

    // Each part rewritten: title, date cleared, wait, note, a step ticked today keeping its cost, a new step, the reference; project and status left alone
    public function testRead(): void
    {
        $card = $this->card();
        $card->setChecklist([['t' => 'step-1', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'step-2', 'cost' => ['credit' => 'item-1', 'amount' => 3], 'action' => 'build']]);
        $this->assertStringContainsString("- [ ] step-2 `build`\n", $this->markdown()->write($card));
        $markdown = "# item-2\n\n- Project: Other\n- Status: done\n- Due:\n- Waiting on: other\n\nnote-2\n\n## Steps\n\n- [x] step-1\n- [x] step-2 `build`\n- [ ] step-3\n\n## Reference\n\n# File 2\n\ndetail-2\n";

        $this->read($card, $markdown);

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
        $this->read($card, "## Steps\n\n- [ ] step-9\n");
        $this->read($card, "# item-1\n\n- Due: soon\n\nnote-1");

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

        $this->read($card, $markdown);
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

        $this->read($card, "## Steps\n\n- [x] review\n- [ ] review\n- [ ] build `build`\n");
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

    // The card written ending with the given text, then read back into the same text
    private function assertWrittenAndReadBack(Card $card, string $end): void
    {
        $markdown = $this->markdown()->write($card);

        $this->assertStringEndsWith($end, $markdown);
        $this->assertSame($markdown, $this->markdown()->write($this->read($card, $markdown)));
    }

    // A text read into the card on a fixed day
    private function read(Card $card, string $text): Card
    {
        return $this->markdown()->read($card, $text, new \DateTimeImmutable('2026-11-21'));
    }

    private function markdown(): CardMarkdown
    {
        return new CardMarkdown($this->createStub(DocRepository::class));
    }

    // Each group of steps written under its « ### » heading, « ### - » closing it, read back the same; a step moved under another heading changes group
    public function testGroups(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'g' => 'Book'], ['t' => 'step-2', 'g' => 'Book'], ['t' => 'step-3'], ['t' => 'step-4', 'g' => 'Film']]);
        $this->assertWrittenAndReadBack($card, "## Steps\n\n\n### Book\n\n- [ ] step-1\n- [ ] step-2\n\n### -\n\n- [ ] step-3\n\n### Film\n\n- [ ] step-4\n");

        $this->read($card, "## Steps\n\n- [ ] step-3\n\n### Film\n\n- [ ] step-1\n");
        $this->assertSame([['t' => 'step-3'], ['t' => 'step-1', 'g' => 'Film']], $card->getChecklist());
    }

    // The day a step is planned for written after its text, before its action, read back; removed, or invalid, it clears
    public function testStepDue(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'due' => '2027-02-03', 'action' => 'action-1'], ['t' => 'step-2', 'due' => '2027-03-03']]);
        $markdown = $this->markdown()->write($card);

        $this->assertStringContainsString("- [ ] step-1 @2027-02-03 `action-1`\n- [ ] step-2 @2027-03-03\n", $markdown);
        $this->assertSame($markdown, $this->markdown()->write($this->read($card, $markdown)));

        $this->read($card, "## Steps\n\n- [ ] step-1 `action-1`\n- [ ] step-2 @2027-13-45\n- [ ] step-3 @2027-04-07\n");
        $this->assertSame([['t' => 'step-1', 'action' => 'action-1'], ['t' => 'step-2'], ['t' => 'step-3', 'due' => '2027-04-07']], $card->getChecklist());
    }

    // The links written in the header when set, read back, an empty one clearing it and one that is not http(s) dropped
    public function testLinks(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setSourceUrl('https://example.com/source');
        $markdown = $this->markdown()->write($card);

        $this->assertStringContainsString("- Source: https://example.com/source\n", $markdown);
        $this->assertStringNotContainsString('- Result:', $markdown);

        $this->read($card, "# item-1\n\n- Source:\n- Result: https://example.com/result\n");
        $this->assertNull($card->getSourceUrl());
        $this->assertSame('https://example.com/result', $card->getResultUrl());

        $this->read($card, "# item-1\n\n- Result: ftp://example.com\n");
        $this->assertNull($card->getResultUrl());
    }
}
