<?php
/**
 * Plugin Name: Membershiping Inventory & Trading System
 * Plugin URI: https://membershiping.com/addons/inventory-trading
 * Description: Advanced inventory and trading system for Membershiping CRM with NFT support, custom currencies, and virtual items.
 * Version: 1.0.0
 * Author: Membershiping Team
 * Author URI: https://membershiping.com
 * Text Domain: membershiping-inventory
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 * WC HPOS Compatible: Yes
 * 
 * Depends: Membershiping Core Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
if (!defined('MEMBERSHIPING_INVENTORY_VERSION')) {
    define('MEMBERSHIPING_INVENTORY_VERSION', '1.0.0');
}
if (!defined('MEMBERSHIPING_INVENTORY_PLUGIN_URL')) {
    define('MEMBERSHIPING_INVENTORY_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('MEMBERSHIPING_INVENTORY_URL')) {
    define('MEMBERSHIPING_INVENTORY_URL', plugin_dir_url(__FILE__)); // Alias for compatibility
}
if (!defined('MEMBERSHIPING_INVENTORY_PLUGIN_PATH')) {
    define('MEMBERSHIPING_INVENTORY_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('MEMBERSHIPING_INVENTORY_PLUGIN_FILE')) {
    define('MEMBERSHIPING_INVENTORY_PLUGIN_FILE', __FILE__);
}
if (!defined('MEMBERSHIPING_INVENTORY_TEXT_DOMAIN')) {
    define('MEMBERSHIPING_INVENTORY_TEXT_DOMAIN', 'membershiping-inventory');
}

/**
 * Main plugin class
 */

/**
 * Main plugin class
 */
class Membershiping_Inventory_Main {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Initialization flag
     */
    private static $initialized = false;
    
    /**
     * Core components
     */
    public $database;
    public $items;
    public $currencies;
    public $nfts;
    public $trading;
    public $admin;
    public $frontend;
    public $security;
    public $woocommerce_integration;
    public $enhanced_woocommerce_integration;
    public $flag_awards;
    public $consumables;
    public $core_restriction_integration;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Early admin initialization for menu registration
        if (is_admin()) {
            add_action('init', array($this, 'init_admin_early'), 1);
        }
        
        // Check dependencies with higher priority to ensure other plugins are loaded
        add_action('plugins_loaded', array($this, 'check_dependencies'), 15);
        
        // Initialize after dependencies are confirmed
        add_action('plugins_loaded', array($this, 'initialize_if_dependencies_met'), 20);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load textdomain
        add_action('init', array($this, 'load_textdomain'));
        
