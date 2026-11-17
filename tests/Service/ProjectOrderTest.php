<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Service\ProjectOrder;
use PHPUnit\Framework\TestCase;

class ProjectOrderTest extends TestCase
{
    // The dragged order comes first, a project missing from it keeps its place after them, an unknown or repeated id changes nothing
    public function testApplyPutsTheDraggedOrderFirst(): void
    {
        $catalog = $this->project(1, 'catalog');
        $item = $this->project(2, 'item-1');
        $other = $this->project(3, 'other');

        new ProjectOrder()->apply([$catalog, $item, $other], [3, 99, 1, 3]);

        $this->assertSame(0, $other->getPosition());
        $this->assertSame(1, $catalog->getPosition());
        $this->assertSame(2, $item->getPosition());
    }

    private function project(int $id, string $slug): Project
    {
        $project = new Project($slug, $slug);
        new \ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }
}
