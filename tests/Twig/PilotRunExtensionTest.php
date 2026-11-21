<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Twig\PilotRunExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

// A run under way shows what its action reports, or how far it should be from the time its action usually takes, never done before it is
class PilotRunExtensionTest extends TestCase
{
    // Halfway through the action's usual time, a run reporting less shows an estimate, one reporting more its own value
    public function testTheEstimateFollowsTheUsualTime(): void
    {
        $run = $this->runAt(5);
        $this->assertSame(['value' => 50, 'estimated' => true], $this->extension(200, '+100 seconds')->progress($run));

        $run->report(70, null, new \DateTimeImmutable('2026-11-20 10:01'));
        $this->assertSame(['value' => 70, 'estimated' => false], $this->extension(200, '+100 seconds')->progress($run));
    }

    // Past its usual time the estimate stops short of done, and an action never run yet keeps what it reports
    public function testTheEstimateStaysShortOfDone(): void
    {
        $this->assertSame(['value' => PilotRunExtension::ESTIMATE_MAX, 'estimated' => true], $this->extension(60, '+10 minutes')->progress($this->runAt(5)));
        $this->assertSame(['value' => 5, 'estimated' => false], $this->extension(null, '+10 minutes')->progress($this->runAt(5)));
    }

    // A run of the action, taken at 10:00 and reporting the given progress
    private function runAt(int $progress): Run
    {
        return new Run(new Card(new Project('catalog', 'Catalog'), 'item-1'), 'hello', [], 0)
            ->claim(new Machine('machine-1', ''), new \DateTimeImmutable('2026-11-20 10:00'))
            ->report($progress, null, new \DateTimeImmutable('2026-11-20 10:00'));
    }

    // The extension on an action usually lasting the given seconds, read at that time after 10:00
    private function extension(?int $typical, string $after): PilotRunExtension
    {
        $runs = $this->createStub(RunRepository::class);
        $runs->method('typicalSeconds')->willReturn($typical);

        return new PilotRunExtension($runs, new MockClock(new \DateTimeImmutable('2026-11-20 10:00')->modify($after)));
    }
}
