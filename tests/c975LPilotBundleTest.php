<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use c975L\PilotBundle\c975LPilotBundle;
use c975L\PilotBundle\Command\ImportCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class c975LPilotBundleTest extends TestCase
{
    // Loaded the way the kernel does, which also checks that config/services.yaml parses and wires
    public function testLoadExtensionImportsServicesYaml(): void
    {
        $container = new ContainerBuilder();

        new c975LPilotBundle()->getContainerExtension()->load([], $container);

        $this->assertTrue($container->hasDefinition(ImportCommand::class));
    }

    public function testGetPathReturnsTheBundleRootDirectory(): void
    {
        $this->assertSame(\dirname(__DIR__), new c975LPilotBundle()->getPath());
    }
}
