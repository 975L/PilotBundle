<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Management;

use c975L\ConfigBundle\Management\ImportmapProviderInterface;

// The importmap.php entry of the piloting pages' script, added by c975l:config:check-importmap
class ImportmapProvider implements ImportmapProviderInterface
{
    public function getAdminImportmapEntries(): array
    {
        return [
            '@c975l/pilot-bundle/controllers-admin.js' => [
                'path' => 'assets/controllers-admin.js',
                'entrypoint' => true,
            ],
        ];
    }

    public function getImportmapEntries(): array
    {
        return [];
    }
}
