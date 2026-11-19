<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Service\CardSetRemoval;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CardSetRemovalTest extends TestCase
{
    // The cards go to the space's first other collection, the collection removed
    public function testHandsTheCardsOver(): void
    {
        $project = new Project('catalog', 'Catalog');
        $removed = new CardSet($project, 'item-1');
        $other = new CardSet($project, 'item-2');
        $card = new Card($project, 'card-1')->setCardSet($removed);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($removed);

        $this->assertSame($other, $this->removal([$card], [$removed, $other], $entityManager)->remove($removed));
        $this->assertSame($other, $card->getCardSet());
    }

    // The last collection of a space goes when empty, and is kept while it holds cards
    public function testLastCollection(): void
    {
        $project = new Project('catalog', 'Catalog');
        $last = new CardSet($project, 'item-1');
        $this->assertNull($this->removal([], [$last], $this->createStub(EntityManagerInterface::class))->remove($last));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');
        $this->expectException(FlowException::class);
        $this->removal([new Card($project, 'card-1')->setCardSet($last)], [$last], $entityManager)->remove($last);
    }

    /**
     * @param list<Card>    $cards
     * @param list<CardSet> $sets
     */
    private function removal(array $cards, array $sets, EntityManagerInterface $entityManager): CardSetRemoval
    {
        $cardRepository = $this->createStub(CardRepository::class);
        $cardRepository->method('findBy')->willReturn($cards);
        $cardSetRepository = $this->createStub(CardSetRepository::class);
        $cardSetRepository->method('findOfProject')->willReturn($sets);

        return new CardSetRemoval($cardRepository, $cardSetRepository, $entityManager);
    }
}
