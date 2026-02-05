# API Integration Guide

## Overview

The Sola Payments module communicates with the Cardknox/Sola Payments API using HTTP POST requests with URL-encoded key=value pairs.

## API Endpoint

**URL**: https://api.cardknox.com/api/transaction

**Method**: HTTP POST

**Content-Type**: application/x-www-form-urlencoded

## Authentication

All API requests require authentication via two keys:

- **xKey**: Your Cardknox Token Key
- **xVersion**: API Version (typically 4.0.0)

## Request Format

All requests follow this basic structure:

```
POST /api/transaction HTTP/1.1
Host: api.cardknox.com
Content-Type: application/x-www-form-urlencoded

xKey=YOUR_TOKEN_KEY&xVersion=4.0.0&xCommand=COMMAND_NAME&param1=value1&param2=value2...
```

## Response Format

Responses are returned as URL-encoded key=value pairs:

```
xRefNum=12345&xError=0&xErrorMessage=&xAuthCode=123456&xToken=TOKEN123
```

### Common Response Fields

| Field | Description |
|-------|-------------|
| `xRefNum` | Transaction reference/ID |
| `xError` | Error code (0 = success) |
| `xErrorMessage` | Error message (if any) |
| `xAuthCode` | Authorization code |
| `xToken` | Tokenized card reference |
| `xMaskedCardNumber` | Masked card (e.g., 4111XXXXXX1111) |
| `xCardType` | Card type (VI, MC, AE, DI, etc.) |

## Transaction Types

### Authorization (cc:auth)

Authorizes a payment without capturing funds immediately.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:auth
xCardNum=4111111111111111
xExp=1225
xCardCode=123
xAmount=19.99
xCurrency=USD
xInvoice=ORDER123
xOrderNumber=ORDER123
xName=John Doe
xAddress=123 Main St
xCity=Anytown
xState=CA
xZip=12345
xEmail=customer@example.com
xPhone=5551234567
```

**Response Example**:

```
xRefNum=123456789
xError=0
xErrorMessage=
xAuthCode=AUTH123
xCardType=VI
xMaskedCardNumber=4111XXXXXX1111
```

**Error Handling**:

```
xRefNum=123456789
xError=1
xErrorMessage=Declined
xAuthCode=
```

### Sale/Authorize & Capture (cc:sale)

Authorizes and captures payment immediately.

**Request Parameters**: Same as Authorization

**Response**: Same as Authorization

### Capture (cc:capture)

Captures a previously authorized transaction.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:capture
xRefNum=123456789
xAmount=19.99
```

**Response**:

```
xRefNum=123456789
xError=0
xErrorMessage=
```

### Refund (cc:void_refund)

Refunds or voids a transaction.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:void_refund
xRefNum=123456789
xAmount=19.99
```

**Response**:

```
xRefNum=123456789
xError=0
xErrorMessage=
```

### Void (cc:void)

Cancels an authorized transaction before capture.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:void
xRefNum=123456789
```

**Response**:

```
xRefNum=123456789
xError=0
xErrorMessage=
```

## Tokenization/Vault API

### Save Card (cc:save)

Tokenizes a card for future use.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:save
xCardNum=4111111111111111
xExp=1225
xCardCode=123
xCustomerId=CUST123
```

**Response**:

```
xRefNum=TOKEN123
xError=0
xErrorMessage=
xToken=TOKEN_ABC123
```

### List Tokens (cc:list)

Retrieves saved tokens for a customer.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:list
xCustomerId=CUST123
```

**Response**:

```
xTokens=TOKEN1,TOKEN2,TOKEN3
xError=0
xErrorMessage=
```

### Charge with Token (cc:sale)

Charges using a saved token.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:sale
xToken=TOKEN_ABC123
xAmount=19.99
xInvoice=ORDER123
```

### Delete Token (cc:delete)

Removes a saved token.

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:delete
xToken=TOKEN_ABC123
```

## 3D Secure Integration

