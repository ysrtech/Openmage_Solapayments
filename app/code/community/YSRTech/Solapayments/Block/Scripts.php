<?php

/**
 * Messages for the card form script (window.solapaymentsConfig), added to checkout pages and
 * admin Create Order by layout.
 */
class YSRTech_Solapayments_Block_Scripts extends Mage_Core_Block_Template
{
    protected function _toHtml()
    {
        $helper = Mage::helper('solapayments');
        $store = Mage::app()->getStore()->isAdmin() ? Mage::getSingleton('adminhtml/session_quote')->getStoreId() : null;
        if (!$helper->getConfigFlag('active', $store)) {
            return '';
        }
        $config = [
            'text' => [
                'cardInvalid'   => $helper->__('Please enter a valid card number.'),
                'cvvInvalid'    => $helper->__('Please enter the security code (3 or 4 digits on the card).'),
                'expiryInvalid' => $helper->__('Please enter a valid expiry date (MM / YY).'),
                'duplicate'     => 'Duplicate Transaction',
            ],
        ];
        return '<script>window.solapaymentsConfig = ' . Mage::helper('core')->jsonEncode($config) . ';</script>' . "\n";
    }
}
