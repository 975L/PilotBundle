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
use c975L\PilotBundle\Entity\Credit;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractCrudController<Credit>
 */
// The paid services' balances shown on the planning: the ones a machine reports appear on their own, a service without an API is added here and its balance typed in
#[AdminRoute(path: '/pilot/settings/credit', name: 'pilot_settings_credit')]
class CreditCrudController extends AbstractCrudController
{
    use AdminActionsTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Credit::class;
    }

    #[\Override]
    public function createEntity(string $entityFqcn): Credit
    {
        return new Credit('');
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.credit', [], 'pilot'))
            ->setEntityLabelInPlural(t('label.credits', [], 'pilot'))
            ->setEntityPermission((string) $this->configService->get('site-role-admin'))
            ->setDefaultSort(['position' => 'ASC', 'label' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_credits', [], 'pilot'))
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
        yield TextField::new('label', t('label.credit_label', [], 'pilot'));
        yield TextField::new('key', t('label.credit_key', [], 'pilot'))->hideOnIndex()->setHelp(t('label.info_credit_key', [], 'pilot'));
        yield IntegerField::new('remaining', t('label.credit_remaining', [], 'pilot'));
        yield IntegerField::new('total', t('label.credit_total', [], 'pilot'));
        yield IntegerField::new('monthly', t('label.credit_monthly', [], 'pilot'))->setHelp(t('label.info_credit_monthly', [], 'pilot'));
        yield TextField::new('unit', t('label.credit_unit', [], 'pilot'));
        yield DateField::new('renewsOn', t('label.credit_renews', [], 'pilot'));
        yield TextField::new('note', t('label.credit_note', [], 'pilot'))->hideOnIndex();
        yield BooleanField::new('manual', t('label.credit_manual', [], 'pilot'))->renderAsSwitch(false)->setHelp(t('label.info_credit_manual', [], 'pilot'));
        yield IntegerField::new('position', t('label.project_position', [], 'pilot'))->hideOnIndex();
        yield DateTimeField::new('updatedAt', t('label.credit_updated', [], 'pilot'))->hideOnForm();
    }
}
