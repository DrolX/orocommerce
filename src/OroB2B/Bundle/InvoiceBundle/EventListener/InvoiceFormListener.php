<?php

namespace OroB2B\Bundle\InvoiceBundle\EventListener;

use Oro\Bundle\FormBundle\Event\FormHandler\AfterFormProcessEvent;

use OroB2B\Bundle\InvoiceBundle\Entity\Invoice;
use OroB2B\Bundle\PricingBundle\SubtotalProcessor\Provider\LineItemSubtotalProvider;

class InvoiceFormListener
{
    /**
     * @var LineItemSubtotalProvider
     */
    protected $subtotalProvider;

    /**
     * @param LineItemSubtotalProvider $subtotalProvider
     */
    public function __construct(LineItemSubtotalProvider $subtotalProvider)
    {
        $this->subtotalProvider = $subtotalProvider;
    }

    /**
     * @param AfterFormProcessEvent $event
     */
    public function beforeFlush(AfterFormProcessEvent $event)
    {
        /** @var Invoice $invoice */
        $invoice = $event->getData();

        $subtotal = $this->subtotalProvider->getSubtotal($invoice);
        $invoice->setSubtotal($subtotal->getAmount());

        foreach ($invoice->getLineItems() as $lineItem) {
            $lineItem->updateItemInformation();
        }
    }
}
