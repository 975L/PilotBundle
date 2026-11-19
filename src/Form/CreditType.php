<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\Credit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A paid service's balance: label, key, what is left, the total, the monthly budget, its unit, renewal, note, typed in by hand or read by a machine
class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'label.credit_label'])
            ->add('key', TextType::class, ['label' => 'label.credit_key', 'help' => 'label.info_credit_key'])
            ->add('remaining', IntegerType::class, ['label' => 'label.credit_remaining', 'required' => false])
            ->add('total', IntegerType::class, ['label' => 'label.credit_total', 'required' => false])
            ->add('monthly', IntegerType::class, ['label' => 'label.credit_monthly', 'help' => 'label.info_credit_monthly', 'required' => false])
            ->add('unit', TextType::class, ['label' => 'label.credit_unit', 'required' => false])
            ->add('renewsOn', DateType::class, ['label' => 'label.credit_renews', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('note', TextType::class, ['label' => 'label.credit_note', 'required' => false])
            ->add('manual', CheckboxType::class, ['label' => 'label.credit_manual', 'help' => 'label.info_credit_manual', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'label.project_position', 'empty_data' => '0'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Credit::class, 'translation_domain' => 'pilot', 'empty_data' => static fn (): Credit => new Credit('')]);
    }
}
