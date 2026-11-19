<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\PilotBundle\Service\SettingsKinds;
use Twig\Attribute\AsTwigFunction;

// The kinds of setting for the links of the piloting pages' column and the tabs of the settings
class PilotSettingsExtension
{
    public function __construct(
        private readonly SettingsKinds $settingsKinds,
    ) {
    }

    // Every kind, in the order the settings show them
    /** @return array<string, array{class: class-string, form: class-string, label: string, title: string, description: string, icon: string, columns: list<array{property: string, label: string}>, translation_domain: string, sort: array<string, string>, new: bool, edit: bool}> */
    #[AsTwigFunction('pilot_setting_kinds')]
    public function all(): array
    {
        return $this->settingsKinds->all();
    }
}
