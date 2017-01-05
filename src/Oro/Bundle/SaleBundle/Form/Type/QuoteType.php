<?php

namespace Oro\Bundle\SaleBundle\Form\Type;

use Oro\Bundle\AddressBundle\Entity\AddressType;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfig;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\CurrencyBundle\Form\Type\PriceType;
use Oro\Bundle\CustomerBundle\Form\Type\AccountSelectType;
use Oro\Bundle\CustomerBundle\Form\Type\AccountUserMultiSelectType;
use Oro\Bundle\CustomerBundle\Form\Type\AccountUserSelectType;
use Oro\Bundle\FormBundle\Form\Type\OroDateTimeType;
use Oro\Bundle\FormBundle\Form\Type\OroDateType;
use Oro\Bundle\OrderBundle\EventListener\Order\OrderPossibleShippingMethodsEventListener;
use Oro\Bundle\SaleBundle\Entity\Quote;
use Oro\Bundle\SaleBundle\Provider\QuoteAddressSecurityProvider;
use Oro\Bundle\UserBundle\Form\Type\UserMultiSelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuoteType extends AbstractType
{
    const NAME = 'oro_sale_quote';

    /** @var QuoteAddressSecurityProvider */
    protected $quoteAddressSecurityProvider;

    /** @var string */
    protected $dataClass;

    /** @var ConfigManager */
    protected $configManager;

    /**
     * @param QuoteAddressSecurityProvider $quoteAddressSecurityProvider
     * @param ConfigManager $configManager
     */
    public function __construct(
        QuoteAddressSecurityProvider $quoteAddressSecurityProvider,
        ConfigManager $configManager
    ) {
        $this->quoteAddressSecurityProvider = $quoteAddressSecurityProvider;
        $this->configManager = $configManager;
    }

    /**
     * @param string $dataClass
     */
    public function setDataClass($dataClass)
    {
        $this->dataClass = $dataClass;
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Quote $quote */
        $quote = $options['data'];
        $defaultCurrency = $this->configManager->get(CurrencyConfig::getConfigKeyByName(
            CurrencyConfig::KEY_DEFAULT_CURRENCY
        ));
        $quote->setCurrency($defaultCurrency);

        $builder
            ->add('qid', HiddenType::class)
            ->add('owner', 'oro_user_select', [
                'label'     => 'oro.sale.quote.owner.label',
                'required'  => true
            ])
            ->add('accountUser', AccountUserSelectType::NAME, [
                'label'     => 'oro.sale.quote.account_user.label',
                'required'  => false
            ])
            ->add('account', AccountSelectType::NAME, [
                'label'     => 'oro.sale.quote.account.label',
                'required'  => false
            ])
            ->add('validUntil', OroDateTimeType::class, [
                'label'     => 'oro.sale.quote.valid_until.label',
                'required'  => false
            ])
            ->add('locked', CheckboxType::class, [
                'label' => 'oro.sale.quote.locked.label',
                'required'  => false
            ])
            ->add('shippingMethodLocked', CheckboxType::class, [
                'label' => 'oro.sale.quote.shipping_method_locked.label',
                'required'  => false
            ])
            ->add('allowUnlistedShippingMethod', CheckboxType::class, [
                'label' => 'oro.sale.quote.allow_unlisted_shipping_method.label',
                'required'  => false
            ])
            ->add('poNumber', TextType::class, [
                'required' => false,
                'label' => 'oro.sale.quote.po_number.label'
            ])
            ->add('shipUntil', OroDateType::class, [
                'required' => false,
                'label' => 'oro.sale.quote.ship_until.label'
            ])
            ->add(
                'quoteProducts',
                QuoteProductCollectionType::class,
                [
                    'add_label' => 'oro.sale.quoteproduct.add_label',
                    'options' => [
                        'compact_units' => true,
                    ]
                ]
            )
            ->add('assignedUsers', UserMultiSelectType::NAME, [
                'label' => 'oro.sale.quote.assigned_users.label',
            ])
            ->add('assignedAccountUsers', AccountUserMultiSelectType::NAME, [
                'label' => 'oro.sale.quote.assigned_account_users.label',
            ]);
        $this->addShippingFields($builder, $quote);

        if ($this->quoteAddressSecurityProvider->isAddressGranted($quote, AddressType::TYPE_SHIPPING)) {
            $builder
                ->add(
                    'shippingAddress',
                    QuoteAddressType::class,
                    [
                        'label' => 'oro.sale.quote.shipping_address.label',
                        'quote' => $options['data'],
                        'required' => false,
                        'addressType' => AddressType::TYPE_SHIPPING,
                    ]
                );
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param Quote $quote
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function addShippingFields(FormBuilderInterface $builder, Quote $quote)
    {
        $builder
            ->add(OrderPossibleShippingMethodsEventListener::CALCULATE_SHIPPING_KEY, HiddenType::class, [
                'mapped' => false
            ])
            ->add('shippingMethod', HiddenType::class)
            ->add('shippingMethodType', HiddenType::class)
            ->add('estimatedShippingCostAmount', HiddenType::class)
            ->add('overriddenShippingCostAmount', PriceType::class, [
                'required' => false,
                'validation_groups' => ['Optional'],
                'hide_currency' => true,
            ])
            ->get('overriddenShippingCostAmount')->addModelTransformer(new CallbackTransformer(
                function ($amount) use ($quote) {
                    return $amount ? Price::create($amount, $quote->getCurrency()) : null;
                },
                function ($price) {
                    return $price instanceof Price ? $price->getValue() : $price;
                }
            ))
        ;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws AccessException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => $this->dataClass,
            'intention'     => 'sale_quote',
            'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}"'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }
}
