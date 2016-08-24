<?php

namespace OroB2B\Bundle\ShoppingListBundle\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

use OroB2B\Bundle\PricingBundle\Manager\UserCurrencyManager;
use OroB2B\Bundle\PricingBundle\SubtotalProcessor\Provider\LineItemNotPricedSubtotalProvider;
use OroB2B\Bundle\ShoppingListBundle\Entity\ShoppingList;
use OroB2B\Bundle\ShoppingListBundle\Entity\ShoppingListTotal;

class ShoppingListTotalManager
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var LineItemNotPricedSubtotalProvider
     */
    protected $lineItemNotPricedSubtotalProvider;

    /**
     * @var UserCurrencyManager
     */
    protected $currencyManager;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param LineItemNotPricedSubtotalProvider $lineItemNotPricedSubtotalProvider
     * @param UserCurrencyManager $currencyManager
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        LineItemNotPricedSubtotalProvider $lineItemNotPricedSubtotalProvider,
        UserCurrencyManager $currencyManager
    ) {
        $this->registry = $managerRegistry;
        $this->lineItemNotPricedSubtotalProvider = $lineItemNotPricedSubtotalProvider;
        $this->currencyManager = $currencyManager;
    }

    /**
     * Sets Shopping Lists Subtotal for user current currency
     *
     * @param array $shoppingLists
     * @param bool $doFlush
     */
    public function setSubtotals(array $shoppingLists, $doFlush = true)
    {
        $currency = $this->currencyManager->getUserCurrency();
        /** @var ShoppingListTotal|null $shoppingListTotal */
        $shoppingListTotals = $this->getTotalRepository()
            ->findBy(['shoppingList' => $shoppingLists, 'currency' => $currency]);

        $shoppingListTotals = array_reduce(
            $shoppingListTotals,
            function (array $result, ShoppingListTotal $shoppingListTotal) {
                $result[$shoppingListTotal->getShoppingList()->getId()] = $shoppingListTotal;
                return $result;
            },
            []
        );

        foreach ($shoppingLists as $shoppingList) {
            if (!array_key_exists($shoppingList->getId(), $shoppingListTotals)) {
                $shoppingListTotals[$shoppingList->getId()] = new ShoppingListTotal($shoppingList, $currency);
                $this->getEntityManager()->persist($shoppingListTotals[$shoppingList->getId()]);
            }
            $shoppingListTotal = $shoppingListTotals[$shoppingList->getId()];
            if (!$shoppingListTotal->isValid()) {
                $subtotal = $this->lineItemNotPricedSubtotalProvider->getSubtotalByCurrency($shoppingList, $currency);
                $shoppingListTotal->setSubtotal($subtotal)
                    ->setValid(true);
            }
            $shoppingList->setSubtotal($shoppingListTotal->getSubtotal());
        }

        if ($doFlush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param ShoppingList $shoppingList
     * @param bool $doFlush
     */
    public function recalculateTotals(ShoppingList $shoppingList, $doFlush)
    {
        /** @var ShoppingListTotal[] $totals */
        $totals = $this->getTotalRepository()->findBy(['shoppingList' => $shoppingList]);
        $enabledCurrencies = $this->currencyManager->getAvailableCurrencies();

        foreach ($totals as $total) {
            $subtotal = $this->lineItemNotPricedSubtotalProvider
                ->getSubtotalByCurrency($shoppingList, $total->getCurrency());
            if (($key = array_search($total->getCurrency(), $enabledCurrencies, true)) !== false) {
                unset($enabledCurrencies[$key]);
            }
            $total->setSubtotal($subtotal)
                ->setValid(true);
        }

        foreach ($enabledCurrencies as $currency) {
            $total = new ShoppingListTotal($shoppingList, $currency);
            $subtotal = $this->lineItemNotPricedSubtotalProvider
                ->getSubtotalByCurrency($shoppingList, $total->getCurrency());

            $total->setSubtotal($subtotal)
                ->setValid(true);
            $this->getEntityManager()->persist($total);
        }

        if ($doFlush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    protected function getTotalRepository()
    {
        return $this->getEntityManager()->getRepository('OroB2BShoppingListBundle:ShoppingListTotal');
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager|null
     */
    protected function getEntityManager()
    {
        if (!$this->em) {
            $this->em = $this->registry->getManagerForClass('OroB2BShoppingListBundle:ShoppingListTotal');
        }

        return $this->em;
    }
}