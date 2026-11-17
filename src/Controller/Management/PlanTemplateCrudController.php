<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Form\PlanStepType;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractCrudController<PlanTemplate>
 */
// The plan templates: their steps in order, each by hand or by a machine, a plan being created from one on a project's page
#[AdminRoute(path: '/pilot/settings/template', name: 'pilot_settings_template')]
class PlanTemplateCrudController extends AbstractCrudController
{
    use AdminActionsTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PlanTemplate::class;
    }

    #[\Override]
    public function createEntity(string $entityFqcn): PlanTemplate
    {
        return new PlanTemplate('', []);
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.template', [], 'pilot'))
            ->setEntityLabelInPlural(t('label.templates', [], 'pilot'))
            ->setEntityPermission((string) $this->configService->get('site-role-admin'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_templates', [], 'pilot'))
            ->showEntityActionsInlined()
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $this->adminActions($actions);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', t('label.template_name', [], 'pilot'));
        yield AssociationField::new('projects', t('label.projects', [], 'pilot'))->setHelp(t('label.info_template_projects', [], 'pilot'));
        yield CollectionField::new('steps', t('label.template_steps', [], 'pilot'))
            ->setEntryType(PlanStepType::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex()
            ->renderExpanded()
            ->onlyOnForms()
        ;
    }
}
