<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A machine action's settings: the spaces offering it, its label, key and parameters being what the machine declares
class ActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('projects', EntityType::class, ['label' => 'label.projects', 'help' => 'label.info_action_projects', 'class' => Project::class, 'multiple' => true, 'expanded' => true, 'by_reference' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Action::class, 'translation_domain' => 'pilot']);
    }
}
