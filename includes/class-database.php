<?php
/**
 * Database management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Database {
    
    private $wpdb;
    private $tables = array();
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_table_names();
    }
    
    /**
     * Initialize table names
     */
    private function init_table_names() {
        $this->tables = array(
            'currencies' => $this->wpdb->prefix . 'membershiping_inventory_currencies',
            'items' => $this->wpdb->prefix . 'membershiping_inventory_items',
            'nfts' => $this->wpdb->prefix . 'membershiping_inventory_nfts',
            'user_items' => $this->wpdb->prefix . 'membershiping_inventory_user_items',
            'user_currencies' => $this->wpdb->prefix . 'membershiping_inventory_user_currencies',
            'trades' => $this->wpdb->prefix . 'membershiping_inventory_trades',
            'currency_transactions' => $this->wpdb->prefix . 'membershiping_inventory_currency_transactions',
            'item_awards' => $this->wpdb->prefix . 'membershiping_inventory_item_awards',
            'product_flags' => $this->wpdb->prefix . 'membershiping_inventory_product_flags',
            'audit_logs' => $this->wpdb->prefix . 'membershiping_inventory_audit_logs',
            'user_levels' => $this->wpdb->prefix . 'membershiping_inventory_user_levels',
            'cart_cleanup' => $this->wpdb->prefix . 'membershiping_inventory_cart_cleanup',
        );
    }
    
    /**
     * Get table name
     */
    public function get_table($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : false;
    }
    
    /**
     * Get all table names
     */
    public function get_all_tables() {
        return $this->tables;
    }
    
    /**
     * Initialize tables (check and create if needed)
     */
    public function init_tables() {
        // For V1, only create tables if they don't exist or during activation
        if ($this->should_create_tables()) {
            $this->create_tables();
            $this->create_default_data();
        }
    }
    
    /**
     * Check if tables should be created
     */
    private function should_create_tables() {
        // Always create during activation
        if (get_option('membershiping_inventory_disable_auto_db_init')) {
            $disable_time = get_option('membershiping_inventory_disable_auto_db_init');
            // If disabled less than 1 hour ago, skip auto creation
            if ((time() - $disable_time) < 3600) {
                return false;
            }
        }
        
        // Check if main tables exist
        $main_table = $this->tables['currencies'];
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$main_table'") === $main_table;
        
        return !$table_exists;
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // For V1, always ensure clean table creation
        $this->cleanup_existing_constraints();
        
        // Create each table in dependency order
        $tables_created = array();
        
        try {
            $tables_created[] = $this->create_currencies_table($charset_collate);
            $tables_created[] = $this->create_items_table($charset_collate);
            $tables_created[] = $this->create_nfts_table($charset_collate);
            $tables_created[] = $this->create_user_items_table($charset_collate);
            $tables_created[] = $this->create_user_currencies_table($charset_collate);
            $tables_created[] = $this->create_trades_table($charset_collate);
            $tables_created[] = $this->create_currency_transactions_table($charset_collate);
            $tables_created[] = $this->create_item_awards_table($charset_collate);
            $tables_created[] = $this->create_product_flags_table($charset_collate);
            $tables_created[] = $this->create_audit_logs_table($charset_collate);
            $tables_created[] = $this->create_user_levels_table($charset_collate);
            $tables_created[] = $this->create_cart_cleanup_table($charset_collate);
            
            error_log('Membershiping Inventory: All tables created successfully');
            
        } catch (Exception $e) {
            error_log('Membershiping Inventory: Table creation error - ' . $e->getMessage());
        }
        
        // Add foreign key constraints after all tables are created (with error handling)
        $this->add_foreign_key_constraints();
        
        // Update database version
        update_option('membershiping_inventory_db_version', MEMBERSHIPING_INVENTORY_VERSION);
        
        error_log('Membershiping Inventory: Database tables created successfully');
    }
    
    /**
     * Create currencies table
     */
    private function create_currencies_table($charset_collate) {
        $table_name = $this->tables['currencies'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            symbol varchar(10) NOT NULL,
            description text,
            icon varchar(255) DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            decimal_places tinyint(2) DEFAULT 2,
            exchange_rate decimal(10,4) DEFAULT 1.0000,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create items table
     */
    private function create_items_table($charset_collate) {
        $table_name = $this->tables['items'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            item_type enum('consumable','equipment','gift_box','material','collectible','weapon','armor') DEFAULT 'collectible',
            rarity enum('common','uncommon','rare','epic','legendary','mythic') DEFAULT 'common',
            base_image varchar(255) DEFAULT NULL,
            rarity_images longtext DEFAULT NULL,
            stats longtext DEFAULT NULL,
            requirements longtext DEFAULT NULL,
            is_tradeable tinyint(1) DEFAULT 1,
            is_consumable tinyint(1) DEFAULT 0,
            is_stackable tinyint(1) DEFAULT 1,
            max_stack_size int(11) DEFAULT 999,
            mint_nft tinyint(1) DEFAULT 0,
            nft_rarity_distribution longtext DEFAULT NULL,
            use_effect longtext DEFAULT NULL,
            gift_box_items longtext DEFAULT NULL,
            currency_prices longtext DEFAULT NULL,
            exclude_from_shop tinyint(1) DEFAULT 0,
            allow_currency_purchase tinyint(1) DEFAULT 1,
            quantity_limit int(11) DEFAULT NULL,
            current_quantity int(11) DEFAULT 0,
            level_requirement int(11) DEFAULT 1,
            status enum('active','inactive','draft') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY item_type (item_type),
            KEY rarity (rarity),
            KEY status (status),
            KEY mint_nft (mint_nft),
            KEY level_requirement (level_requirement),
            KEY item_lookup (item_type, rarity, status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create NFTs table
     */
    private function create_nfts_table($charset_collate) {
        $table_name = $this->tables['nfts'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_id mediumint(9) NOT NULL,
            token_id varchar(255) NOT NULL,
            nft_hash varchar(64) NOT NULL,
            nft_token varchar(128) NOT NULL,
            owner_id bigint(20) UNSIGNED DEFAULT NULL,
            original_owner_id bigint(20) UNSIGNED NOT NULL,
            rarity enum('common','uncommon','rare','epic','legendary','mythic') DEFAULT 'common',
            upgrade_level tinyint(3) DEFAULT 0,
            custom_stats longtext DEFAULT NULL,
            custom_image varchar(255) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            blockchain_data longtext DEFAULT NULL,
            is_tradeable tinyint(1) DEFAULT 1,
            is_burned tinyint(1) DEFAULT 0,
            mint_transaction_id varchar(255) DEFAULT NULL,
            burn_transaction_id varchar(255) DEFAULT NULL,
            minted_at datetime DEFAULT CURRENT_TIMESTAMP,
            burned_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_id (token_id),
            UNIQUE KEY nft_hash (nft_hash),
            UNIQUE KEY nft_token (nft_token),
            KEY item_id (item_id),
            KEY owner_id (owner_id),
            KEY original_owner_id (original_owner_id),
            KEY rarity (rarity),
            KEY is_burned (is_burned),
            KEY nft_lookup (item_id, owner_id, is_burned)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create user items table
     */
    private function create_user_items_table($charset_collate) {
        $table_name = $this->tables['user_items'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            item_id mediumint(9) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            acquired_method enum('purchase','awarded','trade','crafted','gift') DEFAULT 'awarded',
            acquired_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_item_unique (user_id, item_id),
            KEY user_id (user_id),
            KEY item_id (item_id),
            KEY acquired_method (acquired_method)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create user currencies table
     */
    private function create_user_currencies_table($charset_collate) {
        $table_name = $this->tables['user_currencies'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            currency_id mediumint(9) NOT NULL,
            balance decimal(15,4) NOT NULL DEFAULT 0.0000,
            total_earned decimal(15,4) NOT NULL DEFAULT 0.0000,
            total_spent decimal(15,4) NOT NULL DEFAULT 0.0000,
            last_transaction_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_currency_unique (user_id, currency_id),
            KEY user_id (user_id),
            KEY currency_id (currency_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create trades table
     */
    private function create_trades_table($charset_collate) {
        $table_name = $this->tables['trades'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trade_token varchar(64) NOT NULL,
            initiator_id bigint(20) UNSIGNED NOT NULL,
            target_id bigint(20) UNSIGNED NOT NULL,
            status enum('pending','accepted','declined','completed','cancelled','expired') DEFAULT 'pending',
            initiator_items longtext NOT NULL,
            initiator_currencies longtext DEFAULT NULL,
            target_items longtext NOT NULL,
            target_currencies longtext DEFAULT NULL,
            message text DEFAULT NULL,
            expires_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY trade_token (trade_token),
            KEY initiator_id (initiator_id),
            KEY target_id (target_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create currency transactions table
     */
    private function create_currency_transactions_table($charset_collate) {
        $table_name = $this->tables['currency_transactions'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            currency_id mediumint(9) NOT NULL,
            amount decimal(15,4) NOT NULL,
            transaction_type enum('earned','spent','traded','awarded','purchase') NOT NULL,
            reference_type enum('trade','purchase','award','admin') DEFAULT NULL,
            reference_id bigint(20) DEFAULT NULL,
            description text DEFAULT NULL,
            balance_after decimal(15,4) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY currency_id (currency_id),
            KEY transaction_type (transaction_type),
            KEY reference_type (reference_type),
            KEY reference_id (reference_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create item awards table
     */
    private function create_item_awards_table($charset_collate) {
        $table_name = $this->tables['item_awards'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            guest_email varchar(255) DEFAULT NULL,
            item_id mediumint(9) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            award_type enum('purchase','flag_award','admin','promotion','event') NOT NULL,
            source_reference varchar(255) DEFAULT NULL,
            nft_id bigint(20) DEFAULT NULL,
            processed tinyint(1) DEFAULT 0,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY guest_email (guest_email),
            KEY item_id (item_id),
            KEY award_type (award_type),
            KEY processed (processed),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create product flags table
     */
    private function create_product_flags_table($charset_collate) {
        $table_name = $this->tables['product_flags'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            flag_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_flag_unique (product_id, flag_id),
            KEY product_id (product_id),
            KEY flag_id (flag_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create audit logs table (replaces generic logs table)
     */
    private function create_audit_logs_table($charset_collate) {
        $table_name = $this->tables['audit_logs'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(100) NOT NULL,
            object_type enum('item','currency','nft','trade','user','system') NOT NULL,
            object_id bigint(20) DEFAULT NULL,
            old_values longtext DEFAULT NULL,
            new_values longtext DEFAULT NULL,
            quantity int(11) DEFAULT NULL,
            amount decimal(15,4) DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) DEFAULT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            session_id varchar(255) DEFAULT NULL,
            severity enum('low','medium','high','critical') DEFAULT 'medium',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY reference_type (reference_type),
            KEY reference_id (reference_id),
            KEY severity (severity),
            KEY created_at (created_at),
            KEY audit_lookup (user_id, action, object_type, created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create user levels table
     */
    private function create_user_levels_table($charset_collate) {
        $table_name = $this->tables['user_levels'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            level int(11) NOT NULL DEFAULT 1,
            experience bigint(20) NOT NULL DEFAULT 0,
            experience_to_next bigint(20) NOT NULL DEFAULT 100,
            total_experience bigint(20) NOT NULL DEFAULT 0,
            prestige_level int(11) NOT NULL DEFAULT 0,
            achievements longtext DEFAULT NULL,
            stats longtext DEFAULT NULL,
            last_level_up datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_level_unique (user_id),
            KEY level (level),
            KEY prestige_level (prestige_level),
            KEY total_experience (total_experience),
            KEY last_level_up (last_level_up)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create cart cleanup table
     */
    private function create_cart_cleanup_table($charset_collate) {
        $table_name = $this->tables['cart_cleanup'];
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            cart_id varchar(255) NOT NULL,
            cart_contents longtext NOT NULL,
            cart_total decimal(10,2) NOT NULL DEFAULT 0.00,
            cart_status enum('active','abandoned','recovered','cleaned','completed') DEFAULT 'active',
            abandonment_detected_at datetime DEFAULT NULL,
            last_reminder_sent datetime DEFAULT NULL,
            reminder_count int(11) NOT NULL DEFAULT 0,
            recovery_attempts int(11) NOT NULL DEFAULT 0,
            recovery_token varchar(255) DEFAULT NULL,
            cleaned_at datetime DEFAULT NULL,
            cleanup_reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cart_id (cart_id),
            KEY user_id (user_id),
            KEY cart_status (cart_status),
            KEY abandonment_detected_at (abandonment_detected_at),
            KEY last_reminder_sent (last_reminder_sent),
            KEY cleanup_status (cart_status, abandonment_detected_at),
            KEY recovery_token (recovery_token)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create default data
     */
    private function create_default_data() {
        $this->create_default_currencies();
    }
    
    /**
     * Create default currencies
     */
    private function create_default_currencies() {
        $currencies_table = $this->tables['currencies'];
        
        // Check if default currency exists
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $currencies_table WHERE slug = %s",
                'coins'
            )
        );
        
        if ($existing == 0) {
            // Create default "Coins" currency
            $this->wpdb->insert(
                $currencies_table,
                array(
                    'name' => 'Coins',
                    'slug' => 'coins',
                    'symbol' => '🪙',
                    'description' => 'Default currency for the inventory system',
                    'is_default' => 1,
                    'decimal_places' => 0,
                    'exchange_rate' => 1.0000,
                    'status' => 'active'
                ),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s')
            );
            
            // Create gems currency
            $this->wpdb->insert(
                $currencies_table,
                array(
                    'name' => 'Gems',
                    'slug' => 'gems',
                    'symbol' => '💎',
                    'description' => 'Premium currency for special items',
                    'is_default' => 0,
                    'decimal_places' => 0,
                    'exchange_rate' => 10.0000,
                    'status' => 'active'
                ),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s')
            );
            
            error_log('Membershiping Inventory: Default currencies created');
        }
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public function drop_tables() {
        foreach (array_reverse($this->tables) as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('membershiping_inventory_db_version');
    }
    
    /**
     * Check if tables exist and are up to date
     */
    public function check_tables() {
        $current_version = get_option('membershiping_inventory_db_version', '0.0.0');
        
        if (version_compare($current_version, MEMBERSHIPING_INVENTORY_VERSION, '<')) {
            $this->create_tables();
            return true;
        }
        
        // Check if all tables exist
        foreach ($this->tables as $table_key => $table_name) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $this->create_tables();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add foreign key constraints after all tables are created
     */
    private function add_foreign_key_constraints() {
        // Note: WordPress/MySQL often has issues with foreign keys due to table engine differences
        // We'll implement soft foreign keys through application logic instead
        // But we'll add the constraint definitions for documentation and potential future use
        
        $constraints = array(
            'fk_nfts_item_id' => "ALTER TABLE {$this->tables['nfts']} 
             ADD CONSTRAINT fk_nfts_item_id 
             FOREIGN KEY (item_id) REFERENCES {$this->tables['items']}(id) 
             ON DELETE CASCADE ON UPDATE CASCADE",
            
            'fk_user_items_item_id' => "ALTER TABLE {$this->tables['user_items']} 
             ADD CONSTRAINT fk_user_items_item_id 
             FOREIGN KEY (item_id) REFERENCES {$this->tables['items']}(id) 
             ON DELETE CASCADE ON UPDATE CASCADE",
            
            'fk_user_currencies_currency_id' => "ALTER TABLE {$this->tables['user_currencies']} 
             ADD CONSTRAINT fk_user_currencies_currency_id 
             FOREIGN KEY (currency_id) REFERENCES {$this->tables['currencies']}(id) 
             ON DELETE CASCADE ON UPDATE CASCADE",
            
            'fk_currency_transactions_currency_id' => "ALTER TABLE {$this->tables['currency_transactions']} 
             ADD CONSTRAINT fk_currency_transactions_currency_id 
             FOREIGN KEY (currency_id) REFERENCES {$this->tables['currencies']}(id) 
             ON DELETE CASCADE ON UPDATE CASCADE",
            
            'fk_item_awards_item_id' => "ALTER TABLE {$this->tables['item_awards']} 
             ADD CONSTRAINT fk_item_awards_item_id 
             FOREIGN KEY (item_id) REFERENCES {$this->tables['items']}(id) 
             ON DELETE CASCADE ON UPDATE CASCADE",
        );
        
        // Get existing constraints to avoid duplicates
        $existing_constraints = $this->get_existing_foreign_keys();
        
        // Attempt to add foreign keys, but don't fail if they can't be added
        foreach ($constraints as $constraint_name => $constraint_sql) {
            // Skip if constraint already exists
            if (in_array($constraint_name, $existing_constraints)) {
                continue;
            }
            
            $result = $this->wpdb->query($constraint_sql);
            if ($result === false) {
                error_log("Membershiping Inventory: Could not add foreign key constraint '{$constraint_name}' - using soft constraints instead");
            }
        }
    }
    
    /**
     * Get existing foreign key constraints
     */
    private function get_existing_foreign_keys() {
        $constraints = array();
        
        // Get all constraint names from information_schema
        $sql = "SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND CONSTRAINT_NAME LIKE 'fk_%'";
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($results) {
            foreach ($results as $row) {
                $constraints[] = $row['CONSTRAINT_NAME'];
            }
        }
        
        return $constraints;
    }
    
    /**
     * Clean up existing constraints before creating new ones (V1 clean slate)
     */
    private function cleanup_existing_constraints() {
        $constraints_to_clean = array(
            'fk_nfts_item_id',
            'fk_user_items_item_id', 
            'fk_user_currencies_currency_id',
            'fk_currency_transactions_currency_id',
            'fk_item_awards_item_id'
        );
        
        foreach ($constraints_to_clean as $constraint_name) {
            // Get table name for this constraint
            $table_query = "SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE 
                           WHERE CONSTRAINT_SCHEMA = DATABASE() 
                           AND CONSTRAINT_NAME = %s";
            
            $table_result = $this->wpdb->get_var(
                $this->wpdb->prepare($table_query, $constraint_name)
            );
            
            if ($table_result) {
                $drop_sql = "ALTER TABLE `{$table_result}` DROP FOREIGN KEY `{$constraint_name}`";
                $this->wpdb->query($drop_sql);
                // Don't log individual constraint removals as it's normal during activation
            }
        }
    }
    
    /**
     * Get table name by key (public method for other classes)
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : false;
    }
    
    /**
     * Validate table integrity
     */
    public function validate_table_integrity() {
        $errors = array();
        
        // Check each table exists and has correct structure
        foreach ($this->tables as $table_key => $table_name) {
            // Check table exists
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $errors[] = "Table missing: $table_name";
                continue;
            }
            
            // Check table has records (for critical tables)
            if ($table_key === 'currencies') {
                $count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                if ($count == 0) {
                    $errors[] = "No default currencies found in $table_name";
                }
            }
        }
        
        return $errors;
    }
}
