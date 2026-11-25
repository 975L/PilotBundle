<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\PlanFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class PlanFactoryTest extends TestCase
{
    private CardFlow $flow;
    private PlanFactory $factory;
    private PlanTemplate $template;

    protected function setUp(): void
    {
        $this->flow = new CardFlow(new MockClock('2026-11-17 10:00'));
        $this->factory = new PlanFactory($this->flow);
        $this->template = new PlanTemplate('catalog', [
            ['title' => 'Write {item}'],
            ['title' => 'Build {item}', 'action' => 'build', 'params' => ['item' => '{item}', 'mode' => 'test']],
            ['title' => 'Publish'],
        ]);
    }

    // The variables of a template are the {names} of its titles and parameters, each once
    public function testVariables(): void
    {
        $this->assertSame(['item'], $this->factory->variables($this->template));
    }

    // A template makes a single card named after the plan, each step a box naming its action, the card opened
    public function testChecklist(): void
    {
        $plan = $this->factory->create($this->template, new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1']);
        $cards = $plan->getCards()->getValues();

        $this->assertCount(1, $cards);
        $this->assertSame('Item 1', $cards[0]->getTitle());
        $this->assertSame([['t' => 'Write item-1'], ['t' => 'Build item-1', 'action' => 'build', 'params' => ['item' => 'item-1', 'mode' => 'test']], ['t' => 'Publish']], $cards[0]->getChecklist());
        $this->assertSame(CardStatus::Ready, $cards[0]->getStatus());
    }

    // The steps a template step waits for, typed as numbers, reach the card as the keys of those steps; « none » stays empty, and a step naming none waits for the previous one
    public function testChecklistAfter(): void
    {
        $template = new PlanTemplate('catalog', [
            ['title' => 'Write {item}'],
            ['title' => 'Build {item}', 'action' => 'build', 'after' => []],
            ['title' => 'Publish', 'after' => [1, 2, 3, 9]],
        ]);
        $card = $this->factory->create($template, new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1'])->getCards()->first();
        $steps = $card->getChecklist();

        $this->assertArrayNotHasKey('after', $steps[0]);
        $this->assertSame([], $steps[1]['after'] ?? null);
        $this->assertSame(['text:Write item-1', 'action:build'], $steps[2]['after'] ?? null);
        $this->assertSame([0, 1], $card->stepNeeds(2));
    }

    // A template step's group reaches the card's step, a step naming none staying out of any
    public function testChecklistGroups(): void
    {
        $template = new PlanTemplate('catalog', [['title' => 'Write', 'group' => 'Book'], ['title' => 'Publish']]);
        $card = $this->factory->create($template, new Project('catalog', 'Catalog'), 'Item 1', [])->getCards()->first();

        $this->assertSame([['t' => 'Write', 'g' => 'Book'], ['t' => 'Publish']], $card->getChecklist());
    }
}
