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
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CreditRefresher;
use c975L\PilotBundle\Service\RunQueue;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class RunQueueTest extends TestCase
{
    private MockClock $clock;
    private Machine $machine;
    private Run $run;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 10:00');
        $this->machine = new Machine('machine-1', '');
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $this->run = new CardFlow($this->clock)->entrust($card, 'hello', []);
    }

    // The run a machine took is handed back, the call recording what the machine declares
    public function testClaimGivesTheRunTaken(): void
    {
        $runs = $this->createStub(RunRepository::class);
        $runs->method('findExpired')->willReturn([]);
        $runs->method('queuedIds')->willReturn([7]);
        $runs->method('take')->willReturn(true);
        $runs->method('find')->willReturn($this->run);

        $this->assertSame($this->run, $this->queue($runs)->claim($this->machine, ['hello' => ['label' => 'Hello', 'params' => []]]));
        $this->assertSame(['hello'], array_keys($this->machine->getCapabilities()));
    }

    // The actions the runner can start now narrow the queue to those it declared among them
    public function testClaimKeepsToTheActionsStartableNow(): void
    {
        $asked = [];
        $runs = $this->createStub(RunRepository::class);
        $runs->method('findExpired')->willReturn([]);
        $runs->method('queuedIds')->willReturnCallback(static function (array $actions) use (&$asked): array {
            $asked[] = $actions;

            return [];
        });

        $this->assertNull($this->queue($runs)->claim($this->machine, ['hello' => ['label' => 'Hello', 'params' => []], 'film' => ['label' => 'Film', 'params' => []]], ['hello', 'item-9']));
        $this->assertSame([['film'], ['hello']], $asked);
    }

    // An exclusive action waiting in the queue stops the shared ones from being given, so that the machine empties for it
    public function testClaimGivesNothingSharedWhileAnExclusiveWaits(): void
    {
        $runs = $this->createMock(RunRepository::class);
        $runs->method('findExpired')->willReturn([]);
        $runs->expects($this->once())->method('queuedIds')->with(['film'], [], 1)->willReturn([7]);
        $runs->expects($this->never())->method('take');

        $this->assertNull($this->queue($runs)->claim($this->machine, ['hello' => ['label' => 'Hello', 'params' => []], 'film' => ['label' => 'Film', 'params' => []]], ['hello']));
    }

    // Another machine faster on every candidate leaves nothing to do
    public function testClaimGivesNothingWhenAnotherMachineWasFaster(): void
    {
        $runs = $this->createStub(RunRepository::class);
        $runs->method('findExpired')->willReturn([]);
        $runs->method('queuedIds')->willReturn([7, 8]);
        $runs->method('take')->willReturn(false);

        $this->assertNull($this->queue($runs)->claim($this->machine, ['hello' => ['label' => 'Hello', 'params' => []]]));
    }

    // Runs whose lease ended are put back in the queue and counted
    public function testReleaseExpiredPutsRunsBack(): void
    {
        $this->run->claim($this->machine, $this->clock->now());
        $runs = $this->createStub(RunRepository::class);
        $runs->method('findExpired')->willReturn([$this->run]);

        $this->assertSame(1, $this->queue($runs)->releaseExpired());
        $this->assertSame(RunStatus::Queued, $this->run->getStatus());
        $this->assertNull($this->run->getMachine());
    }

    // News about a run the machine does not hold are refused
    public function testReportOnARunNotHeldIsRefused(): void
    {
        $this->expectException(FlowException::class);

        $this->queue($this->createStub(RunRepository::class))->report($this->run, $this->machine, 50, null);
    }

    // A run is finished only with a final status
    public function testFinishNeedsAFinalStatus(): void
    {
        $this->run->claim($this->machine, $this->clock->now());

        $this->expectException(FlowException::class);

        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Claimed, null, []);
    }

    // A held run finished as done sends its card to review
    public function testFinishMovesTheCardOn(): void
    {
        $this->run->claim($this->machine, $this->clock->now());

        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', ['out.md']);

        $this->assertSame(RunStatus::Done, $this->run->getStatus());
        $this->assertSame(CardStatus::Review, $this->run->getCard()->getStatus());
    }

    // Of what a step's run spent, only the credit its step's cost names goes to the credits with its end
    public function testFinishSpendsTheStepCreditOnly(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-2')->setChecklist([['t' => 'first', 'action' => 'hello', 'cost' => ['credit' => 'item-1', 'amount' => 100]]]);
        $run = new CardFlow($this->clock)->runStep($card, 0)->claim($this->machine, $this->clock->now());
        $creditRefresher = $this->createMock(CreditRefresher::class);
        $creditRefresher->expects($this->once())->method('spend')->with(['item-1' => 120]);

        $this->queueSpending($creditRefresher)->finish($run, $this->machine, RunStatus::Done, null, [], spent: ['item-1' => 120, 'item-2' => 999999]);
    }

    // A run whose step names no cost, or the card's own action, debits nothing whatever the machine says
    public function testFinishSpendsNothingWithoutAStepCost(): void
    {
        $creditRefresher = $this->createMock(CreditRefresher::class);
        $creditRefresher->expects($this->once())->method('spend')->with([]);
        $this->run->claim($this->machine, $this->clock->now());

        $this->queueSpending($creditRefresher)->finish($this->run, $this->machine, RunStatus::Done, null, [], spent: ['item-1' => 120]);
    }

    // A queue whose credits are the given refresher
    private function queueSpending(CreditRefresher $creditRefresher): RunQueue
    {
        return new RunQueue($this->createStub(EntityManagerInterface::class), $this->createStub(RunRepository::class), $this->createStub(ActionRepository::class), new CardFlow($this->clock), $this->clock, $creditRefresher);
    }

    // A step's run done ticks its step and queues nothing more, the next step left to a hand
    public function testFinishTicksTheStepOnly(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-2')->setChecklist([['t' => 'first', 'action' => 'hello'], ['t' => 'second', 'action' => 'copy']]);
        $run = new CardFlow($this->clock)->runStep($card, 0)->claim($this->machine, $this->clock->now());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        new RunQueue($entityManager, $this->createStub(RunRepository::class), $this->createStub(ActionRepository::class), new CardFlow($this->clock), $this->clock, $this->createStub(CreditRefresher::class))->finish($run, $this->machine, RunStatus::Done, null, []);
        $this->assertTrue($card->getChecklist()[0]['done'] ?? false);
        $this->assertSame([], $card->activeRuns());
    }

    // The paths of the files the action wrote are kept on its run
    public function testFinishKeepsTheFilesWritten(): void
    {
        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], files: ['/catalog/item-1.pdf']);

        $this->assertSame(['/catalog/item-1.pdf'], $this->run->getFiles());
    }

    // The links the action left are kept on its run, for its step
    public function testFinishKeepsTheLinksLeft(): void
    {
        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], links: [['url' => 'https://example.com/post/1', 'label' => 'Post']]);

        $this->assertSame([['url' => 'https://example.com/post/1', 'label' => 'Post']], $this->run->getLinks());
    }

    // A title the action gave renames its card, an empty one leaving it as it was
    public function testFinishRenamesTheCard(): void
    {
        $this->run->claim($this->machine, $this->clock->now());
        $title = $this->run->getCard()->getTitle();
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Failed, 'stop', [], '  ');
        $this->assertSame($title, $this->run->getCard()->getTitle());

        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], ' 011 item-1 ');
        $this->assertSame('011 item-1', $this->run->getCard()->getTitle());
    }

    // An address the action gave becomes its card's result, a line that is no http(s) address leaving the card's as it was
    public function testFinishSetsTheResult(): void
    {
        $card = $this->run->getCard()->setResultUrl('https://example.com/item-1');
        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], null, [], 'javascript:item-2');
        $this->assertSame('https://example.com/item-1', $card->getResultUrl());

        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], null, [], ' https://example.com/item-2 ');
        $this->assertSame('https://example.com/item-2', $card->getResultUrl());
    }

    // The feedback a finished run was handed is settled: the one the machine left open keeps its reason, the other ticked
    public function testFinishSettlesTheFeedback(): void
    {
        $card = $this->run->getCard()->setComment("1. item-2\n2. item-3")->addFeedback('item-2')->addFeedback('item-3');
        $this->run->claim($this->machine, $this->clock->now());
        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', [], null, [2 => 'item-4']);

        $feedback = array_values($card->getFeedback());
        $this->assertTrue($feedback[0]['done'] ?? false);
        $this->assertSame('item-4', $feedback[1]['r'] ?? null);
    }

    private function queue(RunRepository $runs): RunQueue
    {
        return new RunQueue($this->createStub(EntityManagerInterface::class), $runs, $this->createStub(ActionRepository::class), new CardFlow($this->clock), $this->clock, $this->createStub(CreditRefresher::class));
    }
}
