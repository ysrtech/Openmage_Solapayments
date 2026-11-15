<?php

/**
 * Invoice total: the order's gift card amount is used by the first invoice(s), up to what
 * is left to invoice of it.
 */
class YSRTech_Solapayments_Model_Total_Invoice extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $baseLeft = (float) $order->getBaseSolapaymentsGiftcardAmount() - (float) $order->getBaseSolapaymentsGiftcardInvoiced();
        $left = (float) $order->getSolapaymentsGiftcardAmount() - (float) $order->getSolapaymentsGiftcardInvoiced();
        if ($baseLeft <= 0.0001) {
            return $this;
        }
        $base = min($baseLeft, (float) $invoice->getBaseGrandTotal());
        $amount = min($left, (float) $invoice->getGrandTotal());
        $invoice->setBaseSolapaymentsGiftcardAmount($base)->setSolapaymentsGiftcardAmount($amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $base)->setGrandTotal($invoice->getGrandTotal() - $amount);
        return $this;
    }
}
