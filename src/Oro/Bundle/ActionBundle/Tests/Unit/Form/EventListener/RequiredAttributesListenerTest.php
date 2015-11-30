<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Form\EventListener;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Oro\Bundle\ActionBundle\Form\EventListener\RequiredAttributesListener;
use Oro\Bundle\ActionBundle\Model\ActionContext;

class RequiredAttributesListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var RequiredAttributesListener */
    protected $listener;

    protected function setUp()
    {
        $this->listener = new RequiredAttributesListener();
    }

    protected function tearDown()
    {
        unset($this->listener);
    }

    public function testGetSubscribedEvents()
    {
        $events = $this->listener->getSubscribedEvents();

        $this->assertCount(2, $events);
        $this->assertArrayHasKey(FormEvents::PRE_SET_DATA, $events);
        $this->assertEquals('onPreSetData', $events[FormEvents::PRE_SET_DATA]);
        $this->assertArrayHasKey(FormEvents::SUBMIT, $events);
        $this->assertEquals('onSubmit', $events[FormEvents::SUBMIT]);
    }

    public function testEvents()
    {
        $attributeNames = ['test'];
        $values = ['test' => 'value'];

        $context = $this->createActionContext();
        $context->expects($this->once())->method('getValues')->with($attributeNames)->willReturn($values);

        $event = $this->createFormEvent();
        $event->expects($this->once())->method('getData')->willReturn($context);
        $event->expects($this->once())
            ->method('setData')
            ->with($this->isInstanceOf('Oro\Bundle\ActionBundle\Model\ActionContext'));

        $this->listener->initialize($attributeNames);
        $this->listener->onPreSetData($event);

        // Test submit data
        $formData = $this->createActionContext();
        $formData->expects($this->once())->method('getValues')->willReturn($values);

        $context->expects($this->once())->method('__set')->with('test', 'value');

        $submitEvent = $this->createFormEvent();
        $submitEvent->expects($this->once())->method('getData')->willReturn($formData);
        $submitEvent->expects($this->once())
            ->method('setData')
            ->with($this->isInstanceOf('Oro\Bundle\ActionBundle\Model\ActionContext'));

        $this->listener->onSubmit($submitEvent);
    }

    public function testOnSubmitNoActionContext()
    {
        $context = $this->createActionContext();
        $context->expects($this->never())->method($this->anything());

        $event = $this->createFormEvent();
        $event->expects($this->once())->method('getData')->willReturn($context);
        $event->expects($this->never())->method('setData');

        $this->listener->onSubmit($event);
    }

    public function testOnPreSetDataNoActionContext()
    {
        $event = $this->createFormEvent();
        $event->expects($this->once())->method('getData')->will($this->returnValue(new \stdClass()));
        $event->expects($this->never())->method('setData');

        $this->listener->onPreSetData($event);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ActionContext
     */
    protected function createActionContext()
    {
        return $this->getMockBuilder('Oro\Bundle\ActionBundle\Model\ActionContext')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|FormEvent
     */
    protected function createFormEvent()
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormEvent')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
