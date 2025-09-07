<?php
/**
 * Core Functionality Validator for Membershiping Inventory System
 * Comprehensive testing of all major plugin features
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Functionality_Validator {
    
    private $test_results = array();
    private $database;
    private $items;
    private $currencies;
    private $nfts;
    private $trading;
    private $security;
    
    public function __construct() {
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
        
        // Initialize components if they exist
        if (class_exists('Membershiping_Inventory_Items')) {
            $this->items = new Membershiping_Inventory_Items();
        }
        if (class_exists('Membershiping_Inventory_Currencies')) {
            $this->currencies = new Membershiping_Inventory_Currencies();
        }
        if (class_exists('Membershiping_Inventory_NFTs')) {
            $this->nfts = new Membershiping_Inventory_NFTs();
        }
        if (class_exists('Membershiping_Inventory_Trading')) {
            $this->trading = new Membershiping_Inventory_Trading();
        }
    }
    
    /**
     * Run comprehensive functionality validation
     */
    public function run_validation() {
        $this->test_results = array();
        
        echo "<h2>🔍 Membershiping Inventory - Core Functionality Validation</h2>\n";
        echo "<p>Testing all major plugin features and functionality...</p>\n\n";
        
        // Test 1: Class Existence and Initialization
        $this->test_class_initialization();
        
        // Test 2: Database Integration
        $this->test_database_integration();
        
        // Test 3: Security Framework
        $this->test_security_framework();
        
        // Test 4: Currency System
        $this->test_currency_system();
        
        // Test 5: Items Management
        $this->test_items_management();
        
        // Test 6: NFT Functionality
        $this->test_nft_functionality();
        
        // Test 7: Trading System
        $this->test_trading_system();
        
        // Test 8: Admin Integration
        $this->test_admin_integration();
        
        // Test 9: Frontend Integration
        $this->test_frontend_integration();
        
        // Test 10: WooCommerce Integration
        $this->test_woocommerce_integration();
        
        // Test 11: Plugin Constants and Configuration
        $this->test_plugin_configuration();
        
        // Test 12: Hook System
        $this->test_hook_system();
        
        // Generate summary
        $this->generate_summary();
        
        return $this->test_results;
    }
    
    /**
     * Test 1: Class Existence and Initialization
     */
    private function test_class_initialization() {
        echo "<h3>1. Class Initialization Testing</h3>\n";
        
        $required_classes = array(
            'Membershiping_Inventory_Main' => 'Main plugin class',
            'Membershiping_Inventory_Database' => 'Database management',
            'Membershiping_Inventory_Security' => 'Security framework',
            'Membershiping_Inventory_Items' => 'Items management',
            'Membershiping_Inventory_Currencies' => 'Currency system',
            'Membershiping_Inventory_NFTs' => 'NFT functionality',
            'Membershiping_Inventory_Trading' => 'Trading system',
            'Membershiping_Inventory_Frontend' => 'Frontend display',
            'Membershiping_Inventory_Admin_Dashboard' => 'Admin interface'
        );
        
        foreach ($required_classes as $class_name => $description) {
            if (class_exists($class_name)) {
                $this->log_success("✅ {$description} class exists ({$class_name})");
                
                // Test instantiation
                try {
                    if ($class_name === 'Membershiping_Inventory_Main') {
                        // Singleton pattern - use get_instance
                        $instance = Membershiping_Inventory_Main::get_instance();
                    } else {
                        $instance = new $class_name();
                    }
                    
                    if (is_object($instance)) {
                        $this->log_success("✅ {$description} instantiates correctly");
                    } else {
                        $this->log_error("❌ {$description} failed to instantiate");
                    }
                } catch (Exception $e) {
                    $this->log_error("❌ {$description} instantiation error: " . $e->getMessage());
                }
            } else {
                $this->log_error("❌ {$description} class missing ({$class_name})");
            }
        }
        echo "\n";
    }
    
    /**
     * Test 2: Database Integration
     */
    private function test_database_integration() {
        echo "<h3>2. Database Integration Testing</h3>\n";
        
        if (!$this->database) {
            $this->log_error("❌ Database class not available");
            return;
        }
        
        // Test table existence
        $required_tables = array(
            'items', 'user_items', 'nfts', 'currencies', 'user_currencies',
            'trades', 'trade_history', 'transactions', 'audit_logs', 
            'user_levels', 'cart_cleanup', 'automation_logs'
        );
        
        foreach ($required_tables as $table) {
            $table_name = $this->database->get_table_name($table);
            if ($table_name && $this->table_exists($table_name)) {
                $this->log_success("✅ Table '{$table}' exists and accessible");
            } else {
                $this->log_error("❌ Table '{$table}' missing or inaccessible");
            }
        }
        
        // Test database connection
        global $wpdb;
        $test_query = $wpdb->get_var("SELECT 1");
        if ($test_query == 1) {
            $this->log_success("✅ Database connection functional");
        } else {
            $this->log_error("❌ Database connection issue");
        }
        
        echo "\n";
    }
    
    /**
     * Test 3: Security Framework
     */
    private function test_security_framework() {
        echo "<h3>3. Security Framework Testing</h3>\n";
        
        if (!$this->security) {
            $this->log_error("❌ Security class not available");
            return;
        }
        
        // Test security methods
        $security_methods = array(
            'get_user_ip' => 'IP address detection',
            'verify_ajax_nonce' => 'AJAX nonce verification',
            'sanitize_user_input' => 'Input sanitization',
            'validate_currency_amount' => 'Currency validation',
            'check_rate_limit' => 'Rate limiting',
            'is_user_blocked' => 'User blocking system',
            'log_security_event' => 'Security logging'
        );
        
        foreach ($security_methods as $method => $description) {
            if (method_exists($this->security, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        // Test input sanitization
        $test_input = "<script>alert('xss')</script>test";
        $sanitized = $this->security->sanitize_user_input($test_input);
        if (strpos($sanitized, '<script>') === false) {
            $this->log_success("✅ Input sanitization working correctly");
        } else {
            $this->log_error("❌ Input sanitization failed");
        }
        
        // Test currency validation
        if ($this->security->validate_currency_amount(100.50)) {
            $this->log_success("✅ Currency amount validation working");
        } else {
            $this->log_error("❌ Currency amount validation failed");
        }
        
        echo "\n";
    }
    
    /**
     * Test 4: Currency System
     */
    private function test_currency_system() {
        echo "<h3>4. Currency System Testing</h3>\n";
        
        if (!$this->currencies) {
            $this->log_error("❌ Currency class not available");
            return;
        }
        
        // Test currency methods
        $currency_methods = array(
            'create_currency' => 'Currency creation',
            'get_currency' => 'Currency retrieval',
            'update_currency' => 'Currency updates',
            'get_user_balance' => 'Balance checking',
            'add_currency' => 'Currency addition',
            'deduct_currency' => 'Currency deduction',
            'transfer_currency' => 'Currency transfers'
        );
        
        foreach ($currency_methods as $method => $description) {
            if (method_exists($this->currencies, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 5: Items Management
     */
    private function test_items_management() {
        echo "<h3>5. Items Management Testing</h3>\n";
        
        if (!$this->items) {
            $this->log_error("❌ Items class not available");
            return;
        }
        
        // Test item methods
        $item_methods = array(
            'create_item' => 'Item creation',
            'get_item' => 'Item retrieval',
            'update_item' => 'Item updates',
            'delete_item' => 'Item deletion',
            'add_item_to_user' => 'User item addition',
            'remove_item_from_user' => 'User item removal',
            'get_user_items' => 'User inventory retrieval',
            'use_item' => 'Item consumption'
        );
        
        foreach ($item_methods as $method => $description) {
            if (method_exists($this->items, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 6: NFT Functionality
     */
    private function test_nft_functionality() {
        echo "<h3>6. NFT Functionality Testing</h3>\n";
        
        if (!$this->nfts) {
            $this->log_error("❌ NFT class not available");
            return;
        }
        
        // Test NFT methods
        $nft_methods = array(
            'mint_nft' => 'NFT minting',
            'get_nft' => 'NFT retrieval',
            'transfer_nft' => 'NFT transfers',
            'upgrade_nft' => 'NFT upgrades',
            'get_user_nfts' => 'User NFT retrieval',
            'validate_nft_ownership' => 'Ownership validation',
            'generate_unique_hash' => 'Hash generation',
            'generate_unique_token' => 'Token generation'
        );
        
        foreach ($nft_methods as $method => $description) {
            if (method_exists($this->nfts, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 7: Trading System
     */
    private function test_trading_system() {
        echo "<h3>7. Trading System Testing</h3>\n";
        
        if (!$this->trading) {
            $this->log_error("❌ Trading class not available");
            return;
        }
        
        // Test trading methods
        $trading_methods = array(
            'create_trade' => 'Trade creation',
            'accept_trade' => 'Trade acceptance',
            'decline_trade' => 'Trade decline',
            'cancel_trade' => 'Trade cancellation',
            'get_user_trades' => 'User trades retrieval',
            'validate_trade_data' => 'Trade validation',
            'calculate_trade_value' => 'Trade value calculation',
            'execute_trade' => 'Trade execution'
        );
        
        foreach ($trading_methods as $method => $description) {
            if (method_exists($this->trading, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 8: Admin Integration
     */
    private function test_admin_integration() {
        echo "<h3>8. Admin Integration Testing</h3>\n";
        
        // Test admin class existence
        if (class_exists('Membershiping_Inventory_Admin_Dashboard')) {
            $this->log_success("✅ Admin dashboard class exists");
        } else {
            $this->log_error("❌ Admin dashboard class missing");
        }
        
        // Test admin hooks
        $admin_hooks = array(
            'admin_menu',
            'admin_enqueue_scripts',
            'add_meta_boxes',
            'woocommerce_process_product_meta'
        );
        
        foreach ($admin_hooks as $hook) {
            if (has_action($hook)) {
                $this->log_success("✅ Admin hook '{$hook}' is registered");
            } else {
                $this->log_warning("⚠️ Admin hook '{$hook}' not registered (may be conditional)");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 9: Frontend Integration
     */
    private function test_frontend_integration() {
        echo "<h3>9. Frontend Integration Testing</h3>\n";
        
        // Test frontend class
        if (class_exists('Membershiping_Inventory_Frontend')) {
            $this->log_success("✅ Frontend class exists");
        } else {
            $this->log_error("❌ Frontend class missing");
        }
        
        // Test shortcodes
        $shortcodes = array(
            'membershiping_inventory',
            'membershiping_currencies',
            'membershiping_nfts',
            'membershiping_trading',
            'membershiping_user_inventory',
            'membershiping_trading_interface',
            'membershiping_nft_gallery',
            'membershiping_inventory_currencies',
            'membershiping_require_item',
            'membershiping_if_has_currency',
            'membershiping_restriction_message'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (shortcode_exists($shortcode)) {
                $this->log_success("✅ Shortcode '{$shortcode}' registered");
            } else {
                $this->log_error("❌ Shortcode '{$shortcode}' not registered");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 10: WooCommerce Integration
     */
    private function test_woocommerce_integration() {
        echo "<h3>10. WooCommerce Integration Testing</h3>\n";
        
        // Check WooCommerce availability
        if (!class_exists('WooCommerce')) {
            $this->log_warning("⚠️ WooCommerce not active - integration tests skipped");
            return;
        }
        
        // Test WooCommerce hooks
        $wc_hooks = array(
            'woocommerce_order_status_completed',
            'woocommerce_payment_complete',
            'woocommerce_process_product_meta',
            'woocommerce_single_product_summary'
        );
        
        foreach ($wc_hooks as $hook) {
            if (has_action($hook)) {
                $this->log_success("✅ WooCommerce hook '{$hook}' registered");
            } else {
                $this->log_error("❌ WooCommerce hook '{$hook}' not registered");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 11: Plugin Configuration
     */
    private function test_plugin_configuration() {
        echo "<h3>11. Plugin Configuration Testing</h3>\n";
        
        // Test constants
        $required_constants = array(
            'MEMBERSHIPING_INVENTORY_VERSION' => 'Plugin version',
            'MEMBERSHIPING_INVENTORY_PLUGIN_URL' => 'Plugin URL',
            'MEMBERSHIPING_INVENTORY_PLUGIN_PATH' => 'Plugin path',
            'MEMBERSHIPING_INVENTORY_TEXT_DOMAIN' => 'Text domain'
        );
        
        foreach ($required_constants as $constant => $description) {
            if (defined($constant)) {
                $this->log_success("✅ {$description} constant defined ({$constant})");
            } else {
                $this->log_error("❌ {$description} constant missing ({$constant})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 12: Hook System
     */
    private function test_hook_system() {
        echo "<h3>12. Hook System Testing</h3>\n";
        
        // Test core hooks
        $core_hooks = array(
            'plugins_loaded',
            'init',
            'wp_ajax_membershiping_inventory_create_trade',
            'wp_ajax_membershiping_inventory_accept_trade',
            'wp_enqueue_scripts'
        );
        
        foreach ($core_hooks as $hook) {
            if (has_action($hook) || has_filter($hook)) {
                $this->log_success("✅ Hook '{$hook}' has registered callbacks");
            } else {
                $this->log_warning("⚠️ Hook '{$hook}' has no registered callbacks");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Generate validation summary
     */
    private function generate_summary() {
        echo "<h3>📊 Validation Summary</h3>\n";
        
        $success_count = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'success';
        }));
        
        $error_count = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'error';
        }));
        
        $warning_count = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'warning';
        }));
        
        $total_tests = count($this->test_results);
        $success_rate = $total_tests > 0 ? round(($success_count / $total_tests) * 100, 1) : 0;
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
        echo "<strong>Results:</strong><br>\n";
        echo "✅ Passed: {$success_count}<br>\n";
        echo "❌ Failed: {$error_count}<br>\n";
        echo "⚠️ Warnings: {$warning_count}<br>\n";
        echo "<strong>Success Rate: {$success_rate}%</strong><br>\n";
        echo "</div>\n";
        
        if ($success_rate >= 90) {
            echo "<p style='color: green;'><strong>🎉 Excellent! Core functionality is working well.</strong></p>\n";
        } elseif ($success_rate >= 75) {
            echo "<p style='color: orange;'><strong>⚠️ Good progress, but some issues need attention.</strong></p>\n";
        } else {
            echo "<p style='color: red;'><strong>❌ Critical issues found that need immediate attention.</strong></p>\n";
        }
        
        // Recommendations
        if ($error_count > 0) {
            echo "<h4>🔧 Recommendations:</h4>\n";
            echo "<ul>\n";
            foreach ($this->test_results as $result) {
                if ($result['status'] === 'error') {
                    echo "<li>{$result['message']}</li>\n";
                }
            }
            echo "</ul>\n";
        }
    }
    
    /**
     * Helper methods
     */
    private function log_success($message) {
        $this->test_results[] = array('status' => 'success', 'message' => $message);
        echo $message . "\n";
    }
    
    private function log_error($message) {
        $this->test_results[] = array('status' => 'error', 'message' => $message);
        echo $message . "\n";
    }
    
    private function log_warning($message) {
        $this->test_results[] = array('status' => 'warning', 'message' => $message);
        echo $message . "\n";
    }
    
    private function table_exists($table_name) {
        global $wpdb;
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        return $wpdb->get_var($query) === $table_name;
    }
}

// Auto-run if accessed directly for testing
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI execution
    $validator = new Membershiping_Inventory_Functionality_Validator();
    $results = $validator->run_validation();
} elseif (isset($_GET['run_functionality_test']) && current_user_can('manage_options')) {
    // Admin execution via URL parameter
    $validator = new Membershiping_Inventory_Functionality_Validator();
    $results = $validator->run_validation();
}
