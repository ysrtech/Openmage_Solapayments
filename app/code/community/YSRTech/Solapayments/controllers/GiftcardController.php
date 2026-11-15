<?php

/**
 * Sola gift card in the checkout (JSON, POST with form key):
 *   apply    code   checks the balance (gift:balance) and applies the card to the quote
 *   remove          removes it
 *   balance  code   tells the remaining balance
 * Answer: {success, message} or {error}. The card is only charged when the order is placed.
 */
class YSRTech_Solapayments_GiftcardController extends Mage_Core_Controller_Front_Action
{
    public function applyAction()
    {
        if (!($quote = $this->_quote())) {
            return;
        }
        $code = $this->_code();
        $giftcard = Mage::getSingleton('solapayments/giftcard');
        $helper = Mage::helper('solapayments');
        if ($code === '') {
            $this->_json(['error' => $helper->__('Please enter your gift card number.')]);
            return;
        }
        if ((float) $quote->getBaseGrandTotal() <= 0 && !$quote->getSolapaymentsGiftcardCode()) {
            $this->_json(['error' => $helper->__('Your order total is zero; no gift card is needed.')]);
            return;
        }
        $response = $giftcard->balance($code, $quote->getStoreId());
        $balance = (float) ($response['xRemainingBalance'] ?? 0);
        if (!$giftcard->isApproved($response)) {
            $this->_json(['error' => $giftcard->getError($response)]);
            return;
        }
        if (($response['xActivationStatus'] ?? '') !== 'Active') {
            $this->_json(['error' => $helper->__('This gift card is not active.')]);
            return;
        }
        if ($balance <= 0) {
            $this->_json(['error' => $helper->__('This gift card has no balance left.')]);
            return;
        }
        $quote->setSolapaymentsGiftcardCode($code)->setBaseSolapaymentsGiftcardBalance($balance);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        $this->_json(['success' => true, 'message' => $helper->__(
            '%s of your gift card was applied.',
            $quote->getStore()->formatPrice($quote->getSolapaymentsGiftcardAmount(), false)
        )]);
    }

    public function removeAction()
    {
        if (!($quote = $this->_quote())) {
            return;
        }
        $quote->setSolapaymentsGiftcardCode(null)->setBaseSolapaymentsGiftcardBalance(null)
            ->setBaseSolapaymentsGiftcardAmount(0)->setSolapaymentsGiftcardAmount(0);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        $this->_json(['success' => true, 'message' => Mage::helper('solapayments')->__('The gift card was removed.')]);
    }

    public function balanceAction()
    {
        if (!($quote = $this->_quote())) {
            return;
        }
        $giftcard = Mage::getSingleton('solapayments/giftcard');
        $response = $giftcard->balance($this->_code() ?: (string) $quote->getSolapaymentsGiftcardCode(), $quote->getStoreId());
        if (!$giftcard->isApproved($response)) {
            $this->_json(['error' => $giftcard->getError($response)]);
            return;
        }
        $this->_json(['success' => true, 'message' => Mage::helper('solapayments')->__(
            'Gift card balance: %s',
            Mage::app()->getStore()->getBaseCurrency()->formatTxt((float) ($response['xRemainingBalance'] ?? 0))
        )]);
    }

    /**
     * The checkout quote, when the request is a valid POST and gift cards are on.
     */
    protected function _quote()
    {
        $helper = Mage::helper('solapayments');
        if (!$this->getRequest()->isPost() || !$this->_validateFormKey()) {
            $this->_json(['error' => $helper->__('Your session has expired. Please reload the page.')]);
            return null;
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getId() || !$quote->hasItems() || !Mage::getSingleton('solapayments/giftcard')->isEnabled($quote->getStoreId())) {
            $this->_json(['error' => $helper->__('Gift cards cannot be used for this order.')]);
            return null;
        }
        return $quote;
    }

    protected function _code()
    {
        return preg_replace('/[^0-9A-Za-z]/', '', (string) $this->getRequest()->getPost('code', ''));
    }

    protected function _json(array $data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
