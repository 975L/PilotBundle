<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Service\Credit\CreditBalance;
use PHPUnit\Framework\TestCase;

class CreditTest extends TestCase
{
    // A balance is dated when set, and an empty one is flagged
    public function testBalanceIsDatedAndEmptyFlagged(): void
    {
        $now = new \DateTimeImmutable('2026-11-17 10:00');
        $credit = new Credit('catalog')->setRemaining(0, $now);

        $this->assertTrue($credit->isEmpty());
        $this->assertSame($now, $credit->getUpdatedAt());
        $this->assertTrue($credit->isManual());
        $this->assertFalse($credit->setRemaining(5, $now)->isEmpty());
    }

    // A credit naming a service is read from it, the balance read leaving the label, unit and budget as typed
    public function testReadFromItsService(): void
    {
        $now = new \DateTimeImmutable('2026-11-20 10:00');
        $renews = new \DateTimeImmutable('2026-12-01');
        $credit = new Credit('catalog')->setLabel('Catalog')->setUnit('items')->setMonthly(50)->setProvider('elevenlabs');

        $this->assertFalse($credit->isManual());
        $credit->read(new CreditBalance(7, 10, $renews), $now);
        $this->assertSame([7, 10, $renews, $now], [$credit->getRemaining(), $credit->getTotal(), $credit->getRenewsOn(), $credit->getUpdatedAt()]);
        $this->assertSame(['Catalog', 'items', 50], [$credit->getLabel(), $credit->getUnit(), $credit->getMonthly()]);
        $this->assertTrue($credit->setProvider('')->isManual());
    }

    // An emptied provider, account or key is stored as none
    public function testEmptiedServiceFieldsAreNone(): void
    {
        $credit = new Credit('catalog')->setProvider('')->setAccount('')->setApiKey('');

        $this->assertSame([null, null, null], [$credit->getProvider(), $credit->getAccount(), $credit->getApiKey()]);
    }

    // An emptied form field reaches the setters as null, kept as an empty value for the validation to refuse
    public function testEmptiedFieldsAreKeptEmpty(): void
    {
        $credit = new Credit('catalog')->setKey(null)->setLabel(null)->setPosition(null);

        $this->assertSame('', $credit->getKey());
        $this->assertSame('', $credit->getLabel());
        $this->assertSame(0, $credit->getPosition());
    }
}
