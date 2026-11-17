<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Project;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    // Emptied form fields reach the setters as null, kept as empty values for the validation to refuse
    public function testEmptiedFieldsAreKeptEmpty(): void
    {
        $project = new Project('catalog', 'Catalog')->setSlug(null)->setName(null)->setColor(null)->setPosition(null);

        $this->assertSame('', $project->getSlug());
        $this->assertSame('', $project->getName());
        $this->assertSame('', $project->getColor());
        $this->assertSame(0, $project->getPosition());
    }
}
