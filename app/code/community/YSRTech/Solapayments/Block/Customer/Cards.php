<?php

/**
 * My Account > Saved cards.
 */
class YSRTech_Solapayments_Block_Customer_Cards extends Mage_Core_Block_Template
{
    /**
     * @return YSRTech_Solapayments_Model_Resource_Card_Collection
     */
    public function getCards()
    {
        return Mage::getResourceModel('solapayments/card_collection')
            ->addCustomerFilter(Mage::getSingleton('customer/session')->getCustomerId());
    }
}
