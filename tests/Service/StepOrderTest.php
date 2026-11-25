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
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Service\StepOrder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class StepOrderTest extends TestCase
{
    // A card following a checklist template keeps the template's order, the template and its other cards untouched
    public function testReorderKeepsTheTemplateOrder(): void
    {
        $project = new Project('catalog', 'Catalog');
        $template = new PlanTemplate('catalog', [['title' => 'a'], ['title' => 'b']]);
        $card = new Card($project, 'item-1')->follow($template)->setChecklist([['t' => 'a'], ['t' => 'b']]);

        new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-19 10:00'))->reorder($card, [1, 0]);

        $this->assertSame(['a', 'b'], array_column($card->getChecklist(), 't'));
        $this->assertSame(['a', 'b'], array_column($template->getSteps(), 'title'));
    }

    // A step whose title holds a {variable} is still known on the cards that filled it: a template saved keeps its tick, a step added is filled with the card's title
    public function testStepsWithVariables(): void
    {
        $project = new Project('catalog', 'Catalog');
        $template = new PlanTemplate('catalog', [['title' => 'Write {item}'], ['title' => 'Build', 'action' => 'build']]);
        $card = new Card($project, 'item-1')->follow($template)->setChecklist([['t' => 'Write item-1', 'done' => true], ['t' => 'Build', 'action' => 'build']]);
        $repository = $this->createStub(CardRepository::class);
        $repository->method('findOpenOfTemplate')->willReturn([$card]);
        $order = new StepOrder($repository, new MockClock('2026-11-19 10:00'));

        $order->fromTemplate($template, $template->getSteps());
        $this->assertSame([['t' => 'Write item-1', 'done' => true], ['t' => 'Build', 'action' => 'build']], $card->getChecklist());

        $previous = $template->getSteps();
        $order->fromTemplate($template->setSteps([...$previous, ['title' => 'Clip {item}']]), $previous);
        $this->assertSame(['Write item-1', 'Build', 'Clip item-1'], array_column($card->getChecklist(), 't'));
    }

    // A step moved one place up; the first one stays
    public function testUp(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a'], ['t' => 'b'], ['t' => 'c']]);
        $order = new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-18 10:00'));

        $order->up($card, 2);
        $order->up($card, 0);
        $this->assertSame(['a', 'c', 'b'], array_column($card->getChecklist(), 't'));
    }

    // A step and a feedback never swap places
    public function testUpKeepsTheFeedbackApart(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a'], ['t' => 'b', 'feedback' => true], ['t' => 'c', 'feedback' => true]]);
        $order = new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-19 10:00'));

        $order->up($card, 1);
        $this->assertSame(['a', 'b', 'c'], array_column($card->getChecklist(), 't'));
        $order->up($card, 2);
        $this->assertSame(['a', 'c', 'b'], array_column($card->getChecklist(), 't'));
    }

    // A rewrite that only moved the steps of a template's card gets the template's order back, the runs following their step; a rewrite adding a step stays the card's own
    public function testFollowARewrite(): void
    {
        $project = new Project('catalog', 'Catalog');
        $template = new PlanTemplate('catalog', [['title' => 'a'], ['title' => 'b', 'action' => 'build']]);
        $before = [['t' => 'a'], ['t' => 'b', 'action' => 'build']];
        $card = $this->card($project, $template, $before);
        $run = new Run($card, 'build', [], 1);
        $order = new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-18 10:00'));

        $order->follow($card->restep([['t' => 'b', 'action' => 'build', 'done' => true], ['t' => 'a']], [1, 0], new \DateTimeImmutable()), $before);
        $this->assertSame(['a', 'b'], array_column($card->getChecklist(), 't'));
        $this->assertSame(['a', 'b'], array_column($template->getSteps(), 'title'));
        $this->assertSame(1, $run->getStep());
        $this->assertTrue($card->getChecklist()[1]['done'] ?? false);

        $order->follow($card->setChecklist([['t' => 'b', 'action' => 'build'], ['t' => 'a'], ['t' => 'c']]), [['t' => 'a'], ['t' => 'b', 'action' => 'build']]);
        $this->assertSame(['b', 'a', 'c'], array_column($card->getChecklist(), 't'));
    }

    // A template saved lays its steps on its open cards: new order, a step added filled from the card's values, a removed one dropped unless ticked, the card's own steps kept after, runs following
    public function testFromTemplate(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'a'], ['title' => 'b', 'action' => 'build'], ['title' => 'gone'], ['title' => 'kept']];
        $template = new PlanTemplate('catalog', [['title' => 'b', 'action' => 'build'], ['title' => 'new', 'action' => 'copy', 'params' => ['item' => '{item}']], ['title' => 'a']]);
        $card = $this->card($project, $template, [['t' => 'a', 'done' => true], ['t' => 'b', 'action' => 'build', 'params' => ['item' => 'item-1']], ['t' => 'gone'], ['t' => 'kept', 'done' => true], ['t' => 'mine']]);
        $run = $this->saveTemplate($card, $template, $previous);

        $this->assertSame(['b', 'new', 'a', 'kept', 'mine'], array_column($card->getChecklist(), 't'));
        $this->assertSame(['item' => 'item-1'], $card->getChecklist()[1]['params'] ?? null);
        $this->assertTrue($card->getChecklist()[2]['done'] ?? false);
        $this->assertSame(0, $run->getStep());
    }

    // A template saved refreshes what each step of its open cards waits for, a step no longer naming any waiting for the previous one again
    public function testFromTemplateLaysAfter(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'a'], ['title' => 'b', 'action' => 'build', 'after' => []]];
        $template = new PlanTemplate('catalog', [['title' => 'a'], ['title' => 'b', 'action' => 'build'], ['title' => 'c', 'after' => [1]]]);
        $card = $this->card($project, $template, [['t' => 'a', 'done' => true], ['t' => 'b', 'action' => 'build', 'after' => []]]);
        $this->saveTemplate($card, $template, $previous);

        $this->assertArrayNotHasKey('after', $card->getChecklist()[1]);
        $this->assertSame(['text:a'], $card->getChecklist()[2]['after'] ?? null);
    }

    // Steps of the same text are each matched once: the template's new order keeps each one's tick
    public function testFromTemplateWithStepsOfTheSameText(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'review'], ['title' => 'build', 'action' => 'build'], ['title' => 'review']];
        $template = new PlanTemplate('catalog', [$previous[0], $previous[2], $previous[1]]);
        $card = $this->card($project, $template, [['t' => 'review', 'done' => true], ['t' => 'build', 'action' => 'build'], ['t' => 'review']]);
        $run = $this->saveTemplate($card, $template, $previous);

        $this->assertSame([['t' => 'review', 'done' => true], ['t' => 'review'], ['t' => 'build', 'action' => 'build']], $card->getChecklist());
        $this->assertSame(2, $run->getStep());
    }

    // A card outside any template only reorders itself
    public function testReorderWithoutTemplate(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a'], ['t' => 'b']]);

        new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-18 10:00'))->reorder($card, [1, 0]);

        $this->assertSame(['b', 'a'], array_column($card->getChecklist(), 't'));
    }

    // The template saved with the card as its only open one, a run of the card's second step returned to follow
    /** @param list<array{title: string, action?: string, group?: string}> $previous */
    private function saveTemplate(Card $card, PlanTemplate $template, array $previous): Run
    {
        $run = new Run($card, 'build', [], 1);
        $repository = $this->createStub(CardRepository::class);
        $repository->method('findOpenOfTemplate')->willReturn([$card]);
        new StepOrder($repository, new MockClock('2026-11-18 10:00'))->fromTemplate($template, $previous);

        return $run;
    }

    // A card of a plan built from the template, with its steps
    /** @param list<array{t: string, action?: string, done?: bool}> $steps */
    private function card(Project $project, PlanTemplate $template, array $steps): Card
    {
        return new Card($project, 'item-1')->setChecklist($steps)->placeIn(new Plan($template, $project, 'item-1'), 0);
    }

    // A template saved puts each shared step of its open cards in the group it now names, out of any when it names none
    public function testFromTemplateLaysGroups(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'a'], ['title' => 'b', 'group' => 'Old']];
        $template = new PlanTemplate('catalog', [['title' => 'a', 'group' => 'Book'], ['title' => 'b'], ['title' => 'c', 'group' => 'Film']]);
        $card = $this->card($project, $template, [['t' => 'a', 'done' => true], ['t' => 'b', 'g' => 'Old']]);
        $this->saveTemplate($card, $template, $previous);

        $this->assertSame([['t' => 'a', 'done' => true, 'g' => 'Book'], ['t' => 'b'], ['t' => 'c', 'g' => 'Film']], array_map(static fn (array $step): array => array_diff_key($step, ['after' => 1, 'doneAt' => 1]), $card->getChecklist()));
    }
}
