<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\ConfigBundle\Management\GuidedProjectKeyGenerator;
use c975L\PilotBundle\Service\PilotAsk;
use Twig\Attribute\AsTwigFunction;

// Whether the shell draws Donovan's button and panel, and the key its conversation is kept under, read by every piloting page without each controller handing them
class PilotAskExtension
{
    public function __construct(
        private readonly PilotAsk $pilotAsk,
        private readonly GuidedProjectKeyGenerator $keyGenerator,
    ) {
    }

    // On as soon as the back office's AI key is filled in
    #[AsTwigFunction('pilot_ask_enabled')]
    public function enabled(): bool
    {
        return $this->pilotAsk->isEnabled();
    }

    // The account's key, so the next account in the same tab starts a blank conversation, as the back office's panel does; prefixed, the back office keeping its own conversation under the bare key
    #[AsTwigFunction('pilot_ask_key')]
    public function key(): string
    {
        $key = $this->keyGenerator->getKey();

        return '' === $key ? '' : 'pilot.' . $key;
    }
}
