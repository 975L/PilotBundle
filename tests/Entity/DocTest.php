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
}
