<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Management;

use c975L\UiBundle\Contract\BundleScriptAdminProviderInterface;

// The script of the piloting pages loaded on the dashboard: the card opened in a dialog, the projects ordered by dragging
class ScriptProvider implements BundleScriptAdminProviderInterface
{
    public function getAdminScripts(): array
    {
        return [
            '@c975l/pilot-bundle/controllers-admin.js',
        ];
    }
}
