<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\Executor;

// Builds a plan from a template: one card per step, its {variables} replaced by the values given, the first card opened
class PlanFactory
{
    private const string VARIABLE = '/\{(\w+)\}/';

    public function __construct(
        private readonly CardFlow $cardFlow,
    ) {
    }

    // Names of the {variables} the steps of a template use, in order of appearance
    /** @return list<string> */
    public function variables(PlanTemplate $template): array
    {
        $names = [];
        foreach ($template->getSteps() as $step) {
            foreach ([$step['title'], ...array_values($step['params'] ?? [])] as $text) {
                preg_match_all(self::VARIABLE, (string) $text, $matches);
                $names = [...$names, ...$matches[1]];
            }
        }

        return array_values(array_unique($names));
    }

    // A plan of the project, its cards waiting their turn but the first one
    /** @param array<string, string> $values */
    public function create(PlanTemplate $template, Project $project, string $title, array $values): Plan
    {
        $plan = new Plan($template, $project, $title);
        foreach ($template->getSteps() as $position => $step) {
            $params = array_map(fn (string $value): string => $this->fill($value, $values), $step['params'] ?? []);
            new Card($project, $this->fill($step['title'], $values))
                ->assign(Executor::tryFrom($step['executor']) ?? Executor::Human, CardKind::Task, $step['action'] ?? null, $params)
                ->setStatus(CardStatus::Todo)
                ->placeIn($plan, $position);
        }
        $this->cardFlow->start($plan);

        return $plan;
    }

    // A text with its {variables} replaced, an unknown one left empty
    /** @param array<string, string> $values */
    private function fill(string $text, array $values): string
    {
        return (string) preg_replace_callback(self::VARIABLE, static fn (array $match): string => $values[$match[1]] ?? '', $text);
    }
}
