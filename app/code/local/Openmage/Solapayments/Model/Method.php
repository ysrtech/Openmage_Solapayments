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

class Openmage_Solapayments_Model_Method extends Mage_Payment_Model_Method_Cc
{
    const PAYMENT_METHOD_SOLAPAYMENTS_CODE = 'solapayments';

    protected $_code = self::PAYMENT_METHOD_SOLAPAYMENTS_CODE;
    protected $_formBlockType = 'solapayments/form';
    protected $_infoBlockType = 'solapayments/info';
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = true;
    protected $_isInitializeNeeded = false;
    protected $_canSaveCc = false;

    protected $_allowCurrencyCodes = array('USD', 'EUR', 'GBP', 'CAD', 'AUD');

    /**
     * Get helper instance
     *
     * @return Openmage_Solapayments_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('solapayments');
    }

    /**
     * Check whether payment method can be used
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        // Determine scope
        $storeId = $quote ? $quote->getStoreId() : null;
        $helper = $this->_getHelper();
        $helper->log('isAvailable() called - StoreId: ' . $storeId);

        // Must be enabled in config
        if (!$this->getConfigData('active', $storeId)) {
            $helper->log('isAvailable() - NOT AVAILABLE: Method not enabled');
            return false;
        }

        // Respect frontend vs admin usage
        if (Mage::app()->getStore()->isAdmin()) {
            if (!$this->canUseInternal()) {
                $helper->log('isAvailable() - NOT AVAILABLE: Cannot use internal');
                return false;
            }
        } else {
            if (!$this->canUseCheckout()) {
                $helper->log('isAvailable() - NOT AVAILABLE: Cannot use checkout');
                return false;
            }
        }

        // Require quote to validate country/currency/amount
        if ($quote) {
            $billingCountry = $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : null;
            if ($billingCountry && !$this->canUseForCountry($billingCountry)) {
                $helper->log('isAvailable() - NOT AVAILABLE: Country not allowed - ' . $billingCountry);
                return false;
            }

            $currencyCode = $quote->getBaseCurrencyCode();
            if ($currencyCode && !$this->canUseForCurrency($currencyCode)) {
                $helper->log('isAvailable() - NOT AVAILABLE: Currency not supported - ' . $currencyCode);
                return false;
            }

            $total = $quote->getBaseGrandTotal();
            $minTotal = $this->getConfigData('min_order_total', $storeId);
            $maxTotal = $this->getConfigData('max_order_total', $storeId);

            if (!is_null($minTotal) && $minTotal > 0 && $total < $minTotal) {
                $helper->log('isAvailable() - NOT AVAILABLE: Order total below minimum');
                return false;
            }
            if (!is_null($maxTotal) && $maxTotal > 0 && $total > $maxTotal) {
                $helper->log('isAvailable() - NOT AVAILABLE: Order total above maximum');
                return false;
            }
        }

        // Check if credentials are configured
        $tokenKey = $helper->getTokenKey();
        $gatewayUrl = $helper->getGatewayUrl();
        if (!$tokenKey || !$gatewayUrl) {
            $helper->log('isAvailable() - NOT AVAILABLE: Missing credentials (TokenKey: ' . ($tokenKey ? 'Yes' : 'No') . ', GatewayUrl: ' . ($gatewayUrl ? 'Yes' : 'No') . ')');
            return false;
        }

        $helper->log('isAvailable() - AVAILABLE');
        return true;
    }

    /**
     * Check method for processing with base currency
     *
     * @param string $code
     * @return boolean
     */
    public function canUseForCurrency($code)
    {
        if (!in_array($code, $this->_allowCurrencyCodes)) {
            return false;
        }
        return true;
    }

