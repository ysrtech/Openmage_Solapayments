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

class Openmage_Solapayments_Model_Vault
{
    /**
     * Save card token to vault
     *
     * @param string $customerId
     * @param array $cardData
     * @return string Token
     */
    public static function saveCardToken($customerId, $cardData)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:save',
                'xCardNum' => $cardData['cc_number'],
                'xCardCode' => $cardData['cc_cid'],
                'xExp' => str_pad($cardData['cc_exp_month'], 2, '0', STR_PAD_LEFT) . $cardData['cc_exp_year'],
                'xCustomerId' => $customerId,
            );
            
            $response = $helper->sendRequest($request);
            
            if (!isset($response['xError']) || $response['xError'] != '0') {
                throw new Exception(isset($response['xErrorMessage']) ? $response['xErrorMessage'] : 'Failed to tokenize card');
            }
            
            return isset($response['xToken']) ? $response['xToken'] : null;
        } catch (Exception $e) {
            $helper->log('Vault Error: ' . $e->getMessage(), Zend_Log::ERR);
            throw $e;
        }
    }

    /**
     * Retrieve saved cards for customer
     *
     * @param string $customerId
     * @return array
     */
    public static function getCustomerCards($customerId)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:list',
                'xCustomerId' => $customerId,
            );
            
            $response = $helper->sendRequest($request);
            
            if (!isset($response['xError']) || $response['xError'] != '0') {
                return array();
            }
            
            $cards = array();
            if (isset($response['xTokens'])) {
                $tokens = explode(',', $response['xTokens']);
                foreach ($tokens as $token) {
                    $cards[] = array('token' => trim($token));
                }
            }
            
            return $cards;
        } catch (Exception $e) {
            $helper->log('Vault Retrieval Error: ' . $e->getMessage(), Zend_Log::ERR);
            return array();
        }
    }

    /**
     * Charge using saved token
     *
     * @param string $token
     * @param float $amount
     * @param string $orderId
     * @return array
     */
    public static function chargeWithToken($token, $amount, $orderId)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:sale',
                'xToken' => $token,
                'xAmount' => $helper->formatPrice($amount),
                'xInvoice' => $orderId,
            );
            
            $response = $helper->sendRequest($request);
            
            return $response;
        } catch (Exception $e) {
            $helper->log('Vault Charge Error: ' . $e->getMessage(), Zend_Log::ERR);
            throw $e;
        }
    }

    /**
     * Delete saved card token
     *
     * @param string $token
     * @return bool
     */
    public static function deleteToken($token)
    {
        $helper = Mage::helper('solapayments');
        
        try {
            $request = array(
                'xKey' => $helper->getTokenKey(),
                'xVersion' => '4.0.0',
                'xCommand' => 'cc:delete',
                'xToken' => $token,
            );
            
            $response = $helper->sendRequest($request);
            
            return (!isset($response['xError']) || $response['xError'] == '0');
        } catch (Exception $e) {
            $helper->log('Vault Delete Error: ' . $e->getMessage(), Zend_Log::ERR);
            return false;
        }
    }
}
