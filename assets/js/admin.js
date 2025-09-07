/**
 * Admin JavaScript for Membershiping Inventory
 * Interactive functionality for the admin dashboard
 */

(function($) {
    'use strict';
    
    // Main admin object
    window.membershipingInventoryAdmin = {
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.loadDashboardData();
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Dashboard stats refresh
            $(document).on('click', '#refresh-stats', this.loadDashboardStats.bind(this));
            
            // User search
            $(document).on('submit', '#user-search-form', this.searchUsers.bind(this));
            
            // Bulk operations
            $(document).on('submit', '#bulk-award-form', this.bulkAwardItems.bind(this));
            $(document).on('submit', '#bulk-remove-form', this.bulkRemoveItems.bind(this));
            
            // Item management
            $(document).on('click', '.edit-item', this.editItem.bind(this));
            $(document).on('click', '.delete-item', this.deleteItem.bind(this));
            $(document).on('click', '.view-owners', this.viewItemOwners.bind(this));
            
            // Currency management
            $(document).on('submit', '#add-currency-form', this.addCurrency.bind(this));
            $(document).on('click', '.edit-currency', this.editCurrency.bind(this));
            
            // System tools
            $(document).on('click', '#run-diagnostics', this.runDiagnostics.bind(this));
            $(document).on('click', '#cleanup-orphaned', this.cleanupOrphanedData.bind(this));
            $(document).on('click', '#optimize-tables', this.optimizeTables.bind(this));
            
            // Export/Import
            $(document).on('submit', '#export-form', this.exportData.bind(this));
            $(document).on('submit', '#import-form', this.importData.bind(this));
            
            // Reset tools
            $(document).on('click', '#reset-all-inventories', this.resetAllInventories.bind(this));
            $(document).on('click', '#reset-all-trades', this.resetAllTrades.bind(this));
            $(document).on('click', '#reset-all-currencies', this.resetAllCurrencies.bind(this));
            
            // Analytics filters
            $(document).on('submit', '#analytics-filters', this.updateAnalytics.bind(this));
            
            // Bulk select
            $(document).on('change', '#cb-select-all-1', this.toggleBulkSelect.bind(this));
            $(document).on('click', '#bulk-apply-top', this.applyBulkAction.bind(this));
            
            // User inventory management
            $(document).on('click', '#manage-user-inventory', this.openUserInventoryModal.bind(this));
            $(document).on('click', '.membershiping-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.add-item-btn', this.addUserItem.bind(this));
            $(document).on('click', '.update-item-btn', this.updateUserItem.bind(this));
            $(document).on('click', '.remove-item-btn', this.removeUserItem.bind(this));
            $(document).on('click', '.add-currency-btn', this.addUserCurrency.bind(this));
            $(document).on('click', '.update-currency-btn', this.updateUserCurrency.bind(this));
        },
        
        // Initialize charts
        initCharts: function() {
            this.charts = {};
            
            // Activity Chart
            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                this.charts.activity = new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Trades',
                            data: [],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Items Awarded',
                            data: [],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Item Distribution Chart
            const itemDistCtx = document.getElementById('itemDistributionChart');
            if (itemDistCtx) {
                this.charts.itemDistribution = new Chart(itemDistCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#94a3b8', '#10b981', '#3b82f6', 
                                '#8b5cf6', '#f59e0b', '#ef4444'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Trading Volume Chart
            const tradingCtx = document.getElementById('tradingVolumeChart');
            if (tradingCtx) {
                this.charts.trading = new Chart(tradingCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Completed Trades',
                            data: [],
                            backgroundColor: '#3b82f6'
                        }, {
                            label: 'Pending Trades',
                            data: [],
                            backgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Currency Usage Chart
            const currencyCtx = document.getElementById('currencyUsageChart');
            if (currencyCtx) {
                this.charts.currency = new Chart(currencyCtx, {
                    type: 'pie',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b',
                                '#8b5cf6', '#ef4444', '#06b6d4'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },
        
        // Load dashboard data
        loadDashboardData: function() {
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_get_dashboard_stats',
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateDashboardStats(response.data.stats);
                        this.updateActivityChart(response.data.activity);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Update dashboard stats
        updateDashboardStats: function(stats) {
            $('.stat-card').each(function() {
                const $card = $(this);
                const statKey = $card.data('stat');
                if (stats[statKey] !== undefined) {
                    $card.find('h3').text(this.formatNumber(stats[statKey]));
                }
            }.bind(this));
        },
        
        // Update activity chart
        updateActivityChart: function(activity) {
            if (!this.charts.activity) return;
            
            const labels = activity.map(item => this.formatDate(item.date));
            const tradesData = activity.map(item => item.trades);
            const itemsData = activity.map(item => item.items_awarded);
            
            this.charts.activity.data.labels = labels;
            this.charts.activity.data.datasets[0].data = tradesData;
            this.charts.activity.data.datasets[1].data = itemsData;
            this.charts.activity.update();
        },
        
        // Search users
        searchUsers: function(e) {
            e.preventDefault();
            
            const searchTerm = $('#user_search').val().trim();
            if (!searchTerm) {
                this.showError('Please enter a search term');
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_search_users',
                    nonce: membershipingInventoryAdmin.nonce,
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success) {
                        this.displayUserSearchResults(response.data);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Display user search results
        displayUserSearchResults: function(users) {
            const $results = $('#user-inventory-results');
            const $content = $('#user-inventory-content');
            
            if (!users || users.length === 0) {
                $content.html('<p>No users found.</p>');
                $results.show();
                return;
            }
            
            let html = '<div class="user-list">';
            users.forEach(function(user) {
                html += '<div class="user-item">';
                html += '<h4>' + this.escapeHtml(user.display_name) + ' (' + this.escapeHtml(user.user_email) + ')</h4>';
                html += '<button class="button view-user-inventory" data-user-id="' + user.ID + '">View Inventory</button>';
                html += '</div>';
            }.bind(this));
            html += '</div>';
            
            $content.html(html);
            $results.show();
            
            // Bind user inventory view
            $('.view-user-inventory').on('click', this.viewUserInventory.bind(this));
        },
        
        // View user inventory
        viewUserInventory: function(e) {
            e.preventDefault();
            
            const userId = $(e.target).data('user-id');
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_get_user_inventory',
                    nonce: membershipingInventoryAdmin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        this.displayUserInventory(response.data);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Display user inventory
        displayUserInventory: function(inventory) {
            let html = '<div class="user-inventory-display">';
            
            // Items
            if (inventory.items && inventory.items.length > 0) {
                html += '<div class="inventory-section">';
                html += '<h3>Items</h3>';
                html += '<div class="inventory-grid">';
                inventory.items.forEach(function(item) {
                    html += '<div class="inventory-item">';
                    html += '<div class="item-name">' + this.escapeHtml(item.name) + '</div>';
                    html += '<div class="item-quantity">Quantity: ' + item.quantity + '</div>';
                    html += '<div class="item-actions">';
                    html += '<button class="button remove-user-item" data-user-id="' + item.user_id + '" data-item-id="' + item.item_id + '">Remove</button>';
                    html += '</div>';
                    html += '</div>';
                }.bind(this));
                html += '</div>';
                html += '</div>';
            }
            
            // Currencies
            if (inventory.currencies && inventory.currencies.length > 0) {
                html += '<div class="inventory-section">';
                html += '<h3>Currencies</h3>';
                html += '<div class="inventory-grid">';
                inventory.currencies.forEach(function(currency) {
                    html += '<div class="inventory-item">';
                    html += '<div class="item-name">' + this.escapeHtml(currency.name) + '</div>';
                    html += '<div class="item-quantity">Amount: ' + this.formatNumber(currency.amount) + '</div>';
                    html += '</div>';
                }.bind(this));
                html += '</div>';
                html += '</div>';
            }
            
            // NFTs
            if (inventory.nfts && inventory.nfts.length > 0) {
                html += '<div class="inventory-section">';
                html += '<h3>NFTs</h3>';
                html += '<div class="inventory-grid">';
                inventory.nfts.forEach(function(nft) {
                    html += '<div class="inventory-item">';
                    html += '<div class="item-name">' + this.escapeHtml(nft.name) + '</div>';
                    html += '<div class="item-quantity">Rarity: ' + this.escapeHtml(nft.rarity) + '</div>';
                    html += '<div class="item-quantity">Token: ' + this.escapeHtml(nft.token_id) + '</div>';
                    html += '</div>';
                }.bind(this));
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $('#user-inventory-content').html(html);
            
            // Bind remove item events
            $('.remove-user-item').on('click', this.removeUserItem.bind(this));
        },
        
        // Bulk award items
        bulkAwardItems: function(e) {
            e.preventDefault();
            
            const userIds = $('#bulk_users').val();
            const itemId = $('#bulk_item').val();
            const quantity = $('#bulk_quantity').val();
            
            if (!userIds || userIds.length === 0 || !itemId || !quantity) {
                this.showError('Please fill in all required fields');
                return;
            }
            
            if (!confirm('Are you sure you want to award items to ' + userIds.length + ' users?')) {
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_bulk_award_items',
                    nonce: membershipingInventoryAdmin.nonce,
                    user_ids: userIds,
                    item_id: itemId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Items awarded successfully');
                        $('#bulk-award-form')[0].reset();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Add currency
        addCurrency: function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'membershiping_add_currency');
            formData.append('nonce', membershipingInventoryAdmin.nonce);
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Currency added successfully');
                        location.reload(); // Refresh to show new currency
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Run diagnostics
        runDiagnostics: function(e) {
            e.preventDefault();
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_system_diagnostics',
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayDiagnosticResults(response.data);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Display diagnostic results
        displayDiagnosticResults: function(diagnostics) {
            let html = '<div class="diagnostic-results">';
            
            Object.keys(diagnostics).forEach(function(category) {
                html += '<h4>' + this.escapeHtml(category.replace('_', ' ').toUpperCase()) + '</h4>';
                
                diagnostics[category].forEach(function(check) {
                    html += '<div class="diagnostic-item">';
                    html += '<span>' + this.escapeHtml(check.check) + '</span>';
                    html += '<span class="diagnostic-status ' + check.status + '">' + check.status + '</span>';
                    html += '</div>';
                    if (check.message) {
                        html += '<div class="diagnostic-message">' + this.escapeHtml(check.message) + '</div>';
                    }
                }.bind(this));
            }.bind(this));
            
            html += '</div>';
            
            $('#diagnostic-results').html(html).show();
        },
        
        // Export data
        exportData: function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'membershiping_export_data');
            formData.append('nonce', membershipingInventoryAdmin.nonce);
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Trigger file download
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = 'membershiping-inventory-export-' + new Date().toISOString().split('T')[0] + '.json';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        
                        this.showSuccess('Data exported successfully');
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Utility functions
        showLoading: function() {
            if ($('.loading-overlay').length === 0) {
                $('body').append('<div class="loading-overlay"><div class="loading-spinner"></div></div>');
            }
        },
        
        hideLoading: function() {
            $('.loading-overlay').remove();
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + this.escapeHtml(message) + '</p></div>');
            $('.wrap').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
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
        
        toggleBulkSelect: function(e) {
            const isChecked = $(e.target).prop('checked');
            $('input[name="item[]"]').prop('checked', isChecked);
        },
        
        applyBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#bulk-action-selector-top').val();
            const selectedItems = $('input[name="item[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (action === '-1') {
                this.showError('Please select an action');
                return;
            }
            
            if (selectedItems.length === 0) {
                this.showError('Please select items');
                return;
            }
            
            if (!confirm('Are you sure you want to ' + action + ' ' + selectedItems.length + ' items?')) {
                return;
            }
            
            // Handle bulk actions
            switch (action) {
                case 'delete':
                    this.bulkDeleteItems(selectedItems);
                    break;
                case 'duplicate':
                    this.bulkDuplicateItems(selectedItems);
                    break;
                case 'export':
                    this.bulkExportItems(selectedItems);
                    break;
            }
        },
        
        bulkDeleteItems: function(itemIds) {
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_bulk_delete_items',
                    nonce: membershipingInventoryAdmin.nonce,
                    item_ids: itemIds
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Items deleted successfully');
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(membershipingInventoryAdmin.strings.error);
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Open user inventory management modal
        openUserInventoryModal: function(e) {
            e.preventDefault();
            
            const userId = $(e.currentTarget).data('user-id');
            
            this.showLoading();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_manage_user_inventory',
                    user_id: userId,
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('body').append(response.data.modal_html);
                        $('#user-inventory-modal').show();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to load user inventory');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },
        
        // Close modal
        closeModal: function(e) {
            e.preventDefault();
            $('.membershiping-modal').remove();
        },
        
        // Add item to user
        addUserItem: function(e) {
            e.preventDefault();
            
            const userId = $(e.currentTarget).data('user-id');
            const itemId = $('#add-item-select').val();
            const quantity = $('#add-item-quantity').val();
            
            if (!itemId || !quantity) {
                this.showError('Please select an item and quantity');
                return;
            }
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_add_user_item',
                    user_id: userId,
                    item_id: itemId,
                    quantity: quantity,
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Item added successfully');
                        $('.membershiping-modal').remove();
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to add item');
                }.bind(this)
            });
        },
        
        // Update user item quantity
        updateUserItem: function(e) {
            e.preventDefault();
            
            const userId = $(e.currentTarget).data('user-id');
            const itemId = $(e.currentTarget).data('item-id');
            const quantity = $(e.currentTarget).closest('tr').find('.update-item-quantity').val();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_remove_user_item',
                    user_id: userId,
                    item_id: itemId,
                    quantity: quantity,
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Item updated successfully');
                        $('.membershiping-modal').remove();
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to update item');
                }.bind(this)
            });
        },
        
        // Remove user item
        removeUserItem: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to remove this item?')) {
                return;
            }
            
            const userId = $(e.currentTarget).data('user-id');
            const itemId = $(e.currentTarget).data('item-id');
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_remove_user_item',
                    user_id: userId,
                    item_id: itemId,
                    quantity: 0,
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Item removed successfully');
                        $('.membershiping-modal').remove();
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to remove item');
                }.bind(this)
            });
        },
        
        // Add currency to user
        addUserCurrency: function(e) {
            e.preventDefault();
            
            const userId = $(e.currentTarget).data('user-id');
            const currencyId = $('#add-currency-select').val();
            const amount = $('#add-currency-amount').val();
            
            if (!currencyId || !amount) {
                this.showError('Please select a currency and amount');
                return;
            }
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_update_user_currency',
                    user_id: userId,
                    currency_id: currencyId,
                    amount: amount,
                    action_type: 'add',
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Currency added successfully');
                        $('.membershiping-modal').remove();
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to add currency');
                }.bind(this)
            });
        },
        
        // Update user currency
        updateUserCurrency: function(e) {
            e.preventDefault();
            
            const userId = $(e.currentTarget).data('user-id');
            const currencyId = $(e.currentTarget).data('currency-id');
            const amount = $(e.currentTarget).closest('tr').find('.update-currency-balance').val();
            
            $.ajax({
                url: membershipingInventoryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'membershiping_inventory_update_user_currency',
                    user_id: userId,
                    currency_id: currencyId,
                    amount: amount,
                    action_type: 'set',
                    nonce: membershipingInventoryAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Currency updated successfully');
                        $('.membershiping-modal').remove();
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to update currency');
                }.bind(this)
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        membershipingInventoryAdmin.init();
    });
    
})(jQuery);
