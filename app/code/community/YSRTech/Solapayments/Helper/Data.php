<?php

/**
 * Sola Payments (Cardknox) settings and small utilities.
 */
class YSRTech_Solapayments_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const VERSION = '2.0.0';
    public const XML_PATH = 'payment/solapayments/';
    public const DEFAULT_GATEWAY = 'https://x1.cardknox.com/gateway';

    protected $_moduleName = 'YSRTech_Solapayments';

    public function getConfig($field, $store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH . $field, $store);
    }

    public function getConfigFlag($field, $store = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH . $field, $store);
    }

    /**
     * iFields key (public, used in the browser).
     */
    public function getTokenKey($store = null)
    {
        return trim((string) $this->getConfig('cardknox_token_key', $store));
    }

    /**
     * Transaction key (secret, server only). Stored encrypted.
     */
    public function getTransactionKey($store = null)
    {
        return trim((string) Mage::helper('core')->decrypt($this->getConfig('cardknox_transaction_key', $store)));
    }

    public function getGatewayUrl($store = null)
    {
        $url = trim((string) $this->getConfig('cgi_url', $store));
        return $url !== '' ? $url : self::DEFAULT_GATEWAY;
    }

    public function isDebug($store = null)
    {
        return $this->getConfigFlag('debug', $store);
    }

    public function formatPrice($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * The customer's IP address (first public address of X-Forwarded-For when behind a proxy).
     */
    public function getIpAddress()
    {
        return (string) Mage::helper('core/http')->getRemoteAddr();
    }

    public function getSoftwareName()
    {
        return 'OpenMage ' . Mage::getOpenMageVersion();
    }

    /**
     * Debug log (var/log/solapayments.log). Card data never reaches the server (iFields), and
     * keys are masked before logging.
     */
    public function log($data, $store = null)
    {
        if (!$this->isDebug($store)) {
            return;
        }
        if (is_array($data)) {
            foreach (['xKey', 'xCardNum', 'xCVV', 'xToken', 'xExp'] as $secret) {
                if (!empty($data[$secret])) {
                    $data[$secret] = substr((string) $data[$secret], 0, 4) . '…';
                }
            }
            $data = print_r($data, true);
        }
        Mage::log($data, Zend_Log::DEBUG, 'solapayments.log', true);
    }
}
