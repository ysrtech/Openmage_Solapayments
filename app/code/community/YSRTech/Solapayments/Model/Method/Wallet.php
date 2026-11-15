<?php

/**
 * Base of the wallet methods (Google Pay, Apple Pay), as in Sola's Magento 2 module.
 *
 * The wallet button is a Cardknox iFields component (igp.htm iframe for Google Pay, the Apple
 * Pay JS button for Apple Pay). After the customer approves in the wallet's payment sheet, the
 * page posts the wallet's encrypted payment data (base64) as payment[solapayments_wallet_token];
 * it is sent to Cardknox as xCardNum with xDigitalWalletType, and is never stored.
 *
 * Capture, refund, void and cancel work as for cards (same gateway commands).
 */
abstract class YSRTech_Solapayments_Model_Method_Wallet extends YSRTech_Solapayments_Model_Method_Card
{
    /** xDigitalWalletType sent to Cardknox */
    public const WALLET_TYPE = '';

    protected $_formBlockType = 'solapayments/wallet';
    protected $_canUseInternal = false;

    /** @var string|null the wallet token posted in this request (never stored) */
    protected static $_walletToken = null;

    public static function getWalletToken()
    {
        return self::$_walletToken;
    }

    public function getWalletType()
    {
        return static::WALLET_TYPE;
    }

    /**
     * Needs the card method's API key; the settings (title, payment action, button ...) are the
     * wallet's own.
     */
    public function isAvailable($quote = null)
    {
        $store = $quote ? $quote->getStoreId() : null;
        return Mage_Payment_Model_Method_Abstract::isAvailable($quote)
            && Mage::helper('solapayments')->getTransactionKey($store) !== ''
            && $this->_isUnifiedCheckout($store);
    }

    /**
     * The wallet buttons need the YSRTech_UnifiedCheckout page (1.2 or later).
     */
    protected function _isUnifiedCheckout($store)
    {
        return Mage::helper('core')->isModuleEnabled('YSRTech_UnifiedCheckout')
            && version_compare((string) Mage::getConfig()->getModuleConfig('YSRTech_UnifiedCheckout')->version, '1.2.0', '>=')
            && Mage::helper('ysrtech_unifiedcheckout')->isEnabled($store);
    }

    /**
     * Posted fields: solapayments_wallet_token, solapayments_allow_duplicate.
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $token = trim((string) $data->getData('solapayments_wallet_token'));
        self::$_walletToken = $token !== '' ? $token : null;
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('allow_duplicate', $data->getData('solapayments_allow_duplicate') ? 1 : null);
        $info->setAdditionalInformation('card_id', null);
        $info->setAdditionalInformation('save_card', null);
        $info->setAdditionalInformation('wallet', static::WALLET_TYPE);
        $info->setCcType(null)->setCcLast4(null)->setCcExpMonth(null)->setCcExpYear(null);
        return $this;
    }

    public function validate()
    {
        Mage_Payment_Model_Method_Abstract::validate();
        // the token only comes with the request that places the order
        if (self::$_walletToken === null && $this->_isPlacingOrder()) {
            Mage::throwException(Mage::helper('solapayments')->__('Please pay with the %s button.', $this->getTitle()));
        }
        return $this;
    }

    protected function _applyApproved(Mage_Sales_Model_Order_Payment $payment, array $response)
    {
        parent::_applyApproved($payment, $response);
        self::$_walletToken = null;
    }

    /**
     * True while an order is being submitted (validate() also runs when the method is saved).
     */
    protected function _isPlacingOrder()
    {
        $info = $this->getInfoInstance();
        return $info instanceof Mage_Sales_Model_Order_Payment;
    }
}
