<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Service\PilotUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PilotUrlsTest extends TestCase
{
    // A card is addressed under its space
    public function testCard(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => $route . ' ' . implode('/', $parameters));
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        new \ReflectionProperty(Card::class, 'id')->setValue($card, 12);

        $this->assertSame('pilot_space_card catalog/12', new PilotUrls($urls)->card($card));
    }
}
