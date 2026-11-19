<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Form\ActionType;
use c975L\PilotBundle\Form\CardSetType;
use c975L\PilotBundle\Form\CreditType;
use c975L\PilotBundle\Form\MachineType;
use c975L\PilotBundle\Form\PlanTemplateType;
use c975L\PilotBundle\Form\ProjectType;

// What each kind of setting is (spaces, their collections, templates, actions, machines, credits): its entity, its form, its words, the columns its list shows, its order, and whether a row is made or edited here
class SettingsKinds
{
    private const array KINDS = [
        'space' => [
            'class' => Project::class,
            'form' => ProjectType::class,
            'label' => 'label.project',
            'title' => 'label.projects',
            'description' => 'label.info_projects',
            'icon' => 'fa-folder',
            'columns' => [['property' => 'name', 'label' => 'label.project_name'], ['property' => 'slug', 'label' => 'label.project_slug'], ['property' => 'position', 'label' => 'label.project_position']],
            'translation_domain' => 'pilot',
            'sort' => ['position' => 'ASC', 'name' => 'ASC'],
            'new' => true,
            'edit' => true,
        ],
        'collection' => [
            'class' => CardSet::class,
            'form' => CardSetType::class,
            'label' => 'label.card_collection',
            'title' => 'label.collections',
            'description' => 'label.info_collections',
            'icon' => 'fa-layer-group',
            'columns' => [['property' => 'project', 'label' => 'label.project'], ['property' => 'name', 'label' => 'label.collection_name'], ['property' => 'item', 'label' => 'label.collection_item'], ['property' => 'template', 'label' => 'label.template'], ['property' => 'position', 'label' => 'label.project_position']],
            'translation_domain' => 'pilot',
            'sort' => ['project' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'],
            'new' => false,
            'edit' => true,
        ],
        'template' => [
            'class' => PlanTemplate::class,
            'form' => PlanTemplateType::class,
            'label' => 'label.template',
            'title' => 'label.templates',
            'description' => 'label.info_templates',
            'icon' => 'fa-list-ol',
            'columns' => [['property' => 'name', 'label' => 'label.template_name'], ['property' => 'checklist', 'label' => 'label.template_checklist'], ['property' => 'projects', 'label' => 'label.projects']],
            'translation_domain' => 'pilot',
            'sort' => ['name' => 'ASC'],
            'new' => true,
            'edit' => true,
        ],
        'action' => [
            'class' => Action::class,
            'form' => ActionType::class,
            'label' => 'label.action',
            'title' => 'label.actions',
            'description' => 'label.info_actions',
            'icon' => 'fa-bolt',
            'columns' => [['property' => 'label', 'label' => 'label.action_label'], ['property' => 'key', 'label' => 'label.action_key'], ['property' => 'params', 'label' => 'label.action_params'], ['property' => 'projects', 'label' => 'label.projects']],
            'translation_domain' => 'pilot',
            'sort' => ['position' => 'ASC', 'label' => 'ASC'],
            'new' => false,
            'edit' => true,
        ],
        'machine' => [
            'class' => Machine::class,
            'form' => MachineType::class,
            'label' => 'label.machine',
            'title' => 'label.machines',
            'description' => 'label.info_machines',
            'icon' => 'fa-desktop',
            'columns' => [['property' => 'name', 'label' => 'label.machine_name'], ['property' => 'projects', 'label' => 'label.projects'], ['property' => 'lastSeenAt', 'label' => 'label.machine_seen'], ['property' => 'declaredActions', 'label' => 'label.actions']],
            'translation_domain' => 'pilot',
            'sort' => ['name' => 'ASC'],
            'new' => true,
            'edit' => true,
        ],
        'credit' => [
            'class' => Credit::class,
            'form' => CreditType::class,
            'label' => 'label.credit',
            'title' => 'label.credits',
            'description' => 'label.info_credits',
            'icon' => 'fa-coins',
            'columns' => [['property' => 'label', 'label' => 'label.credit_label'], ['property' => 'remaining', 'label' => 'label.credit_remaining'], ['property' => 'total', 'label' => 'label.credit_total'], ['property' => 'monthly', 'label' => 'label.credit_monthly'], ['property' => 'unit', 'label' => 'label.credit_unit'], ['property' => 'manual', 'label' => 'label.credit_manual'], ['property' => 'updatedAt', 'label' => 'label.credit_updated']],
            'translation_domain' => 'pilot',
            'sort' => ['position' => 'ASC', 'label' => 'ASC'],
            'new' => true,
            'edit' => true,
        ],
    ];

    // Every kind, in the order the settings show them
    /** @return array<string, array{class: class-string, form: class-string, label: string, title: string, description: string, icon: string, columns: list<array{property: string, label: string}>, translation_domain: string, sort: array<string, string>, new: bool, edit: bool}> */
    public function all(): array
    {
        return self::KINDS;
    }

    // One kind, the routes only reaching the known ones
    /** @return array{class: class-string, form: class-string, label: string, title: string, description: string, icon: string, columns: list<array{property: string, label: string}>, translation_domain: string, sort: array<string, string>, new: bool, edit: bool} */
    public function get(string $kind): array
    {
        return self::KINDS[$kind] ?? throw new \InvalidArgumentException($kind);
    }
}
