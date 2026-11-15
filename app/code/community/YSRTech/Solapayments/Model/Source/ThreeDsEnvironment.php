<?php

class YSRTech_Solapayments_Model_Source_ThreeDsEnvironment
{
    public function toOptionArray()
    {
        return [
            ['value' => 'staging', 'label' => Mage::helper('solapayments')->__('Staging (testing)')],
            ['value' => 'production', 'label' => Mage::helper('solapayments')->__('Production')],
        ];
    }
}
