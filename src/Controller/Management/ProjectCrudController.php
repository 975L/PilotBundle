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
use c975L\PilotBundle\Entity\Project;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractCrudController<Project>
 */
// The projects: name, colour, sites and place in the column of the piloting pages
#[AdminRoute(path: '/pilot/settings/project', name: 'pilot_settings_project')]
class ProjectCrudController extends AbstractCrudController
{
    use AdminActionsTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    #[\Override]
    public function createEntity(string $entityFqcn): Project
    {
        return new Project('', '');
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.project', [], 'pilot'))
            ->setEntityLabelInPlural(t('label.projects', [], 'pilot'))
            ->setEntityPermission((string) $this->configService->get('site-role-admin'))
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_projects', [], 'pilot'))
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
        yield TextField::new('name', t('label.project_name', [], 'pilot'));
        yield TextField::new('slug', t('label.project_slug', [], 'pilot'))->setHelp(t('label.info_project_slug', [], 'pilot'));
        yield ColorField::new('color', t('label.project_color', [], 'pilot'))->setTemplatePath('@c975LPilot/management/field_color.html.twig');
        yield ArrayField::new('sites', t('label.project_sites', [], 'pilot'))->hideOnIndex();
        yield IntegerField::new('position', t('label.project_position', [], 'pilot'));
    }
}
