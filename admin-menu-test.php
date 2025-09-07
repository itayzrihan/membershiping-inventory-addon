<?php
/**
 * Admin Menu Test Script
 * 
 * Place this file in your WordPress root directory and visit:
 * yourdomain.com/admin-menu-test.php
 * 
 * This will help diagnose why the admin menu isn't appearing.
 */

// Include WordPress
require_once 'wp-config.php';
require_once 'wp-load.php';

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in()) {
    die('Please log in to WordPress admin first, then access this page.');
}

if (!current_user_can('manage_options')) {
    die('You need administrator privileges to run this test.');
}

echo "<h1>Membershiping Inventory Admin Menu Debug</h1>";

// Check if plugin file exists
$plugin_file = WP_PLUGIN_DIR . '/membershiping-inventory/membershiping-inventory.php';
$plugin_exists = file_exists($plugin_file);

echo "<h2>Plugin File Check</h2>";
echo "Plugin file path: " . $plugin_file . "<br>";
echo "Plugin file exists: " . ($plugin_exists ? 'YES' : 'NO') . "<br>";

if (!$plugin_exists) {
    echo "<strong>Error: Plugin file not found. Make sure the plugin is uploaded to the correct directory.</strong><br>";
}

// Check if plugin is active
$active_plugins = get_option('active_plugins', array());
$plugin_active = in_array('membershiping-inventory/membershiping-inventory.php', $active_plugins);

echo "<h2>Plugin Activation Check</h2>";
echo "Plugin is active: " . ($plugin_active ? 'YES' : 'NO') . "<br>";

if (!$plugin_active) {
    echo "<strong>Error: Plugin is not activated. Please activate it in WordPress admin.</strong><br>";
} else {
    echo "Active plugins:<br>";
    foreach ($active_plugins as $plugin) {
        echo "- " . $plugin . "<br>";
    }
}

// Check if main class exists
echo "<h2>Class Existence Check</h2>";
echo "Membershiping_Inventory class exists: " . (class_exists('Membershiping_Inventory') ? 'YES' : 'NO') . "<br>";
echo "Membershiping_Inventory_Admin class exists: " . (class_exists('Membershiping_Inventory_Admin') ? 'YES' : 'NO') . "<br>";

// Check global variable
echo "<h2>Global Instance Check</h2>";
global $membershiping_inventory;
echo "Global \$membershiping_inventory exists: " . (isset($membershiping_inventory) ? 'YES' : 'NO') . "<br>";

if (isset($membershiping_inventory)) {
    echo "Instance type: " . get_class($membershiping_inventory) . "<br>";
    
    // Check if admin property exists
    if (property_exists($membershiping_inventory, 'admin')) {
        echo "Admin property exists: YES<br>";
        echo "Admin property value: " . (isset($membershiping_inventory->admin) ? get_class($membershiping_inventory->admin) : 'NULL') . "<br>";
    } else {
        echo "Admin property exists: NO<br>";
    }
}

// Check WordPress admin menu global
echo "<h2>WordPress Menu Check</h2>";
global $menu, $submenu;

echo "WordPress \$menu global exists: " . (isset($menu) ? 'YES' : 'NO') . "<br>";

if (isset($menu)) {
    echo "Searching for 'membershiping-inventory' in admin menu...<br>";
    $found_menu = false;
    
    foreach ($menu as $menu_item) {
        if (isset($menu_item[2]) && $menu_item[2] === 'membershiping-inventory') {
            $found_menu = true;
            echo "<strong>Found menu item:</strong><br>";
            echo "- Title: " . $menu_item[0] . "<br>";
            echo "- Capability: " . $menu_item[1] . "<br>";
            echo "- Slug: " . $menu_item[2] . "<br>";
            echo "- Icon: " . $menu_item[6] . "<br>";
            break;
        }
    }
    
    if (!$found_menu) {
        echo "<strong>Menu item 'membershiping-inventory' NOT FOUND in WordPress menu array.</strong><br>";
        echo "This means add_menu_page() was not called or failed.<br>";
    }
}

// Check recent error logs
echo "<h2>Recent Error Logs</h2>";
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    echo "Error log path: " . $error_log_path . "<br>";
    $log_content = file_get_contents($error_log_path);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -50); // Last 50 lines
    
    echo "Recent error log entries containing 'Membershiping':<br>";
    echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: scroll;'>";
    foreach ($recent_lines as $line) {
        if (stripos($line, 'membershiping') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "Error log not found or not accessible.<br>";
}

// Check hooks
echo "<h2>Hook Registration Check</h2>";
global $wp_filter;

$admin_menu_hooks = isset($wp_filter['admin_menu']) ? $wp_filter['admin_menu'] : null;
if ($admin_menu_hooks) {
    echo "admin_menu hooks registered: " . count($admin_menu_hooks->callbacks) . "<br>";
    
    foreach ($admin_menu_hooks->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && 
                is_object($callback['function'][0]) && 
                get_class($callback['function'][0]) === 'Membershiping_Inventory_Admin') {
                echo "<strong>Found Membershiping_Inventory_Admin admin_menu hook at priority $priority</strong><br>";
            }
        }
    }
} else {
    echo "No admin_menu hooks found.<br>";
}

echo "<h2>Manual Hook Test</h2>";
echo "Attempting to manually trigger admin menu registration...<br>";

if (class_exists('Membershiping_Inventory_Admin')) {
    $test_admin = new Membershiping_Inventory_Admin();
    echo "Created test admin instance successfully.<br>";
    
    // Manually call the menu function
    if (method_exists($test_admin, 'add_admin_menus')) {
        $test_admin->add_admin_menus();
        echo "Called add_admin_menus() manually.<br>";
        
        // Check if menu was added
        if (isset($menu)) {
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === 'membershiping-inventory') {
                    echo "<strong>SUCCESS: Menu item found after manual call!</strong><br>";
                    break;
                }
            }
        }
    }
} else {
    echo "Cannot create test instance - class does not exist.<br>";
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Check the results above for any obvious issues</li>";
echo "<li>If the plugin file exists but classes don't, there might be a PHP syntax error</li>";
echo "<li>If classes exist but hooks aren't registered, there might be an initialization issue</li>";
echo "<li>If everything looks good but menu still doesn't appear, try deactivating and reactivating the plugin</li>";
echo "</ol>";
?>
