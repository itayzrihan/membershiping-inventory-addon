/**
 * Enhanced WooCommerce Integration Frontend JavaScript
 * 
 * Handles currency payment interactions and item-based pricing display
 */
(function($) {
    'use strict';
    
    var MembershipingEnhancedWC = {
        
        init: function() {
            this.bindEvents();
            this.checkInitialBalances();
        },
        
        bindEvents: function() {
            // Currency payment option selection
            $(document).on('change', 'input[name="payment_method"]', this.onPaymentMethodChange);
            
            // Add to cart validation for currency payments
            $(document).on('click', '.single_add_to_cart_button', this.validateCurrencyPayment);
            
            // Balance refresh
            $(document).on('click', '.refresh-currency-balance', this.refreshBalance);
            
            // Item pricing info tooltip
            $(document).on('mouseenter', '.item-pricing-info', this.showItemPricingTooltip);
            $(document).on('mouseleave', '.item-pricing-info', this.hideItemPricingTooltip);
        },
        
        onPaymentMethodChange: function() {
            var $this = $(this);
            var paymentMethod = $this.val();
            
            // Hide all balance warnings
            $('.currency-balance-warning').hide();
            
            if (paymentMethod && paymentMethod.startsWith('currency_')) {
                var currencyId = paymentMethod.replace('currency_', '');
                MembershipingEnhancedWC.checkCurrencyBalance(currencyId);
            }
        },
        
        checkCurrencyBalance: function(currencyId) {
            var $option = $('.currency-payment-option[data-currency-id="' + currencyId + '"]');
            var requiredAmount = parseFloat($option.data('price'));
            var quantity = parseInt($('.qty').val()) || 1;
            var totalRequired = requiredAmount * quantity;
            
            $.ajax({
                url: membershiping_enhanced_wc.ajax_url,
                type: 'POST',
                data: {
                    action: 'membershiping_check_currency_balance',
                    currency_id: currencyId,
                    nonce: membershiping_enhanced_wc.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var balance = parseFloat(response.data.balance);
                        var canAfford = balance >= totalRequired;
                        
                        MembershipingEnhancedWC.updateBalanceDisplay(currencyId, balance, totalRequired, canAfford);
                        MembershipingEnhancedWC.togglePaymentOption(currencyId, canAfford);
                    }
                },
                error: function() {
                    console.log('Error checking currency balance');
                }
            });
        },
        
        updateBalanceDisplay: function(currencyId, balance, required, canAfford) {
            var $option = $('.currency-payment-option[data-currency-id="' + currencyId + '"]');
            var $balance = $option.find('.currency-balance');
            
            // Update balance text
            var balanceText = $balance.text().replace(/Your balance: [^(]+/, 'Your balance: ' + balance.toFixed(2) + ' ');
            
            if (!canAfford) {
                balanceText += ' <span style="color: #d32f2f;">(' + membershiping_enhanced_wc.strings.insufficient_funds + ' - Need: ' + required.toFixed(2) + ')</span>';
            } else {
                balanceText = balanceText.replace(/ <span style="color: #d32f2f;">.*?<\/span>/, '');
            }
            
            $balance.html(balanceText);
        },
        
        togglePaymentOption: function(currencyId, canAfford) {
            var $radio = $('input[value="currency_' + currencyId + '"]');
            var $option = $radio.closest('.currency-payment-option');
            
            if (canAfford) {
                $radio.prop('disabled', false);
                $option.removeClass('insufficient-funds');
            } else {
                $radio.prop('disabled', true);
                $option.addClass('insufficient-funds');
                if ($radio.is(':checked')) {
                    $radio.prop('checked', false);
                }
            }
        },
        
        validateCurrencyPayment: function(e) {
            var selectedPayment = $('input[name="payment_method"]:checked').val();
            
            if (selectedPayment && selectedPayment.startsWith('currency_')) {
                var currencyId = selectedPayment.replace('currency_', '');
                var $option = $('.currency-payment-option[data-currency-id="' + currencyId + '"]');
                var $radio = $('input[value="currency_' + currencyId + '"]');
                
                if ($radio.is(':disabled')) {
                    e.preventDefault();
                    alert(membershiping_enhanced_wc.strings.insufficient_funds);
                    return false;
                }
            }
        },
        
        checkInitialBalances: function() {
            $('.currency-payment-option').each(function() {
                var currencyId = $(this).data('currency-id');
                if (currencyId) {
                    MembershipingEnhancedWC.checkCurrencyBalance(currencyId);
                }
            });
        },
        
        refreshBalance: function(e) {
            e.preventDefault();
            var $button = $(this);
            var currencyId = $button.data('currency-id');
            
            $button.text(membershiping_enhanced_wc.strings.checking_balance);
            
            MembershipingEnhancedWC.checkCurrencyBalance(currencyId);
            
            setTimeout(function() {
                $button.text('Refresh');
            }, 1000);
        },
        
        showItemPricingTooltip: function() {
            var $this = $(this);
            var productId = $this.data('product-id');
            
            if (!productId) return;
            
            // Create tooltip if it doesn't exist
            if (!$this.find('.pricing-tooltip').length) {
                var $tooltip = $('<div class="pricing-tooltip">Loading...</div>');
                $this.append($tooltip);
                
                $.ajax({
                    url: membershiping_enhanced_wc.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'membershiping_get_item_pricing',
                        product_id: productId,
                        nonce: membershiping_enhanced_wc.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var content = '<div class="pricing-breakdown">';
                            
                            response.data.forEach(function(item) {
                                content += '<div class="pricing-item' + (item.qualifies ? ' qualified' : '') + '">';
                                content += '<strong>' + item.item_name + '</strong><br>';
                                content += 'Required: ' + item.required_quantity + '<br>';
                                content += 'You have: ' + item.user_quantity + '<br>';
                                content += 'Special price: $' + item.special_price.toFixed(2);
                                if (item.qualifies) {
                                    content += ' <span class="qualified-badge">✓</span>';
                                }
                                content += '</div>';
                            });
                            
                            content += '</div>';
                            $tooltip.html(content);
                        } else {
                            $tooltip.html('Error loading pricing info');
                        }
                    },
                    error: function() {
                        $tooltip.html('Error loading pricing info');
                    }
                });
            }
            
            $this.find('.pricing-tooltip').show();
        },
        
        hideItemPricingTooltip: function() {
            $(this).find('.pricing-tooltip').hide();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MembershipingEnhancedWC.init();
    });
    
    // Re-check balances when quantity changes
    $(document).on('change', '.qty', function() {
        MembershipingEnhancedWC.checkInitialBalances();
    });
    
})(jQuery);
