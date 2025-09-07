/**
 * Admin JavaScript for Membershiping Inventory Restrictions
 * Integrates with core plugin's restriction interface
 */

jQuery(document).ready(function($) {
    'use strict';
    
    const InventoryRestrictions = {
        
        init: function() {
            this.bindEvents();
            this.initializeExistingRestrictions();
        },
        
        bindEvents: function() {
            // Add new item restriction
            $(document).on('click', '.add-restriction-item', this.addItemRestriction);
            
            // Remove item restriction
            $(document).on('click', '.remove-restriction-item', this.removeItemRestriction);
            
            // Handle restriction type changes
            $(document).on('change', '.membershiping-restriction-type', this.handleRestrictionTypeChange);
            
            // Handle item selection changes
            $(document).on('change', '.item-select', this.handleItemSelectionChange);
            
            // Currency restriction toggles
            $(document).on('change', '.restriction-currency input[type="checkbox"]', this.toggleCurrencyFields);
            
            // NFT restriction toggles
            $(document).on('change', '.restriction-nft input[type="checkbox"]', this.toggleNftFields);
            
            // Preview user restrictions
            $(document).on('click', '.preview-user-restrictions', this.previewUserRestrictions);
            
            // Validate restrictions before save
            $(document).on('click', '#publish, #save-post', this.validateRestrictions);
        },
        
        initializeExistingRestrictions: function() {
            // Initialize currency field visibility
            $('.restriction-currency input[type="checkbox"]').each(function() {
                InventoryRestrictions.toggleCurrencyFields.call(this);
            });
            
            // Initialize NFT field visibility
            $('.restriction-nft input[type="checkbox"]').each(function() {
                InventoryRestrictions.toggleNftFields.call(this);
            });
            
            // Add visual indicators for restriction types
            this.addRestrictionTypeIndicators();
        },
        
        handleRestrictionTypeChange: function() {
            const $this = $(this);
            const restrictionType = $this.val();
            const $container = $this.closest('.membershiping-restriction-container');
            
            // Hide all restriction field containers
            $container.find('.membershiping-inventory-restriction-fields').hide();
            
            // Show the selected restriction type fields
            $container.find('.membershiping-inventory-restriction-fields[data-type="' + restrictionType + '"]').show();
            
            // Update the restriction type indicator
            InventoryRestrictions.updateRestrictionTypeIndicator($container, restrictionType);
        },
        
        addItemRestriction: function(e) {
            e.preventDefault();
            
            const $container = $(this).closest('.inventory-item-restrictions');
            const $template = $container.find('.restriction-item-template');
            const $newItem = $template.clone()
                .removeClass('restriction-item-template')
                .show()
                .appendTo($container.find('.restriction-items-list'));
            
            // Update the indices for proper form submission
            InventoryRestrictions.updateItemIndices($container);
            
            // Add visual feedback
            $newItem.hide().fadeIn(300);
            
            // Scroll to the new item
            $('html, body').animate({
                scrollTop: $newItem.offset().top - 100
            }, 300);
        },
        
        removeItemRestriction: function(e) {
            e.preventDefault();
            
            const $item = $(this).closest('.restriction-item');
            const $container = $item.closest('.inventory-item-restrictions');
            
            // Animate removal
            $item.fadeOut(300, function() {
                $item.remove();
                InventoryRestrictions.updateItemIndices($container);
            });
        },
        
        updateItemIndices: function($container) {
            $container.find('.restriction-item:not(.restriction-item-template)').each(function(index) {
                const $item = $(this);
                
                // Update input names with correct indices
                $item.find('select[name*="[items]"]').attr('name', 'membershiping_inventory_restrictions[items][' + index + ']');
                $item.find('input[name*="[quantities]"]').attr('name', 'membershiping_inventory_restrictions[quantities][' + index + ']');
                $item.find('select[name*="[rarities]"]').attr('name', 'membershiping_inventory_restrictions[rarities][' + index + ']');
                $item.find('input[name*="[consume]"]').attr('name', 'membershiping_inventory_restrictions[consume][' + index + ']');
            });
        },
        
        handleItemSelectionChange: function() {
            const $select = $(this);
            const $item = $select.closest('.restriction-item');
            const itemData = $select.find('option:selected').data();
            
            if (itemData && itemData.rarity) {
                // Set minimum rarity based on item's rarity
                const $raritySelect = $item.find('select[name*="[rarities]"]');
                $raritySelect.val(itemData.rarity);
                
                // Add visual indicator of item type and rarity
                $item.removeClass('item-common item-uncommon item-rare item-epic item-legendary item-mythic')
                     .addClass('item-' + itemData.rarity);
                
                // Show/hide consume option based on item type
                const $consumeOption = $item.find('label:has(input[name*="[consume]"])');
                if (itemData.type === 'consumable') {
                    $consumeOption.show();
                } else {
                    $consumeOption.hide();
                    $item.find('input[name*="[consume]"]').prop('checked', false);
                }
            }
        },
        
        toggleCurrencyFields: function() {
            const $checkbox = $(this);
            const $fields = $checkbox.closest('.restriction-currency').find('.currency-fields');
            
            if ($checkbox.is(':checked')) {
                $fields.slideDown(300);
            } else {
                $fields.slideUp(300);
            }
        },
        
        toggleNftFields: function() {
            const $checkbox = $(this);
            const $fields = $checkbox.closest('.restriction-nft').find('.nft-fields');
            
            if ($checkbox.is(':checked')) {
                $fields.slideDown(300);
            } else {
                $fields.slideUp(300);
            }
        },
        
        addRestrictionTypeIndicators: function() {
            $('.membershiping-inventory-restriction-fields').each(function() {
                const $container = $(this);
                const restrictionType = $container.data('type');
                let icon = '';
                let label = '';
                
                switch (restrictionType) {
                    case 'inventory_items':
                        icon = 'dashicons-archive';
                        label = membershipingInventoryRestrictions.strings.inventoryItems || 'Inventory Items';
                        break;
                    case 'inventory_currencies':
                        icon = 'dashicons-money-alt';
                        label = membershipingInventoryRestrictions.strings.currencies || 'Currencies';
                        break;
                    case 'inventory_nfts':
                        icon = 'dashicons-images-alt2';
                        label = membershipingInventoryRestrictions.strings.nfts || 'NFTs';
                        break;
                    case 'inventory_level':
                        icon = 'dashicons-chart-line';
                        label = membershipingInventoryRestrictions.strings.level || 'Level';
                        break;
                }
                
                if (icon && label) {
                    $container.prepend(
                        '<div class="restriction-type-indicator">' +
                        '<span class="dashicons ' + icon + '"></span>' +
                        '<strong>' + label + '</strong>' +
                        '</div>'
                    );
                }
            });
        },
        
        updateRestrictionTypeIndicator: function($container, restrictionType) {
            // This would update the visual indicator when restriction type changes
            // Implementation depends on the core plugin's structure
        },
        
        previewUserRestrictions: function(e) {
            e.preventDefault();
            
            const userId = prompt('Enter User ID to preview restrictions:');
            if (!userId || isNaN(userId)) {
                alert('Please enter a valid User ID.');
                return;
            }
            
            // Show loading state
            const $button = $(this);
            const originalText = $button.text();
            $button.text('Loading...').prop('disabled', true);
            
            // Get user's inventory for preview
            $.ajax({
                url: membershipingInventoryRestrictions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_get_user_items_for_restriction',
                    user_id: userId,
                    nonce: membershipingInventoryRestrictions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        InventoryRestrictions.showUserInventoryPreview(userId, response.data);
                    } else {
                        alert('Error: ' + (response.data || 'Failed to load user inventory'));
                    }
                },
                error: function() {
                    alert('Failed to load user inventory. Please try again.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        showUserInventoryPreview: function(userId, userData) {
            // Create modal or popup to show user's inventory
            const modal = $('<div class="membershiping-inventory-preview-modal">' +
                '<div class="modal-content">' +
                '<h3>User #' + userId + ' Inventory Preview</h3>' +
                '<div class="user-inventory-tabs">' +
                '<div class="tab-nav">' +
                '<button class="tab-button active" data-tab="items">Items</button>' +
                '<button class="tab-button" data-tab="currencies">Currencies</button>' +
                '<button class="tab-button" data-tab="nfts">NFTs</button>' +
                '</div>' +
                '<div class="tab-content">' +
                '<div class="tab-pane active" id="items-tab"></div>' +
                '<div class="tab-pane" id="currencies-tab"></div>' +
                '<div class="tab-pane" id="nfts-tab"></div>' +
                '</div>' +
                '</div>' +
                '<div class="modal-actions">' +
                '<button class="button modal-close">Close</button>' +
                '</div>' +
                '</div>' +
                '</div>');
            
            // Populate items tab
            let itemsHtml = '<h4>Owned Items</h4>';
            if (userData.items && userData.items.length > 0) {
                itemsHtml += '<ul class="user-items-list">';
                userData.items.forEach(function(item) {
                    itemsHtml += '<li class="user-item">' +
                        '<strong>' + item.name + '</strong> ' +
                        '<span class="quantity">×' + item.quantity + '</span> ' +
                        '<span class="rarity rarity-' + item.rarity + '">' + item.rarity + '</span>' +
                        '</li>';
                });
                itemsHtml += '</ul>';
            } else {
                itemsHtml += '<p>No items owned.</p>';
            }
            modal.find('#items-tab').html(itemsHtml);
            
            // Populate currencies tab
            let currenciesHtml = '<h4>Currency Balances</h4>';
            if (userData.currencies && userData.currencies.length > 0) {
                currenciesHtml += '<ul class="user-currencies-list">';
                userData.currencies.forEach(function(currency) {
                    currenciesHtml += '<li class="user-currency">' +
                        '<strong>' + currency.name + '</strong> ' +
                        '<span class="amount">' + currency.amount + ' ' + currency.symbol + '</span>' +
                        '</li>';
                });
                currenciesHtml += '</ul>';
            } else {
                currenciesHtml += '<p>No currencies owned.</p>';
            }
            modal.find('#currencies-tab').html(currenciesHtml);
            
            // Populate NFTs tab
            let nftsHtml = '<h4>Owned NFTs</h4>';
            if (userData.nfts && userData.nfts.length > 0) {
                nftsHtml += '<ul class="user-nfts-list">';
                userData.nfts.forEach(function(nft) {
                    nftsHtml += '<li class="user-nft">' +
                        '<strong>' + nft.name + '</strong> ' +
                        '<span class="token-id">#' + nft.token_id + '</span> ' +
                        '<span class="rarity rarity-' + nft.rarity + '">' + nft.rarity + '</span>' +
                        '</li>';
                });
                nftsHtml += '</ul>';
            } else {
                nftsHtml += '<p>No NFTs owned.</p>';
            }
            modal.find('#nfts-tab').html(nftsHtml);
            
            // Add modal to page and show
            $('body').append(modal);
            modal.fadeIn(300);
            
            // Handle tab switching
            modal.on('click', '.tab-button', function() {
                const $button = $(this);
                const tabId = $button.data('tab');
                
                modal.find('.tab-button').removeClass('active');
                modal.find('.tab-pane').removeClass('active');
                
                $button.addClass('active');
                modal.find('#' + tabId + '-tab').addClass('active');
            });
            
            // Handle modal close
            modal.on('click', '.modal-close, .membershiping-inventory-preview-modal', function(e) {
                if (e.target === this) {
                    modal.fadeOut(300, function() {
                        modal.remove();
                    });
                }
            });
        },
        
        validateRestrictions: function(e) {
            let hasErrors = false;
            const errors = [];
            
            // Validate item restrictions
            $('.restriction-item:not(.restriction-item-template)').each(function() {
                const $item = $(this);
                const itemId = $item.find('.item-select').val();
                const quantity = parseInt($item.find('input[name*="[quantities]"]').val());
                
                if (itemId && (!quantity || quantity < 1)) {
                    hasErrors = true;
                    errors.push('Item restriction must have a quantity of at least 1.');
                    $item.addClass('has-error');
                } else {
                    $item.removeClass('has-error');
                }
            });
            
            // Validate currency restrictions
            $('.restriction-currency').each(function() {
                const $currency = $(this);
                const isEnabled = $currency.find('input[type="checkbox"]').is(':checked');
                const amount = parseFloat($currency.find('input[name*="[amount]"]').val());
                
                if (isEnabled && (!amount || amount < 0)) {
                    hasErrors = true;
                    errors.push('Currency restriction must have a valid amount.');
                    $currency.addClass('has-error');
                } else {
                    $currency.removeClass('has-error');
                }
            });
            
            // Validate level restrictions
            const minLevel = parseInt($('input[name*="[min_level]"]').val());
            const minExperience = parseInt($('input[name*="[min_experience]"]').val());
            
            if (minLevel && (minLevel < 1 || minLevel > 100)) {
                hasErrors = true;
                errors.push('Level must be between 1 and 100.');
            }
            
            if (minExperience && minExperience < 0) {
                hasErrors = true;
                errors.push('Experience must be 0 or greater.');
            }
            
            // Show errors if any
            if (hasErrors) {
                e.preventDefault();
                
                let errorMessage = 'Please fix the following errors:\n\n';
                errors.forEach(function(error, index) {
                    errorMessage += (index + 1) + '. ' + error + '\n';
                });
                
                alert(errorMessage);
                
                // Scroll to first error
                const $firstError = $('.has-error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                }
            }
        }
    };
    
    // Initialize the restrictions interface
    InventoryRestrictions.init();
    
    // Expose to global scope for debugging
    window.InventoryRestrictions = InventoryRestrictions;
});