### Initiate 3DS (cc:3ds_initiate)

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:3ds_initiate
xCardNum=4111111111111111
xExp=1225
xAmount=19.99
xCurrency=USD
xInvoice=ORDER123
```

**Response**:

```
xRefNum=123456789
xError=0
xErrorMessage=
x3DSVersion=2.2.0
xChallengeURL=https://...
```

### Verify 3DS (cc:3ds_result)

**Request Parameters**:

```
xKey=TOKEN_KEY
xVersion=4.0.0
xCommand=cc:3ds_result
xRefNum=123456789
xCAVV=base64_encoded_cavv
xECI=05
xXID=base64_encoded_xid
```

## Error Codes

| Code | Description | Action |
|------|-------------|--------|
| 0 | Success | Continue |
| 1 | Declined | Inform customer |
| 2 | Invalid data | Check parameters |
| 3 | Card error | Check card details |
| 4 | Communication error | Retry with exponential backoff |
| 5 | Timeout | Retry request |
| 6 | System error | Contact support |

## Card Type Codes

| Code | Type |
|------|------|
| VI | Visa |
| MC | MasterCard |
| AE | American Express |
| DI | Discover |
| JCB | JCB |
| MI | Maestro International |
| DN | Diners Club |
| CUP | China Union Pay |

## Currency Codes

Supports ISO 4217 currency codes:

- USD - US Dollar
- EUR - Euro
- GBP - British Pound
- CAD - Canadian Dollar
- AUD - Australian Dollar
- (and many others)

## Best Practices

### Security

1. **Always use HTTPS**: All connections must be encrypted
2. **Never log card numbers**: Implement proper data masking
3. **Secure credentials**: Store API keys securely
4. **Use tokens**: Tokenize cards instead of storing card data
5. **Validate input**: Always validate and sanitize input data

### Performance

1. **Implement retries**: Use exponential backoff for failed requests
2. **Set timeouts**: Configure appropriate request timeouts
3. **Queue requests**: Consider async processing for batch operations
4. **Monitor latency**: Track API response times
5. **Cache responses**: Cache non-sensitive data appropriately

### Error Handling

1. **Specific error codes**: Implement specific handling for different error types
2. **Customer messaging**: Don't expose technical details to customers
3. **Logging**: Log all transactions for auditing and debugging
4. **Notifications**: Alert administrators of critical errors
5. **Fallback behavior**: Define behavior when API is unavailable

## Integration Examples

### PHP Example

```php
<?php
function sendCardknoxRequest($params) {
    $url = 'https://api.cardknox.com/api/transaction';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Parse response
    parse_str($response, $result);
    return $result;
}

// Authorization Example
$request = array(
    'xKey' => 'your_token_key',
    'xVersion' => '4.0.0',
    'xCommand' => 'cc:auth',
    'xCardNum' => '4111111111111111',
    'xExp' => '1225',
    'xCardCode' => '123',
    'xAmount' => '19.99',
    'xCurrency' => 'USD',
    'xInvoice' => 'ORDER123'
);

$response = sendCardknoxRequest($request);

if ($response['xError'] == '0') {
    echo "Payment authorized! Reference: " . $response['xRefNum'];
} else {
    echo "Payment failed: " . $response['xErrorMessage'];
}
?>
```

## Testing

### Test Card Numbers

**Visa**: 4111 1111 1111 1111
**MasterCard**: 5555 5555 5555 4444
**American Express**: 3782 822463 10005
**Discover**: 6011 1111 1111 1117

**Use with**:
- Any future expiration date
- Any 3-4 digit CVV
- Test environment only

## API Rate Limits

- Requests are typically rate-limited to prevent abuse
- Implement appropriate delays between requests
- For batch processing, use async/queue patterns
- Contact Cardknox for production rate limits

## Support

For API support:
- Cardknox Documentation: https://www.cardknox.com/api/
- Technical Support: support@cardknox.com

## Version History

- **API v4.0.0**: Current stable version
- Supports all major features
- Recommended for all new integrations
