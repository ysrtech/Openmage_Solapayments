<?php

/**
 * Builds the transaction fields for the Cardknox gateway from an order payment: the card or
 * saved-card token, amount, invoice, billing and shipping address, and Level 3 data.
 * (Common fields such as xKey are added by YSRTech_Solapayments_Model_Api.)
 *
 * Amounts are in the base currency, and xCurrency is the base currency too (the Magento 2
 * module sent the base amount with the order currency).
 */
class YSRTech_Solapayments_Model_Request
{
    /**
     * Fields for a new transaction on a card (cc:authonly or cc:sale).
     */
    public function buildCardTransaction(Mage_Sales_Model_Order_Payment $payment, $amount, $command)
    {
        $helper = Mage::helper('solapayments');
        $order = $payment->getOrder();
        $fields = [
            'xCommand'        => $command,
            'xAmount'         => $helper->formatPrice($amount),
            'xCurrency'       => $order->getBaseCurrencyCode(),
            'xInvoice'        => $order->getIncrementId(),
            'xIgnoreInvoice'  => 'true',
            'xTimeoutSeconds' => 55,
            'xAllowDuplicate' => $payment->getAdditionalInformation('allow_duplicate') ? 'true' : null,
        ];
        if ($command === 'cc:authonly' && $helper->isSplitCapture($order->getStoreId())) {
            $fields['xRequireSplitCapturable'] = 1;
        }

        $card = $this->getSavedCard($payment);
        if ($card) {
            $fields['xToken'] = $card->getToken();
        } else {
            $fields['xCardNum'] = $payment->getAdditionalInformation('xCardNum');
            $fields['xCVV']     = $payment->getAdditionalInformation('xCVV');
            $fields['xExp']     = sprintf('%02d%02d', (int) $payment->getCcExpMonth(), (int) $payment->getCcExpYear() % 100);
        }
        return array_merge($fields, $this->getAddressFields($order), $this->getLevel3Fields($payment, null));
    }

    /**
     * Fields for capturing an authorization (cc:capture, or cc:splitcapture).
     */
    public function buildCapture(Mage_Sales_Model_Order_Payment $payment, $amount, $refNum)
    {
        $helper = Mage::helper('solapayments');
        $fields = [
            'xCommand'       => $helper->isSplitCapture($payment->getOrder()->getStoreId()) ? 'cc:splitcapture' : 'cc:capture',
            'xAmount'        => $helper->formatPrice($amount),
            'xRefNum'        => $refNum,
            'xIgnoreInvoice' => 'true',
        ];
        return array_merge($fields, $this->getLevel3Fields($payment, $payment->getCreatedInvoice()));
    }

    /**
     * The saved card chosen for this payment, if any (only cards of the order's customer).
     *
     * @return YSRTech_Solapayments_Model_Card|null
     */
    public function getSavedCard(Mage_Payment_Model_Info $payment)
    {
        $cardId = (int) $payment->getAdditionalInformation('card_id');
        if (!$cardId) {
            return null;
        }
        $card = Mage::getModel('solapayments/card')->load($cardId);
        $customerId = (int) ($payment instanceof Mage_Sales_Model_Order_Payment
            ? $payment->getOrder()->getCustomerId()
            : $payment->getQuote()->getCustomerId());
        return $card->getId() && $customerId && (int) $card->getCustomerId() === $customerId ? $card : null;
    }

