<?php
/**
 * Sola Payments Extension for Magento 1
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @category   Openmage
 * @package    Openmage_Solapayments
 * @copyright  Copyright (c) 2024 Openmage
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Openmage_Solapayments_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_CARDKNOX_TOKEN_KEY = 'payment/solapayments/cardknox_token_key';
    const XML_PATH_CARDKNOX_TRANSACTION_KEY = 'payment/solapayments/cardknox_transaction_key';
    const XML_PATH_GATEWAY_URL = 'payment/solapayments/cgi_url';
    const XML_PATH_PAYMENT_ACTION = 'payment/solapayments/payment_action';
    const XML_PATH_RECAPTCHA_ENABLED = 'payment/solapayments/recaptchaEnabled';
    const XML_PATH_RECAPTCHA_SITE_KEY = 'payment/solapayments/visible_api_key';
    const XML_PATH_RECAPTCHA_SECRET_KEY = 'payment/solapayments/secret_key';

    /**
     * Get Cardknox Token Key
     *
     * @param mixed $store
     * @return string
     */
    public function getTokenKey($store = null)
    {
        $value = Mage::getStoreConfig(self::XML_PATH_CARDKNOX_TOKEN_KEY, $store);
        $decrypted = $value ? Mage::helper('core')->decrypt($value) : '';
        $this->log('getTokenKey() called - Store: ' . $store . ', Encrypted: ' . ($value ? 'Yes' : 'No') . ', Decrypted length: ' . strlen($decrypted));
        return $decrypted;
    }

    /**
     * Get Cardknox Transaction Key
     *
     * @param mixed $store
     * @return string
     */
    public function getTransactionKey($store = null)
    {
        $value = Mage::getStoreConfig(self::XML_PATH_CARDKNOX_TRANSACTION_KEY, $store);
        return $value ? Mage::helper('core')->decrypt($value) : '';
    }

    /**
     * Get Gateway URL
     *
     * @param mixed $store
     * @return string
     */
    public function getGatewayUrl($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_GATEWAY_URL, $store);
    }

    /**
     * Get Payment Action
     *
     * @param mixed $store
     * @return string
     */
    public function getPaymentAction($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_ACTION, $store);
    }

    /**
     * Check if reCAPTCHA is enabled
     *
     * @param mixed $store
     * @return bool
     */
    public function isRecaptchaEnabled($store = null)
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_RECAPTCHA_ENABLED, $store);
    }

    /**
     * Get reCAPTCHA Site Key
     *
     * @param mixed $store
     * @return string
     */
    public function getRecaptchaSiteKey($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_RECAPTCHA_SITE_KEY, $store);
    }

    /**
     * Get reCAPTCHA Secret Key
     *
     * @param mixed $store
     * @return string
     */
    public function getRecaptchaSecretKey($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_RECAPTCHA_SECRET_KEY, $store);
    }

    /**
     * Format price for API submission
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Log data to Solapayments log file
     *
     * @param mixed $data
     * @param mixed $level
     * @param mixed $file
     * @return void
     */
    public function log($data, $level = Zend_Log::DEBUG, $file = 'solapayments.log')
    {
        Mage::log('[' . date('Y-m-d H:i:s') . '] ' . $data, $level, $file);
    }

    /**
     * Send API request to Cardknox
     *
     * @param array $data
     * @return array
     */
    public function sendRequest($data)
    {
        $url = $this->getGatewayUrl();
        $this->log('sendRequest() called - URL: ' . $url . ', Command: ' . (isset($data['xCommand']) ? $data['xCommand'] : 'N/A'));
        
        try {
            $client = new Zend_Http_Client($url);
            $client->setMethod(Zend_Http_Client::POST);
            $client->setParameterPost($data);
            $client->setConfig(array('timeout' => 30));
            
            $response = $client->request();
            $this->log('API Response received - HTTP Status: ' . $response->getStatus());
            $responseBody = $response->getBody();
            
            // Parse response - Cardknox returns key=value pairs
            $result = array();
            $pairs = explode('&', $responseBody);
            foreach ($pairs as $pair) {
                $pieces = explode('=', $pair);
                if (count($pieces) == 2) {
                    $result[$pieces[0]] = urldecode($pieces[1]);
                }
            }
            
            $this->log('API Response: ' . print_r($result, true));
            return $result;
        } catch (Exception $e) {
            $this->log('API Request Error: ' . $e->getMessage(), Zend_Log::ERR);
            throw $e;
        }
    }

    /**
     * Build authorization request
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array
     */
    public function buildAuthorizationRequest($payment, $amount)
    {
        $order = $payment->getOrder();
        
        $request = array(
            'xKey' => $this->getTokenKey(),
            'xVersion' => '4.0.0',
            'xSoftwareName' => 'Magento',
            'xSoftwareVersion' => Mage::getVersion(),
            'xCommand' => 'cc:auth',
            'xAmount' => $this->formatPrice($amount),
            'xCurrency' => $order->getBaseCurrencyCode(),
            'xInvoice' => $order->getIncrementId(),
            'xOrderNumber' => $order->getIncrementId(),
            'xAuthCode' => '',
            'xRefNum' => '',
        );
        
        // Add card details if available
        $cardType = $payment->getCcType();
        $cardNumber = $payment->getCcNumber();
        $cardExpMonth = $payment->getCcExpMonth();
        $cardExpYear = $payment->getCcExpYear();
        $cardCvv = $payment->getCcCid();
        
        if ($cardNumber) {
            $request['xCardNum'] = $cardNumber;
            $request['xCardCode'] = $cardCvv;
            $request['xExp'] = str_pad($cardExpMonth, 2, '0', STR_PAD_LEFT) . $cardExpYear;
        }
        
        // Add customer information
        $billing = $order->getBillingAddress();
        if ($billing) {
            $request['xName'] = $billing->getFirstname() . ' ' . $billing->getLastname();
            $request['xAddress'] = $billing->getStreet1();
            $request['xCity'] = $billing->getCity();
            $request['xState'] = $billing->getRegion();
            $request['xZip'] = $billing->getPostcode();
            $request['xCountry'] = $billing->getCountry();
            $request['xEmail'] = $order->getCustomerEmail();
            $request['xPhone'] = $billing->getTelephone();
        }
        
        // Add shipping information if it's different from billing
        $shipping = $order->getShippingAddress();
        if ($shipping && $shipping->getId() != $billing->getId()) {
            $request['xShipName'] = $shipping->getFirstname() . ' ' . $shipping->getLastname();
            $request['xShipAddress'] = $shipping->getStreet1();
            $request['xShipCity'] = $shipping->getCity();
            $request['xShipState'] = $shipping->getRegion();
            $request['xShipZip'] = $shipping->getPostcode();
            $request['xShipCountry'] = $shipping->getCountry();
        }
        
        return $request;
    }

    /**
     * Build capture request
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array
     */
    public function buildCaptureRequest($payment, $amount)
    {
        $request = array(
            'xKey' => $this->getTokenKey(),
            'xVersion' => '4.0.0',
            'xCommand' => 'cc:capture',
            'xRefNum' => $payment->getLastTransId(),
            'xAmount' => $this->formatPrice($amount),
        );
        
        return $request;
    }

    /**
     * Build refund request
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array
     */
    public function buildRefundRequest($payment, $amount)
    {
        $request = array(
            'xKey' => $this->getTokenKey(),
            'xVersion' => '4.0.0',
            'xCommand' => 'cc:void_refund',
            'xRefNum' => $payment->getLastTransId(),
            'xAmount' => $this->formatPrice($amount),
        );
        
        return $request;
    }

    /**
     * Build void request
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return array
     */
    public function buildVoidRequest($payment)
    {
        $request = array(
            'xKey' => $this->getTokenKey(),
            'xVersion' => '4.0.0',
            'xCommand' => 'cc:void',
            'xRefNum' => $payment->getLastTransId(),
        );
        
        return $request;
    }

    /**
     * Get card type code from display name
     *
     * @param string $type
     * @return string
     */
    public function getCardTypeCode($type)
    {
        $cardTypes = array(
            'VI' => 'VI',
            'MC' => 'MC',
            'AE' => 'AE',
            'DI' => 'DI',
            'JCB' => 'JCB',
            'MI' => 'MI',
            'DN' => 'DN',
            'CUP' => 'CUP'
        );
        
        return isset($cardTypes[$type]) ? $cardTypes[$type] : '';
    }
}
