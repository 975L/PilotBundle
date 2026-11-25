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
use c975L\PilotBundle\Enum\CardStatus;

// Builds a plan from a template: a single card whose checklist is the steps, their {variables} replaced by the values given, opened at once
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

    // A plan of the project: its single card, whose checklist is the template's steps, opened at once
    /** @param array<string, string> $values */
    public function create(PlanTemplate $template, Project $project, string $title, array $values): Plan
    {
        $plan = new Plan($template, $project, $title);
        new Card($project, $title)->setChecklist($this->steps($template, $values))->setStatus(CardStatus::Todo)->placeIn($plan, 0);
        $this->cardFlow->start($plan);

        return $plan;
    }

    // The steps of a template as a card's boxes, their {variables} filled, each naming the machine action it hands the card to and the steps it waits for
    /**
     * @param array<string, string> $values
     *
     * @return list<array{t: string, action?: string, params?: array<string, string>, after?: list<string>}>
     */
    public function steps(PlanTemplate $template, array $values): array
    {
        $steps = [];
        foreach ($template->getSteps() as $step) {
            $item = ['t' => $this->fill($step['title'], $values)];
            if (null !== ($step['action'] ?? null) && '' !== $step['action']) {
                $item['action'] = $step['action'];
                $item['params'] = array_map(fn (string $value): string => $this->fill($value, $values), $step['params'] ?? []);
            }
            $steps[] = StepOrder::grouped($item, $step);
        }

        return StepOrder::linkAfter($steps, $template->getSteps());
    }

    // A text with its {variables} replaced, an unknown one left empty
    /** @param array<string, string> $values */
    private function fill(string $text, array $values): string
    {
        return (string) preg_replace_callback(self::VARIABLE, static fn (array $match): string => $values[$match[1]] ?? '', $text);
    }
}
