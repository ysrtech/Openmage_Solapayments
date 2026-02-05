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

class Openmage_Solapayments_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('openmage/solapayments/info.phtml');
    }

    /**
     * Get information (protected) from payment object
     *
     * @param string $key
     * @return mixed
     */
    public function getInfo($key = '')
    {
        $payment = $this->getPayment();
        $info = array();

        if ($payment->getLastTransId()) {
            $info['transaction_id'] = $payment->getLastTransId();
        }

        if ($payment->getAdditionalInformation('authorization_code')) {
            $info['authorization_code'] = $payment->getAdditionalInformation('authorization_code');
        }

        if ($key) {
            return isset($info[$key]) ? $info[$key] : null;
        }

        return $info;
    }

    /**
     * Get payment transaction ID
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->getPayment()->getLastTransId();
    }

    /**
     * Get authorization code
     *
     * @return string
     */
    public function getAuthorizationCode()
    {
        return $this->getPayment()->getAdditionalInformation('authorization_code');
    }
}
