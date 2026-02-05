/**
 * Idev OneStepCheckout Compatibility for Sola Payments
 * 
 * Idev OneStepCheckout excludes sensitive CC fields from AJAX requests for PCI compliance.
 * This script ensures solapayments CC fields are included in the payment method validation.
 */

document.observe('dom:loaded', function() {
    // Override Idev's payment method saving to include CC fields for solapayments
    if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
        
        // Store original method
        var originalGetPaymentData = null;
        
        // Try different possible locations for the payment data function
        if (IWD.OPC.Checkout && IWD.OPC.Checkout.prototype && IWD.OPC.Checkout.prototype.getPaymentData) {
            originalGetPaymentData = IWD.OPC.Checkout.prototype.getPaymentData;
            
            IWD.OPC.Checkout.prototype.getPaymentData = function() {
                var data = originalGetPaymentData.apply(this, arguments);
                
                // If solapayments is selected, ensure CC fields are included
                var selectedMethod = $$('input[name="payment[method]"]:checked')[0];
                if (selectedMethod && selectedMethod.value === 'solapayments') {
                    // Get CC fields
                    var ccNumber = $('solapayments_cc_number');
                    var ccCid = $('solapayments_cc_cid');
                    
                    if (ccNumber && ccNumber.value) {
                        data['payment[cc_number]'] = ccNumber.value;
                    }
                    if (ccCid && ccCid.value) {
                        data['payment[cc_cid]'] = ccCid.value;
                    }
                }
                
                return data;
            };
        }
        
        // Also try to intercept the AJAX request directly
        if (IWD.OPC.saveOrderStatus) {
            var originalSaveOrderStatus = IWD.OPC.saveOrderStatus;
            
            IWD.OPC.saveOrderStatus = function(url, update_payments) {
                // Before sending, add CC fields to the form if solapayments is selected
                var selectedMethod = $$('input[name="payment[method]"]:checked')[0];
                if (selectedMethod && selectedMethod.value === 'solapayments') {
                    var form = $('onestepcheckout-form');
                    if (form) {
                        // Create hidden inputs for CC data if they don't exist
                        var ccNumber = $('solapayments_cc_number');
                        var ccCid = $('solapayments_cc_cid');
                        
                        if (ccNumber && ccNumber.value) {
                            var hiddenCcNumber = form.down('input[name="payment[cc_number]"]');
                            if (!hiddenCcNumber) {
                                hiddenCcNumber = new Element('input', {
                                    type: 'hidden',
                                    name: 'payment[cc_number]',
                                    id: 'hidden_cc_number'
                                });
                                form.insert(hiddenCcNumber);
                            }
                            hiddenCcNumber.value = ccNumber.value;
                        }
                        
                        if (ccCid && ccCid.value) {
                            var hiddenCcCid = form.down('input[name="payment[cc_cid]"]');
                            if (!hiddenCcCid) {
                                hiddenCcCid = new Element('input', {
                                    type: 'hidden',
                                    name: 'payment[cc_cid]',
                                    id: 'hidden_cc_cid'
                                });
                                form.insert(hiddenCcCid);
                            }
                            hiddenCcCid.value = ccCid.value;
                        }
                    }
                }
                
                return originalSaveOrderStatus.apply(this, arguments);
            };
        }
    }
    
    // Alternative approach: Intercept Ajax.Request for set_methods_separate
    if (typeof Ajax !== 'undefined' && Ajax.Request) {
        var originalRequest = Ajax.Request.prototype.initialize;
        
        Ajax.Request.prototype.initialize = function(url, options) {
            // Check if this is the set_methods_separate AJAX call
            if (url && url.indexOf('set_methods_separate') !== -1) {
                console.log('[Sola Payments] Intercepted set_methods_separate AJAX call');
                
                var selectedMethod = $$('input[name="payment[method]"]:checked')[0];
                console.log('[Sola Payments] Selected payment method:', selectedMethod ? selectedMethod.value : 'none');
                
                if (selectedMethod && selectedMethod.value === 'solapayments') {
                    // Add CC fields to parameters
                    options = options || {};
                    options.parameters = options.parameters || {};
                    
                    var ccNumber = $('solapayments_cc_number');
                    var ccCid = $('solapayments_cc_cid');
                    
                    console.log('[Sola Payments] CC Number field:', ccNumber);
                    console.log('[Sola Payments] CC Number value:', ccNumber ? ccNumber.value : 'field not found');
                    console.log('[Sola Payments] CVV field:', ccCid);
                    console.log('[Sola Payments] CVV value:', ccCid ? ccCid.value : 'field not found');
                    
                    if (typeof options.parameters === 'string') {
                        console.log('[Sola Payments] Parameters are string format');
                        // Convert string to object
                        var params = options.parameters.toQueryParams();
                        console.log('[Sola Payments] Current parameters:', params);
                        
                        if (ccNumber && ccNumber.value) {
                            params['payment[cc_number]'] = ccNumber.value;
                            console.log('[Sola Payments] Added cc_number to params');
                        }
                        if (ccCid && ccCid.value) {
                            params['payment[cc_cid]'] = ccCid.value;
                            console.log('[Sola Payments] Added cc_cid to params');
                        }
                        options.parameters = Object.toQueryString(params);
                        console.log('[Sola Payments] Updated parameters string:', options.parameters);
                    } else {
                        console.log('[Sola Payments] Parameters are object format');
                        console.log('[Sola Payments] Current parameters:', options.parameters);
                        
                        if (ccNumber && ccNumber.value) {
                            options.parameters['payment[cc_number]'] = ccNumber.value;
                            console.log('[Sola Payments] Added cc_number to params');
                        }
                        if (ccCid && ccCid.value) {
                            options.parameters['payment[cc_cid]'] = ccCid.value;
                            console.log('[Sola Payments] Added cc_cid to params');
                        }
                        console.log('[Sola Payments] Updated parameters object:', options.parameters);
                    }
                }
            }
            
            return originalRequest.apply(this, [url, options]);
        };
    }
    
    console.log('Sola Payments Idev compatibility loaded');
});
