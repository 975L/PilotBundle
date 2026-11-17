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
use c975L\PilotBundle\Entity\Action as PilotAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractCrudController<PilotAction>
 */
// The actions the machines declared: only the projects whose cards offer each one are set here, the rest comes from the machine
#[AdminRoute(path: '/pilot/settings/action', name: 'pilot_settings_action')]
class ActionCrudController extends AbstractCrudController
{
    use AdminActionsTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PilotAction::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.action', [], 'pilot'))
            ->setEntityLabelInPlural(t('label.actions', [], 'pilot'))
            ->setEntityPermission((string) $this->configService->get('site-role-admin'))
            ->setDefaultSort(['label' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_actions', [], 'pilot'))
            ->showEntityActionsInlined()
        ;
    }

    // No creation: an action exists once a machine declares it
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $this->adminActions($actions->disable(Action::NEW), [Action::INDEX, Action::EDIT, Action::DELETE]);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('label', t('label.action_label', [], 'pilot'))->setDisabled();
        yield TextField::new('key', t('label.action_key', [], 'pilot'))->setDisabled();
        yield ArrayField::new('params', t('label.action_params', [], 'pilot'))->hideOnForm();
        yield AssociationField::new('projects', t('label.projects', [], 'pilot'))->setHelp(t('label.info_action_projects', [], 'pilot'));
    }
}
