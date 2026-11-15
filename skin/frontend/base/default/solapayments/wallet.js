/**
 * Google Pay and Apple Pay through Sola (YSRTech_Solapayments), with Cardknox iFields as in
 * Sola's Magento 2 module.
 *
 * Choosing Google Pay or Apple Pay in the payment step hides the checkout's Pay button and shows
 * the wallet's button. The wallet's sheet charges the checkout's current total (base grand
 * total, kept up to date as the summary changes). When the customer approves, the wallet's
 * encrypted payment data (base64) is posted with the order as
 * payment[solapayments_wallet_token]; the server sends it to Sola. If placing the order fails,
 * the sheet reports the failure and the error is shown on the page.
 *
 * Needs YSRTech_UnifiedCheckout 1.2+ (UnifiedCheckout.validate / totals / place results).
 * Settings: window.solapaymentsConfig.wallets (Block/Scripts.php).
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var cfg = window.solapaymentsConfig;
    var W = cfg && cfg.wallets;
    if (!W || (!W.googlepay && !W.applepay)) { return; }
    var text = cfg.text;
    var uc = null;
    var codes = {};
    Object.keys(W).forEach(function (key) { codes[W[key].code] = key; });

    function box(key) { return document.querySelector('[data-sp-wallet="' + key + '"]'); }
    function radio(key) { return document.querySelector('[data-payment-method][value="' + W[key].code + '"]'); }

    function showError(key, message) {
        var el = box(key) && box(key).querySelector('[data-sp-wallet-error]');
        if (!el) { return; }
        el.textContent = message || '';
        el.hidden = !message;
    }

    function totals() {
        var t = uc.totals();
        return { amount: Number(t.amount || 0).toFixed(2), currency: t.currency || 'USD', country: t.country || 'US' };
    }

    /** checks before the wallet's sheet opens; returns an error message or '' */
    function readyToPay() {
        if (!uc.validate()) { return text.walletCheckForm; }
        if (Number(totals().amount) <= 0) { return text.walletZero; }
        return '';
    }

    /** places the order with the wallet's token; rejects with the error */
    function placeOrder(key, token) {
        showError(key, '');
        var extra = { 'payment[method]': W[key].code, 'payment[solapayments_wallet_token]': token };
        return uc.place(extra).then(function (result) {
            if (result && result.ok) { return result; }
            var message = (result && result.error) || text.walletFailed;
            showError(key, message);
            var dup = box(key) && box(key).querySelector('[data-sp-duplicate]');
            if (dup && message.indexOf(text.duplicate) === 0) { dup.hidden = false; }
            throw new Error(message);
        });
    }

    // ------------------------------------------------------------ Google Pay
    var google = {
        enabled: false,
        init: function () {
            var c = W.googlepay;
            return {
                merchantInfo: { merchantName: c.merchantName },
                buttonOptions: { buttonColor: c.buttonColor || 'default', buttonType: c.buttonType || 'pay', buttonSizeMode: 'fill' },
                environment: c.environment,
                billingParameters: { emailRequired: false, billingAddressRequired: false },
                shippingParameters: { shippingAddressRequired: false },
                onGetTransactionInfo: google.transactionInfo,
                onBeforeProcessPayment: google.beforeProcess,
                onProcessPayment: google.process,
                onPaymentCanceled: function () { showError('googlepay', text.walletCanceled); },
                onGPButtonLoaded: google.loaded,
                isDebug: /[?&](is)?debug/i.test(window.location.search)
            };
        },
        transactionInfo: function () {
            var t = totals();
            return {
                displayItems: [{ label: W.googlepay.storeName || 'Total', type: 'SUBTOTAL', price: t.amount }],
                countryCode: t.country,
                currencyCode: t.currency,
                totalPriceStatus: 'FINAL',
                totalPrice: t.amount,
                totalPriceLabel: 'Total'
            };
        },
        beforeProcess: function () {
            return new Promise(function (resolve, reject) {
                showError('googlepay', '');
                var error = readyToPay();
                if (error === '') { resolve(window.iStatus.success); } else { showError('googlepay', error); reject(error); }
            });
        },
        process: function (response) {
            var token = response && response.paymentData && response.paymentData.paymentMethodData
                && response.paymentData.paymentMethodData.tokenizationData && response.paymentData.paymentMethodData.tokenizationData.token;
            if (!token) { return Promise.reject(new Error(text.walletFailed)); }
            return placeOrder('googlepay', btoa(token));
        },
        loaded: function (response) {
            var button = box('googlepay') && box('googlepay').querySelector('[data-sp-wallet-button]');
            if (response && response.status === window.iStatus.success) {
                if (button) { button.hidden = false; }
            } else {
                showError('googlepay', (response && response.reason) || text.walletFailed);
            }
        },
        enable: function () {
            var frame = document.querySelector('[data-sp-wallet="googlepay"] iframe[data-ifields-id="igp"]');
            if (!frame || frame.getAttribute('data-sp-enabled') || !window.ckGooglePay) { return; }
            frame.setAttribute('data-sp-enabled', '1');
            try {
                // iFields pings the frame and initialises the button once it has loaded
                window.ckGooglePay.enableGooglePay({ iframeField: frame, initFunction: google.init });
                google.enabled = true;
            } catch (e) {
                showError('googlepay', text.walletFailed);
                if (window.console) { console.error('Google Pay', e); }
            }
        }
    };

    // ------------------------------------------------------------ Apple Pay
    var apple = {
        enabled: false,
        supported: function () {
            try { return !!(window.ApplePaySession && window.ApplePaySession.canMakePayments()); } catch (e) { return false; }
        },
        init: function () {
            var c = W.applepay, t = totals();
            return {
                buttonOptions: {
                    buttonContainer: 'ap-container',
                    buttonColor: (window.APButtonColor && window.APButtonColor[c.buttonColor]) || 'black',
                    buttonType: (window.APButtonType && window.APButtonType[c.buttonType]) || 'pay'
                },
                merchantIdentifier: c.merchantIdentifier,
                countryCode: c.merchantCountry || t.country,
                currencyCode: t.currency,
                requiredBillingContactFields: [],
                requiredShippingContactFields: [],
                onGetTransactionInfo: apple.transactionInfo,
                onValidateMerchant: apple.validateMerchant,
                onBeforeProcessPayment: apple.beforeProcess,
                onPaymentAuthorize: apple.authorize,
                onPaymentComplete: function () {},
                onAPButtonLoaded: apple.loaded,
                onError: function (e) { if (window.console) { console.error('Apple Pay', e); } },
                isDebug: /[?&](is)?debug/i.test(window.location.search)
            };
        },
        transactionInfo: function () {
            return { total: { type: 'final', label: W.applepay.storeName || 'Total', amount: totals().amount } };
        },
        validateMerchant: function () {
            // Sola validates the Apple Pay session for registered domains (as in the Magento 2 module)
            return fetch('https://api.cardknox.com/applepay/validate', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
                .then(function (r) {
                    if (!r.ok) { throw new Error('Apple Pay merchant validation failed (' + r.status + ')'); }
                    return r.text();
                });
        },
        beforeProcess: function () {
            showError('applepay', '');
            var error = readyToPay();
            if (error !== '') { showError('applepay', error); return Promise.reject(error); }
            return Promise.resolve(window.iStatus.success);
        },
        authorize: function (payload) {
            if (!payload || !payload.token || !payload.token.paymentData) { return Promise.reject(new Error(text.walletFailed)); }
            return placeOrder('applepay', btoa(JSON.stringify(payload.token.paymentData)));
        },
        loaded: function (response) {
            var button = document.getElementById('ap-container');
            if (response && response.status === window.iStatus.success) {
                if (button) { button.hidden = false; }
            } else if (response && response.status === window.iStatus.unsupported) {
                hideMethod('applepay');
            } else {
                showError('applepay', (response && response.reason) || text.walletFailed);
            }
        },
        enable: function () {
            var container = document.getElementById('ap-container');
            if (!container || container.getAttribute('data-sp-enabled') || !window.ckApplePay) { return; }
            container.setAttribute('data-sp-enabled', '1');
            try {
                window.ckApplePay.enableApplePay({ initFunction: apple.init });
                apple.enabled = true;
            } catch (e) {
                showError('applepay', text.walletFailed);
                if (window.console) { console.error('Apple Pay', e); }
            }
        }
    };

    /** a wallet this browser cannot use is taken out of the list */
    function hideMethod(key) {
        var r = radio(key);
        if (!r) { return; }
        var label = r.closest('label');
        if (label) { label.hidden = true; }
        r.disabled = true;
        var form = document.querySelector('[data-payment-form="' + W[key].code + '"]');
        if (form) { form.hidden = true; }
        if (r.checked) {
            r.checked = false;
            var other = document.querySelector('[data-payment-method]:not(:disabled)');
            if (other) { other.checked = true; other.dispatchEvent(new Event('change', { bubbles: true })); other.click(); }
        }
    }

    function setup() {
        if (W.applepay && !apple.supported()) { hideMethod('applepay'); }
        if (W.googlepay) { google.enable(); }
        if (W.applepay && apple.supported()) { apple.enable(); }
        syncPayButton();
    }

    /** the checkout's Pay button is replaced by the wallet's button */
    function syncPayButton() {
        if (!uc) { return; }
        var method = uc.selectedMethod();
        uc.setPayButton({ hidden: !!codes[method] });
    }

    function start() {
        uc = window.UnifiedCheckout;
        if (!uc || typeof uc.totals !== 'function') { return; }
        var tokenKey = W.googlepay ? W.googlepay.tokenKey : W.applepay.tokenKey;
        var c = W.googlepay || W.applepay;
        if (tokenKey && typeof window.setAccount === 'function') { window.setAccount(tokenKey, c.softwareName, c.softwareVersion); }
        setup();
        document.addEventListener('unifiedcheckout:payment', syncPayButton);
        document.addEventListener('unifiedcheckout:sections', function (e) {
            if (e.detail.names.indexOf('payment') >= 0) { setup(); }
        });
        document.addEventListener('unifiedcheckout:totals', function () {
            try {
                if (google.enabled) { window.ckGooglePay.updateAmount(); }
                if (apple.enabled) { window.ckApplePay.updateAmount(); }
            } catch (e) {
                if (window.console) { console.error('wallet amount', e); }
            }
        });
    }

    window.solapaymentsWallets = { google: google, apple: apple, googleInit: google.init };
    // after every DOMContentLoaded handler ran (the checkout's API is created in one)
    setTimeout(start, 0);
});
