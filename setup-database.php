<?php
/**
 * Database setup and table creation for MYTX addon
 * Run this once to ensure all tables are properly created
 */

// Only run if accessed from WordPress admin
if (!defined('ABSPATH')) {
    die('Access denied');
}

// Require the database class
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

echo '<h2>Membershiping Inventory Database Setup</h2>';

try {
    // Create database instance
    $database = new Membershiping_Inventory_Database();
    
    echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;">Creating database tables...</div>';
    
    // Force table creation
    $database->create_tables();
    
    echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;">✅ Database tables created successfully!</div>';
    
    // Verify tables exist
    $tables = $database->get_all_tables();
    global $wpdb;
    
    echo '<h3>Table Verification:</h3>';
    echo '<table style="border-collapse: collapse; width: 100%; margin-top: 10px;">';
    echo '<tr style="background: #f8f9fa;"><th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Table Key</th><th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Table Name</th><th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Status</th></tr>';
    
    $all_good = true;
    foreach ($tables as $key => $table_name) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $status_color = $exists ? '#155724' : '#721c24';
        $status_icon = $exists ? '✅' : '❌';
        $status_text = $exists ? 'Created' : 'Missing';
        
        if (!$exists) {
            $all_good = false;
        }
        
        echo '<tr>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px;">' . esc_html($key) . '</td>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px; font-family: monospace;">' . esc_html($table_name) . '</td>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px; color: ' . $status_color . ';">' . $status_icon . ' ' . $status_text . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    if ($all_good) {
        echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;">🎉 All tables created successfully! The MYTX addon should now be fully functional.</div>';
    } else {
        echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0;">⚠️ Some tables were not created. Check WordPress error logs for details.</div>';
    }
    
    // Add some sample data
    echo '<h3>Adding Sample Data:</h3>';
    
    // Add sample currency
    $currency_table = $database->get_table('currencies');
    $existing_currency = $wpdb->get_var("SELECT COUNT(*) FROM $currency_table");
    
    if ($existing_currency == 0) {
        $wpdb->insert(
            $currency_table,
            array(
                'name' => 'Gold Coins',
                'code' => 'GOLD',
                'symbol' => '🪙',
                'type' => 'virtual',
                'status' => 'active',
                'description' => 'Primary virtual currency for the membership system',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        $wpdb->insert(
            $currency_table,
            array(
                'name' => 'Premium Gems',
                'code' => 'GEMS',
                'symbol' => '💎',
                'type' => 'premium',
                'status' => 'active',
                'description' => 'Premium currency for special features',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;">✅ Sample currencies added</div>';
    } else {
        echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0;">ℹ️ Currencies already exist, skipping sample data</div>';
    }
    
    // Add sample items
    $items_table = $database->get_table('items');
    $existing_items = $wpdb->get_var("SELECT COUNT(*) FROM $items_table");
    
    if ($existing_items == 0) {
        $wpdb->insert(
            $items_table,
            array(
                'name' => 'Welcome Package',
                'description' => 'A special welcome package for new members',
                'type' => 'virtual',
                'value' => 10.00,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        $wpdb->insert(
            $items_table,
            array(
                'name' => 'Premium Badge',
                'description' => 'Exclusive badge for premium members',
                'type' => 'collectible',
                'value' => 50.00,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;">✅ Sample items added</div>';
    } else {
        echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0;">ℹ️ Items already exist, skipping sample data</div>';
    }
    
} catch (Exception $e) {
    echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0;">❌ Error: ' . esc_html($e->getMessage()) . '</div>';
}

echo '<h3>Next Steps:</h3>';
echo '<ul>';
echo '<li>Visit the <strong>Inventory</strong> menu in your WordPress admin to access the MYTX addon</li>';
echo '<li>Create items and currencies from the respective management pages</li>';
echo '<li>Assign inventory items to users from their profile pages</li>';
echo '<li>Check the Reports section to monitor system usage</li>';
echo '</ul>';

echo '<div style="background: #e2e3e5; border: 1px solid #d1d3d4; color: #383d41; padding: 10px; margin: 20px 0;">';
echo '<strong>Note:</strong> You can safely delete this setup file after running it once.';
echo '</div>';
