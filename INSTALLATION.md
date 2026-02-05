# Installation Guide for Sola Payments Extension

## System Requirements

- **Magento Version**: 1.9.x or OpenMage 1.x
- **PHP Version**: 5.6+ (7.0+ recommended)
- **Database**: MySQL 5.5+
- **Server**: Linux/Unix (Windows supported but not recommended for production)
- **Web Server**: Apache 2.2+ or Nginx
- **SSL**: Required for production

## Pre-Installation Checklist

Before installing, ensure you have:

1. **Backup**: Complete backup of your Magento installation
2. **Database Backup**: Database snapshot
3. **Sola Payments Account**: Active Cardknox/Sola Payments merchant account
4. **API Credentials**: Token Key and Transaction Key from your account dashboard
5. **SSL Certificate**: Valid SSL certificate (production only)

## Installation Steps

### Step 1: Extract Module Files

```bash
# Navigate to your Magento root directory
cd /path/to/magento

# Extract the module
unzip Openmage_Solapayments.zip

# Verify file permissions (755 for directories, 644 for files)
find app/code/local/Openmage/Solapayments -type d -exec chmod 755 {} \;
find app/code/local/Openmage/Solapayments -type f -exec chmod 644 {} \;
```

### Step 2: Clear Magento Cache

Navigate to **System → Cache Management** in your Magento admin panel and:
1. Select all caches
2. Click "Flush Cache Storage"
3. Alternatively, clear the cache directory:
   ```bash
   rm -rf var/cache/*
   ```

### Step 3: Configure the Payment Method

1. Log into your Magento admin panel
2. Navigate to **System → Configuration**
3. In the left panel, click **Payment Methods** under **Sales**
4. Expand the **Sola Payments** section
5. Configure the following settings:

#### Basic Configuration

| Setting | Description |
|---------|-------------|
| **Enabled** | Set to "Yes" to enable the payment method |
| **Title** | Display name (e.g., "Sola Payments" or "Credit Card") |
| **Cardknox Token Key** | Your account token key |
| **Cardknox Transaction Key** | Your account transaction key |
| **Gateway URL** | https://api.cardknox.com/api/transaction |
| **Payment Action** | Choose "Authorize" or "Authorize and Capture" |
| **Order Status for New Orders** | Status for newly created orders |

#### Advanced Configuration

| Setting | Description |
|---------|-------------|
| **Enable reCAPTCHA** | Enable fraud prevention |
| **reCAPTCHA Site Key** | Google reCAPTCHA site key |
| **reCAPTCHA Secret Key** | Google reCAPTCHA secret key |
| **Specific Countries** | Restrict to certain countries |
| **Sort Order** | Display order among payment methods |

### Step 4: Obtain API Credentials

1. Log into your Cardknox/Sola Payments merchant account
2. Navigate to **API Settings** or **Credentials**
3. Copy your:
   - Token Key
   - Transaction Key
4. Paste these values in the module configuration

### Step 5: Configure reCAPTCHA (Optional)

1. Visit https://www.google.com/recaptcha/admin
2. Create a new site:
   - Name: Your Store Name
   - Type: reCAPTCHA v2 (Checkbox)
   - Domains: your-domain.com
3. Copy the **Site Key** and **Secret Key**
4. Paste into module configuration

### Step 6: Save Configuration

1. Click "Save Config" at the bottom of the page
2. You should see a success message
3. Clear cache again if prompted

## Verification Steps

### 1. Verify Module Installation

```bash
# Check if module is recognized
php shell/log.php

# Look for: Openmage_Solapayments in module list
```

Or in admin:
1. Navigate to **System → Configuration**
2. Scroll down to see "Sola Payments" section
3. Verify all settings are visible

### 2. Test Payment Method at Checkout

1. Add an item to cart
2. Proceed to checkout
3. On the payment method selection step, verify "Sola Payments" appears
4. (Optional) Verify Google Pay button appears if device/browser supports it

### 3. Create Test Transaction

**Development/Test Environment Only:**

1. Use test card numbers:
   - Visa: 4111111111111111
   - MasterCard: 5555555555554444
   - American Express: 378282246310005

2. Use any future expiration date and any 3-4 digit CVV

3. Proceed through checkout
4. Check order status - should show successful payment

5. If test fails, check:
   - API credentials are correct
   - Gateway URL is accessible
   - Firewall/security allows outbound HTTPS
   - Check `/var/log/solapayments.log` for errors

### 4. Verify Admin Functions

1. Navigate to **Sales → Orders**
2. Click on an order with Sola Payments transaction
3. Verify you can see:
   - Payment method: "Sola Payments"
   - Transaction ID
   - Authorization Code (if applicable)

## Troubleshooting Installation

### Payment Method Not Appearing

**Solution**:
1. Clear Magento cache completely
2. Verify module is enabled: Check `app/code/local/Openmage/Solapayments/etc/config.xml` exists
3. Check file permissions (should be readable)
4. Verify you cleared browser cache

### "Payment method is not available" Error

**Causes & Solutions**:
- Currency not supported: Add currency to allowed list
- Country not allowed: Check country configuration
- Payment method disabled: Verify in configuration it's set to "Yes"
- SSL issues: Ensure SSL certificate is valid

### Configuration Settings Not Saved

**Solution**:
1. Check file permissions on `app/etc/local.xml`
2. Ensure database user has write permissions
3. Check `/var/log/system.log` for database errors
4. Try re-entering configuration in smaller chunks

### "Cannot establish connection to API" Error

**Solutions**:
1. Verify firewall allows outbound HTTPS on port 443
2. Check SSL certificate is valid: `openssl s_client -connect api.cardknox.com:443`
3. Verify cURL is enabled: `php -m | grep curl`
4. Check PHP can make outbound connections
5. Verify API endpoint URL is correct

## Production Deployment

### Before Going Live

1. **Switch to Production Credentials**
   - Replace test API credentials with production credentials
   - Update in **System → Configuration**

2. **Enable SSL**
   - Ensure valid SSL certificate is installed
   - Set "Secure URLs in Frontend" to "Yes" in configuration

3. **Disable Debug Mode**
   - Ensure logging is appropriate for production
   - Don't expose detailed errors to customers

4. **Security Review**
   - Verify file permissions are correct
   - Ensure configuration values are secure
   - Check that sensitive data is not accessible

5. **Load Testing**
   - Test with realistic transaction volumes
   - Monitor API response times
   - Verify error handling works correctly

### Post-Deployment Monitoring

1. Monitor transaction success rates
2. Check error logs regularly
3. Monitor API response times
4. Verify customer notifications are being sent
5. Regular security audits

## Support & Resources

- **Magento Documentation**: https://magento.com
- **Cardknox Documentation**: https://www.cardknox.com/api/
- **Module Support**: contact support through your provider
- **Magento Community Forums**: https://community.magento.com

## Uninstallation

To remove the module:

```bash
# Remove module directory
rm -rf app/code/local/Openmage/Solapayments

# Remove design files
rm -rf app/design/frontend/default/default/template/openmage/solapayments
rm -rf app/design/adminhtml/default/default/template/openmage/solapayments

# Remove JS files
rm -rf js/openmage/solapayments

# Remove locale files
rm -rf app/locale/en_US/Openmage_Solapayments.csv

# Remove layout files
rm -f app/design/frontend/default/default/layout/solapayments.xml
rm -f app/design/adminhtml/default/default/layout/solapayments.xml

# Clear cache
rm -rf var/cache/*
```

Then navigate to **System → Cache Management** and clear all caches.
