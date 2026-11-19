<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Controller\SettingsController;
use c975L\PilotBundle\Service\SettingsKinds;
use PHPUnit\Framework\TestCase;

class SettingsKindsTest extends TestCase
{
    // Every kind the routes reach is described, its form a form type and each column a property its entity reads
    public function testKindsHoldTogether(): void
    {
        $kinds = new SettingsKinds()->all();

        $this->assertSame(explode('|', SettingsController::KINDS), array_keys($kinds));
        foreach ($kinds as $kind => $settings) {
            $this->assertTrue(class_exists($settings['form']), $kind);
            foreach ($settings['columns'] as $column) {
                $reader = ucfirst($column['property']);
                $this->assertTrue(method_exists($settings['class'], 'get' . $reader) || method_exists($settings['class'], 'is' . $reader), $kind . ' ' . $column['property']);
            }
        }
        $this->assertFalse($kinds['action']['new']);
    }

    // An unknown kind is refused
    public function testUnknownKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingsKinds()->get('other');
    }
}
