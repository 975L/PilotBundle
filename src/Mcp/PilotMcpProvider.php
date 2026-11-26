<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;

// The piloting's tools on the site's MCP endpoint, its cards and files, acting with the rights of the agent token's account
class PilotMcpProvider implements McpToolProviderInterface
{
    public function __construct(
        private readonly PilotTools $pilotTools,
        private readonly PilotDocTools $pilotDocTools,
        private readonly PilotCreditTools $pilotCreditTools,
        private readonly PilotSettingsTools $pilotSettingsTools,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'pilot';
    }

    public function getMcpInstructions(): string
    {
        return 'Cards are tasks grouped in spaces, each space in collections. List the spaces, then the cards of one, read a card before changing it, and give the user the card\'s url. To sort cards, edit their space, collection or day; to merge duplicates, read both and merge the newer into the older; delete only what the user asked to. A machine action always leaves from a step: name it and its parameters on a step with pilot_edit_step, run it by a machine of its space with pilot_run_step, read the card again to follow it. Files hold a short summary for a human and the detail for an agent. A card following a checklist template takes its steps from it: change the template with pilot_template_save, which lays them on its open cards, and offer a space the actions its steps name with pilot_action_offer.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('pilot_spaces', 'Spaces', 'The spaces the account sees, with their open cards counted.', $this->pilotTools->spaces(...));
        yield new McpTool('pilot_cards', 'Cards', 'Cards whose title or note holds the search words, else the open cards of a space, else every open card due today or late.', $this->pilotTools->cards(...));
        yield new McpTool('pilot_card', 'Card', 'A card as markdown, with the index of each step and comment.', $this->pilotTools->card(...));
        yield new McpTool('pilot_add_card', 'Add a card', 'Adds a card to a space, with an optional note and day (YYYY-MM-DD).', $this->pilotTools->addCard(...), true);
        yield new McpTool('pilot_tick_step', 'Tick a step', 'Ticks a step or a comment of a card by its index, or unticks it with done false; one already in that state is left as it is.', $this->pilotTools->tickStep(...), true);
        yield new McpTool('pilot_comment', 'Comment a card', 'Leaves a comment at the end of a card, a done card opening again.', $this->pilotTools->comment(...), true);
        yield new McpTool('pilot_edit_card', 'Edit a card', 'Changes only the fields given: title, note (empty clears it), day (YYYY-MM-DD, empty for the backlog), space (slug), collection (name, in the card\'s space once moved) and doc (slug of a file of its space, empty to detach it).', $this->pilotTools->editCard(...), true);
        yield new McpTool('pilot_add_steps', 'Add steps', 'Adds steps after the last one of a card, before its comments.', $this->pilotTools->addSteps(...), true);
        yield new McpTool('pilot_edit_step', 'Edit a step', 'Changes only what is given of a step: its text, the machine action it names (its key, empty for none) and that action\'s parameters (name to value, replacing the step\'s), so the step can then be run with pilot_run_step; a step laid by the checklist template the card follows is changed on the template only.', $this->pilotTools->editStep(...), true);
        yield new McpTool('pilot_close_card', 'Close a card', 'Marks a card done, or opens it again with done false.', $this->pilotTools->closeCard(...), true);
        yield new McpTool('pilot_run_step', 'Run a step', 'Queues the machine action a step names for a machine of its space; its run then shows on the step in pilot_card.', $this->pilotTools->runStep(...), true);
        yield new McpTool('pilot_cancel_run', 'Cancel a run', 'Withdraws a run queued or under way on a card, the run given (its id from pilot_run_step) or else the card\'s last; a machine holding it stops at its next sign of life.', $this->pilotTools->cancelRun(...), true);
        yield new McpTool('pilot_merge_cards', 'Merge two cards', 'Merges card into the card into: its note, steps and comments carried over, then card deleted.', $this->pilotTools->mergeCards(...), true, true);
        yield new McpTool('pilot_delete_card', 'Delete a card', 'Deletes a card permanently, with its runs and their files.', $this->pilotTools->deleteCard(...), true, true);
        yield new McpTool('pilot_docs', 'Files', 'Files whose title or text holds the search words, else those of a space, else every file the account sees.', $this->pilotDocTools->docs(...));
        yield new McpTool('pilot_doc', 'File', 'A file as markdown: title, summary, then detail.', $this->pilotDocTools->doc(...));
        yield new McpTool('pilot_write_doc', 'Write a file', 'Adds a file to a space (space and title), or rewrites the one named by doc, only the summary or detail given changing.', $this->pilotDocTools->writeDoc(...), true);
        yield new McpTool('pilot_templates', 'Templates', 'For the admin, the checklist templates, with the spaces offering them, their steps counted and the open cards following them.', $this->pilotSettingsTools->templates(...));
        yield new McpTool('pilot_template', 'Template', 'For the admin, a checklist template with its steps numbered from 1, as their « after » names them.', $this->pilotSettingsTools->template(...));
        yield new McpTool('pilot_template_save', 'Save a template', 'For the admin, makes a template (name and steps) or changes the one named in what is given only; its steps, given whole in their order, are laid on its open cards as the settings page does, each card keeping its ticks and its own steps.', $this->pilotSettingsTools->saveTemplate(...), true);
        yield new McpTool('pilot_actions', 'Machine actions', 'For the admin, the machine actions declared, with the spaces offering them and the machines running them.', $this->pilotSettingsTools->actions(...));
        yield new McpTool('pilot_action_offer', 'Offer an action', 'For the admin, the spaces offering a machine action, replacing those it had: a step of a space not offering it is not run.', $this->pilotSettingsTools->offerAction(...), true);
        yield new McpTool('pilot_machines', 'Machines', 'For the admin, the runner machines, with the spaces each serves (every one when none), the runs it takes at once, whether it is online and the actions it declared.', $this->pilotSettingsTools->machines(...));
        yield new McpTool('pilot_machine_edit', 'Edit a machine', 'For the admin, the runs a machine takes at once and the spaces it serves, only what is given changing; a machine is made, and its token given, on the settings page only.', $this->pilotSettingsTools->editMachine(...), true);
        yield new McpTool('pilot_collection_save', 'Save a collection', 'Adds a collection to a space, or changes the one named in what is given only: its name, its word for a card, the template a card added to it gets (among those the space offers, empty for none) and whether its cards show as sheets.', $this->pilotSettingsTools->saveCollection(...), true);
        yield new McpTool('pilot_credit_outlook', 'Credits outlook', 'For the admin, each credit the steps planned before its renewal leave unspent, or overspend, with its tone (fine, soon past half the month before the renewal, late in the last week or when overspent), the steps of later cards that could spend it (the readiest first) and the steps they still wait for; bring one forward by editing its card\'s day.', $this->pilotCreditTools->outlook(...));
        yield new McpTool('pilot_credit_set', 'Type in a credit', 'For the admin, the balance of a credit typed in by hand, as read on its service\'s own site (in a browser for a service without an API), with its next renewal day (YYYY-MM-DD) when given; a credit read from its service\'s API refused.', $this->pilotCreditTools->set(...), true);
    }
}
