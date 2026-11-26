<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\ConfigBundle\Management\GuidedProjectKeyGenerator;
use c975L\PilotBundle\Service\PilotAsk;
use c975L\PilotBundle\Twig\PilotAskExtension;
use PHPUnit\Framework\TestCase;

// The shell draws Donovan as soon as the back office's AI key is filled in
class PilotAskExtensionTest extends TestCase
{
    // The switch is the service's own
    public function testFollowsTheService(): void
    {
        $ask = $this->createStub(PilotAsk::class);
        $ask->method('isEnabled')->willReturnOnConsecutiveCalls(true, false);
        $extension = new PilotAskExtension($ask, $this->createStub(GuidedProjectKeyGenerator::class));

        $this->assertTrue($extension->enabled());
        $this->assertFalse($extension->enabled());
    }

    // The piloting's conversation kept apart from the back office's, nothing kept with no account
    public function testKeyIsPrefixed(): void
    {
        $keys = $this->createStub(GuidedProjectKeyGenerator::class);
        $keys->method('getKey')->willReturnOnConsecutiveCalls('abc', '');
        $extension = new PilotAskExtension($this->createStub(PilotAsk::class), $keys);

        $this->assertSame('pilot.abc', $extension->key());
        $this->assertSame('', $extension->key());
    }
}
