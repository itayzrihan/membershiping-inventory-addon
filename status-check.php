<?php
/**
 * Integration Status Check
 * Place this file in the plugin root and access via yoursite.com/wp-content/plugins/membershiping-inventory-addon/status-check.php
 */

// Security check
if (!defined('ABSPATH')) {
    // Load WordPress
    require_once('../../../wp-load.php');
}

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Membershiping Inventory Integration Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Membershiping Inventory Integration Status</h1>
    
    <h2>📋 Basic Checks</h2>
    
    <?php
    // Check WordPress
    echo '<div class="status success">✅ WordPress loaded successfully</div>';
    
    // Check WooCommerce
    if (class_exists('WooCommerce')) {
        echo '<div class="status success">✅ WooCommerce is active</div>';
    } else {
        echo '<div class="status error">❌ WooCommerce is not active</div>';
    }
    
    // Check Membershiping Core
    if (class_exists('Membershiping')) {
        echo '<div class="status success">✅ Membershiping Core is active</div>';
    } else {
        echo '<div class="status error">❌ Membershiping Core is not active</div>';
    }
    ?>
    
    <h2>🏗️ Class Availability</h2>
    
    <?php
    $classes = [
        'Membershiping_Inventory_Main' => 'Main plugin class',
        'Membershiping_Inventory_Flag_Awards' => 'Flag Awards class',
        'Membershiping_Inventory_Enhanced_WooCommerce_Integration' => 'Enhanced WooCommerce Integration',
        'Membershiping_Inventory_WooCommerce_Integration' => 'Basic WooCommerce Integration',
        'Membershiping_Inventory_Currencies' => 'Currencies class',
        'Membershiping_Inventory_Items' => 'Items class',
    ];
    
    foreach ($classes as $class => $description) {
        if (class_exists($class)) {
            echo '<div class="status success">✅ ' . $class . ' (' . $description . ')</div>';
        } else {
            echo '<div class="status error">❌ ' . $class . ' (' . $description . ')</div>';
        }
    }
    ?>
    
    <h2>🎯 Instance Check</h2>
    
    <?php
    global $membershiping_inventory;
    if (isset($membershiping_inventory)) {
        echo '<div class="status success">✅ Global plugin instance exists</div>';
        
        $components = [
            'flag_awards' => 'Flag Awards',
            'enhanced_woocommerce_integration' => 'Enhanced WooCommerce',
            'woocommerce_integration' => 'Basic WooCommerce',
            'currencies' => 'Currencies',
            'items' => 'Items'
        ];
        
        foreach ($components as $prop => $name) {
            if (isset($membershiping_inventory->$prop)) {
                echo '<div class="status success">✅ ' . $name . ' component initialized</div>';
            } else {
                echo '<div class="status warning">⚠️ ' . $name . ' component not initialized</div>';
            }
        }
    } else {
        echo '<div class="status error">❌ Global plugin instance not found</div>';
    }
    ?>
    
    <h2>🔧 Hook Registration Check</h2>
    
    <?php
    $hooks_to_check = [
        'woocommerce_product_options_general_product_data' => 'Product admin fields',
        'woocommerce_get_price_html' => 'Price display modification',
        'woocommerce_single_product_summary' => 'Product page display',
    ];
    
    global $wp_filter;
    
    foreach ($hooks_to_check as $hook => $description) {
        if (isset($wp_filter[$hook])) {
            $callbacks = [];
            foreach ($wp_filter[$hook]->callbacks as $priority => $functions) {
                foreach ($functions as $function) {
                    if (is_array($function['function']) && is_object($function['function'][0])) {
                        $class_name = get_class($function['function'][0]);
                        if (strpos($class_name, 'Membershiping_Inventory') !== false) {
                            $callbacks[] = $class_name . '::' . $function['function'][1] . ' (priority: ' . $priority . ')';
                        }
                    }
                }
            }
            
            if (!empty($callbacks)) {
                echo '<div class="status success">✅ ' . $hook . ' (' . $description . ')<br>';
                echo '<small>' . implode('<br>', $callbacks) . '</small></div>';
            } else {
                echo '<div class="status warning">⚠️ ' . $hook . ' (' . $description . ') - no Membershiping callbacks found</div>';
            }
        } else {
            echo '<div class="status error">❌ ' . $hook . ' (' . $description . ') - hook not found</div>';
        }
    }
    ?>
    
    <h2>📊 Recent Error Log</h2>
    
    <?php
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $recent_lines = array_slice($lines, -20); // Last 20 lines
        
        $membershiping_lines = [];
        foreach ($recent_lines as $line) {
            if (strpos($line, 'Membershiping') !== false) {
                $membershiping_lines[] = $line;
            }
        }
        
        if (!empty($membershiping_lines)) {
            echo '<div class="status info">';
            echo '<strong>Recent Membershiping log entries:</strong><br>';
            echo '<pre>' . implode("\n", array_slice($membershiping_lines, -10)) . '</pre>';
            echo '</div>';
        } else {
            echo '<div class="status warning">⚠️ No recent Membershiping log entries found</div>';
        }
    } else {
        echo '<div class="status info">ℹ️ Debug log not found (WP_DEBUG_LOG might be disabled)</div>';
    }
    ?>
    
    <h2>🧪 Quick Fix Suggestions</h2>
    
    <div class="status info">
        <strong>If admin fields are missing:</strong><br>
        1. Check that all classes are loading<br>
        2. Verify hook registration in error log<br>
        3. Try deactivating and reactivating the plugin<br>
        4. Check for plugin conflicts<br>
    </div>
    
    <div class="status info">
        <strong>If currency prices don't display:</strong><br>
        1. Ensure enhanced WooCommerce integration is initialized<br>
        2. Check that currencies exist in the system<br>
        3. Verify products have "Allow Currency Payment" enabled<br>
        4. Look for JavaScript console errors<br>
    </div>
    
</body>
</html>
