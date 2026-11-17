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
}
