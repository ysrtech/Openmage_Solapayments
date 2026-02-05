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

class Openmage_Solapayments_Model_Method_Applepay extends Mage_Payment_Model_Method_Abstract
{
    const PAYMENT_METHOD_APPLEPAY_CODE = 'solapayments_apple_pay';

    protected $_code = self::PAYMENT_METHOD_APPLEPAY_CODE;
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
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;

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
     * Authorize payment using Apple Pay token
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Openmage_Solapayments_Model_Method_Applepay
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('solapayments')->__('Invalid amount for authorization.'));
        }

        try {
            $payment->setAmount($amount);
            
            $helper = $this->_getHelper();
            
            // Get Apple Pay token from additional info
            $apayToken = $payment->getAdditionalInformation('apple_pay_token');
            if (!$apayToken) {
                Mage::throwException(Mage::helper('solapayments')->__('Apple Pay token not provided.'));
            }
            
            // Build request for Apple Pay transaction
            $order = $payment->getOrder();
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xSoftwareName' => 'Magento',
                'xSoftwareVersion' => Mage::getVersion(),
                'xCommand' => 'cc:auth',
                'xToken' => $apayToken,
                'xAmount' => $helper->formatPrice($amount),
                'xCurrency' => $order->getBaseCurrencyCode(),
                'xInvoice' => $order->getIncrementId(),
                'xOrderNumber' => $order->getIncrementId(),
            );
            
            $helper->log('Apple Pay Authorization Request: ' . print_r($request, true));
            
            // Send request
            $response = $helper->sendRequest($request);
            
            $helper->log('Apple Pay Authorization Response: ' . print_r($response, true));
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                Mage::throwException(Mage::helper('solapayments')->__('Apple Pay authorization failed: %s', $errorMsg));
            }
            
            // Set transaction ID and status
            if (isset($response['xRefNum'])) {
                $payment->setTransactionId($response['xRefNum']);
                $payment->setIsTransactionClosed(false);
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Capture payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Openmage_Solapayments_Model_Method_Applepay
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('solapayments')->__('Invalid amount for capture.'));
        }

        try {
            $helper = $this->_getHelper();
            
            // For Apple Pay, authorize_capture is typically used
            return $this->authorize($payment, $amount);
            
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
     * @return Openmage_Solapayments_Model_Method_Applepay
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
            
            $helper->log('Apple Pay Refund Request: ' . print_r($request, true));
            
            // Send request
            $response = $helper->sendRequest($request);
            
            // Check response
            if (!isset($response['xError']) || $response['xError'] != '0') {
                $errorMsg = isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Unknown error';
                Mage::throwException(Mage::helper('solapayments')->__('Apple Pay refund failed: %s', $errorMsg));
            }
            
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
}
