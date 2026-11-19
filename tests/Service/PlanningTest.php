<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Service\Planning;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class PlanningTest extends TestCase
{
    // Late cards first, only the coming days holding a card, the cards past the horizon by month
    public function testLateDaysAndLaterMonths(): void
    {
        $project = new Project('catalog', 'Catalog');
        $card = static fn (string $due): Card => new Card($project, 'item-' . $due)->setDue(new \DateTimeImmutable($due));
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findDatedUntil')->willReturn([$card('2026-11-10'), $card('2026-11-18')]);
        $cards->method('findDatedAfter')->willReturn([$card('2026-12-01'), $card('2026-12-12'), $card('2027-01-02')]);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn(7);

        $planning = new Planning($cards, $config, new MockClock('2026-11-17 10:00'))->build(new PilotScope(true));

        $this->assertCount(1, $planning['late']);
        $this->assertSame(['2026-11-18'], array_keys($planning['days']));
        $this->assertCount(1, $planning['days']['2026-11-18']);
        $this->assertSame(['2026-12-01', '2027-01-01'], array_keys($planning['later']));
        $this->assertCount(2, $planning['later']['2026-12-01']);
    }

    // Steps still to do count in the month of their card's date, a late card in this month, a ticked step, one without cost or with an incomplete one not at all
    public function testSpendByMonthAndCredit(): void
    {
        $project = new Project('catalog', 'Catalog');
        $steps = [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 10]], ['t' => 'b', 'done' => true, 'cost' => ['credit' => 'item-1', 'amount' => 99]], ['t' => 'c']];
        $late = new Card($project, 'late')->setDue(new \DateTimeImmutable('2026-10-01'))->setChecklist($steps);
        $next = new Card($project, 'next')->setDue(new \DateTimeImmutable('2026-12-12'))->setChecklist($steps);
        $planning = new Planning($this->createStub(CardRepository::class), $this->createStub(ConfigServiceInterface::class), new MockClock('2026-11-17'));

        // A cost imported without its amount, as the artifact may write it, past the entity's typed setter
        new \ReflectionProperty(Card::class, 'checklist')->setValue($next, [...$steps, ['t' => 'd', 'cost' => ['credit' => 'item-1']]]);

        $this->assertSame(['2026-11-01' => ['item-1' => 10], '2026-12-01' => ['item-1' => 10]], $planning->spend([$late, $next], new \DateTimeImmutable('2026-11-17')));
    }
}
