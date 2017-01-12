<?php

namespace Oro\Bundle\PayPalBundle\Method\Config;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\PayPalBundle\DependencyInjection\Configuration;
use Oro\Bundle\PayPalBundle\DependencyInjection\OroPayPalExtension;
use Oro\Bundle\PayPalBundle\PayPal\Payflow\Option;
use Oro\Bundle\PaymentBundle\Method\Config\AbstractPaymentConfig;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;

class PayPalExpressCheckoutConfig extends AbstractPaymentConfig implements
    PayPalExpressCheckoutConfigInterface
{
    const TYPE = 'express_checkout';

    /**
     * @var SymmetricCrypterInterface
     */
    protected $encoder;

    /**
     * @param Channel $channel
     * @param SymmetricCrypterInterface $encoder
     */
    public function __construct(Channel $channel, SymmetricCrypterInterface $encoder)
    {
        parent::__construct($channel);

        $this->encoder = $encoder;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentExtensionAlias()
    {
        return OroPayPalExtension::ALIAS;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return [
            Option\Partner::PARTNER => $this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_PARTNER_KEY),
            Option\Vendor::VENDOR => $this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_VENDOR_KEY),
            Option\User::USER => $this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_USER_KEY),
            Option\Password::PASSWORD =>
                $this->encoder->encryptData($this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_PASSWORD_KEY))
            ,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPurchaseAction()
    {
        return (string)$this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_EXPRESS_CHECKOUT_PAYMENT_ACTION_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function isTestMode()
    {
        return (bool)$this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_EXPRESS_CHECKOUT_TEST_MODE_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return (string)$this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_EXPRESS_CHECKOUT_LABEL_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function getShortLabel()
    {
        return (string)$this->getConfigValue(Configuration::PAYPAL_PAYMENTS_PRO_EXPRESS_CHECKOUT_SHORT_LABEL_KEY);
    }

    /** {@inheritdoc} */
    public function getAdminLabel()
    {
        return (string)$this->getLabel();
    }

    /** {@inheritdoc} */
    public function getPaymentMethodIdentifier()
    {
        return $this->channel->getType() . '_' . self::TYPE . '_' . $this->channel->getId();
    }
}