        // Show activation warnings if any
        add_action('admin_notices', array($this, 'show_activation_warnings'));
    }
    
    /**
     * Initialize admin components early for menu registration
     */
    public function init_admin_early() {
        if (!is_admin()) {
            return;
        }
        
        error_log('Membershiping Inventory: Early admin initialization...');
        
        // Load required files for admin
        $this->load_includes();
        
        // Initialize admin component
        if (class_exists('Membershiping_Inventory_Admin')) {
            $this->admin = new Membershiping_Inventory_Admin();
            error_log('Membershiping Inventory: Admin component initialized early');
        } else {
            error_log('Membershiping Inventory: Admin class not found during early init');
        }

        // Ensure Flag Awards UI is available in product editor even if core dependency fails later
        if (class_exists('WooCommerce') && class_exists('Membershiping_Inventory_Flag_Awards')) {
            if (!isset($this->flag_awards)) {
                $this->flag_awards = new Membershiping_Inventory_Flag_Awards();
                error_log('Membershiping Inventory: Flag Awards initialized early for admin');
            }
        }
    }
    
    /**
     * Check if required dependencies are active
     */
    public function check_dependencies() {
        error_log('Membershiping Inventory: Starting dependency check...');
        $missing_dependencies = array();
        
        // Check for Membershiping Core (multiple detection methods)
        $membershiping_detected = false;
        
        // Method 1: Class exists
        if (class_exists('Membershiping')) {
            $membershiping_detected = true;
        }
        
        // Method 2: Check if plugin is active via plugin list
        if (!$membershiping_detected && function_exists('is_plugin_active')) {
            if (is_plugin_active('membershiping-plugin/membershiping.php') || 
                is_plugin_active('membershiping-elementor/membershiping.php')) {
                $membershiping_detected = true;
            }
        }
        
        // Method 3: Check for core constants
        if (!$membershiping_detected && defined('MEMBERSHIPING_VERSION')) {
            $membershiping_detected = true;
        }
        
        // Method 4: Check if Membershiping functions exist
        if (!$membershiping_detected && function_exists('membershiping_get_user_flags')) {
            $membershiping_detected = true;
        }
        
        if (!$membershiping_detected) {
            $missing_dependencies[] = 'Membershiping Core Plugin';
            error_log('Membershiping Inventory: Membershiping Core Plugin not detected');
        } else {
            error_log('Membershiping Inventory: Membershiping Core Plugin detected successfully');
        }
        
        // Check for WooCommerce
        if (!class_exists('WooCommerce')) {
            $missing_dependencies[] = 'WooCommerce';
            error_log('Membershiping Inventory: WooCommerce not detected');
        } else {
            error_log('Membershiping Inventory: WooCommerce detected successfully');
            // Check WooCommerce version compatibility
            if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>Membershiping Inventory & Trading System</strong> recommends WooCommerce 8.0 or higher for optimal performance.</p>';
                    echo '<p>Current version: ' . WC_VERSION . '</p>';
                    echo '</div>';
                });
            }
            
            // Verify HPOS compatibility if it's enabled
            if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
                method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                    // HPOS is enabled - ensure our plugin declared compatibility
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>Membershiping Inventory & Trading System</strong> is compatible with WooCommerce High-Performance Order Storage (HPOS).</p>';
                        echo '</div>';
                    });
                }
            }
        }
        
        // Show admin notice if dependencies are missing (but don't kill the plugin)
        if (!empty($missing_dependencies)) {
            add_action('admin_notices', function() use ($missing_dependencies) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Membershiping Inventory & Trading System</strong> requires the following plugins to be active:</p>';
                echo '<ul>';
                foreach ($missing_dependencies as $dependency) {
                    echo '<li>• ' . esc_html($dependency) . '</li>';
                }
                echo '</ul>';
                echo '<p>Please ensure these plugins are installed and activated. The Inventory addon will not function properly without them.</p>';
                echo '</div>';
            });
            return false;
        }
        
        error_log('Membershiping Inventory: All dependencies met successfully');
        return true;
    }
    
    /**
     * Show activation warnings if any
     */
    public function show_activation_warnings() {
        $warning = get_transient('membershiping_inventory_activation_warning');
        if ($warning) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Warning:</strong> ' . esc_html($warning) . '</p>';
            echo '</div>';
            delete_transient('membershiping_inventory_activation_warning');
        }
    }
    
    /**
     * Initialize plugin only if dependencies are met
     */
    public function initialize_if_dependencies_met() {
        error_log('Membershiping Inventory: initialize_if_dependencies_met called');
        // Prevent multiple initializations
        if (self::$initialized) {
            error_log('Membershiping Inventory: Already initialized, skipping');
            return;
        }
        
        if ($this->check_dependencies()) {
            error_log('Membershiping Inventory: Dependencies met, initializing components...');
            $this->initialize_components();
        } else {
            error_log('Membershiping Inventory: Dependencies not met, using minimal initialization');
            // Minimal init: if WooCommerce exists, at least load includes and Flag Awards (for product UI/frontend display)
            $this->load_includes();
            $this->initialize_minimal_woocommerce_flag_awards();
        }
    }
    
    /**
     * Initialize all components after dependencies are confirmed
     */
    public function initialize_components() {
        error_log('Membershiping Inventory: initialize_components() started');
        
        // Additional safety check for duplicate initialization
        if (self::$initialized) {
            error_log('Membershiping Inventory: Already initialized, exiting initialize_components');
            return;
        }
        
        error_log('Membershiping Inventory: Starting load_includes...');
        // Load includes first
        $this->load_includes();
        error_log('Membershiping Inventory: load_includes completed');
        
        // Initialize admin (only if not already done in early init)
        if (is_admin() && !isset($this->admin)) {
            error_log('Membershiping Inventory: Initializing admin component...');
            $this->admin = new Membershiping_Inventory_Admin();
            error_log('Membershiping Inventory: Admin component initialized successfully');
        }
        
        // Check dependencies for other components
        if (!$this->check_dependencies()) {
            error_log('Membershiping Inventory: Second dependency check failed, admin-only mode');
            // Still provide minimal WooCommerce flag awards integration so editors can configure products
            $this->initialize_minimal_woocommerce_flag_awards();
            return;
        }
        
        error_log('Membershiping Inventory: Second dependency check passed, continuing with full initialization');
        
        // Initialize database component (but don't recreate tables unless needed)
        error_log('Membershiping Inventory: Initializing database...');
        $this->database = new Membershiping_Inventory_Database();
        error_log('Membershiping Inventory: Database initialized');
        
        // Only initialize tables if not already done or if this is first activation
        error_log('Membershiping Inventory: Initializing tables if needed...');
        $this->init_tables_if_needed();
        error_log('Membershiping Inventory: Tables initialization completed');
        
        // Initialize core components
                // Initialize security system
        error_log('Membershiping Inventory: Initializing security system...');
        $this->security = Membershiping_Inventory_Security::get_instance();
        error_log('Membershiping Inventory: Security system initialized');
        
        error_log('Membershiping Inventory: Initializing currencies...');
        $this->currencies = new Membershiping_Inventory_Currencies();
        error_log('Membershiping Inventory: Currencies initialized');
        
        error_log('Membershiping Inventory: Initializing items...');
        $this->items = new Membershiping_Inventory_Items();
        error_log('Membershiping Inventory: Items initialized');
        
        error_log('Membershiping Inventory: Initializing NFTs...');
        $this->nfts = new Membershiping_Inventory_NFTs();
        error_log('Membershiping Inventory: NFTs initialized');
        
        error_log('Membershiping Inventory: Initializing frontend...');
        $this->frontend = new Membershiping_Inventory_Frontend();
        error_log('Membershiping Inventory: Frontend initialized');
        
        error_log('Membershiping Inventory: Initializing trading...');
        $this->trading = new Membershiping_Inventory_Trading();
        error_log('Membershiping Inventory: Trading initialized');
        
        error_log('Membershiping Inventory: Initializing flag awards...');
        $this->flag_awards = new Membershiping_Inventory_Flag_Awards();
        error_log('Membershiping Inventory: Flag awards initialized');
        
        error_log('Membershiping Inventory: Initializing consumables...');
        $this->consumables = new Membershiping_Inventory_Consumables();
        error_log('Membershiping Inventory: Consumables initialized');
        
        // Initialize core plugin integration (replaces standalone content restriction)
        error_log('Membershiping Inventory: Initializing core restriction integration...');
        $this->core_restriction_integration = new Membershiping_Inventory_Core_Restriction_Integration();
        error_log('Membershiping Inventory: Core restriction integration initialized');
        
        // Initialize integrations
        error_log('Membershiping Inventory: Initializing WooCommerce integration...');
        $this->woocommerce_integration = new Membershiping_Inventory_WooCommerce_Integration();
        error_log('Membershiping Inventory: WooCommerce integration initialized');
        
        // Initialize enhanced WooCommerce integration (currency pricing, item-based pricing)
        if (class_exists('Membershiping_Inventory_Enhanced_WooCommerce_Integration')) {
            error_log('Membershiping Inventory: Initializing Enhanced WooCommerce Integration...');
            $this->enhanced_woocommerce_integration = new Membershiping_Inventory_Enhanced_WooCommerce_Integration();
            error_log('Membershiping Inventory: Enhanced WooCommerce Integration initialized successfully');
        } else {
            error_log('Membershiping Inventory: Enhanced WooCommerce Integration class not found');
        }
        
        $this->frontend = new Membershiping_Inventory_Frontend();
        
        // Initialize database tables
        $this->database->init_tables();
        
        // Hook into core plugin actions
        $this->init_core_hooks();
        
        // Set initialization flag at the very end
        self::$initialized = true;
        error_log('Membershiping Inventory: Full initialization completed successfully');
        
        // Trigger initialization complete action
        do_action('membershiping_inventory_loaded');
    }

    /**
     * Minimal init to expose Flag Awards UI and frontend section when WooCommerce is active
     */
    private function initialize_minimal_woocommerce_flag_awards() {
        if (class_exists('WooCommerce') && class_exists('Membershiping_Inventory_Flag_Awards')) {
            if (!isset($this->flag_awards)) {
                $this->flag_awards = new Membershiping_Inventory_Flag_Awards();
                error_log('Membershiping Inventory: Minimal Flag Awards initialized (WooCommerce detected)');
            }
        }
    }
    
    /**
     * Load required files
     */
    private function load_includes() {
        error_log('Membershiping Inventory: load_includes() started');
        
        $includes = array(
            'includes/class-database.php',
            'includes/class-security.php',
            'includes/class-currencies.php',
            'includes/class-items.php',
            'includes/class-nfts.php',
            'includes/class-trading.php',
            'includes/class-flag-awards.php',
            'includes/class-consumables.php',
            'includes/class-core-restriction-integration.php',
            'includes/class-admin-dashboard.php',
            'includes/class-woocommerce-integration.php',
            'includes/class-enhanced-woocommerce-integration.php',
            'includes/class-frontend.php',
        );
        
        // Load admin files only in admin
        if (is_admin()) {
            $includes[] = 'admin/class-admin.php';
        }
        
        foreach ($includes as $file) {
            $file_path = MEMBERSHIPING_INVENTORY_PLUGIN_PATH . $file;
            error_log('Membershiping Inventory: Loading file - ' . $file);
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log('Membershiping Inventory: Successfully loaded - ' . $file);
            } else {
                error_log('Membershiping Inventory: Missing file - ' . $file_path);
            }
        }
        
        error_log('Membershiping Inventory: load_includes() completed');
    }
    
    /**
     * Initialize hooks with core plugin
     */
    private function init_core_hooks() {
        // Hook into WooCommerce order completion
        add_action('woocommerce_order_status_completed', array($this, 'process_order_completion'), 20, 1);
        add_action('woocommerce_payment_complete', array($this, 'process_order_completion'), 20, 1);
        
        // Hook into flag awards
        add_action('membershiping_flag_awarded', array($this, 'process_flag_award'), 10, 3);
        
        // Hook into user registration
        add_action('user_register', array($this, 'setup_new_user_inventory'), 10, 1);
    }
    
    /**
     * Process WooCommerce order completion for item awards
     */
    public function process_order_completion($order_id) {
        if (!$this->items || !$this->woocommerce_integration) {
            return;
        }
        
        $this->woocommerce_integration->process_order_items($order_id);
        $this->woocommerce_integration->process_flag_awards($order_id);
    }
    
    /**
     * Process flag award for potential item rewards
     */
    public function process_flag_award($user_id, $flag_id, $assigner_id) {
        // Future feature: Award items based on flag awards
        do_action('membershiping_inventory_flag_awarded', $user_id, $flag_id, $assigner_id);
    }
    
    /**
     * Setup inventory for new users
     */
    public function setup_new_user_inventory($user_id) {
        if (!$this->currencies) {
            return;
        }
        
        // Initialize default currency balances
        $this->currencies->initialize_user_currencies($user_id);
        
        do_action('membershiping_inventory_user_initialized', $user_id);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Step 1: Reset initialization state for clean activation
        self::reset_initialization_state();
        
        // Step 2: Clear any previous installation remnants (V1 clean slate)
        $this->cleanup_previous_installation();
        
        // Step 3: Check dependencies - warn but don't fail completely
        if (!$this->check_dependencies()) {
            // Set a transient to show warning on next admin page load
            set_transient('membershiping_inventory_activation_warning', 
                'Membershiping Inventory & Trading System was activated but requires Membershiping Core and WooCommerce to function properly. Please install and activate these dependencies.', 
                300); // 5 minutes
            
            // Still create tables in case dependencies are added later
        }
        
        // Step 4: Create database tables with proper constraint handling
        require_once MEMBERSHIPING_INVENTORY_PLUGIN_PATH . 'includes/class-database.php';
        $database = new Membershiping_Inventory_Database();
        $database->create_tables();
        
        // Step 5: Set default options
        $this->set_default_options();
        
        // Step 6: Initialize security settings
        $this->initialize_security_settings();
        
        // Step 7: Schedule cleanup events
        if (!wp_next_scheduled('membershiping_inventory_cleanup_expired_trades')) {
            wp_schedule_event(time(), 'hourly', 'membershiping_inventory_cleanup_expired_trades');
        }
        
        // Step 8: Clear any caches
        $this->clear_activation_caches();
        
        // Step 9: Flush rewrite rules
        flush_rewrite_rules();
        
        // Step 10: Set activation flag for first-run initialization
        update_option('membershiping_inventory_activated', time(), false);
        update_option('membershiping_inventory_version', MEMBERSHIPING_INVENTORY_VERSION, false);
        
        // Log successful activation
        error_log('Membershiping Inventory & Trading System V1 activated successfully with clean initialization');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('membershiping_inventory_cleanup_expired_trades');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Membershiping Inventory & Trading System deactivated');
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'membershiping_inventory_enable_trading' => 1,
            'membershiping_inventory_enable_nfts' => 1,
            'membershiping_inventory_trade_expiry_hours' => 72,
            'membershiping_inventory_max_trade_items' => 10,
            'membershiping_inventory_enable_currencies' => 1,
            'membershiping_inventory_default_currency' => 'coins',
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Clean up any previous installation remnants (V1 clean slate)
     */
    private function cleanup_previous_installation() {
        global $wpdb;
        
        // Clear any stuck transients from previous attempts
        $old_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_membershiping_inventory_%' 
             OR option_name LIKE '_transient_timeout_membershiping_inventory_%'"
        );
        
        foreach ($old_transients as $transient) {
            delete_option($transient->option_name);
        }
        
        // Clear rate limiting transients
        $rate_limit_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_membershiping_requests_%'"
        );
        
        foreach ($rate_limit_transients as $transient) {
            delete_option($transient->option_name);
        }
        
        // Remove any old activation flags
        delete_option('membershiping_inventory_activating');
        delete_option('membershiping_inventory_db_version');
        delete_transient('membershiping_inventory_tables_created');
        
        // Clear any old foreign key error flags
        delete_option('membershiping_inventory_fk_errors');
        
        // Reset initialization state
        self::$initialized = false;
        
        error_log('Membershiping Inventory: V1 activation cleanup completed');
    }
    
    /**
     * Reset initialization state (used during activation)
     */
    public static function reset_initialization_state() {
        self::$initialized = false;
    }
    
    /**
     * Initialize security settings on activation
     */
    private function initialize_security_settings() {
        // Set secure defaults for rate limiting
        $security_defaults = array(
            'membershiping_inventory_rate_limit_enabled' => 1,
            'membershiping_inventory_user_rate_limit' => 500,  // 500 requests per hour
            'membershiping_inventory_ip_rate_limit' => 1000,   // 1000 requests per hour per IP
            'membershiping_inventory_trade_rate_limit' => 10,  // 10 trades per hour
            'membershiping_inventory_currency_rate_limit' => 50, // 50 currency operations per hour
        );
        
        foreach ($security_defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // Disable rate limiting temporarily for first 5 minutes after activation
        update_option('membershiping_inventory_disable_rate_limiting', time(), false);
        
        error_log('Membershiping Inventory: Security settings initialized');
    }
    
    /**
     * Clear activation-related caches
     */
    private function clear_activation_caches() {
        // Clear WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear any plugin-specific caches
        wp_cache_delete('membershiping_inventory_tables', 'plugins');
        wp_cache_delete('membershiping_inventory_constraints', 'plugins');
        
        // Clear transients that might interfere
        delete_transient('membershiping_inventory_init_lock');
        delete_transient('membershiping_inventory_db_check');
        
        error_log('Membershiping Inventory: Activation caches cleared');
    }
    
    /**
     * Initialize tables only if needed (prevents recreation on every load)
     */
    private function init_tables_if_needed() {
        // Check if tables were already created and version matches
        $db_version = get_option('membershiping_inventory_db_version');
        $activation_time = get_option('membershiping_inventory_activated');
        
        // If no version recorded or this is first 24 hours after activation, ensure tables exist
        if (!$db_version || (time() - $activation_time) < DAY_IN_SECONDS) {
            // Only call init_tables (which just checks/creates) not create_tables (which recreates)
            $this->database->init_tables();
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            MEMBERSHIPING_INVENTORY_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return MEMBERSHIPING_INVENTORY_PLUGIN_URL;
    }
    
    /**
     * Get plugin path
     */
    public function get_plugin_path() {
        return MEMBERSHIPING_INVENTORY_PLUGIN_PATH;
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return MEMBERSHIPING_INVENTORY_VERSION;
    }
    
    /**
     * Check if feature is enabled
     */
    public function is_feature_enabled($feature) {
        $option_map = array(
            'trading' => 'membershiping_inventory_enable_trading',
            'nfts' => 'membershiping_inventory_enable_nfts',
            'currencies' => 'membershiping_inventory_enable_currencies',
        );
        
        if (isset($option_map[$feature])) {
            return (bool) get_option($option_map[$feature], 1);
        }
        
        return false;
    }
}

/**
 * Get main plugin instance
 */
function membershiping_inventory() {
    return Membershiping_Inventory_Main::get_instance();
}

// Initialize plugin
membershiping_inventory();

/**
 * Schedule cleanup of expired trades
 */
add_action('membershiping_inventory_cleanup_expired_trades', function() {
    if (class_exists('Membershiping_Inventory_Trading')) {
        $trading = new Membershiping_Inventory_Trading();
        $trading->cleanup_expired_trades();
    }
});