    public function getAddressFields(Mage_Sales_Model_Order $order)
    {
        $fields = [];
        $billing = $order->getBillingAddress();
        if ($billing) {
            $fields += [
                'xBillFirstName' => $billing->getFirstname(),
                'xBillLastName'  => $billing->getLastname(),
                'xBillCompany'   => $billing->getCompany(),
                'xBillStreet'    => $billing->getStreet1(),
                'xBillStreet2'   => $billing->getStreet2(),
                'xBillCity'      => $billing->getCity(),
                'xBillState'     => $billing->getRegionCode(),
                'xBillZip'       => $billing->getPostcode(),
                'xBillCountry'   => $billing->getCountryId(),
                'xBillPhone'     => $billing->getTelephone(),
            ];
        }
        $fields['xEmail'] = $order->getCustomerEmail();
        $shipping = $order->getIsVirtual() ? null : $order->getShippingAddress();
        if ($shipping) {
            $fields += [
                'xShipFirstName' => $shipping->getFirstname(),
                'xShipLastName'  => $shipping->getLastname(),
                'xShipCompany'   => $shipping->getCompany(),
                'xShipStreet'    => $shipping->getStreet1(),
                'xShipStreet2'   => $shipping->getStreet2(),
                'xShipCity'      => $shipping->getCity(),
                'xShipState'     => $shipping->getRegionCode(),
                'xShipZip'       => $shipping->getPostcode(),
                'xShipCountry'   => $shipping->getCountryId(),
            ];
        }
        return $fields;
    }

    /**
     * Level 3: order-level tax/discount/shipping and line items. On a capture of part of an
     * order, the gateway re-adds the authorization's tax, shipping and discount, so Level 3 is
     * only sent when this invoice carries the same order-level amounts (as Sola's module does).
     *
     * @param Mage_Sales_Model_Order_Invoice|null $invoice the invoice being captured
     */
    public function getLevel3Fields(Mage_Sales_Model_Order_Payment $payment, $invoice)
    {
        $order = $payment->getOrder();
        $helper = Mage::helper('solapayments');
        if (!$helper->getConfigFlag('level3_enabled', $order->getStoreId())) {
            return [];
        }
        $source = $invoice ?: $order;
        if ($invoice && abs($this->_orderLevelAdjustment($invoice) - $this->_orderLevelAdjustment($order)) > 0.009) {
            return [];
        }
        $fields = [
            'xPONum'      => $order->getIncrementId(),
            'xTax'        => $helper->formatPrice($source->getBaseTaxAmount()),
            'xDiscount'   => $helper->formatPrice(abs((float) $source->getBaseDiscountAmount()) + $this->_giftCardAmount($source)),
            'xShipAmount' => $helper->formatPrice($source->getBaseShippingAmount()),
        ];
        if ($zip = Mage::getStoreConfig('shipping/origin/postcode', $order->getStoreId())) {
            $fields['xShipFromZip'] = $zip;
        }
        $index = 1;
        $anyTax = false;
        $items = $invoice ? $invoice->getAllItems() : $order->getAllItems();
        foreach ($items as $item) {
            $orderItem = $invoice ? $item->getOrderItem() : $item;
            if ($orderItem->getParentItem()) {
                continue;
            }
            $qty = (float) ($invoice ? $item->getQty() : $item->getQtyOrdered());
            if ($qty <= 0) {
                continue;
            }
            $fields["x{$index}Sku"]         = (string) $item->getSku();
            $fields["x{$index}Description"] = mb_substr((string) $item->getName(), 0, 100);
            $fields["x{$index}Qty"]         = (string) ($qty * 1);
            $fields["x{$index}UnitPrice"]   = $helper->formatPrice($orderItem->getBasePrice());
            $fields["x{$index}TaxRate"]     = $helper->formatPrice($orderItem->getTaxPercent() ?: 0);
            $fields["x{$index}TaxAmount"]   = $helper->formatPrice($item->getBaseTaxAmount() ?: 0);
            $anyTax = $anyTax || (float) $item->getBaseTaxAmount() > 0;
            $index++;
        }
        $fields['xNonTaxable'] = $anyTax ? 'false' : 'true';
        return $fields;
    }

    protected function _orderLevelAdjustment($entity)
    {
        return (float) $entity->getBaseTaxAmount() + (float) $entity->getBaseShippingAmount()
            - abs((float) $entity->getBaseDiscountAmount()) - $this->_giftCardAmount($entity);
    }

    /**
     * Sola gift card amount on an order / invoice (base currency), 0 without a gift card.
     */
    protected function _giftCardAmount($entity)
    {
        return abs((float) $entity->getBaseSolapaymentsGiftcardAmount());
    }
}
