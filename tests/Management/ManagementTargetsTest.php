<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use c975L\PilotBundle\Management\MenuProvider;
use c975L\PilotBundle\Management\PilotGuidedProjectProvider;

// Every controller and route the menu names, checked against what the controllers declare (see ManagementTargetsTestCase)
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider(),
            new PilotGuidedProjectProvider($this->urlGenerator(), $this->adminUrlGenerator(), $this->createStub(ConfigServiceInterface::class)),
        ];
    }

    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller/Management'];
    }
}
