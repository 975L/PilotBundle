<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

// A collection deleted from its tab or the settings, its cards handed to its space's first other collection, the last one of a space holding cards kept
class CardSetRemoval
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly CardSetRepository $cardSetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // The collection removed, not flushed, giving the one its cards went to (null for the last one, empty); refused for the last one holding cards
    public function remove(CardSet $cardSet): ?CardSet
    {
        $cards = $this->cardRepository->findBy(['cardSet' => $cardSet]);
        $other = current(array_filter($this->cardSetRepository->findOfProject($cardSet->getProject()), static fn (CardSet $set): bool => $set !== $cardSet)) ?: null;
        if (null === $other && [] !== $cards) {
            throw new FlowException(t('label.collection_last', [], 'pilot'));
        }
        foreach ($cards as $card) {
            $card->setCardSet($other);
        }
        $this->entityManager->remove($cardSet);

        return $other;
    }
}
