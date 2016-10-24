<?php

namespace Oro\Bundle\CustomerBundle\Tests\Functional\Visibility\Provider;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CustomerBundle\Entity\AccountGroup;
use Oro\Bundle\CustomerBundle\Entity\Visibility\VisibilityInterface;
use Oro\Bundle\CustomerBundle\Entity\VisibilityResolved\BaseVisibilityResolved;
use Oro\Bundle\CustomerBundle\Migrations\Data\ORM\LoadAnonymousAccountGroup;
use Oro\Bundle\CustomerBundle\Visibility\Provider\ProductVisibilityProvider;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\WebsiteBundle\Migrations\Data\ORM\LoadWebsiteData;

/**
 * @dbIsolation
 */
class ProductVisibilityProviderTest extends WebTestCase
{
    const PRODUCT_VISIBILITY_CONFIGURATION_PATH = 'oro_customer.product_visibility';
    const CATEGORY_VISIBILITY_CONFIGURATION_PATH = 'oro_customer.category_visibility';

    /**
     * @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configManager;

    /**
     * @var ProductVisibilityProvider
     */
    private $provider;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();

        $this->loadFixtures([
            'Oro\Bundle\WebsiteBundle\Tests\Functional\DataFixtures\LoadWebsiteData',
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadAccountUserData',
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCategoryVisibilityData',
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadProductVisibilityData',
        ]);

        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->provider = new ProductVisibilityProvider(
            $this->getContainer()->get('oro_entity.doctrine_helper'),
            $this->configManager
        );

        $this->getContainer()->get('oro_customer.visibility.cache.product.cache_builder')->buildCache();
    }

    /**
     * @return int
     */
    private function getAnonymousAccountGroupId()
    {
        $accountGroupRepository = $this->getContainer()
            ->get('doctrine')
            ->getManagerForClass('OroCustomerBundle:AccountGroup')
            ->getRepository('OroCustomerBundle:AccountGroup');

        /** @var AccountGroup $accountGroup */
        $accountGroup = $accountGroupRepository
            ->findOneBy(['name' => LoadAnonymousAccountGroup::GROUP_NAME_NON_AUTHENTICATED]);

        return $accountGroup->getId();
    }

    public function testGetAccountVisibilitiesForProducts()
    {
        $this->configManager
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [self::PRODUCT_VISIBILITY_CONFIGURATION_PATH],
                [self::CATEGORY_VISIBILITY_CONFIGURATION_PATH]
            )
            ->willReturnOnConsecutiveCalls(VisibilityInterface::HIDDEN, VisibilityInterface::HIDDEN);

        $this->provider->setProductVisibilitySystemConfigurationPath(static::PRODUCT_VISIBILITY_CONFIGURATION_PATH);
        $this->provider->setCategoryVisibilitySystemConfigurationPath(static::CATEGORY_VISIBILITY_CONFIGURATION_PATH);

        $expectedAccountsVisibilities = [
            [
                'productId' => $this->getReference('product.1')->getId(),
                'accountId' => $this->getReference('account.level_1')->getId(),
            ],
            [
                'productId' => $this->getReference('product.4')->getId(),
                'accountId' => $this->getReference('account.orphan')->getId(),
            ],
        ];

        $this->assertEquals(
            $expectedAccountsVisibilities,
            $this->provider->getAccountVisibilitiesForProducts(
                [
                    $this->getReference('product.1'),
                    $this->getReference('product.4'),
                ],
                $this->getDefaultWebsiteId()
            )
        );
    }

    /**
     * @return int
     */
    private function getDefaultWebsiteId()
    {
        return $this->getContainer()
            ->get('doctrine')
            ->getManagerForClass('OroWebsiteBundle:Website')
            ->getRepository('OroWebsiteBundle:Website')
            ->findOneBy(['name' => LoadWebsiteData::DEFAULT_WEBSITE_NAME])
            ->getId();
    }

    public function testGetNewUserAndAnonymousVisibilitiesForProducts()
    {
        $this->configManager
            ->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive(
                [self::PRODUCT_VISIBILITY_CONFIGURATION_PATH],
                [self::CATEGORY_VISIBILITY_CONFIGURATION_PATH],
                ['oro_customer.anonymous_account_group']
            )
            ->willReturnOnConsecutiveCalls(
                VisibilityInterface::HIDDEN,
                VisibilityInterface::HIDDEN,
                $this->getAnonymousAccountGroupId()
            );

        $this->provider->setProductVisibilitySystemConfigurationPath(static::PRODUCT_VISIBILITY_CONFIGURATION_PATH);
        $this->provider->setCategoryVisibilitySystemConfigurationPath(static::CATEGORY_VISIBILITY_CONFIGURATION_PATH);

        $expectedVisibilities = [
            [
                'productId' => $this->getReference('product.3')->getId(),
                'visibility_new' => BaseVisibilityResolved::VISIBILITY_VISIBLE,
                'visibility_anonymous' => BaseVisibilityResolved::VISIBILITY_VISIBLE,
                'is_visible_by_default' => BaseVisibilityResolved::VISIBILITY_HIDDEN
            ],
            [
                'productId' => $this->getReference('product.5')->getId(),
                'visibility_new' => BaseVisibilityResolved::VISIBILITY_HIDDEN,
                'visibility_anonymous' => BaseVisibilityResolved::VISIBILITY_VISIBLE,
                'is_visible_by_default' => BaseVisibilityResolved::VISIBILITY_HIDDEN
            ],
        ];

        $this->assertEquals(
            $expectedVisibilities,
            $this->provider->getNewUserAndAnonymousVisibilitiesForProducts([
                $this->getReference('product.3'),
                $this->getReference('product.5'),
            ], $this->getDefaultWebsiteId())
        );
    }
}