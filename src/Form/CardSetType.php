<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\PlanTemplate;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A collection's settings, the collection itself made on its space's page: name, the word for one of its cards, the checklist template a new card gets, whether its cards show as sheets, and its place
class CardSetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'label.collection_name', 'empty_data' => ''])
            ->add('item', TextType::class, ['label' => 'label.collection_item', 'help' => 'label.info_collection_item', 'required' => false])
            ->add('template', EntityType::class, [
                'label' => 'label.template',
                'help' => 'label.info_collection_template',
                'class' => PlanTemplate::class,
                'required' => false,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('t')->where('t.checklist = true')->orderBy('t.name', 'ASC'),
            ])
            ->add('sheets', CheckboxType::class, ['label' => 'label.collection_sheets', 'help' => 'label.info_collection_sheets', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'label.project_position', 'empty_data' => '0'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CardSet::class, 'translation_domain' => 'pilot']);
    }
}
