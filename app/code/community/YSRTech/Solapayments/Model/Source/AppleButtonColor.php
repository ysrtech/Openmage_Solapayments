<?php

class YSRTech_Solapayments_Model_Source_AppleButtonColor extends YSRTech_Solapayments_Model_Source_Wallet
{
    public function toOptionArray()
    {
        return $this->getAppleButtonColors();
    }
}
