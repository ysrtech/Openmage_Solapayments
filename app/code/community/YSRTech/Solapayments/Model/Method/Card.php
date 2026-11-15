<?php

/**
 * Sola Payments card method ("solapayments").
 *
 * The customer pays with a card typed into the payment form (number, expiry, security code) or
 * with one of their saved cards (Cardknox xToken). After a successful payment a new card is
 * saved when the customer ticked "Save this card" (only Sola's token and the card's type, last
 * four digits and expiry are kept).
 *
 * The card number and security code are kept in memory for the request that places the order
 * (self::$_postedCard) and sent to Cardknox; they are never stored on the quote or order, in
 * additional information, or in logs.
 *
 *   authorize            cc:authonly (xRequireSplitCapturable with split capture)
 *   capture, no auth     cc:sale            (payment action "authorize and capture")
 *   capture of an auth   cc:capture, or cc:splitcapture with split capture
 *   refund               cc:refund, or cc:voidrefund for the whole order
 *   void / cancel        cc:void
 */
class YSRTech_Solapayments_Model_Method_Card extends Mage_Payment_Model_Method_Abstract
{
    public const CODE = 'solapayments';

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

    /** @var array|null ['number' => digits, 'cvv' => digits] of the card posted in this request */
    protected static $_postedCard = null;

    /**
     * The card number and security code posted in this request (never stored).
     */
    public static function getPostedCard()
    {
        return self::$_postedCard;
    }

    // ------------------------------------------------------------------ checkout data

    public function isAvailable($quote = null)
    {
        $helper = Mage::helper('solapayments');
        $store = $quote ? $quote->getStoreId() : null;
        return parent::isAvailable($quote) && $helper->getTransactionKey($store) !== '';
    }

    /**
     * Posted fields (payment[...]): solapayments_card_id (a saved card, or empty for a new card),
     * cc_number, cc_cid, cc_exp_month, cc_exp_year, solapayments_save_card,
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
            self::$_postedCard = null;
            $info->setAdditionalInformation('save_card', null);
            $card = Mage::getModel('solapayments/request')->getSavedCard($info);
            if ($card) {
                $info->setCcType($card->getCardType())->setCcLast4($card->getLast4())
                    ->setCcExpMonth($card->getExpMonth())->setCcExpYear($card->getExpYear());
            }
            return $this;
        }
        $number = preg_replace('/\D/', '', (string) $data->getData('cc_number'));
        $cvv = preg_replace('/\D/', '', (string) $data->getData('cc_cid'));
        if ($number !== '') {
            self::$_postedCard = ['number' => $number, 'cvv' => $cvv];
        }
        $info->setAdditionalInformation('save_card', $data->getData('solapayments_save_card') ? 1 : null);
        $year = (int) $data->getData('cc_exp_year');
        $info->setCcExpMonth((int) $data->getData('cc_exp_month'))
            ->setCcExpYear($year && $year < 100 ? 2000 + $year : $year)
            ->setCcLast4($number !== '' ? substr($number, -4) : null)
            ->setCcType($number !== '' ? Mage::helper('solapayments')->getCardBrand($number) : null);
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
        $card = self::$_postedCard;
        if (!$card || !Mage::helper('solapayments')->isValidCardNumber($card['number'])) {
            Mage::throwException($helper->__('Please enter a valid card number.'));
        }
        if (!preg_match('/^\d{3,4}$/', $card['cvv'])) {
            Mage::throwException($helper->__('Please enter the security code (3 or 4 digits on the card).'));
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
        $authorization = $this->_refNum($authorization);
        $response = $this->_api()->request($request->buildCapture($payment, $amount, $authorization), $payment->getOrder()->getStoreId());
        $this->_requireApproved($response);
        // Cardknox answers a capture with the authorization's reference number: keep both records
        $txnId = (string) $response['xRefNum'] === (string) $authorization ? $authorization . '-capture' : $response['xRefNum'];
        $payment->setTransactionId($txnId)->setIsTransactionClosed(0);
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
            'xRefNum'  => $this->_refNum($payment->getParentTransactionId() ?: $payment->getLastTransId()),
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
        $refNum = $this->_refNum($payment->getParentTransactionId() ?: $payment->getLastTransId());
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
        $request = Mage::getModel('solapayments/request');
        $card = $request->getSavedCard($payment);
        $response = $this->_api()->request($request->buildCardTransaction($payment, $amount, $command), $order->getStoreId());

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
        self::$_postedCard = null;   // not needed any more
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
     * Cardknox reference number of a transaction id ("123-capture" -> "123").
     */
    protected function _refNum($transactionId)
    {
        return preg_replace('/-(capture|void|refund)$/', '', (string) $transactionId);
    }

    /**
     * @return YSRTech_Solapayments_Model_Api
     */
    protected function _api()
    {
        return Mage::getSingleton('solapayments/api');
    }
}
