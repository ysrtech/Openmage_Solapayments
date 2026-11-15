<?php

/**
 * Credit memo total: what was paid by gift card is refunded to the gift card first (gift:issue
 * when the credit memo is refunded, see Observer); only the rest is refunded to the payment.
 */
class YSRTech_Solapayments_Model_Total_Creditmemo extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $baseLeft = (float) $order->getBaseSolapaymentsGiftcardInvoiced() - (float) $order->getBaseSolapaymentsGiftcardRefunded();
        $left = (float) $order->getSolapaymentsGiftcardInvoiced() - (float) $order->getSolapaymentsGiftcardRefunded();
        if ($baseLeft <= 0.0001) {
            return $this;
        }
        $base = min($baseLeft, (float) $creditmemo->getBaseGrandTotal());
        $amount = min($left, (float) $creditmemo->getGrandTotal());
        $creditmemo->setBaseSolapaymentsGiftcardAmount($base)->setSolapaymentsGiftcardAmount($amount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $base)->setGrandTotal($creditmemo->getGrandTotal() - $amount);
        if ($creditmemo->getBaseGrandTotal() <= 0.0001) {
            $creditmemo->setAllowZeroGrandTotal(true);
        }
        return $this;
    }
}
