<?php

/**
 * Cardknox gateway client: posts form fields, returns the parsed answer.
 *
 * Every request carries xKey (transaction key), xVersion, xSoftwareName/Version, xIP.
 * Answer fields of note: xResult (A approved, D declined, E error, V verify / 3DS),
 * xStatus, xError, xErrorCode, xRefNum, xToken, xMaskedCardNumber, xCardType, xExp,
 * xAuthCode, xAvsResult, xCvvResult, xBatch.
 */
class YSRTech_Solapayments_Model_Api
{
    public const RESULT_APPROVED = 'A';
    public const RESULT_DECLINED = 'D';
    public const RESULT_ERROR    = 'E';
    public const RESULT_VERIFY   = 'V';

    /**
     * @param array       $fields request fields (without the common ones)
     * @param mixed       $store
     * @param string|null $url    another endpoint (3D Secure verify), default the gateway URL
     * @return array answer fields
     * @throws Mage_Core_Exception when the gateway cannot be reached
     */
    public function request(array $fields, $store = null, $url = null)
    {
        $helper = Mage::helper('solapayments');
        $fields = array_merge([
            'xKey'                 => $helper->getTransactionKey($store),
            'xVersion'             => '5.0.0',
            'xSoftwareName'        => $helper->getSoftwareName(),
            'xSoftwareVersion'     => YSRTech_Solapayments_Helper_Data::VERSION,
            'xIP'                  => $helper->getIpAddress(),
            'xSupports64BitRefnum' => 'true',
        ], array_filter($fields, fn ($v) => $v !== null && $v !== ''));

        $helper->log(['request' => $fields], $store);
        $curl = curl_init($url ?: $helper->getGatewayUrl($store));
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $body === '') {
            $helper->log('Gateway not reachable: ' . $error, $store);
            Mage::throwException($helper->__('The payment service could not be reached. Please try again.'));
        }
        parse_str((string) $body, $result);
        $helper->log(['response' => $result], $store);
        return $result;
    }
}
