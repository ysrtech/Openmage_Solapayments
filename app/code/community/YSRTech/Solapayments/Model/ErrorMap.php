<?php

/**
 * Customer-facing messages for Cardknox error codes (xErrorCode), from Sola's Magento 2 module.
 * Codes not listed here show a generic decline message; the gateway text is only logged.
 */
class YSRTech_Solapayments_Model_ErrorMap
{
    public const DUPLICATE = '01332';

    protected static $_messages = [
        '01010' => 'Unable to verify card ID number.', '01011' => 'Customer Name Required', '01013' => 'AVS Error',
        '01016' => 'CVV Required', '01017' => 'Invalid Street', '01023' => 'Refund not permitted',
        '01026' => 'Unmatched Return Not Allowed', '01027' => 'Void Rejected', '01043' => 'No Original',
        '01044' => 'A capture requires that the existing transaction be an auth',
        '01045' => 'The original transaction cannot have this operation performed on it at this time',
        '01060' => 'Partial Refund Not Allowed', '01063' => 'Original Transaction Not Approved',
        '01070' => 'Transaction type is not voidable. Issue refund instead.', '01076' => 'AVS CVV Declined',
        '01094' => 'Invalid CC Account Number', '01100' => 'Transaction Not Found',
        '01119' => 'Transaction is in a state that cannot be refunded', '01120' => 'Transaction already refunded',
        '01121' => 'Transaction not eligible for refund.', '01135' => 'Unable to retrieve expiration date',
        '01137' => 'CVV error.', '01140' => 'Name On Account Required.',
        '01151' => 'Refunds cannot be performed until the transaction has settled', '01170' => 'Phone Field Required',
        '01171' => 'Country Field Required', '01172' => 'Email Field Required',
        '01194' => 'Amount exceeds original transaction amount.', '01196' => 'Zip Required for Keyed Transaction',
        '01201' => 'Refund amount exceeds sale amount', '01204' => 'Invalid Token', '01205' => 'Invalid Zip',
        '01223' => 'Card Number was not between 13 and 16 digits', '01235' => 'Missing CVV', '01251' => 'Card Expired',
        '01271' => 'Billing Information Required', '01280' => 'Cardnumber Mismatch', '01286' => 'No Phone Number Provided',
        '01287' => 'Invalid CVV', '01291' => 'Invalid Email', '01294' => 'Invalid Exp Length',
        '01295' => 'Expiration Date Must Be In Future', '01327' => 'Duplicate Return', '01328' => 'Unmatched card expiry date',
        '01332' => 'Duplicate Transaction', '01333' => 'CVV failed', '01334' => 'Decline', '01337' => 'Invalid account number',
        '01338' => 'Invalid Card Account', '01347' => 'Expired card', '01385' => 'CVV Declined', '01411' => 'Insufficient funds',
        '01462' => 'Transaction Previously Voided', '01463' => 'Invalid RefNum', '01464' => 'Invalid Credit Card',
        '01469' => 'Transaction amount exceeded for test account', '01470' => 'Invalid EXP', '01477' => 'Expiration Date Required',
        '01479' => 'Invalid Card', '01481' => 'Timeout', '01482' => 'Billing Address Mismatch', '01489' => 'Invalid CVV Length',
        '01490' => 'Card Number Error', '01491' => 'Invalid card number', '01494' => 'Use VOID to refund an unsettled transaction',
        '01502' => 'Name exceeds max length', '01514' => 'Invalid Bill Street', '01515' => 'Invalid Bill First Name',
        '01516' => 'Invalid Bill Last Name', '01517' => 'Invalid Ship Street', '01518' => 'Invalid Ship First Name',
        '01519' => 'Invalid Ship Last Name', '01538' => 'CVV Error', '01540' => 'Name Required',
        '01541' => 'Street exceeds max length', '01546' => 'Invalid Name', '01633' => 'Mailing address invalid',
        '01660' => 'Invalid Void After Refund', '01661' => 'Refund on a refund not allowed',
        '01664' => 'CVV Response Code is required', '01678' => 'Refund not permitted, amount exceeds original sale',
        '01681' => 'AVS Street Required', '01689' => 'Capture not allowed on voided auth', '01698' => 'Invalid billing address',
        '01699' => 'Incorrect CVV', '01704' => 'Refund not allowed; issue void', '01721' => 'Transaction can no longer be voided',
        '01758' => 'Transaction already settled, issue refund (P)', '99999' => '3DS Authentication Error',
    ];

    /**
     * Message for a failed gateway answer.
     */
    public function getMessage(array $response)
    {
        $code = (string) ($response['xErrorCode'] ?? '');
        $helper = Mage::helper('solapayments');
        if (isset(self::$_messages[$code])) {
            return $helper->__(self::$_messages[$code]);
        }
        return $helper->__('Your payment was declined. Please check your card details or use another card.');
    }

    public function isDuplicate(array $response)
    {
        return (string) ($response['xErrorCode'] ?? '') === self::DUPLICATE;
    }
}
