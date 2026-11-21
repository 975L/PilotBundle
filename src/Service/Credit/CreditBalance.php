<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service\Credit;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

// What a service's API says is left on an account
#[Exclude]
final readonly class CreditBalance
{
    public function __construct(
        public int $remaining,
        public ?int $total = null,
        public ?\DateTimeImmutable $renewsOn = null,
    ) {
    }
}
