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
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Service\CreditOutlook;
use PHPUnit\Framework\TestCase;

class CreditOutlookTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('catalog', 'Catalog');
    }

    // What is planned before the renewal is taken off, then the later steps fitting what is left offered, the nearest first, one too dear or waiting for a credit of unknown balance left out
    public function testLeftAndOffers(): void
    {
        $credit = $this->credit('item-1', 40);
        $before = $this->card('2026-12-01', [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 10]]]);
        $ready = $this->card('2027-03-01', [['t' => 'a', 'done' => true], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 12]]]);
        $waiting = $this->card('2027-01-01', [['t' => 'a'], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 8]]]);
        $dear = $this->card('2027-01-01', [['t' => 'a', 'done' => true], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 99]]]);
        $elsewhere = $this->card('2027-01-01', [['t' => 'a', 'cost' => ['credit' => 'item-2', 'amount' => 1]], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 1]]]);

        $outlook = new CreditOutlook()->build([$credit], [$before, $ready, $waiting, $dear, $elsewhere], new \DateTimeImmutable('2026-11-25'));

        $this->assertCount(1, $outlook);
        $this->assertSame(30, $outlook[0]['left']);
        $this->assertSame('2026-12-08', $outlook[0]['renewsOn']->format('Y-m-d'));
        $this->assertSame([[$waiting, 1, 8, ['a']], [$ready, 1, 12, []]], array_map(static fn (array $offer): array => [$offer['card'], $offer['index'], $offer['amount'], $offer['awaits']], $outlook[0]['steps']));
    }

    // A step waiting for another credit offered while that credit holds enough for it, without a renewal of its own, its amount counting this credit only
    public function testOtherCreditHoldingEnough(): void
    {
        $card = $this->card('2027-01-01', [['t' => 'a', 'cost' => ['credit' => 'item-2', 'amount' => 5]], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 1]]]);
        $credits = [$this->credit('item-1', 10), new Credit('item-2')->setRemaining(5)];

        $this->assertSame([[1, 1]], array_map(static fn (array $offer): array => [$offer['index'], $offer['amount']], new CreditOutlook()->build($credits, [$card], new \DateTimeImmutable('2026-11-25'))[0]['steps']));
        $credits[1]->setRemaining(4);
        $this->assertSame([], new CreditOutlook()->build($credits, [$card], new \DateTimeImmutable('2026-11-25'))[0]['steps']);
    }

    // A step waited for that spends the same credit counts in the offer, and once only when two offers wait for it
    public function testStepWaitedForCountedOnce(): void
    {
        $card = $this->card('2027-01-01', [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 5]], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 3], 'after' => ['text:a']], ['t' => 'c', 'cost' => ['credit' => 'item-1', 'amount' => 2], 'after' => ['text:a']]]);

        $steps = new CreditOutlook()->build([$this->credit('item-1', 20)], [$card], new \DateTimeImmutable('2026-11-25'))[0]['steps'];

        $this->assertSame([[0, 5], [1, 3], [2, 2]], array_map(static fn (array $offer): array => [$offer['index'], $offer['amount']], $steps));
    }

    // A credit overspent before its renewal said so without offers, one spent exactly, without a balance or without a renewal left out
    public function testShortExactAndUnknown(): void
    {
        $card = $this->card('2026-12-01', [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 50]], ['t' => 'b', 'cost' => ['credit' => 'item-2', 'amount' => 10]]]);
        $credits = [$this->credit('item-1', 40), $this->credit('item-2', 10), new Credit('item-3')->setRenewsOn(new \DateTimeImmutable('2026-12-08')), new Credit('item-4')->setRemaining(5)];

        $outlook = new CreditOutlook()->build($credits, [$card], new \DateTimeImmutable('2026-11-25'));

        $this->assertSame([['item-1', -10, []]], array_map(static fn (array $line): array => [$line['credit']->getKey(), $line['left'], $line['steps']], $outlook));
    }

    // A credit renewing on the 8th, holding a balance
    private function credit(string $key, int $remaining): Credit
    {
        return new Credit($key)->setRemaining($remaining)->setRenewsOn(new \DateTimeImmutable('2026-10-08'));
    }

    // A card dated with its steps
    /** @param list<array{t: string, done?: bool, cost?: array{credit: string, amount: int}, after?: list<string>}> $steps */
    private function card(string $due, array $steps): Card
    {
        return new Card($this->project, 'item-' . $due)->setDue(new \DateTimeImmutable($due))->setChecklist($steps);
    }
}
