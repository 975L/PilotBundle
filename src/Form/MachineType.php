<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A runner machine's settings: its name and the spaces it serves (every one when none is chosen), its token drawn when it is created
class MachineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'label.machine_name'])
            ->add('projects', EntityType::class, ['label' => 'label.projects', 'help' => 'label.info_machine_projects', 'class' => Project::class, 'multiple' => true, 'expanded' => true, 'by_reference' => false, 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Machine::class, 'translation_domain' => 'pilot', 'empty_data' => static fn (): Machine => new Machine('', '')]);
    }
}
