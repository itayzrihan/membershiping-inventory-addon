<?php
/**
 * Security management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Security {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize security hooks
     */
    private function init_hooks() {
        // AJAX security
        add_action('wp_ajax_membershiping_inventory_trade_request', array($this, 'verify_ajax_nonce'));
        add_action('wp_ajax_membershiping_inventory_accept_trade', array($this, 'verify_ajax_nonce'));
        add_action('wp_ajax_membershiping_inventory_use_item', array($this, 'verify_ajax_nonce'));
        
        // Admin security
        add_action('admin_init', array($this, 'check_admin_capabilities'));
        
        // Rate limiting
        add_action('init', array($this, 'init_rate_limiting'));
    }
    
    /**
     * Verify AJAX nonce before processing
     */
    public function verify_ajax_nonce() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'membershiping_inventory_nonce')) {
            wp_die('Security check failed', 'Unauthorized', array('response' => 403));
        }
    }
    
    /**
     * Check admin capabilities
     */
    public function check_admin_capabilities() {
        if (is_admin() && !current_user_can('manage_options')) {
            // Restrict access to sensitive admin pages
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'membershiping-inventory') !== false) {
                wp_die('You do not have sufficient permissions to access this page.');
            }
        }
    }
    
    /**
     * Initialize rate limiting
     */
    public function init_rate_limiting() {
        // Implement rate limiting for trades and transactions
        $this->setup_trade_rate_limits();
        $this->setup_transaction_rate_limits();
    }
    
    /**
     * Setup trade rate limits
     */
    private function setup_trade_rate_limits() {
        if (!get_transient('membershiping_inventory_trade_limits_enabled')) {
            set_transient('membershiping_inventory_trade_limits_enabled', true, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Setup transaction rate limits
     */
    private function setup_transaction_rate_limits() {
        if (!get_transient('membershiping_inventory_transaction_limits_enabled')) {
            set_transient('membershiping_inventory_transaction_limits_enabled', true, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Sanitize item data
     */
    public function sanitize_item_data($data) {
        $sanitized = array();
        
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['item_type'])) {
            $allowed_types = array('consumable', 'equipment', 'gift_box', 'material', 'collectible');
            $sanitized['item_type'] = in_array($data['item_type'], $allowed_types) ? $data['item_type'] : 'collectible';
        }
        
        if (isset($data['rarity'])) {
            $allowed_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic');
            $sanitized['rarity'] = in_array($data['rarity'], $allowed_rarities) ? $data['rarity'] : 'common';
        }
        
        if (isset($data['stats'])) {
            $sanitized['stats'] = $this->sanitize_json_data($data['stats']);
        }
        
        if (isset($data['requirements'])) {
            $sanitized['requirements'] = $this->sanitize_json_data($data['requirements']);
        }
        
        if (isset($data['use_effect'])) {
            $sanitized['use_effect'] = $this->sanitize_json_data($data['use_effect']);
        }
        
        if (isset($data['gift_box_items'])) {
            $sanitized['gift_box_items'] = $this->sanitize_json_data($data['gift_box_items']);
        }
        
        if (isset($data['currency_prices'])) {
            $sanitized['currency_prices'] = $this->sanitize_json_data($data['currency_prices']);
        }
        
        // Boolean fields
        $boolean_fields = array('is_tradeable', 'is_consumable', 'is_stackable', 'exclude_from_shop', 'allow_currency_purchase');
        foreach ($boolean_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (bool) $data[$field];
            }
        }
        
        // Integer fields
        $integer_fields = array('max_stack_size', 'quantity_limit', 'current_quantity');
        foreach ($integer_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = intval($data[$field]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize JSON data
     */
    private function sanitize_json_data($data) {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($this->sanitize_array_recursive($decoded));
            }
        } elseif (is_array($data)) {
            return wp_json_encode($this->sanitize_array_recursive($data));
        }
        
        return null;
    }
    
    /**
     * Recursively sanitize array data
     */
    private function sanitize_array_recursive($array) {
        $sanitized = array();
        
        foreach ($array as $key => $value) {
            $clean_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_array_recursive($value);
            } elseif (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = (bool) $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate trade data
     */
    public function validate_trade_data($trade_data) {
        $errors = array();
        
        // Check required fields
        $required_fields = array('initiator_id', 'target_id', 'initiator_items', 'target_items');
        foreach ($required_fields as $field) {
            if (empty($trade_data[$field])) {
                $errors[] = sprintf('Missing required field: %s', $field);
            }
        }
        
        // Validate user IDs
        if (!empty($trade_data['initiator_id']) && !get_user_by('id', $trade_data['initiator_id'])) {
            $errors[] = 'Invalid initiator user ID';
        }
        
        if (!empty($trade_data['target_id']) && !get_user_by('id', $trade_data['target_id'])) {
            $errors[] = 'Invalid target user ID';
        }
        
        // Validate same user
        if (!empty($trade_data['initiator_id']) && !empty($trade_data['target_id']) && 
            $trade_data['initiator_id'] == $trade_data['target_id']) {
            $errors[] = 'Cannot trade with yourself';
        }
        
        // Validate items arrays
        if (!empty($trade_data['initiator_items'])) {
            if (!is_array($trade_data['initiator_items'])) {
                $errors[] = 'Initiator items must be an array';
            } elseif (count($trade_data['initiator_items']) > 10) {
                $errors[] = 'Too many items in trade (max 10)';
            }
        }
        
        if (!empty($trade_data['target_items'])) {
            if (!is_array($trade_data['target_items'])) {
                $errors[] = 'Target items must be an array';
            } elseif (count($trade_data['target_items']) > 10) {
                $errors[] = 'Too many items requested (max 10)';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Check user permissions for item operations
     */
    public function can_user_manage_item($user_id, $item_id) {
        // Check if user owns the item
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $user_items_table = $database->get_table('user_items');
        
        $owned = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT quantity FROM $user_items_table WHERE user_id = %d AND item_id = %d",
                $user_id,
                $item_id
            )
        );
        
        return $owned > 0;
    }
    
    /**
     * Check user permissions for NFT operations
     */
    public function can_user_manage_nft($user_id, $nft_id) {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $nfts_table = $database->get_table('nfts');
        
        $owned = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $nfts_table WHERE owner_id = %d AND id = %d",
                $user_id,
                $nft_id
            )
        );
        
        return $owned > 0;
    }
    
    /**
     * Rate limit check for trades
     */
    public function check_trade_rate_limit($user_id) {
        $transient_key = 'membershiping_inventory_trade_limit_' . $user_id;
        $current_count = get_transient($transient_key) ?: 0;
        $max_trades_per_hour = 5;
        
        if ($current_count >= $max_trades_per_hour) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Rate limit check for currency transactions
     */
    public function check_currency_rate_limit($user_id) {
        $transient_key = 'membershiping_inventory_currency_limit_' . $user_id;
        $current_count = get_transient($transient_key) ?: 0;
        $max_transactions_per_minute = 10;
        
        if ($current_count >= $max_transactions_per_minute) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    /**
     * Generate secure token
     */
    public function generate_secure_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate NFT hash
     */
    public function generate_nft_hash($item_id, $user_id, $timestamp = null) {
        if (!$timestamp) {
            $timestamp = time();
        }
        
        $data = sprintf(
            '%d:%d:%d:%s:%s',
            $item_id,
            $user_id,
            $timestamp,
            wp_generate_uuid4(),
            $this->generate_secure_token(16)
        );
        
        return hash('sha256', $data);
    }
    
    /**
     * Generate unique NFT token
     */
    public function generate_nft_token($item_id, $user_id) {
        $prefix = 'MINV';
        $timestamp = time();
        $random = $this->generate_secure_token(8);
        
        return sprintf(
            '%s-%d-%d-%d-%s',
            $prefix,
            $item_id,
            $user_id,
            $timestamp,
            strtoupper($random)
        );
    }
    
    /**
     * Validate file upload
     */
    public function validate_image_upload($file) {
        $errors = array();
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
        }
        
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            $errors[] = 'File size too large. Maximum 5MB allowed.';
        }
        
        // Check image dimensions
        if (!empty($file['tmp_name'])) {
            $image_info = getimagesize($file['tmp_name']);
            if (!$image_info) {
                $errors[] = 'Invalid image file.';
            } else {
                $max_width = 2000;
                $max_height = 2000;
                
                if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
                    $errors[] = sprintf('Image dimensions too large. Maximum %dx%d pixels.', $max_width, $max_height);
                }
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Log security event
     */
    public function log_security_event($event_type, $user_id = null, $details = array()) {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $logs_table = $database->get_table('logs');
        
        $wpdb->insert(
            $logs_table,
            array(
                'user_id' => $user_id,
                'action' => $event_type,
                'details' => wp_json_encode($details),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if user is blocked
     */
    public function is_user_blocked($user_id) {
        $blocked_users = get_option('membershiping_inventory_blocked_users', array());
        return in_array($user_id, $blocked_users);
    }
    
    /**
     * Block user
     */
    public function block_user($user_id, $reason = '') {
        $blocked_users = get_option('membershiping_inventory_blocked_users', array());
        if (!in_array($user_id, $blocked_users)) {
            $blocked_users[] = $user_id;
            update_option('membershiping_inventory_blocked_users', $blocked_users);
            
            $this->log_security_event('user_blocked', $user_id, array('reason' => $reason));
        }
    }
    
    /**
     * Unblock user
     */
    public function unblock_user($user_id) {
        $blocked_users = get_option('membershiping_inventory_blocked_users', array());
        $key = array_search($user_id, $blocked_users);
        if ($key !== false) {
            unset($blocked_users[$key]);
            update_option('membershiping_inventory_blocked_users', array_values($blocked_users));
            
            $this->log_security_event('user_unblocked', $user_id);
        }
    }
    
    /**
     * Validate currency amount
     */
    public function validate_currency_amount($amount) {
        if (empty($amount) || !is_numeric($amount)) {
            return false;
        }
        
        $amount = floatval($amount);
        return $amount >= 0 && $amount <= 999999999.99;
    }
    
    /**
     * Validate trade data
     */
    public function validate_trade_data($trade_data) {
        // Check required fields
        if (!isset($trade_data['initiator_items']) || !isset($trade_data['target_items']) || !isset($trade_data['target_user_id'])) {
            return false;
        }
        
        // Validate structure
        if (!is_array($trade_data['initiator_items']) || !is_array($trade_data['target_items'])) {
            return false;
        }
        
        // Validate user ID
        if (!is_numeric($trade_data['target_user_id']) || $trade_data['target_user_id'] <= 0) {
            return false;
        }
        
        // Validate items structure
        foreach ($trade_data['initiator_items'] as $item) {
            if (!isset($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['item_id']) || !is_numeric($item['quantity'])) {
                return false;
            }
        }
        
        foreach ($trade_data['target_items'] as $item) {
            if (!isset($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['item_id']) || !is_numeric($item['quantity'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize user input (generic)
     */
    public function sanitize_user_input($input) {
        // Remove potential XSS
        $input = wp_kses($input, array());
        
        // Remove javascript: protocol
        $input = preg_replace('/javascript:/i', '', $input);
        
        // Remove on* event handlers
        $input = preg_replace('/on\w+\s*=/i', '', $input);
        
        return sanitize_text_field($input);
    }
    
    /**
     * Check rate limit
     */
    public function check_rate_limit($user_id, $action, $limit = 10, $timeframe = 300) {
        $key = "rate_limit_{$action}_{$user_id}";
        $current_count = get_transient($key) ?: 0;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($key, $current_count + 1, $timeframe);
        return true;
    }
    
    /**
     * Check if action requires authentication
     */
    public function requires_authentication($action) {
        $auth_required_actions = array(
            'trade_request',
            'accept_trade',
            'use_item',
            'mint_nft',
            'transfer_currency',
            'purchase_item'
        );
        
        return in_array($action, $auth_required_actions);
    }
    
    /**
     * Validate user session
     */
    public function validate_user_session($user_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        return $current_user_id === intval($user_id);
    }
    
    /**
     * Check user capability
     */
    public function check_user_capability($capability, $function_type) {
        $required_caps = array(
            'admin_functions' => array('manage_options'),
            'basic_functions' => array('read'),
            'edit_functions' => array('edit_posts'),
            'trade_functions' => array('read')
        );
        
        if (!isset($required_caps[$function_type])) {
            return false;
        }
        
        foreach ($required_caps[$function_type] as $required_cap) {
            if (!current_user_can($required_cap)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user owns item
     */
    public function user_owns_item($user_id, $item_id) {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('user_items');
        
        if (!$table_name) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM $table_name WHERE user_id = %d AND item_id = %d",
            $user_id, $item_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Sanitize data array
     */
    public function sanitize_data_array($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url($value);
                    break;
                case 'number':
                    $sanitized[$key] = intval($value);
                    break;
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case 'json':
                    $decoded = json_decode($value, true);
                    if ($decoded !== null) {
                        $sanitized[$key] = wp_json_encode($this->sanitize_data_array($decoded));
                    } else {
                        $sanitized[$key] = '';
                    }
                    break;
                default:
                    $sanitized[$key] = $this->sanitize_user_input($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get allowed file types
     */
    public function get_allowed_file_types() {
        return array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp');
    }
    
    /**
     * Get maximum file size
     */
    public function get_max_file_size() {
        return 5 * 1024 * 1024; // 5MB
    }
    
    /**
     * Check if file is allowed
     */
    public function is_file_allowed($filename) {
        $allowed_types = $this->get_allowed_file_types();
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($file_extension, $allowed_types);
    }
    
    /**
     * Log security event to audit logs
     */
    public function log_security_event($action, $details, $severity = 'medium') {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('audit_logs');
        
        if (!$table_name) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'action' => sanitize_text_field($action),
                'object_type' => 'system',
                'details' => wp_json_encode($details),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_id' => session_id(),
                'severity' => sanitize_text_field($severity),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get recent security logs
     */
    public function get_recent_security_logs($limit = 50) {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('audit_logs');
        
        if (!$table_name) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE object_type = 'system' ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Enhanced CSRF protection for AJAX
     */
    public function verify_ajax_nonce_enhanced($action = 'membershiping_inventory_nonce') {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            wp_send_json_error('Security check failed', 403);
            wp_die();
        }
        
        // Check rate limiting
        $user_id = get_current_user_id();
        if (!$this->check_rate_limit($user_id, 'ajax_request', 100, 300)) {
            wp_send_json_error('Too many requests', 429);
            wp_die();
        }
        
        // Log the request
        $this->log_security_event('ajax_request', array(
            'action' => $action,
            'user_id' => $user_id,
            'ip' => $this->get_user_ip(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ), 'low');
        
        return true;
    }
}
