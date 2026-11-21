<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Security\PilotScope;
use Mcp\Server\Exception\ToolCallException;

// MCP tools acting with the rights of the agent token's account, as the piloting pages give them
trait AccountTools
{
    // The repository the spaces are found in
    abstract private function projects(): ProjectRepository;

    // The account's rights on the piloting
    abstract private function access(): PilotAccess;

    // What the account sees
    private function scope(): PilotScope
    {
        return $this->access()->scope();
    }

    // A space by its slug at the level asked: one the account does not see refused as unknown, one it may not change at that level said so
    private function space(string $slug, string $level = PilotAccess::VIEW): Project
    {
        $project = $this->projects()->findOneBy(['slug' => trim($slug)]);
        if (!$project instanceof Project || !$this->access()->can($project, PilotAccess::VIEW)) {
            throw new ToolCallException(sprintf('No space "%s".', $slug));
        }
        if (!$this->access()->can($project, $level)) {
            throw new ToolCallException(sprintf('This account may not do that in the space "%s".', $slug));
        }

        return $project;
    }

    // A free text trimmed, null when empty
    private function text(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }
}
