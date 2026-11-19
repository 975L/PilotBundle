<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Form;

use c975L\PilotBundle\Form\PlanStep;
use PHPUnit\Framework\TestCase;

class PlanStepTest extends TestCase
{
    // A step goes back to the template as it came, its empty parts dropped, and is named by its title
    public function testRoundTrip(): void
    {
        $step = ['title' => 'Build {item}', 'executor' => 'runner', 'action' => 'build', 'params' => ['item' => '{item}']];

        $this->assertSame($step, PlanStep::fromArray($step)->toArray());
        $this->assertSame(['title' => 'Write', 'executor' => 'human'], PlanStep::fromArray(['title' => 'Write'])->toArray());
        $this->assertSame('Build {item}', (string) PlanStep::fromArray($step));
    }

    // The steps a step waits for are typed as their numbers: empty for the previous step, « - » for none, kept through the form
    public function testAfter(): void
    {
        $this->assertSame([2, 4], new PlanStep('Publish', after: '2, 4, 2')->toArray()['after']);
        $this->assertSame([], new PlanStep('Publish', after: ' - ')->toArray()['after']);
        $this->assertArrayNotHasKey('after', new PlanStep('Publish', after: '  ')->toArray());
        $this->assertSame('2, 4', PlanStep::fromArray(['title' => 'Publish', 'after' => [2, 4]])->after);
        $this->assertSame('-', PlanStep::fromArray(['title' => 'Publish', 'after' => []])->after);
        $this->assertSame('', PlanStep::fromArray(['title' => 'Publish'])->after);
    }

    // A step's group is kept through the form, trimmed, an empty one dropped
    public function testGroup(): void
    {
        $this->assertSame('Book', new PlanStep('Write', group: ' Book ')->toArray()['group']);
        $this->assertArrayNotHasKey('group', new PlanStep('Write', group: ' ')->toArray());
        $this->assertSame('Book', PlanStep::fromArray(['title' => 'Write', 'group' => 'Book'])->group);
    }
}
