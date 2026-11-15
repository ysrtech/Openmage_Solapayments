<?php

class YSRTech_Solapayments_Model_Resource_Card extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('solapayments/card', 'card_id');
    }
}
