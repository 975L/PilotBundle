<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Service\PilotUrls;
use c975L\PilotBundle\Twig\PilotUrlExtension;
use PHPUnit\Framework\TestCase;

// A path a step names becomes a link of the scheme the site sets, its segments encoded, and stays text without one, with none or with a scheme of the web
class PilotUrlExtensionTest extends TestCase
{
    public function testOpenLinkFollowsTheScheme(): void
    {
        $this->assertSame('item-1:///catalog/Item%20%C3%A9t%C3%A9/01.jpg', $this->extension('item-1')->open('/catalog/Item été/01.jpg'));
        $this->assertNull($this->extension('')->open('/catalog/01.jpg'));
        $this->assertNull($this->extension('none')->open('/catalog/01.jpg'));
        $this->assertNull($this->extension('javascript')->open('/catalog/01.jpg'));
        $this->assertNull($this->extension('item 1')->open('/catalog/01.jpg'));
    }

    // The extension on a site whose opening scheme is the given one
    private function extension(string $scheme): PilotUrlExtension
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn($scheme);

        return new PilotUrlExtension($this->createStub(PilotUrls::class), $config);
    }
}
