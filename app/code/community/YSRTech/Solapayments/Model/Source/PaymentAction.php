<?php

class YSRTech_Solapayments_Model_Source_PaymentAction
{
    public function toOptionArray()
    {
        $helper = Mage::helper('solapayments');
        return [
            ['value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE, 'label' => $helper->__('Authorize (capture when invoiced)')],
            ['value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE, 'label' => $helper->__('Authorize and capture')],
        ];
    }
}
