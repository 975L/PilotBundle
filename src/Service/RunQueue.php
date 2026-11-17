<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\RunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

use function Symfony\Component\Translation\t;

// Queue of runs seen from the machines: claim under lease, progress, finish, lost leases put back
class RunQueue
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RunRepository $runRepository,
        private readonly ActionRepository $actionRepository,
        private readonly CardFlow $cardFlow,
        private readonly ClockInterface $clock,
    ) {
    }

    // Puts back in the queue every run whose machine stopped answering
    public function releaseExpired(): int
    {
        $expired = $this->runRepository->findExpired($this->clock->now());
        foreach ($expired as $run) {
            $run->release();
        }
        if ([] !== $expired) {
            $this->entityManager->flush();
        }

        return \count($expired);
    }

    // Gives the oldest queued run among the declared actions to the machine, null when there is nothing to do
    /** @param array<string, array{label: string, params: list<string>, card?: bool}> $capabilities */
    public function claim(Machine $machine, array $capabilities): ?Run
    {
        $this->releaseExpired();
        $now = $this->clock->now();
        $machine->seen($capabilities, $now);
        $this->actionRepository->declare($capabilities);
        $this->entityManager->flush();
        foreach ($this->runRepository->queuedIds(array_map(strval(...), array_keys($capabilities))) as $id) {
            if ($this->runRepository->take($id, $machine, $now)) {
                $run = $this->runRepository->find($id);
                if (null !== $run) {
                    $this->entityManager->refresh($run);

                    return $run;
                }
            }
        }

        return null;
    }

    // Records the progress of a run the machine holds and renews its lease
    public function report(Run $run, Machine $machine, ?int $progress, ?string $message): void
    {
        $this->expectHeld($run, $machine);
        $now = $this->clock->now();
        $run->report($progress, $message, $now);
        $machine->seen($machine->getCapabilities(), $now);
        $this->entityManager->flush();
    }

    // Closes a run the machine holds and moves its card on
    /** @param list<string> $outputs */
    public function finish(Run $run, Machine $machine, RunStatus $status, ?string $message, array $outputs, ?string $log): void
    {
        $this->expectHeld($run, $machine);
        if (!$status->isFinished()) {
            throw new FlowException(t('flow.unknown_status', [], 'pilot'));
        }
        $now = $this->clock->now();
        $run->finish($status, $message, $outputs, $log, $now);
        $machine->seen($machine->getCapabilities(), $now);
        $this->cardFlow->runFinished($run);
        $this->entityManager->flush();
    }

    // Refuses news about a run the machine no longer holds, after a lost lease for instance
    public function expectHeld(Run $run, Machine $machine): void
    {
        if (!$run->isHeldBy($machine)) {
            throw new FlowException(t('flow.not_held', [], 'pilot'));
        }
    }
}
