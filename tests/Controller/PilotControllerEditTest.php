<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Controller\Management\PilotController;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class PilotControllerEditTest extends TestCase
{
    // A field edited in place posts alone: the others keep their value
    public function testOnlyThePostedFieldChanges(): void
    {
        $card = $this->card();

        $this->edit($card, ['note' => 'note-2']);

        $this->assertSame('item-1', $card->getTitle());
        $this->assertSame('note-2', $card->getNote());
        $this->assertSame('someone', $card->getWaitingOn());
        $this->assertSame('catalog-file', $card->getDoc()?->getSlug());
    }

    // An empty title keeps the one the card had, an emptied note or wait clears it
    public function testEmptiedFields(): void
    {
        $card = $this->card();

        $this->edit($card, ['title' => ' ', 'note' => '', 'waitingOn' => '']);

        $this->assertSame('item-1', $card->getTitle());
        $this->assertNull($card->getNote());
        $this->assertNull($card->getWaitingOn());
        $this->assertNotNull($card->getDoc());
    }

    // The file posted empty leaves the card without one
    public function testFileCleared(): void
    {
        $card = $this->card();

        $this->edit($card, ['doc' => '', 'newDoc' => '']);

        $this->assertNull($card->getDoc());
        $this->assertSame('note-1', $card->getNote());
    }

    private function card(): Card
    {
        return new Card(new Project('catalog', 'Catalog'), 'item-1')->setNote('note-1')->setWaitingOn('someone')->setDoc(new Doc('catalog-file', 'File'));
    }

    // The private gesture run on the posted fields
    /** @param array<string, string> $fields */
    private function edit(Card $card, array $fields): void
    {
        $controller = new PilotController(
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CardRepository::class),
            $this->createStub(ReportRepository::class),
            $this->createStub(DocRepository::class),
            $this->createStub(ProjectRepository::class),
            new MockClock(),
            new CardFlow(new MockClock()),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(ActionRepository::class),
            new StepOrder($this->createStub(CardRepository::class), new MockClock()),
        );
        new \ReflectionMethod($controller, 'edit')->invoke($controller, $card, new Request(request: $fields));
    }
}
