<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Repository;

use c975L\PilotBundle\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Credit>
 */
class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }

    // Every balance, in the order chosen
    /** @return list<Credit> */
    public function findOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'label' => 'ASC']);
    }

    // Records the balances a machine reports, a new account appearing at the end; a balance typed in by hand is never overwritten
    /** @param array<string, array{label: string, remaining: ?int, total: ?int, unit: ?string, renewsOn: ?\DateTimeImmutable}> $reports */
    public function report(array $reports, \DateTimeImmutable $now): void
    {
        $known = [];
        foreach ($this->findBy(['key' => array_keys($reports)]) as $credit) {
            $known[$credit->getKey()] = $credit;
        }
        foreach ($reports as $key => $report) {
            $credit = $known[$key] ?? new Credit((string) $key)->setManual(false)->setPosition(100);
            if ($credit->isManual()) {
                continue;
            }
            $credit->setLabel($report['label'])->setTotal($report['total'])->setUnit($report['unit'])->setRenewsOn($report['renewsOn'])->setRemaining($report['remaining'], $now);
            $this->getEntityManager()->persist($credit);
        }
    }
}
