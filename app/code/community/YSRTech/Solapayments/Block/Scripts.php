<?php

/**
 * Loads Cardknox iFields and passes the settings the payment form script needs
 * (window.solapaymentsConfig). Added to the head of checkout pages by layout.
 */
class YSRTech_Solapayments_Block_Scripts extends Mage_Core_Block_Template
{
    protected function _toHtml()
    {
        $helper = Mage::helper('solapayments');
        if (!$helper->getConfigFlag('active') || $helper->getTokenKey() === '') {
            return '';
        }
        $config = [
            'tokenKey'        => $helper->getTokenKey(),
            'softwareName'    => $helper->getSoftwareName(),
            'softwareVersion' => YSRTech_Solapayments_Helper_Data::VERSION,
            'threeDs'         => $helper->isThreeDsEnabled(),
            'threeDsEnv'      => (string) $helper->getConfig('cardknox_three_d_secure_environment') ?: 'production',
            'verifyUrl'       => $this->getUrl('solapayments/threeds/verify', ['_secure' => true]),
            'formKey'         => Mage::getSingleton('core/session')->getFormKey(),
            'text'            => [
                'cardRequired'   => $helper->__('Please enter your card number.'),
                'cvvRequired'    => $helper->__('Please enter the security code.'),
                'cardInvalid'    => $helper->__('The card number is not valid.'),
                'cvvInvalid'     => $helper->__('The security code is not valid.'),
                'expiryInvalid'  => $helper->__('Please enter a valid expiry date (MM / YY).'),
                'tokenError'     => $helper->__('Your card details could not be checked. Please try again.'),
                'threeDsFailed'  => $helper->__('Your bank did not confirm the payment. Please try again or use another card.'),
                'duplicate'      => 'Duplicate Transaction',
            ],
        ];
        return '<script src="' . $this->escapeUrl($helper->getIfieldsUrl('ifields.min.js')) . '"></script>' . "\n"
            . '<script>window.solapaymentsConfig = ' . Mage::helper('core')->jsonEncode($config) . ';</script>' . "\n";
    }
}
