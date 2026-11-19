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

// Keeps a checklist template and its open cards in step: the template alone orders the steps of the cards following it, a template saved lays its steps on its cards, each keeping its ticks and its own steps
class StepOrder
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // Puts the card's steps in the given order of their current indices; a card following a checklist template keeps the template's order, changed on the template only
    /** @param list<int> $order */
    public function reorder(Card $card, array $order): Card
    {
        return self::templated($card) ? $card : $card->reorderSteps($order);
    }

    // Moves a step one place up, like any new order; a step and a feedback never swap
    public function up(Card $card, int $index): Card
    {
        $order = array_keys($card->getChecklist());
        if ($index < 1 || !isset($order[$index]) || $card->isFeedback($index) !== $card->isFeedback($index - 1)) {
            return $card;
        }
        [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]];

        return $this->reorder($card, $order);
    }

    // After a rewrite that only moved the steps around (its runs already following their step), a card following a checklist template gets the template's order back; any other rewrite stays the card's own
    /** @param list<array{t: string, action?: string}> $before */
    public function follow(Card $card, array $before): Card
    {
        $after = self::keys($card->getChecklist());
        $previous = self::keys($before);
        $sorted = $after;
        sort($sorted);
        $known = $previous;
        sort($known);

        return $after === $previous || $sorted !== $known || !self::templated($card) ? $card : $card->reorderSteps(self::order($after, $previous));
    }

    // Whether the card follows a checklist template, which alone sets the order of its steps
    private static function templated(Card $card): bool
    {
        return true === $card->getTemplate()?->isChecklist();
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
        $before = array_map(self::templateKey(...), $previous);
        foreach ($this->cardRepository->findOpenOfTemplate($template) as $card) {
            $this->relay($card, $steps, $current, $before);
        }
    }

    // One card laid again after its template: shared steps as they were, in their template step's group (a title holding {variables} known by the text that fills it), new ones filled with the values the card's steps already give, or the card's title on a card laid outside any plan
    /**
     * @param list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>, after?: list<int>}> $steps
     * @param list<string>                                                                                                                    $current
     * @param list<string>                                                                                                                    $before
     */
    private function relay(Card $card, array $steps, array $current, array $before): void
    {
        $own = $card->getChecklist();
        $keys = self::keys($own);
        $current = self::filled($current, $keys);
        $removed = array_diff(self::filled($before, $keys), $current);
        $values = array_merge([], ...array_map(static fn (array $step): array => $step['params'] ?? [], $own));
        $title = null === $card->getPlan() ? $card->getTitle() : null;
        $free = $keys;
        $laid = [];
        $from = [];
        foreach ($steps as $position => $step) {
            $index = self::take($free, $current[$position]);
            $from[] = $index;
            $laid[] = self::grouped(null === $index ? self::fresh($step, $values, $title) : $own[$index], $step);
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

    // A card's step in the group its template step names, out of any when it names none
    /**
     * @param array{t: string, g?: string} $item
     * @param array{group?: string}        $step
     *
     * @return array{t: string, g?: string}
     */
    public static function grouped(array $item, array $step): array
    {
        unset($item['g']);
        if ('' !== ($step['group'] ?? '')) {
            $item['g'] = $step['group'];
        }

        return $item;
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

    // A step the template added, its {variables} filled with the values the card's steps already give, else with the fallback given, else left as they are
    /**
     * @param array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>} $step
     * @param array<string, string>                                                                                  $values
     *
     * @return array{t: string, action?: string, params?: array<string, string>}
     */
    private static function fresh(array $step, array $values, ?string $fallback = null): array
    {
        $fill = static fn (string $text): string => (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $match): string => $values[$match[1]] ?? $fallback ?? $match[0], $text);
        $item = ['t' => $fill($step['title'])];
        if (null !== ($step['action'] ?? null) && '' !== $step['action']) {
            $item['action'] = $step['action'];
            $item['params'] = array_map($fill, $step['params'] ?? []);
        }

        return $item;
    }

    // The template's keys as a card names them: one whose title holds {variables} known by the first card step it fits, an exact key kept as it is, each card step taken once
    /**
     * @param list<string> $templateKeys
     * @param list<string> $cardKeys
     *
     * @return list<string>
     */
    private static function filled(array $templateKeys, array $cardKeys): array
    {
        $free = array_diff($cardKeys, $templateKeys);
        $filled = [];
        foreach ($templateKeys as $key) {
            $filled[] = self::fitting($key, $free) ?? $key;
        }

        return $filled;
    }

    // The first free card key a template key holding {variables} fits, taken from now on; null when it holds none or none fits
    /** @param array<int, string> $free */
    private static function fitting(string $key, array &$free): ?string
    {
        if (1 !== preg_match('/\{\w+\}/', $key)) {
            return null;
        }
        $pattern = '/^' . preg_replace('/\\\\\{\w+\\\\\}/', '.+', preg_quote($key, '/')) . '$/su';
        foreach ($free as $index => $candidate) {
            if (1 === preg_match($pattern, $candidate)) {
                unset($free[$index]);

                return $candidate;
            }
        }

        return null;
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
