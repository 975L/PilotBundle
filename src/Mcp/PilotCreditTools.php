<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Service\CreditOutlook;
use c975L\PilotBundle\Service\PilotUrls;
use c975L\PilotBundle\Service\Planning;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;

// The credits of the paid services as MCP tools, seen and typed in as the dashboard does for the admin
class PilotCreditTools
{
    public function __construct(
        private readonly Planning $planning,
        private readonly CreditRepository $creditRepository,
        private readonly CreditOutlook $creditOutlook,
        private readonly PilotAccess $pilotAccess,
        private readonly PilotUrls $pilotUrls,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    // Each credit the steps planned before its renewal leave unspent, or overspend, with the later steps that could spend it and the steps they wait for
    /**
     * @return list<array{credit: string, label: string, unit: ?string, renews: string, left: int, steps: list<array{card: ?int, title: string, url: string, due: ?string, step: int, text: string, amount: int, awaits: list<string>}>}>
     */
    public function outlook(): array
    {
        $scope = $this->admin();
        $plan = $this->planning->build($scope);
        $lines = [];
        foreach ($this->creditOutlook->build($this->creditRepository->findOrdered(), Planning::cardsOf($plan), $plan['today']) as $line) {
            $lines[] = [
                'credit' => $line['credit']->getKey(),
                'label' => $line['credit']->getLabel(),
                'unit' => $line['credit']->getUnit(),
                'renews' => $line['renewsOn']->format('Y-m-d'),
                'left' => $line['left'],
                'steps' => array_map(fn (array $offer): array => [
                    'card' => $offer['card']->getId(),
                    'title' => $offer['card']->getTitle(),
                    'url' => $this->pilotUrls->shared($offer['card']),
                    'due' => $offer['card']->getDue()?->format('Y-m-d'),
                    'step' => $offer['index'],
                    'text' => $offer['card']->getChecklist()[$offer['index']]['t'],
                    'amount' => $offer['amount'],
                    'awaits' => $offer['awaits'],
                ], $line['steps']),
            ];
        }

        return $lines;
    }

    // A balance typed in by hand, as read on the service's own site, with its renewal day when given; a credit whose service API is read refused
    /**
     * @param string  $credit    key of the credit
     * @param int     $remaining what is left on the account
     * @param ?string $renews    next renewal day, as YYYY-MM-DD
     *
     * @return array{credit: string, remaining: int, renews: ?string}
     */
    public function set(string $credit, int $remaining, ?string $renews = null): array
    {
        $this->admin();
        $found = $this->creditRepository->findOneBy(['key' => trim($credit)]);
        if (!$found instanceof Credit) {
            throw new ToolCallException(sprintf('No credit "%s".', $credit));
        }
        if (!$found->isManual()) {
            throw new ToolCallException(sprintf('The credit "%s" is read from its service, not typed in.', $credit));
        }
        if ($remaining < 0) {
            throw new ToolCallException('A balance is never below zero.');
        }
        $day = null === $renews || '' === trim($renews) ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', trim($renews));
        if (false === $day) {
            throw new ToolCallException(sprintf('"%s" is no day as YYYY-MM-DD.', $renews));
        }
        $found->setRemaining($remaining, $this->clock->now());
        if (null !== $day) {
            $found->setRenewsOn($day);
        }
        $this->entityManager->flush();

        return ['credit' => $found->getKey(), 'remaining' => $remaining, 'renews' => $found->getRenewsOn()?->format('Y-m-d')];
    }

    // The scope of an account seeing everything, any other refused as the dashboard hides the credits from it
    private function admin(): PilotScope
    {
        $scope = $this->pilotAccess->scope();
        if (!$scope->all) {
            throw new ToolCallException('Only an administrator sees the credits.');
        }

        return $scope;
    }
}
