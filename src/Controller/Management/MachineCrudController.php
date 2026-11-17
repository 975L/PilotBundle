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
use c975L\PilotBundle\Entity\Machine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractCrudController<Machine>
 */
// The runner machines: a new one gets its token shown once, a lost token is replaced by deleting the machine and adding it again
#[AdminRoute(path: '/pilot/settings/machine', name: 'pilot_settings_machine')]
class MachineCrudController extends AbstractCrudController
{
    use AdminActionsTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Machine::class;
    }

    #[\Override]
    public function createEntity(string $entityFqcn): Machine
    {
        return new Machine('', '');
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.machine', [], 'pilot'))
            ->setEntityLabelInPlural(t('label.machines', [], 'pilot'))
            ->setEntityPermission((string) $this->configService->get('site-role-admin'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_machines', [], 'pilot'))
            ->showEntityActionsInlined()
        ;
    }

    // No edit: a machine is its name and its token
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $this->adminActions($actions->disable(Action::EDIT), [Action::INDEX, Action::NEW, Action::DELETE], [Action::DELETE]);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', t('label.machine_name', [], 'pilot'));
        yield DateTimeField::new('lastSeenAt', t('label.machine_seen', [], 'pilot'))->hideOnForm();
        yield TextField::new('declaredActions', t('label.actions', [], 'pilot'))->hideOnForm();
    }

    // Draws the token on creation and shows it once, only its hash being saved
    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $token = $entityInstance->newToken();
        parent::persistEntity($entityManager, $entityInstance);
        $this->addFlash('success', $this->translator->trans('flash.machine_created', ['%token%' => $token], 'pilot'));
    }
}
