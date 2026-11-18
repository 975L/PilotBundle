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
    // A card of a checklist template carries its new order to the template and to the template's other open cards, each keeping its ticks
    public function testReorderCarriesTheOrderToTheTemplate(): void
    {
        $project = new Project('catalog', 'Catalog');
        $template = new PlanTemplate('catalog', [['title' => 'build', 'executor' => 'runner', 'action' => 'build'], ['title' => 'check', 'executor' => 'human'], ['title' => 'publish', 'executor' => 'runner', 'action' => 'publish']])->setChecklist(true);
        $card = $this->card($project, $template, [['t' => 'build', 'action' => 'build'], ['t' => 'check'], ['t' => 'publish', 'action' => 'publish']]);
        $other = $this->card($project, $template, [['t' => 'build', 'action' => 'build', 'done' => true], ['t' => 'extra'], ['t' => 'check'], ['t' => 'publish', 'action' => 'publish']]);
        $repository = $this->createStub(CardRepository::class);
        $repository->method('findOpenOfTemplate')->willReturn([$card, $other]);

        new StepOrder($repository, new MockClock('2026-11-18 10:00'))->reorder($card, [2, 0, 1]);

        $this->assertSame(['publish', 'build', 'check'], array_column($card->getChecklist(), 't'));
        $this->assertSame(['publish', 'build', 'check'], array_column($template->getSteps(), 'title'));
        $this->assertSame(['publish', 'build', 'check', 'extra'], array_column($other->getChecklist(), 't'));
        $this->assertTrue($other->getChecklist()[1]['done'] ?? false);
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

    // A rewrite that only moved the steps is carried to the template, the runs following their step; a rewrite adding a step stays the card's own
    public function testFollowARewrite(): void
    {
        $project = new Project('catalog', 'Catalog');
        $template = new PlanTemplate('catalog', [['title' => 'a', 'executor' => 'human'], ['title' => 'b', 'executor' => 'runner', 'action' => 'build']])->setChecklist(true);
        $before = [['t' => 'a'], ['t' => 'b', 'action' => 'build']];
        $card = $this->card($project, $template, $before);
        $run = new Run($card, 'build', [], 1);
        $order = new StepOrder($this->createStub(CardRepository::class), new MockClock('2026-11-18 10:00'));

        $order->follow($card->restep([['t' => 'b', 'action' => 'build', 'done' => true], ['t' => 'a']], [1, 0], new \DateTimeImmutable()), $before);
        $this->assertSame(['b', 'a'], array_column($template->getSteps(), 'title'));
        $this->assertSame(0, $run->getStep());
        $this->assertTrue($card->getChecklist()[0]['done'] ?? false);

        $order->follow($card->setChecklist([['t' => 'b', 'action' => 'build'], ['t' => 'a'], ['t' => 'c']]), [['t' => 'b', 'action' => 'build'], ['t' => 'a']]);
        $this->assertSame(['b', 'a'], array_column($template->getSteps(), 'title'));
    }

    // A template saved lays its steps on its open cards: new order, a step added filled from the card's values, a removed one dropped unless ticked, the card's own steps kept after, runs following
    public function testFromTemplate(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'a', 'executor' => 'human'], ['title' => 'b', 'executor' => 'runner', 'action' => 'build'], ['title' => 'gone', 'executor' => 'human'], ['title' => 'kept', 'executor' => 'human']];
        $template = new PlanTemplate('catalog', [['title' => 'b', 'executor' => 'runner', 'action' => 'build'], ['title' => 'new', 'executor' => 'runner', 'action' => 'copy', 'params' => ['item' => '{item}']], ['title' => 'a', 'executor' => 'human']])->setChecklist(true);
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
        $previous = [['title' => 'a', 'executor' => 'human'], ['title' => 'b', 'executor' => 'runner', 'action' => 'build', 'after' => []]];
        $template = new PlanTemplate('catalog', [['title' => 'a', 'executor' => 'human'], ['title' => 'b', 'executor' => 'runner', 'action' => 'build'], ['title' => 'c', 'executor' => 'human', 'after' => [1]]])->setChecklist(true);
        $card = $this->card($project, $template, [['t' => 'a', 'done' => true], ['t' => 'b', 'action' => 'build', 'after' => []]]);
        $this->saveTemplate($card, $template, $previous);

        $this->assertArrayNotHasKey('after', $card->getChecklist()[1]);
        $this->assertSame(['text:a'], $card->getChecklist()[2]['after'] ?? null);
    }

    // Steps of the same text are each matched once: the template's new order keeps each one's tick
    public function testFromTemplateWithStepsOfTheSameText(): void
    {
        $project = new Project('catalog', 'Catalog');
        $previous = [['title' => 'review', 'executor' => 'human'], ['title' => 'build', 'executor' => 'runner', 'action' => 'build'], ['title' => 'review', 'executor' => 'human']];
        $template = new PlanTemplate('catalog', [$previous[0], $previous[2], $previous[1]])->setChecklist(true);
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
    /** @param list<array{title: string, executor: string, action?: string}> $previous */
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
}
