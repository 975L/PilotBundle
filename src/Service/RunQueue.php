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
        private readonly CreditRefresher $creditRefresher,
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

    // Gives the oldest queued run among the declared actions, of the spaces it serves, to the machine, null when there is nothing to do; $only narrows the actions to those the runner can start now, an exclusive one waiting while others run, and none given while an exclusive one waits so that the machine empties for it
    /**
     * @param array<string, array{label: string, params: list<string>, card?: bool, help?: ?string}> $capabilities
     * @param list<string>|null                                                                      $only
     */
    public function claim(Machine $machine, array $capabilities, ?array $only = null): ?Run
    {
        $this->releaseExpired();
        $now = $this->clock->now();
        $machine->seen($capabilities, $now);
        $this->actionRepository->declare($machine, $capabilities);
        $this->entityManager->flush();
        $actions = array_map(strval(...), array_keys($capabilities));
        if (null !== $only) {
            if ([] !== $this->runRepository->queuedIds(array_values(array_diff($actions, $only)), $machine->projectIds(), 1)) {
                return null;
            }
            $actions = array_values(array_intersect($actions, $only));
        }
        foreach ($this->runRepository->queuedIds($actions, $machine->projectIds()) as $id) {
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

    // Closes a run the machine holds, settles the feedback it was handed (by number, the reason the machine left one open) and moves its card on
    /**
     * @param list<string>                            $outputs
     * @param array<int, string>                      $left
     * @param array<string, int>                      $spent
     * @param list<string>                            $files
     * @param list<array{url?: mixed, label?: mixed}> $links
     */
    public function finish(Run $run, Machine $machine, RunStatus $status, ?string $message, array $outputs, ?string $title = null, array $left = [], ?string $result = null, array $spent = [], array $files = [], array $links = []): void
    {
        $this->expectHeld($run, $machine);
        if (!$status->isFinished()) {
            throw new FlowException(t('flow.unknown_status', [], 'pilot'));
        }
        $now = $this->clock->now();
        $run->finish($status, $message, $outputs, $now)->setFiles($files)->setLinks($links);

        // The title the action gave its card, a number it took for instance
        if ('' !== trim((string) $title)) {
            $run->getCard()->setTitle(trim((string) $title));
        }

        // The address of what the action made, a page it published for instance, a line that is no http(s) address leaving the card's as it was
        if (1 === preg_match('~^https?://\S+$~i', trim((string) $result))) {
            $run->getCard()->setResultUrl(trim((string) $result));
        }
        $machine->seen($machine->getCapabilities(), $now);
        $this->cardFlow->feedbackSettled($run, $left);
        $this->cardFlow->runFinished($run);

        // What the run spent on the paid services, as the services themselves told it
        $this->creditRefresher->spend($this->stepSpent($run, $spent));
        $this->entityManager->flush();
    }

    // Of what the machine says the run spent, only the credit its step's cost names, so a machine never debits another credit
    /**
     * @param array<string, int> $spent
     *
     * @return array<string, int>
     */
    private function stepSpent(Run $run, array $spent): array
    {
        $credit = $run->getCard()->getChecklist()[$run->getStep() ?? Run::LEFT_STEP]['cost']['credit'] ?? null;

        return null === $credit ? [] : array_intersect_key($spent, [$credit => true]);
    }

    // Refuses news about a run the machine no longer holds, after a lost lease for instance
    public function expectHeld(Run $run, Machine $machine): void
    {
        if (!$run->isHeldBy($machine)) {
            throw new FlowException(t('flow.not_held', [], 'pilot'));
        }
    }
}
