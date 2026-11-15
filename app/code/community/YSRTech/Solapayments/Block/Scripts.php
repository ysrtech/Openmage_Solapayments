<?php

/**
 * Settings for the payment scripts (window.solapaymentsConfig), added to checkout pages and
 * admin Create Order by layout: messages for the card form and, on the storefront, the Google Pay
 * and Apple Pay settings plus the Cardknox iFields library when a wallet is enabled.
 */
class YSRTech_Solapayments_Block_Scripts extends Mage_Core_Block_Template
{
    protected function _toHtml()
    {
        $helper = Mage::helper('solapayments');
        $admin = Mage::app()->getStore()->isAdmin();
        $store = $admin ? Mage::getSingleton('adminhtml/session_quote')->getStoreId() : Mage::app()->getStore()->getId();
        $wallets = $admin ? [] : $this->getWallets($store);
        if (!$helper->getConfigFlag('active', $store) && !$wallets) {
            return '';
        }
        $config = [
            'text' => [
                'cardInvalid'   => $helper->__('Please enter a valid card number.'),
                'cvvInvalid'    => $helper->__('Please enter the security code (3 or 4 digits on the card).'),
                'expiryInvalid' => $helper->__('Please enter a valid expiry date (MM / YY).'),
                'duplicate'     => 'Duplicate Transaction',
                'walletCanceled' => $helper->__('The payment was canceled.'),
                'walletFailed'  => $helper->__('The payment could not be completed. Please try again or choose another payment method.'),
                'walletZero'    => $helper->__('Nothing to pay: please choose another payment method.'),
                'walletCheckForm' => $helper->__('Please complete the highlighted fields first.'),
            ],
            'wallets' => $wallets ?: new stdClass(),
        ];
        $html = '<script>window.solapaymentsConfig = ' . Mage::helper('core')->jsonEncode($config) . ';</script>' . "\n";
        if ($wallets) {
            $html .= '<script src="' . $this->escapeHtml($helper->getIfieldsUrl('ifields.min.js', $store)) . '"></script>' . "\n";
        }
        return $html;
    }

    /**
     * Enabled wallets (Google Pay needs its merchant name).
     */
    public function getWallets($store)
    {
        $helper = Mage::helper('solapayments');
        if ($helper->getTransactionKey($store) === '') {
            return [];
        }
        $wallets = [];
        $common = [
            'tokenKey'        => $helper->getTokenKey($store),
            'softwareName'    => $helper->getSoftwareName(),
            'softwareVersion' => YSRTech_Solapayments_Helper_Data::VERSION,
            'storeName'       => (string) (Mage::getStoreConfig('general/store_information/name', $store) ?: Mage::app()->getStore($store)->getFrontendName()),
        ];
        $google = 'payment/' . YSRTech_Solapayments_Model_Method_Googlepay::CODE . '/';
        if (Mage::getStoreConfigFlag($google . 'active', $store) && trim((string) Mage::getStoreConfig($google . 'merchant_name', $store)) !== '') {
            $wallets['googlepay'] = $common + [
                'code'         => YSRTech_Solapayments_Model_Method_Googlepay::CODE,
                'merchantName' => trim((string) Mage::getStoreConfig($google . 'merchant_name', $store)),
                'environment'  => Mage::getStoreConfig($google . 'environment', $store) === 'PRODUCTION' ? 'PRODUCTION' : 'TEST',
                'buttonColor'  => (string) Mage::getStoreConfig($google . 'button_color', $store),
                'buttonType'   => (string) Mage::getStoreConfig($google . 'button_type', $store),
            ];
        }
        $apple = 'payment/' . YSRTech_Solapayments_Model_Method_Applepay::CODE . '/';
        if (Mage::getStoreConfigFlag($apple . 'active', $store)) {
            $wallets['applepay'] = $common + [
                'code'               => YSRTech_Solapayments_Model_Method_Applepay::CODE,
                'merchantIdentifier' => trim((string) Mage::getStoreConfig($apple . 'merchant_identifier', $store)) ?: 'merchant.cardknox.com',
                'merchantCountry'    => (string) (Mage::getStoreConfig('general/store_information/merchant_country', $store)
                    ?: Mage::getStoreConfig('general/country/default', $store)),
                'buttonColor'        => (string) Mage::getStoreConfig($apple . 'button_color', $store),
                'buttonType'         => (string) Mage::getStoreConfig($apple . 'button_type', $store),
            ];
        }
        return $wallets;
    }
}
