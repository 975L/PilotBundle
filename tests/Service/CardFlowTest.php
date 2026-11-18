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
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Service\CardFlow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class CardFlowTest extends TestCase
{
    private CardFlow $flow;
    private Card $card;

    protected function setUp(): void
    {
        $this->flow = new CardFlow(new MockClock('2026-11-17 10:00'));
        $this->card = new Card(new Project('catalog', 'Catalog'), 'item-1');
    }

    // Handing a card to a machine makes it a runner card with its action and a queued run
    public function testEntrustQueuesARun(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', ['text' => 'catalog']);

        $this->assertTrue($this->card->isRunner());
        $this->assertSame('hello', $this->card->getAction());
        $this->assertSame(['text' => 'catalog'], $this->card->getParams());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
        $this->assertSame(RunStatus::Queued, $run->getStatus());
        $this->assertSame($run, $this->card->lastRun());
    }

    // A card already running cannot be handed over a second time
    public function testEntrustRefusedWhileRunning(): void
    {
        $this->flow->entrust($this->card, 'hello', []);

        $this->expectException(FlowException::class);
        $this->flow->entrust($this->card, 'hello', []);
    }

    // A run still waiting is withdrawn and the card is to do again; one a machine holds is stopped, the machine no longer holding it; a finished one stays
    public function testCancelWithdrawsOrStops(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->flow->cancel($this->card);

        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
        $this->assertSame(RunStatus::Cancelled, $run->getStatus());

        $machine = new Machine('catalog', str_repeat('0', 64));
        $held = $this->flow->entrust($this->card, 'hello', [])->claim($machine, new \DateTimeImmutable('2026-11-17 10:00'));
        $this->flow->cancel($this->card);
        $this->assertSame(RunStatus::Cancelled, $held->getStatus());
        $this->assertFalse($held->isHeldBy($machine));
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());

        $this->expectException(FlowException::class);
        $this->flow->cancel($this->card);
    }

    // Ticking a step whose next one names an action queues it; unticking or a plain next step does nothing
    public function testTickLaunchesTheNextStepsAction(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'hello', 'params' => ['text' => 'catalog']], ['t' => 'third']]);

        $run = $this->flow->tick($this->card, 0);
        $this->assertNotNull($run);
        $this->assertSame('hello', $run->getAction());
        $this->assertSame(['text' => 'catalog'], $run->getParams());
        $this->assertSame(1, $run->getStep());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());

        $this->assertNull($this->flow->tick($this->card, 1));
        $this->assertNull($this->flow->tick($this->card, 0));
    }

    // A finished run puts the card to validate, a failed one marks it failed
    public function testRunFinishedSetsReviewOrFailed(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
        $this->assertSame(CardStatus::Review, $this->card->getStatus());

        $this->card->setStatus(CardStatus::Failed);
        $again = $this->flow->launch($this->card);
        $this->finish($again, RunStatus::Failed);
        $this->flow->runFinished($again);
        $this->assertSame(CardStatus::Failed, $this->card->getStatus());
    }

    // A card closed by hand while its run goes on stays closed when the run ends
    public function testRunFinishedLeavesAClosedCard(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->card->markDone(new \DateTimeImmutable('2026-11-17'));
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);

        $this->assertSame(CardStatus::Done, $this->card->getStatus());
    }

    // Validating closes the card on the day; sending back needs a comment and relaunches with it
    public function testValidateAndSendBack(): void
    {
        $this->review();
        $second = $this->flow->sendBack($this->card, ' Shorter ');
        $this->assertSame('Shorter', $this->card->getComment());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
        $this->assertCount(2, $this->card->getRuns());

        $this->finish($second, RunStatus::Done);
        $this->flow->runFinished($second);
        $this->flow->validate($this->card);
        $this->assertSame(CardStatus::Done, $this->card->getStatus());
        $this->assertNull($this->card->getComment());
        $this->assertSame('2026-11-17', $this->card->getDoneAt()?->format('Y-m-d'));
    }

    // A step's run done ticks its step with no validation, the card to do again once nothing else waits
    public function testStepRunDoneTicksItsStep(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'hello'], ['t' => 'third']]);
        $run = $this->flow->tick($this->card, 0);
        $this->assertNotNull($run);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);

        $this->assertTrue($this->card->getChecklist()[1]['done'] ?? false);
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
    }

    // Steps launched one after the other queue behind each other and run in turn, each done one ticking its step
    public function testStepRunsChain(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second', 'action' => 'copy', 'params' => ['text' => 'item-1'], 'after' => []]]);
        $first = $this->flow->runStep($this->card, 0);
        $second = $this->flow->runStep($this->card, 1);
        $this->assertSame([$first, $second], $this->card->activeRuns());
        $this->assertSame(['text' => 'item-1'], $second->getParams());

        $this->finish($first, RunStatus::Done);
        $this->flow->runFinished($first);
        $this->assertTrue($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame(CardStatus::Running, $this->card->getStatus());

        $this->finish($second, RunStatus::Done);
        $this->flow->runFinished($second);
        $this->assertTrue($this->card->getChecklist()[1]['done'] ?? false);
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
    }

    // A failed step's run stops the chain: what was still queued is withdrawn and the card failed
    public function testStepRunFailureStopsTheChain(): void
    {
        [$first, $second] = $this->queueTwoSteps();
        $this->finish($first, RunStatus::Failed);
        $this->flow->runFinished($first);

        $this->assertSame(RunStatus::Cancelled, $second->getStatus());
        $this->assertSame(CardStatus::Failed, $this->card->getStatus());
        $this->assertFalse($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame($first, $this->card->stepRun(0));
    }

    // One queued step among others is withdrawn alone, the card running on
    public function testCancelOneQueuedStep(): void
    {
        [$first, $second] = $this->queueTwoSteps();
        new \ReflectionProperty(Run::class, 'id')->setValue($second, 2);

        $this->assertSame($second, $this->flow->cancel($this->card, 2));
        $this->assertSame(RunStatus::Queued, $first->getStatus());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
    }

    // A step's run done is sent back with a comment: the step unticked and queued again with it
    public function testSendBackAStepsRun(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello', 'params' => ['story' => '025']]]);
        $run = $this->flow->runStep($this->card, 0);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);

        $again = $this->flow->sendBack($this->card, 'Image 3');
        $this->assertFalse($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame(['comment' => 'Image 3', 'story' => '025'], $again->getParams());
        $this->assertSame(0, $again->getStep());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
    }

    // A step naming an action is run by hand, never one done, one without action, or one already under way
    public function testRunStepHandsTheCardToTheStepsAction(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello', 'params' => ['text' => 'catalog'], 'done' => true], ['t' => 'second'], ['t' => 'third', 'action' => 'copy', 'after' => ['action:hello']]]);
        foreach ([0, 1, 9] as $index) {
            try {
                $this->flow->runStep($this->card, $index);
                $this->fail('Step run: ' . $index);
            } catch (FlowException) {
                $this->assertSame(CardStatus::Ready, $this->card->getStatus());
            }
        }

        $run = $this->flow->runStep($this->card, 2);
        $this->assertSame('copy', $run->getAction());
        $this->assertSame(RunStatus::Queued, $run->getStatus());
        $this->expectException(FlowException::class);
        $this->flow->runStep($this->card, 2);
    }

    // An empty comment or one read as an option is refused
    public function testSendBackRefusesEmptyOrDashComment(): void
    {
        $this->review();
        foreach (['  ', '-rf'] as $comment) {
            try {
                $this->flow->sendBack($this->card, $comment);
                $this->fail('Comment accepted: ' . $comment);
            } catch (FlowException $exception) {
                $this->assertStringStartsWith('flow.', $exception->getMessage());
            }
        }
    }

    // Nothing to validate on a card that is not waiting for it
    public function testValidateRefusedOutsideReview(): void
    {
        $this->expectException(FlowException::class);
        $this->flow->validate($this->card);
    }

    // A step's run done ticks its step like a hand would: the next machine step is queued at once and handed back
    public function testStepRunDoneQueuesTheNextStep(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second', 'action' => 'copy', 'params' => ['text' => 'item-1']]]);
        $first = $this->flow->runStep($this->card, 0);
        $this->finish($first, RunStatus::Done);

        $next = $this->flow->runFinished($first);
        $this->assertNotNull($next);
        $this->assertSame(1, $next->getStep());
        $this->assertSame(['text' => 'item-1'], $next->getParams());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());

        $this->finish($next, RunStatus::Done);
        $this->assertNull($this->flow->runFinished($next));
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
    }

    // The last run done on a step that left the checklist is sent back like the card's own: refused outside review
    public function testSendBackARunWhoseStepLeft(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second']]);
        $run = $this->flow->runStep($this->card, 0);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
        $this->card->renameStep(0, '', new \DateTimeImmutable('2026-11-17 11:00'));

        $this->expectException(FlowException::class);
        $this->flow->sendBack($this->card, 'Again');
    }

    // A step whose previous step is still to tick cannot be launched, and is queued as soon as that step is ticked
    public function testRunStepWaitsForThePreviousStep(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'copy']]);
        try {
            $this->flow->runStep($this->card, 1);
            $this->fail('A step waiting for the previous one was launched');
        } catch (FlowException) {
            $this->assertSame([], $this->card->activeRuns());
        }

        $run = $this->flow->tick($this->card, 0);
        $this->assertSame('copy', $run?->getAction());
        $this->assertSame(1, $run->getStep());
    }

    // Ticking a step does not queue the next one while that one still waits for another of its steps
    public function testTickLeavesTheNextStepWaitingForItsOtherSteps(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'copy', 'after' => ['text:first', 'text:third']], ['t' => 'third']]);

        $this->assertNull($this->flow->tick($this->card, 0));
        $this->assertSame([], $this->card->activeRuns());
        $this->assertSame(['third'], $this->card->stepAwaits(1));
    }

    // A card with a finished run waiting for validation
    private function review(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
    }

    // Two machine steps queued on the card, one after the other, the second needing nothing
    /** @return array{Run, Run} */
    private function queueTwoSteps(): array
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second', 'action' => 'copy', 'after' => []]]);

        return [$this->flow->runStep($this->card, 0), $this->flow->runStep($this->card, 1)];
    }

    // A run claimed by a machine and finished with a status
    private function finish(Run $run, RunStatus $status): void
    {
        $now = new \DateTimeImmutable('2026-11-17 10:00');
        $run->claim(new Machine('machine-1', str_repeat('a', 64)), $now)->finish($status, null, [], null, $now);
    }
}
