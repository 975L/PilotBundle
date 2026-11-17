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

    // An emptied form field reaches the setters as null, kept as an empty value for the validation to refuse
    public function testEmptiedFieldsAreKeptEmpty(): void
    {
        $credit = new Credit('catalog')->setKey(null)->setLabel(null)->setPosition(null);

        $this->assertSame('', $credit->getKey());
        $this->assertSame('', $credit->getLabel());
        $this->assertSame(0, $credit->getPosition());
    }
}
