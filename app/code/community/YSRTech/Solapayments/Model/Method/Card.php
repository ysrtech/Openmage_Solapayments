<?php

/**
 * Sola Payments card method ("solapayments").
 *
 * The customer pays with a new card entered in Cardknox iFields (the browser sends single-use
 * tokens xCardNum / xCVV and the expiry) or with one of their saved cards (Cardknox xToken).
 * After a successful payment a new card is saved when the customer ticked "Save this card".
 *
 *   authorize            cc:authonly (xRequireSplitCapturable with split capture)
 *   capture, no auth     cc:sale            (payment action "authorize and capture")
 *   capture of an auth   cc:capture, or cc:splitcapture with split capture
 *   refund               cc:refund, or cc:voidrefund for the whole order
 *   void / cancel        cc:void
 *
 * 3D Secure: when the gateway answers xResult=V the order is not placed; the checkout runs the
 * bank's challenge in the browser (iFields), the result is verified with Cardknox
 * (ThreedsController) and kept in the checkout session, and the order is placed again: this
 * method then uses the verified transaction instead of charging a second time.
 */
class YSRTech_Solapayments_Model_Method_Card extends Mage_Payment_Model_Method_Abstract
{
    public const CODE = 'solapayments';
    /** checkout session: transaction approved by 3D Secure verification, ['quote_id', 'amount', 'response'] */
    public const SESSION_VERIFIED = 'solapayments_3ds_verified';
    /** checkout session key read by YSRTech_UnifiedCheckout (OnepageController::CLIENT_ACTION) */
    public const SESSION_CLIENT_ACTION = 'unifiedcheckout_client_action';

    /** answer fields kept on the payment (shown in the admin) */
    public const INFO_KEYS = ['xMaskedCardNumber', 'xCardType', 'xExp', 'xAuthCode', 'xAuthAmount', 'xBatch', 'xRefNum',
                              'xAvsResult', 'xAvsResultCode', 'xCvvResult', 'xCvvResultCode'];

    protected $_code = self::CODE;
    protected $_formBlockType = 'solapayments/form';
    protected $_infoBlockType = 'solapayments/info';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;

    // ------------------------------------------------------------------ checkout data

    public function isAvailable($quote = null)
    {
        $helper = Mage::helper('solapayments');
        $store = $quote ? $quote->getStoreId() : null;
        return parent::isAvailable($quote) && $helper->getTransactionKey($store) !== '' && $helper->getTokenKey($store) !== '';
    }

