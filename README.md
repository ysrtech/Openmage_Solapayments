# Sola Payments Extension for Magento 1

A comprehensive payment gateway module for Magento 1 using Sola Payments (Cardknox API), featuring support for credit cards, Google Pay, Apple Pay, 3D Secure, and tokenization.

## Features

### Core Payment Features
- **Credit Card Processing**: Full PCI-DSS compliant payment processing
- **Payment Actions**: Support for both Authorize and Authorize & Capture
- **Refunds**: Full and partial refund capabilities
- **Void Support**: Cancel authorized transactions before capture
- **Transaction Management**: Complete transaction logging and tracking

### Advanced Features
- **Google Pay Integration**: Seamless Google Pay checkout experience
- **Apple Pay Integration**: Native Apple Pay support
- **3D Secure Support**: Enhanced security with 3D Secure authentication
- **Card Tokenization (Vault)**: Save and reuse payment cards securely
- **reCAPTCHA Support**: Google reCAPTCHA integration for fraud prevention
- **Multi-currency Support**: Support for USD, EUR, GBP, CAD, AUD

### Administrative Features
- **Order Payment Management**: View and manage payments in admin
- **Refund Management**: Process refunds directly from order view
- **Payment Logging**: Comprehensive debug logging
- **Configuration Dashboard**: Easy-to-use admin configuration interface

## Installation

### Prerequisites
- Magento 1.9.x or OpenMage 1.x
- PHP 5.6+ (PHP 7.x recommended)
- cURL enabled
- SSL certificate (required for production)

### Installation Steps

1. **Download and Extract**
   ```bash
   # Extract the module to your Magento root directory
   unzip Openmage_Solapayments.zip -d /path/to/magento/
   ```

2. **Clear Cache**
   Navigate to **System > Cache Management** and clear all caches.

3. **Configure Payment Method**
   Navigate to **System > Configuration > Payment Methods > Sola Payments**

4. **Enter Credentials**
   - **Enabled**: Set to "Yes"
   - **Title**: "Sola Payments" (or your preferred name)
   - **Cardknox Token Key**: Your account token key
   - **Cardknox Transaction Key**: Your account transaction key
   - **Gateway URL**: https://api.cardknox.com/api/transaction
   - **Payment Action**: Select "Authorize" or "Authorize and Capture"

5. **Optional Configuration**
   - Enable reCAPTCHA if desired
   - Configure allowed countries
   - Set sort order

## Configuration

### Basic Settings

**Enabled**
Enable or disable the payment method.

**Title**
The payment method title displayed at checkout.

**Cardknox Token Key**
Your Cardknox/Sola Payments account token key for API authentication.

**Cardknox Transaction Key**
Your Cardknox/Sola Payments account transaction key.

**Gateway URL**
Default: `https://api.cardknox.com/api/transaction`
The API endpoint for processing transactions.

**Payment Action**
- **Authorize**: Only authorizes the payment, capture must be done manually or via cron
- **Authorize and Capture**: Authorizes and captures the payment immediately

### Advanced Settings

**Enable reCAPTCHA**
Enable Google reCAPTCHA verification on checkout form.

**reCAPTCHA Site Key**
Your Google reCAPTCHA site key.

**reCAPTCHA Secret Key**
Your Google reCAPTCHA secret key.

**Payment Applicable From**
Restrict payment method to specific countries.

## Usage

### Customer Checkout

1. Customer adds items to cart and proceeds to checkout
2. On the payment method selection, customer chooses "Sola Payments"
3. Customer enters card details in the payment form
4. (Optional) reCAPTCHA verification if enabled
5. Customer completes order and payment is processed

### Google Pay Checkout

1. Google Pay button appears (if available on device)
2. Customer clicks "Pay with Google Pay"
3. Google Pay popup appears with available payment methods
4. Customer confirms payment
5. Order is created and payment is processed

### Apple Pay Checkout

1. Apple Pay button appears (if available on device)
2. Customer clicks "Pay with Apple Pay"
3. Apple Pay confirmation window appears
4. Customer confirms with Face ID/Touch ID
5. Order is created and payment is processed

### Using Saved Cards (Vault)

1. On checkout, customer can select a previously saved card
2. Card is charged using the stored token
3. No card details need to be re-entered

### Admin Payment Management

**View Payment Information**
1. Navigate to **Sales > Orders**
2. Click on an order with a Sola Payments transaction
3. Payment details including Transaction ID and Authorization Code are displayed

**Process Refunds**
1. From the order view, click "Invoice"
2. Create an invoice for the items
3. Click "Credit Memo" on the invoice
4. Select items to refund
5. Click "Refund Offline" or "Refund" (for online refunds via Sola Payments)

