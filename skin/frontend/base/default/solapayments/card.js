/**
 * Sola Payments card form (YSRTech_Solapayments): Cardknox iFields, saved cards, 3D Secure.
 *
 * Card number and security code are iframes served by Cardknox. Before the order is sent, the
 * iFields library swaps them for single-use tokens (getTokens), written into
 * payment[xCardNum] / payment[xCVV]. This page never sees the card number.
 *
 * Works with YSRTech_UnifiedCheckout (window.UnifiedCheckout hooks), and with the standard
 * OpenMage onepage checkout (wraps Payment.prototype.save; no 3D Secure there).
 * Settings: window.solapaymentsConfig (Block/Scripts.php).
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    // the settings are printed after this file (Block/Scripts.php), so read them now
    var cfg = window.solapaymentsConfig;
    if (!cfg) { return; }
    var CODE = 'solapayments';
    var state = { card: { len: 0, valid: false }, cvv: { len: 0, valid: false }, account: false, threeDs: false, verifying: false };

    var fieldStyle = {
        'border': '0', 'outline': 'none', 'box-shadow': 'none', 'background': 'transparent',
        'width': '100%', 'height': '44px', 'padding': '0 12px', 'margin': '0', 'box-sizing': 'border-box',
        'font-size': '15px', 'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
        'color': '#1a1a1a'
    };

    function form() { return document.querySelector('[data-solapayments]'); }
    function q(sel) { var f = form(); return f ? f.querySelector(sel) : null; }

    function usesSavedCard() {
        var chosen = q('[data-sp-card]:checked');
        return !!(chosen && chosen.value);
    }

    // ------------------------------------------------------------ iFields
    function markField(name, ok, len) {
        var box = q('[data-sp-field="' + name + '"]');
        if (!box) { return; }
        box.classList.toggle('is-invalid', len > 0 && !ok);
        box.classList.toggle('is-valid', len > 0 && ok);
    }

    function onInput(data) {
        if (!data) { return; }
        state.card.len = data.cardNumberFormattedLength || data.cardNumberLength || 0;
        state.card.valid = !!data.cardNumberIsValid;
        state.cvv.len = data.cvvLength || 0;
        state.cvv.valid = data.issuer === 'unknown' ? state.cvv.len >= 3 : !!data.cvvIsValid;
        markField('card-number', state.card.valid, state.card.len);
        if (data.lastIfieldChanged === 'cvv' || state.cvv.len > 0) { markField('cvv', state.cvv.valid, state.cvv.len); }
        var brand = q('[data-sp-field="card-number"]');
        if (brand) { brand.setAttribute('data-issuer', data.issuer || ''); }
    }

    function initFields() {
        if (!form() || typeof window.setAccount !== 'function') { return; }
        if (!state.account) {
            if (typeof window.enableAutoFormatting === 'function') { window.enableAutoFormatting(' '); }
            window.setAccount(cfg.tokenKey, cfg.softwareName, cfg.softwareVersion);
            window.addIfieldCallback('input', onInput);
            window.addIfieldCallback('issuerupdated', onInput);
            state.account = true;
        }
        window.setIfieldStyle('card-number', fieldStyle);
        window.setIfieldStyle('cvv', fieldStyle);
    }

    // 3D Secure must be enabled once, after the iframes have loaded (Cardinal error 10004 otherwise)
    function initThreeDs() {
        if (!cfg.threeDs || state.threeDs || typeof window.enable3DS !== 'function') { return; }
        state.threeDs = true;
        window.enable3DS(cfg.threeDsEnv || 'production', onThreeDsResult);
    }

    // ------------------------------------------------------------ form behaviour
    function bindForm() {
        var f = form();
        if (!f || f.getAttribute('data-sp-bound')) { return; }
        f.setAttribute('data-sp-bound', '1');
        f.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-card]')) {
                q('[data-sp-new]').hidden = usesSavedCard();
            }
        });
        var expiry = q('[data-sp-expiry]');
        if (expiry) {
            expiry.addEventListener('input', function () {
                var digits = expiry.value.replace(/\D/g, '').slice(0, 4);
                if (digits.length === 1 && digits > '1') { digits = '0' + digits; }
                expiry.value = digits.length > 2 ? digits.slice(0, 2) + ' / ' + digits.slice(2) : digits;
                expiry.classList.remove('is-invalid');
            });
        }
    }

    function readExpiry() {
        var expiry = q('[data-sp-expiry]');
        var m = expiry ? expiry.value.replace(/\s/g, '').match(/^(\d{2})\/?(\d{2})$/) : null;
        if (!m) { return null; }
        var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10);
        var now = new Date();
        if (month < 1 || month > 12 || year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) { return null; }
        return { month: month, year: year };
    }

    /**
     * Before the order is sent: nothing for a saved card; for a new card check the fields and
     * get the iFields tokens.
     */
    function beforePlace() {
        var dup = q('[data-sp-duplicate]');
        if (usesSavedCard()) { return Promise.resolve(); }
        var exp = readExpiry();
        if (!exp) {
            q('[data-sp-expiry]').classList.add('is-invalid');
            return Promise.reject(new Error(cfg.text.expiryInvalid));
        }
        q('[data-sp-exp-month]').value = exp.month;
        q('[data-sp-exp-year]').value = exp.year;
        if (state.card.len <= 0) { return Promise.reject(new Error(cfg.text.cardRequired)); }
        if (!state.card.valid) { return Promise.reject(new Error(cfg.text.cardInvalid)); }
        if (state.cvv.len <= 0) { return Promise.reject(new Error(cfg.text.cvvRequired)); }
        if (!state.cvv.valid) { return Promise.reject(new Error(cfg.text.cvvInvalid)); }
        return new Promise(function (resolve, reject) {
            window.getTokens(function () { resolve(); }, function () {
                var err = q('[data-ifields-id="card-data-error"]');
                reject(new Error((err && err.textContent.trim()) || cfg.text.tokenError));
            }, 30000);
        }).then(function () { if (dup) { dup.dataset.used = '1'; } });
    }

    // ------------------------------------------------------------ 3D Secure
    function runChallenge(action) {
        initThreeDs();
        if (typeof window.verify3DS !== 'function') { return Promise.reject(new Error(cfg.text.threeDsFailed)); }
        window.verify3DS(action.response);
        return Promise.resolve();   // continues in onThreeDsResult
    }

    function onThreeDsResult(actionCode, xCavv, xEciFlag, xRefNum, xAuthenticateStatus, xSignatureVerification) {
        if (state.verifying || !xRefNum || ['SUCCESS', 'FAILURE', 'ERROR', 'NOACTION'].indexOf(actionCode) < 0) { return; }
        state.verifying = true;
        var data = new FormData();
        data.append('form_key', cfg.formKey);
        data.append('xRefNum', xRefNum || '');
        data.append('xCavv', xCavv || '');
        data.append('xEci', xEciFlag || '');
        data.append('x3dsAuthenticationStatus', xAuthenticateStatus || '');
        data.append('x3dsSignatureVerificationStatus', xSignatureVerification || '');
        data.append('x3dsActionCode', actionCode || '');
        data.append('x3dsError', (window.ck3DS && window.ck3DS.error) || '');
        fetch(cfg.verifyUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                state.verifying = false;
                if (res.success && window.UnifiedCheckout) { return window.UnifiedCheckout.place(); }
                if (window.UnifiedCheckout) { window.UnifiedCheckout.showError(res.error || cfg.text.threeDsFailed); }
            })
            .catch(function () {
                state.verifying = false;
                if (window.UnifiedCheckout) { window.UnifiedCheckout.showError(cfg.text.threeDsFailed); }
            });
    }

    // ------------------------------------------------------------ wiring
    function start() {
        bindForm();
        initFields();
    }

    // wire up after every DOMContentLoaded handler ran (the checkout's API is created in one)
    setTimeout(wire, 0);
    function wire() {
        start();
        var uc = window.UnifiedCheckout;
        if (uc) {
            uc.onBeforePlace(CODE, beforePlace);
            uc.onClientAction('solapayments_3ds', runChallenge);
            if (uc.selectedMethod() === CODE) { setTimeout(initThreeDs, 1000); }
        } else if (window.Payment && Payment.prototype.save) {
            // standard onepage checkout: get the tokens before the payment step is saved
            var save = Payment.prototype.save;
            Payment.prototype.save = function () {
                var self = this, args = arguments;
                if (this.currentMethod !== CODE || usesSavedCard()) { return save.apply(self, args); }
                beforePlace().then(function () { save.apply(self, args); }, function (e) { alert(e.message); });
            };
        }
    }
    document.addEventListener('unifiedcheckout:payment', function (e) {
        if (e.detail.code === CODE) { start(); setTimeout(initThreeDs, 300); }
    });
    document.addEventListener('unifiedcheckout:sections', function (e) {
        if (e.detail.names.indexOf('payment') >= 0) { start(); }
    });
    // a duplicate decline offers "place it anyway"
    document.addEventListener('unifiedcheckout:error', function (e) {
        var dup = q('[data-sp-duplicate]');
        if (dup && (e.detail.message || '').indexOf(cfg.text.duplicate) === 0) { dup.hidden = false; }
    });
});
