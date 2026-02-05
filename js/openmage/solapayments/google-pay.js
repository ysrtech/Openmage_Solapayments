/**
 * Sola Payments Extension for Magento 1 - Google Pay
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 */

var SolaPaymentsGooglePay = Class.create();

SolaPaymentsGooglePay.prototype = {
    tokenKey: null,
    config: null,
    merchantId: null,
    merchantName: null,
    environment: 'TEST',

    initialize: function(config) {
        this.config = config || {};
        this.tokenKey = config.tokenKey;
        this.merchantId = config.merchantId;
        this.merchantName = config.merchantName || 'Sola Payments';
        this.environment = config.environment || 'TEST';
        this.initGooglePay();
    },

    initGooglePay: function() {
        if (!window.google || !window.google.payments) {
            console.warn('Google Pay API not loaded');
            return;
        }

        try {
            this.paymentsClient = new google.payments.api.PaymentsClient({
                environment: this.environment
            });

            // Check if Google Pay is available
            var isReadyToPayRequest = this.getIsReadyToPayRequest();
            this.paymentsClient.isReadyToPay(isReadyToPayRequest).then(function(response) {
                if (response.result) {
                    this.attachGooglePayButton();
                }
            }.bind(this));
        } catch (e) {
            console.error('Google Pay initialization error:', e);
        }
    },

    getIsReadyToPayRequest: function() {
        return {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [{
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                    allowedCardNetworks: ['MASTERCARD', 'VISA', 'AMEX', 'DISCOVER']
                }
            }]
        };
    },

    getPaymentDataRequest: function(amount) {
        var paymentDataRequest = this.getIsReadyToPayRequest();
        paymentDataRequest.transactionInfo = {
            totalPriceStatus: 'FINAL',
            totalPrice: amount.toString(),
            currencyCode: this.config.currencyCode || 'USD'
        };
        paymentDataRequest.merchantInfo = {
            merchantId: this.merchantId,
            merchantName: this.merchantName
        };
        paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
        return paymentDataRequest;
    },

    attachGooglePayButton: function() {
        var button = this.paymentsClient.createButton({
            onClick: this.onGooglePayClicked.bind(this),
            allowedPaymentMethods: [{
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                    allowedCardNetworks: ['MASTERCARD', 'VISA', 'AMEX', 'DISCOVER']
                }
            }]
        });

        var buttonContainer = document.getElementById('google-pay-button-container');
        if (buttonContainer) {
            buttonContainer.appendChild(button);
        }
    },

    onGooglePayClicked: function() {
        var paymentDataRequest = this.getPaymentDataRequest(this.getOrderTotal());
        this.paymentsClient.loadPaymentData(paymentDataRequest).then(function(paymentData) {
            this.handlePaymentData(paymentData);
        }.bind(this)).catch(function(err) {
            console.error('Payment error:', err);
        });
    },

    handlePaymentData: function(paymentData) {
        // Extract token from payment data
        var token = paymentData.paymentMethodData.tokenizationData.token;
        
        // Store token in form
        document.getElementById('solapayments_google_pay_token').value = token;
        
        // Submit checkout form
        var form = document.querySelector('form[name="co-payment-form"]') || 
                   document.querySelector('form[id*="co-payment"]');
        if (form) {
            form.submit();
        }
    },

    getOrderTotal: function() {
        // Get order total from page
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
    if (typeof solaGooglePayConfig !== 'undefined') {
        window.solaGooglePay = new SolaPaymentsGooglePay(solaGooglePayConfig);
    }
});
