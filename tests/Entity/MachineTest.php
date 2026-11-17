<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Machine;
use PHPUnit\Framework\TestCase;

class MachineTest extends TestCase
{
    // A new token is returned once, only its sha256 being kept, and a second one replaces it
    public function testNewTokenKeepsOnlyItsHash(): void
    {
        $machine = new Machine('machine-1', '');
        $token = $machine->newToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), $machine->getTokenHash());
        $this->assertNotSame($token, $machine->newToken());
    }

    // An emptied name reaches the setter as null, kept empty for the validation to refuse
    public function testEmptiedNameIsKeptEmpty(): void
    {
        $this->assertSame('', new Machine('machine-1', '')->setName(null)->getName());
    }
}
