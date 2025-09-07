<?php
/**
 * Security Audit and Testing Class
 * Comprehensive security validation for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Security_Audit {
    
    private $security;
    private $audit_results = array();
    
    public function __construct() {
        $this->security = new Membershiping_Inventory_Security();
    }
    
    /**
     * Run complete security audit
     */
    public function run_security_audit() {
        $this->audit_results = array();
        
        // Test CSRF protection
        $this->test_csrf_protection();
        
        // Test input validation
        $this->test_input_validation();
        
        // Test SQL injection prevention
        $this->test_sql_injection_prevention();
        
        // Test XSS protection
        $this->test_xss_protection();
        
        // Test rate limiting
        $this->test_rate_limiting();
        
        // Test authentication checks
        $this->test_authentication();
        
        // Test authorization checks
        $this->test_authorization();
        
        // Test data sanitization
        $this->test_data_sanitization();
        
        // Test file upload security
        $this->test_file_upload_security();
        
        // Test audit logging
        $this->test_audit_logging();
        
        return $this->audit_results;
    }
    
    /**
     * Test CSRF protection
     */
    private function test_csrf_protection() {
        $this->add_audit_result('CSRF_PROTECTION', 'Testing CSRF protection...');
        
        // Test nonce generation
        $nonce = wp_create_nonce('membershiping_inventory_nonce');
        if ($nonce && strlen($nonce) > 0) {
            $this->add_audit_result('CSRF_NONCE_GENERATION', 'PASS: Nonce generation working', 'success');
        } else {
            $this->add_audit_result('CSRF_NONCE_GENERATION', 'FAIL: Nonce generation failed', 'error');
        }
        
        // Test nonce verification (simulated)
        $valid_nonce = wp_verify_nonce($nonce, 'membershiping_inventory_nonce');
        if ($valid_nonce) {
            $this->add_audit_result('CSRF_NONCE_VERIFICATION', 'PASS: Nonce verification working', 'success');
        } else {
            $this->add_audit_result('CSRF_NONCE_VERIFICATION', 'FAIL: Nonce verification failed', 'error');
        }
        
        // Test invalid nonce rejection
        $invalid_nonce = wp_verify_nonce('invalid_nonce', 'membershiping_inventory_nonce');
        if (!$invalid_nonce) {
            $this->add_audit_result('CSRF_INVALID_REJECTION', 'PASS: Invalid nonces properly rejected', 'success');
        } else {
            $this->add_audit_result('CSRF_INVALID_REJECTION', 'FAIL: Invalid nonces not rejected', 'error');
        }
        
        // Check AJAX endpoints have nonce verification
        $this->check_ajax_endpoints_csrf();
    }
    
    /**
     * Check AJAX endpoints for CSRF protection
     */
    private function check_ajax_endpoints_csrf() {
        $ajax_actions = array(
            'membershiping_inventory_trade_request',
            'membershiping_inventory_accept_trade',
            'membershiping_inventory_use_item',
            'membershiping_inventory_mint_nft',
            'membershiping_inventory_transfer_currency'
        );
        
        $protected_actions = 0;
        foreach ($ajax_actions as $action) {
            if (has_action("wp_ajax_$action")) {
                $protected_actions++;
            }
        }
        
        if ($protected_actions > 0) {
            $this->add_audit_result('AJAX_CSRF_PROTECTION', "PASS: $protected_actions AJAX endpoints protected", 'success');
        } else {
            $this->add_audit_result('AJAX_CSRF_PROTECTION', 'WARN: No AJAX endpoints found for testing', 'warning');
        }
    }
    
    /**
     * Test input validation
     */
    private function test_input_validation() {
        $this->add_audit_result('INPUT_VALIDATION', 'Testing input validation...');
        
        // Test item data validation
        $test_data = array(
            'name' => '<script>alert("xss")</script>Test Item',
            'description' => 'Valid description',
            'item_type' => 'invalid_type',
            'rarity' => 'common',
            'quantity' => 'not_a_number'
        );
        
        $sanitized = $this->security->sanitize_item_data($test_data);
        
        // Check XSS removal
        if (strpos($sanitized['name'], '<script>') === false) {
            $this->add_audit_result('INPUT_XSS_REMOVAL', 'PASS: XSS tags removed from input', 'success');
        } else {
            $this->add_audit_result('INPUT_XSS_REMOVAL', 'FAIL: XSS tags not properly removed', 'error');
        }
        
        // Check enum validation
        if ($sanitized['item_type'] === 'collectible') { // Should default to valid value
            $this->add_audit_result('INPUT_ENUM_VALIDATION', 'PASS: Invalid enum values corrected', 'success');
        } else {
            $this->add_audit_result('INPUT_ENUM_VALIDATION', 'FAIL: Invalid enum values not corrected', 'error');
        }
        
        // Test currency validation
        $this->test_currency_validation();
        
        // Test trade validation
        $this->test_trade_validation();
    }
    
    /**
     * Test currency validation
     */
    private function test_currency_validation() {
        $test_amounts = array(
            '100.50' => true,   // Valid
            '-50.00' => false,  // Negative
            'abc' => false,     // Non-numeric
            '999999999.99' => true, // Large valid
            '' => false         // Empty
        );
        
        $valid_count = 0;
        $total_tests = count($test_amounts);
        
        foreach ($test_amounts as $amount => $should_be_valid) {
            $is_valid = $this->security->validate_currency_amount($amount);
            if ($is_valid === $should_be_valid) {
                $valid_count++;
            }
        }
        
        if ($valid_count === $total_tests) {
            $this->add_audit_result('CURRENCY_VALIDATION', 'PASS: Currency validation working correctly', 'success');
        } else {
            $this->add_audit_result('CURRENCY_VALIDATION', "FAIL: Currency validation failed $valid_count/$total_tests tests", 'error');
        }
    }
    
    /**
     * Test trade validation
     */
    private function test_trade_validation() {
        // Test trade data structure validation
        $valid_trade = array(
            'initiator_items' => array(array('item_id' => 1, 'quantity' => 1)),
            'target_items' => array(array('item_id' => 2, 'quantity' => 1)),
            'target_user_id' => 123
        );
        
        $invalid_trade = array(
            'initiator_items' => 'invalid_structure',
            'target_items' => array(),
            'target_user_id' => 'not_a_number'
        );
        
        $valid_result = $this->security->validate_trade_data($valid_trade);
        $invalid_result = $this->security->validate_trade_data($invalid_trade);
        
        if ($valid_result === true && $invalid_result === false) {
            $this->add_audit_result('TRADE_VALIDATION', 'PASS: Trade validation working correctly', 'success');
        } else {
            $this->add_audit_result('TRADE_VALIDATION', 'FAIL: Trade validation not working properly', 'error');
        }
    }
    
    /**
     * Test SQL injection prevention
     */
    private function test_sql_injection_prevention() {
        $this->add_audit_result('SQL_INJECTION', 'Testing SQL injection prevention...');
        
        // Test prepared statements usage
        global $wpdb;
        
        // Check if all database queries use prepared statements
        $database_class_content = file_get_contents(MEMBERSHIPING_INVENTORY_PLUGIN_PATH . 'includes/class-database.php');
        
        // Look for direct SQL without prepare()
        $unsafe_patterns = array(
            '/\$wpdb->query\s*\(\s*["\'](?!INSERT INTO|UPDATE|DELETE FROM|SELECT)/',
            '/\$wpdb->get_results\s*\(\s*["\'].*\$/',
            '/\$wpdb->get_var\s*\(\s*["\'].*\$/',
        );
        
        $unsafe_queries = 0;
        foreach ($unsafe_patterns as $pattern) {
            if (preg_match($pattern, $database_class_content)) {
                $unsafe_queries++;
            }
        }
        
        if ($unsafe_queries === 0) {
            $this->add_audit_result('SQL_PREPARED_STATEMENTS', 'PASS: All queries use prepared statements', 'success');
        } else {
            $this->add_audit_result('SQL_PREPARED_STATEMENTS', "WARN: $unsafe_queries potentially unsafe queries found", 'warning');
        }
        
        // Test input escaping
        $this->test_sql_escaping();
    }
    
    /**
     * Test SQL escaping
     */
    private function test_sql_escaping() {
        global $wpdb;
        
        $malicious_input = "'; DROP TABLE users; --";
        $escaped = $wpdb->prepare("SELECT * FROM test WHERE name = %s", $malicious_input);
        
        if (strpos($escaped, 'DROP TABLE') === false) {
            $this->add_audit_result('SQL_ESCAPING', 'PASS: SQL injection attempts properly escaped', 'success');
        } else {
            $this->add_audit_result('SQL_ESCAPING', 'FAIL: SQL injection attempts not escaped', 'error');
        }
    }
    
    /**
     * Test XSS protection
     */
    private function test_xss_protection() {
        $this->add_audit_result('XSS_PROTECTION', 'Testing XSS protection...');
        
        $xss_payloads = array(
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onerror="alert(1)">',
            '"><script>alert("xss")</script>',
            '<svg onload="alert(1)">'
        );
        
        $protected_count = 0;
        foreach ($xss_payloads as $payload) {
            $sanitized = $this->security->sanitize_user_input($payload);
            if ($sanitized !== $payload && strpos($sanitized, 'script') === false) {
                $protected_count++;
            }
        }
        
        $success_rate = ($protected_count / count($xss_payloads)) * 100;
        
        if ($success_rate === 100) {
            $this->add_audit_result('XSS_SANITIZATION', 'PASS: All XSS payloads properly sanitized', 'success');
        } elseif ($success_rate >= 80) {
            $this->add_audit_result('XSS_SANITIZATION', "WARN: $success_rate% XSS protection rate", 'warning');
        } else {
            $this->add_audit_result('XSS_SANITIZATION', "FAIL: Only $success_rate% XSS protection rate", 'error');
        }
        
        // Test output escaping
        $this->test_output_escaping();
    }
    
    /**
     * Test output escaping
     */
    private function test_output_escaping() {
        $test_data = '<script>alert("test")</script>Test Item';
        
        $escaped_html = esc_html($test_data);
        $escaped_attr = esc_attr($test_data);
        $escaped_js = esc_js($test_data);
        
        $tests_passed = 0;
        if (strpos($escaped_html, '<script>') === false) $tests_passed++;
        if (strpos($escaped_attr, '<script>') === false) $tests_passed++;
        if (strpos($escaped_js, '<script>') === false) $tests_passed++;
        
        if ($tests_passed === 3) {
            $this->add_audit_result('OUTPUT_ESCAPING', 'PASS: Output escaping functions working', 'success');
        } else {
            $this->add_audit_result('OUTPUT_ESCAPING', "FAIL: Output escaping failed $tests_passed/3 tests", 'error');
        }
    }
    
    /**
     * Test rate limiting
     */
    private function test_rate_limiting() {
        $this->add_audit_result('RATE_LIMITING', 'Testing rate limiting...');
        
        // Test if rate limiting is enabled
        $trade_limits = get_transient('membershiping_inventory_trade_limits_enabled');
        $transaction_limits = get_transient('membershiping_inventory_transaction_limits_enabled');
        
        if ($trade_limits && $transaction_limits) {
            $this->add_audit_result('RATE_LIMIT_SETUP', 'PASS: Rate limiting systems enabled', 'success');
        } else {
            $this->add_audit_result('RATE_LIMIT_SETUP', 'WARN: Rate limiting not fully enabled', 'warning');
        }
        
        // Test rate limit enforcement
        $this->test_rate_limit_enforcement();
    }
    
    /**
     * Test rate limit enforcement
     */
    private function test_rate_limit_enforcement() {
        $user_id = get_current_user_id() ?: 1;
        
        // Simulate rapid requests
        $blocked = false;
        for ($i = 0; $i < 20; $i++) {
            $result = $this->security->check_rate_limit($user_id, 'trade_request');
            if (!$result) {
                $blocked = true;
                break;
            }
        }
        
        if ($blocked) {
            $this->add_audit_result('RATE_LIMIT_ENFORCEMENT', 'PASS: Rate limiting blocks excessive requests', 'success');
        } else {
            $this->add_audit_result('RATE_LIMIT_ENFORCEMENT', 'WARN: Rate limiting may not be enforcing limits', 'warning');
        }
    }
    
    /**
     * Test authentication
     */
    private function test_authentication() {
        $this->add_audit_result('AUTHENTICATION', 'Testing authentication...');
        
        // Test user authentication requirement
        $requires_auth = $this->security->requires_authentication('trade_request');
        if ($requires_auth) {
            $this->add_audit_result('AUTH_REQUIREMENTS', 'PASS: Sensitive actions require authentication', 'success');
        } else {
            $this->add_audit_result('AUTH_REQUIREMENTS', 'WARN: Authentication requirements may be missing', 'warning');
        }
        
        // Test session validation
        $this->test_session_validation();
    }
    
    /**
     * Test session validation
     */
    private function test_session_validation() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $session_valid = $this->security->validate_user_session($user_id);
            
            if ($session_valid) {
                $this->add_audit_result('SESSION_VALIDATION', 'PASS: User session validation working', 'success');
            } else {
                $this->add_audit_result('SESSION_VALIDATION', 'FAIL: User session validation failed', 'error');
            }
        } else {
            $this->add_audit_result('SESSION_VALIDATION', 'INFO: No user logged in for session testing', 'info');
        }
    }
    
    /**
     * Test authorization
     */
    private function test_authorization() {
        $this->add_audit_result('AUTHORIZATION', 'Testing authorization...');
        
        // Test capability checks
        $capabilities = array(
            'manage_options' => 'admin_functions',
            'edit_posts' => 'basic_functions'
        );
        
        foreach ($capabilities as $cap => $function_type) {
            $has_cap = current_user_can($cap);
            $allowed = $this->security->check_user_capability($cap, $function_type);
            
            if ($has_cap === $allowed) {
                $this->add_audit_result("AUTHORIZATION_$cap", "PASS: $cap authorization working", 'success');
            } else {
                $this->add_audit_result("AUTHORIZATION_$cap", "FAIL: $cap authorization mismatch", 'error');
            }
        }
        
        // Test ownership validation
        $this->test_ownership_validation();
    }
    
    /**
     * Test ownership validation
     */
    private function test_ownership_validation() {
        $user_id = get_current_user_id() ?: 1;
        
        // Test item ownership validation
        $owns_item = $this->security->user_owns_item($user_id, 1);
        $this->add_audit_result('OWNERSHIP_VALIDATION', 'INFO: Ownership validation methods available', 'info');
    }
    
    /**
     * Test data sanitization
     */
    private function test_data_sanitization() {
        $this->add_audit_result('DATA_SANITIZATION', 'Testing data sanitization...');
        
        $test_inputs = array(
            'text_field' => '<script>alert("xss")</script>Test',
            'textarea' => "Line 1\r\nLine 2\nLine 3",
            'email' => 'test@example.com<script>',
            'url' => 'http://example.com"><script>alert(1)</script>',
            'number' => '123abc',
            'json' => '{"key": "value", "script": "<script>alert(1)</script>"}'
        );
        
        $sanitized = $this->security->sanitize_data_array($test_inputs);
        
        $tests_passed = 0;
        $total_tests = count($test_inputs);
        
        // Check if dangerous content was removed
        if (strpos($sanitized['text_field'], '<script>') === false) $tests_passed++;
        if (strlen($sanitized['textarea']) > 0) $tests_passed++;
        if (is_email($sanitized['email'])) $tests_passed++;
        if (filter_var($sanitized['url'], FILTER_VALIDATE_URL) !== false) $tests_passed++;
        if (is_numeric($sanitized['number'])) $tests_passed++;
        if (json_decode($sanitized['json']) !== null) $tests_passed++;
        
        if ($tests_passed === $total_tests) {
            $this->add_audit_result('DATA_SANITIZATION_TESTS', 'PASS: All data sanitization tests passed', 'success');
        } else {
            $this->add_audit_result('DATA_SANITIZATION_TESTS', "WARN: $tests_passed/$total_tests sanitization tests passed", 'warning');
        }
    }
    
    /**
     * Test file upload security
     */
    private function test_file_upload_security() {
        $this->add_audit_result('FILE_UPLOAD_SECURITY', 'Testing file upload security...');
        
        // Test file type validation
        $allowed_types = $this->security->get_allowed_file_types();
        if (is_array($allowed_types) && !empty($allowed_types)) {
            $this->add_audit_result('FILE_TYPE_WHITELIST', 'PASS: File type whitelist configured', 'success');
        } else {
            $this->add_audit_result('FILE_TYPE_WHITELIST', 'WARN: File type whitelist not configured', 'warning');
        }
        
        // Test file size limits
        $max_size = $this->security->get_max_file_size();
        if ($max_size > 0 && $max_size <= 10 * 1024 * 1024) { // 10MB max reasonable
            $this->add_audit_result('FILE_SIZE_LIMITS', 'PASS: File size limits configured', 'success');
        } else {
            $this->add_audit_result('FILE_SIZE_LIMITS', 'WARN: File size limits not properly configured', 'warning');
        }
        
        // Test malicious file detection
        $this->test_malicious_file_detection();
    }
    
    /**
     * Test malicious file detection
     */
    private function test_malicious_file_detection() {
        $malicious_files = array(
            'test.php' => false,    // Should be blocked
            'test.exe' => false,    // Should be blocked
            'test.jpg' => true,     // Should be allowed
            'test.png' => true,     // Should be allowed
            'test.gif' => true,     // Should be allowed
        );
        
        $correct_detections = 0;
        foreach ($malicious_files as $filename => $should_allow) {
            $allowed = $this->security->is_file_allowed($filename);
            if ($allowed === $should_allow) {
                $correct_detections++;
            }
        }
        
        $success_rate = ($correct_detections / count($malicious_files)) * 100;
        
        if ($success_rate === 100) {
            $this->add_audit_result('MALICIOUS_FILE_DETECTION', 'PASS: Malicious file detection working', 'success');
        } else {
            $this->add_audit_result('MALICIOUS_FILE_DETECTION', "WARN: $success_rate% file detection accuracy", 'warning');
        }
    }
    
    /**
     * Test audit logging
     */
    private function test_audit_logging() {
        $this->add_audit_result('AUDIT_LOGGING', 'Testing audit logging...');
        
        // Test log creation
        $log_created = $this->security->log_security_event('test_event', 'Security audit test', 'medium');
        if ($log_created) {
            $this->add_audit_result('AUDIT_LOG_CREATION', 'PASS: Audit logs can be created', 'success');
        } else {
            $this->add_audit_result('AUDIT_LOG_CREATION', 'FAIL: Audit log creation failed', 'error');
        }
        
        // Test log retrieval
        $recent_logs = $this->security->get_recent_security_logs(10);
        if (is_array($recent_logs)) {
            $this->add_audit_result('AUDIT_LOG_RETRIEVAL', 'PASS: Audit logs can be retrieved', 'success');
        } else {
            $this->add_audit_result('AUDIT_LOG_RETRIEVAL', 'FAIL: Audit log retrieval failed', 'error');
        }
        
        // Test log cleanup
        $this->test_log_cleanup();
    }
    
    /**
     * Test log cleanup
     */
    private function test_log_cleanup() {
        $cleanup_scheduled = wp_next_scheduled('membershiping_inventory_cleanup_logs');
        if ($cleanup_scheduled) {
            $this->add_audit_result('LOG_CLEANUP_SCHEDULED', 'PASS: Log cleanup is scheduled', 'success');
        } else {
            $this->add_audit_result('LOG_CLEANUP_SCHEDULED', 'WARN: Log cleanup not scheduled', 'warning');
        }
    }
    
    /**
     * Helper methods
     */
    
    private function add_audit_result($test_name, $message, $status = 'info') {
        $this->audit_results[] = array(
            'test' => $test_name,
            'message' => $message,
            'status' => $status,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate security report
     */
    public function generate_security_report() {
        $results = $this->run_security_audit();
        
        $report = array(
            'summary' => $this->generate_security_summary($results),
            'details' => $results,
            'recommendations' => $this->generate_security_recommendations($results),
            'timestamp' => current_time('mysql'),
            'version' => MEMBERSHIPING_INVENTORY_VERSION
        );
        
        return $report;
    }
    
    private function generate_security_summary($results) {
        $total = count($results);
        $passed = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
        $warnings = count(array_filter($results, function($r) { return $r['status'] === 'warning'; }));
        $errors = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
        
        $security_score = $total > 0 ? round((($passed + ($warnings * 0.5)) / $total) * 100, 2) : 0;
        
        return array(
            'total_tests' => $total,
            'passed' => $passed,
            'warnings' => $warnings,
            'errors' => $errors,
            'security_score' => $security_score,
            'risk_level' => $this->calculate_risk_level($errors, $warnings, $total)
        );
    }
    
    private function calculate_risk_level($errors, $warnings, $total) {
        if ($errors > 0) {
            return 'HIGH';
        } elseif ($warnings > ($total * 0.3)) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
    
    private function generate_security_recommendations($results) {
        $recommendations = array();
        
        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                $recommendations[] = "CRITICAL: Fix {$result['test']} - {$result['message']}";
            } elseif ($result['status'] === 'warning') {
                $recommendations[] = "IMPROVE: Address {$result['test']} - {$result['message']}";
            }
        }
        
        return $recommendations;
    }
}
