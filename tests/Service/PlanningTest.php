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
use c975L\PilotBundle\Service\Planning;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class PlanningTest extends TestCase
{
    // Late cards first, every coming day listed with its cards, the cards past the horizon by month
    public function testLateDaysAndLaterMonths(): void
    {
        $project = new Project('catalog', 'Catalog');
        $card = static fn (string $due): Card => new Card($project, 'item-' . $due)->setDue(new \DateTimeImmutable($due));
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findDatedUntil')->willReturn([$card('2026-11-10'), $card('2026-11-18')]);
        $cards->method('findDatedAfter')->willReturn([$card('2026-12-01'), $card('2026-12-12'), $card('2027-01-02')]);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn(7);

        $planning = new Planning($cards, $config, new MockClock('2026-11-17 10:00'))->build();

        $this->assertCount(1, $planning['late']);
        $this->assertCount(8, $planning['days']);
        $this->assertSame('2026-11-17', array_key_first($planning['days']));
        $this->assertSame('2026-11-24', array_key_last($planning['days']));
        $this->assertSame([], $planning['days']['2026-11-17']);
        $this->assertCount(1, $planning['days']['2026-11-18']);
        $this->assertSame(['2026-12-01', '2027-01-01'], array_keys($planning['later']));
        $this->assertCount(2, $planning['later']['2026-12-01']);
    }
}
