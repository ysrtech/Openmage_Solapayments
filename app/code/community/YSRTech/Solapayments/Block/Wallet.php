<?php

/**
 * Payment form of Google Pay / Apple Pay: the wallet's button (Cardknox iFields), an error line
 * and, after a duplicate-transaction decline, "place it anyway".
 * Behaviour: skin solapayments/wallet.js; settings in window.solapaymentsConfig.wallets.
 */
class YSRTech_Solapayments_Block_Wallet extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('solapayments/wallet.phtml');
    }

    /**
     * 'googlepay' or 'applepay'
     */
    public function getWalletKey()
    {
        return $this->getMethodCode() === YSRTech_Solapayments_Model_Method_Applepay::CODE ? 'applepay' : 'googlepay';
    }

    public function getGooglePayFrameUrl()
    {
        return Mage::helper('solapayments')->getIfieldsUrl('igp.htm');
    }
}
