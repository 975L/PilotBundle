<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Service\PilotUrls;
use Twig\Attribute\AsTwigFunction;

// The address of a card under its space, and the link opening on the computer a file a step names, for the piloting pages' links
class PilotUrlExtension
{
    // The config key naming the scheme of the opening links, the link being that name, :// then the path
    public const string OPEN_KEY = 'pilot-open-scheme';

    public function __construct(
        private readonly PilotUrls $pilotUrls,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // A card's page under its space
    #[AsTwigFunction('pilot_card_path')]
    public function card(Card $card): string
    {
        return $this->pilotUrls->card($card);
    }

    // The link opening a file or a folder of the machine, each segment of its path encoded; null while the site sets no opening link or none, the path then shown as text
    #[AsTwigFunction('pilot_open_url')]
    public function open(string $path): ?string
    {
        $scheme = strtolower(trim((string) $this->configService->get(self::OPEN_KEY)));

        return 1 !== preg_match('/^[a-z][a-z0-9+.-]{1,30}$/', $scheme) || \in_array($scheme, ['none', 'http', 'https', 'javascript', 'data', 'file'], true) ? null : $scheme . '://' . implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
