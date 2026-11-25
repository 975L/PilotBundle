<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A plan template's settings: name, the spaces offering it and the steps of its card added, removed and ordered
class PlanTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'label.template_name'])
            ->add('projects', EntityType::class, ['label' => 'label.projects', 'help' => 'label.info_template_projects', 'class' => Project::class, 'multiple' => true, 'expanded' => true, 'by_reference' => false])
            ->add('steps', CollectionType::class, ['label' => 'label.template_steps', 'entry_type' => PlanStepType::class, 'allow_add' => true, 'allow_delete' => true, 'prototype' => true, 'entry_options' => ['label' => false]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlanTemplate::class, 'translation_domain' => 'pilot', 'empty_data' => static fn (): PlanTemplate => new PlanTemplate('', [])]);
    }
}
