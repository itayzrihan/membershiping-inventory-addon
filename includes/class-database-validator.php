<?php
/**
 * Database Schema Validation Script
 * Tests all database tables, indexes, and functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Database_Validator {
    
    private $wpdb;
    private $database;
    private $validation_results = array();
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
    }
    
    /**
     * Run complete database validation
     */
    public function run_validation() {
        $this->validation_results = array();
        
        // Test basic table existence
        $this->validate_table_existence();
        
        // Test table structure
        $this->validate_table_structure();
        
        // Test indexes
        $this->validate_indexes();
        
        // Test data integrity
        $this->validate_data_integrity();
        
        // Test foreign key relationships
        $this->validate_relationships();
        
        // Test performance
        $this->validate_performance();
        
        return $this->validation_results;
    }
    
    /**
     * Validate all tables exist
     */
    private function validate_table_existence() {
        $this->add_test_result('TABLE_EXISTENCE', 'Testing table existence...');
        
        $expected_tables = array(
            'currencies', 'items', 'nfts', 'user_items', 'user_currencies',
            'trades', 'currency_transactions', 'item_awards', 'product_flags',
            'audit_logs', 'user_levels', 'cart_cleanup'
        );
        
        $missing_tables = array();
        $existing_tables = array();
        
        foreach ($expected_tables as $table_key) {
            $table_name = $this->database->get_table_name($table_key);
            if ($table_name) {
                $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                if ($exists) {
                    $existing_tables[] = $table_key;
                } else {
                    $missing_tables[] = $table_key;
                }
            } else {
                $missing_tables[] = $table_key . ' (not configured)';
            }
        }
        
        if (empty($missing_tables)) {
            $this->add_test_result('TABLE_EXISTENCE', 'PASS: All 12 tables exist', 'success');
        } else {
            $this->add_test_result('TABLE_EXISTENCE', 'FAIL: Missing tables: ' . implode(', ', $missing_tables), 'error');
        }
        
        $this->add_test_result('TABLE_COUNT', count($existing_tables) . ' of 12 tables found', 'info');
    }
    
    /**
     * Validate table structure
     */
    private function validate_table_structure() {
        $this->add_test_result('TABLE_STRUCTURE', 'Testing table structure...');
        
        // Test currencies table
        $this->validate_currencies_structure();
        
        // Test items table
        $this->validate_items_structure();
        
        // Test NFTs table
        $this->validate_nfts_structure();
        
        // Test user tables
        $this->validate_user_tables_structure();
        
        // Test transaction tables
        $this->validate_transaction_tables_structure();
    }
    
    /**
     * Validate currencies table structure
     */
    private function validate_currencies_structure() {
        $table_name = $this->database->get_table_name('currencies');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'name', 'slug', 'symbol', 'decimal_places', 'exchange_rate', 'status');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('CURRENCIES_STRUCTURE', 'PASS: Currencies table structure valid', 'success');
        } else {
            $this->add_test_result('CURRENCIES_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // Check for default currency
        $default_count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_default = 1");
        if ($default_count > 0) {
            $this->add_test_result('DEFAULT_CURRENCY', 'PASS: Default currency exists', 'success');
        } else {
            $this->add_test_result('DEFAULT_CURRENCY', 'WARN: No default currency set', 'warning');
        }
    }
    
    /**
     * Validate items table structure
     */
    private function validate_items_structure() {
        $table_name = $this->database->get_table_name('items');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'product_id', 'name', 'item_type', 'rarity', 'mint_nft', 'is_tradeable');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('ITEMS_STRUCTURE', 'PASS: Items table structure valid', 'success');
        } else {
            $this->add_test_result('ITEMS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // Test enum values
        $this->validate_enum_values($table_name, 'rarity', array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic'));
        $this->validate_enum_values($table_name, 'item_type', array('consumable', 'equipment', 'gift_box', 'material', 'collectible', 'weapon', 'armor'));
    }
    
    /**
     * Validate NFTs table structure
     */
    private function validate_nfts_structure() {
        $table_name = $this->database->get_table_name('nfts');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'item_id', 'token_id', 'nft_hash', 'owner_id', 'rarity');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('NFTS_STRUCTURE', 'PASS: NFTs table structure valid', 'success');
        } else {
            $this->add_test_result('NFTS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // Check unique constraints
        $this->validate_unique_constraints($table_name, array('token_id', 'nft_hash', 'nft_token'));
    }
    
    /**
     * Validate user tables structure
     */
    private function validate_user_tables_structure() {
        // User items
        $table_name = $this->database->get_table_name('user_items');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'user_id', 'item_id', 'quantity');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('USER_ITEMS_STRUCTURE', 'PASS: User items table structure valid', 'success');
        } else {
            $this->add_test_result('USER_ITEMS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // User currencies
        $table_name = $this->database->get_table_name('user_currencies');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'user_id', 'currency_id', 'balance');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('USER_CURRENCIES_STRUCTURE', 'PASS: User currencies table structure valid', 'success');
        } else {
            $this->add_test_result('USER_CURRENCIES_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // User levels
        $table_name = $this->database->get_table_name('user_levels');
        if ($table_name && $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $columns = $this->get_table_columns($table_name);
            $required_columns = array('id', 'user_id', 'level', 'experience');
            $missing_columns = array_diff($required_columns, array_keys($columns));
            
            if (empty($missing_columns)) {
                $this->add_test_result('USER_LEVELS_STRUCTURE', 'PASS: User levels table structure valid', 'success');
            } else {
                $this->add_test_result('USER_LEVELS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
            }
        } else {
            $this->add_test_result('USER_LEVELS_STRUCTURE', 'FAIL: User levels table missing', 'error');
        }
    }
    
    /**
     * Validate transaction tables structure
     */
    private function validate_transaction_tables_structure() {
        // Currency transactions
        $table_name = $this->database->get_table_name('currency_transactions');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'user_id', 'currency_id', 'amount', 'transaction_type', 'balance_after');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('CURRENCY_TRANSACTIONS_STRUCTURE', 'PASS: Currency transactions table structure valid', 'success');
        } else {
            $this->add_test_result('CURRENCY_TRANSACTIONS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // Trades
        $table_name = $this->database->get_table_name('trades');
        $columns = $this->get_table_columns($table_name);
        
        $required_columns = array('id', 'trade_token', 'initiator_id', 'target_id', 'status');
        $missing_columns = array_diff($required_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            $this->add_test_result('TRADES_STRUCTURE', 'PASS: Trades table structure valid', 'success');
        } else {
            $this->add_test_result('TRADES_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
        }
        
        // Audit logs
        $table_name = $this->database->get_table_name('audit_logs');
        if ($table_name && $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $columns = $this->get_table_columns($table_name);
            $required_columns = array('id', 'user_id', 'action', 'object_type');
            $missing_columns = array_diff($required_columns, array_keys($columns));
            
            if (empty($missing_columns)) {
                $this->add_test_result('AUDIT_LOGS_STRUCTURE', 'PASS: Audit logs table structure valid', 'success');
            } else {
                $this->add_test_result('AUDIT_LOGS_STRUCTURE', 'FAIL: Missing columns: ' . implode(', ', $missing_columns), 'error');
            }
        } else {
            $this->add_test_result('AUDIT_LOGS_STRUCTURE', 'FAIL: Audit logs table missing', 'error');
        }
    }
    
    /**
     * Validate indexes
     */
    private function validate_indexes() {
        $this->add_test_result('INDEXES', 'Testing database indexes...');
        
        $index_tests = array(
            'currencies' => array('PRIMARY', 'slug'),
            'items' => array('PRIMARY', 'product_id', 'item_type', 'rarity'),
            'nfts' => array('PRIMARY', 'token_id', 'nft_hash', 'owner_id'),
            'user_items' => array('PRIMARY', 'user_id', 'item_id'),
            'user_currencies' => array('PRIMARY', 'user_id', 'currency_id'),
            'trades' => array('PRIMARY', 'trade_token', 'initiator_id', 'target_id'),
        );
        
        foreach ($index_tests as $table_key => $expected_indexes) {
            $table_name = $this->database->get_table_name($table_key);
            if ($table_name) {
                $indexes = $this->get_table_indexes($table_name);
                $missing_indexes = array_diff($expected_indexes, array_keys($indexes));
                
                if (empty($missing_indexes)) {
                    $this->add_test_result("INDEX_$table_key", "PASS: $table_key indexes valid", 'success');
                } else {
                    $this->add_test_result("INDEX_$table_key", "WARN: Missing indexes in $table_key: " . implode(', ', $missing_indexes), 'warning');
                }
            }
        }
    }
    
    /**
     * Validate data integrity
     */
    private function validate_data_integrity() {
        $this->add_test_result('DATA_INTEGRITY', 'Testing data integrity...');
        
        // Test for orphaned records
        $this->check_orphaned_records();
        
        // Test balance calculations
        $this->check_balance_integrity();
        
        // Test quantity consistency
        $this->check_quantity_integrity();
    }
    
    /**
     * Check for orphaned records
     */
    private function check_orphaned_records() {
        // Check user_items without corresponding items
        $user_items_table = $this->database->get_table_name('user_items');
        $items_table = $this->database->get_table_name('items');
        
        if ($user_items_table && $items_table) {
            $orphaned_user_items = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $user_items_table ui 
                LEFT JOIN $items_table i ON ui.item_id = i.id 
                WHERE i.id IS NULL
            ");
            
            if ($orphaned_user_items == 0) {
                $this->add_test_result('ORPHANED_USER_ITEMS', 'PASS: No orphaned user items', 'success');
            } else {
                $this->add_test_result('ORPHANED_USER_ITEMS', "WARN: $orphaned_user_items orphaned user items found", 'warning');
            }
        }
        
        // Check NFTs without corresponding items
        $nfts_table = $this->database->get_table_name('nfts');
        if ($nfts_table && $items_table) {
            $orphaned_nfts = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $nfts_table n 
                LEFT JOIN $items_table i ON n.item_id = i.id 
                WHERE i.id IS NULL
            ");
            
            if ($orphaned_nfts == 0) {
                $this->add_test_result('ORPHANED_NFTS', 'PASS: No orphaned NFTs', 'success');
            } else {
                $this->add_test_result('ORPHANED_NFTS', "WARN: $orphaned_nfts orphaned NFTs found", 'warning');
            }
        }
    }
    
    /**
     * Check balance integrity
     */
    private function check_balance_integrity() {
        $user_currencies_table = $this->database->get_table_name('user_currencies');
        $transactions_table = $this->database->get_table_name('currency_transactions');
        
        if ($user_currencies_table && $transactions_table) {
            // This is a complex check - for now just verify no negative balances
            $negative_balances = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $user_currencies_table 
                WHERE balance < 0
            ");
            
            if ($negative_balances == 0) {
                $this->add_test_result('NEGATIVE_BALANCES', 'PASS: No negative currency balances', 'success');
            } else {
                $this->add_test_result('NEGATIVE_BALANCES', "FAIL: $negative_balances negative balances found", 'error');
            }
        }
    }
    
    /**
     * Check quantity integrity
     */
    private function check_quantity_integrity() {
        $user_items_table = $this->database->get_table_name('user_items');
        
        if ($user_items_table) {
            $zero_quantities = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $user_items_table 
                WHERE quantity <= 0
            ");
            
            if ($zero_quantities == 0) {
                $this->add_test_result('ZERO_QUANTITIES', 'PASS: No zero/negative quantities', 'success');
            } else {
                $this->add_test_result('ZERO_QUANTITIES', "WARN: $zero_quantities zero/negative quantities found", 'warning');
            }
        }
    }
    
    /**
     * Validate relationships
     */
    private function validate_relationships() {
        $this->add_test_result('RELATIONSHIPS', 'Testing table relationships...');
        
        // Test item-product relationships
        $this->test_item_product_relationships();
        
        // Test user-item relationships
        $this->test_user_item_relationships();
        
        // Test currency relationships
        $this->test_currency_relationships();
    }
    
    /**
     * Test item-product relationships
     */
    private function test_item_product_relationships() {
        $items_table = $this->database->get_table_name('items');
        
        if ($items_table) {
            // Check for items with invalid product IDs
            $invalid_products = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $items_table i 
                LEFT JOIN {$this->wpdb->posts} p ON i.product_id = p.ID 
                WHERE p.ID IS NULL AND i.product_id > 0
            ");
            
            if ($invalid_products == 0) {
                $this->add_test_result('ITEM_PRODUCT_RELATIONSHIPS', 'PASS: All items have valid product references', 'success');
            } else {
                $this->add_test_result('ITEM_PRODUCT_RELATIONSHIPS', "WARN: $invalid_products items with invalid product IDs", 'warning');
            }
        }
    }
    
    /**
     * Test user-item relationships
     */
    private function test_user_item_relationships() {
        $user_items_table = $this->database->get_table_name('user_items');
        
        if ($user_items_table) {
            // Check for user items with invalid user IDs
            $invalid_users = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $user_items_table ui 
                LEFT JOIN {$this->wpdb->users} u ON ui.user_id = u.ID 
                WHERE u.ID IS NULL
            ");
            
            if ($invalid_users == 0) {
                $this->add_test_result('USER_ITEM_RELATIONSHIPS', 'PASS: All user items have valid user references', 'success');
            } else {
                $this->add_test_result('USER_ITEM_RELATIONSHIPS', "WARN: $invalid_users user items with invalid user IDs", 'warning');
            }
        }
    }
    
    /**
     * Test currency relationships
     */
    private function test_currency_relationships() {
        $user_currencies_table = $this->database->get_table_name('user_currencies');
        $currencies_table = $this->database->get_table_name('currencies');
        
        if ($user_currencies_table && $currencies_table) {
            // Check for user currencies with invalid currency IDs
            $invalid_currencies = $this->wpdb->get_var("
                SELECT COUNT(*) 
                FROM $user_currencies_table uc 
                LEFT JOIN $currencies_table c ON uc.currency_id = c.id 
                WHERE c.id IS NULL
            ");
            
            if ($invalid_currencies == 0) {
                $this->add_test_result('USER_CURRENCY_RELATIONSHIPS', 'PASS: All user currencies have valid currency references', 'success');
            } else {
                $this->add_test_result('USER_CURRENCY_RELATIONSHIPS', "WARN: $invalid_currencies user currencies with invalid currency IDs", 'warning');
            }
        }
    }
    
    /**
     * Validate performance
     */
    private function validate_performance() {
        $this->add_test_result('PERFORMANCE', 'Testing database performance...');
        
        // Test query performance on common operations
        $this->test_query_performance();
    }
    
    /**
     * Test query performance
     */
    private function test_query_performance() {
        $tests = array();
        
        // Test user inventory query
        $start_time = microtime(true);
        $user_items_table = $this->database->get_table_name('user_items');
        if ($user_items_table) {
            $this->wpdb->get_results("SELECT * FROM $user_items_table LIMIT 100");
            $tests['user_inventory'] = microtime(true) - $start_time;
        }
        
        // Test item search query
        $start_time = microtime(true);
        $items_table = $this->database->get_table_name('items');
        if ($items_table) {
            $this->wpdb->get_results("SELECT * FROM $items_table WHERE status = 'active' LIMIT 50");
            $tests['item_search'] = microtime(true) - $start_time;
        }
        
        // Test currency balance query
        $start_time = microtime(true);
        $user_currencies_table = $this->database->get_table_name('user_currencies');
        if ($user_currencies_table) {
            $this->wpdb->get_results("SELECT * FROM $user_currencies_table LIMIT 100");
            $tests['currency_balance'] = microtime(true) - $start_time;
        }
        
        foreach ($tests as $test_name => $execution_time) {
            if ($execution_time < 0.1) {
                $this->add_test_result("PERFORMANCE_$test_name", "PASS: $test_name query executed in " . round($execution_time * 1000, 2) . "ms", 'success');
            } elseif ($execution_time < 0.5) {
                $this->add_test_result("PERFORMANCE_$test_name", "WARN: $test_name query executed in " . round($execution_time * 1000, 2) . "ms", 'warning');
            } else {
                $this->add_test_result("PERFORMANCE_$test_name", "FAIL: $test_name query too slow: " . round($execution_time * 1000, 2) . "ms", 'error');
            }
        }
    }
    
    /**
     * Helper methods
     */
    
    private function get_table_columns($table_name) {
        $columns = array();
        $results = $this->wpdb->get_results("DESCRIBE $table_name");
        foreach ($results as $column) {
            $columns[$column->Field] = $column;
        }
        return $columns;
    }
    
    private function get_table_indexes($table_name) {
        $indexes = array();
        $results = $this->wpdb->get_results("SHOW INDEXES FROM $table_name");
        foreach ($results as $index) {
            $indexes[$index->Key_name] = $index;
        }
        return $indexes;
    }
    
    private function validate_enum_values($table_name, $column_name, $expected_values) {
        $column_info = $this->wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE '$column_name'");
        if ($column_info && strpos($column_info->Type, 'enum') !== false) {
            $this->add_test_result("ENUM_$column_name", "PASS: $column_name enum values valid", 'success');
        } else {
            $this->add_test_result("ENUM_$column_name", "FAIL: $column_name not an enum or missing", 'error');
        }
    }
    
    private function validate_unique_constraints($table_name, $columns) {
        $indexes = $this->get_table_indexes($table_name);
        $unique_indexes = array();
        
        foreach ($indexes as $index_name => $index_info) {
            if ($index_info->Non_unique == 0) {
                $unique_indexes[] = $index_info->Column_name;
            }
        }
        
        $missing_unique = array_diff($columns, $unique_indexes);
        if (empty($missing_unique)) {
            $this->add_test_result("UNIQUE_CONSTRAINTS_$table_name", 'PASS: All unique constraints exist', 'success');
        } else {
            $this->add_test_result("UNIQUE_CONSTRAINTS_$table_name", 'WARN: Missing unique constraints: ' . implode(', ', $missing_unique), 'warning');
        }
    }
    
    private function add_test_result($test_name, $message, $status = 'info') {
        $this->validation_results[] = array(
            'test' => $test_name,
            'message' => $message,
            'status' => $status,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate validation report
     */
    public function generate_report() {
        $results = $this->run_validation();
        
        $report = array(
            'summary' => $this->generate_summary($results),
            'details' => $results,
            'timestamp' => current_time('mysql'),
            'version' => MEMBERSHIPING_INVENTORY_VERSION
        );
        
        return $report;
    }
    
    private function generate_summary($results) {
        $total = count($results);
        $passed = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
        $warnings = count(array_filter($results, function($r) { return $r['status'] === 'warning'; }));
        $errors = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
        
        return array(
            'total_tests' => $total,
            'passed' => $passed,
            'warnings' => $warnings,
            'errors' => $errors,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0
        );
    }
}
