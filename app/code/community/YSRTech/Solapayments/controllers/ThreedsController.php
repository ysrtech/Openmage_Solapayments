<?php

/**
 * 3D Secure, second step: the browser ran the bank's challenge (iFields verify3DS) and posts
 * its result here. It is checked with Cardknox (three_ds_verify_url); when approved, the
 * approved transaction is kept in the checkout session and the page places the order again
 * (YSRTech_Solapayments_Model_Method_Card then uses it instead of charging a second time).
 *
 * POST solapayments/threeds/verify: xRefNum, xCavv, xEci, x3dsAuthenticationStatus,
 * x3dsSignatureVerificationStatus, x3dsActionCode, x3dsError, form_key.
 * Answer: {success: true} or {error: message}.
 */
class YSRTech_Solapayments_ThreedsController extends Mage_Core_Controller_Front_Action
{
    public const FIELDS = ['xRefNum', 'xCavv', 'xEci', 'x3dsAuthenticationStatus', 'x3dsSignatureVerificationStatus', 'x3dsActionCode', 'x3dsError'];

    public function verifyAction()
    {
        $helper = Mage::helper('solapayments');
        $session = Mage::getSingleton('checkout/session');
        $pending = $session->getData('solapayments_3ds_pending');
        if (!$this->getRequest()->isPost() || !$this->_validateFormKey()) {
            $this->_json(['error' => $helper->__('Your session has expired. Please reload the page.')]);
            return;
        }
        if (!is_array($pending) || (int) $pending['quote_id'] !== (int) $session->getQuoteId()) {
            $this->_json(['error' => $helper->__('The payment verification has expired. Please try again.')]);
            return;
        }
        $fields = ['xAllowDuplicate' => 1];
        foreach (self::FIELDS as $field) {
            $fields[$field] = (string) $this->getRequest()->getPost($field, '');
        }
        try {
            $store = $session->getQuote()->getStoreId();
            $response = Mage::getSingleton('solapayments/api')->request($fields, $store, (string) $helper->getConfig('three_ds_verify_url', $store));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_json(['error' => $helper->__('The payment could not be verified. Please try again.')]);
            return;
        }
        if (($response['xResult'] ?? '') === YSRTech_Solapayments_Model_Api::RESULT_APPROVED && !empty($response['xRefNum'])) {
            $session->unsetData('solapayments_3ds_pending');
            $session->setData(YSRTech_Solapayments_Model_Method_Card::SESSION_VERIFIED, [
                'quote_id' => (int) $pending['quote_id'],
                'amount'   => (float) $pending['amount'],
                'response' => $response,
            ]);
            $this->_json(['success' => true]);
            return;
        }
        $this->_json(['error' => ($response['xResult'] ?? '') === YSRTech_Solapayments_Model_Api::RESULT_ERROR && !empty($response['xErrorCode'])
            ? Mage::getModel('solapayments/errorMap')->getMessage($response)
            : $helper->__('Your bank did not confirm the payment. Please try again or use another card.')]);
    }

    protected function _json(array $data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
