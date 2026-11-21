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

    // What runs spent taken off the balances typed in by hand, never below zero, each in one atomic update so that two runs ending together both count; a service's balance is left to its refresh
    /** @param array<string, int> $spent */
    public function spend(array $spent, \DateTimeImmutable $now): void
    {
        foreach ($spent as $key => $amount) {
            $this->getEntityManager()->createQuery('UPDATE ' . Credit::class . ' c SET c.remaining = CASE WHEN c.remaining > :amount THEN c.remaining - :amount ELSE 0 END, c.updatedAt = :now WHERE c.key = :key AND c.provider IS NULL AND c.remaining IS NOT NULL')
                ->setParameter('amount', $amount)
                ->setParameter('now', $now)
                ->setParameter('key', (string) $key)
                ->execute();
        }
    }
}
