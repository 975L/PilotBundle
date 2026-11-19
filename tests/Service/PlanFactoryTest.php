<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
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
            ['title' => 'Write {item}', 'executor' => 'human'],
            ['title' => 'Build {item}', 'executor' => 'runner', 'action' => 'build', 'params' => ['item' => '{item}', 'mode' => 'test']],
            ['title' => 'Publish', 'executor' => 'human'],
        ]);
    }

    // The variables of a template are the {names} of its titles and parameters, each once
    public function testVariables(): void
    {
        $this->assertSame(['item'], $this->factory->variables($this->template));
    }

    // A plan gets one card per step with its variables replaced, the first one to do and the others waiting
    public function testCreate(): void
    {
        $plan = $this->factory->create($this->template, new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1']);
        $cards = $plan->getCards()->getValues();

        $this->assertSame(['Write item-1', 'Build item-1', 'Publish'], array_map(static fn ($card): string => $card->getTitle(), $cards));
        $this->assertSame(['item' => 'item-1', 'mode' => 'test'], $cards[1]->getParams());
        $this->assertTrue($cards[1]->isRunner());
        $this->assertSame([CardStatus::Ready, CardStatus::Todo, CardStatus::Todo], array_map(static fn ($card): CardStatus => $card->getStatus(), $cards));
    }

    // A checklist template makes a single card named after the plan, each step a box naming its action, the card opened
    public function testChecklist(): void
    {
        $plan = $this->factory->create($this->template->setChecklist(true), new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1']);
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
            ['title' => 'Write {item}', 'executor' => 'human'],
            ['title' => 'Build {item}', 'executor' => 'runner', 'action' => 'build', 'after' => []],
            ['title' => 'Publish', 'executor' => 'human', 'after' => [1, 2, 3, 9]],
        ]);
        $card = $this->factory->create($template->setChecklist(true), new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1'])->getCards()->first();
        $steps = $card->getChecklist();

        $this->assertArrayNotHasKey('after', $steps[0]);
        $this->assertSame([], $steps[1]['after'] ?? null);
        $this->assertSame(['text:Write item-1', 'action:build'], $steps[2]['after'] ?? null);
        $this->assertSame([0, 1], $card->stepNeeds(2));
    }

    // Closing a card opens the next one, a machine card waiting for its button, and its validation opens the one after
    public function testCardsFollowEachOther(): void
    {
        $cards = $this->factory->create($this->template, new Project('catalog', 'Catalog'), 'Item 1', ['item' => 'item-1'])->getCards()->getValues();

        $this->flow->complete($cards[0]);
        $this->assertSame(CardStatus::Done, $cards[0]->getStatus());
        $this->assertSame(CardStatus::Ready, $cards[1]->getStatus());
        $this->assertSame([], $cards[1]->activeRuns());

        $run = $this->flow->launch($cards[1]);

        $run->claim(new Machine('machine-1', ''), new \DateTimeImmutable())->finish(RunStatus::Done, null, [], null, new \DateTimeImmutable());
        $this->flow->runFinished($run);
        $this->flow->validate($cards[1]);
        $this->assertSame(CardStatus::Ready, $cards[2]->getStatus());
    }

    // A template step's group reaches the card's step, a step naming none staying out of any
    public function testChecklistGroups(): void
    {
        $template = new PlanTemplate('catalog', [['title' => 'Write', 'executor' => 'human', 'group' => 'Book'], ['title' => 'Publish', 'executor' => 'human']])->setChecklist(true);
        $card = $this->factory->create($template, new Project('catalog', 'Catalog'), 'Item 1', [])->getCards()->first();

        $this->assertSame([['t' => 'Write', 'g' => 'Book'], ['t' => 'Publish']], $card->getChecklist());
    }
}