    /**
     * Posted fields (payment[...]): solapayments_card_id (a saved card, or empty for a new
     * card), xCardNum / xCVV (iFields tokens), cc_exp_month / cc_exp_year, solapayments_save_card,
     * solapayments_allow_duplicate.
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $cardId = (int) $data->getData('solapayments_card_id');
        $info->setAdditionalInformation('card_id', $cardId ?: null);
        $info->setAdditionalInformation('allow_duplicate', $data->getData('solapayments_allow_duplicate') ? 1 : null);
        if ($cardId) {
            $info->setAdditionalInformation('xCardNum', null)->setAdditionalInformation('xCVV', null);
            $info->setAdditionalInformation('save_card', null);
            $card = Mage::getModel('solapayments/request')->getSavedCard($info);
            if ($card) {
                $info->setCcType($card->getCardType())->setCcLast4($card->getLast4())
                    ->setCcExpMonth($card->getExpMonth())->setCcExpYear($card->getExpYear());
            }
        } else {
            $info->setAdditionalInformation('xCardNum', (string) $data->getData('xCardNum'));
            $info->setAdditionalInformation('xCVV', (string) $data->getData('xCVV'));
            $info->setAdditionalInformation('save_card', $data->getData('solapayments_save_card') ? 1 : null);
            $year = (int) $data->getData('cc_exp_year');
            $info->setCcExpMonth((int) $data->getData('cc_exp_month'))
                ->setCcExpYear($year && $year < 100 ? 2000 + $year : $year)
                ->setCcLast4(null)->setCcType(null);
        }
        return $this;
    }

    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance();
        $helper = Mage::helper('solapayments');
        if ($info->getAdditionalInformation('card_id')) {
            $card = Mage::getModel('solapayments/request')->getSavedCard($info);
            if (!$card) {
                Mage::throwException($helper->__('This saved card is not available. Please choose another card.'));
            }
            if ($card->isExpired()) {
                Mage::throwException($helper->__('This saved card has expired. Please use another card.'));
            }
            return $this;
        }
        if ($this->_getVerified()) {
            return $this;   // 3D Secure already approved this payment
        }
        if (!$info->getAdditionalInformation('xCardNum') || !$info->getAdditionalInformation('xCVV')) {
            Mage::throwException($helper->__('Please enter your card number and security code.'));
        }
        $month = (int) $info->getCcExpMonth();
        $year = (int) $info->getCcExpYear();
        if ($month < 1 || $month > 12 || !$year || mktime(0, 0, 0, $month + 1, 1, $year) <= time()) {
            Mage::throwException($helper->__('Please enter a valid expiry date.'));
        }
        return $this;
    }

    // ------------------------------------------------------------------ gateway operations

    public function authorize(Varien_Object $payment, $amount)
    {
        $this->_newCardTransaction($payment, $amount, 'cc:authonly');
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $authorization = $payment->getParentTransactionId();
        if (!$authorization) {
            $this->_newCardTransaction($payment, $amount, 'cc:sale');
            return $this;
        }
        $request = Mage::getModel('solapayments/request');
        $response = $this->_api()->request($request->buildCapture($payment, $amount, $authorization), $payment->getOrder()->getStoreId());
        $this->_requireApproved($response);
        $payment->setTransactionId($response['xRefNum'])->setIsTransactionClosed(0);
        if (!empty($response['xBatch'])) {
            $payment->setAdditionalInformation('xBatch', $response['xBatch']);
        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $helper = Mage::helper('solapayments');
        $whole = abs((float) $amount - (float) $order->getBaseGrandTotal()) < 0.005;
        $response = $this->_api()->request([
            'xCommand' => $whole ? 'cc:voidrefund' : 'cc:refund',
            'xAmount'  => $helper->formatPrice($amount),
            'xRefNum'  => $payment->getParentTransactionId() ?: $payment->getLastTransId(),
        ], $order->getStoreId());
        $this->_requireApproved($response);
        $invoice = $payment->getCreditmemo() ? $payment->getCreditmemo()->getInvoice() : null;
        $payment->setTransactionId($response['xRefNum'] ?? ($payment->getParentTransactionId() . '-refund'))
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(!$invoice || !$invoice->canRefund());
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        $refNum = $payment->getParentTransactionId() ?: $payment->getLastTransId();
        $response = $this->_api()->request(['xCommand' => 'cc:void', 'xRefNum' => $refNum], $payment->getOrder()->getStoreId());
        $this->_requireApproved($response);
        $payment->setTransactionId(($response['xRefNum'] ?? $refNum) . '-void')
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        if ($payment->getAuthorizationTransaction() && !$payment->getAuthorizationTransaction()->getIsClosed()) {
            return $this->void($payment);
        }
        return $this;
    }

    // ------------------------------------------------------------------ internals

    /**
     * cc:authonly or cc:sale on the new card or saved card of the payment.
     */
    protected function _newCardTransaction(Mage_Sales_Model_Order_Payment $payment, $amount, $command)
    {
        $order = $payment->getOrder();
        $verified = $this->_getVerified();
        if ($verified && abs((float) $verified['amount'] - (float) $amount) < 0.005) {
            // approved by 3D Secure verification: use that transaction, do not charge again
            $this->_clearVerified();
            $this->_applyApproved($payment, $verified['response']);
            return;
        }
        $request = Mage::getModel('solapayments/request');
        $card = $request->getSavedCard($payment);
        $response = $this->_api()->request($request->buildCardTransaction($payment, $amount, $command), $order->getStoreId());

        if (($response['xResult'] ?? '') === YSRTech_Solapayments_Model_Api::RESULT_VERIFY
            && Mage::helper('solapayments')->isThreeDsEnabled($order->getStoreId())
            && !Mage::app()->getStore()->isAdmin()
        ) {
            $this->_requestThreeDs($payment, $amount, $response);
        }
        $this->_requireApproved($response);
        $this->_applyApproved($payment, $response);
        if ($card) {
            $card->setLastUsedAt(Varien_Date::now())->save();
        }
    }

    /**
     * Keeps an approved answer on the payment and saves the card when asked to.
     */
    protected function _applyApproved(Mage_Sales_Model_Order_Payment $payment, array $response)
    {
        $payment->setTransactionId($response['xRefNum'])
            ->setLastTransId($response['xRefNum'])
            ->setCcTransId($response['xRefNum'])
            ->setIsTransactionClosed(0);
        foreach (self::INFO_KEYS as $key) {
            if (isset($response[$key]) && $response[$key] !== '') {
                $payment->setAdditionalInformation($key, $response[$key]);
            }
        }
        if (!empty($response['xMaskedCardNumber'])) {
            $payment->setCcLast4(substr($response['xMaskedCardNumber'], -4));
        }
        if (!empty($response['xCardType'])) {
            $payment->setCcType($response['xCardType']);
        }
        if (!empty($response['xAvsResultCode'])) {
            $payment->setCcAvsStatus($response['xAvsResultCode']);
        }
        if (!empty($response['xCvvResultCode'])) {
            $payment->setCcCidStatus($response['xCvvResultCode']);
        }
        if ($payment->getAdditionalInformation('save_card')) {
            $this->_saveCard($payment, $response);
        }
        // single-use tokens are of no further use (not unsAdditionalInformation(): for a key that
        // is not set it clears all additional information)
        $payment->setAdditionalInformation('xCardNum', null)->setAdditionalInformation('xCVV', null);
    }

