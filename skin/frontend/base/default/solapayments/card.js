/**
 * Sola Payments card form (YSRTech_Solapayments): saved cards, or a new card typed in.
 * Formats the card number and expiry, checks them before the order is sent, and offers
 * "place it anyway" after a duplicate-transaction decline.
 *
 * Works with YSRTech_UnifiedCheckout (window.UnifiedCheckout hooks), and with the standard
 * OpenMage onepage checkout (checks run before the payment step is saved).
 * Messages: window.solapaymentsConfig (Block/Scripts.php, printed after this file).
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var cfg = window.solapaymentsConfig;
    if (!cfg) { return; }
    var CODE = 'solapayments';

    function form() { return document.querySelector('[data-solapayments]'); }
    function q(sel) { var f = form(); return f ? f.querySelector(sel) : null; }
    function digits(v) { return String(v || '').replace(/\D/g, ''); }
    function usesSavedCard() { var c = q('[data-sp-card]:checked'); return !!(c && c.value); }

    function brand(number) {
        if (/^3[47]/.test(number)) { return 'amex'; }
        if (/^4/.test(number)) { return 'visa'; }
        if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(number)) { return 'mastercard'; }
        if (/^(6011|65|64[4-9]|622)/.test(number)) { return 'discover'; }
        return '';
    }

    function luhn(number) {
        if (number.length < 13 || number.length > 19) { return false; }
        var sum = 0;
        for (var i = 0; i < number.length; i++) {
            var d = parseInt(number.charAt(number.length - 1 - i), 10);
            if (i % 2) { d *= 2; if (d > 9) { d -= 9; } }
            sum += d;
        }
        return sum % 10 === 0;
    }

    function formatNumber(number) {
        if (brand(number) === 'amex') {
            return [number.slice(0, 4), number.slice(4, 10), number.slice(10, 15)].filter(Boolean).join(' ');
        }
        return number.replace(/(\d{4})(?=\d)/g, '$1 ');
    }

    function readExpiry() {
        var m = digits(q('[data-sp-expiry]') && q('[data-sp-expiry]').value).match(/^(\d{2})(\d{2})$/);
        if (!m) { return null; }
        var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10), now = new Date();
        if (month < 1 || month > 12 || year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) { return null; }
        return { month: month, year: year };
    }

    function mark(el, bad) {
        var box = el && el.closest('.sp-field');
        if (box) { box.classList.toggle('is-invalid', !!bad); }
    }

    function bindForm() {
        var f = form();
        if (!f || f.getAttribute('data-sp-bound')) { return; }
        f.setAttribute('data-sp-bound', '1');
        f.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-card]')) { q('[data-sp-new]').hidden = usesSavedCard(); }
        });
        f.addEventListener('input', function (e) {
            var el = e.target;
            if (el.matches('[data-sp-number]')) {
                var n = digits(el.value).slice(0, 19);
                el.value = formatNumber(n);
                var box = el.closest('.sp-field');
                if (box) { box.setAttribute('data-issuer', brand(n)); }
                q('[data-sp-cvv]').maxLength = brand(n) === 'amex' ? 4 : 3;
                mark(el, false);
            } else if (el.matches('[data-sp-expiry]')) {
                var d = digits(el.value).slice(0, 4);
                if (d.length === 1 && d > '1') { d = '0' + d; }
                el.value = d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d;
                mark(el, false);
            } else if (el.matches('[data-sp-cvv]')) {
                el.value = digits(el.value).slice(0, 4);
                mark(el, false);
            }
        });
        f.addEventListener('focusout', function (e) {
            if (e.target.matches('[data-sp-number]') && e.target.value) { mark(e.target, !luhn(digits(e.target.value))); }
        });
    }

    /**
     * Before the order is sent: nothing for a saved card; for a new card check the fields and
     * fill in the expiry month and year.
     */
    function check() {
        if (usesSavedCard()) { return Promise.resolve(); }
        var number = q('[data-sp-number]'), cvv = q('[data-sp-cvv]'), expiry = q('[data-sp-expiry]');
        var n = digits(number.value), exp = readExpiry();
        if (!luhn(n)) { mark(number, true); number.focus(); return Promise.reject(new Error(cfg.text.cardInvalid)); }
        if (!exp) { mark(expiry, true); expiry.focus(); return Promise.reject(new Error(cfg.text.expiryInvalid)); }
        var need = brand(n) === 'amex' ? 4 : 3;
        if (digits(cvv.value).length !== need && !(need === 3 && digits(cvv.value).length === 4 && brand(n) === '')) {
            mark(cvv, true); cvv.focus(); return Promise.reject(new Error(cfg.text.cvvInvalid));
        }
        q('[data-sp-exp-month]').value = exp.month;
        q('[data-sp-exp-year]').value = exp.year;
        return Promise.resolve();
    }

    function wire() {
        bindForm();
        var uc = window.UnifiedCheckout;
        if (uc) {
            uc.onBeforePlace(CODE, check);
        } else if (window.Payment && Payment.prototype.save) {
            var save = Payment.prototype.save;
            Payment.prototype.save = function () {
                var self = this, args = arguments;
                if (this.currentMethod !== CODE) { return save.apply(self, args); }
                check().then(function () { save.apply(self, args); }, function (e) { alert(e.message); });
            };
        }
    }

    // after every DOMContentLoaded handler ran (the checkout's API is created in one)
    setTimeout(wire, 0);
    document.addEventListener('unifiedcheckout:payment', function (e) { if (e.detail.code === CODE) { bindForm(); } });
    document.addEventListener('unifiedcheckout:sections', function (e) { if (e.detail.names.indexOf('payment') >= 0) { bindForm(); } });
    // a duplicate decline offers "place it anyway"
    document.addEventListener('unifiedcheckout:error', function (e) {
        var dup = q('[data-sp-duplicate]');
        if (dup && (e.detail.message || '').indexOf(cfg.text.duplicate) === 0) { dup.hidden = false; }
    });
});
