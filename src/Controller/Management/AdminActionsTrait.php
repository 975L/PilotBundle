<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

// The actions of the settings screens behind the admin role, the row ones shown as icons; the using class holds $configService and $translator
trait AdminActionsTrait
{
    /**
     * @param list<string> $names
     * @param list<string> $icons
     */
    private function adminActions(Actions $actions, array $names = [Action::INDEX, Action::NEW, Action::EDIT, Action::DELETE], array $icons = [Action::EDIT, Action::DELETE]): Actions
    {
        foreach ($names as $name) {
            $actions->setPermission($name, (string) $this->configService->get('site-role-admin'));
        }

        return EasyAdminActionHelper::iconOnlyDefaults($actions, $this->translator, $icons);
    }
}
