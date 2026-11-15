# Sola Payments (Cardknox) for OpenMage

`YSRTech_Solapayments` 2.0 — a port of Sola's Magento 2 module
([cardknox/magento2_cardknox](https://github.com/cardknox/magento2_cardknox)) to OpenMage 20 /
PHP 8.4. Replaces the 1.x `Openmage_Solapayments` module (same payment method code
`solapayments`, so existing orders keep showing their payment).

## Features

- **Cards in Cardknox iFields**: card number and security code are iframes served by Cardknox;
  the store only receives single-use tokens (PCI SAQ A-EP instead of SAQ D).
- **Saved cards**: signed-in customers tick "Save this card" when paying and choose it next time;
  My Account > Saved cards to remove a card or set the default. Only Sola's token and the card's
  type, last four digits and expiry are stored.
- Authorize, or authorize and capture; **split capture** (several invoices on one authorization);
  online **refunds** (partial or whole) and **voids** from the admin.
- **3D Secure** (iFields challenge, verified with Cardknox before the order is placed).
- **Level 3** data (tax, shipping, discount, line items).
- Duplicate-transaction protection with a "place it anyway" option; Sola's error messages.

- **Admin Create Order**: charge a saved card of the customer, or a new card in iFields (optionally saved).

- **Sola gift cards**: applied in the checkout summary, redeemed when the order is placed (put back automatically if the order fails), used first by invoices and refunded first by credit memos, put back on cancellation. Orders paid in full by gift card use Zero Subtotal Checkout.

In progress: Google Pay, Apple Pay.

## Checkout

Built for [YSRTech_UnifiedCheckout](https://github.com/ysrtech/YSRTech_UnifiedCheckout) (uses its
payment API for card tokens and 3D Secure). The standard OpenMage onepage checkout works for new
and saved cards, without 3D Secure.

## Install

    composer require ysrtech/openmage-solapayments

(with this repository under "repositories"). Then System > Configuration > Payment Methods >
**Sola Payments (Cardknox)**: API key (transaction key), iFields key (token key), payment action,
saved cards, 3D Secure, Level 3.

## Changes from Sola's Magento 2 module

- Amount and currency are always both in the base currency.
- Payment action and split capture come from the settings only (the browser cannot change them).
- Debug logging is off by default and masks keys and tokens (including the CVV token).
- Form keys on every endpoint; saved cards are matched to the signed-in customer.
- Saved cards show the last four digits; a card saved twice is updated, not duplicated.

See NOTICE.md for the license of the ported code.
