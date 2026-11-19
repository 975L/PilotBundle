<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A space's settings: name, address, colour, sites and place; the word for a card and the template live on its collections
class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'label.project_name'])
            ->add('slug', TextType::class, ['label' => 'label.project_slug', 'help' => 'label.info_project_slug'])
            ->add('color', ColorType::class, ['label' => 'label.project_color'])
            ->add('sites', TextareaType::class, ['label' => 'label.project_sites', 'help' => 'label.info_project_sites', 'required' => false, 'empty_data' => ''])
            ->add('position', IntegerType::class, ['label' => 'label.project_position', 'empty_data' => '0'])
        ;
        // One site per line
        $builder->get('sites')->addModelTransformer(new CallbackTransformer(
            static fn (mixed $sites): string => implode("\n", \is_array($sites) ? $sites : []),
            static fn (mixed $text): array => array_values(array_filter(array_map(trim(...), preg_split('/\R/', (string) $text) ?: []), static fn (string $site): bool => '' !== $site)),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Project::class, 'translation_domain' => 'pilot', 'empty_data' => static fn (): Project => new Project('', '')]);
    }
}