## API Integration Details

### Request/Response Format

The module communicates with Cardknox API using HTTP POST with key=value pairs.

### Transaction Types

**Authorization (cc:auth)**
```
xKey: Token Key
xCommand: cc:auth
xCardNum: Card Number
xExp: Expiration (MMYY)
xCardCode: CVV
xAmount: Amount (X.XX format)
xInvoice: Invoice/Order ID
```

**Sale/Authorize & Capture (cc:sale)**
```
Similar to auth, but processes immediately
```

**Capture (cc:capture)**
```
xKey: Token Key
xCommand: cc:capture
xRefNum: Reference Number from auth
xAmount: Amount to capture
```

**Refund (cc:void_refund)**
```
xKey: Token Key
xCommand: cc:void_refund
xRefNum: Reference Number
xAmount: Refund amount
```

**Void (cc:void)**
```
xKey: Token Key
xCommand: cc:void
xRefNum: Reference Number
```

### Response Format

Responses are returned as URL-encoded key=value pairs:

```
xRefNum=12345&xError=0&xErrorMessage=&xAuthCode=123456
```

**Response Fields**:
- `xRefNum`: Transaction reference number
- `xError`: Error code (0 = success, non-zero = error)
- `xErrorMessage`: Error message description
- `xAuthCode`: Authorization code (for auth transactions)
- `xToken`: Token (for tokenization)

## Troubleshooting

### Payment Processing Issues

**"Payment authorization failed"**
- Verify Token Key and Transaction Key are correct
- Check if card details are valid
- Verify amount is positive and correctly formatted
- Check firewall/security settings not blocking API calls

**"Invalid transaction ID"**
- Ensure previous transaction was completed successfully
- Check transaction reference is stored correctly

**"Gateway connection error"**
- Verify SSL certificate is valid
- Check cURL is enabled on server
- Verify network connectivity to API endpoint
- Check firewall isn't blocking API calls

### Configuration Issues

**Payment method not appearing at checkout**
- Verify payment method is enabled in configuration
- Clear Magento cache
- Check allowed countries configuration
- Verify currency is supported (USD, EUR, GBP, CAD, AUD)

**reCAPTCHA not appearing**
- Verify reCAPTCHA is enabled
- Check Site Key is correct
- Verify reCAPTCHA script is loaded (check browser console)

### Debugging

Enable debug logging:
1. Navigate to **System > Configuration > Payment Methods > Sola Payments**
2. Look for debug options (if available)
3. Check `/var/log/solapayments.log` for detailed transaction logs

## API Documentation

For detailed API documentation, visit:
- Cardknox: https://www.cardknox.com/api/

## Support

For technical support, please contact:
- Sola Payments: support@solapayments.com
- Cardknox: support@cardknox.com

## License

This extension is released under the Open Software License (OSL 3.0).
See LICENSE.txt for details.

## Changelog

### Version 1.0.0 (Initial Release)
- Credit card payment processing
- Google Pay integration
- Apple Pay integration
- 3D Secure support
- Card tokenization (Vault)
- reCAPTCHA integration
- Admin order payment management
- Multi-currency support
- Comprehensive logging

## Security Considerations

- **PCI-DSS Compliance**: The module implements industry-standard security practices
- **SSL Required**: Always use SSL/TLS for production environments
- **Card Data**: Card data is encrypted before transmission
- **Never Log Card Numbers**: The module never logs full card numbers in plaintext
- **Security Updates**: Keep the module and Magento updated with latest security patches

## Development Notes

### Module Structure
```
app/
├── code/local/Openmage/Solapayments/
│   ├── Block/           # Display blocks
│   ├── Model/           # Business logic models
│   │   ├── Method.php   # Main payment method
│   │   ├── Vault.php    # Tokenization
│   │   ├── ThreeDs.php  # 3D Secure
│   │   └── Method/      # Payment method variants
│   ├── Helper/          # Helper functions
│   ├── controllers/     # Frontend controllers
│   └── etc/
│       ├── config.xml   # Module configuration
│       └── system.xml   # Admin settings
├── design/
│   ├── frontend/        # Frontend templates
│   └── adminhtml/       # Admin templates
├── locale/              # Translation files
└── js/
    └── openmage/solapayments/  # JavaScript files
```

### Adding New Payment Methods

To add a new payment method variant:

1. Create a new method class extending the base method
2. Define the method code constant
3. Implement `authorize()`, `capture()`, `refund()` methods
4. Add configuration in `system.xml`
5. Create frontend blocks and templates if needed

## Contributing

For bug reports, feature requests, or contributions, please contact support.

## Version History

See CHANGELOG.md for complete version history.
