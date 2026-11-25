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
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Service\CardFiller;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\PlanFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class CardFillerTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('catalog', 'Catalog');
    }

    // One card per line in the space, empty lines, titles already there and repeated ones left aside
    public function testOneCardPerNewLine(): void
    {
        $cards = $this->filler(['item-1'])->fill($this->project, "item-1\n\n  item-2  \r\nitem-3\nitem-2\n");

        $this->assertSame(['item-2', 'item-3'], array_map(static fn (Card $card): string => $card->getTitle(), $cards));
        $this->assertSame($this->project, $cards[0]->getProject());
        $this->assertSame([], $cards[0]->getChecklist());
        $this->assertNull($cards[0]->getTemplate());
    }

    // Each card gets the steps of its collection's checklist template, its variables filled with the card's title, and follows it
    public function testTemplateSteps(): void
    {
        $template = new PlanTemplate('catalog', [
            ['title' => 'Write {item}'],
            ['title' => 'Build', 'action' => 'build', 'params' => ['item' => '{item}']],
        ]);

        $card = $this->filler()->card($this->project, 'item-1', new CardSet($this->project, 'Stories')->setTemplate($template));

        $this->assertSame([['t' => 'Write item-1'], ['t' => 'Build', 'action' => 'build', 'params' => ['item' => 'item-1']]], $card->getChecklist());
        $this->assertSame($template, $card->getTemplate());
    }

    // A card added to a collection goes into it and follows its template; a collection of another space is ignored, the card then laid bare
    public function testCollection(): void
    {
        $own = new PlanTemplate('catalog', [['title' => 'Draw']]);
        $set = new CardSet($this->project, 'Strips')->setTemplate($own);

        $card = $this->filler()->fill($this->project, 'item-1', $set)[0];
        $this->assertSame($set, $card->getCardSet());
        $this->assertSame([['t' => 'Draw']], $card->getChecklist());

        $stranger = $this->filler()->card($this->project, 'item-2', new CardSet(new Project('other', 'Other'), 'Other')->setTemplate($own));
        $this->assertNull($stranger->getCardSet());
        $this->assertSame([], $stranger->getChecklist());
    }

    // No more lines than the cap, a title cut to its column
    public function testBounds(): void
    {
        $lines = implode("\n", array_map(static fn (int $index): string => 'item-' . $index, range(1, CardFiller::MAX_LINES + 5)));

        $this->assertCount(CardFiller::MAX_LINES, $this->filler()->fill($this->project, $lines));
        $this->assertSame(500, mb_strlen($this->filler()->fill($this->project, str_repeat('é', 600))[0]->getTitle()));
    }

    /** @param list<string> $known */
    private function filler(array $known = []): CardFiller
    {
        $cards = $this->createStub(CardRepository::class);
        $cards->method('titlesOf')->willReturn($known);

        return new CardFiller($cards, new PlanFactory(new CardFlow(new MockClock())));
    }
}
