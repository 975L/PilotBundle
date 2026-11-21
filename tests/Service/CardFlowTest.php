<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
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

    // Initials come from the name part of an identifier: two words give their first letters, one word its first two letters, nothing gives none
    public function testInitials(): void
    {
        $this->assertSame('IO', CardFlow::initials('item.one@example.com'));
        $this->assertSame('IT', CardFlow::initials('item@example.com'));
        $this->assertSame('ÉT', CardFlow::initials('élan-two'));
        $this->assertNull(CardFlow::initials(null));
        $this->assertNull(CardFlow::initials('@example.com'));
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

    // An action the card's space is not offered is refused, handed to the card or named by a step, the one it is offered queued
    public function testActionNotOfferedRefused(): void
    {
        $offered = new Action('action-1')->addProject($this->card->getProject());
        $actions = $this->createStub(ActionRepository::class);
        $actions->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Action => 'action-1' === $criteria['key'] ? $offered : new Action($criteria['key']));
        $flow = new CardFlow(new MockClock('2026-11-17 10:00'), null, $actions);

        $this->assertSame(RunStatus::Queued, $flow->entrust($this->card, 'action-1', [])->getStatus());

        $other = new Card($this->card->getProject(), 'item-2')->addStep('step-1')->setStepAction(0, 'action-2');
        $refused = 0;
        foreach ([fn (): Run => $flow->entrust(new Card($this->card->getProject(), 'item-3'), 'action-2', []), fn (): Run => $flow->runStep($other, 0)] as $gesture) {
            try {
                $gesture();
            } catch (FlowException) {
                ++$refused;
            }
        }
        $this->assertSame(2, $refused);
        $this->assertNotSame(CardStatus::Running, $other->getStatus());
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

    // Ticking a step never queues the next one, even one naming a machine action: actions only leave by their button
    public function testTickQueuesNothing(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'hello', 'params' => ['text' => 'catalog']], ['t' => 'third']]);

        $this->flow->tick($this->card, 0);
        $this->assertFirstTickedAndIdle();
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

    // Validating closes the card on the day; the open feedback handed back relaunches the card's own action with it as the comment
    public function testValidateAndTreatFeedback(): void
    {
        $this->review();
        $this->card->addFeedback('Shorter')->setStatus(CardStatus::Review);
        $second = $this->flow->treatFeedback($this->card);
        $this->assertSame('1. Shorter', $this->card->getComment());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
        $this->assertCount(2, $this->card->getRuns());

        $this->finish($second, RunStatus::Done);
        $this->flow->feedbackSettled($second, []);
        $this->flow->runFinished($second);
        $this->assertTrue(array_values($this->card->getFeedback())[0]['done'] ?? false);
        $this->flow->validate($this->card);
        $this->assertSame(CardStatus::Done, $this->card->getStatus());
        $this->assertNull($this->card->getComment());
        $this->assertSame('2026-11-17', $this->card->getDoneAt()?->format('Y-m-d'));
    }

    // A step's run done ticks its step with no validation, the card to do again once nothing else waits
    public function testStepRunDoneTicksItsStep(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'hello'], ['t' => 'third']]);
        $run = $this->flow->runStep($this->flow->tick($this->card, 0), 1);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);

        $this->assertTrue($this->card->getChecklist()[1]['done'] ?? false);
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
    }

    // Steps launched one after the other queue behind each other and run in turn, each done one ticking its step
    public function testStepRunsChain(): void
    {
        $this->machineSteps(['after' => []]);
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

    // The open feedback after a step's run: its action queued again on the step, numbered as the comment, the step left ticked
    public function testTreatFeedbackAfterAStepsRun(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello', 'params' => ['story' => '025']]]);
        $run = $this->flow->runStep($this->card, 0);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
        $this->card->addFeedback('Image 3')->addFeedback('Done already')->addFeedback("Text\nof case 2");
        $this->card->toggleStep(2, new \DateTimeImmutable('2026-11-17'));

        $again = $this->flow->treatFeedback($this->card);
        $this->assertTrue($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame(['comment' => "1. Image 3\n2. Text of case 2", 'story' => '025'], $again->getParams());
        $this->assertSame(0, $again->getStep());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
    }

    // A finished run ticks the feedback it was handed, found by its text though it moved, and keeps the one it left open with the reason
    public function testFeedbackSettled(): void
    {
        $run = $this->feedbackAfterAStepsRun('Image 3', 'Case 2');
        $this->card->addStep('inserted');
        $this->finish($run, RunStatus::Done);
        $this->flow->feedbackSettled($run, [2 => 'To decide']);

        $feedback = array_values($this->card->getFeedback());
        $this->assertTrue($feedback[0]['done'] ?? false);
        $this->assertFalse($feedback[1]['done'] ?? false);
        $this->assertSame('To decide', $feedback[1]['r'] ?? null);
    }

    // A failed run ticks nothing of the feedback it was handed
    public function testFeedbackSettledByAFailedRun(): void
    {
        $run = $this->feedbackAfterAStepsRun('Image 3');
        $this->finish($run, RunStatus::Failed);
        $this->flow->feedbackSettled($run, []);

        $this->assertFalse(array_values($this->card->getFeedback())[0]['done'] ?? false);
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

    // No open feedback, or no run to take up again, nothing is handed to the machine
    public function testTreatFeedbackRefusedWithoutFeedbackOrRun(): void
    {
        $this->card->addFeedback('Image 3');
        foreach (['no run', 'no feedback'] as $case) {
            try {
                $this->flow->treatFeedback($this->card);
                $this->fail('Feedback handed: ' . $case);
            } catch (FlowException $exception) {
                $this->assertStringStartsWith('flow.', $exception->getMessage());
            }
            $this->review();
            $this->card->toggleStep(0, new \DateTimeImmutable('2026-11-17'));
        }
    }

    // Nothing to validate on a card that is not waiting for it
    public function testValidateRefusedOutsideReview(): void
    {
        $this->expectException(FlowException::class);
        $this->flow->validate($this->card);
    }

    // A step's run done ticks its step but never queues the next machine step, left to a hand
    public function testStepRunDoneLeavesTheNextStep(): void
    {
        $this->machineSteps();
        $first = $this->flow->runStep($this->card, 0);
        $this->finish($first, RunStatus::Done);
        $this->flow->runFinished($first);

        $this->assertFirstTickedAndIdle();
    }

    // The last run done on a step that left the checklist takes no feedback outside review
    public function testTreatFeedbackOnARunWhoseStepLeft(): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second']]);
        $run = $this->flow->runStep($this->card, 0);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
        $this->card->renameStep(0, '', new \DateTimeImmutable('2026-11-17 11:00'));
        $this->card->addFeedback('Again');

        $this->expectException(FlowException::class);
        $this->flow->treatFeedback($this->card);
    }

    // A step whose previous step is still to tick cannot be launched, and can be once that step is ticked
    public function testRunStepWaitsForThePreviousStep(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'copy']]);
        try {
            $this->flow->runStep($this->card, 1);
            $this->fail('A step waiting for the previous one was launched');
        } catch (FlowException) {
            $this->assertSame([], $this->card->activeRuns());
        }

        $run = $this->flow->runStep($this->flow->tick($this->card, 0), 1);
        $this->assertSame('copy', $run->getAction());
        $this->assertSame(1, $run->getStep());
    }

    // A step still waiting for another of its steps once the one before is ticked
    public function testTickLeavesTheNextStepWaitingForItsOtherSteps(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'copy', 'after' => ['text:first', 'text:third']], ['t' => 'third']]);

        $this->flow->tick($this->card, 0);
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

    // Two steps each naming a machine action, the second's text given, with what else the second carries
    /** @param array<string, mixed> $second */
    private function machineSteps(array $second = []): void
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second', 'action' => 'copy', 'params' => ['text' => 'item-1']] + $second]);
    }

    // The first step ticked, nothing queued and the card back to do
    private function assertFirstTickedAndIdle(): void
    {
        $this->assertTrue($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame([], $this->card->activeRuns());
        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
    }

    // The feedback given handed to the machine after the run of the card's only step
    private function feedbackAfterAStepsRun(string ...$feedback): Run
    {
        $this->card->setChecklist([['t' => 'first', 'action' => 'hello']]);
        $this->finish($this->flow->runStep($this->card, 0), RunStatus::Done);
        $this->flow->runFinished($this->card->lastRun());
        foreach ($feedback as $text) {
            $this->card->addFeedback($text);
        }

        return $this->flow->treatFeedback($this->card);
    }

    // A run claimed by a machine and finished with a status
    private function finish(Run $run, RunStatus $status): void
    {
        $now = new \DateTimeImmutable('2026-11-17 10:00');
        $run->claim(new Machine('machine-1', str_repeat('a', 64)), $now)->finish($status, null, [], $now);
    }
}