    /**
     * Authorize payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Openmage_Solapayments_Model_Method
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $helper = $this->_getHelper();
        $helper->log('authorize() called - Amount: ' . $amount);
        
        if ($amount <= 0) {
            $helper->log('authorize() - ERROR: Invalid amount');
            Mage::throwException(Mage::helper('solapayments')->__('Invalid amount for authorization.'));
        }

        try {
            $payment->setAmount($amount);
            
            // Build request
            $request = $helper->buildAuthorizationRequest($payment, $amount);
            
            $helper->log('Authorization Request built - Command: ' . $request['xCommand']);
            
            // Send request
            $response = $helper->sendRequest($request);
            
            $helper->log('Authorization Response: xError=' . (isset($response['xError']) ? $response['xError'] : 'N/A'));
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                $helper->log('authorize() - ERROR: ' . $errorMsg);
                Mage::throwException(Mage::helper('solapayments')->__('Payment authorization failed: %s', $errorMsg));
            }
            
            // Set transaction ID and status
            if (isset($response['xRefNum'])) {
                $payment->setTransactionId($response['xRefNum']);
                $payment->setIsTransactionClosed(false);
                $payment->setIsTransactionPending(false);
                $helper->log('authorize() - SUCCESS: RefNum=' . $response['xRefNum']);
            }
            
            // Store authorization code if available
            if (isset($response['xAuthCode'])) {
                $payment->setAdditionalInformation('authorization_code', $response['xAuthCode']);
            }
            
        } catch (Exception $e) {
            $helper->log('authorize() - EXCEPTION: ' . $e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Openmage_Solapayments_Model_Method
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('solapayments')->__('Invalid amount for capture.'));
        }

        try {
            $helper = $this->_getHelper();
            $paymentAction = $helper->getPaymentAction();
            
            // If payment action is authorize_capture, do the full sale transaction
            if ($paymentAction == 'authorize_capture') {
                return $this->authorize($payment, $amount);
            }
            
            // Otherwise, capture an authorized transaction
            if (!$payment->getParentTransactionId() && !$payment->getLastTransId()) {
                Mage::throwException(Mage::helper('solapayments')->__('Invalid transaction ID for capture.'));
            }
            
            // Build capture request
            $request = $helper->buildCaptureRequest($payment, $amount);
            
            $helper->log('Capture Request: ' . print_r($request, true));
            
            // Send request
            $response = $helper->sendRequest($request);
            
            $helper->log('Capture Response: ' . print_r($response, true));
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                Mage::throwException(Mage::helper('solapayments')->__('Payment capture failed: %s', $errorMsg));
            }
            
            // Set transaction information
            if (isset($response['xRefNum'])) {
                $payment->setTransactionId($response['xRefNum']);
                $payment->setIsTransactionClosed(true);
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Refund capture
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Openmage_Solapayments_Model_Method
     */
    public function refund(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('solapayments')->__('Invalid amount for refund.'));
        }

        try {
            $helper = $this->_getHelper();
            
            if (!$payment->getParentTransactionId() && !$payment->getLastTransId()) {
                Mage::throwException(Mage::helper('solapayments')->__('Invalid transaction ID for refund.'));
            }
            
            // Build refund request
            $request = $helper->buildRefundRequest($payment, $amount);
            
            $helper->log('Refund Request: ' . print_r($request, true));
            
            // Send request
            $response = $helper->sendRequest($request);
            
            $helper->log('Refund Response: ' . print_r($response, true));
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                Mage::throwException(Mage::helper('solapayments')->__('Payment refund failed: %s', $errorMsg));
            }
            
            // Set transaction information
            if (isset($response['xRefNum'])) {
                $payment->setTransactionId($response['xRefNum']);
                $payment->setIsTransactionClosed(true);
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Void payment
     *
     * @param Varien_Object $payment
     * @return Openmage_Solapayments_Model_Method
     */
    public function void(Varien_Object $payment)
    {
        try {
            $helper = $this->_getHelper();
            
            if (!$payment->getParentTransactionId() && !$payment->getLastTransId()) {
                Mage::throwException(Mage::helper('solapayments')->__('Invalid transaction ID for void.'));
            }
            
            // Build void request
            $request = $helper->buildVoidRequest($payment);
            
            $helper->log('Void Request: ' . print_r($request, true));
            
            // Send request
            $response = $helper->sendRequest($request);
            
            $helper->log('Void Response: ' . print_r($response, true));
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                Mage::throwException(Mage::helper('solapayments')->__('Payment void failed: %s', $errorMsg));
            }
            
            // Set transaction information
            if (isset($response['xRefNum'])) {
                $payment->setTransactionId($response['xRefNum']);
                $payment->setIsTransactionClosed(true);
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Cancel payment
     *
     * @param Varien_Object $payment
     * @return Openmage_Solapayments_Model_Method
     */
    public function cancel(Varien_Object $payment)
    {
        try {
            if ($payment->getLookupReference()) {
                return $this->void($payment);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Validate payment method information object
     * Override to skip CC validation during AJAX calls (e.g., Idev OneStepCheckout)
     *
     * @return Openmage_Solapayments_Model_Method
     */
    public function validate()
    {
        $helper = $this->_getHelper();
        $helper->log('validate() called');
        
        $payment = $this->getInfoInstance();
        
        if ($payment instanceof Mage_Sales_Model_Quote_Payment) {
            $currency_code = $payment->getQuote()->getBaseCurrencyCode();
        } else {
            $currency_code = $payment->getOrder()->getBaseCurrencyCode();
        }

        if (!$this->canUseForCurrency($currency_code)) {
            Mage::throwException(
                Mage::helper('solapayments')->__('Selected currency code (%s) is not compatible with this payment method.', $currency_code)
            );
        }

        // Check if CC data is actually present - if not, skip validation (Idev compatibility)
        $ccNumber = $payment->getCcNumber();
        if (empty($ccNumber)) {
            $helper->log('validate() - CC number empty, skipping parent validation (likely AJAX/initial load)');
            return $this;
        }

        // Check if this is an AJAX request (Idev OneStepCheckout compatibility)
        $isAjax = Mage::app()->getRequest()->isXmlHttpRequest() || 
                  Mage::app()->getRequest()->getParam('isAjax') ||
                  strpos(Mage::app()->getRequest()->getRequestUri(), '/ajax/') !== false;
        
        if ($isAjax) {
            $helper->log('validate() - AJAX request detected, skipping CC validation');
            // Skip parent CC validation during AJAX - full validation happens at order placement
            return $this;
        }
        
        $helper->log('validate() - Normal request, performing full validation');
        // Normal flow - call parent validation
        return parent::validate();
    }

    /**
     * Assign data to info model instance
     * Override to handle empty CC data during AJAX calls
     *
     * @param mixed $data
     * @return Openmage_Solapayments_Model_Method
     */
    public function assignData($data)
    {
        $helper = $this->_getHelper();
        $helper->log('assignData() called');
        
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        
        $helper->log('assignData() - CC Number present: ' . ($data->getCcNumber() ? 'Yes' : 'No'));
        $helper->log('assignData() - CC Type: ' . $data->getCcType());
        
        // Call parent to assign the data
        return parent::assignData($data);
    }
}
