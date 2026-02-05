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

class Openmage_Solapayments_Model_ThreeDs
{
    /**
     * Check if 3D Secure is enabled
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return (bool) Mage::getStoreConfig('payment/solapayments/3ds_enabled');
    }

    /**
     * Get 3D Secure challenge URL
     *
     * @param string $refNum
     * @return string
     */
    public static function getChallengeUrl($refNum)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:3ds_verify',
                'xRefNum' => $refNum,
            );
            
            $response = $helper->sendRequest($request);
            
            if (isset($response['xChallengeURL'])) {
                return $response['xChallengeURL'];
            }
            
            return null;
        } catch (Exception $e) {
            $helper->log('3D Secure Challenge Error: ' . $e->getMessage(), Zend_Log::ERR);
            return null;
        }
    }

    /**
     * Verify 3D Secure authentication
     *
     * @param string $refNum
     * @param string $cavv
     * @param string $eci
     * @param string $xid
     * @return bool
     */
    public static function verify3DSecure($refNum, $cavv, $eci, $xid)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:3ds_result',
                'xRefNum' => $refNum,
                'xCAVV' => $cavv,
                'xECI' => $eci,
                'xXID' => $xid,
            );
            
            $response = $helper->sendRequest($request);
            
            if (!isset($response['xError']) || $response['xError'] != '0') {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            $helper->log('3D Secure Verification Error: ' . $e->getMessage(), Zend_Log::ERR);
            return false;
        }
    }

    /**
     * Initiate 3D Secure authentication
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array
     */
    public static function initiate3DSecure($payment, $amount)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $order = $payment->getOrder();
            
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:3ds_initiate',
                'xAmount' => $helper->formatPrice($amount),
                'xCurrency' => $order->getBaseCurrencyCode(),
                'xInvoice' => $order->getIncrementId(),
            );
            
            // Add card details
            $cardNumber = $payment->getCcNumber();
            if ($cardNumber) {
                $request['xCardNum'] = $cardNumber;
                $request['xExp'] = str_pad($payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT) . 
                                   $payment->getCcExpYear();
            }
            
            $response = $helper->sendRequest($request);
            
            if (!isset($response['xError']) || $response['xError'] != '0') {
                throw new Exception(isset($response['xErrorMessage']) ? 
                    $response['xErrorMessage'] : 'Failed to initiate 3D Secure');
            }
            
            return $response;
        } catch (Exception $e) {
            $helper->log('3D Secure Initiation Error: ' . $e->getMessage(), Zend_Log::ERR);
            throw $e;
        }
    }
}
