<?php

class YSRTech_Solapayments_Model_Resource_Card_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('solapayments/card');
    }

    /**
     * Cards of a customer, default first, then most recently used.
     */
    public function addCustomerFilter($customerId)
    {
        $this->addFieldToFilter('customer_id', (int) $customerId)
            ->setOrder('is_default', 'DESC')
            ->setOrder('last_used_at', 'DESC')
            ->setOrder('card_id', 'DESC');
        return $this;
    }
}
