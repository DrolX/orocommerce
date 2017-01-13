<?php

namespace Oro\Bundle\PayPalBundle\Method\Config\Provider;

use Oro\Bundle\PayPalBundle\Method\Config\PayPalExpressCheckoutConfig;
use Oro\Bundle\PayPalBundle\Method\Config\PayPalExpressCheckoutConfigInterface;

class PayPalExpressCheckoutConfigProvider extends AbstractPayPalConfigProvider implements
    PayPalExpressCheckoutConfigProviderInterface
{
    /**
     * @var array|PayPalExpressCheckoutConfigInterface[]
     */
    protected $configs = [];

    /**
     * @return array|PayPalExpressCheckoutConfigInterface[]
     */
    public function getPaymentConfigs()
    {
        if (0 === count($this->configs)) {
            $this->fillConfigs();
        }

        return $this->configs;
    }

    /**
     * @param string $identifier
     * @return PayPalExpressCheckoutConfigInterface|null
     */
    public function getPaymentConfig($identifier)
    {
        if (!$this->hasPaymentConfig($identifier)) {
            return null;
        }

        $configs = $this->getPaymentConfigs();

        return $configs[$identifier];
    }

    protected function fillConfigs()
    {
        $channels = $this->getEnabledIntegrationChannels();

        foreach ($channels as $channel) {
            $config = new PayPalExpressCheckoutConfig($channel, $this->encoder, $this->localizationHelper);
            $this->configs[$config->getPaymentMethodIdentifier()] = $config;
        }
    }
}
