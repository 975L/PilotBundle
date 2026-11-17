<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Exception;

use Symfony\Component\Translation\TranslatableMessage;

// A gesture refused on a card or a run, its reason translatable and its message the reason's key
class FlowException extends \RuntimeException
{
    public function __construct(
        public readonly TranslatableMessage $reason,
    ) {
        parent::__construct($reason->getMessage());
    }
}
