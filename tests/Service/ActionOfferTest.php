<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Service\ActionOffer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ActionOfferTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('catalog', 'Catalog');
    }

    // The actions offered are those a machine online runs, named after the first machine declaring each one
    public function testOnNamesTheFirstMachineRunningEachAction(): void
    {
        $asked = [];
        $actions = $this->createStub(ActionRepository::class);
        $actions->method('findOffered')->willReturnCallback(static function (Project $project, array $keys) use (&$asked): array {
            $asked = $keys;

            return [new Action('hello')->declare('Hello', ['text'], false, 'Writes a text'), new Action('render')->declare('Render', [], true)];
        });

        $offer = $this->offer([$this->machine('machine-1', ['hello']), $this->machine('machine-2', ['hello', 'render'])], $actions);

        $this->assertSame([
            ['key' => 'hello', 'label' => 'Hello', 'params' => ['text'], 'machine' => 'machine-1', 'card' => false, 'help' => 'Writes a text'],
            ['key' => 'render', 'label' => 'Render', 'params' => [], 'machine' => 'machine-2', 'card' => true, 'help' => null],
        ], $offer->on($this->project));
        $this->assertSame(['hello', 'render'], $asked);
    }

    // No machine online offers nothing
    public function testOnIsEmptyWithoutMachineOnline(): void
    {
        $actions = $this->createStub(ActionRepository::class);
        $actions->method('findOffered')->willReturn([]);

        $this->assertSame([], $this->offer([], $actions)->on($this->project));
    }

    // Only the actions asking no parameter are handed in one click
    public function testDirectKeysLeavesOutTheActionsAskingParameters(): void
    {
        $actions = $this->createStub(ActionRepository::class);
        $actions->method('findOffered')->willReturn([new Action('hello')->declare('Hello', ['text']), new Action('render')->declare('Render', [])]);

        $this->assertSame(['render'], $this->offer([$this->machine('machine-1', ['hello', 'render'])], $actions)->directKeys($this->project));
    }

    // Every action declared gives its label, by key
    public function testLabelsByKey(): void
    {
        $actions = $this->createStub(ActionRepository::class);
        $actions->method('findAll')->willReturn([new Action('hello')->declare('Hello', []), new Action('render')]);

        $this->assertSame(['hello' => 'Hello', 'render' => 'render'], $this->offer([], $actions)->labels());
    }

    // An offer over the given machines online
    /** @param list<Machine> $online */
    private function offer(array $online, ActionRepository $actions): ActionOffer
    {
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOnline')->willReturn($online);

        return new ActionOffer($machines, $actions, new MockClock('2026-11-18 10:00'));
    }

    // A machine declaring the given action keys
    /** @param list<string> $keys */
    private function machine(string $name, array $keys): Machine
    {
        $capabilities = [];
        foreach ($keys as $key) {
            $capabilities[$key] = ['label' => $key, 'params' => []];
        }

        return new Machine($name, '')->seen($capabilities, new \DateTimeImmutable('2026-11-18 10:00'));
    }
}
