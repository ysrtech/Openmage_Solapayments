/**
 * Sola Payments Extension for Magento 1 - Apple Pay
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 */

var SolaPaymentsApplePay = Class.create();

SolaPaymentsApplePay.prototype = {
    tokenKey: null,
    config: null,
    merchantIdentifier: null,
    displayName: null,
    session: null,

    initialize: function(config) {
        this.config = config || {};
        this.tokenKey = config.tokenKey;
        this.merchantIdentifier = config.merchantIdentifier;
        this.displayName = config.displayName || 'Sola Payments';
        this.initApplePay();
    },

    initApplePay: function() {
        if (!window.ApplePaySession) {
            console.warn('Apple Pay not available on this device');
            return;
        }

        if (!ApplePaySession.canMakePayments()) {
            console.warn('This device cannot make Apple Pay payments');
            return;
        }

        this.attachApplePayButton();
    },

    attachApplePayButton: function() {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'apple-pay-button';
        button.setAttribute('style', 
            '-webkit-appearance: -apple-system-attrib; width: 100%; height: 44px; ' +
            'border-radius: 4px; background: black; color: white; font-size: 14px; ' +
            'font-weight: 500; cursor: pointer;'
        );
        button.textContent = 'Pay with Apple Pay';
        button.onclick = this.onApplePayClicked.bind(this);

        var buttonContainer = document.getElementById('apple-pay-button-container');
        if (buttonContainer) {
            buttonContainer.appendChild(button);
        }
    },

    onApplePayClicked: function(e) {
        e.preventDefault();

        var request = this.getApplePayRequest();
        this.session = new ApplePaySession(3, request);

        this.session.onvalidatemerchant = this.onValidateMerchant.bind(this);
        this.session.onpaymentauthorized = this.onPaymentAuthorized.bind(this);
        this.session.oncancel = this.onCancel.bind(this);

        this.session.begin();
    },

    getApplePayRequest: function() {
        return {
            countryCode: this.config.countryCode || 'US',
            currencyCode: this.config.currencyCode || 'USD',
            supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
            merchantCapabilities: ['supports3DS', 'supportsEMV'],
            total: {
                label: this.displayName,
                amount: this.getOrderTotal(),
                type: 'final'
            }
        };
    },

    onValidateMerchant: function(event) {
        // In production, you would validate the merchant certificate server-side
        var validationURL = event.validationURL;
        
        // For this implementation, you would need to call your server
        // to get a session from Apple
        this.session.completeMerchantValidation({});
    },

    onPaymentAuthorized: function(event) {
        var payment = event.payment;
        
        // Extract token
        var token = btoa(JSON.stringify(payment.token.paymentData));
        
        // Store token in form
        document.getElementById('solapayments_apple_pay_token').value = token;
        
        // Complete the transaction
        this.session.completePayment(ApplePaySession.STATUS_SUCCESS);
        
        // Submit checkout form
        var form = document.querySelector('form[name="co-payment-form"]') || 
                   document.querySelector('form[id*="co-payment"]');
        if (form) {
            form.submit();
        }
    },

    onCancel: function(event) {
        console.log('Apple Pay cancelled');
    },

    getOrderTotal: function() {
        var totalElement = document.querySelector('[data-bind*="orderData"]') ||
                          document.querySelector('.grand-total');
        if (totalElement) {
            var totalText = totalElement.textContent;
            var matches = totalText.match(/\d+\.?\d*/);
            return matches ? matches[0] : '0.00';
        }
        return '0.00';
    }
};

document.addEventListener('DOMContentLoaded', function() {
    if (typeof solaApplePayConfig !== 'undefined') {
        window.solaApplePay = new SolaPaymentsApplePay(solaApplePayConfig);
    }
});
