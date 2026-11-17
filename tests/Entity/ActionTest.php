<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Project;
use PHPUnit\Framework\TestCase;

class ActionTest extends TestCase
{
    // A declared action is offered nowhere until a project is chosen for it, and a new declaration keeps its projects
    public function testOfferedOnlyToItsProjects(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $action = new Action('build')->declare('Build', ['item']);

        $this->assertFalse($action->isOfferedTo($catalog));
        $action->addProject($catalog)->declare('Build again', ['item', 'mode']);

        $this->assertTrue($action->isOfferedTo($catalog));
        $this->assertSame('Build again', $action->getLabel());
        $this->assertSame(['item', 'mode'], $action->getParams());
    }

    // An action declared as a card's own is never a button of the project's page, and a new declaration can change that
    public function testCardOnlyFollowsTheDeclaration(): void
    {
        $action = new Action('study')->declare('Study', [], true);
        $this->assertTrue($action->isCardOnly());
        $this->assertFalse($action->declare('Study', [])->isCardOnly());
        $action->declare('Study', [], true, 'Writes the file', 3);
        $this->assertSame('Writes the file', $action->getHelp());
        $this->assertSame(3, $action->getPosition());
    }
}
