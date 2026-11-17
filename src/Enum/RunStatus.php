<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Enum;

// Life of an execution: queued until a machine claims it, then finished as done, failed or blocked
enum RunStatus: string
{
    case Queued = 'queued';
    case Claimed = 'claimed';
    case Done = 'done';
    case Failed = 'failed';
    case Blocked = 'blocked';

    // Whether the run is over
    public function isFinished(): bool
    {
        return \in_array($this, [self::Done, self::Failed, self::Blocked], true);
    }
}
