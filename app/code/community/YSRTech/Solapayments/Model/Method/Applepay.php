<?php

/**
 * Apple Pay through Sola ("solapayments_applepay"). The site's domain must be registered for
 * Apple Pay in the Sola portal (Settings > Gateway settings > Payment methods).
 */
class YSRTech_Solapayments_Model_Method_Applepay extends YSRTech_Solapayments_Model_Method_Wallet
{
    public const CODE = 'solapayments_applepay';
    public const WALLET_TYPE = 'ApplePay';

    protected $_code = self::CODE;
}
