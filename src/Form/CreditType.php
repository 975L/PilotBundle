<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Service\CreditRefresher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A paid service's balance: label, key, the service read and its account's API key, what is left, the total, the monthly budget, its unit, renewal, note
class CreditType extends AbstractType
{
    public function __construct(
        private readonly CreditRefresher $creditRefresher,
        private readonly VaultEncryptor $vaultEncryptor,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'label.credit_label'])
            ->add('key', TextType::class, ['label' => 'label.credit_key', 'help' => 'label.info_credit_key'])
            ->add('provider', ChoiceType::class, ['label' => 'label.credit_provider', 'help' => 'label.info_credit_provider', 'required' => false, 'placeholder' => 'label.credit_by_hand', 'choices' => array_flip($this->creditRefresher->providers())])
            ->add('account', TextType::class, ['label' => 'label.credit_account', 'help' => 'label.info_credit_account', 'required' => false])
            ->add('apiKey', PasswordType::class, ['label' => 'label.credit_api_key', 'help' => 'label.info_credit_api_key', 'required' => false, 'mapped' => false, 'attr' => ['autocomplete' => 'new-password']])
            ->add('remaining', IntegerType::class, ['label' => 'label.credit_remaining', 'required' => false])
            ->add('total', IntegerType::class, ['label' => 'label.credit_total', 'required' => false])
            ->add('monthly', IntegerType::class, ['label' => 'label.credit_monthly', 'help' => 'label.info_credit_monthly', 'required' => false])
            ->add('unit', TextType::class, ['label' => 'label.credit_unit', 'required' => false])
            ->add('renewsOn', DateType::class, ['label' => 'label.credit_renews', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('note', TextType::class, ['label' => 'label.credit_note', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'label.project_position', 'empty_data' => '0'])
        ;

        // The key never shown back, stored encrypted when one is typed; left empty it stays as it was, taken off with the service when the credit goes back to a balance typed in by hand
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $credit = $event->getData();
            $typed = trim((string) $event->getForm()->get('apiKey')->getData());
            if (!$credit instanceof Credit) {
                return;
            }
            if ('' !== $typed) {
                $credit->setApiKey($this->vaultEncryptor->encrypt($typed));
            } elseif (null === $credit->getProvider()) {
                $credit->setApiKey(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Credit::class, 'translation_domain' => 'pilot', 'empty_data' => static fn (): Credit => new Credit('')]);
    }
}
