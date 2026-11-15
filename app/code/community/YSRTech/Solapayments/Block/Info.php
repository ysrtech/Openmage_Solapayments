<?php

/**
 * Payment info: card type and last four digits; in the admin also the gateway details
 * (reference number, authorization code, AVS and CVV results, batch).
 */
class YSRTech_Solapayments_Block_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $helper = Mage::helper('solapayments');
        $data = [];
        $card = trim($info->getCcType() . ' •••• ' . $info->getCcLast4());
        if ($info->getCcLast4()) {
            $data[$helper->__('Card')] = $card;
        }
        if (!$this->getIsSecureMode()) {
            $labels = [
                'xRefNum' => 'Reference number', 'xAuthCode' => 'Authorization code', 'xAuthAmount' => 'Authorized amount',
                'xBatch' => 'Batch', 'xAvsResult' => 'AVS result', 'xCvvResult' => 'CVV result', 'xExp' => 'Expiry',
            ];
            foreach ($labels as $key => $label) {
                if ($value = $info->getAdditionalInformation($key)) {
                    $data[$helper->__($label)] = $value;
                }
            }
            if ($info->getAdditionalInformation('card_id')) {
                $data[$helper->__('Paid with')] = $helper->__('Saved card');
            }
        }
        return $transport->addData($data);
    }
}
