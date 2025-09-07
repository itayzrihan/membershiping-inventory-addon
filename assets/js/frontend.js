/**
 * Membershiping Inventory Frontend JavaScript
 */

(function($) {
    'use strict';

    const MembershipingInventory = {
        
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initModals();
        },
        
        bindEvents: function() {
            // Item usage
            $(document).on('click', '.use-item-btn', this.handleUseItem);
            
            // Item details
            $(document).on('click', '.item-details-btn', this.handleItemDetails);
            
            // Trading
            $(document).on('click', '.trade-item-btn, .trade-nft-btn', this.handleTradeItem);
            
            // NFT certificates
            $(document).on('click', '.nft-certificate-btn', this.handleNFTCertificate);
            
            // Filters
            $(document).on('change', '.inventory-filter', this.handleFilterChange);
            
            // Modal close
            $(document).on('click', '.modal-close, .inventory-modal', this.handleModalClose);
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboard);
        },
        
        initFilters: function() {
            // Initialize filter state from URL parameters if any
            const urlParams = new URLSearchParams(window.location.search);
            const typeFilter = urlParams.get('type');
            const rarityFilter = urlParams.get('rarity');
            
            if (typeFilter) {
                $('#inventory-filter-type').val(typeFilter);
            }
            
            if (rarityFilter) {
                $('#inventory-filter-rarity').val(rarityFilter);
            }
            
            // Apply initial filters if set
            if (typeFilter || rarityFilter) {
                this.applyFilters();
            }
        },
        
        initModals: function() {
            // Create modal if it doesn't exist
            if ($('#item-details-modal').length === 0) {
                $('body').append(`
                    <div id="item-details-modal" class="inventory-modal" style="display: none;">
                        <div class="modal-content">
                            <span class="modal-close">&times;</span>
                            <div id="item-details-content">
                                <!-- Content loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                `);
            }
        },
        
        handleUseItem: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const itemId = $btn.data('item-id');
            const itemType = $btn.data('item-type');
            const nftId = $btn.data('nft-id') || 0;
            
            // Confirm action
            if (!confirm(membershiping_inventory_ajax.strings.confirm_use)) {
                return;
            }
            
            // Disable button and show loading
            $btn.prop('disabled', true).text(membershiping_inventory_ajax.strings.loading);
            
            $.ajax({
                url: membershiping_inventory_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_use_item',
                    nonce: membershiping_inventory_ajax.nonce,
                    item_id: itemId,
                    item_type: itemType,
                    nft_id: nftId,
                    quantity: 1
                },
                success: function(response) {
                    if (response.success) {
                        MembershipingInventory.showNotification(response.data.message, 'success');
                        
                        // Show effects if any
                        if (response.data.effects) {
                            MembershipingInventory.showEffects(response.data.effects);
                        }
                        
                        // Refresh inventory
                        MembershipingInventory.refreshInventory();
                    } else {
                        MembershipingInventory.showNotification(response.data || membershiping_inventory_ajax.strings.error, 'error');
                    }
                },
                error: function() {
                    MembershipingInventory.showNotification(membershiping_inventory_ajax.strings.error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Use');
                }
            });
        },
        
        handleItemDetails: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const itemId = $btn.data('item-id');
            const itemType = $btn.data('item-type');
            const nftId = $btn.data('nft-id') || 0;
            
            // Show loading in modal
            $('#item-details-content').html('<div class="loading-content">Loading...</div>');
            $('#item-details-modal').fadeIn(300);
            
            $.ajax({
                url: membershiping_inventory_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_get_item_details',
                    nonce: membershiping_inventory_ajax.nonce,
                    item_id: itemId,
                    item_type: itemType,
                    nft_id: nftId
                },
                success: function(response) {
                    if (response.success) {
                        MembershipingInventory.renderItemDetails(response.data);
                    } else {
                        $('#item-details-content').html('<p>Error loading item details.</p>');
                    }
                },
                error: function() {
                    $('#item-details-content').html('<p>Error loading item details.</p>');
                }
            });
        },
        
        renderItemDetails: function(item) {
            let html = '<div class="item-details-modal">';
            
            html += `<h3>${this.escapeHtml(item.name)}</h3>`;
            
            if (item.type === 'nft') {
                html += `<div class="nft-token"><strong>Token:</strong> ${this.escapeHtml(item.token)}</div>`;
                if (item.upgrade_level > 0) {
                    html += `<div class="upgrade-level"><strong>Upgrade Level:</strong> +${item.upgrade_level}</div>`;
                }
            } else {
                html += `<div class="item-quantity"><strong>Quantity:</strong> ${item.quantity}</div>`;
                html += `<div class="item-type"><strong>Type:</strong> ${this.escapeHtml(item.item_type)}</div>`;
            }
            
            html += `<div class="item-rarity"><strong>Rarity:</strong> <span class="rarity-${item.rarity}">${this.escapeHtml(item.rarity.charAt(0).toUpperCase() + item.rarity.slice(1))}</span></div>`;
            
            if (item.description) {
                html += `<div class="item-description"><strong>Description:</strong><p>${this.escapeHtml(item.description)}</p></div>`;
            }
            
            // Stats
            if (item.stats && Object.keys(item.stats).length > 0) {
                html += '<div class="item-stats"><strong>Stats:</strong>';
                html += '<div class="stats-grid">';
                for (const [stat, value] of Object.entries(item.stats)) {
                    if (stat !== 'special_abilities') {
                        html += `<div class="stat-item">`;
                        html += `<span class="stat-name">${this.escapeHtml(stat.replace('_', ' ').charAt(0).toUpperCase() + stat.slice(1))}:</span>`;
                        html += `<span class="stat-value">${this.escapeHtml(value)}</span>`;
                        html += `</div>`;
                    }
                }
                html += '</div>';
                
                // Special abilities
                if (item.stats.special_abilities) {
                    html += '<div class="special-abilities"><strong>Special:</strong> ';
                    const abilities = [];
                    for (const [ability, enabled] of Object.entries(item.stats.special_abilities)) {
                        if (enabled) {
                            abilities.push(ability.replace('_', ' ').charAt(0).toUpperCase() + ability.slice(1));
                        }
                    }
                    html += abilities.join(', ');
                    html += '</div>';
                }
                
                html += '</div>';
            }
            
            // Metadata for NFTs
            if (item.type === 'nft' && item.metadata) {
                html += '<div class="nft-metadata"><strong>Metadata:</strong>';
                html += '<div class="metadata-grid">';
                for (const [key, value] of Object.entries(item.metadata)) {
                    html += `<div class="metadata-item">`;
                    html += `<span class="metadata-key">${this.escapeHtml(key)}:</span>`;
                    html += `<span class="metadata-value">${this.escapeHtml(value)}</span>`;
                    html += `</div>`;
                }
                html += '</div></div>';
            }
            
            // Properties
            html += '<div class="item-properties">';
            if (item.is_tradeable !== undefined) {
                html += `<div class="property"><strong>Tradeable:</strong> ${item.is_tradeable ? 'Yes' : 'No'}</div>`;
            }
            if (item.is_consumable !== undefined) {
                html += `<div class="property"><strong>Consumable:</strong> ${item.is_consumable ? 'Yes' : 'No'}</div>`;
            }
            html += '</div>';
            
            // Dates
            html += '<div class="item-dates">';
            if (item.acquired_at || item.created_at) {
                const date = item.acquired_at || item.created_at;
                html += `<div class="date"><strong>Acquired:</strong> ${this.formatDate(date)}</div>`;
            }
            if (item.last_used_at) {
                html += `<div class="date"><strong>Last Used:</strong> ${this.formatDate(item.last_used_at)}</div>`;
            }
            html += '</div>';
            
            html += '</div>';
            
            $('#item-details-content').html(html);
        },
        
        handleTradeItem: function(e) {
            e.preventDefault();
            
            // This will be implemented with the trading system
            MembershipingInventory.showNotification('Trading system coming soon!', 'info');
        },
        
        handleNFTCertificate: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const nftId = $btn.data('nft-id');
            
            // Open certificate in new window
            const certificateUrl = `${window.location.origin}/wp-json/membershiping-inventory/v1/nft/certificate/${nftId}`;
            window.open(certificateUrl, '_blank', 'width=600,height=800');
        },
        
        handleFilterChange: function(e) {
            MembershipingInventory.applyFilters();
        },
        
        applyFilters: function() {
            const typeFilter = $('#inventory-filter-type').val();
            const rarityFilter = $('#inventory-filter-rarity').val();
            
            // Show loading
            $('#inventory-grid').addClass('loading');
            
            $.ajax({
                url: membershiping_inventory_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_get_inventory',
                    nonce: membershiping_inventory_ajax.nonce,
                    type: typeFilter,
                    rarity: rarityFilter
                },
                success: function(response) {
                    if (response.success) {
                        $('#inventory-grid').html(response.data.html);
                    }
                },
                error: function() {
                    MembershipingInventory.showNotification('Error applying filters', 'error');
                },
                complete: function() {
                    $('#inventory-grid').removeClass('loading');
                }
            });
            
            // Update URL parameters
            const url = new URL(window.location);
            if (typeFilter && typeFilter !== 'all') {
                url.searchParams.set('type', typeFilter);
            } else {
                url.searchParams.delete('type');
            }
            
            if (rarityFilter && rarityFilter !== 'all') {
                url.searchParams.set('rarity', rarityFilter);
            } else {
                url.searchParams.delete('rarity');
            }
            
            window.history.replaceState(null, '', url);
        },
        
        refreshInventory: function() {
            this.applyFilters();
        },
        
        handleModalClose: function(e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $('.inventory-modal').fadeOut(300);
            }
        },
        
        handleKeyboard: function(e) {
            // Close modal with Escape key
            if (e.keyCode === 27) {
                $('.inventory-modal').fadeOut(300);
            }
        },
        
        showNotification: function(message, type = 'info') {
            // Create notification element
            const notification = $(`
                <div class="inventory-notification ${type}">
                    <span class="notification-message">${this.escapeHtml(message)}</span>
                    <button class="notification-close">&times;</button>
                </div>
            `);
            
            // Add to page
            if ($('.inventory-notifications').length === 0) {
                $('body').append('<div class="inventory-notifications"></div>');
            }
            
            $('.inventory-notifications').append(notification);
            
            // Animate in
            notification.fadeIn(300);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            notification.find('.notification-close').on('click', function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        showEffects: function(effects) {
            if (!effects || typeof effects !== 'object') {
                return;
            }
            
            let effectsHtml = '<div class="item-effects-popup">';
            effectsHtml += '<h4>Item Effects:</h4>';
            effectsHtml += '<ul>';
            
            if (effects.currencies) {
                for (const [currency, amount] of Object.entries(effects.currencies)) {
                    effectsHtml += `<li>+${amount} ${currency}</li>`;
                }
            }
            
            if (effects.items) {
                for (const [item, quantity] of Object.entries(effects.items)) {
                    effectsHtml += `<li>+${quantity} ${item}</li>`;
                }
            }
            
            if (effects.flags) {
                for (const flag of effects.flags) {
                    effectsHtml += `<li>Flag: ${flag}</li>`;
                }
            }
            
            if (effects.points) {
                effectsHtml += `<li>+${effects.points} Points</li>`;
            }
            
            effectsHtml += '</ul></div>';
            
            // Show effects popup
            const popup = $(effectsHtml);
            $('body').append(popup);
            
            popup.css({
                position: 'fixed',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                background: 'white',
                padding: '20px',
                borderRadius: '8px',
                boxShadow: '0 10px 30px rgba(0,0,0,0.3)',
                zIndex: 1001,
                minWidth: '300px'
            });
            
            popup.fadeIn(300);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                popup.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    };
    
    // Additional notification styles
    const notificationStyles = `
        <style>
        .inventory-notifications {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 300px;
        }
        
        .inventory-notification {
            background: white;
            border-left: 4px solid #ccc;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: none;
            position: relative;
        }
        
        .inventory-notification.success { border-left-color: #28a745; }
        .inventory-notification.error { border-left-color: #dc3545; }
        .inventory-notification.warning { border-left-color: #ffc107; }
        .inventory-notification.info { border-left-color: #17a2b8; }
        
        .notification-message {
            display: block;
            margin-right: 20px;
        }
        
        .notification-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #666;
        }
        
        .notification-close:hover {
            color: #333;
        }
        
        .loading-content {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .item-details-modal h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .item-details-modal .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 10px;
        }
        
        .item-details-modal .stat-item,
        .item-details-modal .metadata-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-details-modal .property,
        .item-details-modal .date {
            margin: 5px 0;
        }
        
        .item-details-modal .special-abilities {
            margin-top: 10px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            font-style: italic;
        }
        </style>
    `;
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Add notification styles
        $('head').append(notificationStyles);
        
        // Initialize the inventory system
        MembershipingInventory.init();
    });

})(jQuery);
