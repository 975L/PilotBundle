<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Repository\ActionRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// One step of a plan template: its title, the group it belongs to on a card, the machine action it names and its parameters, {variables} asked when the plan is created, and the steps it waits for
class PlanStepType extends AbstractType
{
    public function __construct(
        private readonly ActionRepository $actionRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $actions = [];
        foreach ($this->actionRepository->findBy([], ['label' => 'ASC']) as $action) {
            $actions[$action->getLabel()] = $action->getKey();
        }
        $builder
            ->add('title', TextType::class, ['label' => 'label.step_title', 'constraints' => [new NotBlank(), new Length(max: 500)]])
            ->add('group', TextType::class, ['label' => 'label.step_group', 'help' => 'label.info_step_group', 'required' => false, 'empty_data' => '', 'constraints' => [new Length(max: 50)]])
            ->add('action', ChoiceType::class, ['label' => 'label.step_action', 'choices' => $actions, 'required' => false, 'placeholder' => '', 'choice_translation_domain' => false])
            ->add('params', TextareaType::class, ['label' => 'label.step_params', 'help' => 'label.info_step_params', 'required' => false])
            ->add('after', TextType::class, ['label' => 'label.step_after', 'help' => 'label.info_step_after', 'required' => false, 'empty_data' => ''])
        ;
        $builder->get('params')->addModelTransformer(new CallbackTransformer(static fn (mixed $params): string => PlanStep::paramsText(\is_array($params) ? $params : []), static fn (mixed $text): array => PlanStep::paramsFrom((string) $text)));
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $step): PlanStep => $step instanceof PlanStep ? $step : PlanStep::fromArray(\is_array($step) ? $step : []),
            static fn (mixed $step): array => $step instanceof PlanStep ? $step->toArray() : [],
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'pilot', 'data_class' => PlanStep::class, 'empty_data' => static fn (): PlanStep => new PlanStep()]);
    }
}
