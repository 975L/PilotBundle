<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Security\PilotScope;

// The order of the queue set by dragging: the runs given take, in that order, the places they held among the queued runs the account sees, the others keeping theirs, then the whole queue is ranked from the top
class RunOrder
{
    public function __construct(
        private readonly RunRepository $runRepository,
        private readonly PilotAccess $pilotAccess,
    ) {
    }

    // Reorders the queued runs named by id that the account may run, unknown, held or foreign ones ignored, none given leaving the queue as it is; the queue seen returned in its order
    /**
     * @param list<int> $ids
     *
     * @return list<Run>
     */
    public function reorder(array $ids): array
    {
        $seen = self::queued($this->runRepository->findActive($this->pilotAccess->scope()));
        $movable = [];
        foreach ($seen as $run) {
            if ($this->pilotAccess->can($run->getCard(), PilotAccess::RUN)) {
                $movable[(int) $run->getId()] = $run;
            }
        }

        $moved = array_values(array_filter(array_map(static fn (int $id): ?Run => $movable[$id] ?? null, array_unique($ids))));
        if ([] === $moved) {
            return $seen;
        }

        // Ranked over the whole queue, so that the runs of the spaces the account does not see keep their places too
        $queue = self::rank(self::queued($this->runRepository->findActive(new PilotScope(true))), $moved);

        return array_values(array_filter($queue, static fn (Run $run): bool => \in_array($run, $seen, true)));
    }

    // The runs waiting for a machine among those under way
    /**
     * @param list<Run> $active
     *
     * @return list<Run>
     */
    private static function queued(array $active): array
    {
        return array_values(array_filter($active, static fn (Run $run): bool => RunStatus::Queued === $run->getStatus()));
    }

    // The runs moved fill the places they held, in their new order, those of one card staying in their order of arrival as the machines take them, and each run of the queue gets its rank, the first the highest
    /**
     * @param list<Run> $queue
     * @param list<Run> $moved
     *
     * @return list<Run>
     */
    private static function rank(array $queue, array $moved): array
    {
        $next = 0;
        $places = [];
        foreach ($queue as $place => $run) {
            if (\in_array($run, $moved, true)) {
                $queue[$place] = $moved[$next++];
            }
            $places[spl_object_id($queue[$place]->getCard())][] = $place;
        }
        foreach ($places as $taken) {
            $runs = array_map(static fn (int $place): Run => $queue[$place], $taken);
            usort($runs, static fn (Run $one, Run $other): int => $one->getId() <=> $other->getId());
            $queue = array_replace($queue, array_combine($taken, $runs));
        }
        foreach ($queue as $place => $run) {
            $run->setPriority(\count($queue) - $place);
        }

        return $queue;
    }
}
