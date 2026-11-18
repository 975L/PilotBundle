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
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Repository\CardRepository;
use Psr\Clock\ClockInterface;

// Keeps a checklist template and its open cards in step: a card's new order goes to the template and its other cards, a template saved lays its steps on its cards, each keeping its ticks and its own steps
class StepOrder
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // Puts the card's steps in the given order of their current indices and carries that order to its template and the template's other open cards
    /** @param list<int> $order */
    public function reorder(Card $card, array $order): Card
    {
        return $this->carry($card->reorderSteps($order));
    }

    // Carries the card's order of steps to its checklist template and the template's other open cards
    private function carry(Card $card): Card
    {
        $template = $card->getPlan()?->getTemplate();
        if (null === $template || !$template->isChecklist()) {
            return $card;
        }
        $keys = self::keys($card->getChecklist());
        $steps = $template->getSteps();
        $template->setSteps(array_map(static fn (int $index): array => $steps[$index], self::order(array_map(self::templateKey(...), $steps), $keys)));
        foreach ($this->cardRepository->findOpenOfTemplate($template) as $other) {
            if ($other !== $card) {
                $other->reorderSteps(self::order(self::keys($other->getChecklist()), $keys));
            }
        }

        return $card;
    }

    // Moves a step one place up, carried like any new order
    public function up(Card $card, int $index): Card
    {
        $order = array_keys($card->getChecklist());
        if ($index < 1 || !isset($order[$index])) {
            return $card;
        }
        [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]];

        return $this->reorder($card, $order);
    }

    // After a rewrite that only moved the steps around (its runs already following their step), the move is carried like a dragged one; any other rewrite stays the card's own
    /** @param list<array{t: string, action?: string}> $before */
    public function follow(Card $card, array $before): Card
    {
        $after = self::keys($card->getChecklist());
        $previous = self::keys($before);
        $sorted = $after;
        sort($sorted);
        $known = $previous;
        sort($known);

        return $after === $previous || $sorted !== $known ? $card : $this->carry($card);
    }

    // A checklist template saved: each open card takes its steps in the template's order, gains the steps added, those it shares keeping their ticks, loses the ones removed unless ticked, its own steps kept after
    /** @param list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>, after?: list<int>}> $previous */
    public function fromTemplate(PlanTemplate $template, array $previous): void
    {
        if (!$template->isChecklist()) {
            return;
        }
        $steps = $template->getSteps();
        $current = array_map(self::templateKey(...), $steps);
        $removed = array_diff(array_map(self::templateKey(...), $previous), $current);
        foreach ($this->cardRepository->findOpenOfTemplate($template) as $card) {
            $this->relay($card, $steps, $current, $removed);
        }
    }

    // One card laid again after its template: shared steps as they were, new ones filled with the values the card's steps already give
    /**
     * @param list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>, after?: list<int>}> $steps
     * @param list<string>                                                                                                                    $current
     * @param array<int, string>                                                                                                              $removed
     */
    private function relay(Card $card, array $steps, array $current, array $removed): void
    {
        $own = $card->getChecklist();
        $keys = self::keys($own);
        $values = array_merge([], ...array_map(static fn (array $step): array => $step['params'] ?? [], $own));
        $free = $keys;
        $laid = [];
        $from = [];
        foreach ($steps as $position => $step) {
            $index = self::take($free, $current[$position]);
            $from[] = $index;
            $laid[] = null === $index ? self::fresh($step, $values) : $own[$index];
        }
        foreach ($free as $index => $key) {
            if (!\in_array($key, $removed, true) || ($own[$index]['done'] ?? false)) {
                $from[] = $index;
                $laid[] = $own[$index];
            }
        }
        $card->restep(self::linkAfter($laid, $steps), $from, $this->clock->now());
    }

    // The template's "after" (numbers of its steps) laid on a card's steps, aligned with the template's first, as the keys of the steps they name; a step without it waits for the step before
    /**
     * @param list<array{t: string, action?: string, after?: list<string>}> $laid
     * @param list<array{after?: list<int>}>                                $steps
     *
     * @return list<array{t: string, action?: string, after?: list<string>}>
     */
    public static function linkAfter(array $laid, array $steps): array
    {
        foreach ($steps as $position => $step) {
            unset($laid[$position]['after']);
            if (isset($step['after'])) {
                $named = array_filter($step['after'], static fn (int $number): bool => $number >= 1 && $number <= \count($steps) && $number - 1 !== $position);
                $laid[$position]['after'] = array_values(array_map(static fn (int $number): string => Card::stepKey($laid[$number - 1]), $named));
            }
        }

        return $laid;
    }

    // Index of the first step of the given key not taken yet, taken from now on, null when none is left
    /** @param array<int, string> $free */
    public static function take(array &$free, string $key): ?int
    {
        $index = array_search($key, $free, true);
        if (false === $index) {
            return null;
        }
        unset($free[$index]);

        return $index;
    }

    // A step the template added, its {variables} filled with the values the card's steps already give
    /**
     * @param array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>} $step
     * @param array<string, string>                                                                                  $values
     *
     * @return array{t: string, action?: string, params?: array<string, string>}
     */
    private static function fresh(array $step, array $values): array
    {
        $fill = static fn (string $text): string => (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $match): string => $values[$match[1]] ?? $match[0], $text);
        $item = ['t' => $fill($step['title'])];
        if (null !== ($step['action'] ?? null) && '' !== $step['action']) {
            $item['action'] = $step['action'];
            $item['params'] = array_map($fill, $step['params'] ?? []);
        }

        return $item;
    }

    // Key of a template's step
    /** @param array{title: string, action?: string} $step */
    private static function templateKey(array $step): string
    {
        return self::key($step['action'] ?? null, $step['title']);
    }

    // Keys of a card's steps
    /**
     * @param list<array{t: string, action?: string}> $steps
     *
     * @return list<string>
     */
    private static function keys(array $steps): array
    {
        return array_map(static fn (array $step): string => self::key($step['action'] ?? null, $step['t']), $steps);
    }

    // A step known by its machine action, or by its text when it names none
    private static function key(?string $action, string $text): string
    {
        return Card::stepKey(['t' => $text, 'action' => $action ?? '']);
    }

    // Indices of the given keys put in the order of the reference ones, those the reference lacks kept after, in their own order
    /**
     * @param list<string> $keys
     * @param list<string> $reference
     *
     * @return list<int>
     */
    private static function order(array $keys, array $reference): array
    {
        $rank = array_flip($reference);
        $indices = array_keys($keys);
        usort($indices, static fn (int $a, int $b): int => [$rank[$keys[$a]] ?? \PHP_INT_MAX, $a] <=> [$rank[$keys[$b]] ?? \PHP_INT_MAX, $b]);

        return $indices;
    }
}
