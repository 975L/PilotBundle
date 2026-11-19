<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Security;

use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

// What an account sees of the piloting: everything for the admin, otherwise the spaces it works in whole and, in those it is a guest of, the cards given to it
#[Exclude]
final readonly class PilotScope
{
    /**
     * @param list<int> $full  spaces whose every card is seen
     * @param list<int> $guest spaces where only the cards given to the account are
     */
    public function __construct(
        public bool $all,
        public array $full = [],
        public array $guest = [],
        public ?UserInterface $user = null,
    ) {
    }

    // The spaces seen at all
    /** @return list<int> */
    public function projectIds(): array
    {
        return [...$this->full, ...$this->guest];
    }

    // A query on cards kept to those seen, its alias for the card given
    public function restrictCards(QueryBuilder $builder, string $card = 'c'): QueryBuilder
    {
        if ($this->all) {
            return $builder;
        }
        if ([] === $this->projectIds()) {
            return $builder->andWhere('1 = 0');
        }

        return $builder
            ->andWhere(sprintf('(IDENTITY(%1$s.project) IN (:pilotFull) OR (IDENTITY(%1$s.project) IN (:pilotGuest) AND :pilotUser MEMBER OF %1$s.assignees))', $card))
            ->setParameter('pilotFull', [] === $this->full ? [0] : $this->full)
            ->setParameter('pilotGuest', [] === $this->guest ? [0] : $this->guest)
            ->setParameter('pilotUser', $this->user);
    }

    // A query on something of a space (a file, a report) kept to the spaces worked in whole, its expression for the space given
    public function restrictProjects(QueryBuilder $builder, string $project): QueryBuilder
    {
        if ($this->all) {
            return $builder;
        }

        return $builder->andWhere(sprintf('%s IN (:pilotFull)', $project))->setParameter('pilotFull', [] === $this->full ? [0] : $this->full);
    }
}
