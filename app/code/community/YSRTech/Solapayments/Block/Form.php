<?php

/**
 * Payment form: the customer's saved cards, or a new card (number, expiry, security code) and
 * "Save this card".
 */
class YSRTech_Solapayments_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('solapayments/form.phtml');
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     */
    public function getQuote()
    {
        $info = $this->getMethod()->getInfoInstance();
        return $info instanceof Mage_Sales_Model_Quote_Payment ? $info->getQuote() : null;
    }

    public function getCustomerId()
    {
        $quote = $this->getQuote();
        return $quote ? (int) $quote->getCustomerId() : 0;
    }

    /**
     * Saved, unexpired cards of the customer (default first).
     *
     * @return YSRTech_Solapayments_Model_Card[]
     */
    public function getSavedCards()
    {
        if (!$this->getCustomerId() || !Mage::helper('solapayments')->isVaultEnabled()) {
            return [];
        }
        $cards = [];
        foreach (Mage::getResourceModel('solapayments/card_collection')->addCustomerFilter($this->getCustomerId()) as $card) {
            if (!$card->isExpired()) {
                $cards[] = $card;
            }
        }
        return $cards;
    }

    /**
     * The card to preselect: the one chosen before on this quote, else the default card.
     */
    public function getSelectedCardId(array $cards)
    {
        $chosen = (int) $this->getMethod()->getInfoInstance()->getAdditionalInformation('card_id');
        foreach ($cards as $card) {
            if ((int) $card->getId() === $chosen) {
                return $chosen;
            }
        }
        return $cards ? (int) reset($cards)->getId() : 0;
    }

    public function canSaveCard()
    {
        return $this->getCustomerId() > 0 && Mage::helper('solapayments')->isVaultEnabled();
    }

}
