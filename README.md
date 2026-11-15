# Sola Payments (Cardknox) for OpenMage

`YSRTech_Solapayments` 2.2 — a port of Sola's Magento 2 module
([cardknox/magento2_cardknox](https://github.com/cardknox/magento2_cardknox)) to OpenMage 20 /
PHP 8.4. Replaces the 1.x `Openmage_Solapayments` module (same payment method code
`solapayments`, so existing orders keep showing their payment).

## Features

- **Card form on the checkout page** (like the stock Authorize.net method): the card number and
  security code are posted with the order, sent straight to Sola and never stored or logged.
  Checked client-side (Luhn, expiry, security code) and again on the server.
- **Saved cards**: signed-in customers tick "Save this card" when paying and choose it next time;
  My Account > Saved cards to remove a card or set the default. Only Sola's token and the card's
  type, last four digits and expiry are stored.
- Authorize, or authorize and capture; **split capture** (several invoices on one authorization);
  online **refunds** (partial or whole) and **voids** from the admin.
- **Level 3** data (tax, shipping, discount, line items).
- Duplicate-transaction protection with a "place it anyway" option; Sola's error messages.

- **Admin Create Order**: charge a saved card of the customer, or a new card (optionally saved).

- **Sola gift cards**: applied in the checkout summary, redeemed when the order is placed (put back automatically if the order fails), used first by invoices and refunded first by credit memos, put back on cancellation. Orders paid in full by gift card use Zero Subtotal Checkout.

- **Google Pay and Apple Pay** (Cardknox iFields buttons, as in Sola's Magento 2 module): separate
  payment methods with their own title, payment action, button style and countries. The button
  replaces the checkout's Pay button; the wallet's sheet charges the checkout total, and the
  wallet's encrypted payment data goes to Sola with the order (`xDigitalWalletType`). Captures,
  refunds and voids work as for cards. Needs YSRTech_UnifiedCheckout 1.2+ and HTTPS.
  - Google Pay: set the merchant name; "Test" shows Google's test cards. For "Production", the
    site must be approved for Google Pay (Google Pay & Wallet Console).
  - Apple Pay: register the site's domain in the Sola portal (Settings > Gateway settings >
    Payment methods) first. The method only appears in Safari on Apple devices with Apple Pay.

## Checkout

Built for [YSRTech_UnifiedCheckout](https://github.com/ysrtech/YSRTech_UnifiedCheckout) (uses its
payment API to check the card before the order is sent). The standard OpenMage onepage checkout
works too.

## Install

    composer require ysrtech/openmage-solapayments

(with this repository under "repositories"). Then System > Configuration > Payment Methods >
**Sola Payments (Cardknox)**: API key (transaction key), payment action,
saved cards, Level 3, gift cards; iFields key (token key) for the wallets. Then
**Sola Payments: Google Pay** and **Sola Payments: Apple Pay**.

## Changes from Sola's Magento 2 module

- Cards without iFields or 3D Secure: the card form is part of the page and the card goes to
  Sola from the server (the store needs PCI SAQ D, as with Authorize.net AIM). iFields is only
  loaded for the Google Pay / Apple Pay buttons, and only when one of them is enabled.
- Wallets charge the order total computed by the server, not an amount sent by the browser.
- Amount and currency are always both in the base currency.
- Payment action and split capture come from the settings only (the browser cannot change them).
- Debug logging is off by default and masks keys, card numbers, security codes and tokens.
- Form keys on every endpoint; saved cards are matched to the signed-in customer.
- Saved cards show the last four digits; a card saved twice is updated, not duplicated.

See NOTICE.md for the license of the ported code.
