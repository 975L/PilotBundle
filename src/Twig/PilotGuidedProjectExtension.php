<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\ConfigBundle\Management\GuidedProjectMountBuilder;
use Twig\Attribute\AsTwigFunction;

// The element ConfigBundle's guided-project panel mounts on, which the back office puts on its own pages and the piloting pages, on a shell of their own, have to carry themselves for this bundle's projects to walk them
class PilotGuidedProjectExtension
{
    public function __construct(
        private readonly GuidedProjectMountBuilder $mountBuilder,
    ) {
    }

    // Plain markup with data-* attributes, empty when nobody is signed in
    #[AsTwigFunction('pilot_guided_project_mount', isSafe: ['html'])]
    public function mount(): string
    {
        return $this->mountBuilder->getHtml();
    }
}
