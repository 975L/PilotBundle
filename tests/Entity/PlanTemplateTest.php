<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use PHPUnit\Framework\TestCase;

class PlanTemplateTest extends TestCase
{
    // A template is offered on no project until one is chosen for it, and only on the chosen ones
    public function testOfferedOnlyToItsProjects(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $other = new Project('other', 'Other');
        $template = new PlanTemplate('item-1', []);

        $this->assertFalse($template->isOfferedTo($catalog));
        $template->addProject($catalog)->addProject($catalog);

        $this->assertTrue($template->isOfferedTo($catalog));
        $this->assertFalse($template->isOfferedTo($other));
        $this->assertCount(1, $template->getProjects());
        $template->removeProject($catalog);
        $this->assertFalse($template->isOfferedTo($catalog));
    }

    // An emptied name reaches the setter as null, kept empty for the validation to refuse
    public function testEmptiedNameIsKeptEmpty(): void
    {
        $this->assertSame('', new PlanTemplate('item-1', [])->setName(null)->getName());
    }
}
