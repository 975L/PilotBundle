<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

// Life of a card: waiting for its plan to open it, to do, running on a machine, done or failed
enum CardStatus: string
{
    case Todo = 'todo';
    case Ready = 'ready';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    // Word shown on the card, translatable
    public function label(): TranslatableInterface
    {
        return match ($this) {
            self::Todo => t('label.status_todo', [], 'pilot'),
            self::Ready => t('label.status_ready', [], 'pilot'),
            self::Running => t('label.status_running', [], 'pilot'),
            self::Done => t('label.status_done', [], 'pilot'),
            self::Failed => t('label.status_failed', [], 'pilot'),
        };
    }
}
