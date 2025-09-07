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
     * Get user IP address
     */
    public function get_user_ip() {
        // Check for IP from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for IP from remote address
        else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
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
            if ($screen && strpos($screen->id, 'membershiping_inventory') !== false) {
                wp_die('You do not have sufficient permissions to access this page.');
            }
        }
    }
    
    /**
     * Initialize rate limiting
     */
    public function init_rate_limiting() {
        // Check if rate limiting is temporarily disabled
        $disable_until = get_option('membershiping_inventory_disable_rate_limiting', false);
        if ($disable_until && (time() - $disable_until) < 3600) { // Disabled for 1 hour
            return;
        }
        
        // Only apply rate limiting to plugin-specific actions, not general WordPress operations
        if (!$this->should_apply_rate_limiting()) {
            return;
        }
        
        // Check for excessive requests
        $user_id = get_current_user_id();
        $ip = $this->get_user_ip();
        
        // Rate limit by user (more reasonable limits)
        if ($user_id) {
            $key = 'membershiping_requests_user_' . $user_id;
            $count = get_transient($key) ?: 0;
            if ($count > 500) { // 500 requests per hour (increased from 100)
                wp_die('Rate limit exceeded. Please try again later.');
            }
            set_transient($key, $count + 1, HOUR_IN_SECONDS);
        }
        
        // Rate limit by IP (more reasonable limits)
        $key = 'membershiping_requests_ip_' . md5($ip);
        $count = get_transient($key) ?: 0;
        if ($count > 1000) { // 1000 requests per hour per IP (increased from 200)
            wp_die('Rate limit exceeded. Please try again later.');
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }
    
    /**
     * Check if rate limiting should be applied to current request
     */
    private function should_apply_rate_limiting() {
        // Don't apply rate limiting during plugin installation/activation
        if (defined('WP_INSTALLING') && WP_INSTALLING) {
            return false;
        }
        
        // Don't apply rate limiting to admin pages (except our plugin pages)
        if (is_admin()) {
            $page = $_GET['page'] ?? '';
            // Only apply to our plugin's admin pages
            if (strpos($page, 'membershiping-inventory') === false) {
                return false;
            }
        }
        
        // Don't apply rate limiting to AJAX requests unless they're ours
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';
            // Only apply to our plugin's AJAX actions
            if (strpos($action, 'membershiping') === false) {
                return false;
            }
        }
        
        // Don't apply rate limiting to cron jobs
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }
        
        // Don't apply rate limiting to CLI requests
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }
        
        // Apply rate limiting to REST API requests for our endpoints
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            return strpos($request_uri, '/wp-json/membershiping') !== false;
        }
        
        // Apply rate limiting to our plugin's frontend actions
        $plugin_actions = array('membershiping_trade', 'membershiping_consume', 'membershiping_transfer');
        $current_action = $_POST['membershiping_action'] ?? $_GET['membershiping_action'] ?? '';
        
        return in_array($current_action, $plugin_actions);
    }
    
    /**
     * Sanitize item data
     */
    public function sanitize_item_data($data) {
        $sanitized = array();
        
        // Basic sanitization for common fields
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = wp_kses_post($data['description']);
        }
        
        if (isset($data['quantity'])) {
            $sanitized['quantity'] = max(0, intval($data['quantity']));
        }
        
        if (isset($data['category'])) {
            $sanitized['category'] = sanitize_text_field($data['category']);
        }
        
        if (isset($data['rarity'])) {
            $allowed_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary');
            $sanitized['rarity'] = in_array($data['rarity'], $allowed_rarities) ? $data['rarity'] : 'common';
        }
        
        if (isset($data['tradeable'])) {
            $sanitized['tradeable'] = (bool) $data['tradeable'];
        }
        
        if (isset($data['value'])) {
            $sanitized['value'] = max(0, floatval($data['value']));
        }
        
        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $sanitized['metadata'] = array_map('sanitize_text_field', $data['metadata']);
            } else {
                $sanitized['metadata'] = array();
            }
        }
        
        // NFT specific fields
        if (isset($data['is_nft'])) {
            $sanitized['is_nft'] = (bool) $data['is_nft'];
        }
        
        if (isset($data['token_id'])) {
            $sanitized['token_id'] = sanitize_text_field($data['token_id']);
        }
        
        if (isset($data['blockchain_hash'])) {
            $sanitized['blockchain_hash'] = sanitize_text_field($data['blockchain_hash']);
        }
        
        if (isset($data['smart_contract'])) {
            $sanitized['smart_contract'] = sanitize_text_field($data['smart_contract']);
        }
        
        if (isset($data['owner_address'])) {
            $sanitized['owner_address'] = sanitize_text_field($data['owner_address']);
        }
        
        // Validate specific NFT fields
        if (isset($sanitized['is_nft']) && $sanitized['is_nft']) {
            // NFT must have token_id
            if (!isset($sanitized['token_id']) || empty($sanitized['token_id'])) {
                $sanitized['token_id'] = $this->generate_secure_token(16);
            }
            
            // NFT quantity should be 1
            $sanitized['quantity'] = 1;
            
            // NFT should be tradeable by default
            if (!isset($sanitized['tradeable'])) {
                $sanitized['tradeable'] = true;
            }
        }
        
        // Additional validation
        if (isset($sanitized['name']) && strlen($sanitized['name']) > 255) {
            $sanitized['name'] = substr($sanitized['name'], 0, 255);
        }
        
        if (isset($sanitized['description']) && strlen($sanitized['description']) > 5000) {
            $sanitized['description'] = substr($sanitized['description'], 0, 5000);
        }
        
        return $sanitized;
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
     * Check if user can manage item
     */
    public function can_user_manage_item($user_id, $item_id) {
        // Admin can manage all items
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user owns the item
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('user_items');
        
        if (!$table_name) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND item_id = %d",
            $user_id, $item_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Check if user can manage NFT
     */
    public function can_user_manage_nft($user_id, $nft_id) {
        // Admin can manage all NFTs
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user owns the NFT
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('nfts');
        
        if (!$table_name) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d AND owner_id = %d",
            $nft_id, $user_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Check trade rate limit
     */
    public function check_trade_rate_limit($user_id) {
        $key = 'membershiping_trade_rate_' . $user_id;
        $count = get_transient($key) ?: 0;
        
        if ($count >= 10) { // 10 trades per hour
            return false;
        }
        
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Check currency rate limit
     */
    public function check_currency_rate_limit($user_id) {
        $key = 'membershiping_currency_rate_' . $user_id;
        $count = get_transient($key) ?: 0;
        
        if ($count >= 50) { // 50 currency operations per hour
            return false;
        }
        
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
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
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $data = $item_id . '|' . $user_id . '|' . $timestamp . '|' . wp_salt();
        return hash('sha256', $data);
    }
    
    /**
     * Generate NFT token
     */
    public function generate_nft_token($item_id, $user_id) {
        $timestamp = time();
        $hash = $this->generate_nft_hash($item_id, $user_id, $timestamp);
        
        // Create a unique token combining timestamp and hash
        $token = $timestamp . '_' . substr($hash, 0, 16);
        
        return $token;
    }
    
    /**
     * Validate image upload
     */
    public function validate_image_upload($file) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds 5MB limit.');
        }
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload error: ' . $file['error']);
        }
        
        return true;
    }
    
    /**
     * Log security event
     */
    public function log_security_event($event_type, $user_id = null, $details = array()) {
        global $wpdb;
        $database = new Membershiping_Inventory_Database();
        $table_name = $database->get_table_name('audit_logs');
        
        if (!$table_name) {
            return false;
        }
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $log_data = array(
            'user_id' => $user_id,
            'action' => sanitize_text_field($event_type),
            'object_type' => 'security',
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'severity' => 'medium',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $table_name,
            $log_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
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
            "SELECT * FROM $table_name WHERE object_type = 'security' ORDER BY created_at DESC LIMIT %d",
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
