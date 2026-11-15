<?php

/**
 * Adds the gift card line to order, invoice and credit memo totals (account pages, print,
 * e-mails and admin). Place as a child of order_totals / invoice_totals / creditmemo_totals.
 */
class YSRTech_Solapayments_Block_Sales_Totals extends Mage_Core_Block_Abstract
{
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource();
        $amount = (float) $source->getSolapaymentsGiftcardAmount();
        if ($amount <= 0) {
            return $this;
        }
        $order = $parent->getOrder() ?: $source;
        $parent->addTotalBefore(new Varien_Object([
            'code'       => 'solapayments_giftcard',
            'value'      => -$amount,
            'base_value' => -(float) $source->getBaseSolapaymentsGiftcardAmount(),
            'label'      => Mage::getSingleton('solapayments/giftcard')->getTitle($order->getStoreId())
                . ($order->getSolapaymentsGiftcardCode() ? ' (' . Mage::helper('solapayments')->maskGiftcard($order->getSolapaymentsGiftcardCode()) . ')' : ''),
        ]), 'grand_total');
        return $this;
    }
}
