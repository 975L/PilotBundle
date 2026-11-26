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
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Service\RunOrder;
use PHPUnit\Framework\TestCase;

class RunOrderTest extends TestCase
{
    // The runs dragged take the places they held in their new order, a run not given keeping its own, the queue ranked from the top
    public function testRunsDraggedSwapTheirPlaces(): void
    {
        [$first, $second, $third] = [$this->queued(1), $this->queued(2), $this->queued(3)];

        $queue = $this->order([$first, $second, $third])->reorder([3, 1]);

        $this->assertSame([$third, $second, $first], $queue);
        $this->assertSame([3, 2, 1], [$third->getPriority(), $second->getPriority(), $first->getPriority()]);
    }

    // A held run, an unknown id, a repeated one or a run the account may not run moves nothing
    public function testOnlyQueuedRunsTheAccountMayRunMove(): void
    {
        $held = $this->queued(1)->claim(new Machine('machine-1', str_repeat('a', 64)), new \DateTimeImmutable('2026-11-26 10:00'));
        [$first, $foreign] = [$this->queued(2), $this->queued(3, 'other')];

        $queue = $this->order([$held, $first, $foreign])->reorder([3, 99, 2, 2, 1]);

        $this->assertSame([$first, $foreign], $queue);
        $this->assertSame([0, 2, 1], [$held->getPriority(), $first->getPriority(), $foreign->getPriority()]);
    }

    // The runs of a space the account does not see keep their places in the queue, the runs moved only swapping theirs
    public function testUnseenRunsKeepTheirPlaces(): void
    {
        [$first, $hidden, $second] = [$this->queued(1), $this->queued(2, 'hidden'), $this->queued(3)];

        $queue = $this->order([$first, $hidden, $second], [$first, $second])->reorder([3, 1]);

        $this->assertSame([$second, $first], $queue);
        $this->assertSame([3, 2, 1], [$second->getPriority(), $hidden->getPriority(), $first->getPriority()]);
    }

    // The runs of one card stay in their order of arrival, the machines taking them one after the other
    public function testRunsOfOneCardKeepTheirOrder(): void
    {
        $card = new Card(new Project('catalog', 'catalog'), 'item-1');
        [$first, $second, $other] = [$this->queued(1, card: $card), $this->queued(2, card: $card), $this->queued(3)];

        $queue = $this->order([$first, $second, $other])->reorder([2, 3, 1]);

        $this->assertSame([$first, $other, $second], $queue);
    }

    // The service over the runs under way given, all of them or those seen, the account running the cards of the catalog space only
    /**
     * @param list<Run>      $active
     * @param list<Run>|null $seen
     */
    private function order(array $active, ?array $seen = null): RunOrder
    {
        $runs = $this->createStub(RunRepository::class);
        $runs->method('findActive')->willReturnCallback(static fn (PilotScope $scope): array => $scope->all ? $active : $seen ?? $active);
        $access = $this->createStub(PilotAccess::class);
        $access->method('scope')->willReturn(new PilotScope(null === $seen));
        $access->method('can')->willReturnCallback(static fn (Project | Card $subject): bool => $subject instanceof Card && 'catalog' === $subject->getProject()->getSlug());

        return new RunOrder($runs, $access);
    }

    // A queued run of its own card unless one is given, with its id
    private function queued(int $id, string $space = 'catalog', ?Card $card = null): Run
    {
        $run = new Run($card ?? new Card(new Project($space, $space), 'item-' . $id), 'build', [], 0);
        new \ReflectionProperty(Run::class, 'id')->setValue($run, $id);

        return $run;
    }
}
