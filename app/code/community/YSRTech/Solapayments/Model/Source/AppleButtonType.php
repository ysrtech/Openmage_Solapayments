<?php

class YSRTech_Solapayments_Model_Source_AppleButtonType extends YSRTech_Solapayments_Model_Source_Wallet
{
    public function toOptionArray()
    {
        return $this->getAppleButtonTypes();
    }
}
