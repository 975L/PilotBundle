<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Enum;

use c975L\PilotBundle\Enum\RunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RunStatusTest extends TestCase
{
    // A run is either active or finished, never both
    #[DataProvider('statuses')]
    public function testActiveOrFinished(RunStatus $status, bool $active): void
    {
        $this->assertSame($active, $status->isActive());
        $this->assertSame(!$active, $status->isFinished());
    }

    /** @return iterable<string, array{RunStatus, bool}> */
    public static function statuses(): iterable
    {
        yield 'queued' => [RunStatus::Queued, true];
        yield 'claimed' => [RunStatus::Claimed, true];
        yield 'done' => [RunStatus::Done, false];
        yield 'failed' => [RunStatus::Failed, false];
        yield 'blocked' => [RunStatus::Blocked, false];
        yield 'cancelled' => [RunStatus::Cancelled, false];
    }
}
