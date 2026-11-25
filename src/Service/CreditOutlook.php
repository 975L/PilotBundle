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
use c975L\PilotBundle\Entity\Credit;

// What each credit holds that the steps planned before its renewal will not spend, and the steps of later cards that could spend it, with the steps they still wait for
class CreditOutlook
{
    // Steps offered at most for a credit
    public const int LIMIT = 5;

    // A line per credit with a balance and a renewal whose planned steps leave some of it, or spend more than it holds
    /**
     * @param list<Credit> $credits
     * @param list<Card>   $cards   the open dated cards
     *
     * @return list<array{credit: Credit, renewsOn: \DateTimeImmutable, left: int, steps: list<array{card: Card, index: int, amount: int, awaits: list<string>}>}>
     */
    public function build(array $credits, array $cards, \DateTimeImmutable $today): array
    {
        $lefts = $this->lefts($credits, $cards, $today);
        $outlook = [];
        foreach ($credits as $credit) {
            $renewsOn = $credit->nextRenewal($today);
            $left = $lefts[$credit->getKey()] ?? 0;
            if (null !== $renewsOn && null !== $credit->getRemaining() && 0 !== $left) {
                $outlook[] = ['credit' => $credit, 'renewsOn' => $renewsOn, 'left' => $left, 'steps' => $left > 0 ? $this->offers($credit->getKey(), $cards, $renewsOn, $left, $lefts) : []];
            }
        }

        return $outlook;
    }

    // What each credit with a balance holds once the steps planned before its renewal are taken off, the whole balance for one without a renewal
    /**
     * @param list<Credit> $credits
     * @param list<Card>   $cards
     *
     * @return array<string, int>
     */
    private function lefts(array $credits, array $cards, \DateTimeImmutable $today): array
    {
        $lefts = [];
        foreach ($credits as $credit) {
            $renewsOn = $credit->nextRenewal($today);
            if (null !== $credit->getRemaining()) {
                $lefts[$credit->getKey()] = $credit->getRemaining() - (null === $renewsOn ? 0 : $this->planned($credit->getKey(), $cards, $renewsOn));
            }
        }

        return $lefts;
    }

    // What the steps of the cards dated before the renewal plan to spend of a credit
    /** @param list<Card> $cards */
    private function planned(string $key, array $cards, \DateTimeImmutable $renewsOn): int
    {
        $planned = 0;
        foreach ($cards as $card) {
            if ($card->getDue() < $renewsOn) {
                foreach ($card->getSteps() as $step) {
                    $cost = Card::stepCost($step);
                    $planned += null !== $cost && $key === $cost['credit'] ? $cost['amount'] : 0;
                }
            }
        }

        return $planned;
    }

    // Steps of the later cards spending the credit that fit in what is left, the nearest first then the readiest, a step waited for counted once
    /**
     * @param list<Card>         $cards
     * @param array<string, int> $lefts what each credit holds
     *
     * @return list<array{card: Card, index: int, amount: int, awaits: list<string>}>
     */
    private function offers(string $key, array $cards, \DateTimeImmutable $renewsOn, int $left, array $lefts): array
    {
        $offers = [];
        $taken = [];
        foreach ($this->candidates($key, $cards, $renewsOn, $lefts) as [$card, $index, $awaits]) {
            $steps = array_filter([$index, ...$awaits], static fn (int $step): bool => !isset($taken[spl_object_id($card) . ':' . $step]));
            $amount = array_sum(array_map(static fn (int $step): int => $key === (Card::stepCost($card->getChecklist()[$step])['credit'] ?? null) ? Card::stepCost($card->getChecklist()[$step])['amount'] : 0, $steps));
            if (!\in_array($index, $steps, true) || $amount > $left) {
                continue;
            }
            $left -= $amount;
            foreach ($steps as $step) {
                $taken[spl_object_id($card) . ':' . $step] = true;
            }
            $offers[] = ['card' => $card, 'index' => $index, 'amount' => $amount, 'awaits' => array_map(static fn (int $step): string => $card->getChecklist()[$step]['t'], $awaits)];
            if (\count($offers) >= self::LIMIT) {
                break;
            }
        }

        return $offers;
    }

    // Steps spending the credit on the cards dated from the renewal on, with the steps they wait for, those another credit could not pay for left out, sorted by date then steps awaited
    /**
     * @param list<Card>         $cards
     * @param array<string, int> $lefts
     *
     * @return list<array{0: Card, 1: int, 2: list<int>}>
     */
    private function candidates(string $key, array $cards, \DateTimeImmutable $renewsOn, array $lefts): array
    {
        $candidates = [];
        foreach ($cards as $card) {
            if ($card->getDue() < $renewsOn) {
                continue;
            }
            foreach ($card->getSteps() as $index => $step) {
                $awaits = $card->stepAwaitsAll($index);
                if ($key === (Card::stepCost($step)['credit'] ?? null) && !$this->spendsElsewhere($card, $awaits, $key, $lefts)) {
                    $candidates[] = [$card, $index, $awaits];
                }
            }
        }
        usort($candidates, static fn (array $a, array $b): int => [$a[0]->getDue(), \count($a[2])] <=> [$b[0]->getDue(), \count($b[2])]);

        return $candidates;
    }

    // Whether the steps waited for spend more of another credit than it holds, or of one whose balance is unknown, bringing the step forward spending them too
    /**
     * @param list<int>          $awaits
     * @param array<string, int> $lefts
     */
    private function spendsElsewhere(Card $card, array $awaits, string $key, array $lefts): bool
    {
        $spent = [];
        foreach ($awaits as $step) {
            $cost = Card::stepCost($card->getChecklist()[$step]);
            if (null !== $cost && $key !== $cost['credit']) {
                $spent[$cost['credit']] = ($spent[$cost['credit']] ?? 0) + $cost['amount'];
            }
        }

        return array_any($spent, static fn (int $amount, string $credit): bool => $amount > ($lefts[$credit] ?? 0));
    }
}
