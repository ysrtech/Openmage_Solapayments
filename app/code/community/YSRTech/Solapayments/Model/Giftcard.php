<?php

/**
 * Sola gift cards through Cardknox's JSON gateway (payment/solapayments/giftcard_url,
 * default https://x1.cardknox.com/gatewayjson):
 *
 *   balance  gift:balance  xCardNum                      -> xStatus, xActivationStatus, xRemainingBalance
 *   redeem   gift:redeem   xCardNum, xAmount, xInvoice   -> xStatus, xRefNum, xMaskedCardNumber, xAuthAmount
 *   issue    gift:issue    xCardNum, xAmount, xInvoice   (puts an amount back on the card)
 *
 * Answers with xStatus "Approved" are successful; anything else carries xError.
 */
class YSRTech_Solapayments_Model_Giftcard
{
    public const DEFAULT_URL = 'https://x1.cardknox.com/gatewayjson';

    public function isEnabled($store = null)
    {
        $helper = Mage::helper('solapayments');
        return $helper->getConfigFlag('giftcard_enabled', $store) && $helper->getTransactionKey($store) !== '';
    }

    public function getTitle($store = null)
    {
        return (string) (Mage::helper('solapayments')->getConfig('giftcard_title', $store) ?: Mage::helper('solapayments')->__('Gift card'));
    }

    public function balance($code, $store = null)
    {
        return $this->_request(['xCommand' => 'gift:balance', 'xCardNum' => $code], $store);
    }

    /**
     * Takes an amount (base currency) off the card for an order.
     */
    public function redeem($code, $amount, Mage_Sales_Model_Order $order)
    {
        return $this->_request(array_merge([
            'xCommand' => 'gift:redeem',
            'xCardNum' => $code,
            'xAmount'  => Mage::helper('solapayments')->formatPrice($amount),
            'xInvoice' => $order->getIncrementId(),
        ], Mage::getModel('solapayments/request')->getAddressFields($order)), $order->getStoreId());
    }

    /**
     * Puts an amount (base currency) back on the card (refund, cancellation, failed order).
     */
    public function issue($code, $amount, Mage_Sales_Model_Order $order)
    {
        return $this->_request(array_merge([
            'xCommand' => 'gift:issue',
            'xCardNum' => $code,
            'xAmount'  => Mage::helper('solapayments')->formatPrice($amount),
            'xInvoice' => $order->getIncrementId(),
        ], Mage::getModel('solapayments/request')->getAddressFields($order)), $order->getStoreId());
    }

    public function isApproved(array $response)
    {
        return ($response['xStatus'] ?? '') === 'Approved';
    }

    public function getError(array $response)
    {
        return (string) ($response['xError'] ?? Mage::helper('solapayments')->__('The gift card could not be used.'));
    }

    protected function _request(array $fields, $store)
    {
        $helper = Mage::helper('solapayments');
        $fields = array_merge([
            'xKey'                 => $helper->getTransactionKey($store),
            'xVersion'             => '5.0.0',
            'xSoftwareName'        => $helper->getSoftwareName(),
            'xSoftwareVersion'     => YSRTech_Solapayments_Helper_Data::VERSION,
            'xIP'                  => $helper->getIpAddress(),
            'xExistingCustomer'    => 'TRUE',
            'xTimeoutSeconds'      => '10',
            'xSupports64BitRefnum' => true,
        ], array_filter($fields, fn ($v) => $v !== null && $v !== ''));
        $helper->log(['giftcard request' => $fields], $store);
        $url = (string) ($helper->getConfig('giftcard_url', $store) ?: self::DEFAULT_URL);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $body = curl_exec($curl);
        curl_close($curl);
        $response = json_decode((string) $body, true);
        $helper->log(['giftcard response' => $response ?: $body], $store);
        if (!is_array($response)) {
            return ['xStatus' => 'Error', 'xError' => $helper->__('The gift card service could not be reached. Please try again.')];
        }
        return $response;
    }
}
