<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\EventListener;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\EventListener\CardHomeListener;
use c975L\PilotBundle\Tests\Fixtures\TestEntityManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RequiresPhpExtension('pdo_sqlite')]
class CardHomeListenerTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestEntityManager::create(true);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('item-cards');
        $this->entityManager->getEventManager()->addEventListener(Events::onFlush, new CardHomeListener($translator));
    }

    // A card saved outside any collection gets one made for its space, the next one joining it
    public function testACardWithoutCollectionGetsOne(): void
    {
        $project = new Project('catalog', 'Catalog');
        $first = new Card($project, 'item-1');
        $this->entityManager->persist($project);
        $this->entityManager->persist($first);
        $this->entityManager->flush();

        $this->assertSame('item-cards', $first->getCardSet()?->getName());

        $second = new Card($project, 'item-2');
        $this->entityManager->persist($second);
        $this->entityManager->flush();
        $this->assertSame($first->getCardSet(), $second->getCardSet());
    }

    // A card moved to another space leaves its collection for the first one of its new space
    public function testAMovedCardJoinsItsNewSpace(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $other = new Project('other', 'Other');
        $home = new CardSet($other, 'item-set');
        $card = new Card($catalog, 'item-1')->setCardSet(new CardSet($catalog, 'item-old'));
        foreach ([$catalog, $other, $home, $card, $card->getCardSet()] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $card->moveTo($other);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertSame('item-set', $this->entityManager->find(Card::class, $card->getId())?->getCardSet()?->getName());
    }
}
