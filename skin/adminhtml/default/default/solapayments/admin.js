/**
 * Sola Payments in admin Create Order (YSRTech_Solapayments): the customer's saved cards, or a
 * new card in Cardknox iFields. Before the order is submitted, a new card's iframes are swapped
 * for single-use tokens (getTokens). The payment section is reloaded by AJAX when the order
 * changes, so the form is (re)initialised whenever it appears.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var cfg = window.solapaymentsConfig;
    if (!cfg || !window.AdminOrder) { return; }
    var CODE = 'solapayments';
    var state = { card: { len: 0, valid: false }, cvv: { len: 0, valid: false }, callbacks: false };
    var fieldStyle = { 'border': '0', 'outline': 'none', 'width': '100%', 'height': '30px', 'padding': '0 8px', 'font-size': '13px', 'box-sizing': 'border-box' };

    function q(sel) { var f = document.querySelector('[data-solapayments]'); return f ? f.querySelector(sel) : null; }
    function usesSavedCard() { var c = q('[data-sp-card]:checked'); return !!(c && c.value); }

    function onInput(data) {
        if (!data) { return; }
        state.card.len = data.cardNumberFormattedLength || data.cardNumberLength || 0;
        state.card.valid = !!data.cardNumberIsValid;
        state.cvv.len = data.cvvLength || 0;
        state.cvv.valid = data.issuer === 'unknown' ? state.cvv.len >= 3 : !!data.cvvIsValid;
    }

    function init() {
        var form = document.querySelector('[data-solapayments]');
        if (!form || form.getAttribute('data-sp-bound') || typeof window.setAccount !== 'function') { return; }
        form.setAttribute('data-sp-bound', '1');
        if (typeof window.enableAutoFormatting === 'function') { window.enableAutoFormatting(' '); }
        window.setAccount(cfg.tokenKey, cfg.softwareName, cfg.softwareVersion);
        if (!state.callbacks) {
            window.addIfieldCallback('input', onInput);
            window.addIfieldCallback('issuerupdated', onInput);
            state.callbacks = true;
        }
        window.setIfieldStyle('card-number', fieldStyle);
        window.setIfieldStyle('cvv', fieldStyle);
        form.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-card]')) { q('[data-sp-new]').hidden = usesSavedCard(); }
        });
        var expiry = q('[data-sp-expiry]');
        if (expiry) {
            expiry.addEventListener('input', function () {
                var d = expiry.value.replace(/\D/g, '').slice(0, 4);
                if (d.length === 1 && d > '1') { d = '0' + d; }
                expiry.value = d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d;
            });
        }
    }

    function readExpiry() {
        var expiry = q('[data-sp-expiry]');
        var m = expiry ? expiry.value.replace(/\s/g, '').match(/^(\d{2})\/?(\d{2})$/) : null;
        if (!m) { return null; }
        var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10), now = new Date();
        if (month < 1 || month > 12 || year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) { return null; }
        return { month: month, year: year };
    }

    // the payment section is replaced by AJAX: watch for the form
    new MutationObserver(init).observe(document.body, { childList: true, subtree: true });
    init();

    var submit = AdminOrder.prototype.submit;
    AdminOrder.prototype.submit = function () {
        var self = this, args = arguments;
        if (this.paymentMethod !== CODE || usesSavedCard()) { return submit.apply(self, args); }
        var exp = readExpiry();
        var error = !exp ? cfg.text.expiryInvalid
            : state.card.len <= 0 ? cfg.text.cardRequired
            : !state.card.valid ? cfg.text.cardInvalid
            : state.cvv.len <= 0 ? cfg.text.cvvRequired
            : !state.cvv.valid ? cfg.text.cvvInvalid : null;
        if (error) { alert(error); return; }
        q('[data-sp-exp-month]').value = exp.month;
        q('[data-sp-exp-year]').value = exp.year;
        window.getTokens(function () { submit.apply(self, args); }, function () {
            var err = q('[data-ifields-id="card-data-error"]');
            alert((err && err.textContent.trim()) || cfg.text.tokenError);
        }, 30000);
    };
});
