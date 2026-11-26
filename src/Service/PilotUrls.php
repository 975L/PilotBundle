<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Card;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The addresses of the piloting pages following their levels: /pilot/{space}, then a card under its space
class PilotUrls
{
    public const string CARD_ROUTE = 'pilot_space_card';
    public const string SPACE_ROUTE = 'pilot_project';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // A card's page: /pilot/{space}/{id}, absolute when asked, for an agent to hand it on
    public function card(Card $card, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate(self::CARD_ROUTE, ['space' => $card->getProject()->getSlug(), 'id' => $card->getId()], $referenceType);
    }

    // A card as an absolute address handed on, its space's page opening it in its dialog: /pilot/{space}#card-{id}
    public function shared(Card $card): string
    {
        return $this->inSpace($card, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    // A card on its space's page, opened there in its dialog: /pilot/{space}#card-{id}
    public function inSpace(Card $card, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate(self::SPACE_ROUTE, ['slug' => $card->getProject()->getSlug()], $referenceType) . '#card-' . $card->getId();
    }
}
