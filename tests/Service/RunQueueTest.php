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

        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Claimed, null, [], null);
    }

    // A held run finished as done sends its card to review
    public function testFinishMovesTheCardOn(): void
    {
        $this->run->claim($this->machine, $this->clock->now());

        $this->queue($this->createStub(RunRepository::class))->finish($this->run, $this->machine, RunStatus::Done, 'ok', ['out.md'], null);

        $this->assertSame(RunStatus::Done, $this->run->getStatus());
        $this->assertSame(CardStatus::Review, $this->run->getCard()->getStatus());
    }

    private function queue(RunRepository $runs): RunQueue
    {
        return new RunQueue($this->createStub(EntityManagerInterface::class), $runs, $this->createStub(ActionRepository::class), new CardFlow($this->clock), $this->clock);
    }
}