    /**
     * Saves the card of an approved transaction for the order's customer (once per card).
     */
    protected function _saveCard(Mage_Sales_Model_Order_Payment $payment, array $response)
    {
        $order = $payment->getOrder();
        $customerId = (int) $order->getCustomerId();
        $token = (string) ($response['xToken'] ?? '');
        if (!$customerId || $token === '' || !Mage::helper('solapayments')->isVaultEnabled($order->getStoreId())) {
            return;
        }
        try {
            $exp = (string) ($response['xExp'] ?? sprintf('%02d%02d', $payment->getCcExpMonth(), $payment->getCcExpYear() % 100));
            $month = (int) substr($exp, 0, 2);
            $year = 2000 + (int) substr($exp, 2, 2);
            $last4 = substr((string) ($response['xMaskedCardNumber'] ?? ''), -4);
            $type = (string) ($response['xCardType'] ?? '');
            $fingerprint = sha1(strtolower($type) . '|' . $last4 . '|' . $exp);

            $cards = Mage::getResourceModel('solapayments/card_collection')->addFieldToFilter('customer_id', $customerId);
            $card = null;
            foreach ($cards as $existing) {
                if ($existing->getFingerprint() === $fingerprint) {
                    $card = $existing;
                }
            }
            $card = $card ?: Mage::getModel('solapayments/card')->setCustomerId($customerId)->setIsDefault(count($cards) ? 0 : 1);
            $card->addData([
                'token'        => $token,
                'card_type'    => $type,
                'last4'        => $last4,
                'exp_month'    => $month,
                'exp_year'     => $year,
                'fingerprint'  => $fingerprint,
                'last_used_at' => Varien_Date::now(),
            ])->save();
            $payment->setAdditionalInformation('saved_card_id', $card->getId());
        } catch (Exception $e) {
            Mage::logException($e);   // the payment itself succeeded
        }
    }

    /**
     * Stops order placement and asks the checkout to run the bank's 3D Secure challenge.
     */
    protected function _requestThreeDs(Mage_Sales_Model_Order_Payment $payment, $amount, array $response)
    {
        $session = Mage::getSingleton('checkout/session');
        unset($response['xKey']);
        $session->setData(self::SESSION_CLIENT_ACTION, [
            'type'     => 'solapayments_3ds',
            'response' => $response,
            'amount'   => (float) $amount,
        ]);
        $session->setData('solapayments_3ds_pending', [
            'quote_id' => (int) $session->getQuoteId(),
            'amount'   => (float) $amount,
            'invoice'  => $payment->getOrder()->getIncrementId(),
            'save_card' => $payment->getAdditionalInformation('save_card') ? 1 : 0,
        ]);
        Mage::throwException(Mage::helper('solapayments')->__('Your bank asks you to confirm this payment.'));
    }

    /**
     * Throws the customer-facing message unless the gateway approved.
     */
    protected function _requireApproved(array $response)
    {
        if (($response['xResult'] ?? '') === YSRTech_Solapayments_Model_Api::RESULT_APPROVED && !empty($response['xRefNum'])) {
            return;
        }
        $map = Mage::getModel('solapayments/errorMap');
        Mage::log(sprintf('Sola Payments declined: %s %s (%s)', $response['xErrorCode'] ?? '', $response['xError'] ?? '', $response['xRefNum'] ?? ''), Zend_Log::WARN, 'solapayments.log', true);
        Mage::throwException($map->getMessage($response));
    }

    /**
     * Transaction approved by 3D Secure for the current quote, if any.
     */
    protected function _getVerified()
    {
        $verified = Mage::getSingleton('checkout/session')->getData(self::SESSION_VERIFIED);
        if (!is_array($verified) || (int) ($verified['quote_id'] ?? 0) !== (int) Mage::getSingleton('checkout/session')->getQuoteId()) {
            return null;
        }
        return $verified;
    }

    protected function _clearVerified()
    {
        Mage::getSingleton('checkout/session')->unsetData(self::SESSION_VERIFIED);
    }

    /**
     * @return YSRTech_Solapayments_Model_Api
     */
    protected function _api()
    {
        return Mage::getSingleton('solapayments/api');
    }
}
