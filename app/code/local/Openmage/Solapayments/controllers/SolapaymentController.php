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

class Openmage_Solapayments_SolapaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirect action
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getQuoteId());
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Return action
     */
    public function returnAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $order = $session->getLastRealOrder();
        
        if ($order && $order->getId()) {
            $session->setLastSuccessQuoteId($session->getQuoteId());
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Cancel action
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->restoreQuote();
        $this->_redirect('checkout/cart');
    }

    /**
     * Notify action - for IPN/webhook notifications
     */
    public function notifyAction()
    {
        // This is for future webhook implementation
        $this->getResponse()->setHttpResponseCode(200);
    }
}
