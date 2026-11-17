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
    /** @var list<\DateTimeImmutable> */
    private array $asked = [];

    // Planning on a fixed day, over the cards given, the horizon read from the config
    /**
     * @param list<Card> $cards
     */
    private function planning(array $cards, mixed $horizon): Planning
    {
        $repository = $this->createStub(CardRepository::class);
        $repository->method('findDatedUntil')->willReturnCallback(function (\DateTimeImmutable $last) use ($cards): array {
            $this->asked[] = $last;

            return $cards;
        });
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn($horizon);

        return new Planning($repository, $config, new MockClock('2026-11-17 15:00:00'));
    }

    private function card(string $due): Card
    {
        return new Card(new Project('catalog', 'Catalog'), 'Item ' . $due)->setDue(new \DateTimeImmutable($due));
    }

    // Late cards apart, the others by day, as many as there are
    public function testLateApartComingByDay(): void
    {
        $plan = $this->planning([$this->card('2026-11-10'), $this->card('2026-11-17'), $this->card('2026-11-20'), $this->card('2026-11-20')], '5');

        $built = $plan->build();

        $this->assertCount(1, $built['late']);
        $this->assertSame(['2026-11-17', '2026-11-20'], array_keys($built['days']));
        $this->assertCount(2, $built['days']['2026-11-20']);
        $this->assertSame(5, $built['horizon']);
        $this->assertSame('2026-11-22', $this->asked[0]->format('Y-m-d'));
    }

    // An unset or wrong horizon falls back to a week
    public function testHorizonDefaultsToAWeek(): void
    {
        $this->assertSame(7, $this->planning([], null)->build()['horizon']);
        $this->assertSame(7, $this->planning([], '-3')->build()['horizon']);
    }
}
