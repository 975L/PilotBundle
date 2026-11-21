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
use c975L\PilotBundle\Entity\Project;
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

    // A machine with no space chosen serves them all, one with spaces only those
    // The runs taken at once stay between one and the most allowed, an emptied field meaning one
    public function testParallelIsBounded(): void
    {
        $machine = new Machine('machine-1', '');
        $this->assertSame(1, $machine->getParallel());
        $this->assertSame(3, $machine->setParallel(3)->getParallel());
        $this->assertSame(Machine::MAX_PARALLEL, $machine->setParallel(99)->getParallel());
        $this->assertSame(1, $machine->setParallel(0)->getParallel());
        $this->assertSame(1, $machine->setParallel(null)->getParallel());
    }

    public function testServes(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $other = new Project('other', 'Other');
        $machine = new Machine('machine-1', str_repeat('a', 64));

        $this->assertTrue($machine->serves($other));
        $machine->addProject($catalog);
        $this->assertTrue($machine->serves($catalog));
        $this->assertFalse($machine->serves($other));
    }

    // A machine takes an action only while online, for a space it serves, when it declared the action
    public function testCanRun(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $machine = new Machine('machine-1', str_repeat('a', 64))->addProject($catalog);
        $now = new \DateTimeImmutable('2026-11-19 10:00:00');
        $this->assertFalse($machine->canRun('build', $catalog, $now));

        $machine->seen(['build' => ['label' => 'Build', 'params' => []]], $now);
        $this->assertTrue($machine->canRun('build', $catalog, $now->modify('+30 seconds')));
        $this->assertFalse($machine->canRun('copy', $catalog, $now));
        $this->assertFalse($machine->canRun('build', new Project('other', 'Other'), $now));
        $this->assertFalse($machine->canRun('build', $catalog, $now->modify('+2 minutes')));
    }
}
