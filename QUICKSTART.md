# Quick Start Guide - Sola Payments for Magento 1

## 5-Minute Setup

### Prerequisites
- Magento 1.9.x or OpenMage installed
- Sola Payments (Cardknox) merchant account
- API Token Key and Transaction Key

### Installation

1. **Extract Files**
   ```bash
   unzip Openmage_Solapayments.zip -d /path/to/magento/
   ```

2. **Clear Cache**
   - Go to: **System > Cache Management**
   - Click: "Flush Cache Storage"

3. **Configure Payment Method**
   - Go to: **System > Configuration > Payment Methods > Sola Payments**
   - Set **Enabled** to "Yes"
   - Enter **Cardknox Token Key**
   - Enter **Cardknox Transaction Key**
   - Click: "Save Config"

4. **Test**
   - Add item to cart
   - Proceed to checkout
   - Verify "Sola Payments" appears as payment option
   - Test with card: 4111 1111 1111 1111 (any future expiration, any CVV)

### Success!
Payment method is now active on your store.

---

## What's Included

### Payment Methods
✓ Credit Cards (Visa, MasterCard, Amex, Discover, etc.)
✓ Google Pay
✓ Apple Pay

### Features
✓ Authorize & Capture
✓ Refunds
✓ Void transactions
✓ Card tokenization
✓ 3D Secure
✓ reCAPTCHA fraud protection

### Documentation
- `README.md` - Full feature documentation
- `INSTALLATION.md` - Detailed installation guide
- `API_INTEGRATION.md` - API reference
- `CHANGELOG.md` - Version history

---

## Common Configuration

### Enable reCAPTCHA
1. Get Google reCAPTCHA keys from: https://www.google.com/recaptcha/admin
2. Go to: **System > Configuration > Payment Methods > Sola Payments**
3. Enable reCAPTCHA and enter keys

### Restrict to Countries
1. Go to: **System > Configuration > Payment Methods > Sola Payments**
2. Set "Payment Applicable From" to "Specific Countries"
3. Select countries from list

### Change Payment Action
1. Go to: **System > Configuration > Payment Methods > Sola Payments**
2. Payment Action:
   - **Authorize**: Requires manual capture
   - **Authorize and Capture**: Automatic capture

---

## Troubleshooting

### Payment method not appearing
- Clear cache (System > Cache Management > Flush)
- Verify enabled = "Yes"
- Check currency is supported (USD, EUR, GBP, CAD, AUD)

### "Invalid API credentials"
- Verify Token Key and Transaction Key are correct
- Check they're from your account dashboard
- Ensure they're not expired

### Gateway connection error
- Verify SSL is enabled on server
- Check firewall allows outbound HTTPS
- Contact hosting provider if issue persists

### Test cards not working
- Use: 4111 1111 1111 1111
- Expiration: Any future date
- CVV: Any 3 digits
- Only works in test environment

---

## File Structure

```
Module Location: app/code/local/Openmage/Solapayments/

Key Files:
- Helper/Data.php - API integration
- Model/Method.php - Payment processing
- Block/Form.php - Checkout form
- etc/config.xml - Module config
- etc/system.xml - Admin settings
```

---

## Next Steps

1. **Review Configuration**
   - Verify all settings are correct
   - Test with test card numbers
   - Process test transactions

2. **Test Transactions**
   - Create test order
   - Verify payment processes
   - Test refund functionality

3. **Go Live**
   - Switch to production API credentials
   - Update store to use SSL
   - Test with live transactions
   - Monitor transaction logs

4. **Ongoing**
   - Monitor transaction success rates
   - Keep API credentials secure
   - Stay updated with module updates
   - Regular security reviews

---

## Security Checklist

- [ ] SSL certificate is valid and installed
- [ ] API credentials stored securely
- [ ] reCAPTCHA enabled (recommended)
- [ ] Test transactions verified
- [ ] Admin access restricted
- [ ] Logs monitored regularly
- [ ] Module updated to latest version
- [ ] Backup created before going live

---

## Support Resources

- **Documentation**: See README.md, INSTALLATION.md, API_INTEGRATION.md
- **Cardknox**: https://www.cardknox.com/api/
- **Magento Forum**: https://community.magento.com
- **Module Support**: Contact your provider

---

## API Credentials

You'll need:
1. **Token Key** - Authentication key from Cardknox
2. **Transaction Key** - Additional authentication key

Find these in your Cardknox merchant dashboard:
→ Account Settings → API Credentials

---

## Test Card Numbers

| Card Type | Number |
|-----------|--------|
| Visa | 4111 1111 1111 1111 |
| MasterCard | 5555 5555 5555 4444 |
| Amex | 3782 822463 10005 |
| Discover | 6011 1111 1111 1117 |

**Use any:**
- Future expiration date (MM/YY)
- 3-4 digit CVV

---

## Command Reference

### Clear Cache
```bash
rm -rf var/cache/*
```

### Check Module
```bash
grep -r "Openmage_Solapayments" app/etc/modules/
```

### View Logs
```bash
tail -f var/log/solapayments.log
```

---

## Additional Features

### Google Pay
- Automatically available on supported browsers/devices
- Customers see Google Pay button at checkout
- One-click payment

### Apple Pay
- Available on Safari on iOS and macOS
- Customers see Apple Pay button
- Secure biometric authentication

### Card Vault
- Save cards securely for future purchases
- One-click checkout with saved cards
- Powered by Cardknox tokenization

### 3D Secure
- Enhanced security for card transactions
- Additional authentication layer
- Protects against fraud

---

## Performance Tips

1. **Enable caching** for better performance
2. **Monitor API response times** - aim for <2 seconds
3. **Use SSL/TLS** for security and performance
4. **Regular log cleanup** to save disk space
5. **Database optimization** for transaction queries

---

## FAQ

**Q: Is my data secure?**
A: Yes. PCI-DSS compliant, SSL encrypted, secure tokenization.

**Q: What cards do you accept?**
A: Visa, MasterCard, American Express, Discover, JCB, Diners Club, China Union Pay, Maestro.

**Q: Can I refund transactions?**
A: Yes, full and partial refunds supported from admin panel.

**Q: Is there a subscription feature?**
A: Coming in v1.1.0. Currently supports one-time payments.

**Q: Can I use saved cards?**
A: Yes, through card tokenization/vault feature.

**Q: Is reCAPTCHA required?**
A: No, but recommended for fraud prevention.

---

## Version Information

- **Module Version**: 1.0.0
- **Magento Compatibility**: 1.9.x - 1.14.x, OpenMage 1.x
- **PHP Requirement**: 5.6+ (7.0+ recommended)
- **Release Date**: December 25, 2024

---

## Getting Help

1. **Check Documentation**: See included .md files
2. **Review Logs**: Check var/log/solapayments.log
3. **Test API**: Verify credentials with Cardknox
4. **Contact Support**: See support info in README.md

---

**Ready to accept payments?** Start with the INSTALLATION.md guide!

For complete details, see README.md
