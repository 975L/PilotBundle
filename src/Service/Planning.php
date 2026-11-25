<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Security\PilotScope;
use Psr\Clock\ClockInterface;

// The planning: open dated cards, late ones first, the coming days holding a card, one by one up to the horizon set in days (config "pilot-horizon-days"), then the later ones by month, never limited in number
class Planning
{
    public const string HORIZON_KEY = 'pilot-horizon-days';

    private const int DEFAULT_HORIZON = 7;

    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly ClockInterface $clock,
    ) {
    }

    // Late cards, the coming ones by day, then the later ones by month, among those the account sees
    /**
     * @return array{today: \DateTimeImmutable, horizon: int, late: list<Card>, days: array<string, list<Card>>, later: array<string, list<Card>>, spend: array<string, array<string, int>>}
     */
    public function build(PilotScope $scope): array
    {
        $today = $this->clock->now()->setTime(0, 0);
        $horizon = $this->horizon();
        $late = [];
        $days = [];
        $later = [];
        $last = $today->modify('+' . $horizon . ' days');
        foreach ($this->cardRepository->findDatedAfter($last, $scope) as $card) {
            $later[$card->getDue()?->format('Y-m-01')][] = $card;
        }
        foreach ($this->cardRepository->findDatedUntil($last, $scope) as $card) {
            if ($card->getDue() < $today) {
                $late[] = $card;
                continue;
            }
            $days[$card->getDue()->format('Y-m-d')][] = $card;
        }
        ksort($days);

        $planning = ['today' => $today, 'horizon' => $horizon, 'late' => $late, 'days' => $days, 'later' => $later];

        return $planning + ['spend' => $this->spend(self::cardsOf($planning), $today)];
    }

    // Every card of a planning, late, coming and later ones in a single list
    /**
     * @param array{late: list<Card>, days: array<string, list<Card>>, later: array<string, list<Card>>} $planning
     *
     * @return list<Card>
     */
    public static function cardsOf(array $planning): array
    {
        return [...$planning['late'], ...array_merge(...array_values($planning['days'])), ...array_merge(...array_values($planning['later']))];
    }

    // What the steps still to do plan to spend, by month of their card's date (a late card counting in this month) and by credit
    /**
     * @param list<Card> $cards
     *
     * @return array<string, array<string, int>>
     */
    public function spend(array $cards, \DateTimeImmutable $today): array
    {
        $spend = [];
        foreach ($cards as $card) {
            $month = max($card->getDue() ?? $today, $today)->format('Y-m-01');
            foreach ($card->getChecklist() as $step) {
                $cost = Card::stepCost($step);
                if (null !== $cost) {
                    $spend[$month][$cost['credit']] = ($spend[$month][$cost['credit']] ?? 0) + $cost['amount'];
                }
            }
        }
        ksort($spend);

        return $spend;
    }

    // Days shown, the default when the key is unset or not a positive number
    private function horizon(): int
    {
        $days = (int) $this->configService->get(self::HORIZON_KEY);

        return $days > 0 ? $days : self::DEFAULT_HORIZON;
    }
}
