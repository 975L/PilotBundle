<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Project;

// The order of the projects set by dragging them: the listed ones first in that order, the others after them in their own order
class ProjectOrder
{
    /**
     * @param list<Project> $projects all the projects, in their current order
     * @param list<int>     $ids      the new order, by id
     */
    public function apply(array $projects, array $ids): void
    {
        $byId = [];
        foreach ($projects as $project) {
            $byId[(int) $project->getId()] = $project;
        }
        $position = 0;
        foreach (array_unique($ids) as $id) {
            if (isset($byId[$id])) {
                $byId[$id]->setPosition($position++);
                unset($byId[$id]);
            }
        }
        foreach ($byId as $project) {
            $project->setPosition($position++);
        }
    }
}
