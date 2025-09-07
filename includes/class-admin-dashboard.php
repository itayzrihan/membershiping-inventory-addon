<?php
/**
 * Admin Dashboard for Membershiping Inventory
 * Comprehensive management interface with analytics and bulk operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Admin_Dashboard {
    
    private $wpdb;
    private $database;
    private $security;
    private $items;
    private $currencies;
    private $nfts;
    private $trading;
    private $consumables;
    private $flag_awards;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
        $this->items = new Membershiping_Inventory_Items();
        $this->currencies = new Membershiping_Inventory_Currencies();
        $this->nfts = new Membershiping_Inventory_NFTs();
        $this->trading = new Membershiping_Inventory_Trading();
        $this->consumables = new Membershiping_Inventory_Consumables();
        $this->flag_awards = new Membershiping_Inventory_Flag_Awards();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_membershiping_get_user_inventory', array($this, 'ajax_get_user_inventory'));
        add_action('wp_ajax_membershiping_bulk_award_items', array($this, 'ajax_bulk_award_items'));
        add_action('wp_ajax_membershiping_bulk_remove_items', array($this, 'ajax_bulk_remove_items'));
        add_action('wp_ajax_membershiping_reset_user_inventory', array($this, 'ajax_reset_user_inventory'));
        add_action('wp_ajax_membershiping_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_membershiping_import_data', array($this, 'ajax_import_data'));
        add_action('wp_ajax_membershiping_system_diagnostics', array($this, 'ajax_system_diagnostics'));
        add_action('wp_ajax_membershiping_cleanup_system', array($this, 'ajax_cleanup_system'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Settings API
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu
        add_menu_page(
            __('Membershiping Inventory', 'membershiping-inventory'),
            __('Inventory', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory',
            array($this, 'render_dashboard_page'),
            'dashicons-archive',
            56
        );
        
        // Submenu pages
        add_submenu_page(
            'membershiping-inventory',
            __('Dashboard', 'membershiping-inventory'),
            __('Dashboard', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('Items Management', 'membershiping-inventory'),
            __('Items', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-items',
            array($this, 'render_items_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('Currencies', 'membershiping-inventory'),
            __('Currencies', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-currencies',
            array($this, 'render_currencies_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('NFTs Management', 'membershiping-inventory'),
            __('NFTs', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-nfts',
            array($this, 'render_nfts_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('Trading System', 'membershiping-inventory'),
            __('Trading', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-trading',
            array($this, 'render_trading_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('User Management', 'membershiping-inventory'),
            __('Users', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-users',
            array($this, 'render_users_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('Analytics', 'membershiping-inventory'),
            __('Analytics', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-analytics',
            array($this, 'render_analytics_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('Settings', 'membershiping-inventory'),
            __('Settings', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'membershiping-inventory',
            __('System Tools', 'membershiping-inventory'),
            __('Tools', 'membershiping-inventory'),
            $capability,
            'membershiping-inventory-tools',
            array($this, 'render_tools_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'membershiping-inventory') === false) {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker');
        
        wp_enqueue_script(
            'membershiping-inventory-admin',
            MEMBERSHIPING_INVENTORY_URL . 'assets/js/admin.js',
            array('jquery', 'chart-js'),
            MEMBERSHIPING_INVENTORY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'membershiping-inventory-admin',
            MEMBERSHIPING_INVENTORY_URL . 'assets/css/admin.css',
            array(),
            MEMBERSHIPING_INVENTORY_VERSION
        );
        
        wp_localize_script('membershiping-inventory-admin', 'membershipingInventoryAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('membershiping_inventory_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'membershiping-inventory'),
                'confirm_reset' => __('Are you sure you want to reset this user\'s inventory? This cannot be undone.', 'membershiping-inventory'),
                'confirm_cleanup' => __('Are you sure you want to run system cleanup? This will remove orphaned data.', 'membershiping-inventory'),
                'processing' => __('Processing...', 'membershiping-inventory'),
                'success' => __('Operation completed successfully.', 'membershiping-inventory'),
                'error' => __('An error occurred. Please try again.', 'membershiping-inventory')
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('Membershiping Inventory Dashboard', 'membershiping-inventory'); ?></h1>
            
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <div class="postbox-container">
                        
                        <!-- Overview Stats -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('System Overview', 'membershiping-inventory'); ?></h2>
                            <div class="inside">
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <h3><?php echo number_format($stats['total_items']); ?></h3>
                                        <p><?php _e('Total Items', 'membershiping-inventory'); ?></p>
                                    </div>
                                    <div class="stat-card">
                                        <h3><?php echo number_format($stats['total_nfts']); ?></h3>
                                        <p><?php _e('Minted NFTs', 'membershiping-inventory'); ?></p>
                                    </div>
                                    <div class="stat-card">
                                        <h3><?php echo number_format($stats['active_trades']); ?></h3>
                                        <p><?php _e('Active Trades', 'membershiping-inventory'); ?></p>
                                    </div>
                                    <div class="stat-card">
                                        <h3><?php echo number_format($stats['total_currencies']); ?></h3>
                                        <p><?php _e('Currencies', 'membershiping-inventory'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Recent Activity', 'membershiping-inventory'); ?></h2>
                            <div class="inside">
                                <div id="recent-activity-chart">
                                    <canvas id="activityChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Quick Actions', 'membershiping-inventory'); ?></h2>
                            <div class="inside">
                                <div class="quick-actions">
                                    <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-items'); ?>" class="button button-primary">
                                        <?php _e('Manage Items', 'membershiping-inventory'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-currencies'); ?>" class="button button-secondary">
                                        <?php _e('Manage Currencies', 'membershiping-inventory'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-trading'); ?>" class="button button-secondary">
                                        <?php _e('View Trades', 'membershiping-inventory'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-tools'); ?>" class="button button-secondary">
                                        <?php _e('System Tools', 'membershiping-inventory'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Health -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('System Health', 'membershiping-inventory'); ?></h2>
                            <div class="inside">
                                <div class="system-health">
                                    <?php $this->render_system_health_status(); ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load dashboard data
            membershipingInventoryAdmin.loadDashboardStats();
        });
        </script>
        <?php
    }
    
    /**
     * Render items management page
     */
    public function render_items_page() {
        $items = $this->items->get_all_items();
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('Items Management', 'membershiping-inventory'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'membershiping-inventory'); ?></option>
                        <option value="delete"><?php _e('Delete', 'membershiping-inventory'); ?></option>
                        <option value="duplicate"><?php _e('Duplicate', 'membershiping-inventory'); ?></option>
                        <option value="export"><?php _e('Export', 'membershiping-inventory'); ?></option>
                    </select>
                    <button class="button action" id="bulk-apply-top"><?php _e('Apply', 'membershiping-inventory'); ?></button>
                </div>
                <div class="alignright actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                        <?php _e('Add New Item', 'membershiping-inventory'); ?>
                    </a>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox" id="cb-select-all-1"></td>
                        <th><?php _e('ID', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Name', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Type', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Rarity', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Stackable', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Owned Count', 'membershiping-inventory'); ?></th>
                        <th><?php _e('Actions', 'membershiping-inventory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <th class="check-column"><input type="checkbox" name="item[]" value="<?php echo $item->id; ?>"></th>
                        <td><?php echo $item->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($item->name); ?></strong>
                            <?php if ($item->description): ?>
                            <div class="item-description"><?php echo esc_html(wp_trim_words($item->description, 10)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item->item_type); ?></td>
                        <td>
                            <span class="rarity-badge rarity-<?php echo $item->rarity; ?>">
                                <?php echo esc_html(ucfirst($item->rarity)); ?>
                            </span>
                        </td>
                        <td><?php echo $item->is_stackable ? __('Yes', 'membershiping-inventory') : __('No', 'membershiping-inventory'); ?></td>
                        <td><?php echo $this->get_item_total_owned($item->id); ?></td>
                        <td>
                            <a href="#" class="edit-item" data-item-id="<?php echo $item->id; ?>"><?php _e('Edit', 'membershiping-inventory'); ?></a> |
                            <a href="#" class="view-owners" data-item-id="<?php echo $item->id; ?>"><?php _e('Owners', 'membershiping-inventory'); ?></a> |
                            <a href="#" class="delete-item" data-item-id="<?php echo $item->id; ?>"><?php _e('Delete', 'membershiping-inventory'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render currencies page
     */
    public function render_currencies_page() {
        $currencies = $this->currencies->get_all_currencies();
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('Currencies Management', 'membershiping-inventory'); ?></h1>
            
            <div class="currency-management">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Add New Currency', 'membershiping-inventory'); ?></h2>
                    <div class="inside">
                        <form id="add-currency-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="currency_name"><?php _e('Name', 'membershiping-inventory'); ?></label></th>
                                    <td><input type="text" id="currency_name" name="currency_name" required></td>
                                </tr>
                                <tr>
                                    <th><label for="currency_symbol"><?php _e('Symbol', 'membershiping-inventory'); ?></label></th>
                                    <td><input type="text" id="currency_symbol" name="currency_symbol" maxlength="10" required></td>
                                </tr>
                                <tr>
                                    <th><label for="currency_description"><?php _e('Description', 'membershiping-inventory'); ?></label></th>
                                    <td><textarea id="currency_description" name="currency_description"></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="exchange_rate"><?php _e('Exchange Rate to Primary', 'membershiping-inventory'); ?></label></th>
                                    <td><input type="number" id="exchange_rate" name="exchange_rate" step="0.01" value="1.00"></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Add Currency', 'membershiping-inventory'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Existing Currencies', 'membershiping-inventory'); ?></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'membershiping-inventory'); ?></th>
                                    <th><?php _e('Name', 'membershiping-inventory'); ?></th>
                                    <th><?php _e('Symbol', 'membershiping-inventory'); ?></th>
                                    <th><?php _e('Exchange Rate', 'membershiping-inventory'); ?></th>
                                    <th><?php _e('Total Circulation', 'membershiping-inventory'); ?></th>
                                    <th><?php _e('Actions', 'membershiping-inventory'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $currency): ?>
                                <tr>
                                    <td><?php echo $currency->id; ?></td>
                                    <td><?php echo esc_html($currency->name); ?></td>
                                    <td><?php echo esc_html($currency->symbol); ?></td>
                                    <td><?php echo number_format($currency->exchange_rate, 4); ?></td>
                                    <td><?php echo number_format($this->get_currency_circulation($currency->id), 2); ?></td>
                                    <td>
                                        <a href="#" class="edit-currency" data-currency-id="<?php echo $currency->id; ?>"><?php _e('Edit', 'membershiping-inventory'); ?></a> |
                                        <a href="#" class="view-transactions" data-currency-id="<?php echo $currency->id; ?>"><?php _e('Transactions', 'membershiping-inventory'); ?></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user management page
     */
    public function render_users_page() {
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('User Inventory Management', 'membershiping-inventory'); ?></h1>
            
            <div class="user-search">
                <h2><?php _e('Search Users', 'membershiping-inventory'); ?></h2>
                <form id="user-search-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="user_search"><?php _e('Search by Username/Email', 'membershiping-inventory'); ?></label></th>
                            <td>
                                <input type="text" id="user_search" name="user_search" class="regular-text">
                                <button type="submit" class="button"><?php _e('Search', 'membershiping-inventory'); ?></button>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            
            <div id="user-inventory-results" style="display: none;">
                <h2><?php _e('User Inventory', 'membershiping-inventory'); ?></h2>
                <div id="user-inventory-content"></div>
            </div>
            
            <div class="bulk-operations">
                <h2><?php _e('Bulk Operations', 'membershiping-inventory'); ?></h2>
                <div class="postbox">
                    <div class="inside">
                        <form id="bulk-award-form">
                            <h3><?php _e('Award Items to Users', 'membershiping-inventory'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th><label for="bulk_users"><?php _e('Users', 'membershiping-inventory'); ?></label></th>
                                    <td>
                                        <select id="bulk_users" name="bulk_users[]" multiple class="regular-text">
                                            <?php
                                            $users = get_users();
                                            foreach ($users as $user) {
                                                echo '<option value="' . $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="bulk_item"><?php _e('Item', 'membershiping-inventory'); ?></label></th>
                                    <td>
                                        <select id="bulk_item" name="bulk_item" class="regular-text">
                                            <?php
                                            $items = $this->items->get_all_items();
                                            foreach ($items as $item) {
                                                echo '<option value="' . $item->id . '">' . esc_html($item->name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="bulk_quantity"><?php _e('Quantity', 'membershiping-inventory'); ?></label></th>
                                    <td><input type="number" id="bulk_quantity" name="bulk_quantity" value="1" min="1"></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Award Items', 'membershiping-inventory'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('Analytics & Reports', 'membershiping-inventory'); ?></h1>
            
            <div class="analytics-dashboard">
                <div class="analytics-filters">
                    <form id="analytics-filters">
                        <label for="date_range"><?php _e('Date Range:', 'membershiping-inventory'); ?></label>
                        <select id="date_range" name="date_range">
                            <option value="7"><?php _e('Last 7 Days', 'membershiping-inventory'); ?></option>
                            <option value="30" selected><?php _e('Last 30 Days', 'membershiping-inventory'); ?></option>
                            <option value="90"><?php _e('Last 90 Days', 'membershiping-inventory'); ?></option>
                            <option value="365"><?php _e('Last Year', 'membershiping-inventory'); ?></option>
                        </select>
                        <button type="submit" class="button"><?php _e('Update', 'membershiping-inventory'); ?></button>
                    </form>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h3><?php _e('Daily Activity', 'membershiping-inventory'); ?></h3>
                        <canvas id="dailyActivityChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Item Distribution', 'membershiping-inventory'); ?></h3>
                        <canvas id="itemDistributionChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Trading Volume', 'membershiping-inventory'); ?></h3>
                        <canvas id="tradingVolumeChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Currency Usage', 'membershiping-inventory'); ?></h3>
                        <canvas id="currencyUsageChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-tables">
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Top Items', 'membershiping-inventory'); ?></h2>
                        <div class="inside">
                            <div id="top-items-table"></div>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Most Active Users', 'membershiping-inventory'); ?></h2>
                        <div class="inside">
                            <div id="active-users-table"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('Inventory Settings', 'membershiping-inventory'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('membershiping_inventory_settings');
                do_settings_sections('membershiping_inventory_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Currency', 'membershiping-inventory'); ?></th>
                        <td>
                            <select name="membershiping_inventory_default_currency">
                                <?php
                                $currencies = $this->currencies->get_all_currencies();
                                $default_currency = get_option('membershiping_inventory_default_currency', '');
                                foreach ($currencies as $currency) {
                                    echo '<option value="' . $currency->id . '"' . selected($default_currency, $currency->id, false) . '>' . esc_html($currency->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Max Items Per User', 'membershiping-inventory'); ?></th>
                        <td>
                            <input type="number" name="membershiping_inventory_max_items" value="<?php echo get_option('membershiping_inventory_max_items', 1000); ?>" min="1">
                            <p class="description"><?php _e('Maximum number of items a user can own (0 = unlimited)', 'membershiping-inventory'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Trading Enabled', 'membershiping-inventory'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="membershiping_inventory_trading_enabled" value="1" <?php checked(get_option('membershiping_inventory_trading_enabled', 1)); ?>>
                                <?php _e('Enable trading between users', 'membershiping-inventory'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('NFT Auto-Mint', 'membershiping-inventory'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="membershiping_inventory_auto_mint_nft" value="1" <?php checked(get_option('membershiping_inventory_auto_mint_nft', 0)); ?>>
                                <?php _e('Automatically mint NFTs for non-stackable items', 'membershiping-inventory'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Security Level', 'membershiping-inventory'); ?></th>
                        <td>
                            <select name="membershiping_inventory_security_level">
                                <option value="low" <?php selected(get_option('membershiping_inventory_security_level', 'medium'), 'low'); ?>><?php _e('Low', 'membershiping-inventory'); ?></option>
                                <option value="medium" <?php selected(get_option('membershiping_inventory_security_level', 'medium'), 'medium'); ?>><?php _e('Medium', 'membershiping-inventory'); ?></option>
                                <option value="high" <?php selected(get_option('membershiping_inventory_security_level', 'medium'), 'high'); ?>><?php _e('High', 'membershiping-inventory'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render tools page
     */
    public function render_tools_page() {
        ?>
        <div class="wrap membershiping-inventory-admin">
            <h1><?php _e('System Tools', 'membershiping-inventory'); ?></h1>
            
            <div class="system-tools">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Database Tools', 'membershiping-inventory'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Tools for maintaining and optimizing the database.', 'membershiping-inventory'); ?></p>
                        <div class="tool-actions">
                            <button class="button" id="run-diagnostics"><?php _e('Run Diagnostics', 'membershiping-inventory'); ?></button>
                            <button class="button" id="cleanup-orphaned"><?php _e('Cleanup Orphaned Data', 'membershiping-inventory'); ?></button>
                            <button class="button" id="optimize-tables"><?php _e('Optimize Tables', 'membershiping-inventory'); ?></button>
                        </div>
                        <div id="diagnostic-results" style="display: none; margin-top: 20px;"></div>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Data Export/Import', 'membershiping-inventory'); ?></h2>
                    <div class="inside">
                        <h3><?php _e('Export Data', 'membershiping-inventory'); ?></h3>
                        <form id="export-form">
                            <label>
                                <input type="checkbox" name="export_items" checked> <?php _e('Items', 'membershiping-inventory'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_currencies" checked> <?php _e('Currencies', 'membershiping-inventory'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_user_items" checked> <?php _e('User Items', 'membershiping-inventory'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_nfts" checked> <?php _e('NFTs', 'membershiping-inventory'); ?>
                            </label><br>
                            <button type="submit" class="button button-primary"><?php _e('Export Data', 'membershiping-inventory'); ?></button>
                        </form>
                        
                        <h3><?php _e('Import Data', 'membershiping-inventory'); ?></h3>
                        <form id="import-form" enctype="multipart/form-data">
                            <input type="file" name="import_file" accept=".json,.csv" required>
                            <button type="submit" class="button button-primary"><?php _e('Import Data', 'membershiping-inventory'); ?></button>
                        </form>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Reset Tools', 'membershiping-inventory'); ?></h2>
                    <div class="inside">
                        <p class="description"><?php _e('These tools will permanently delete data. Use with caution.', 'membershiping-inventory'); ?></p>
                        <div class="reset-tools">
                            <button class="button button-secondary" id="reset-all-inventories"><?php _e('Reset All User Inventories', 'membershiping-inventory'); ?></button>
                            <button class="button button-secondary" id="reset-all-trades"><?php _e('Reset All Trades', 'membershiping-inventory'); ?></button>
                            <button class="button button-secondary" id="reset-all-currencies"><?php _e('Reset All User Currencies', 'membershiping-inventory'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        $stats = array();
        
        // Total items
        $stats['total_items'] = $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->database->get_table_name('items'));
        
        // Total NFTs
        $stats['total_nfts'] = $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->database->get_table_name('nfts'));
        
        // Active trades
        $stats['active_trades'] = $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->database->get_table_name('trades') . " WHERE status = 'pending'");
        
        // Total currencies
        $stats['total_currencies'] = $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->database->get_table_name('currencies'));
        
        // Recent activity (last 7 days)
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stats['recent_trades'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM " . $this->database->get_table_name('trades') . " WHERE created_at >= %s",
            $seven_days_ago
        ));
        
        $stats['recent_items_awarded'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM " . $this->database->get_table_name('user_items') . " WHERE created_at >= %s",
            $seven_days_ago
        ));
        
        return $stats;
    }
    
    /**
     * Render system health status
     */
    private function render_system_health_status() {
        $health_checks = array();
        
        // Check database tables
        $required_tables = array('items', 'currencies', 'user_items', 'user_currencies', 'nfts', 'trades', 'trade_items', 'transactions', 'flag_awards', 'audit_logs');
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_name = $this->database->get_table_name($table);
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            $health_checks[] = array(
                'status' => 'good',
                'message' => __('All database tables exist', 'membershiping-inventory')
            );
        } else {
            $health_checks[] = array(
                'status' => 'error',
                'message' => sprintf(__('Missing tables: %s', 'membershiping-inventory'), implode(', ', $missing_tables))
            );
        }
        
        // Check for orphaned data
        $orphaned_user_items = $this->wpdb->get_var("
            SELECT COUNT(*) FROM " . $this->database->get_table_name('user_items') . " ui 
            LEFT JOIN " . $this->database->get_table_name('items') . " i ON ui.item_id = i.id 
            WHERE i.id IS NULL
        ");
        
        if ($orphaned_user_items == 0) {
            $health_checks[] = array(
                'status' => 'good',
                'message' => __('No orphaned user items found', 'membershiping-inventory')
            );
        } else {
            $health_checks[] = array(
                'status' => 'warning',
                'message' => sprintf(__('%d orphaned user items found', 'membershiping-inventory'), $orphaned_user_items)
            );
        }
        
        // Check plugin dependencies
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $health_checks[] = array(
                'status' => 'good',
                'message' => __('WooCommerce is active', 'membershiping-inventory')
            );
        } else {
            $health_checks[] = array(
                'status' => 'error',
                'message' => __('WooCommerce is not active', 'membershiping-inventory')
            );
        }
        
        foreach ($health_checks as $check) {
            $icon = $check['status'] === 'good' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
            $class = 'health-' . $check['status'];
            echo '<div class="' . $class . '"><span class="health-icon">' . $icon . '</span> ' . $check['message'] . '</div>';
        }
    }
    
    /**
     * Get item total owned count
     */
    private function get_item_total_owned($item_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(quantity) FROM " . $this->database->get_table_name('user_items') . " WHERE item_id = %d",
            $item_id
        )) ?: 0;
    }
    
    /**
     * Get currency circulation
     */
    private function get_currency_circulation($currency_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(amount) FROM " . $this->database->get_table_name('user_currencies') . " WHERE currency_id = %d",
            $currency_id
        )) ?: 0;
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_default_currency');
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_max_items');
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_trading_enabled');
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_auto_mint_nft');
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_security_level');
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check for any critical issues
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            echo '<div class="notice notice-error"><p>';
            _e('Membershiping Inventory requires WooCommerce to be installed and active.', 'membershiping-inventory');
            echo '</p></div>';
        }
    }
    
    /**
     * AJAX Handlers
     */
    
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $stats = $this->get_dashboard_stats();
        
        // Get activity data for charts
        $activity_data = $this->get_activity_data();
        
        wp_send_json_success(array(
            'stats' => $stats,
            'activity' => $activity_data
        ));
    }
    
    public function ajax_get_user_inventory() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user_items = $this->items->get_user_items($user_id);
        $user_currencies = $this->currencies->get_user_currencies($user_id);
        $user_nfts = $this->nfts->get_user_nfts($user_id);
        
        wp_send_json_success(array(
            'items' => $user_items,
            'currencies' => $user_currencies,
            'nfts' => $user_nfts
        ));
    }
    
    public function ajax_bulk_award_items() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_ids = array_map('intval', $_POST['user_ids'] ?? array());
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (empty($user_ids) || !$item_id || $quantity < 1) {
            wp_send_json_error('Invalid parameters');
        }
        
        $results = array();
        foreach ($user_ids as $user_id) {
            $result = $this->items->add_user_item($user_id, $item_id, $quantity);
            $results[] = array(
                'user_id' => $user_id,
                'success' => !is_wp_error($result),
                'message' => is_wp_error($result) ? $result->get_error_message() : 'Success'
            );
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_system_diagnostics() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $diagnostics = $this->run_system_diagnostics();
        
        wp_send_json_success($diagnostics);
    }
    
    /**
     * Get activity data for charts
     */
    private function get_activity_data() {
        $days = 30;
        $activity = array();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $next_date = date('Y-m-d', strtotime("-$i days + 1 day"));
            
            $trades = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM " . $this->database->get_table_name('trades') . " 
                 WHERE created_at >= %s AND created_at < %s",
                $date, $next_date
            ));
            
            $items_awarded = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM " . $this->database->get_table_name('user_items') . " 
                 WHERE created_at >= %s AND created_at < %s",
                $date, $next_date
            ));
            
            $activity[] = array(
                'date' => $date,
                'trades' => intval($trades),
                'items_awarded' => intval($items_awarded)
            );
        }
        
        return $activity;
    }
    
    /**
     * Run system diagnostics
     */
    private function run_system_diagnostics() {
        $diagnostics = array();
        
        // Check database integrity
        $diagnostics['database'] = $this->check_database_integrity();
        
        // Check for data inconsistencies
        $diagnostics['data_consistency'] = $this->check_data_consistency();
        
        // Check system performance
        $diagnostics['performance'] = $this->check_system_performance();
        
        return $diagnostics;
    }
    
    /**
     * Check database integrity
     */
    private function check_database_integrity() {
        $checks = array();
        
        // Check table structures
        $required_tables = array('items', 'currencies', 'user_items', 'user_currencies', 'nfts', 'trades', 'trade_items', 'transactions', 'flag_awards', 'audit_logs');
        
        foreach ($required_tables as $table) {
            $table_name = $this->database->get_table_name($table);
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $checks[] = array(
                'check' => "Table: $table",
                'status' => $exists ? 'pass' : 'fail',
                'message' => $exists ? 'Table exists' : 'Table missing'
            );
        }
        
        return $checks;
    }
    
    /**
     * Check data consistency
     */
    private function check_data_consistency() {
        $checks = array();
        
        // Check for orphaned user items
        $orphaned_items = $this->wpdb->get_var("
            SELECT COUNT(*) FROM " . $this->database->get_table_name('user_items') . " ui 
            LEFT JOIN " . $this->database->get_table_name('items') . " i ON ui.item_id = i.id 
            WHERE i.id IS NULL
        ");
        
        $checks[] = array(
            'check' => 'Orphaned user items',
            'status' => $orphaned_items == 0 ? 'pass' : 'warning',
            'message' => $orphaned_items == 0 ? 'No orphaned items' : "$orphaned_items orphaned items found"
        );
        
        // Check for invalid NFT owners
        $invalid_nft_owners = $this->wpdb->get_var("
            SELECT COUNT(*) FROM " . $this->database->get_table_name('nfts') . " n 
            LEFT JOIN {$this->wpdb->users} u ON n.owner_id = u.ID 
            WHERE u.ID IS NULL
        ");
        
        $checks[] = array(
            'check' => 'Invalid NFT owners',
            'status' => $invalid_nft_owners == 0 ? 'pass' : 'warning',
            'message' => $invalid_nft_owners == 0 ? 'All NFTs have valid owners' : "$invalid_nft_owners NFTs with invalid owners"
        );
        
        return $checks;
    }
    
    /**
     * Check system performance
     */
    private function check_system_performance() {
        $checks = array();
        
        // Check table sizes
        $table_sizes = array();
        $required_tables = array('items', 'currencies', 'user_items', 'user_currencies', 'nfts', 'trades', 'transactions', 'audit_logs');
        
        foreach ($required_tables as $table) {
            $table_name = $this->database->get_table_name($table);
            $size = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $table_sizes[$table] = $size;
        }
        
        $checks[] = array(
            'check' => 'Table sizes',
            'status' => 'info',
            'message' => 'Items: ' . $table_sizes['items'] . ', Users Items: ' . $table_sizes['user_items'] . ', NFTs: ' . $table_sizes['nfts']
        );
        
        return $checks;
    }
}
