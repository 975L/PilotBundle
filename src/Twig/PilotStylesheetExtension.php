<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use Twig\Attribute\AsTwigFunction;

// The piloting pages' own stylesheet, installed under public/bundles/c975lpilot by assets:install
class PilotStylesheetExtension
{
    // The compiled stylesheet, relative to the bundle
    public const string FILE = __DIR__ . '/../../public/css/pilot.min.css';

    // The time it was compiled, put after its address so that a browser never keeps a former one
    #[AsTwigFunction('pilot_stylesheet_version')]
    public function version(): int
    {
        return (int) @filemtime(self::FILE);
    }
}
