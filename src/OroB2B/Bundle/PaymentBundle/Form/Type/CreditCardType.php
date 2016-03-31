<?php

namespace OroB2B\Bundle\PaymentBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

use OroB2B\Bundle\ValidationBundle\Validator\Constraints\Integer;

class CreditCardType extends AbstractType
{
    const NAME = 'orob2b_payment_credit_card';
    const CONFIG_NAME = 'paypal_payments_pro';

    /** @var ConfigManager */
    protected $configManager;

    /**
     * @param ConfigManager $configManager
     */
    public function __construct(
        ConfigManager $configManager
    ) {
        $this->configManager = $configManager;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'ACCT',
                'text',
                [
                    'required' => true,
                    'label' => 'orob2b.payment.credit_card.card_number.label',
                    'mapped' => false,
                    'attr' => [
                        'data-validation' => [
                            'creditCardNumberLuhnCheck' => [
                                'message' => 'Invalid card number.',
                                'payload' => null
                            ]
                        ]
                    ],
                    'constraints' => [
                        new Integer(),
                        new NotBlank(),
                    ]
                ]
            )
            ->add(
                'expirationDate',
                'orob2b_payment_credit_card_expiration_date',
                [
                    'required' => true,
                    'label' => 'orob2b.payment.credit_card.expiration_date.label',
                    'mapped' => false,
                    'placeholder' => [
                        'year' => 'Year',
                        'month' => 'Month'
                    ],
                    'constraints' => [
                        new NotBlank()
                    ]
                ]
            )
            ->add(
                'EXPDATE',
                'hidden'
            )
            ->add(
                'CVV2',
                'password',
                [
                    'required' => true,
                    'label' => 'orob2b.payment.credit_card.cvv2.label',
                    'mapped' => false,
                    'block_name' => 'payment_credit_card_cvv',
                    'constraints' => [
                        new Integer(),
                        new NotBlank(),
                        new Length(['max' => 3, 'min' => 3])
                    ]
                ]
            );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $label = $this->configManager->get('orob2b_payment.' . self::CONFIG_NAME . '_label');

        $resolver->setDefaults(
            [
                'label' => empty($label) ? 'orob2b.payment.methods.credit_card.label' : $label
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        foreach ($view->children as $child) {
            $child->vars['full_name'] = $child->vars['name'];
        }

        $view->vars['method_enabled'] = $this->configManager->get('orob2b_payment.' . self::CONFIG_NAME . '_enabled');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }
}
