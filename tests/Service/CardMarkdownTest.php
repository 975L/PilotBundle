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

    // A bare card still writes its date and awaited answer, empty
    public function testWriteBare(): void
    {
        $this->assertSame("# item-1\n\n- Project: Catalog\n- Status: ready\n- Due: \n- Waiting on: \n", $this->markdown()->write(new Card(new Project('catalog', 'Catalog'), 'item-1')));
    }

    // The feedback after the steps, a step's machine action after its text
    public function testFeedback(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'done' => true, 'action' => 'build']])->addFeedback('feedback-1')->addFeedback('feedback-2');

        $this->assertStringEndsWith("## Steps\n\n- [x] step-1 `build`\n\n## Feedback\n\n- [ ] feedback-1\n- [ ] feedback-2\n", $this->markdown()->write($card));
    }

    // A feedback's screenshots written as images under it, at the address an agent reads them from
    public function testScreenshot(): void
    {
        $shot = str_repeat('a', 32) . '.png';
        $other = str_repeat('d', 32) . '.png';
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->addFeedback('feedback-1');
        $card->setFeedbackImages(0, [$shot, $other]);

        $this->assertStringContainsString("- [ ] feedback-1\n  ![screenshot](https://example.com/pilot/share/image/" . $shot . ")\n  ![screenshot](https://example.com/pilot/share/image/" . $other . ")\n", $this->markdown()->write($card));
    }

    // Each group of steps written under its « ### » heading, « ### - » closing it
    public function testGroups(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'g' => 'Book'], ['t' => 'step-2', 'g' => 'Book'], ['t' => 'step-3'], ['t' => 'step-4', 'g' => 'Film']]);

        $this->assertStringEndsWith("## Steps\n\n\n### Book\n\n- [ ] step-1\n- [ ] step-2\n\n### -\n\n- [ ] step-3\n\n### Film\n\n- [ ] step-4\n", $this->markdown()->write($card));
    }

    // The links written in the header when set, an unset one left out
    public function testLinks(): void
    {
        $markdown = $this->markdown()->write(new Card(new Project('catalog', 'Catalog'), 'item-1')->setSourceUrl('https://example.com/source'));

        $this->assertStringContainsString("- Source: https://example.com/source\n", $markdown);
        $this->assertStringNotContainsString('- Result:', $markdown);
    }

    // A line once marked by a shared link written as plain text, no tag around it
    public function testOldSharedMarkIgnored(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 's' => true]]);

        $this->assertStringEndsWith("- [ ] step-1\n", $this->markdown()->write($card));
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
        return new CardMarkdown($this->urls());
    }
}
