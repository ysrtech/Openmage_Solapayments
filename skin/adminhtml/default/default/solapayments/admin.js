/**
 * Sola Payments in admin Create Order (YSRTech_Solapayments): the customer's saved cards, or a
 * new card typed in. Formats the number and expiry and checks them before the order is
 * submitted. The payment section is reloaded by AJAX when the order changes, so the form is
 * (re)bound whenever it appears.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var cfg = window.solapaymentsConfig;
    if (!cfg || !window.AdminOrder) { return; }
    var CODE = 'solapayments';

    function q(sel) { var f = document.querySelector('[data-solapayments]'); return f ? f.querySelector(sel) : null; }
    function digits(v) { return String(v || '').replace(/\D/g, ''); }
    function usesSavedCard() { var c = q('[data-sp-card]:checked'); return !!(c && c.value); }
    function isAmex(n) { return /^3[47]/.test(n); }

    function luhn(n) {
        if (n.length < 13 || n.length > 19) { return false; }
        var sum = 0;
        for (var i = 0; i < n.length; i++) {
            var d = parseInt(n.charAt(n.length - 1 - i), 10);
            if (i % 2) { d *= 2; if (d > 9) { d -= 9; } }
            sum += d;
        }
        return sum % 10 === 0;
    }

    function readExpiry() {
        var m = digits(q('[data-sp-expiry]') && q('[data-sp-expiry]').value).match(/^(\d{2})(\d{2})$/);
        if (!m) { return null; }
        var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10), now = new Date();
        if (month < 1 || month > 12 || year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) { return null; }
        return { month: month, year: year };
    }

    function init() {
        var form = document.querySelector('[data-solapayments]');
        if (!form || form.getAttribute('data-sp-bound')) { return; }
        form.setAttribute('data-sp-bound', '1');
        form.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-card]')) { q('[data-sp-new]').hidden = usesSavedCard(); }
        });
        form.addEventListener('input', function (e) {
            var el = e.target, d;
            if (el.matches('[data-sp-number]')) {
                d = digits(el.value).slice(0, 19);
                el.value = isAmex(d)
                    ? [d.slice(0, 4), d.slice(4, 10), d.slice(10, 15)].filter(Boolean).join(' ')
                    : d.replace(/(\d{4})(?=\d)/g, '$1 ');
            } else if (el.matches('[data-sp-expiry]')) {
                d = digits(el.value).slice(0, 4);
                if (d.length === 1 && d > '1') { d = '0' + d; }
                el.value = d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d;
            } else if (el.matches('[data-sp-cvv]')) {
                el.value = digits(el.value).slice(0, 4);
            }
        });
    }

    // the payment section is replaced by AJAX: watch for the form
    new MutationObserver(init).observe(document.body, { childList: true, subtree: true });
    init();

    var submit = AdminOrder.prototype.submit;
    AdminOrder.prototype.submit = function () {
        if (this.paymentMethod === CODE && !usesSavedCard()) {
            var n = digits(q('[data-sp-number]').value), cvv = digits(q('[data-sp-cvv]').value), exp = readExpiry();
            var error = !luhn(n) ? cfg.text.cardInvalid
                : !exp ? cfg.text.expiryInvalid
                : (isAmex(n) ? cvv.length !== 4 : cvv.length < 3) ? cfg.text.cvvInvalid : null;
            if (error) { alert(error); return; }
            q('[data-sp-exp-month]').value = exp.month;
            q('[data-sp-exp-year]').value = exp.year;
        }
        return submit.apply(this, arguments);
    };
});
