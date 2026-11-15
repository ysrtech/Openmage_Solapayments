<?php

/**
 * Sola gift card life cycle on orders:
 *
 *   sales_model_service_quote_submit_before   redeem (gift:redeem) the quote's gift card amount
 *                                              before the order and its payment are saved
 *   sales_model_service_quote_submit_failure  order not placed: put the amount back (gift:issue)
 *   sales_order_invoice_register              count what invoices used of the gift card
 *   sales_order_creditmemo_refund             put the refunded gift card part back (gift:issue)
 *   order_cancel_after                         put what was not refunded back (gift:issue)
 *   sales_model_service_quote_submit_success  clear the gift card from the checkout
 *   payment_method_is_active                  a gift card pays the whole order: only Zero
 *                                              Subtotal Checkout ("free") is offered
 */
class YSRTech_Solapayments_Model_Observer
{
    /** @var array order increment id => [code, base amount] redeemed during this request */
    protected static $_redeemed = [];

    public function redeemGiftcard(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        $code = (string) $quote->getSolapaymentsGiftcardCode();
        $base = (float) $quote->getBaseSolapaymentsGiftcardAmount();
        if ($code === '' || $base <= 0) {
            return;
        }
        $giftcard = Mage::getSingleton('solapayments/giftcard');
        $helper = Mage::helper('solapayments');
        $response = $giftcard->redeem($code, $base, $order);
        if (!$giftcard->isApproved($response)) {
            Mage::throwException($helper->__('Your gift card could not be used: %s', $giftcard->getError($response)));
        }
        self::$_redeemed[$order->getIncrementId()] = [$code, $base];
        $order->setSolapaymentsGiftcardCode($code)
            ->setSolapaymentsGiftcardRefnum((string) ($response['xRefNum'] ?? ''))
            ->setBaseSolapaymentsGiftcardAmount($base)
            ->setSolapaymentsGiftcardAmount((float) $quote->getSolapaymentsGiftcardAmount());
        $order->addStatusHistoryComment($helper->__(
            'Sola gift card %s paid %s (transaction %s).',
            $response['xMaskedCardNumber'] ?? $helper->maskGiftcard($code),
            $order->getBaseCurrency()->formatTxt($base),
            $response['xRefNum'] ?? ''
        ));
    }

    public function reissueOnFailure(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !isset(self::$_redeemed[$order->getIncrementId()])) {
            return;
        }
        [$code, $base] = self::$_redeemed[$order->getIncrementId()];
        unset(self::$_redeemed[$order->getIncrementId()]);
        $giftcard = Mage::getSingleton('solapayments/giftcard');
        $response = $giftcard->issue($code, $base, $order);
        if (!$giftcard->isApproved($response)) {
            Mage::log(sprintf('Gift card %s: %s could not be put back after a failed order %s: %s',
                Mage::helper('solapayments')->maskGiftcard($code), $base, $order->getIncrementId(), $giftcard->getError($response)),
                Zend_Log::ERR, 'solapayments.log', true);
        }
    }

    public function clearCheckout(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote && $quote->getSolapaymentsGiftcardCode()) {
            $quote->setSolapaymentsGiftcardCode(null)->setBaseSolapaymentsGiftcardBalance(null);
        }
        $order = $observer->getEvent()->getOrder();
        if ($order) {
            unset(self::$_redeemed[$order->getIncrementId()]);
        }
    }

    public function invoiceRegister(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $observer->getEvent()->getOrder();
        if ((float) $invoice->getBaseSolapaymentsGiftcardAmount() > 0) {
            $order->setBaseSolapaymentsGiftcardInvoiced((float) $order->getBaseSolapaymentsGiftcardInvoiced() + (float) $invoice->getBaseSolapaymentsGiftcardAmount());
            $order->setSolapaymentsGiftcardInvoiced((float) $order->getSolapaymentsGiftcardInvoiced() + (float) $invoice->getSolapaymentsGiftcardAmount());
        }
    }

    public function creditmemoRefund(Varien_Event_Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $base = (float) $creditmemo->getBaseSolapaymentsGiftcardAmount();
        if ($base <= 0 || !$order->getSolapaymentsGiftcardCode()) {
            return;
        }
        $order->setBaseSolapaymentsGiftcardRefunded((float) $order->getBaseSolapaymentsGiftcardRefunded() + $base);
        $order->setSolapaymentsGiftcardRefunded((float) $order->getSolapaymentsGiftcardRefunded() + (float) $creditmemo->getSolapaymentsGiftcardAmount());
        $this->_issue($order, $base, 'refund');
    }

    public function orderCancel(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $left = (float) $order->getBaseSolapaymentsGiftcardAmount() - (float) $order->getBaseSolapaymentsGiftcardRefunded();
        if ($left <= 0.0001 || !$order->getSolapaymentsGiftcardCode()) {
            return;
        }
        $order->setBaseSolapaymentsGiftcardRefunded((float) $order->getBaseSolapaymentsGiftcardAmount());
        $order->setSolapaymentsGiftcardRefunded((float) $order->getSolapaymentsGiftcardAmount());
        $this->_issue($order, $left, 'cancel');
    }

    protected function _issue(Mage_Sales_Model_Order $order, $base, $reason)
    {
        $helper = Mage::helper('solapayments');
        $giftcard = Mage::getSingleton('solapayments/giftcard');
        $response = $giftcard->issue($order->getSolapaymentsGiftcardCode(), $base, $order);
        $amount = $order->getBaseCurrency()->formatTxt($base);
        $order->addStatusHistoryComment($giftcard->isApproved($response)
            ? $helper->__('%s put back on Sola gift card %s (transaction %s).', $amount, $response['xMaskedCardNumber'] ?? $helper->maskGiftcard($order->getSolapaymentsGiftcardCode()), $response['xRefNum'] ?? '')
            : $helper->__('Putting %s back on the Sola gift card failed: %s. Please issue it in the Sola portal.', $amount, $giftcard->getError($response)));
    }

    /**
     * OpenMage's zero-total check looks at subtotal + shipping, so an order the gift card pays in
     * full would still offer every payment method.
     */
    public function zeroTotalMethods(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $method = $observer->getEvent()->getMethodInstance();
        if ($quote && $method && $method->getCode() !== 'free'
            && (float) $quote->getBaseSolapaymentsGiftcardAmount() > 0
            && (float) $quote->getBaseGrandTotal() < 0.0001
        ) {
            $observer->getEvent()->getResult()->isAvailable = false;   // stdClass in OpenMage
        }
    }
}
