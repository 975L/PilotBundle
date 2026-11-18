<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Repository;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\RunRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The queries carrying a subquery, run on an in-memory database
#[RequiresPhpExtension('pdo_sqlite')]
class QueryTest extends TestCase
{
    private EntityManager $entityManager;
    private ManagerRegistry $registry;
    private Project $project;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setUseSchemaEditorApi(true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->registry = $this->createStub(ManagerRegistry::class);
        $this->registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->project = new Project('catalog', 'Catalog');
        $this->entityManager->persist($this->project);
    }

    // One run of a card at a time: the next one waits while one is held or queued before it, other cards' runs being offered
    public function testQueuedIdsOneRunOfACardAtATime(): void
    {
        $card = $this->card('item-1');
        $first = new Run($card, 'build', [], 0);
        $second = new Run($card, 'build', [], 1);
        $other = new Run($this->card('item-2'), 'build', [], 0);
        $this->entityManager->flush();
        $repository = new RunRepository($this->registry);

        $this->assertSame([$first->getId(), $other->getId()], $repository->queuedIds(['build']));

        $machine = new Machine('machine-1', str_repeat('a', 64));
        $this->entityManager->persist($machine);
        $first->claim($machine, new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->assertSame([$other->getId()], $repository->queuedIds(['build']));

        $first->finish(RunStatus::Done, null, [], null, new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->assertSame([$second->getId(), $other->getId()], $repository->queuedIds(['build']));
    }

    // Only the open cards of the template's plans made of a single card, a plan of a card per step left out
    public function testFindOpenOfTemplateKeepsSingleCardPlans(): void
    {
        $template = new PlanTemplate('catalog', [['title' => 'a', 'executor' => 'human'], ['title' => 'b', 'executor' => 'human']]);
        $this->entityManager->persist($template);
        $checklist = $this->card('item-1')->placeIn(new Plan($template, $this->project, 'item-1'), 1);
        $perStep = new Plan($template, $this->project, 'item-2');
        $this->card('item-2-a')->placeIn($perStep, 1);
        $this->card('item-2-b')->placeIn($perStep, 2);
        $this->entityManager->persist($checklist->getPlan());
        $this->entityManager->persist($perStep);
        $this->entityManager->flush();

        $this->assertSame([$checklist], new CardRepository($this->registry)->findOpenOfTemplate($template));
    }

    // A card of the project, persisted
    private function card(string $title): Card
    {
        $card = new Card($this->project, $title);
        $this->entityManager->persist($card);

        return $card;
    }
}
