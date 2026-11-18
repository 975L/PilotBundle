<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Doc;
use PHPUnit\Framework\TestCase;

class DocTest extends TestCase
{
    // The download puts the title, the summary under its heading, then the detail, in one markdown file
    public function testToMarkdownJoinsBothFloors(): void
    {
        $doc = new Doc('catalog-file', 'Catalog')->write("Short\n", 'Long');

        $this->assertSame("# Catalog\n\n" . Doc::SUMMARY_HEADING . "\n\nShort\n\nLong\n", $doc->toMarkdown());
    }

    // A file without summary nor detail still downloads its title
    public function testToMarkdownWithoutContent(): void
    {
        $this->assertSame("# Catalog\n", new Doc('catalog-file', 'Catalog')->toMarkdown());
    }

    // A file read back from its markdown keeps its title, summary and detail, and a file without a summary heading is all detail
    public function testFromMarkdownReadsWhatToMarkdownWrites(): void
    {
        $doc = new Doc('item-1', 'Old')->write("Five lines\nfor a human", "## Steps\n\n1. first");
        $copy = new Doc('item-1', 'Other')->fromMarkdown($doc->setTitle('Catalog')->toMarkdown());

        $this->assertSame('Catalog', $copy->getTitle());
        $this->assertSame("Five lines\nfor a human", $copy->getSummary());
        $this->assertSame("## Steps\n\n1. first", $copy->getDetail());

        $plain = new Doc('item-2', 'Plain')->fromMarkdown("Just a note\n\nand more");
        $this->assertSame('Plain', $plain->getTitle());
        $this->assertNull($plain->getSummary());
        $this->assertSame("Just a note\n\nand more", $plain->getDetail());
    }

    // Boxes counted through the summary then the detail: ticked with their day, unticked without it, none past the last
    public function testToggleTask(): void
    {
        $doc = new Doc('catalog-objectives', 'Objectives')->write("- [ ] item-1\n", "Intro\n\n- [ ] item-2\n  - [x] item-3 (01/03/2026)\n");
        $this->toggleSecondAndThird($doc);

        $this->assertSame("- [ ] item-1\n", $doc->getSummary());
        $this->assertSame("Intro\n\n- [x] item-2 (18/11/2026)\n  - [ ] item-3\n", $doc->getDetail());
    }

    // Boxes counted as the renderer draws them: numbered and quoted items count, those of a fenced code block do not
    public function testToggleTaskCountsLikeTheRenderer(): void
    {
        $doc = new Doc('catalog-objectives', 'Objectives')->write(null, "```\n- [ ] code-1\n```\n1. [ ] item-1\n~~~md\n- [ ] code-2\n```\n~~~\n> - [ ] item-2\n2) [ ] item-3\n");
        $this->toggleSecondAndThird($doc);
        $this->assertSame("```\n- [ ] code-1\n```\n1. [ ] item-1\n~~~md\n- [ ] code-2\n```\n~~~\n> - [x] item-2 (18/11/2026)\n2) [x] item-3 (18/11/2026)\n", $doc->getDetail());
    }

    // The second and the third boxes toggled on 18/11/2026, none found at the fourth
    private function toggleSecondAndThird(Doc $doc): void
    {
        $day = new \DateTimeImmutable('2026-11-18');
        $this->assertTrue($doc->toggleTask(1, $day));
        $this->assertTrue($doc->toggleTask(2, $day));
        $this->assertFalse($doc->toggleTask(3, $day));
    }
}
