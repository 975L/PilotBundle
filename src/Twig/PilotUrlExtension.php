<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Service\PilotUrls;
use Twig\Attribute\AsTwigFunction;

// The address of a card under its space, for the piloting pages' links
class PilotUrlExtension
{
    public function __construct(
        private readonly PilotUrls $pilotUrls,
    ) {
    }

    // A card's page under its space
    #[AsTwigFunction('pilot_card_path')]
    public function card(Card $card): string
    {
        return $this->pilotUrls->card($card);
    }
}
