<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Enum;

// What a document is: a project file, a graphic charter or a character sheet
enum DocKind: string
{
    case File = 'file';
    case Charter = 'charter';
    case Character = 'character';
}
