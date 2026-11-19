<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use PHPUnit\Framework\TestCase;

class CardSetTest extends TestCase
{
    // The word for one card trimmed, an empty one being none; an empty name keeps the one it had; only a checklist template lays its steps on a new card
    public function testItemNameAndTemplate(): void
    {
        $set = new CardSet(new Project('catalog', 'Catalog'), 'Stories');
        $template = new PlanTemplate('catalog', []);

        $this->assertSame('story', $set->setItem(' story ')->getItem());
        $this->assertNull($set->setItem('  ')->getItem());
        $this->assertSame('Stories', $set->setName('  ')->getName());
        $this->assertSame('Tales', $set->setName(' Tales ')->getName());
        $this->assertNull($set->setTemplate($template)->getChecklistTemplate());
        $this->assertSame($template, $set->setTemplate($template->setChecklist(true))->getChecklistTemplate());
    }

    // A card is a sheet only in a collection of sheets
    public function testSheets(): void
    {
        $project = new Project('catalog', 'Catalog');
        $set = new CardSet($project, 'item-set');
        $card = new Card($project, 'item-1');

        $this->assertFalse($card->isSheet());
        $this->assertFalse($card->setCardSet($set)->isSheet());
        $this->assertTrue($card->setCardSet($set->setSheets(true))->isSheet());
    }
}
