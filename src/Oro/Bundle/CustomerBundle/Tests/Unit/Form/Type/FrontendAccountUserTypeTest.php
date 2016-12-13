<?php

namespace Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

use Oro\Component\Testing\Unit\Form\Type\Stub\EntityType as AccountSelectTypeStub;

use Oro\Bundle\FormBundle\Form\Type\OroDateType;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\CustomerBundle\Entity\Account;
use Oro\Bundle\CustomerBundle\Form\Type\AccountUserRoleSelectType;
use Oro\Bundle\CustomerBundle\Form\Type\AccountUserType;
use Oro\Bundle\CustomerBundle\Form\Type\FrontendAccountUserRoleSelectType;
use Oro\Bundle\CustomerBundle\Form\Type\FrontendAccountUserType;
use Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type\Stub\EntitySelectTypeStub;
use Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type\Stub\EntityType;
use Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type\Stub\AddressCollectionTypeStub;
use Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type\Stub\FrontendOwnerSelectTypeStub;

class FrontendAccountUserTypeTest extends AccountUserTypeTest
{
    const DATA_CLASS = 'Oro\Bundle\CustomerBundle\Entity\AccountUser';

    /**
     * @var FrontendAccountUserType
     */
    protected $formType;

    /** @var  SecurityFacade|\PHPUnit_Framework_MockObject_MockObject */
    protected $securityFacade;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->securityFacade = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $this->formType = new FrontendAccountUserType($this->securityFacade);
        $this->formType->setAccountUserClass(self::DATA_CLASS);
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->getFormFactory();
    }

    /**
     * @return array
     */
    protected function getExtensions()
    {
        $account = $this->getAccount(1);
        $user = new AccountUser();
        $user->setAccount($account);
        $this->securityFacade->expects($this->any())->method('getLoggedUser')->willReturn($user);

        $frontendUserRoleSelectType = new EntitySelectTypeStub(
            $this->getRoles(),
            FrontendAccountUserRoleSelectType::NAME,
            new AccountUserRoleSelectType($this->createTranslator())
        );
        $addressEntityType = new EntityType($this->getAddresses(), 'test_address_entity');
        $accountSelectType = new AccountSelectTypeStub($this->getAccounts(), 'oro_customer_account_select');

        $accountUserType = new AccountUserType($this->securityFacade);
        $accountUserType->setDataClass(self::DATA_CLASS);
        $accountUserType->setAddressClass(self::ADDRESS_CLASS);

        return [
            new PreloadedExtension(
                [
                    OroDateType::NAME => new OroDateType(),
                    AccountUserType::NAME => $accountUserType,
                    FrontendAccountUserRoleSelectType::NAME => $frontendUserRoleSelectType,
                    $accountSelectType->getName() => $accountSelectType,
                    FrontendOwnerSelectTypeStub::NAME => new FrontendOwnerSelectTypeStub(),
                    AddressCollectionTypeStub::NAME => new AddressCollectionTypeStub(),
                    $addressEntityType->getName() => $addressEntityType,
                ],
                []
            ),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    /**
     * @dataProvider submitProvider
     *
     * @param AccountUser $defaultData
     * @param array $submittedData
     * @param AccountUser $expectedData
     * @param bool $roleGranted
     */
    public function testSubmit(
        AccountUser $defaultData,
        array $submittedData,
        AccountUser $expectedData,
        $roleGranted = true
    ) {
        $form = $this->factory->create($this->formType, $defaultData, []);

        $this->assertEquals($defaultData, $form->getData());
        $form->submit($submittedData);
        $result = $form->isValid();
        $this->assertTrue($result);
        $this->assertEquals($expectedData, $form->getData());
    }

    /**
     * @return array
     */
    public function submitProvider()
    {
        $newAccountUser = new AccountUser();
        $account = new Account();
        $newAccountUser->setAccount($account);
        $existingAccountUser = new AccountUser();

        $class = new \ReflectionClass($existingAccountUser);
        $prop = $class->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($existingAccountUser, 42);

        $existingAccountUser->setFirstName('John');
        $existingAccountUser->setLastName('Doe');
        $existingAccountUser->setEmail('johndoe@example.com');
        $existingAccountUser->setPassword('123456');
        $existingAccountUser->setAccount($account);
        $existingAccountUser->addAddress($this->getAddresses()[1]);

        $alteredExistingAccountUser = clone $existingAccountUser;
        $alteredExistingAccountUser->setEnabled(false);
        $alteredExistingAccountUser->setAccount($account);

        $alteredExistingAccountUserWithRole = clone $alteredExistingAccountUser;
        $alteredExistingAccountUserWithRole->setRoles([$this->getRole(2, 'test02')]);

        $alteredExistingAccountUserWithAddresses = clone $alteredExistingAccountUser;
        $alteredExistingAccountUserWithAddresses->addAddress($this->getAddresses()[2]);

        return
            [
                'user without submitted data' => [
                    'defaultData' => $newAccountUser,
                    'submittedData' => [],
                    'expectedData' => $newAccountUser,
                ],
                'altered existing user' => [
                    'defaultData' => $existingAccountUser,
                    'submittedData' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'email' => 'johndoe@example.com',
                        'account' => $existingAccountUser->getAccount()->getName(),
                    ],
                    'expectedData' => $alteredExistingAccountUser,
                ],
                'altered existing user with roles' => [
                    'defaultData' => $existingAccountUser,
                    'submittedData' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'email' => 'johndoe@example.com',
                        'account' => $existingAccountUser->getAccount()->getName(),
                        'roles' => [2],
                    ],
                    'expectedData' => $alteredExistingAccountUserWithRole,
                    'altered existing user with addresses' => [
                        'defaultData' => $existingAccountUser,
                        'submittedData' => [
                            'firstName' => 'John',
                            'lastName' => 'Doe',
                            'email' => 'johndoe@example.com',
                            'account' => $alteredExistingAccountUserWithRole->getAccount()->getName(),
                            'addresses' => [1, 2],
                        ],
                        'expectedData' => $alteredExistingAccountUserWithAddresses,
                    ],
                ],
            ];
    }

    /**
     * Test getName
     */
    public function testGetName()
    {
        $this->assertEquals(FrontendAccountUserType::NAME, $this->formType->getName());
    }

    /**
     * @depends testSubmit
     */
    public function testOnPreSetData()
    {
        /** @var $securityFacade SecurityFacade|\PHPUnit_Framework_MockObject_MockObject */
        $securityFacade = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()
            ->getMock();
        /** @var FrontendAccountUserType $formType */
        $formType = new FrontendAccountUserType($securityFacade);
        /** @var $event FormEvent|\PHPUnit_Framework_MockObject_MockObject */
        $event = $this->getMockBuilder('Symfony\Component\Form\FormEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $securityFacade->expects($this->any())->method('getLoggedUser')->willReturn(null);
        $formType->onPreSetData($event);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|TranslatorInterface
     */
    private function createTranslator()
    {
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $translator->expects($this->any())
            ->method('trans')
            ->willReturnCallback(
                function ($message) {
                    return $message . '.trans';
                }
            );

        return $translator;
    }
}
