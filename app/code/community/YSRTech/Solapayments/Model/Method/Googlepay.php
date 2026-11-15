<?php

/**
 * Google Pay through Sola ("solapayments_googlepay").
 */
class YSRTech_Solapayments_Model_Method_Googlepay extends YSRTech_Solapayments_Model_Method_Wallet
{
    public const CODE = 'solapayments_googlepay';
    public const WALLET_TYPE = 'GooglePay';

    protected $_code = self::CODE;

    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) && trim((string) $this->getConfigData('merchant_name', $quote ? $quote->getStoreId() : null)) !== '';
    }
}
