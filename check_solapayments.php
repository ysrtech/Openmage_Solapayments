<?php
/**
 * Diagnostic script for Sola Payments module
 * 
 * Copy this file to your Magento root directory and run it:
 * php check_solapayments.php
 * 
 * Or access via browser:
 * http://yoursite.com/check_solapayments.php
 */

// Include Magento
require_once 'app/Mage.php';
Mage::app('admin');

echo "=== Sola Payments Diagnostic Check ===\n\n";

// Check 1: Module registered
echo "1. Module Registration:\n";
$modules = (array)Mage::getConfig()->getNode('modules')->children();
if (isset($modules['Openmage_Solapayments'])) {
    $module = $modules['Openmage_Solapayments'];
    echo "   ✓ Module is registered\n";
    echo "   - Active: " . (string)$module->active . "\n";
    echo "   - Code Pool: " . (string)$module->codePool . "\n";
} else {
    echo "   ✗ Module NOT registered\n";
}

// Check 2: Helper exists
echo "\n2. Helper Class:\n";
try {
    $helper = Mage::helper('solapayments');
    echo "   ✓ Helper loaded: " . get_class($helper) . "\n";
} catch (Exception $e) {
    echo "   ✗ Helper error: " . $e->getMessage() . "\n";
}

// Check 3: Payment model exists
echo "\n3. Payment Model:\n";
try {
    $model = Mage::getModel('solapayments/method');
    echo "   ✓ Model loaded: " . get_class($model) . "\n";
    echo "   - Code: " . $model->getCode() . "\n";
    echo "   - Can Use Checkout: " . ($model->canUseCheckout() ? 'Yes' : 'No') . "\n";
    echo "   - Can Use Internal: " . ($model->canUseInternal() ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "   ✗ Model error: " . $e->getMessage() . "\n";
}

// Check 4: Configuration
echo "\n4. Configuration:\n";
$storeId = Mage::app()->getStore()->getId();
echo "   - Store ID: " . $storeId . "\n";
echo "   - Enabled: " . (Mage::getStoreConfig('payment/solapayments/active', $storeId) ? 'Yes' : 'No') . "\n";
echo "   - Title: " . Mage::getStoreConfig('payment/solapayments/title', $storeId) . "\n";
$tokenKeyRaw = Mage::getStoreConfig('payment/solapayments/cardknox_token_key', $storeId);
$tokenKeyDecrypted = $helper->getTokenKey($storeId);
echo "   - Token Key (encrypted): " . ($tokenKeyRaw ? 'Set' : 'Not set') . "\n";
echo "   - Token Key (decrypted): " . ($tokenKeyDecrypted ? substr($tokenKeyDecrypted, 0, 6) . '...' : 'EMPTY') . "\n";
echo "   - Token Key Length: " . strlen($tokenKeyDecrypted) . "\n";
echo "   - Gateway URL: " . Mage::getStoreConfig('payment/solapayments/cgi_url', $storeId) . "\n";
echo "   - Payment Action: " . Mage::getStoreConfig('payment/solapayments/payment_action', $storeId) . "\n";

// Check 5: Currency support
echo "\n5. Currency Support:\n";
$currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
echo "   - Store Currency: " . $currencyCode . "\n";
if ($model) {
    echo "   - Can Use Currency: " . ($model->canUseForCurrency($currencyCode) ? 'Yes' : 'No') . "\n";
}

// Check 6: isAvailable
echo "\n6. Availability Check:\n";
if ($model) {
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId($storeId);
    // Set minimal context so availability checks pass
    $quote->getBillingAddress()->setCountryId('US');
    $quote->setBaseCurrencyCode('USD');
    $quote->setStoreCurrencyCode('USD');

    $isAvailable = $model->isAvailable($quote);
    echo "   - Is Available: " . ($isAvailable ? 'Yes' : 'No') . "\n";
    
    if (!$isAvailable) {
        echo "\n   Possible reasons:\n";
        if (!Mage::getStoreConfig('payment/solapayments/active', $storeId)) {
            echo "   - Module is not enabled in configuration\n";
        }
        if (!Mage::getStoreConfig('payment/solapayments/cardknox_token_key', $storeId)) {
            echo "   - Token Key is not configured\n";
        }
        if (!Mage::getStoreConfig('payment/solapayments/cgi_url', $storeId)) {
            echo "   - Gateway URL is not configured\n";
        }
    }
}

// Check 7: All payment methods
echo "\n7. All Configured Payment Methods:\n";
$payments = Mage::getSingleton('payment/config')->getActiveMethods();
foreach ($payments as $code => $method) {
    echo "   - " . $code . " (" . $method->getTitle() . ")\n";
}

// Check 7b: Check if solapayments is in ALL methods (not just active)
echo "\n7b. Debug - Check All Methods (including inactive):\n";
$allMethods = Mage::getSingleton('payment/config')->getAllMethods();
$found = false;
foreach ($allMethods as $code => $method) {
    if ($code === 'solapayments') {
        echo "   ✓ Found solapayments in getAllMethods()\n";
        echo "   - Title: " . $method->getTitle() . "\n";
        echo "   - Config Active: " . $method->getConfigData('active') . "\n";
        echo "   - Can Use Checkout: " . ($method->canUseCheckout() ? 'Yes' : 'No') . "\n";
        echo "   - Can Use Internal: " . ($method->canUseInternal() ? 'Yes' : 'No') . "\n";
        $testQuote = Mage::getModel('sales/quote');
        $testQuote->setStoreId(0);
        $testQuote->getBillingAddress()->setCountryId('US');
        $testQuote->setBaseCurrencyCode('USD');
        echo "   - isAvailable() from config: " . ($method->isAvailable($testQuote) ? 'Yes' : 'No') . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "   ✗ solapayments NOT found in getAllMethods()\n";
    echo "   This means the payment method is not registered in the config\n";
}

// Check 8: Test Form Block Instantiation
echo "\n8. Form Block Test:\n";
try {
    $block = Mage::app()->getLayout()->createBlock('solapayments/form');
    if ($block) {
        echo "   ✓ Form block created: " . get_class($block) . "\n";
        echo "   - Form Block Type from Model: " . $model->getFormBlockType() . "\n";
        echo "   - Info Block Type from Model: " . $model->getInfoBlockType() . "\n";
        // Set method on block
        $block->setMethod($model);
        echo "   - Method set on block successfully\n";
        echo "   - Template: " . $block->getTemplate() . "\n";
    } else {
        echo "   ✗ Form block could not be created\n";
    }
} catch (Exception $e) {
    echo "   ✗ Form block error: " . $e->getMessage() . "\n";
}

echo "\n=== End of Diagnostic ===\n";
