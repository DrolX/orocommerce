<?php

namespace Oro\Bundle\CustomerBundle\Tests\Unit\Datagrid\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\CustomerBundle\Datagrid\Extension\AccountUserExtension;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;

class AccountUserExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AccountUserExtension
     */
    protected $extension;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ContainerInterface
     */
    protected $container;

    protected function setUp()
    {
        $this->container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $this->extension = new AccountUserExtension();
        $this->extension->setContainer($this->container);
    }

    protected function tearDown()
    {
        unset($this->extension, $this->container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage ContainerInterface not injected
     */
    public function testIsApplicableWithoutContainer()
    {
        $extension = new AccountUserExtension();
        $extension->isApplicable(DatagridConfiguration::create([]));
    }

    /**
     * @param mixed $user
     * @param string $class
     * @param bool $expected
     *
     * @dataProvider applicableDataProvider
     */
    public function testIsApplicable($user, $class, $expected)
    {
        $this->container->expects($this->once())->method('getParameter')
            ->with('oro_customer.entity.account_user.class')->willReturn($class);

        $securityFacade = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()->getMock();
        $securityFacade->expects($this->once())->method('getLoggedUser')->willReturn($user);
        $this->container->expects($this->once())->method('get')->with('oro_security.security_facade')
            ->willReturn($securityFacade);

        $this->assertEquals($expected, $this->extension->isApplicable(DatagridConfiguration::create([])));
    }

    public function testProcessConfigs()
    {
        $config = DatagridConfiguration::create([]);
        $this->extension->processConfigs($config);

        $this->assertEquals(AccountUserExtension::ROUTE, $config->offsetGetByPath('[options][route]'));
    }

    /**
     * @return array
     */
    public function applicableDataProvider()
    {
        return [
            [new User(), 'Oro\Bundle\CustomerBundle\Entity\AccountUser', false],
            [null, 'Oro\Bundle\CustomerBundle\Entity\AccountUser', true],
            ['anon.', 'Oro\Bundle\CustomerBundle\Entity\AccountUser', true],
            [new AccountUser(), 'Oro\Bundle\CustomerBundle\Entity\AccountUser', true],
            [
                'Oro\Bundle\CustomerBundle\Entity\AccountUser',
                'Oro\Bundle\CustomerBundle\Entity\AccountUser',
                true,
            ],
        ];
    }
}
