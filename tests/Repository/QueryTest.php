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
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Tests\Fixtures\TestEntityManager;
use c975L\PilotBundle\Tests\Fixtures\UserFixture;
use Doctrine\ORM\EntityManager;
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
        $this->entityManager = TestEntityManager::create(true);
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

    // The cards laid from a checklist template outside any plan count as the template's, those of a plan of another template and the done ones do not
    public function testFindOpenOfTemplateTakesTheFollowingCards(): void
    {
        $template = new PlanTemplate('catalog', [['title' => 'a', 'executor' => 'human']])->setChecklist(true);
        $other = new PlanTemplate('other', [['title' => 'a', 'executor' => 'human']])->setChecklist(true);
        $this->entityManager->persist($template);
        $this->entityManager->persist($other);
        $following = $this->card('item-1')->follow($template);
        $plan = new Plan($other, $this->project, 'item-2');
        $this->card('item-2')->follow($template)->placeIn($plan, 1);
        $this->entityManager->persist($plan);
        $this->card('item-3')->follow($template)->markDone(new \DateTimeImmutable('2026-11-19'));
        $this->card('item-4');
        $this->entityManager->flush();

        $this->assertSame([$following], new CardRepository($this->registry)->findOpenOfTemplate($template));
    }

    // The titles of a space's cards, done ones included
    public function testTitlesOf(): void
    {
        $this->card('item-1');
        $this->card('item-2')->markDone(new \DateTimeImmutable('2026-11-19'));
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing(['item-1', 'item-2'], new CardRepository($this->registry)->titlesOf($this->project));
    }

    // A guest of a space sees there only the cards given to it, a contributor all of them, nobody the other spaces'
    public function testScopeKeepsTheCardsSeen(): void
    {
        $user = new UserFixture();
        $other = new Project('other', 'Other');
        $this->entityManager->persist($user);
        $this->entityManager->persist($other);
        $given = $this->card('item-1')->addAssignee($user);
        $this->card('item-2');
        $this->entityManager->persist(new Card($other, 'item-3'));
        $this->entityManager->flush();
        $repository = new CardRepository($this->registry);
        $id = (int) $this->project->getId();

        $this->assertSame([$given], $repository->findOpen($this->project, new PilotScope(false, [], [$id], $user)));
        $this->assertCount(2, $repository->findOpen($this->project, new PilotScope(false, [$id], [], $user)));
        $this->assertSame([], $repository->search('item-3', new PilotScope(false, [$id], [], $user)));
        $this->assertSame([$id => 1], $repository->countOpenByProject(new PilotScope(false, [], [$id], $user)));
        $this->assertCount(3, $repository->search('item', new PilotScope(true)));
    }

    // The done cards of a tab: counted by collection, listed for one collection or for none, a guest seeing only those given to it
    public function testDoneByCollection(): void
    {
        $user = new UserFixture();
        $set = new CardSet($this->project, 'catalog-set');
        $this->entityManager->persist($user);
        $this->entityManager->persist($set);
        $now = new \DateTimeImmutable();
        $inSet = $this->card('item-1')->setCardSet($set)->markDone($now);
        $own = $this->card('item-2')->markDone($now)->addAssignee($user);
        $this->card('item-3');
        $this->entityManager->flush();
        $repository = new CardRepository($this->registry);
        $id = (int) $this->project->getId();

        $counts = $repository->countDone($this->project, new PilotScope(true));
        ksort($counts);
        $this->assertSame([0 => 1, (int) $set->getId() => 1], $counts);
        $this->assertSame([$inSet], $repository->findDone($this->project, new PilotScope(true), $set));
        $this->assertSame([$own], $repository->findDone($this->project, new PilotScope(true), null));
        $this->assertSame([0 => 1], $repository->countDone($this->project, new PilotScope(false, [], [$id], $user)));
        $this->assertSame([], $repository->findDone($this->project, new PilotScope(false, [], [$id], $user), $set));
    }

    // A machine serving some spaces takes only their runs, one serving every space all of them
    public function testQueuedIdsOfTheSpacesServed(): void
    {
        $other = new Project('other', 'Other');
        $this->entityManager->persist($other);
        $own = new Run($this->card('item-1'), 'build', [], 0);
        $foreign = new Card($other, 'item-2');
        $this->entityManager->persist($foreign);
        $away = new Run($foreign, 'build', [], 0);
        $this->entityManager->flush();
        $repository = new RunRepository($this->registry);

        $this->assertSame([$own->getId()], $repository->queuedIds(['build'], [(int) $this->project->getId()]));
        $this->assertSame([$own->getId(), $away->getId()], $repository->queuedIds(['build']));
    }

    // A card of the project, persisted
    private function card(string $title): Card
    {
        $card = new Card($this->project, $title);
        $this->entityManager->persist($card);

        return $card;
    }
}
