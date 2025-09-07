<?php
/**
 * Currency management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Currencies {
    
    private $wpdb;
    private $database;
    private $security;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
    }
    
    /**
     * Create a new currency
     */
    public function create_currency($data) {
        // Sanitize and validate data
        $sanitized_data = $this->sanitize_currency_data($data);
        
        if (is_wp_error($sanitized_data)) {
            return $sanitized_data;
        }
        
        $currencies_table = $this->database->get_table('currencies');
        
        // Check if slug already exists
        $existing = $this->get_currency_by_slug($sanitized_data['slug']);
        if ($existing) {
            return new WP_Error('currency_exists', 'Currency with this slug already exists');
        }
        
        // If this is marked as default, unset other defaults
        if ($sanitized_data['is_default']) {
            $this->wpdb->update(
                $currencies_table,
                array('is_default' => 0),
                array('is_default' => 1)
            );
        }
        
        $result = $this->wpdb->insert(
            $currencies_table,
            $sanitized_data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create currency');
        }
        
        $currency_id = $this->wpdb->insert_id;
        
        // Log the creation
        $this->security->log_security_event('currency_created', get_current_user_id(), array(
            'currency_id' => $currency_id,
            'currency_name' => $sanitized_data['name']
        ));
        
        do_action('membershiping_inventory_currency_created', $currency_id, $sanitized_data);
        
        return $currency_id;
    }
    
    /**
     * Update currency
     */
    public function update_currency($currency_id, $data) {
        $sanitized_data = $this->sanitize_currency_data($data);
        
        if (is_wp_error($sanitized_data)) {
            return $sanitized_data;
        }
        
        $currencies_table = $this->database->get_table('currencies');
        
        // Check if currency exists
        $existing = $this->get_currency($currency_id);
        if (!$existing) {
            return new WP_Error('currency_not_found', 'Currency not found');
        }
        
        // Check slug uniqueness if slug is being changed
        if (isset($sanitized_data['slug']) && $sanitized_data['slug'] !== $existing->slug) {
            $slug_exists = $this->get_currency_by_slug($sanitized_data['slug']);
            if ($slug_exists && $slug_exists->id != $currency_id) {
                return new WP_Error('currency_exists', 'Currency with this slug already exists');
            }
        }
        
        // If setting as default, unset other defaults
        if (isset($sanitized_data['is_default']) && $sanitized_data['is_default']) {
            $this->wpdb->update(
                $currencies_table,
                array('is_default' => 0),
                array('is_default' => 1)
            );
        }
        
        $result = $this->wpdb->update(
            $currencies_table,
            $sanitized_data,
            array('id' => $currency_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update currency');
        }
        
        $this->security->log_security_event('currency_updated', get_current_user_id(), array(
            'currency_id' => $currency_id,
            'changes' => array_keys($sanitized_data)
        ));
        
        do_action('membershiping_inventory_currency_updated', $currency_id, $sanitized_data, $existing);
        
        return true;
    }
    
    /**
     * Delete currency
     */
    public function delete_currency($currency_id) {
        $currencies_table = $this->database->get_table('currencies');
        
        $currency = $this->get_currency($currency_id);
        if (!$currency) {
            return new WP_Error('currency_not_found', 'Currency not found');
        }
        
        // Check if currency is being used
        if ($this->is_currency_in_use($currency_id)) {
            return new WP_Error('currency_in_use', 'Cannot delete currency that is currently in use');
        }
        
        $result = $this->wpdb->delete(
            $currencies_table,
            array('id' => $currency_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete currency');
        }
        
        $this->security->log_security_event('currency_deleted', get_current_user_id(), array(
            'currency_id' => $currency_id,
            'currency_name' => $currency->name
        ));
        
        do_action('membershiping_inventory_currency_deleted', $currency_id, $currency);
        
        return true;
    }
    
    /**
     * Get currency by ID
     */
    public function get_currency($currency_id) {
        $currencies_table = $this->database->get_table('currencies');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $currencies_table WHERE id = %d",
                $currency_id
            )
        );
    }
    
    /**
     * Get currency by slug
     */
    public function get_currency_by_slug($slug) {
        $currencies_table = $this->database->get_table('currencies');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $currencies_table WHERE slug = %s",
                $slug
            )
        );
    }
    
    /**
     * Get all currencies
     */
    public function get_all_currencies($status = 'active') {
        $currencies_table = $this->database->get_table('currencies');
        
        $where = '';
        if ($status) {
            $where = $this->wpdb->prepare(" WHERE status = %s", $status);
        }
        
        return $this->wpdb->get_results(
            "SELECT * FROM $currencies_table $where ORDER BY is_default DESC, name ASC"
        );
    }
    
    /**
     * Get default currency
     */
    public function get_default_currency() {
        $currencies_table = $this->database->get_table('currencies');
        
        $default = $this->wpdb->get_row(
            "SELECT * FROM $currencies_table WHERE is_default = 1 AND status = 'active' LIMIT 1"
        );
        
        // Fallback to first active currency if no default set
        if (!$default) {
            $default = $this->wpdb->get_row(
                "SELECT * FROM $currencies_table WHERE status = 'active' ORDER BY id ASC LIMIT 1"
            );
        }
        
        return $default;
    }
    
    /**
     * Get user currency balance
     */
    public function get_user_balance($user_id, $currency_id) {
        $user_currencies_table = $this->database->get_table('user_currencies');
        
        $balance = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT balance FROM $user_currencies_table WHERE user_id = %d AND currency_id = %d",
                $user_id,
                $currency_id
            )
        );
        
        return $balance !== null ? floatval($balance) : 0.0;
    }
    
    /**
     * Get all user currency balances
     */
    public function get_user_balances($user_id) {
        $user_currencies_table = $this->database->get_table('user_currencies');
        $currencies_table = $this->database->get_table('currencies');
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT uc.*, c.name, c.slug, c.symbol, c.decimal_places 
                FROM $user_currencies_table uc 
                INNER JOIN $currencies_table c ON uc.currency_id = c.id 
                WHERE uc.user_id = %d AND c.status = 'active'
                ORDER BY c.is_default DESC, c.name ASC",
                $user_id
            )
        );
    }
    
    /**
     * Add currency to user
     */
    public function add_currency($user_id, $currency_id, $amount, $transaction_type = 'earned', $reference_type = null, $reference_id = null, $description = null) {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive');
        }
        
        // Check rate limit
        if (!$this->security->check_currency_rate_limit($user_id)) {
            return new WP_Error('rate_limit', 'Too many transactions, please wait');
        }
        
        $user_currencies_table = $this->database->get_table('user_currencies');
        $transactions_table = $this->database->get_table('currency_transactions');
        
        // Get current balance
        $current_balance = $this->get_user_balance($user_id, $currency_id);
        $new_balance = $current_balance + $amount;
        
        // Update or insert user currency record
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $user_currencies_table WHERE user_id = %d AND currency_id = %d",
                $user_id,
                $currency_id
            )
        );
        
        if ($existing) {
            $result = $this->wpdb->update(
                $user_currencies_table,
                array(
                    'balance' => $new_balance,
                    'total_earned' => $existing->total_earned + $amount,
                    'last_transaction_at' => current_time('mysql')
                ),
                array('user_id' => $user_id, 'currency_id' => $currency_id),
                array('%f', '%f', '%s'),
                array('%d', '%d')
            );
        } else {
            $result = $this->wpdb->insert(
                $user_currencies_table,
                array(
                    'user_id' => $user_id,
                    'currency_id' => $currency_id,
                    'balance' => $new_balance,
                    'total_earned' => $amount,
                    'total_spent' => 0,
                    'last_transaction_at' => current_time('mysql')
                ),
                array('%d', '%d', '%f', '%f', '%f', '%s')
            );
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update currency balance');
        }
        
        // Log transaction
        $this->wpdb->insert(
            $transactions_table,
            array(
                'user_id' => $user_id,
                'currency_id' => $currency_id,
                'amount' => $amount,
                'transaction_type' => $transaction_type,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'description' => $description,
                'balance_after' => $new_balance
            ),
            array('%d', '%d', '%f', '%s', '%s', '%d', '%s', '%f')
        );
        
        $transaction_id = $this->wpdb->insert_id;
        
        // Log security event
        $this->security->log_security_event('currency_earned', $user_id, array(
            'currency_id' => $currency_id,
            'amount' => $amount,
            'transaction_type' => $transaction_type,
            'new_balance' => $new_balance
        ));
        
        do_action('membershiping_inventory_currency_earned', $user_id, $currency_id, $amount, $transaction_id);
        
        return $transaction_id;
    }
    
    /**
     * Subtract currency from user
     */
    public function subtract_currency($user_id, $currency_id, $amount, $transaction_type = 'spent', $reference_type = null, $reference_id = null, $description = null) {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive');
        }
        
        // Check rate limit
        if (!$this->security->check_currency_rate_limit($user_id)) {
            return new WP_Error('rate_limit', 'Too many transactions, please wait');
        }
        
        $current_balance = $this->get_user_balance($user_id, $currency_id);
        
        if ($current_balance < $amount) {
            return new WP_Error('insufficient_funds', 'Insufficient currency balance');
        }
        
        $user_currencies_table = $this->database->get_table('user_currencies');
        $transactions_table = $this->database->get_table('currency_transactions');
        
        $new_balance = $current_balance - $amount;
        
        // Update user currency record
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $user_currencies_table WHERE user_id = %d AND currency_id = %d",
                $user_id,
                $currency_id
            )
        );
        
        if ($existing) {
            $result = $this->wpdb->update(
                $user_currencies_table,
                array(
                    'balance' => $new_balance,
                    'total_spent' => $existing->total_spent + $amount,
                    'last_transaction_at' => current_time('mysql')
                ),
                array('user_id' => $user_id, 'currency_id' => $currency_id),
                array('%f', '%f', '%s'),
                array('%d', '%d')
            );
        } else {
            return new WP_Error('no_balance', 'User has no balance for this currency');
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update currency balance');
        }
        
        // Log transaction
        $this->wpdb->insert(
            $transactions_table,
            array(
                'user_id' => $user_id,
                'currency_id' => $currency_id,
                'amount' => -$amount,
                'transaction_type' => $transaction_type,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'description' => $description,
                'balance_after' => $new_balance
            ),
            array('%d', '%d', '%f', '%s', '%s', '%d', '%s', '%f')
        );
        
        $transaction_id = $this->wpdb->insert_id;
        
        // Log security event
        $this->security->log_security_event('currency_spent', $user_id, array(
            'currency_id' => $currency_id,
            'amount' => $amount,
            'transaction_type' => $transaction_type,
            'new_balance' => $new_balance
        ));
        
        do_action('membershiping_inventory_currency_spent', $user_id, $currency_id, $amount, $transaction_id);
        
        return $transaction_id;
    }
    
    /**
     * Transfer currency between users
     */
    public function transfer_currency($from_user_id, $to_user_id, $currency_id, $amount, $description = null) {
        if ($from_user_id == $to_user_id) {
            return new WP_Error('same_user', 'Cannot transfer to same user');
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Subtract from sender
            $debit_result = $this->subtract_currency(
                $from_user_id,
                $currency_id,
                $amount,
                'traded',
                'transfer',
                $to_user_id,
                $description
            );
            
            if (is_wp_error($debit_result)) {
                throw new Exception($debit_result->get_error_message());
            }
            
            // Add to receiver
            $credit_result = $this->add_currency(
                $to_user_id,
                $currency_id,
                $amount,
                'traded',
                'transfer',
                $from_user_id,
                $description
            );
            
            if (is_wp_error($credit_result)) {
                throw new Exception($credit_result->get_error_message());
            }
            
            $this->wpdb->query('COMMIT');
            
            do_action('membershiping_inventory_currency_transferred', $from_user_id, $to_user_id, $currency_id, $amount);
            
            return array(
                'debit_transaction' => $debit_result,
                'credit_transaction' => $credit_result
            );
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('transfer_failed', $e->getMessage());
        }
    }
    
    /**
     * Initialize currencies for new user
     */
    public function initialize_user_currencies($user_id) {
        $currencies = $this->get_all_currencies('active');
        $default_currency = $this->get_default_currency();
        
        if ($default_currency) {
            // Give new users some starting currency
            $starting_amount = apply_filters('membershiping_inventory_starting_currency', 100);
            $this->add_currency(
                $user_id,
                $default_currency->id,
                $starting_amount,
                'awarded',
                'registration',
                null,
                'Welcome bonus'
            );
        }
        
        do_action('membershiping_inventory_user_currencies_initialized', $user_id, $currencies);
    }
    
    /**
     * Get user transaction history
     */
    public function get_user_transactions($user_id, $currency_id = null, $limit = 50, $offset = 0) {
        $transactions_table = $this->database->get_table('currency_transactions');
        $currencies_table = $this->database->get_table('currencies');
        
        $where = "WHERE t.user_id = %d";
        $params = array($user_id);
        
        if ($currency_id) {
            $where .= " AND t.currency_id = %d";
            $params[] = $currency_id;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT t.*, c.name as currency_name, c.symbol as currency_symbol 
                FROM $transactions_table t 
                INNER JOIN $currencies_table c ON t.currency_id = c.id 
                $where 
                ORDER BY t.created_at DESC, t.id DESC 
                LIMIT %d OFFSET %d",
                ...$params
            )
        );
    }
    
    /**
     * Format currency amount
     */
    public function format_currency_amount($amount, $currency_id) {
        $currency = $this->get_currency($currency_id);
        if (!$currency) {
            return $amount;
        }
        
        $formatted = number_format($amount, $currency->decimal_places);
        
        return $currency->symbol . ' ' . $formatted;
    }
    
    /**
     * Convert between currencies
     */
    public function convert_currency($amount, $from_currency_id, $to_currency_id) {
        if ($from_currency_id == $to_currency_id) {
            return $amount;
        }
        
        $from_currency = $this->get_currency($from_currency_id);
        $to_currency = $this->get_currency($to_currency_id);
        
        if (!$from_currency || !$to_currency) {
            return false;
        }
        
        // Convert to base currency first, then to target currency
        $base_amount = $amount / $from_currency->exchange_rate;
        $converted_amount = $base_amount * $to_currency->exchange_rate;
        
        return round($converted_amount, $to_currency->decimal_places);
    }
    
    /**
     * Check if currency is in use
     */
    private function is_currency_in_use($currency_id) {
        $user_currencies_table = $this->database->get_table('user_currencies');
        $transactions_table = $this->database->get_table('currency_transactions');
        
        // Check user balances
        $balance_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $user_currencies_table WHERE currency_id = %d AND balance > 0",
                $currency_id
            )
        );
        
        // Check transactions
        $transaction_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $transactions_table WHERE currency_id = %d",
                $currency_id
            )
        );
        
        return $balance_count > 0 || $transaction_count > 0;
    }
    
    /**
     * Sanitize currency data
     */
    private function sanitize_currency_data($data) {
        $sanitized = array();
        $errors = array();
        
        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Currency name is required';
        } else {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }
        
        if (empty($data['slug'])) {
            $sanitized['slug'] = sanitize_title($data['name'] ?? '');
        } else {
            $sanitized['slug'] = sanitize_title($data['slug']);
        }
        
        if (empty($data['symbol'])) {
            $errors[] = 'Currency symbol is required';
        } else {
            $sanitized['symbol'] = sanitize_text_field($data['symbol']);
        }
        
        // Optional fields
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['icon'])) {
            $sanitized['icon'] = esc_url_raw($data['icon']);
        }
        
        if (isset($data['is_default'])) {
            $sanitized['is_default'] = (bool) $data['is_default'];
        }
        
        if (isset($data['decimal_places'])) {
            $decimal_places = intval($data['decimal_places']);
            $sanitized['decimal_places'] = max(0, min(4, $decimal_places));
        }
        
        if (isset($data['exchange_rate'])) {
            $exchange_rate = floatval($data['exchange_rate']);
            $sanitized['exchange_rate'] = max(0.0001, $exchange_rate);
        }
        
        if (isset($data['status'])) {
            $allowed_statuses = array('active', 'inactive');
            $sanitized['status'] = in_array($data['status'], $allowed_statuses) ? $data['status'] : 'active';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return $sanitized;
    }
    
    /**
     * Get currency name by ID
     */
    public function get_currency_name($currency_id) {
        $currencies_table = $this->database->get_table('currencies');
        
        $name = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT name FROM {$currencies_table} WHERE id = %d",
            $currency_id
        ));
        
        return $name ? $name : __('Unknown Currency', 'membershiping-inventory');
    }
    
    /**
     * Deduct currency from user balance
     */
    public function deduct_user_balance($user_id, $currency_id, $amount) {
        $user_id = intval($user_id);
        $currency_id = intval($currency_id);
        $amount = floatval($amount);
        
        if ($user_id <= 0 || $currency_id <= 0 || $amount <= 0) {
            return new WP_Error('invalid_params', 'Invalid parameters for balance deduction');
        }
        
        // Check if currency exists and is active
        $currency = $this->get_currency($currency_id);
        if (!$currency || $currency->status !== 'active') {
            return new WP_Error('invalid_currency', 'Currency not found or inactive');
        }
        
        // Check current balance
        $current_balance = $this->get_user_balance($user_id, $currency_id);
        if ($current_balance < $amount) {
            return new WP_Error('insufficient_balance', 
                sprintf(__('Insufficient %s balance. Required: %s, Available: %s', 'membershiping-inventory'),
                    $currency->name,
                    number_format($amount, 2),
                    number_format($current_balance, 2)
                )
            );
        }
        
        $user_currencies_table = $this->database->get_table('user_currencies');
        
        // Deduct the amount
        $result = $this->wpdb->update(
            $user_currencies_table,
            array('balance' => $current_balance - $amount),
            array('user_id' => $user_id, 'currency_id' => $currency_id),
            array('%f'),
            array('%d', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to deduct currency balance');
        }
        
        // Log the transaction
        $this->security->log_security_event('currency_deducted', $user_id, array(
            'currency_id' => $currency_id,
            'currency_name' => $currency->name,
            'amount' => $amount,
            'previous_balance' => $current_balance,
            'new_balance' => $current_balance - $amount
        ));
        
        do_action('membershiping_inventory_currency_deducted', $user_id, $currency_id, $amount, $current_balance - $amount);
        
        return $current_balance - $amount;
    }
}
