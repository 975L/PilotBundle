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
use Psr\Clock\ClockInterface;

// The planning: open dated cards up to the horizon set in days (config "pilot-horizon-days"), split into late and coming, never limited in number
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

    // Late cards and the coming ones, by day
    /**
     * @return array{today: \DateTimeImmutable, horizon: int, late: list<Card>, days: array<string, list<Card>>}
     */
    public function build(): array
    {
        $today = $this->clock->now()->setTime(0, 0);
        $horizon = $this->horizon();
        $late = [];
        $days = [];
        foreach ($this->cardRepository->findDatedUntil($today->modify('+' . $horizon . ' days')) as $card) {
            if ($card->getDue() < $today) {
                $late[] = $card;
                continue;
            }
            $days[$card->getDue()->format('Y-m-d')][] = $card;
        }

        return ['today' => $today, 'horizon' => $horizon, 'late' => $late, 'days' => $days];
    }

    // Days shown, the default when the key is unset or not a positive number
    private function horizon(): int
    {
        $days = (int) $this->configService->get(self::HORIZON_KEY);

        return $days > 0 ? $days : self::DEFAULT_HORIZON;
    }
}
