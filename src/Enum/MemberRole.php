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

// What a member may do in a space, each role holding the ones below it: a guest sees and ticks the cards assigned to it, a contributor works on every card, an editor launches the machines, an owner sets the space up and chooses its members
enum MemberRole: string
{
    case Guest = 'guest';
    case Contributor = 'contributor';
    case Editor = 'editor';
    case Owner = 'owner';

    // Its place on the ladder, to compare two roles
    public function rank(): int
    {
        return match ($this) {
            self::Guest => 1,
            self::Contributor => 2,
            self::Editor => 3,
            self::Owner => 4,
        };
    }

    // Whether it holds the given one
    public function holds(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    // Word shown in the members' list, translatable
    public function label(): TranslatableInterface
    {
        return match ($this) {
            self::Guest => t('label.role_guest', [], 'pilot'),
            self::Contributor => t('label.role_contributor', [], 'pilot'),
            self::Editor => t('label.role_editor', [], 'pilot'),
            self::Owner => t('label.role_owner', [], 'pilot'),
        };
    }
}
