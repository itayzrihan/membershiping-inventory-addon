<?php
/**
 * Admin functionality for Membershiping Inventory
 * 
 * Handles all admin-related functionality including dashboard,
 * settings, user management, and administrative interfaces.
 * 
 * @package Membershiping_Inventory
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        error_log('Membershiping Inventory Admin: Constructor called');
        
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'init_admin_settings'));
        
        error_log('Membershiping Inventory Admin: admin_menu action added');
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_inventory_get_user_data', array($this, 'ajax_get_user_data'));
        add_action('wp_ajax_membershiping_inventory_update_user_inventory', array($this, 'ajax_update_user_inventory'));
        add_action('wp_ajax_membershiping_inventory_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_membershiping_inventory_manage_user_inventory', array($this, 'ajax_manage_user_inventory'));
        add_action('wp_ajax_membershiping_inventory_add_user_item', array($this, 'ajax_add_user_item'));
        add_action('wp_ajax_membershiping_inventory_remove_user_item', array($this, 'ajax_remove_user_item'));
        add_action('wp_ajax_membershiping_inventory_update_user_currency', array($this, 'ajax_update_user_currency'));
        
        // User profile integration
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Debug logging
        error_log('Membershiping Inventory: Adding admin menus...');
        error_log('Membershiping Inventory: Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        
        // Main menu
        $menu_page = add_menu_page(
            __('Membershiping Inventory', 'membershiping-inventory'),
            __('Inventory', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory',
            array($this, 'dashboard_page'),
            'dashicons-archive',
            30
        );
        
        if ($menu_page) {
            error_log('Membershiping Inventory: Main menu page added successfully');
        } else {
            error_log('Membershiping Inventory: Failed to add main menu page');
        }
        
        // Dashboard submenu
        add_submenu_page(
            'membershiping-inventory',
            __('Dashboard', 'membershiping-inventory'),
            __('Dashboard', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory',
            array($this, 'dashboard_page')
        );
        
        // Items management
        add_submenu_page(
            'membershiping-inventory',
            __('Items', 'membershiping-inventory'),
            __('Items', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory-items',
            array($this, 'items_page')
        );
        
        // Currencies management
        add_submenu_page(
            'membershiping-inventory',
            __('Currencies', 'membershiping-inventory'),
            __('Currencies', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory-currencies',
            array($this, 'currencies_page')
        );
        
        // User management
        add_submenu_page(
            'membershiping-inventory',
            __('User Inventory', 'membershiping-inventory'),
            __('User Inventory', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory-users',
            array($this, 'users_page')
        );
        
        // Reports
        add_submenu_page(
            'membershiping-inventory',
            __('Reports', 'membershiping-inventory'),
            __('Reports', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory-reports',
            array($this, 'reports_page')
        );
        
        // Settings
        add_submenu_page(
            'membershiping-inventory',
            __('Settings', 'membershiping-inventory'),
            __('Settings', 'membershiping-inventory'),
            'manage_options',
            'membershiping-inventory-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Load on inventory admin pages
        $load_on_inventory_pages = strpos($hook, 'membershiping-inventory') !== false;
        
        // Load on user profile pages
        $load_on_user_pages = in_array($hook, array('profile.php', 'user-edit.php'));
        
        if (!$load_on_inventory_pages && !$load_on_user_pages) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-dialog');
        
        wp_enqueue_script(
            'membershiping-inventory-admin',
            plugins_url('js/admin.js', dirname(__FILE__)),
            array('jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-dialog'),
            MEMBERSHIPING_INVENTORY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'membershiping-inventory-admin',
            plugins_url('css/admin.css', dirname(__FILE__)),
            array(),
            MEMBERSHIPING_INVENTORY_VERSION
        );
        
        wp_enqueue_style('jquery-ui-core');
        wp_enqueue_style('jquery-ui-theme');
        
        // Localize script
        wp_localize_script('membershiping-inventory-admin', 'membershiping_inventory_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('membershiping_inventory_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'membershiping-inventory'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected items?', 'membershiping-inventory'),
                'loading' => __('Loading...', 'membershiping-inventory'),
                'error' => __('An error occurred. Please try again.', 'membershiping-inventory'),
                'success' => __('Operation completed successfully.', 'membershiping-inventory'),
            )
        ));
    }
    
    /**
     * Initialize admin settings
     */
    public function init_admin_settings() {
        register_setting('membershiping_inventory_settings', 'membershiping_inventory_options');
        
        // General settings section
        add_settings_section(
            'membershiping_inventory_general',
            __('General Settings', 'membershiping-inventory'),
            array($this, 'general_settings_callback'),
            'membershiping_inventory_settings'
        );
        
        // Enable/disable features
        add_settings_field(
            'enable_woocommerce_integration',
            __('Enable WooCommerce Integration', 'membershiping-inventory'),
            array($this, 'checkbox_field_callback'),
            'membershiping_inventory_settings',
            'membershiping_inventory_general',
            array('field' => 'enable_woocommerce_integration')
        );
        
        add_settings_field(
            'enable_auto_awards',
            __('Enable Auto Awards', 'membershiping-inventory'),
            array($this, 'checkbox_field_callback'),
            'membershiping_inventory_settings',
            'membershiping_inventory_general',
            array('field' => 'enable_auto_awards')
        );
        
        add_settings_field(
            'enable_restrictions',
            __('Enable Content Restrictions', 'membershiping-inventory'),
            array($this, 'checkbox_field_callback'),
            'membershiping_inventory_settings',
            'membershiping_inventory_general',
            array('field' => 'enable_restrictions')
        );
        
        add_settings_field(
            'default_currency',
            __('Default Currency', 'membershiping-inventory'),
            array($this, 'currency_select_callback'),
            'membershiping_inventory_settings',
            'membershiping_inventory_general',
            array('field' => 'default_currency')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Membershiping Inventory Dashboard', 'membershiping-inventory'); ?></h1>
            
            <div class="membershiping-dashboard-stats">
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3><?php _e('Total Items', 'membershiping-inventory'); ?></h3>
                        <div class="stat-number"><?php echo $this->get_total_items(); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Total Currencies', 'membershiping-inventory'); ?></h3>
                        <div class="stat-number"><?php echo $this->get_total_currencies(); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Active Users', 'membershiping-inventory'); ?></h3>
                        <div class="stat-number"><?php echo $this->get_active_users(); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Total Transactions', 'membershiping-inventory'); ?></h3>
                        <div class="stat-number"><?php echo $this->get_total_transactions(); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="membershiping-dashboard-content">
                <div class="dashboard-widgets">
                    <div class="widget-box">
                        <h3><?php _e('Recent Activity', 'membershiping-inventory'); ?></h3>
                        <div class="widget-content">
                            <?php $this->display_recent_activity(); ?>
                        </div>
                    </div>
                    
                    <div class="widget-box">
                        <h3><?php _e('Quick Actions', 'membershiping-inventory'); ?></h3>
                        <div class="widget-content">
                            <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-items&action=add'); ?>" class="button button-primary">
                                <?php _e('Add New Item', 'membershiping-inventory'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-currencies&action=add'); ?>" class="button button-secondary">
                                <?php _e('Add New Currency', 'membershiping-inventory'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=membershiping-inventory-users'); ?>" class="button button-secondary">
                                <?php _e('Manage Users', 'membershiping-inventory'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Items management page
     */
    public function items_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $this->add_item_form();
                break;
            case 'edit':
                $this->edit_item_form();
                break;
            case 'delete':
                $this->delete_item();
                break;
            default:
                $this->list_items();
                break;
        }
    }
    
    /**
     * Currencies management page
     */
    public function currencies_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $this->add_currency_form();
                break;
            case 'edit':
                $this->edit_currency_form();
                break;
            case 'delete':
                $this->delete_currency();
                break;
            default:
                $this->list_currencies();
                break;
        }
    }
    
    /**
     * User inventory management page
     */
    public function users_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('User Inventory Management', 'membershiping-inventory'); ?></h1>
            
            <div class="user-search-form">
                <form method="get" action="">
                    <input type="hidden" name="page" value="membershiping-inventory-users">
                    <label for="user-search"><?php _e('Search Users:', 'membershiping-inventory'); ?></label>
                    <input type="text" id="user-search" name="user_search" value="<?php echo esc_attr($_GET['user_search'] ?? ''); ?>" placeholder="<?php _e('Username or email', 'membershiping-inventory'); ?>">
                    <input type="submit" class="button" value="<?php _e('Search', 'membershiping-inventory'); ?>">
                </form>
            </div>
            
            <div class="users-list">
                <?php $this->display_users_list(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Inventory Reports', 'membershiping-inventory'); ?></h1>
            
            <div class="reports-tabs">
                <div id="reports-tabs">
                    <ul>
                        <li><a href="#tab-overview"><?php _e('Overview', 'membershiping-inventory'); ?></a></li>
                        <li><a href="#tab-items"><?php _e('Items Report', 'membershiping-inventory'); ?></a></li>
                        <li><a href="#tab-currencies"><?php _e('Currencies Report', 'membershiping-inventory'); ?></a></li>
                        <li><a href="#tab-users"><?php _e('Users Report', 'membershiping-inventory'); ?></a></li>
                    </ul>
                    
                    <div id="tab-overview">
                        <?php $this->display_overview_report(); ?>
                    </div>
                    
                    <div id="tab-items">
                        <?php $this->display_items_report(); ?>
                    </div>
                    
                    <div id="tab-currencies">
                        <?php $this->display_currencies_report(); ?>
                    </div>
                    
                    <div id="tab-users">
                        <?php $this->display_users_report(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Membershiping Inventory Settings', 'membershiping-inventory'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('membershiping_inventory_settings');
                do_settings_sections('membershiping_inventory_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Add user profile fields
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <h3><?php _e('Membershiping Inventory', 'membershiping-inventory'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th><label><?php _e('User Inventory', 'membershiping-inventory'); ?></label></th>
                <td>
                    <div id="user-inventory-section">
                        <?php $this->display_user_inventory($user->ID); ?>
                    </div>
                    <button type="button" class="button" id="manage-user-inventory" data-user-id="<?php echo $user->ID; ?>">
                        <?php _e('Manage Inventory', 'membershiping-inventory'); ?>
                    </button>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle any user inventory updates if submitted
        if (isset($_POST['membershiping_inventory_action'])) {
            $this->process_user_inventory_update($user_id);
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if Membershiping core is active
        if (!class_exists('Membershiping')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('Membershiping Inventory requires the Membershiping core plugin to be active.', 'membershiping-inventory'); ?></p>
            </div>
            <?php
        }
        
        // Check if WooCommerce is active (if integration is enabled)
        $options = get_option('membershiping_inventory_options', array());
        if (!empty($options['enable_woocommerce_integration']) && !class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('WooCommerce integration is enabled but WooCommerce is not active.', 'membershiping-inventory'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX: Get user data
     */
    public function ajax_get_user_data() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user_data = array(
            'items' => $this->get_user_items($user_id),
            'currencies' => $this->get_user_currencies($user_id),
        );
        
        wp_send_json_success($user_data);
    }
    
    /**
     * AJAX: Update user inventory
     */
    public function ajax_update_user_inventory() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['inventory_action'] ?? '');
        $item_type = sanitize_text_field($_POST['item_type'] ?? '');
        $item_id = intval($_POST['item_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        
        if (!$user_id || !$action || !$item_type || !$item_id) {
            wp_send_json_error('Missing required parameters');
        }
        
        $result = false;
        
        switch ($action) {
            case 'add':
                if ($item_type === 'item') {
                    $result = $this->add_user_item($user_id, $item_id, $amount);
                } elseif ($item_type === 'currency') {
                    $result = $this->add_user_currency($user_id, $item_id, $amount);
                }
                break;
                
            case 'remove':
                if ($item_type === 'item') {
                    $result = $this->remove_user_item($user_id, $item_id, $amount);
                } elseif ($item_type === 'currency') {
                    $result = $this->remove_user_currency($user_id, $item_id, $amount);
                }
                break;
                
            case 'set':
                if ($item_type === 'currency') {
                    $result = $this->set_user_currency($user_id, $item_id, $amount);
                }
                break;
        }
        
        if ($result) {
            wp_send_json_success('Inventory updated successfully');
        } else {
            wp_send_json_error('Failed to update inventory');
        }
    }
    
    /**
     * AJAX: Bulk action
     */
    public function ajax_bulk_action() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $items = array_map('intval', $_POST['items'] ?? array());
        
        if (!$action || empty($items)) {
            wp_send_json_error('Missing required parameters');
        }
        
        $result = $this->process_bulk_action($action, $items);
        
        if ($result) {
            wp_send_json_success('Bulk action completed successfully');
        } else {
            wp_send_json_error('Failed to complete bulk action');
        }
    }
    
    // Helper methods for statistics and data retrieval
    private function get_total_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_items';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    private function get_total_currencies() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    private function get_active_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        return $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
    }
    
    private function get_total_transactions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currency_transactions';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        return (int) $count;
    }
    
    private function display_recent_activity() {
        global $wpdb;
        
        echo '<div class="recent-activity-list">';
        
        // Get recent transactions
        $transactions_table = $wpdb->prefix . 'membershiping_inventory_currency_transactions';
        $recent_transactions = $wpdb->get_results(
            "SELECT * FROM $transactions_table 
             ORDER BY created_at DESC 
             LIMIT 10"
        );
        
        if ($recent_transactions) {
            echo '<h4>' . __('Recent Transactions', 'membershiping-inventory') . '</h4>';
            echo '<ul class="activity-list">';
            foreach ($recent_transactions as $transaction) {
                $user = get_user_by('id', $transaction->user_id);
                $username = $user ? $user->display_name : __('Unknown User', 'membershiping-inventory');
                echo '<li>';
                echo '<strong>' . esc_html($username) . '</strong> ';
                echo sprintf(__('%s %s %s', 'membershiping-inventory'), 
                    $transaction->type, 
                    $transaction->amount, 
                    $transaction->currency_code
                );
                echo ' <span class="activity-time">' . human_time_diff(strtotime($transaction->created_at)) . ' ' . __('ago', 'membershiping-inventory') . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No recent activity found.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function list_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_items';
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Items Management', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items&action=add') . '" class="page-title-action">' . __('Add New Item', 'membershiping-inventory') . '</a></h1>';
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['item_ids'])) {
            $item_ids = array_map('intval', $_POST['item_ids']);
            if (!empty($item_ids)) {
                $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
                $wpdb->query($wpdb->prepare("UPDATE $table_name SET status = 'deleted' WHERE id IN ($placeholders)", $item_ids));
                echo '<div class="notice notice-success"><p>' . __('Selected items have been deleted.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        // Get items
        $items = $wpdb->get_results("SELECT * FROM $table_name WHERE status != 'deleted' ORDER BY created_at DESC");
        
        echo '<form method="post" action="">';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="action" id="bulk-action-selector-top">';
        echo '<option value="-1">' . __('Bulk Actions', 'membershiping-inventory') . '</option>';
        echo '<option value="bulk_delete">' . __('Delete', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '<input type="submit" class="button action" value="' . __('Apply', 'membershiping-inventory') . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>';
        echo '<th>' . __('Name', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Type', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Value', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Status', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Created', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Actions', 'membershiping-inventory') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if ($items) {
            foreach ($items as $item) {
                echo '<tr>';
                echo '<th scope="row" class="check-column"><input type="checkbox" name="item_ids[]" value="' . $item->id . '"></th>';
                echo '<td><strong>' . esc_html($item->name) . '</strong></td>';
                echo '<td>' . esc_html($item->type) . '</td>';
                echo '<td>' . esc_html($item->value) . '</td>';
                echo '<td><span class="status-' . esc_attr($item->status) . '">' . esc_html(ucfirst($item->status)) . '</span></td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($item->created_at)) . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-items&action=edit&id=' . $item->id) . '" class="button button-small">' . __('Edit', 'membershiping-inventory') . '</a> ';
                echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-items&action=delete&id=' . $item->id) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Are you sure?', 'membershiping-inventory') . '\')">' . __('Delete', 'membershiping-inventory') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">' . __('No items found. ', 'membershiping-inventory') . '<a href="' . admin_url('admin.php?page=membershiping-inventory-items&action=add') . '">' . __('Add your first item', 'membershiping-inventory') . '</a></td></tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</form>';
        echo '</div>';
    }
    
    private function add_item_form() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_items';
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Add New Item', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="page-title-action">' . __('Back to Items', 'membershiping-inventory') . '</a></h1>';
        
        // Handle form submission
        if (isset($_POST['submit_item']) && wp_verify_nonce($_POST['item_nonce'], 'add_item_nonce')) {
            $name = sanitize_text_field($_POST['item_name']);
            $description = sanitize_textarea_field($_POST['item_description']);
            $type = sanitize_text_field($_POST['item_type']);
            $value = floatval($_POST['item_value']);
            $status = sanitize_text_field($_POST['item_status']);
            $metadata = sanitize_textarea_field($_POST['item_metadata']);
            
            if ($name) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'description' => $description,
                        'type' => $type,
                        'value' => $value,
                        'status' => $status,
                        'metadata' => $metadata,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Item added successfully!', 'membershiping-inventory') . '</p></div>';
                    echo '<script>setTimeout(function() { window.location.href = "' . admin_url('admin.php?page=membershiping-inventory-items') . '"; }, 2000);</script>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error adding item. Please try again.', 'membershiping-inventory') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Item name is required.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        echo '<form method="post" action="">';
        wp_nonce_field('add_item_nonce', 'item_nonce');
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_name">' . __('Item Name', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="item_name" name="item_name" class="regular-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_description">' . __('Description', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="item_description" name="item_description" class="large-text" rows="3"></textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_type">' . __('Item Type', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="item_type" name="item_type">';
        echo '<option value="virtual">' . __('Virtual Item', 'membershiping-inventory') . '</option>';
        echo '<option value="consumable">' . __('Consumable', 'membershiping-inventory') . '</option>';
        echo '<option value="equipment">' . __('Equipment', 'membershiping-inventory') . '</option>';
        echo '<option value="collectible">' . __('Collectible', 'membershiping-inventory') . '</option>';
        echo '<option value="nft">' . __('NFT', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_value">' . __('Value', 'membershiping-inventory') . '</label></th>';
        echo '<td><input type="number" id="item_value" name="item_value" step="0.01" min="0" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_status">' . __('Status', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="item_status" name="item_status">';
        echo '<option value="active">' . __('Active', 'membershiping-inventory') . '</option>';
        echo '<option value="inactive">' . __('Inactive', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_metadata">' . __('Metadata (JSON)', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="item_metadata" name="item_metadata" class="large-text" rows="3" placeholder="' . __('Optional JSON metadata', 'membershiping-inventory') . '"></textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit_item" class="button button-primary" value="' . __('Add Item', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    private function edit_item_form() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_items';
        
        $item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$item_id) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-items'));
            exit;
        }
        
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id));
        
        if (!$item) {
            echo '<div class="wrap">';
            echo '<h1>' . __('Edit Item', 'membershiping-inventory') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Item not found.', 'membershiping-inventory') . '</p></div>';
            echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="button">' . __('Back to Items', 'membershiping-inventory') . '</a>';
            echo '</div>';
            return;
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Edit Item', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="page-title-action">' . __('Back to Items', 'membershiping-inventory') . '</a></h1>';
        
        // Handle form submission
        if (isset($_POST['submit_item']) && wp_verify_nonce($_POST['item_nonce'], 'edit_item_nonce')) {
            $name = sanitize_text_field($_POST['item_name']);
            $description = sanitize_textarea_field($_POST['item_description']);
            $type = sanitize_text_field($_POST['item_type']);
            $value = floatval($_POST['item_value']);
            $status = sanitize_text_field($_POST['item_status']);
            $metadata = sanitize_textarea_field($_POST['item_metadata']);
            
            if ($name) {
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'description' => $description,
                        'type' => $type,
                        'value' => $value,
                        'status' => $status,
                        'metadata' => $metadata,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $item_id),
                    array('%s', '%s', '%s', '%f', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Item updated successfully!', 'membershiping-inventory') . '</p></div>';
                    // Refresh item data
                    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id));
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error updating item. Please try again.', 'membershiping-inventory') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Item name is required.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        echo '<form method="post" action="">';
        wp_nonce_field('edit_item_nonce', 'item_nonce');
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_name">' . __('Item Name', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="item_name" name="item_name" class="regular-text" value="' . esc_attr($item->name) . '" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_description">' . __('Description', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="item_description" name="item_description" class="large-text" rows="3">' . esc_textarea($item->description) . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_type">' . __('Item Type', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="item_type" name="item_type">';
        $types = array('virtual' => __('Virtual Item', 'membershiping-inventory'), 'consumable' => __('Consumable', 'membershiping-inventory'), 'equipment' => __('Equipment', 'membershiping-inventory'), 'collectible' => __('Collectible', 'membershiping-inventory'), 'nft' => __('NFT', 'membershiping-inventory'));
        foreach ($types as $value => $label) {
            echo '<option value="' . $value . '"' . selected($item->type, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_value">' . __('Value', 'membershiping-inventory') . '</label></th>';
        echo '<td><input type="number" id="item_value" name="item_value" step="0.01" min="0" class="regular-text" value="' . esc_attr($item->value) . '"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_status">' . __('Status', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="item_status" name="item_status">';
        echo '<option value="active"' . selected($item->status, 'active', false) . '>' . __('Active', 'membershiping-inventory') . '</option>';
        echo '<option value="inactive"' . selected($item->status, 'inactive', false) . '>' . __('Inactive', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="item_metadata">' . __('Metadata (JSON)', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="item_metadata" name="item_metadata" class="large-text" rows="3" placeholder="' . __('Optional JSON metadata', 'membershiping-inventory') . '">' . esc_textarea($item->metadata) . '</textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit_item" class="button button-primary" value="' . __('Update Item', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    private function delete_item() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_items';
        
        $item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$item_id) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-items'));
            exit;
        }
        
        // Get item for confirmation
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id));
        
        if (!$item) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-items'));
            exit;
        }
        
        // Handle confirmation
        if (isset($_POST['confirm_delete']) && wp_verify_nonce($_POST['delete_nonce'], 'delete_item_nonce')) {
            $result = $wpdb->update(
                $table_name,
                array('status' => 'deleted', 'updated_at' => current_time('mysql')),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=membershiping-inventory-items&message=deleted'));
                exit;
            } else {
                $error_message = __('Error deleting item. Please try again.', 'membershiping-inventory');
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Delete Item', 'membershiping-inventory') . '</h1>';
        
        if (isset($error_message)) {
            echo '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
        }
        
        echo '<div class="notice notice-warning">';
        echo '<p>' . sprintf(__('Are you sure you want to delete the item "%s"? This action cannot be undone.', 'membershiping-inventory'), esc_html($item->name)) . '</p>';
        echo '</div>';
        
        echo '<form method="post" action="">';
        wp_nonce_field('delete_item_nonce', 'delete_nonce');
        echo '<p class="submit">';
        echo '<input type="submit" name="confirm_delete" class="button button-primary" value="' . __('Yes, Delete Item', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-items') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        echo '</form>';
        
        echo '</div>';
    }
    
    private function list_currencies() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Currencies Management', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies&action=add') . '" class="page-title-action">' . __('Add New Currency', 'membershiping-inventory') . '</a></h1>';
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['currency_ids'])) {
            $currency_ids = array_map('intval', $_POST['currency_ids']);
            if (!empty($currency_ids)) {
                $placeholders = implode(',', array_fill(0, count($currency_ids), '%d'));
                $wpdb->query($wpdb->prepare("UPDATE $table_name SET status = 'deleted' WHERE id IN ($placeholders)", $currency_ids));
                echo '<div class="notice notice-success"><p>' . __('Selected currencies have been deleted.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        // Get currencies
        $currencies = $wpdb->get_results("SELECT * FROM $table_name WHERE status != 'deleted' ORDER BY created_at DESC");
        
        echo '<form method="post" action="">';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="action" id="bulk-action-selector-top">';
        echo '<option value="-1">' . __('Bulk Actions', 'membershiping-inventory') . '</option>';
        echo '<option value="bulk_delete">' . __('Delete', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '<input type="submit" class="button action" value="' . __('Apply', 'membershiping-inventory') . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>';
        echo '<th>' . __('Name', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Code', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Symbol', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Type', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Status', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Created', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Actions', 'membershiping-inventory') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if ($currencies) {
            foreach ($currencies as $currency) {
                echo '<tr>';
                echo '<th scope="row" class="check-column"><input type="checkbox" name="currency_ids[]" value="' . $currency->id . '"></th>';
                echo '<td><strong>' . esc_html($currency->name) . '</strong></td>';
                echo '<td>' . esc_html($currency->code) . '</td>';
                echo '<td>' . esc_html($currency->symbol) . '</td>';
                echo '<td>' . esc_html(ucfirst($currency->type)) . '</td>';
                echo '<td><span class="status-' . esc_attr($currency->status) . '">' . esc_html(ucfirst($currency->status)) . '</span></td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($currency->created_at)) . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-currencies&action=edit&id=' . $currency->id) . '" class="button button-small">' . __('Edit', 'membershiping-inventory') . '</a> ';
                echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-currencies&action=delete&id=' . $currency->id) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Are you sure?', 'membershiping-inventory') . '\')">' . __('Delete', 'membershiping-inventory') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">' . __('No currencies found. ', 'membershiping-inventory') . '<a href="' . admin_url('admin.php?page=membershiping-inventory-currencies&action=add') . '">' . __('Add your first currency', 'membershiping-inventory') . '</a></td></tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</form>';
        echo '</div>';
    }
    
    private function add_currency_form() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Add New Currency', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="page-title-action">' . __('Back to Currencies', 'membershiping-inventory') . '</a></h1>';
        
        // Handle form submission
        if (isset($_POST['submit_currency']) && wp_verify_nonce($_POST['currency_nonce'], 'add_currency_nonce')) {
            $name = sanitize_text_field($_POST['currency_name']);
            $code = sanitize_text_field($_POST['currency_code']);
            $symbol = sanitize_text_field($_POST['currency_symbol']);
            $type = sanitize_text_field($_POST['currency_type']);
            $status = sanitize_text_field($_POST['currency_status']);
            $description = sanitize_textarea_field($_POST['currency_description']);
            $metadata = sanitize_textarea_field($_POST['currency_metadata']);
            
            if ($name && $code) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'code' => $code,
                        'symbol' => $symbol,
                        'type' => $type,
                        'status' => $status,
                        'description' => $description,
                        'metadata' => $metadata,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Currency added successfully!', 'membershiping-inventory') . '</p></div>';
                    echo '<script>setTimeout(function() { window.location.href = "' . admin_url('admin.php?page=membershiping-inventory-currencies') . '"; }, 2000);</script>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error adding currency. Please try again.', 'membershiping-inventory') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Currency name and code are required.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        echo '<form method="post" action="">';
        wp_nonce_field('add_currency_nonce', 'currency_nonce');
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_name">' . __('Currency Name', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="currency_name" name="currency_name" class="regular-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_code">' . __('Currency Code', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="currency_code" name="currency_code" class="regular-text" maxlength="10" required><p class="description">' . __('Unique identifier for this currency (e.g., GOLD, GEMS, USD)', 'membershiping-inventory') . '</p></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_symbol">' . __('Symbol', 'membershiping-inventory') . '</label></th>';
        echo '<td><input type="text" id="currency_symbol" name="currency_symbol" class="regular-text" maxlength="5"><p class="description">' . __('Display symbol (e.g., $, €, ⭐)', 'membershiping-inventory') . '</p></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_type">' . __('Currency Type', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="currency_type" name="currency_type">';
        echo '<option value="virtual">' . __('Virtual Currency', 'membershiping-inventory') . '</option>';
        echo '<option value="premium">' . __('Premium Currency', 'membershiping-inventory') . '</option>';
        echo '<option value="reward">' . __('Reward Currency', 'membershiping-inventory') . '</option>';
        echo '<option value="fiat">' . __('Fiat Currency', 'membershiping-inventory') . '</option>';
        echo '<option value="crypto">' . __('Cryptocurrency', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_description">' . __('Description', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="currency_description" name="currency_description" class="large-text" rows="3"></textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_status">' . __('Status', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="currency_status" name="currency_status">';
        echo '<option value="active">' . __('Active', 'membershiping-inventory') . '</option>';
        echo '<option value="inactive">' . __('Inactive', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_metadata">' . __('Metadata (JSON)', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="currency_metadata" name="currency_metadata" class="large-text" rows="3" placeholder="' . __('Optional JSON metadata', 'membershiping-inventory') . '"></textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit_currency" class="button button-primary" value="' . __('Add Currency', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    private function edit_currency_form() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        $currency_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$currency_id) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-currencies'));
            exit;
        }
        
        $currency = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $currency_id));
        
        if (!$currency) {
            echo '<div class="wrap">';
            echo '<h1>' . __('Edit Currency', 'membershiping-inventory') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Currency not found.', 'membershiping-inventory') . '</p></div>';
            echo '<a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="button">' . __('Back to Currencies', 'membershiping-inventory') . '</a>';
            echo '</div>';
            return;
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Edit Currency', 'membershiping-inventory') . ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="page-title-action">' . __('Back to Currencies', 'membershiping-inventory') . '</a></h1>';
        
        // Handle form submission
        if (isset($_POST['submit_currency']) && wp_verify_nonce($_POST['currency_nonce'], 'edit_currency_nonce')) {
            $name = sanitize_text_field($_POST['currency_name']);
            $code = sanitize_text_field($_POST['currency_code']);
            $symbol = sanitize_text_field($_POST['currency_symbol']);
            $type = sanitize_text_field($_POST['currency_type']);
            $status = sanitize_text_field($_POST['currency_status']);
            $description = sanitize_textarea_field($_POST['currency_description']);
            $metadata = sanitize_textarea_field($_POST['currency_metadata']);
            
            if ($name && $code) {
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'code' => $code,
                        'symbol' => $symbol,
                        'type' => $type,
                        'status' => $status,
                        'description' => $description,
                        'metadata' => $metadata,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $currency_id),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Currency updated successfully!', 'membershiping-inventory') . '</p></div>';
                    // Refresh currency data
                    $currency = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $currency_id));
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error updating currency. Please try again.', 'membershiping-inventory') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Currency name and code are required.', 'membershiping-inventory') . '</p></div>';
            }
        }
        
        echo '<form method="post" action="">';
        wp_nonce_field('edit_currency_nonce', 'currency_nonce');
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_name">' . __('Currency Name', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="currency_name" name="currency_name" class="regular-text" value="' . esc_attr($currency->name) . '" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_code">' . __('Currency Code', 'membershiping-inventory') . ' *</label></th>';
        echo '<td><input type="text" id="currency_code" name="currency_code" class="regular-text" maxlength="10" value="' . esc_attr($currency->code) . '" required><p class="description">' . __('Unique identifier for this currency (e.g., GOLD, GEMS, USD)', 'membershiping-inventory') . '</p></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_symbol">' . __('Symbol', 'membershiping-inventory') . '</label></th>';
        echo '<td><input type="text" id="currency_symbol" name="currency_symbol" class="regular-text" maxlength="5" value="' . esc_attr($currency->symbol) . '"><p class="description">' . __('Display symbol (e.g., $, €, ⭐)', 'membershiping-inventory') . '</p></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_type">' . __('Currency Type', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="currency_type" name="currency_type">';
        $types = array('virtual' => __('Virtual Currency', 'membershiping-inventory'), 'premium' => __('Premium Currency', 'membershiping-inventory'), 'reward' => __('Reward Currency', 'membershiping-inventory'), 'fiat' => __('Fiat Currency', 'membershiping-inventory'), 'crypto' => __('Cryptocurrency', 'membershiping-inventory'));
        foreach ($types as $value => $label) {
            echo '<option value="' . $value . '"' . selected($currency->type, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_description">' . __('Description', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="currency_description" name="currency_description" class="large-text" rows="3">' . esc_textarea($currency->description) . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_status">' . __('Status', 'membershiping-inventory') . '</label></th>';
        echo '<td>';
        echo '<select id="currency_status" name="currency_status">';
        echo '<option value="active"' . selected($currency->status, 'active', false) . '>' . __('Active', 'membershiping-inventory') . '</option>';
        echo '<option value="inactive"' . selected($currency->status, 'inactive', false) . '>' . __('Inactive', 'membershiping-inventory') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="currency_metadata">' . __('Metadata (JSON)', 'membershiping-inventory') . '</label></th>';
        echo '<td><textarea id="currency_metadata" name="currency_metadata" class="large-text" rows="3" placeholder="' . __('Optional JSON metadata', 'membershiping-inventory') . '">' . esc_textarea($currency->metadata) . '</textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit_currency" class="button button-primary" value="' . __('Update Currency', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    private function delete_currency() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        $currency_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$currency_id) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-currencies'));
            exit;
        }
        
        // Get currency for confirmation
        $currency = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $currency_id));
        
        if (!$currency) {
            wp_redirect(admin_url('admin.php?page=membershiping-inventory-currencies'));
            exit;
        }
        
        // Handle confirmation
        if (isset($_POST['confirm_delete']) && wp_verify_nonce($_POST['delete_nonce'], 'delete_currency_nonce')) {
            $result = $wpdb->update(
                $table_name,
                array('status' => 'deleted', 'updated_at' => current_time('mysql')),
                array('id' => $currency_id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=membershiping-inventory-currencies&message=deleted'));
                exit;
            } else {
                $error_message = __('Error deleting currency. Please try again.', 'membershiping-inventory');
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Delete Currency', 'membershiping-inventory') . '</h1>';
        
        if (isset($error_message)) {
            echo '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
        }
        
        echo '<div class="notice notice-warning">';
        echo '<p>' . sprintf(__('Are you sure you want to delete the currency "%s" (%s)? This action cannot be undone.', 'membershiping-inventory'), esc_html($currency->name), esc_html($currency->code)) . '</p>';
        echo '</div>';
        
        echo '<form method="post" action="">';
        wp_nonce_field('delete_currency_nonce', 'delete_nonce');
        echo '<p class="submit">';
        echo '<input type="submit" name="confirm_delete" class="button button-primary" value="' . __('Yes, Delete Currency', 'membershiping-inventory') . '">';
        echo ' <a href="' . admin_url('admin.php?page=membershiping-inventory-currencies') . '" class="button">' . __('Cancel', 'membershiping-inventory') . '</a>';
        echo '</p>';
        echo '</form>';
        
        echo '</div>';
    }
    
    private function display_users_list() {
        global $wpdb;
        
        $search = isset($_GET['user_search']) ? sanitize_text_field($_GET['user_search']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Build user query
        $user_args = array(
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        if ($search) {
            $user_args['search'] = '*' . $search . '*';
        }
        
        $users = get_users($user_args);
        $total_users = count(get_users(array('search' => $search ? '*' . $search . '*' : '')));
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('User', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Email', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Items', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Currencies', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Registered', 'membershiping-inventory') . '</th>';
        echo '<th>' . __('Actions', 'membershiping-inventory') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if ($users) {
            foreach ($users as $user) {
                $user_items_count = $this->get_user_items_count($user->ID);
                $user_currencies_count = $this->get_user_currencies_count($user->ID);
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($user->display_name) . '</strong><br><small>' . esc_html($user->user_login) . '</small></td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . $user_items_count . ' ' . __('items', 'membershiping-inventory') . '</td>';
                echo '<td>' . $user_currencies_count . ' ' . __('currencies', 'membershiping-inventory') . '</td>';
                echo '<td>' . date('Y-m-d', strtotime($user->user_registered)) . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('user-edit.php?user_id=' . $user->ID . '#membershiping-inventory') . '" class="button button-small">' . __('Manage Inventory', 'membershiping-inventory') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">' . __('No users found.', 'membershiping-inventory') . '</td></tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Pagination
        if ($total_users > $per_page) {
            $total_pages = ceil($total_users / $per_page);
            echo '<div class="tablenav">';
            echo '<div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $paged
            ));
            echo '</div>';
            echo '</div>';
        }
    }
    
    private function display_overview_report() {
        global $wpdb;
        
        echo '<div class="overview-report">';
        
        // Key Statistics
        echo '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">';
        
        echo '<div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">';
        echo '<h4>' . __('Total Items', 'membershiping-inventory') . '</h4>';
        echo '<div class="stat-number" style="font-size: 32px; font-weight: bold; color: #1d2327;">' . $this->get_total_items() . '</div>';
        echo '</div>';
        
        echo '<div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">';
        echo '<h4>' . __('Total Currencies', 'membershiping-inventory') . '</h4>';
        echo '<div class="stat-number" style="font-size: 32px; font-weight: bold; color: #1d2327;">' . $this->get_total_currencies() . '</div>';
        echo '</div>';
        
        echo '<div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">';
        echo '<h4>' . __('Active Users', 'membershiping-inventory') . '</h4>';
        echo '<div class="stat-number" style="font-size: 32px; font-weight: bold; color: #1d2327;">' . $this->get_active_users() . '</div>';
        echo '</div>';
        
        echo '<div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">';
        echo '<h4>' . __('Total Transactions', 'membershiping-inventory') . '</h4>';
        echo '<div class="stat-number" style="font-size: 32px; font-weight: bold; color: #1d2327;">' . $this->get_total_transactions() . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Recent Activity Summary
        echo '<div class="recent-activity-summary">';
        echo '<h4>' . __('Recent Activity Summary', 'membershiping-inventory') . '</h4>';
        
        $transactions_table = $wpdb->prefix . 'membershiping_inventory_currency_transactions';
        $recent_activity = $wpdb->get_results("
            SELECT DATE(created_at) as activity_date, COUNT(*) as transaction_count 
            FROM $transactions_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY activity_date DESC
        ");
        
        if ($recent_activity) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Date', 'membershiping-inventory') . '</th><th>' . __('Transactions', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($recent_activity as $activity) {
                echo '<tr>';
                echo '<td>' . date('Y-m-d', strtotime($activity->activity_date)) . '</td>';
                echo '<td>' . $activity->transaction_count . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No recent activity data available.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private function display_items_report() {
        global $wpdb;
        
        $items_table = $wpdb->prefix . 'membershiping_inventory_items';
        $user_items_table = $wpdb->prefix . 'membershiping_inventory_user_items';
        
        echo '<div class="items-report">';
        
        // Items by Type
        echo '<h4>' . __('Items by Type', 'membershiping-inventory') . '</h4>';
        $items_by_type = $wpdb->get_results("
            SELECT type, COUNT(*) as count 
            FROM $items_table 
            WHERE status != 'deleted' 
            GROUP BY type 
            ORDER BY count DESC
        ");
        
        if ($items_by_type) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Item Type', 'membershiping-inventory') . '</th><th>' . __('Count', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($items_by_type as $type_data) {
                echo '<tr>';
                echo '<td>' . esc_html(ucfirst($type_data->type)) . '</td>';
                echo '<td>' . $type_data->count . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No items data available.', 'membershiping-inventory') . '</p>';
        }
        
        // Most Popular Items
        echo '<h4>' . __('Most Popular Items', 'membershiping-inventory') . '</h4>';
        $popular_items = $wpdb->get_results("
            SELECT i.name, i.type, SUM(ui.quantity) as total_quantity 
            FROM $items_table i 
            LEFT JOIN $user_items_table ui ON i.id = ui.item_id 
            WHERE i.status != 'deleted' 
            GROUP BY i.id 
            ORDER BY total_quantity DESC 
            LIMIT 10
        ");
        
        if ($popular_items) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Item Name', 'membershiping-inventory') . '</th><th>' . __('Type', 'membershiping-inventory') . '</th><th>' . __('Total Owned', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($popular_items as $item) {
                echo '<tr>';
                echo '<td>' . esc_html($item->name) . '</td>';
                echo '<td>' . esc_html(ucfirst($item->type)) . '</td>';
                echo '<td>' . ($item->total_quantity ?: 0) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No item usage data available.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function display_currencies_report() {
        global $wpdb;
        
        $currencies_table = $wpdb->prefix . 'membershiping_inventory_currencies';
        $user_currencies_table = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        $transactions_table = $wpdb->prefix . 'membershiping_inventory_currency_transactions';
        
        echo '<div class="currencies-report">';
        
        // Currencies by Type
        echo '<h4>' . __('Currencies by Type', 'membershiping-inventory') . '</h4>';
        $currencies_by_type = $wpdb->get_results("
            SELECT type, COUNT(*) as count 
            FROM $currencies_table 
            WHERE status != 'deleted' 
            GROUP BY type 
            ORDER BY count DESC
        ");
        
        if ($currencies_by_type) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Currency Type', 'membershiping-inventory') . '</th><th>' . __('Count', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($currencies_by_type as $type_data) {
                echo '<tr>';
                echo '<td>' . esc_html(ucfirst($type_data->type)) . '</td>';
                echo '<td>' . $type_data->count . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No currencies data available.', 'membershiping-inventory') . '</p>';
        }
        
        // Currency Balances
        echo '<h4>' . __('Total Currency Balances', 'membershiping-inventory') . '</h4>';
        $currency_balances = $wpdb->get_results("
            SELECT c.name, c.symbol, SUM(uc.balance) as total_balance 
            FROM $currencies_table c 
            LEFT JOIN $user_currencies_table uc ON c.id = uc.currency_id 
            WHERE c.status != 'deleted' 
            GROUP BY c.id 
            ORDER BY total_balance DESC
        ");
        
        if ($currency_balances) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Currency', 'membershiping-inventory') . '</th><th>' . __('Total Balance', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($currency_balances as $balance) {
                echo '<tr>';
                echo '<td>' . esc_html($balance->name) . '</td>';
                echo '<td>' . number_format($balance->total_balance ?: 0, 2) . ' ' . esc_html($balance->symbol) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No currency balance data available.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function display_users_report() {
        global $wpdb;
        
        $user_items_table = $wpdb->prefix . 'membershiping_inventory_user_items';
        $user_currencies_table = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        
        echo '<div class="users-report">';
        
        // Top Users by Items
        echo '<h4>' . __('Top Users by Item Count', 'membershiping-inventory') . '</h4>';
        $top_users_items = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email, SUM(ui.quantity) as total_items 
            FROM {$wpdb->users} u 
            LEFT JOIN $user_items_table ui ON u.ID = ui.user_id 
            GROUP BY u.ID 
            HAVING total_items > 0 
            ORDER BY total_items DESC 
            LIMIT 10
        ");
        
        if ($top_users_items) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('User', 'membershiping-inventory') . '</th><th>' . __('Email', 'membershiping-inventory') . '</th><th>' . __('Total Items', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($top_users_items as $user) {
                echo '<tr>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . ($user->total_items ?: 0) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No user item data available.', 'membershiping-inventory') . '</p>';
        }
        
        // User Activity Summary
        echo '<h4>' . __('User Activity Summary', 'membershiping-inventory') . '</h4>';
        $user_activity = $wpdb->get_results("
            SELECT 
                COUNT(DISTINCT ui.user_id) as users_with_items,
                COUNT(DISTINCT uc.user_id) as users_with_currencies
            FROM $user_items_table ui
            RIGHT JOIN $user_currencies_table uc ON ui.user_id = uc.user_id
        ");
        
        if ($user_activity && !empty($user_activity[0])) {
            $activity = $user_activity[0];
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Activity Type', 'membershiping-inventory') . '</th><th>' . __('User Count', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            echo '<tr><td>' . __('Users with Items', 'membershiping-inventory') . '</td><td>' . ($activity->users_with_items ?: 0) . '</td></tr>';
            echo '<tr><td>' . __('Users with Currencies', 'membershiping-inventory') . '</td><td>' . ($activity->users_with_currencies ?: 0) . '</td></tr>';
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No user activity data available.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function display_user_inventory($user_id) {
        global $wpdb;
        
        $user_items_table = $wpdb->prefix . 'membershiping_inventory_user_items';
        $user_currencies_table = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        $items_table = $wpdb->prefix . 'membershiping_inventory_items';
        $currencies_table = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        echo '<div class="user-inventory-display">';
        
        // User Items
        echo '<h4>' . __('User Items', 'membershiping-inventory') . '</h4>';
        $user_items = $wpdb->get_results($wpdb->prepare("
            SELECT ui.*, i.name as item_name, i.type as item_type 
            FROM $user_items_table ui 
            LEFT JOIN $items_table i ON ui.item_id = i.id 
            WHERE ui.user_id = %d AND ui.quantity > 0
            ORDER BY ui.updated_at DESC
        ", $user_id));
        
        if ($user_items) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Item', 'membershiping-inventory') . '</th><th>' . __('Type', 'membershiping-inventory') . '</th><th>' . __('Quantity', 'membershiping-inventory') . '</th><th>' . __('Updated', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($user_items as $item) {
                echo '<tr>';
                echo '<td>' . esc_html($item->item_name ?: 'Unknown Item') . '</td>';
                echo '<td>' . esc_html($item->item_type ?: 'Unknown') . '</td>';
                echo '<td>' . esc_html($item->quantity) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($item->updated_at)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No items found for this user.', 'membershiping-inventory') . '</p>';
        }
        
        // User Currencies
        echo '<h4>' . __('User Currencies', 'membershiping-inventory') . '</h4>';
        $user_currencies = $wpdb->get_results($wpdb->prepare("
            SELECT uc.*, c.name as currency_name, c.symbol as currency_symbol, c.code as currency_code 
            FROM $user_currencies_table uc 
            LEFT JOIN $currencies_table c ON uc.currency_id = c.id 
            WHERE uc.user_id = %d AND uc.balance > 0
            ORDER BY uc.updated_at DESC
        ", $user_id));
        
        if ($user_currencies) {
            echo '<table class="wp-list-table widefat">';
            echo '<thead><tr><th>' . __('Currency', 'membershiping-inventory') . '</th><th>' . __('Code', 'membershiping-inventory') . '</th><th>' . __('Balance', 'membershiping-inventory') . '</th><th>' . __('Updated', 'membershiping-inventory') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($user_currencies as $currency) {
                echo '<tr>';
                echo '<td>' . esc_html($currency->currency_name ?: 'Unknown Currency') . '</td>';
                echo '<td>' . esc_html($currency->currency_code ?: 'N/A') . '</td>';
                echo '<td>' . esc_html($currency->balance) . ' ' . esc_html($currency->currency_symbol) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($currency->updated_at)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No currencies found for this user.', 'membershiping-inventory') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function get_user_items($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND quantity > 0", $user_id));
    }
    
    private function get_user_currencies($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND balance > 0", $user_id));
    }
    
    private function get_user_items_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND quantity > 0", $user_id));
    }
    
    private function get_user_currencies_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND balance > 0", $user_id));
    }
    
    private function process_user_inventory_update($user_id) {
        // Process any user inventory updates if submitted
        // Implementation can be added here as needed
    }
    
    private function add_user_item($user_id, $item_id, $amount) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND item_id = %d", $user_id, $item_id));
        
        if ($existing) {
            return $wpdb->update(
                $table_name,
                array('quantity' => $existing->quantity + $amount, 'updated_at' => current_time('mysql')),
                array('user_id' => $user_id, 'item_id' => $item_id),
                array('%d', '%s'),
                array('%d', '%d')
            );
        } else {
            return $wpdb->insert(
                $table_name,
                array('user_id' => $user_id, 'item_id' => $item_id, 'quantity' => $amount, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('%d', '%d', '%d', '%s', '%s')
            );
        }
    }
    
    private function add_user_currency($user_id, $currency_id, $amount) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND currency_id = %d", $user_id, $currency_id));
        
        if ($existing) {
            return $wpdb->update(
                $table_name,
                array('balance' => $existing->balance + $amount, 'updated_at' => current_time('mysql')),
                array('user_id' => $user_id, 'currency_id' => $currency_id),
                array('%f', '%s'),
                array('%d', '%d')
            );
        } else {
            return $wpdb->insert(
                $table_name,
                array('user_id' => $user_id, 'currency_id' => $currency_id, 'balance' => $amount, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('%d', '%d', '%f', '%s', '%s')
            );
        }
    }
    
    // Settings callbacks
    public function general_settings_callback() {
        echo '<p>' . __('Configure general inventory settings.', 'membershiping-inventory') . '</p>';
    }
    
    public function checkbox_field_callback($args) {
        $options = get_option('membershiping_inventory_options', array());
        $field = $args['field'];
        $value = $options[$field] ?? '';
        
        echo '<input type="checkbox" name="membershiping_inventory_options[' . $field . ']" value="1" ' . checked(1, $value, false) . '>';
    }
    
    public function currency_select_callback($args) {
        $options = get_option('membershiping_inventory_options', array());
        $field = $args['field'];
        $value = $options[$field] ?? '';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_currencies';
        $currencies = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name");
        
        echo '<select name="membershiping_inventory_options[' . $field . ']">';
        echo '<option value="">' . __('Select a currency', 'membershiping-inventory') . '</option>';
        
        foreach ($currencies as $currency) {
            echo '<option value="' . $currency->id . '" ' . selected($currency->id, $value, false) . '>';
            echo esc_html($currency->name);
            echo '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * AJAX: Manage user inventory modal
     */
    public function ajax_manage_user_inventory() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Get user's current inventory and currencies
        global $wpdb;
        
        // Get user items
        $items_table = $wpdb->prefix . 'membershiping_inventory_user_items';
        $products_table = $wpdb->prefix . 'posts';
        
        $user_items = $wpdb->get_results($wpdb->prepare("
            SELECT ui.*, p.post_title as item_name
            FROM $items_table ui
            LEFT JOIN $products_table p ON ui.item_id = p.ID
            WHERE ui.user_id = %d
            ORDER BY p.post_title
        ", $user_id));
        
        // Get user currencies
        $currencies_table = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        $currency_types_table = $wpdb->prefix . 'membershiping_inventory_currencies';
        
        $user_currencies = $wpdb->get_results($wpdb->prepare("
            SELECT uc.*, c.name as currency_name, c.symbol
            FROM $currencies_table uc
            LEFT JOIN $currency_types_table c ON uc.currency_id = c.id
            WHERE uc.user_id = %d
            ORDER BY c.name
        ", $user_id));
        
        // Get all available items (products)
        $available_items = $wpdb->get_results("
            SELECT ID, post_title
            FROM $products_table
            WHERE post_type = 'product'
            AND post_status = 'publish'
            ORDER BY post_title
        ");
        
        // Get all available currencies
        $available_currencies = $wpdb->get_results("
            SELECT id, name, symbol
            FROM $currency_types_table
            ORDER BY name
        ");
        
        ob_start();
        ?>
        <div id="user-inventory-modal" class="membershiping-modal">
            <div class="membershiping-modal-content">
                <div class="membershiping-modal-header">
                    <h2><?php echo sprintf(__('Manage Inventory for %s', 'membershiping-inventory'), $user->display_name); ?></h2>
                    <span class="membershiping-modal-close">&times;</span>
                </div>
                
                <div class="membershiping-modal-body">
                    <!-- Items Section -->
                    <div class="inventory-section">
                        <h3><?php _e('User Items', 'membershiping-inventory'); ?></h3>
                        
                        <div class="current-items">
                            <h4><?php _e('Current Items', 'membershiping-inventory'); ?></h4>
                            <?php if (empty($user_items)): ?>
                                <p><?php _e('No items found.', 'membershiping-inventory'); ?></p>
                            <?php else: ?>
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Item', 'membershiping-inventory'); ?></th>
                                            <th><?php _e('Quantity', 'membershiping-inventory'); ?></th>
                                            <th><?php _e('Actions', 'membershiping-inventory'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_items as $item): ?>
                                            <tr>
                                                <td><?php echo esc_html($item->item_name ?: 'Unknown Item'); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           value="<?php echo $item->quantity; ?>" 
                                                           min="0" 
                                                           class="small-text update-item-quantity"
                                                           data-user-id="<?php echo $user_id; ?>"
                                                           data-item-id="<?php echo $item->item_id; ?>">
                                                </td>
                                                <td>
                                                    <button type="button" class="button update-item-btn" 
                                                            data-user-id="<?php echo $user_id; ?>"
                                                            data-item-id="<?php echo $item->item_id; ?>">
                                                        <?php _e('Update', 'membershiping-inventory'); ?>
                                                    </button>
                                                    <button type="button" class="button remove-item-btn" 
                                                            data-user-id="<?php echo $user_id; ?>"
                                                            data-item-id="<?php echo $item->item_id; ?>">
                                                        <?php _e('Remove', 'membershiping-inventory'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <div class="add-items">
                            <h4><?php _e('Add Item', 'membershiping-inventory'); ?></h4>
                            <select id="add-item-select">
                                <option value=""><?php _e('Select an item...', 'membershiping-inventory'); ?></option>
                                <?php foreach ($available_items as $item): ?>
                                    <option value="<?php echo $item->ID; ?>"><?php echo esc_html($item->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" id="add-item-quantity" value="1" min="1" class="small-text">
                            <button type="button" class="button button-primary add-item-btn" 
                                    data-user-id="<?php echo $user_id; ?>">
                                <?php _e('Add Item', 'membershiping-inventory'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Currencies Section -->
                    <div class="inventory-section">
                        <h3><?php _e('User Currencies', 'membershiping-inventory'); ?></h3>
                        
                        <div class="current-currencies">
                            <h4><?php _e('Current Currencies', 'membershiping-inventory'); ?></h4>
                            <?php if (empty($user_currencies)): ?>
                                <p><?php _e('No currencies found.', 'membershiping-inventory'); ?></p>
                            <?php else: ?>
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Currency', 'membershiping-inventory'); ?></th>
                                            <th><?php _e('Balance', 'membershiping-inventory'); ?></th>
                                            <th><?php _e('Actions', 'membershiping-inventory'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_currencies as $currency): ?>
                                            <tr>
                                                <td><?php echo esc_html($currency->currency_name ?: 'Unknown Currency'); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           value="<?php echo $currency->balance; ?>" 
                                                           min="0" 
                                                           step="0.01"
                                                           class="small-text update-currency-balance"
                                                           data-user-id="<?php echo $user_id; ?>"
                                                           data-currency-id="<?php echo $currency->currency_id; ?>">
                                                </td>
                                                <td>
                                                    <button type="button" class="button update-currency-btn" 
                                                            data-user-id="<?php echo $user_id; ?>"
                                                            data-currency-id="<?php echo $currency->currency_id; ?>">
                                                        <?php _e('Update', 'membershiping-inventory'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <div class="add-currency">
                            <h4><?php _e('Add Currency', 'membershiping-inventory'); ?></h4>
                            <select id="add-currency-select">
                                <option value=""><?php _e('Select a currency...', 'membershiping-inventory'); ?></option>
                                <?php foreach ($available_currencies as $currency): ?>
                                    <option value="<?php echo $currency->id; ?>"><?php echo esc_html($currency->name . ' (' . $currency->symbol . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" id="add-currency-amount" value="100" min="0" step="0.01" class="small-text">
                            <button type="button" class="button button-primary add-currency-btn" 
                                    data-user-id="<?php echo $user_id; ?>">
                                <?php _e('Add Currency', 'membershiping-inventory'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="membershiping-modal-footer">
                    <button type="button" class="button button-secondary membershiping-modal-close">
                        <?php _e('Close', 'membershiping-inventory'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        $modal_html = ob_get_clean();
        
        wp_send_json_success(array(
            'modal_html' => $modal_html
        ));
    }
    
    /**
     * AJAX: Add item to user
     */
    public function ajax_add_user_item() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (!$user_id || !$item_id || $quantity < 1) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Use the items class to add the item
        $items_class = new Membershiping_Inventory_Items();
        $result = $items_class->add_user_item($user_id, $item_id, $quantity);
        
        if ($result) {
            wp_send_json_success('Item added successfully');
        } else {
            wp_send_json_error('Failed to add item');
        }
    }
    
    /**
     * AJAX: Remove item from user
     */
    public function ajax_remove_user_item() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        
        if (!$user_id || !$item_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        
        if ($quantity > 0) {
            // Update quantity
            $result = $wpdb->update(
                $table_name,
                array('quantity' => $quantity),
                array('user_id' => $user_id, 'item_id' => $item_id)
            );
        } else {
            // Remove completely
            $result = $wpdb->delete(
                $table_name,
                array('user_id' => $user_id, 'item_id' => $item_id)
            );
        }
        
        if ($result !== false) {
            wp_send_json_success('Item updated successfully');
        } else {
            wp_send_json_error('Failed to update item');
        }
    }
    
    /**
     * AJAX: Update user currency
     */
    public function ajax_update_user_currency() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $currency_id = intval($_POST['currency_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? 'set');
        
        if (!$user_id || !$currency_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Use the currencies class to update the currency
        $currencies_class = new Membershiping_Inventory_Currencies();
        
        if ($action === 'add') {
            $result = $currencies_class->add_user_currency(
                $user_id, 
                $currency_id, 
                $amount, 
                'admin_added', 
                'Added by administrator'
            );
        } else {
            // Set balance directly
            global $wpdb;
            $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
            
            $result = $wpdb->replace(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'currency_id' => $currency_id,
                    'balance' => $amount,
                    'updated_at' => current_time('mysql')
                )
            );
        }
        
        if ($result) {
            wp_send_json_success('Currency updated successfully');
        } else {
            wp_send_json_error('Failed to update currency');
        }
    }
}
