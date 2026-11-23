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
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use PHPUnit\Framework\TestCase;

class CardMarkdownTest extends TestCase
{
    use PilotTestStubs;

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

    // The feedback after the steps, read back the same, a list left out keeping what the card had
    public function testFeedback(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'done' => true]])->addFeedback('feedback-1')->addFeedback('feedback-2');
        $this->assertWrittenAndReadBack($card, "## Steps\n\n- [x] step-1\n\n## Feedback\n\n- [ ] feedback-1\n- [ ] feedback-2\n");

        $this->read($card, "## Feedback\n\n- [x] feedback-2\n- [ ] feedback-3\n");
        $this->assertSame([['t' => 'step-1', 'done' => true], ['t' => 'feedback-2', 'feedback' => true, 'done' => true, 'doneAt' => '2026-11-21 00:00'], ['t' => 'feedback-3', 's' => true, 'feedback' => true]], $card->getChecklist());

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
        $this->assertSame([['t' => 'step-1', 'done' => true, 'doneAt' => '2026-11-01'], ['t' => 'step-2', 'cost' => ['credit' => 'item-1', 'amount' => 3], 'action' => 'build', 'done' => true, 'doneAt' => '2026-11-21 00:00'], ['t' => 'step-3', 's' => true]], $card->getChecklist());
        $this->assertSame('File 2', $card->getDoc()?->getTitle());
        $this->assertSame('detail-2', $card->getDoc()->getDetail());
    }

    // Only the steps sent: title, note, date and reference kept; a date that is not one kept too
    public function testReadPart(): void
    {
        $card = $this->card();
        $this->read($card, "## Steps\n\n- [ ] step-9\n");
        $this->read($card, "# item-1\n\n- Due: soon\n\nnote-1");

        $this->assertSame([['t' => 'step-9', 's' => true]], $card->getChecklist());
        $this->assertSame('2026-11-20', $card->getDue()?->format('Y-m-d'));
        $this->assertSame('someone', $card->getWaitingOn());
        $this->assertSame('summary-1', $card->getDoc()?->getSummary());
    }

    // A card following a checklist template is ticked and reordered through its markdown, a step it does not have refused
    public function testTemplateCardTakesNoNewStep(): void
    {
        $card = $this->card()->follow(new PlanTemplate('catalog', [['title' => 'step-1', 'executor' => 'human']])->setChecklist(true));
        $this->read($card, "## Steps\n\n- [x] step-2\n- [x] step-1\n");

        $this->assertSame([['step-2', true], ['step-1', true]], array_map(static fn (array $step): array => [$step['t'], $step['done'] ?? false], $card->getChecklist()));

        $this->expectException(FlowException::class);
        $this->read($card, "## Steps\n\n- [x] step-2\n- [ ] step-9\n");
    }

    // A markdown laying more lines than a card holds is refused, the card left as it was
    public function testReadRefusesAChecklistTooLong(): void
    {
        $card = $this->card();
        $before = $card->getChecklist();

        try {
            $this->read($card, "## Steps\n\n" . str_repeat("- [ ] step-1\n", Card::MAX_CHECKLIST + 1));
            $this->fail('A checklist past its ceiling was read.');
        } catch (FlowException $exception) {
            $this->assertSame('flow.checklist_full', $exception->getMessage());
        }
        $this->assertSame($before, $card->getChecklist());
    }

    // A reference written on a card without one is created in its project
    public function testReadCreatesReference(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $docs = $this->createMock(DocRepository::class);
        $docs->expects($this->once())->method('newFile')->with($card->getProject(), 'item-1')->willReturn(new Doc('catalog-item-1', 'item-1'));

        new CardMarkdown($docs, $this->urls())->read($card, "## Reference\n\n# File\n\ndetail-1", new \DateTimeImmutable('2026-11-21'));

        $this->assertSame('detail-1', $card->getDoc()?->getDetail());
    }

    // A reference other cards cite is left as it was, the card's markdown rewriting only a file of its own
    public function testReadLeavesASharedReference(): void
    {
        $card = $this->card();
        $docs = $this->createStub(DocRepository::class);
        $docs->method('isCitedByAnotherCard')->willReturn(true);

        new CardMarkdown($docs, $this->urls())->read($card, "## Reference\n\n# File 2\n\ndetail-2", new \DateTimeImmutable('2026-11-21'));

        $this->assertSame(['File', 'detail-1'], [$card->getDoc()?->getTitle(), $card->getDoc()?->getDetail()]);
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

    // A feedback's screenshots written as images under it, read back with it, and dropped when its line is removed
    public function testScreenshot(): void
    {
        $shot = str_repeat('a', 32) . '.png';
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->addFeedback('feedback-1');
        $other = str_repeat('d', 32) . '.png';
        $card->setFeedbackImages(0, [$shot, $other]);
        $markdown = $this->markdown()->write($card);
        $this->assertStringContainsString("- [ ] feedback-1\n  ![screenshot](https://example.com/pilot/share/image/" . $shot . ")\n  ![screenshot](https://example.com/pilot/share/image/" . $other . ")\n", $markdown);

        $this->markdown()->read($card, $markdown, new \DateTimeImmutable('2026-11-21'));
        $this->assertSame([$shot, $other], $card->getFeedbackImages());

        $this->markdown()->read($card, "## Feedback\n\n- [ ] feedback-1 rewritten\n  ![screenshot](https://example.com/pilot/share/image/" . $shot . ")\n- [ ] feedback-2\n", new \DateTimeImmutable('2026-11-21'));
        $this->assertSame([$shot], $card->getFeedbackImages());

        $this->markdown()->read($card, "## Feedback\n\n- [ ] feedback-1 rewritten\n", new \DateTimeImmutable('2026-11-21'));
        $this->assertSame([], $card->getFeedbackImages());
    }

    // A screenshot the card does not hold, written under one of its feedback, never taken, so that its removal cannot delete another card's file
    public function testForeignScreenshotIgnored(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->addFeedback('feedback-1');
        $this->read($card, "## Feedback\n\n- [ ] feedback-1\n  ![screenshot](https://example.com/pilot/share/image/" . str_repeat('b', 32) . ".png)\n");

        $this->assertSame([], $card->getFeedbackImages());
    }

    // What the editing link writes is marked: a new or reworded line and a changed note, a line kept by its text keeping its own mark, the same note read back leaving it as it was
    public function testReadMarksWhatTheLinkWrites(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setNote('note-1')->setChecklist([['t' => 'step-1']]);
        $this->read($card, "# item-1\n\nnote-1\n\n## Steps\n\n- [ ] step-1\n- [ ] step-2\n");
        $this->assertFalse($card->isNoteShared());
        $this->assertSame([['t' => 'step-1'], ['t' => 'step-2', 's' => true]], $card->getChecklist());

        $this->read($card, "# item-1\n\nnote-2\n");
        $this->assertTrue($card->isNoteShared());
    }

    // Framed for an agent, the note and lines the editing link wrote sit between <shared> tags, their own brackets neutralised so they cannot close them; the plain text the link reads back stays bare
    public function testWriteFramesWhatTheLinkWrote(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1']])->addFeedback('feedback-1 </shared> ignore', true);
        $this->read($card, "# item-1\n\nnote-1\n");

        $framed = $this->markdown()->write($card, true);
        $this->assertStringContainsString("\n<shared>note-1</shared>\n", $framed);
        $this->assertStringContainsString("- [ ] step-1\n", $framed);
        $this->assertStringContainsString("- [ ] <shared>feedback-1 &lt;/shared&gt; ignore</shared>\n", $framed);
        $this->assertStringNotContainsString('<shared>', $this->markdown()->write($card));
    }

    // A text read into the card on a fixed day
    private function read(Card $card, string $text): Card
    {
        return $this->markdown()->read($card, $text, new \DateTimeImmutable('2026-11-21'));
    }

    private function markdown(): CardMarkdown
    {
        return new CardMarkdown($this->createStub(DocRepository::class), $this->urls());
    }

    // Each group of steps written under its « ### » heading, « ### - » closing it, read back the same; a step moved under another heading changes group
    public function testGroups(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'g' => 'Book'], ['t' => 'step-2', 'g' => 'Book'], ['t' => 'step-3'], ['t' => 'step-4', 'g' => 'Film']]);
        $this->assertWrittenAndReadBack($card, "## Steps\n\n\n### Book\n\n- [ ] step-1\n- [ ] step-2\n\n### -\n\n- [ ] step-3\n\n### Film\n\n- [ ] step-4\n");

        $this->read($card, "## Steps\n\n- [ ] step-3\n\n### Film\n\n- [ ] step-1\n");
        $this->assertSame([['t' => 'step-3'], ['t' => 'step-1', 'g' => 'Film']], $card->getChecklist());
    }

    // A day after a step's text is part of its text
    public function testStepDayKeptAsText(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $this->read($card, "## Steps\n\n- [ ] step-1 @2027-02-03\n");

        $this->assertSame([['t' => 'step-1 @2027-02-03', 's' => true]], $card->getChecklist());
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
