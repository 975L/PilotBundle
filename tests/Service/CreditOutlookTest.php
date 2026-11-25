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

    // Fine far from the renewal or with nothing to place, soon past half the month, late in the last week or when overspent, no tone without a renewal
    public function testTones(): void
    {
        $card = $this->card('2026-11-30', [['t' => 'a', 'cost' => ['credit' => 'item-5', 'amount' => 9]], ['t' => 'b', 'cost' => ['credit' => 'item-6', 'amount' => 3]]]);
        $credits = [
            $this->credit('item-1', 10, '2026-10-23'),
            $this->credit('item-2', 10),
            $this->credit('item-3', 10, '2026-10-01'),
            $this->credit('item-4', 0, '2026-10-01'),
            $this->credit('item-5', 5),
            $this->credit('item-6', 3),
            new Credit('item-7')->setRemaining(5),
        ];
        $outlook = new CreditOutlook();

        $this->assertSame(['item-1' => 'fine', 'item-2' => 'soon', 'item-3' => 'late', 'item-4' => 'fine', 'item-5' => 'late', 'item-6' => 'fine'], $outlook->tones($credits, $outlook->build($credits, [$card], new \DateTimeImmutable('2026-11-25')), new \DateTimeImmutable('2026-11-25')));
    }

    // The offers of every credit gathered card by card, the one spending most of the credit renewing first ahead, each card's steps in order and what they wait for named once
    public function testByCard(): void
    {
        $near = $this->card('2027-01-01', [['t' => 'a'], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 2]], ['t' => 'c', 'cost' => ['credit' => 'item-2', 'amount' => 3]]]);
        $far = $this->card('2027-02-01', [['t' => 'a', 'done' => true], ['t' => 'b', 'cost' => ['credit' => 'item-1', 'amount' => 1]]]);
        $credits = [$this->credit('item-1', 10), $this->credit('item-2', 10)];
        $outlook = new CreditOutlook();

        $cards = $outlook->byCard($outlook->build($credits, [$far, $near], new \DateTimeImmutable('2026-11-25')));

        $this->assertSame([$near, $far], array_column($cards, 'card'));
        $this->assertSame([[1, 2, 'item-1'], [2, 3, 'item-2']], array_map(static fn (array $step): array => [$step['index'], $step['amount'], $step['credit']->getKey()], $cards[0]['steps']));
        $this->assertSame(['a'], $cards[0]['awaits']);
        $this->assertSame([], $cards[1]['awaits']);
    }

    // A card spending the credit renewing first ahead of a nearer one spending a later credit, then the one spending most of it ahead of a nearer one spending less
    public function testByCardRankedBySpending(): void
    {
        $later = $this->card('2027-01-01', [['t' => 'a', 'cost' => ['credit' => 'item-2', 'amount' => 9]]]);
        $small = $this->card('2027-01-02', [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 1]]]);
        $big = $this->card('2027-01-03', [['t' => 'a', 'cost' => ['credit' => 'item-1', 'amount' => 5]]]);
        $credits = [$this->credit('item-1', 10), $this->credit('item-2', 10, '2026-10-20')];
        $outlook = new CreditOutlook();

        $this->assertSame([$big, $small, $later], array_column($outlook->byCard($outlook->build($credits, [$later, $small, $big], new \DateTimeImmutable('2026-11-25'))), 'card'));
    }

    // A credit holding a balance, renewing on the 8th unless told
    private function credit(string $key, int $remaining, string $renewsOn = '2026-10-08'): Credit
    {
        return new Credit($key)->setRemaining($remaining)->setRenewsOn(new \DateTimeImmutable($renewsOn));
    }

    // A card dated with its steps
    /** @param list<array{t: string, done?: bool, cost?: array{credit: string, amount: int}, after?: list<string>}> $steps */
    private function card(string $due, array $steps): Card
    {
        return new Card($this->project, 'item-' . $due)->setDue(new \DateTimeImmutable($due))->setChecklist($steps);
    }
}
