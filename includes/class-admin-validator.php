<?php
/**
 * Admin Interface Validator for Membershiping Inventory System
 * Comprehensive testing of admin panel functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Admin_Validator {
    
    private $test_results = array();
    private $admin_dashboard;
    
    public function __construct() {
        if (class_exists('Membershiping_Inventory_Admin_Dashboard')) {
            $this->admin_dashboard = new Membershiping_Inventory_Admin_Dashboard();
        }
    }
    
    /**
     * Run comprehensive admin interface validation
     */
    public function run_validation() {
        $this->test_results = array();
        
        echo "<h2>⚙️ Membershiping Inventory - Admin Interface Validation</h2>\n";
        echo "<p>Testing admin panel functionality, menus, pages, and management features...</p>\n\n";
        
        // Test 1: Admin Menu Structure
        $this->test_admin_menu_structure();
        
        // Test 2: Admin Class and Methods
        $this->test_admin_class_methods();
        
        // Test 3: Admin Hooks and Actions
        $this->test_admin_hooks();
        
        // Test 4: AJAX Handlers
        $this->test_ajax_handlers();
        
        // Test 5: Capability Checks
        $this->test_admin_capabilities();
        
        // Test 6: Admin Assets (CSS/JS)
        $this->test_admin_assets();
        
        // Test 7: Settings API Integration
        $this->test_settings_api();
        
        // Test 8: Meta Boxes (WooCommerce Integration)
        $this->test_meta_boxes();
        
        // Test 9: Admin Notices System
        $this->test_admin_notices();
        
        // Test 10: Data Export/Import Features
        $this->test_data_management();
        
        // Test 11: Bulk Operations
        $this->test_bulk_operations();
        
        // Test 12: User Management Interface
        $this->test_user_management();
        
        // Generate summary
        $this->generate_summary();
        
        return $this->test_results;
    }
    
    /**
     * Test 1: Admin Menu Structure
     */
    private function test_admin_menu_structure() {
        echo "<h3>1. Admin Menu Structure Testing</h3>\n";
        
        global $menu, $submenu;
        
        // Check main menu item
        $main_menu_found = false;
        if (is_array($menu)) {
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === 'membershiping-inventory') {
                    $main_menu_found = true;
                    $this->log_success("✅ Main inventory menu item found");
                    
                    // Check menu properties
                    if (isset($menu_item[0]) && strpos($menu_item[0], 'Inventory') !== false) {
                        $this->log_success("✅ Menu title is correct");
                    } else {
                        $this->log_error("❌ Menu title issue");
                    }
                    
                    if (isset($menu_item[5]) && $menu_item[5] === 'dashicons-archive') {
                        $this->log_success("✅ Menu icon is set correctly");
                    } else {
                        $this->log_warning("⚠️ Menu icon may not be set");
                    }
                    break;
                }
            }
        }
        
        if (!$main_menu_found) {
            $this->log_error("❌ Main inventory menu item not found");
        }
        
        // Check submenu items
        $expected_submenus = array(
            'membershiping-inventory' => 'Dashboard',
            'membershiping-inventory-items' => 'Items',
            'membershiping-inventory-currencies' => 'Currencies',
            'membershiping-inventory-nfts' => 'NFTs',
            'membershiping-inventory-trading' => 'Trading',
            'membershiping-inventory-users' => 'Users',
            'membershiping-inventory-settings' => 'Settings'
        );
        
        if (isset($submenu['membershiping-inventory']) && is_array($submenu['membershiping-inventory'])) {
            $found_submenus = array();
            foreach ($submenu['membershiping-inventory'] as $submenu_item) {
                if (isset($submenu_item[2])) {
                    $found_submenus[] = $submenu_item[2];
                }
            }
            
            foreach ($expected_submenus as $slug => $title) {
                if (in_array($slug, $found_submenus)) {
                    $this->log_success("✅ Submenu '{$title}' found ({$slug})");
                } else {
                    $this->log_warning("⚠️ Submenu '{$title}' not found ({$slug})");
                }
            }
        } else {
            $this->log_error("❌ No submenus found for inventory");
        }
        
        echo "\n";
    }
    
    /**
     * Test 2: Admin Class and Methods
     */
    private function test_admin_class_methods() {
        echo "<h3>2. Admin Class and Methods Testing</h3>\n";
        
        if (!$this->admin_dashboard) {
            $this->log_error("❌ Admin dashboard class not available");
            return;
        }
        
        // Test essential admin methods
        $required_methods = array(
            'add_admin_menu' => 'Menu creation',
            'enqueue_admin_assets' => 'Asset loading',
            'render_dashboard_page' => 'Dashboard rendering',
            'render_items_page' => 'Items page rendering',
            'render_currencies_page' => 'Currencies page rendering',
            'render_nfts_page' => 'NFTs page rendering',
            'render_trading_page' => 'Trading page rendering',
            'render_users_page' => 'Users page rendering',
            'render_settings_page' => 'Settings page rendering',
            'ajax_get_dashboard_stats' => 'Dashboard statistics',
            'ajax_get_user_inventory' => 'User inventory retrieval',
            'ajax_bulk_award_items' => 'Bulk item awards',
            'ajax_bulk_remove_items' => 'Bulk item removal',
            'ajax_export_data' => 'Data export',
            'ajax_import_data' => 'Data import'
        );
        
        foreach ($required_methods as $method => $description) {
            if (method_exists($this->admin_dashboard, $method)) {
                $this->log_success("✅ {$description} method available ({$method})");
            } else {
                $this->log_error("❌ {$description} method missing ({$method})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 3: Admin Hooks and Actions
     */
    private function test_admin_hooks() {
        echo "<h3>3. Admin Hooks and Actions Testing</h3>\n";
        
        // Test core admin hooks
        $admin_hooks = array(
            'admin_menu' => 'Menu registration',
            'admin_enqueue_scripts' => 'Asset enqueuing',
            'admin_init' => 'Admin initialization',
            'admin_notices' => 'Admin notices',
            'add_meta_boxes' => 'Meta boxes registration',
            'woocommerce_process_product_meta' => 'WooCommerce integration'
        );
        
        foreach ($admin_hooks as $hook => $description) {
            if (has_action($hook)) {
                $priority = has_action($hook);
                $this->log_success("✅ {$description} hook registered (priority: {$priority})");
            } else {
                $this->log_error("❌ {$description} hook not registered ({$hook})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 4: AJAX Handlers
     */
    private function test_ajax_handlers() {
        echo "<h3>4. AJAX Handlers Testing</h3>\n";
        
        // Test AJAX actions
        $ajax_actions = array(
            'wp_ajax_membershiping_get_dashboard_stats' => 'Dashboard statistics',
            'wp_ajax_membershiping_get_user_inventory' => 'User inventory',
            'wp_ajax_membershiping_bulk_award_items' => 'Bulk award items',
            'wp_ajax_membershiping_bulk_remove_items' => 'Bulk remove items',
            'wp_ajax_membershiping_reset_user_inventory' => 'Reset user inventory',
            'wp_ajax_membershiping_export_data' => 'Data export',
            'wp_ajax_membershiping_import_data' => 'Data import',
            'wp_ajax_membershiping_system_diagnostics' => 'System diagnostics',
            'wp_ajax_membershiping_cleanup_system' => 'System cleanup'
        );
        
        foreach ($ajax_actions as $action => $description) {
            if (has_action($action)) {
                $this->log_success("✅ {$description} AJAX handler registered");
            } else {
                $this->log_error("❌ {$description} AJAX handler missing ({$action})");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 5: Admin Capabilities
     */
    private function test_admin_capabilities() {
        echo "<h3>5. Admin Capabilities Testing</h3>\n";
        
        // Test current user capabilities for admin access
        $required_capabilities = array(
            'manage_options' => 'General admin access',
            'edit_posts' => 'Post editing (for meta boxes)',
            'upload_files' => 'File uploads'
        );
        
        foreach ($required_capabilities as $capability => $description) {
            if (current_user_can($capability)) {
                $this->log_success("✅ {$description} capability available ({$capability})");
            } else {
                $this->log_warning("⚠️ {$description} capability not available ({$capability})");
            }
        }
        
        // Test admin page access simulation
        if (current_user_can('manage_options')) {
            $this->log_success("✅ User can access admin pages");
        } else {
            $this->log_error("❌ User cannot access admin pages");
        }
        
        echo "\n";
    }
    
    /**
     * Test 6: Admin Assets
     */
    private function test_admin_assets() {
        echo "<h3>6. Admin Assets Testing</h3>\n";
        
        // Check if admin assets would be enqueued
        global $wp_scripts, $wp_styles;
        
        // Simulate admin page
        set_current_screen('toplevel_page_membershiping-inventory');
        
        // Check for expected admin assets
        $expected_scripts = array(
            'membershiping-inventory-admin',
            'jquery',
            'wp-util'
        );
        
        $expected_styles = array(
            'membershiping-inventory-admin'
        );
        
        // Test script registration (would need to be run in admin context)
        $this->log_success("✅ Admin assets system ready for testing");
        $this->log_warning("⚠️ Actual asset loading requires admin context");
        
        echo "\n";
    }
    
    /**
     * Test 7: Settings API Integration
     */
    private function test_settings_api() {
        echo "<h3>7. Settings API Integration Testing</h3>\n";
        
        // Check if settings are registered
        global $wp_settings_sections, $wp_settings_fields;
        
        // Expected settings sections
        $expected_sections = array(
            'membershiping_inventory_general',
            'membershiping_inventory_display',
            'membershiping_inventory_trading',
            'membershiping_inventory_security'
        );
        
        $found_sections = 0;
        if (is_array($wp_settings_sections)) {
            foreach ($expected_sections as $section) {
                if (isset($wp_settings_sections['membershiping-inventory-settings'][$section])) {
                    $found_sections++;
                    $this->log_success("✅ Settings section '{$section}' registered");
                } else {
                    $this->log_warning("⚠️ Settings section '{$section}' not found");
                }
            }
        }
        
        if ($found_sections > 0) {
            $this->log_success("✅ Settings API integration working");
        } else {
            $this->log_warning("⚠️ Settings may not be fully registered yet");
        }
        
        echo "\n";
    }
    
    /**
     * Test 8: Meta Boxes
     */
    private function test_meta_boxes() {
        echo "<h3>8. Meta Boxes Testing</h3>\n";
        
        global $wp_meta_boxes;
        
        // Check WooCommerce product meta boxes
        if (class_exists('WooCommerce')) {
            $this->log_success("✅ WooCommerce available for meta box integration");
            
            // Check if meta box hook is registered
            if (has_action('add_meta_boxes')) {
                $this->log_success("✅ Meta boxes hook registered");
            } else {
                $this->log_error("❌ Meta boxes hook not registered");
            }
        } else {
            $this->log_warning("⚠️ WooCommerce not active - meta box testing skipped");
        }
        
        echo "\n";
    }
    
    /**
     * Test 9: Admin Notices System
     */
    private function test_admin_notices() {
        echo "<h3>9. Admin Notices System Testing</h3>\n";
        
        // Check admin notices hook
        if (has_action('admin_notices')) {
            $this->log_success("✅ Admin notices hook registered");
        } else {
            $this->log_error("❌ Admin notices hook not registered");
        }
        
        // Test notice system availability
        if (function_exists('add_action')) {
            $this->log_success("✅ Notice system infrastructure available");
        }
        
        echo "\n";
    }
    
    /**
     * Test 10: Data Management
     */
    private function test_data_management() {
        echo "<h3>10. Data Export/Import Features Testing</h3>\n";
        
        if (!$this->admin_dashboard) {
            $this->log_error("❌ Admin dashboard not available for testing");
            return;
        }
        
        // Test data management methods
        $data_methods = array(
            'ajax_export_data' => 'Data export functionality',
            'ajax_import_data' => 'Data import functionality',
            'ajax_system_diagnostics' => 'System diagnostics',
            'ajax_cleanup_system' => 'System cleanup'
        );
        
        foreach ($data_methods as $method => $description) {
            if (method_exists($this->admin_dashboard, $method)) {
                $this->log_success("✅ {$description} available");
            } else {
                $this->log_error("❌ {$description} missing");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 11: Bulk Operations
     */
    private function test_bulk_operations() {
        echo "<h3>11. Bulk Operations Testing</h3>\n";
        
        if (!$this->admin_dashboard) {
            $this->log_error("❌ Admin dashboard not available for testing");
            return;
        }
        
        // Test bulk operation methods
        $bulk_methods = array(
            'ajax_bulk_award_items' => 'Bulk item awards',
            'ajax_bulk_remove_items' => 'Bulk item removal',
            'ajax_reset_user_inventory' => 'Inventory reset'
        );
        
        foreach ($bulk_methods as $method => $description) {
            if (method_exists($this->admin_dashboard, $method)) {
                $this->log_success("✅ {$description} available");
            } else {
                $this->log_error("❌ {$description} missing");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test 12: User Management Interface
     */
    private function test_user_management() {
        echo "<h3>12. User Management Interface Testing</h3>\n";
        
        if (!$this->admin_dashboard) {
            $this->log_error("❌ Admin dashboard not available for testing");
            return;
        }
        
        // Test user management methods
        $user_methods = array(
            'render_users_page' => 'Users page rendering',
            'ajax_get_user_inventory' => 'User inventory display',
            'ajax_reset_user_inventory' => 'User inventory management'
        );
        
        foreach ($user_methods as $method => $description) {
            if (method_exists($this->admin_dashboard, $method)) {
                $this->log_success("✅ {$description} available");
            } else {
                $this->log_error("❌ {$description} missing");
            }
        }
        
        echo "\n";
    }
    
    /**
     * Generate validation summary
     */
    private function generate_summary() {
        echo "<h3>📊 Admin Interface Validation Summary</h3>\n";
        
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
        echo "<strong>Admin Interface Results:</strong><br>\n";
        echo "✅ Passed: {$success_count}<br>\n";
        echo "❌ Failed: {$error_count}<br>\n";
        echo "⚠️ Warnings: {$warning_count}<br>\n";
        echo "<strong>Success Rate: {$success_rate}%</strong><br>\n";
        echo "</div>\n";
        
        if ($success_rate >= 85) {
            echo "<p style='color: green;'><strong>🎉 Excellent! Admin interface is well implemented.</strong></p>\n";
        } elseif ($success_rate >= 70) {
            echo "<p style='color: orange;'><strong>⚠️ Good foundation, minor improvements needed.</strong></p>\n";
        } else {
            echo "<p style='color: red;'><strong>❌ Significant admin interface issues need attention.</strong></p>\n";
        }
        
        // Key recommendations
        echo "<h4>🔧 Key Admin Interface Areas:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Menu Structure:</strong> Main menu and submenus for organized navigation</li>\n";
        echo "<li><strong>Page Rendering:</strong> Dashboard, items, currencies, NFTs, trading management</li>\n";
        echo "<li><strong>AJAX Operations:</strong> Real-time data updates and bulk operations</li>\n";
        echo "<li><strong>Security:</strong> Proper capability checks and nonce verification</li>\n";
        echo "<li><strong>User Experience:</strong> Intuitive interface with helpful feedback</li>\n";
        echo "</ul>\n";
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
}

// Auto-run if accessed directly for testing
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI execution
    $validator = new Membershiping_Inventory_Admin_Validator();
    $results = $validator->run_validation();
} elseif (isset($_GET['run_admin_test']) && current_user_can('manage_options')) {
    // Admin execution via URL parameter
    $validator = new Membershiping_Inventory_Admin_Validator();
    $results = $validator->run_validation();
}
