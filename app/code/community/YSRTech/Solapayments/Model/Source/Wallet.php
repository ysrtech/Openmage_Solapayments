<?php

/**
 * Options of the Google Pay and Apple Pay settings (button colour and type, environment).
 */
class YSRTech_Solapayments_Model_Source_Wallet
{
    protected function _options(array $values)
    {
        $helper = Mage::helper('solapayments');
        $options = [];
        foreach ($values as $value => $label) {
            $options[] = ['value' => $value, 'label' => $helper->__($label)];
        }
        return $options;
    }

    public function getGoogleEnvironments()
    {
        return $this->_options(['TEST' => 'Test', 'PRODUCTION' => 'Production']);
    }

    public function getGoogleButtonColors()
    {
        return $this->_options(['default' => 'Default (black)', 'black' => 'Black', 'white' => 'White']);
    }

    public function getGoogleButtonTypes()
    {
        return $this->_options(['pay' => 'Pay', 'buy' => 'Buy', 'checkout' => 'Checkout', 'order' => 'Order', 'plain' => 'Plain (logo only)']);
    }

    public function getAppleButtonColors()
    {
        return $this->_options(['black' => 'Black', 'white' => 'White', 'whiteOutline' => 'White with outline']);
    }

    public function getAppleButtonTypes()
    {
        return $this->_options(['pay' => 'Pay', 'buy' => 'Buy', 'checkout' => 'Check out', 'order' => 'Order', 'plain' => 'Plain (logo only)', 'continue' => 'Continue']);
    }
}
