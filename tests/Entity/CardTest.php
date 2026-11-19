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
use c975L\PilotBundle\Entity\Doc;
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

        // A place given by hand leaves the chain of the plan as it was
        $cards[0]->setRank(2);
        $cards[1]->setRank(0);
        $this->assertSame($cards[1], $plan->cardAfter($cards[0]));
        $this->assertSame(2, $cards[0]->getRank());
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

    // Shared under a 32 hex secret kept from one share to the next, a new one only after sharing stopped
    public function testShare(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $this->assertNull($card->getShareToken());

        $first = $card->share()->getShareToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $first);
        $this->assertSame($first, $card->share()->getShareToken());
        $this->assertNull($card->unshare()->getShareToken());
        $this->assertNotSame($first, $card->share()->getShareToken());
    }

    // The editing link is a secret of its own, kept until taken back, apart from the read-only one
    public function testLend(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->share()->lend();
        $edit = $card->getEditToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $edit);
        $this->assertNotSame($card->getShareToken(), $edit);
        $this->assertSame($edit, $card->lend()->getEditToken());
        $this->assertNull($card->unlend()->getEditToken());
        $this->assertNotNull($card->getShareToken());
    }

    // The copy keeps what the card says and its steps unticked, not what it has lived (done, shared, waiting)
    public function testDuplicate(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')
            ->setNote('note-1')
            ->setDue(new \DateTimeImmutable('2026-11-20'))
            ->setWaitingOn('someone')
            ->setChecklist([['t' => 'step-1', 'done' => true, 'doneAt' => '2026-11-01', 'cost' => ['credit' => 'item-1', 'amount' => 2]]])
            ->share()
            ->markDone(new \DateTimeImmutable('2026-11-18'));

        $copy = $card->duplicate(' (copy)');

        $this->assertSame('item-1 (copy)', $copy->getTitle());
        $this->assertSame($card->getProject(), $copy->getProject());
        $this->assertSame('note-1', $copy->getNote());
        $this->assertSame('2026-11-20', $copy->getDue()?->format('Y-m-d'));
        $this->assertSame([['t' => 'step-1', 'cost' => ['credit' => 'item-1', 'amount' => 2]]], $copy->getChecklist());
        $this->assertNull($copy->getWaitingOn());
        $this->assertNull($copy->getShareToken());
        $this->assertNull($copy->getDoneAt());
        $this->assertNotSame('done', $copy->getStatus()->value);
    }

    // Steps added at the end, renamed keeping what they carry, removed by an empty text; nothing for an empty text or a missing step
    public function testStepsEditedInPlace(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'action' => 'build', 'done' => true]]);

        $card->addStep(' step-2 ')->addStep(' ')->renameStep(0, 'step-1b', new \DateTimeImmutable())->renameStep(5, 'nothing', new \DateTimeImmutable());
        $this->assertSame([['t' => 'step-1b', 'action' => 'build', 'done' => true], ['t' => 'step-2']], $card->getChecklist());

        $this->assertSame([['t' => 'step-2']], $card->renameStep(0, '', new \DateTimeImmutable())->getChecklist());
    }

    // Steps reordered by their indices, the runs following their step; a partial order is ignored
    public function testReorderSteps(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a', 'action' => 'build'], ['t' => 'b'], ['t' => 'c', 'action' => 'copy']]);
        $run = new Run($card, 'copy', [], 2);

        $card->reorderSteps([2, 0, 1]);
        $this->assertSame(['c', 'a', 'b'], array_column($card->getChecklist(), 't'));
        $this->assertSame(0, $run->getStep());
        $this->assertSame($run, $card->stepRun(0));

        $card->reorderSteps([1, 0]);
        $this->assertSame(['c', 'a', 'b'], array_column($card->getChecklist(), 't'));
    }

    // A step's machine action is set, changed with its parameters dropped, or removed by an empty value
    public function testSetStepAction(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'action' => 'build', 'params' => ['item' => 'item-1']], ['t' => 'step-2']]);

        $card->setStepAction(0, 'build')->setStepAction(1, ' copy ')->setStepAction(9, 'copy');
        $this->assertSame([['t' => 'step-1', 'action' => 'build', 'params' => ['item' => 'item-1']], ['t' => 'step-2', 'action' => 'copy']], $card->getChecklist());

        $card->setStepAction(0, 'publish')->setStepAction(1, '');
        $this->assertSame([['t' => 'step-1', 'action' => 'publish'], ['t' => 'step-2']], $card->getChecklist());
    }

    // A title is cut to the length of its column
    public function testTitleCut(): void
    {
        $this->assertSame(500, mb_strlen(new Card(new Project('catalog', 'Catalog'), 'item-1')->setTitle(str_repeat('é', 600))->getTitle()));
    }

    // A step removed by an empty text moves the runs of the steps after it, its own run leaving with it, withdrawn and the card to do again
    public function testRemovedStepMovesTheRuns(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a', 'action' => 'build'], ['t' => 'b'], ['t' => 'c', 'action' => 'copy']])->setStatus(CardStatus::Running);
        $removed = new Run($card, 'build', [], 0);
        $after = new Run($card, 'copy', [], 2)->finish(RunStatus::Done, null, [], null, new \DateTimeImmutable('2026-11-18 09:00'));

        $card->renameStep(0, '', new \DateTimeImmutable('2026-11-18 10:00'));
        $this->assertSame(['b', 'c'], array_column($card->getChecklist(), 't'));
        $this->assertSame([RunStatus::Cancelled, Run::LEFT_STEP], [$removed->getStatus(), $removed->getStep()]);
        $this->assertSame(1, $after->getStep());
        $this->assertSame([1 => $after], $card->stepRuns());
        $this->assertSame(CardStatus::Ready, $card->getStatus());
    }

    // Steps laid again keep the card running while a run stays under way on a step kept
    public function testRestepKeepsTheCardRunning(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a', 'action' => 'build'], ['t' => 'b', 'action' => 'copy']])->setStatus(CardStatus::Running);
        $kept = new Run($card, 'copy', [], 1);
        $gone = new Run($card, 'build', [], 0);

        $card->restep([['t' => 'b', 'action' => 'copy'], ['t' => 'new']], [1, null], new \DateTimeImmutable('2026-11-18 10:00'));
        $this->assertSame([0, RunStatus::Queued], [$kept->getStep(), $kept->getStatus()]);
        $this->assertSame(RunStatus::Cancelled, $gone->getStatus());
        $this->assertSame(CardStatus::Running, $card->getStatus());
    }

    // The latest run of each step in one pass, the card's own last run counting for the steps naming its action
    public function testStepRuns(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'a', 'action' => 'build'], ['t' => 'b', 'action' => 'copy'], ['t' => 'c']]);
        new Run($card, 'build', [], 0);
        $latest = new Run($card, 'build', [], 0);
        $card->assign(Executor::Runner, CardKind::Task, 'copy');
        $own = new Run($card);

        $this->assertSame([0 => $latest, 1 => $own], $card->stepRuns());
        $this->assertSame($own, $card->stepRun(1));
        $this->assertNull($card->stepRun(2));
    }

    // A step waits for the step before unless its "after" names others, by action or by text; the first one waits for nothing
    public function testStepNeeds(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([
            ['t' => 'first', 'action' => 'hello'],
            ['t' => 'second'],
            ['t' => 'third', 'after' => ['action:hello', 'text:missing']],
            ['t' => 'fourth', 'after' => []],
        ]);

        $this->assertSame([], $card->stepNeeds(0));
        $this->assertSame([0], $card->stepNeeds(1));
        $this->assertSame([0], $card->stepNeeds(2));
        $this->assertSame([], $card->stepNeeds(3));
        $this->assertSame([], $card->stepNeeds(9));
    }

    // A step waits while one of the steps it needs is still to tick, and no longer once they are
    public function testStepWaitingUntilItsStepsAreTicked(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'copy']]);

        $this->assertTrue($card->isStepWaiting(1));
        $this->assertSame(['first'], $card->stepAwaits(1));

        $card->toggleStep(0, new \DateTimeImmutable('2026-11-18'));
        $this->assertFalse($card->isStepWaiting(1));
        $this->assertSame([], $card->stepAwaits(1));
    }

    // A step renamed stays the one the others wait for
    public function testRenamedStepStaysAwaited(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'check'], ['t' => 'publish', 'action' => 'copy', 'after' => ['text:check']]]);
        $card->renameStep(0, 'check again', new \DateTimeImmutable('2026-11-18'));

        $this->assertSame(['text:check again'], $card->getChecklist()[1]['after'] ?? null);
        $this->assertSame([0], $card->stepNeeds(1));
    }

    // The template of a card is its plan's, otherwise the checklist one it follows; its copy follows it too
    public function testTemplate(): void
    {
        $checklist = new PlanTemplate('catalog-checklist', [])->setChecklist(true);
        $perStep = new PlanTemplate('catalog-steps', []);
        $card = new Card($this->project, 'item-1');

        $this->assertNull($card->getTemplate());
        $this->assertSame($checklist, $card->follow($checklist)->getTemplate());
        $this->assertSame($checklist, $card->duplicate(' (copy)')->getTemplate());
        $card->placeIn(new Plan($perStep, $this->project, 'item-1'), 1);
        $this->assertSame($perStep, $card->getTemplate());
    }

    // A card is done once marked so
    public function testDone(): void
    {
        $card = new Card($this->project, 'item-1');
        $this->assertFalse($card->isDone());
        $this->assertTrue($card->markDone(new \DateTimeImmutable('2026-11-19'))->isDone());
    }

    // A feedback goes after the steps, an empty one adding nothing; a step added goes before it, the runs following their step
    public function testFeedback(): void
    {
        $card = new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1']]);
        $card->addFeedback(' feedback-1 ')->addFeedback('feedback-2')->addFeedback('  ');
        $run = new Run($card, 'build', [], 1);
        $card->addStep('step-2');

        $this->assertSame(['step-1', 'step-2', 'feedback-1', 'feedback-2'], array_column($card->getChecklist(), 't'));
        $this->assertSame([0, 1], array_keys($card->getSteps()));
        $this->assertSame([2 => ['t' => 'feedback-1', 'feedback' => true], 3 => ['t' => 'feedback-2', 'feedback' => true]], $card->getFeedback());
        $this->assertSame(2, $run->getStep());
        $this->assertTrue($card->isFeedback(2));
    }

    // A feedback waits for nothing, a step after one waits for the step before it; a reorder of the steps keeps the feedback after them
    public function testFeedbackOutOfTheChain(): void
    {
        $card = new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1'], ['t' => 'feedback-1', 'feedback' => true], ['t' => 'step-2']]);

        $this->assertSame([], $card->stepNeeds(1));
        $this->assertSame([0], $card->stepNeeds(2));
        $this->assertSame(['step-2', 'step-1', 'feedback-1'], array_column($card->reorderSteps([2, 0])->getChecklist(), 't'));
    }

    // A done card opens again once a box is to tick: a step unticked, a step or a feedback added, a rewrite bringing one; ticking leaves it done
    public function testReopenedByABoxToTick(): void
    {
        $day = new \DateTimeImmutable('2026-11-19');
        $done = fn (): Card => new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1', 'done' => true]])->markDone($day);

        $this->assertSame(CardStatus::Ready, $done()->toggleStep(0, $day)->getStatus());
        $this->assertNull($done()->toggleStep(0, $day)->getDoneAt());
        $this->assertSame(CardStatus::Ready, $done()->addStep('step-2')->getStatus());
        $this->assertSame(CardStatus::Ready, $done()->addFeedback('feedback-1')->getStatus());
        $this->assertSame(CardStatus::Ready, $done()->restep([['t' => 'step-1', 'done' => true], ['t' => 'step-2']], [0, null], $day)->getStatus());
        $this->assertSame(CardStatus::Done, $done()->restep([['t' => 'step-1', 'done' => true]], [0], $day)->getStatus());
        $this->assertSame(CardStatus::Done, $done()->addStep(' ')->getStatus());
        $ticked = new Card($this->project, 'item-2')->setChecklist([['t' => 'step-1'], ['t' => 'step-2', 'done' => true]])->markDone($day);
        $this->assertSame(CardStatus::Done, $ticked->toggleStep(0, $day)->getStatus());
    }

    // The steps gathered in runs of one group as they follow each other, the feedback left out, each step keeping its index
    public function testStepGroups(): void
    {
        $card = new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1', 'g' => 'Book'], ['t' => 'step-2', 'g' => 'Book'], ['t' => 'step-3'], ['t' => 'step-4', 'g' => 'Film']])->addFeedback('feedback-1');
        $groups = $card->getStepGroups();

        $this->assertSame(['Book', null, 'Film'], array_column($groups, 'group'));
        $this->assertSame([0, 1], array_keys($groups[0]['steps']));
        $this->assertSame([3], array_keys($groups[2]['steps']));
    }

    // Ticked steps kept in their order: a lone one left among the open ones, several in a row folded in place
    public function testStepParts(): void
    {
        $card = new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1', 'done' => true], ['t' => 'step-2'], ['t' => 'step-3', 'done' => true], ['t' => 'step-4', 'done' => true], ['t' => 'step-5'], ['t' => 'step-6', 'done' => true]]);
        $parts = $card->getStepGroups()[0]['parts'];

        $this->assertSame([false, true, false], array_column($parts, 'folded'));
        $this->assertSame([[0, 1], [2, 3], [4, 5]], array_map(static fn (array $part): array => array_keys($part['steps']), $parts));
        $this->assertSame([], new Card($this->project, 'item-2')->getStepGroups());
    }

    // A screenshot set on a feedback only, listed, cleared, and never carried to a copy of the card
    public function testFeedbackImage(): void
    {
        $card = new Card($this->project, 'item-1')->addStep('step-1')->addFeedback('feedback-1');
        $card->setFeedbackImage(0, 'item-1.png')->setFeedbackImage(1, 'item-2.png');
        $this->assertSame(['item-2.png'], $card->getFeedbackImages());
        $this->assertSame([], $card->duplicate(' (copy)')->getFeedbackImages());

        $card->setFeedbackImage(1, null);
        $this->assertSame([], $card->getFeedbackImages());
    }

    // A step planned for a day, cleared by none, an index out of the list changing nothing
    public function testStepDue(): void
    {
        $card = new Card($this->project, 'item-1')->setChecklist([['t' => 'step-1'], ['t' => 'step-2']]);
        $card->setStepDue(1, new \DateTimeImmutable('2027-02-03'))->setStepDue(5, new \DateTimeImmutable('2027-02-03'));
        $this->assertSame([['t' => 'step-1'], ['t' => 'step-2', 'due' => '2027-02-03']], $card->getChecklist());

        $card->setStepDue(1, null);
        $this->assertSame([['t' => 'step-1'], ['t' => 'step-2']], $card->getChecklist());
    }

    // A card goes only into a collection of its own space, leaves it and a file of the space it leaves when moved, a shared file staying
    public function testCollectionAndMove(): void
    {
        $other = new Project('other', 'Other');
        $set = new CardSet($this->project, 'Stories');
        $card = new Card($this->project, 'item-1')->setCardSet(new CardSet($other, 'Elsewhere'));
        $this->assertNull($card->getCardSet());

        $card->setCardSet($set)->setDoc(new Doc('catalog-file', 'File')->setProject($this->project));
        $this->assertSame($set, $card->duplicate(' (copy)')->getCardSet());
        $card->moveTo($other);
        $this->assertNull($card->getCardSet());
        $this->assertNull($card->getDoc());

        $shared = new Doc('shared-file', 'Shared');
        $this->assertSame($shared, $card->setDoc($shared)->moveTo($this->project)->getDoc());
    }
}
