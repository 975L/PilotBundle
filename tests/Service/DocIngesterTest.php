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
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\DocIngester;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DocIngesterTest extends TestCase
{
    // A <slug>.doc.md file becomes a new file of the card's project attached to the card; any other name is left aside
    public function testIngestAttachesTheFileToTheCard(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, "# Catalog\n\n## L'essentiel\n\nShort\n\n## Steps\n\nLong");
        $project = new Project('catalog', 'Catalog');
        $card = new Card($project, 'item-1');
        $ingester = new DocIngester($this->createStub(DocRepository::class), $this->createStub(EntityManagerInterface::class));

        $this->assertFalse($ingester->ingest('reponse.md', $path, $card));
        $this->assertTrue($ingester->ingest('item-1.doc.md', $path, $card));
        $this->assertSame('item-1', $card->getDoc()?->getSlug());
        $this->assertSame('Catalog', $card->getDoc()->getTitle());
        $this->assertSame($project, $card->getDoc()->getProject());
        $this->assertSame('Short', $card->getDoc()->getSummary());
        unlink($path);
    }
}
