# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2024-12-25

### Added
- Initial release of Sola Payments Extension for Magento 1
- Credit card payment processing with PCI-DSS compliance
- Support for Authorization and Authorize & Capture payment actions
- Partial and full refund capabilities
- Void/cancel transaction support
- Google Pay integration for quick mobile checkout
- Apple Pay integration for iOS devices
- 3D Secure (EMV 3DS) authentication support
- Card tokenization (Vault) for one-click checkout
- Google reCAPTCHA integration for fraud prevention
- Multi-currency support (USD, EUR, GBP, CAD, AUD)
- Admin order payment management and manual refunds
- Comprehensive transaction logging and debugging
- Order status management for new orders
- Configurable payment action and order status
- Support for specific country restrictions
- Localization support (English)
- Complete API integration with Cardknox/Sola Payments

### Security
- PCI-DSS compliant implementation
- Secure card tokenization using server-side tokens
- Never logs full card numbers
- SSL/TLS encryption for all API communications
- CSRF protection for admin actions
- Secure storage of API credentials

### Documentation
- Comprehensive README with installation and configuration guide
- API integration documentation
- Troubleshooting guide
- Security best practices
- Developer integration notes

## Future Enhancements

### Planned for Version 1.1.0
- Subscription/recurring payment support
- Gift card processing
- Level 3 data support for B2B transactions
- Webhook/IPN support for real-time updates
- ACH/Bank transfer support
- PayPal integration
- Additional mobile wallet support
- Advanced fraud tools

### Planned for Version 1.2.0
- Batch processing capabilities
- Invoice-based automatic capture
- Advanced reporting and analytics
- Custom payment routing
- Multi-gateway failover support

## Known Limitations

- Requires PHP 5.6+ (PHP 7+ recommended)
- Requires active SSL certificate in production
- Limited to Magento 1.9.x and OpenMage 1.x
- API calls require outbound HTTPS connectivity
- reCAPTCHA requires valid Google credentials

## Support

For issues, questions, or feature requests, please contact:
- Sola Payments: support@solapayments.com
- Cardknox: support@cardknox.com
