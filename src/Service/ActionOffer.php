<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use Psr\Clock\ClockInterface;

// The machine actions a project offers right now: those a machine online runs, each with the name of that machine
class ActionOffer
{
    public function __construct(
        private readonly MachineRepository $machineRepository,
        private readonly ActionRepository $actionRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // The actions offered on the project that a machine online runs, the first machine declaring one running it
    /** @return list<array{key: string, label: string, params: list<string>, machine: string, card: bool, help: ?string}> */
    public function on(Project $project): array
    {
        $runners = [];
        foreach ($this->machineRepository->findOnline($this->clock->now()) as $machine) {
            foreach (array_keys($machine->getCapabilities()) as $key) {
                $runners[$key] ??= $machine->getName();
            }
        }
        $actions = [];
        foreach ($this->actionRepository->findOffered($project, array_map(strval(...), array_keys($runners))) as $action) {
            $actions[] = ['key' => $action->getKey(), 'label' => $action->getLabel(), 'params' => $action->getParams(), 'machine' => $runners[$action->getKey()], 'card' => $action->isCardOnly(), 'help' => $action->getHelp()];
        }

        return $actions;
    }

    // The label of every action a machine declared, by key, whether or not a machine is online now
    /** @return array<string, string> */
    public function labels(): array
    {
        $labels = [];
        foreach ($this->actionRepository->findAll() as $action) {
            $labels[$action->getKey()] = $action->getLabel();
        }

        return $labels;
    }
}
