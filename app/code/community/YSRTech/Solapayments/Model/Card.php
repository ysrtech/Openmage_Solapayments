<?php

/**
 * A saved card (Cardknox token) of a customer.
 *
 * @method int getCustomerId()
 * @method string getToken()
 * @method string getCardType()
 * @method string getLast4()
 * @method int getExpMonth()
 * @method int getExpYear()
 */
class YSRTech_Solapayments_Model_Card extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('solapayments/card');
    }

    public function isExpired()
    {
        if (!$this->getExpYear() || !$this->getExpMonth()) {
            return false;
        }
        $lastDay = mktime(23, 59, 59, (int) $this->getExpMonth() + 1, 0, (int) $this->getExpYear());
        return $lastDay < Mage::getModel('core/date')->timestamp();
    }

    /**
     * e.g. "Visa •••• 4242 (12/28)"
     */
    public function getLabel()
    {
        $label = trim(($this->getCardType() ?: Mage::helper('solapayments')->__('Card')) . ' •••• ' . $this->getLast4());
        if ($this->getExpMonth() && $this->getExpYear()) {
            $label .= sprintf(' (%02d/%02d)', $this->getExpMonth(), $this->getExpYear() % 100);
        }
        return $label;
    }

    public function getExpiryLabel()
    {
        return $this->getExpMonth() ? sprintf('%02d/%02d', $this->getExpMonth(), $this->getExpYear() % 100) : '';
    }
}
