<?php

/**
 * Sola Payments (Cardknox) settings and small utilities.
 */
class YSRTech_Solapayments_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const VERSION = '2.1.0';
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
     * Transaction key (secret, server only). Stored encrypted; getStoreConfig() decrypts it
     * (backend_model attribute in config.xml).
     */
    public function getTransactionKey($store = null)
    {
        return trim((string) $this->getConfig('cardknox_transaction_key', $store));
    }

    public function getIfieldsVersion($store = null)
    {
        return (string) ($this->getConfig('ifields_version', $store) ?: '3.5.2607.1401');
    }

    public function getIfieldsUrl($file, $store = null)
    {
        return 'https://cdn.cardknox.com/ifields/' . $this->getIfieldsVersion($store) . '/' . $file;
    }

    public function isVaultEnabled($store = null)
    {
        return $this->getConfigFlag('vault_active', $store);
    }

    public function isThreeDsEnabled($store = null)
    {
        return $this->getConfigFlag('cardknox_enable_three_d_secure', $store)
            && (string) $this->getConfig('cardknox_three_d_secure_environment', $store) !== '';
    }

    public function isSplitCapture($store = null)
    {
        return $this->getConfigFlag('split_capture_enabled', $store)
            && $this->getConfig('payment_action', $store) === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
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
        $ip = (string) Mage::helper('core/http')->getRemoteAddr();
        $ip = trim(explode(',', $ip)[0]);
        return strlen($ip) <= 45 ? $ip : '';
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
            $data = print_r($this->maskSecrets($data), true);
        }
        Mage::log($data, Zend_Log::DEBUG, 'solapayments.log', true);
    }

    /**
     * Keys, card numbers / tokens and security codes masked, at any depth.
     */
    public function maskSecrets(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSecrets($value);
            } elseif (in_array($key, ['xKey', 'xCardNum', 'xCVV', 'xToken', 'xExp', 'xCavv'], true) && (string) $value !== '') {
                $data[$key] = substr((string) $value, 0, 4) . '…';
            }
        }
        return $data;
    }

    public function maskGiftcard($code)
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        return strlen($code) > 4 ? '•••• ' . substr($code, -4) : $code;
    }
}
