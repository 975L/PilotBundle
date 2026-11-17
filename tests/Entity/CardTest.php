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
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\Executor;
use c975L\PilotBundle\Enum\RunStatus;
use PHPUnit\Framework\TestCase;

class CardTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('catalog', 'Catalog');
    }

    // A card without a date sits in the backlog until it is done
    public function testBacklogIsTheUndatedOpenCards(): void
    {
        $card = new Card($this->project, 'Item 1');
        $this->assertTrue($card->isInBacklog());

        $card->setDue(new \DateTimeImmutable('2026-12-01'));
        $this->assertFalse($card->isInBacklog());

        $card->setDue(null)->markDone(new \DateTimeImmutable('2026-11-17'));
        $this->assertFalse($card->isInBacklog());
        $this->assertSame(CardStatus::Done, $card->getStatus());

        $card->markDone(null);
        $this->assertSame(CardStatus::Ready, $card->getStatus());
        $this->assertNull($card->getDoneAt());
    }

    // In a plan, the next card and the runner card a validation sends back to are found by position
    public function testPlanNavigation(): void
    {
        $plan = new Plan(new PlanTemplate('Test', []), $this->project, 'item-1');
        $cards = [];
        foreach ([Executor::Human, Executor::Runner, Executor::Human] as $index => $executor) {
            $cards[] = new Card($this->project, 'Item ' . $index)->assign($executor, 2 === $index ? CardKind::Validate : CardKind::Task)->placeIn($plan, $index + 1);
        }

        $this->assertSame($cards[1], $plan->cardAfter($cards[0]));
        $this->assertNull($plan->cardAfter($cards[2]));
        $this->assertSame($cards[1], $plan->runnerCardBefore($cards[2]));
        $this->assertTrue($cards[2]->isValidation());
        $this->assertFalse($plan->isDone());
    }

    // A run held by a machine is released when its lease is lost, then finished with its outputs
    public function testRunLifecycle(): void
    {
        $card = new Card($this->project, 'Item 1');
        $machine = new Machine('machine-1', str_repeat('a', 64));
        $run = new Run($card);
        $now = new \DateTimeImmutable('2026-11-17 10:00:00');

        $run->claim($machine, $now)->report(40, 'step 4', $now);
        $this->assertSame(RunStatus::Claimed, $run->getStatus());
        $this->assertSame('2026-11-17 10:01:00', $run->getLeaseUntil()?->format('Y-m-d H:i:s'));
        $this->assertSame($run, $card->lastRun());

        $run->release();
        $this->assertSame(RunStatus::Queued, $run->getStatus());
        $this->assertNull($run->getMachine());

        $run->finish(RunStatus::Done, 'ok', ['/tmp/item-1.txt'], 'log', $now);
        $this->assertSame(100, $run->getProgress());
        $this->assertTrue($run->getStatus()->isFinished());
    }

    // A step ticked keeps its day, unticked loses it, and an index out of the list changes nothing
    public function testToggleStep(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'first'], ['t' => 'second']]);
        $day = new \DateTimeImmutable('2026-11-17');

        $card->toggleStep(1, $day)->toggleStep(5, $day);
        $this->assertSame([['t' => 'first'], ['t' => 'second', 'done' => true, 'doneAt' => '2026-11-17']], $card->getChecklist());

        $card->toggleStep(1, $day);
        $this->assertSame([['t' => 'first'], ['t' => 'second']], $card->getChecklist());
    }

    // Who an answer is awaited from is trimmed, and an empty value ends the wait
    public function testWaitingOn(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setWaitingOn('  Catalog  ');
        $this->assertSame('Catalog', $card->getWaitingOn());
        $this->assertNull($card->setWaitingOn(' ')->getWaitingOn());
    }
}
