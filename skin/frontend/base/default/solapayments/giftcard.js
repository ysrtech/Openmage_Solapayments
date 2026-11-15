/**
 * Sola gift card in the YSRTech_UnifiedCheckout summary: apply / remove / check balance, then
 * refresh the summary and payment sections (the total, and whether a payment is still needed).
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    function box() { return document.querySelector('[data-sp-giftcard]'); }
    function message(text, isError) {
        var el = box() && box().querySelector('[data-sp-giftcard-message]');
        if (!el) { return; }
        el.textContent = text || '';
        el.classList.toggle('is-error', !!isError);
        el.hidden = !text;
    }
    function call(action, code) {
        var uc = window.UnifiedCheckout, b = box();
        if (!uc || !b) { return Promise.resolve({}); }
        var data = {};
        if (code !== undefined) { data.code = code; }
        return uc.post(b.getAttribute('data-url-' + action), data, ['summary']);
    }
    document.addEventListener('click', function (e) {
        var b = box();
        if (!b || !b.contains(e.target)) { return; }
        var input = b.querySelector('[data-sp-giftcard-code]');
        if (e.target.closest('[data-sp-giftcard-apply]')) {
            if (!input.value.trim()) { input.focus(); return; }
            call('apply', input.value.trim()).then(function (res) {
                if (res.error) { message(res.error, true); return; }
                window.UnifiedCheckout.refresh(['summary', 'payment']).then(function () { message(res.message); });
            });
        } else if (e.target.closest('[data-sp-giftcard-remove]')) {
            call('remove').then(function (res) {
                window.UnifiedCheckout.refresh(['summary', 'payment']).then(function () { message(res.message || res.error, !!res.error); });
            });
        } else if (e.target.closest('[data-sp-giftcard-balance]')) {
            if (!input.value.trim()) { input.focus(); return; }
            call('balance', input.value.trim()).then(function (res) { message(res.error || res.message, !!res.error); });
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches && e.target.matches('[data-sp-giftcard-code]')) {
            e.preventDefault();
            var b = box().querySelector('[data-sp-giftcard-apply]');
            if (b) { b.click(); }
        }
    });
});
