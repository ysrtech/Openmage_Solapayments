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

class Openmage_Solapayments_Block_Form extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        Mage::helper('solapayments')->log('Form Block _construct() called');
        $this->setTemplate('openmage/solapayments/form.phtml');
        Mage::helper('solapayments')->log('Form template set: openmage/solapayments/form.phtml');
    }

    /**
     * Get Cardknox Token Key
     *
     * @return string
     */
    public function getTokenKey()
    {
        $token = Mage::helper('solapayments')->getTokenKey();
        Mage::helper('solapayments')->log('getTokenKey() called - Length: ' . strlen($token));
        return $token;
    }

    /**
     * Check if reCAPTCHA is enabled
     *
     * @return bool
     */
    public function isRecaptchaEnabled()
    {
        $enabled = Mage::helper('solapayments')->isRecaptchaEnabled();
        Mage::helper('solapayments')->log('isRecaptchaEnabled() called - Value: ' . ($enabled ? 'Yes' : 'No'));
        return $enabled;
    }

    /**
     * Get reCAPTCHA Site Key
     *
     * @return string
     */
    public function getRecaptchaSiteKey()
    {
        $siteKey = Mage::helper('solapayments')->getRecaptchaSiteKey();
        Mage::helper('solapayments')->log('getRecaptchaSiteKey() called');
        return $siteKey;
    }
}
