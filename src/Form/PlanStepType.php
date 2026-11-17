<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Enum\Executor;
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

// One step of a plan template: its title, who does it, and for a machine the action and its parameters, {variables} asked when the plan is created
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
            ->add('executor', ChoiceType::class, ['label' => 'label.step_executor', 'choices' => ['label.executor_human' => Executor::Human->value, 'label.executor_runner' => Executor::Runner->value]])
            ->add('action', ChoiceType::class, ['label' => 'label.step_action', 'choices' => $actions, 'required' => false, 'placeholder' => '', 'choice_translation_domain' => false])
            ->add('params', TextareaType::class, ['label' => 'label.step_params', 'help' => 'label.info_step_params', 'required' => false])
        ;
        $builder->get('params')->addModelTransformer(new CallbackTransformer($this->paramsToText(...), $this->textToParams(...)));
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $step): PlanStep => $step instanceof PlanStep ? $step : PlanStep::fromArray(\is_array($step) ? $step : []),
            static fn (mixed $step): array => $step instanceof PlanStep ? $step->toArray() : [],
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'pilot', 'data_class' => PlanStep::class, 'empty_data' => static fn (): PlanStep => new PlanStep()]);
    }

    // Parameters shown one "name=value" per line
    private function paramsToText(mixed $params): string
    {
        $lines = [];
        foreach (\is_array($params) ? $params : [] as $name => $value) {
            $lines[] = $name . '=' . $value;
        }

        return implode("\n", $lines);
    }

    // Parameters read back from their lines, a line without "=" or without a name dropped
    /** @return array<string, string> */
    private function textToParams(mixed $text): array
    {
        $params = [];
        foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
            if (null !== $value && 1 === preg_match('/^\w{1,30}$/', trim($name))) {
                $params[trim($name)] = trim($value);
            }
        }

        return $params;
    }
}
