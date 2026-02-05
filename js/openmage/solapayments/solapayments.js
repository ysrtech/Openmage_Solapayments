/**
 * Sola Payments Extension for Magento 1
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 */

var SolaPayments = Class.create();

SolaPayments.prototype = {
    tokenKey: null,
    config: null,
    recaptchaToken: null,

    initialize: function(tokenKey, config) {
        this.tokenKey = tokenKey;
        this.config = config || {};
        this.initEventListeners();
    },

    initEventListeners: function() {
        // Setup event listeners for form submission
        var paymentForm = document.querySelector('form[id*="co-payment-form"]') || 
                         document.querySelector('form[name*="payment"]');
        
        if (paymentForm) {
            // Handle form submission
            var self = this;
            paymentForm.addEventListener('submit', function(e) {
                if (self.isPaymentMethodSelected()) {
                    // Validation will be handled before submission
                }
            });
        }
    },

    isPaymentMethodSelected: function() {
        var method = document.querySelector('input[name="payment[method]"][value="solapayments"]');
        return method && method.checked;
    },

    validateCardNumber: function(cardNumber) {
        // Luhn algorithm validation
        cardNumber = cardNumber.replace(/\s+/g, '');
        if (!/^\d+$/.test(cardNumber)) {
            return false;
        }
        
        var sum = 0;
        var isEven = false;
        
        for (var i = cardNumber.length - 1; i >= 0; i--) {
            var digit = parseInt(cardNumber.charAt(i), 10);
            
            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }
            
            sum += digit;
            isEven = !isEven;
        }
        
        return (sum % 10) === 0;
    },

    validateExpiration: function(month, year) {
        var now = new Date();
        var currentYear = now.getFullYear();
        var currentMonth = now.getMonth() + 1;
        
        var expYear = parseInt(year, 10);
        var expMonth = parseInt(month, 10);
        
        if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
            return false;
        }
        
        return true;
    },

    validateCVV: function(cvv, cardType) {
        cvv = cvv.replace(/\s+/g, '');
        
        if (!/^\d+$/.test(cvv)) {
            return false;
        }
        
        // American Express uses 4-digit CVV
        if (cardType === 'AE') {
            return cvv.length === 4;
        }
        
        // Other card types use 3-digit CVV
        return cvv.length === 3;
    },

    formatCardNumber: function(cardNumber) {
        return cardNumber.replace(/\s+/g, '');
    },

    getCardType: function(cardNumber) {
        cardNumber = this.formatCardNumber(cardNumber);
        
        if (/^4[0-9]{12}(?:[0-9]{3})?$/.test(cardNumber)) {
            return 'VI'; // Visa
        } else if (/^5[1-5][0-9]{14}$/.test(cardNumber)) {
            return 'MC'; // MasterCard
        } else if (/^3[47][0-9]{13}$/.test(cardNumber)) {
            return 'AE'; // American Express
        } else if (/^6(?:011|5[0-9]{2})[0-9]{12}$/.test(cardNumber)) {
            return 'DI'; // Discover
        } else if (/^(?:2131|1800|35\d{3})\d{11}$/.test(cardNumber)) {
            return 'JCB'; // JCB
        } else if (/^30[0-5][0-9]{11}$/.test(cardNumber)) {
            return 'DN'; // Diners Club
        }
        
        return null;
    },

    showError: function(message) {
        var errorDiv = document.getElementById('solapayments-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'solapayments-error';
            errorDiv.className = 'error-msg';
            var formContainer = document.querySelector('form');
            if (formContainer) {
                formContainer.parentNode.insertBefore(errorDiv, formContainer);
            }
        }
        errorDiv.innerHTML = message;
        errorDiv.style.display = 'block';
    },

    clearError: function() {
        var errorDiv = document.getElementById('solapayments-error');
        if (errorDiv) {
            errorDiv.innerHTML = '';
            errorDiv.style.display = 'none';
        }
    }
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('[SolaPayments] DOMContentLoaded fired');
    if (typeof solaPaymentsConfig !== 'undefined') {
        console.log('[SolaPayments] Config detected, initializing');
        window.solaPayments = new SolaPayments(
            solaPaymentsConfig.tokenKey,
            solaPaymentsConfig
        );
    } else {
        console.warn('[SolaPayments] solaPaymentsConfig is undefined');
    }
});
