<?php

/**
 * Quote total "solapayments_giftcard" (after the grand total): the applied gift card pays
 * min(card balance, grand total) of the order, shipping and tax included.
 */
class YSRTech_Solapayments_Model_Total_Quote extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('solapayments_giftcard');
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $address->setBaseSolapaymentsGiftcardAmount(0)->setSolapaymentsGiftcardAmount(0);
        $quote = $address->getQuote();
        $items = $this->_getAddressItems($address);
        $balance = (float) $quote->getBaseSolapaymentsGiftcardBalance();
        if (!count($items) || !$quote->getSolapaymentsGiftcardCode() || $balance <= 0
            || !Mage::getSingleton('solapayments/giftcard')->isEnabled($quote->getStoreId())
        ) {
            if (count($items)) {
                $quote->setBaseSolapaymentsGiftcardAmount(0)->setSolapaymentsGiftcardAmount(0);
            }
            return $this;
        }
        $baseAmount = min($balance, max(0, (float) $address->getBaseGrandTotal()));
        if ($baseAmount <= 0) {
            return $this;
        }
        $amount = $quote->getStore()->roundPrice($quote->getStore()->convertPrice($baseAmount));
        $amount = min($amount, (float) $address->getGrandTotal());
        $address->setBaseSolapaymentsGiftcardAmount($baseAmount)->setSolapaymentsGiftcardAmount($amount);
        $address->setBaseGrandTotal($address->getBaseGrandTotal() - $baseAmount);
        $address->setGrandTotal($address->getGrandTotal() - $amount);
        $quote->setBaseSolapaymentsGiftcardAmount($baseAmount)->setSolapaymentsGiftcardAmount($amount);
        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $amount = (float) $address->getSolapaymentsGiftcardAmount();
        if ($amount > 0) {
            $code = (string) $address->getQuote()->getSolapaymentsGiftcardCode();
            $address->addTotal([
                'code'  => $this->getCode(),
                'title' => Mage::getSingleton('solapayments/giftcard')->getTitle() . ' (' . Mage::helper('solapayments')->maskGiftcard($code) . ')',
                'value' => -$amount,
            ]);
        }
        return $this;
    }
}
