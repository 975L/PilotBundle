<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Service\RunOutputs;

// The files the machines sent for their runs, kept nowhere else once the machine cleans up, and the pictures of the files
class PilotBackupPathProvider implements BackupPathProviderInterface
{
    public function getBackupPaths(): array
    {
        return [
            new BackupPath(RunOutputs::DIRECTORY, BackupPath::MODE_MIRROR),
            new BackupPath(Doc::IMAGE_DIRECTORY, BackupPath::MODE_MIRROR),
        ];
    }
}
