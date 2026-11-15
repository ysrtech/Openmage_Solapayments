<?php

class YSRTech_Solapayments_Model_Source_GoogleButtonColor extends YSRTech_Solapayments_Model_Source_Wallet
{
    public function toOptionArray()
    {
        return $this->getGoogleButtonColors();
    }
}
