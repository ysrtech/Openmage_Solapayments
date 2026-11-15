<?php

/**
 * Gift card field in the checkout summary (below the discount code).
 */
class YSRTech_Solapayments_Block_Giftcard extends Mage_Core_Block_Template
{
    protected function _toHtml()
    {
        $quote = $this->getQuote();
        if (!$quote->getId() || !Mage::getSingleton('solapayments/giftcard')->isEnabled($quote->getStoreId())) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function getTitle()
    {
        return Mage::getSingleton('solapayments/giftcard')->getTitle();
    }
}
