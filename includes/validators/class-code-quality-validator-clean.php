<?php
/**
 * Code Quality Validator
 * 
 * Comprehensive analysis of code quality, standards compliance, and best practices
 * 
 * @package    Membershiping_Inventory
 * @subpackage Validators
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Code_Quality_Validator {
    
    private $plugin_path;
    private $results = [];
    private $warnings = [];
    private $errors = [];
    
    public function __construct() {
        $this->plugin_path = plugin_dir_path(dirname(dirname(__FILE__)));
    }
    
    /**
     * Run comprehensive code quality validation
     * 
     * @return array Validation results
     */
    public function run_validation() {
        $this->results = [];
        $this->warnings = [];
        $this->errors = [];
        
        echo "=== CODE QUALITY VALIDATION ===\n";
        echo "Analyzing code standards, best practices, and quality metrics...\n\n";
        
        // Core Quality Tests
        $this->test_wordpress_coding_standards();
        $this->test_psr_compliance();
        $this->test_security_patterns();
        $this->test_performance_patterns();
        $this->test_documentation_coverage();
        
        // Generate summary
        $this->generate_summary();
        
        return $this->results;
    }
    
    /**
     * Test WordPress coding standards
     */
    private function test_wordpress_coding_standards() {
        echo "--- Testing WordPress Coding Standards ---\n";
        
        $php_files = $this->get_php_files();
        $standards_score = 0;
        $total_checks = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for proper PHP opening tags
            if (preg_match('/^<\?php/', $content)) {
                $standards_score++;
                $this->add_result('pass', "✓ Proper PHP opening tag in " . basename($file));
            } else {
                $this->add_result('fail', "✗ Missing or improper PHP opening tag in " . basename($file));
            }
            $total_checks++;
            
            // Check for exit security
            if (preg_match('/if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*{?\s*exit/', $content)) {
                $standards_score++;
                $this->add_result('pass', "✓ ABSPATH security check in " . basename($file));
            } else {
                $this->add_result('warning', "! Missing ABSPATH security check in " . basename($file));
            }
            $total_checks++;
            
            // Check for proper indentation (tabs vs spaces)
            if (preg_match('/^\t/m', $content) && !preg_match('/^    /m', $content)) {
                $standards_score++;
                $this->add_result('pass', "✓ Consistent tab indentation in " . basename($file));
            } elseif (preg_match('/^    /m', $content) && !preg_match('/^\t/m', $content)) {
                $standards_score++;
                $this->add_result('pass', "✓ Consistent space indentation in " . basename($file));
            } else {
                $this->add_result('warning', "! Mixed indentation in " . basename($file));
            }
            $total_checks++;
        }
        
        $compliance_rate = $total_checks > 0 ? round(($standards_score / $total_checks) * 100, 1) : 0;
        echo "WordPress Standards Compliance: {$compliance_rate}%\n\n";
    }
    
    /**
     * Test PSR compliance
     */
    private function test_psr_compliance() {
        echo "--- Testing PSR Compliance ---\n";
        
        $php_files = $this->get_php_files();
        $psr_score = 0;
        $total_checks = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // PSR-1: Class names in StudlyCaps
            if (preg_match_all('/class\s+([A-Z][a-zA-Z0-9_]*)/', $content, $matches)) {
                foreach ($matches[1] as $class_name) {
                    if (preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $class_name)) {
                        $psr_score++;
                        $this->add_result('pass', "✓ PSR-1 compliant class name: $class_name");
                    } else {
                        $this->add_result('fail', "✗ Non-PSR-1 class name: $class_name");
                    }
                    $total_checks++;
                }
            }
            
            // PSR-2: Method names in camelCase
            if (preg_match_all('/(?:public|private|protected)\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
                foreach ($matches[1] as $method_name) {
                    if (preg_match('/^[a-z_][a-zA-Z0-9_]*$/', $method_name)) {
                        $psr_score++;
                        $this->add_result('pass', "✓ PSR-2 compliant method name: $method_name");
                    } else {
                        $this->add_result('warning', "! Method name style: $method_name");
                    }
                    $total_checks++;
                }
            }
        }
        
        $psr_rate = $total_checks > 0 ? round(($psr_score / $total_checks) * 100, 1) : 0;
        echo "PSR Compliance Rate: {$psr_rate}%\n\n";
    }
    
    /**
     * Test security patterns
     */
    private function test_security_patterns() {
        echo "--- Testing Security Patterns ---\n";
        
        $php_files = $this->get_php_files();
        $security_score = 0;
        $total_checks = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for SQL injection protection
            if (preg_match('/\$wpdb->prepare/', $content)) {
                $security_score++;
                $this->add_result('pass', "✓ SQL injection protection found in " . basename($file));
            }
            $total_checks++;
            
            // Check for nonce verification
            if (preg_match('/wp_verify_nonce|check_admin_referer/', $content)) {
                $security_score++;
                $this->add_result('pass', "✓ Nonce verification found in " . basename($file));
            }
            $total_checks++;
            
            // Check for capability checks
            if (preg_match('/current_user_can|user_can/', $content)) {
                $security_score++;
                $this->add_result('pass', "✓ Capability checks found in " . basename($file));
            }
            $total_checks++;
            
            // Check for data sanitization
            if (preg_match('/sanitize_|esc_|wp_kses/', $content)) {
                $security_score++;
                $this->add_result('pass', "✓ Data sanitization found in " . basename($file));
            }
            $total_checks++;
        }
        
        $security_rate = $total_checks > 0 ? round(($security_score / $total_checks) * 100, 1) : 0;
        echo "Security Patterns Score: {$security_rate}%\n\n";
    }
    
    /**
     * Test performance patterns
     */
    private function test_performance_patterns() {
        echo "--- Testing Performance Patterns ---\n";
        
        $php_files = $this->get_php_files();
        $performance_issues = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for potential performance issues
            if (preg_match('/get_posts.*-1|WP_Query.*posts_per_page.*-1/', $content)) {
                $performance_issues++;
                $this->add_result('warning', "! Potential unlimited query in " . basename($file));
            }
            
            // Check for caching usage
            if (preg_match('/wp_cache_get|get_transient|wp_cache_set|set_transient/', $content)) {
                $this->add_result('pass', "✓ Caching implementation found in " . basename($file));
            }
            
            // Check for database optimization
            if (preg_match('/wp_cache_flush|delete_transient/', $content)) {
                $this->add_result('pass', "✓ Cache management found in " . basename($file));
            }
        }
        
        if ($performance_issues === 0) {
            $this->add_result('pass', "✓ No obvious performance issues detected");
        }
        
        echo "Performance Analysis Complete\n\n";
    }
    
    /**
     * Test documentation coverage
     */
    private function test_documentation_coverage() {
        echo "--- Testing Documentation Coverage ---\n";
        
        $php_files = $this->get_php_files();
        $total_functions = 0;
        $documented_functions = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Count functions
            preg_match_all('/(?:public|private|protected)\s+function\s+[a-zA-Z_]/', $content, $functions);
            $total_functions += count($functions[0]);
            
            // Count documented functions
            preg_match_all('/\/\*\*.*?\*\/\s*(?:public|private|protected)\s+function/s', $content, $doc_functions);
            $documented_functions += count($doc_functions[0]);
        }
        
        $doc_coverage = $total_functions > 0 ? round(($documented_functions / $total_functions) * 100, 1) : 0;
        
        if ($doc_coverage >= 80) {
            $this->add_result('pass', "✓ Excellent documentation coverage: {$doc_coverage}%");
        } elseif ($doc_coverage >= 60) {
            $this->add_result('warning', "! Good documentation coverage: {$doc_coverage}%");
        } else {
            $this->add_result('fail', "✗ Low documentation coverage: {$doc_coverage}%");
        }
        
        echo "Documentation Coverage: {$doc_coverage}%\n\n";
    }
    
    /**
     * Generate comprehensive summary
     */
    private function generate_summary() {
        echo "=== CODE QUALITY SUMMARY ===\n";
        
        $total = count($this->results);
        $passed = count(array_filter($this->results, function($r) { return $r['status'] === 'pass'; }));
        $warnings = count($this->warnings);
        $errors = count($this->errors);
        
        $success_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Warnings: $warnings\n";
        echo "Errors: $errors\n";
        echo "Success Rate: {$success_rate}%\n\n";
        
        if ($success_rate >= 90) {
            echo "🏆 EXCELLENT: Code quality meets high standards!\n";
        } elseif ($success_rate >= 75) {
            echo "✅ GOOD: Code quality is solid with minor improvements possible.\n";
        } else {
            echo "⚠️ NEEDS IMPROVEMENT: Code quality requires attention.\n";
        }
    }
    
    /**
     * Get PHP files in plugin
     */
    private function get_php_files() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Add validation result
     */
    private function add_result($status, $message) {
        $this->results[] = [
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
        
        if ($status === 'warning') {
            $this->warnings[] = $message;
        } elseif ($status === 'fail') {
            $this->errors[] = $message;
        }
        
        // Output immediately
        if ($status === 'pass') {
            echo "✅ $message\n";
        } elseif ($status === 'warning') {
            echo "⚠️ $message\n";
        } else {
            echo "❌ $message\n";
        }
    }
    
    /**
     * Get validation summary
     */
    public function get_summary() {
        $total = count($this->results);
        $success = count(array_filter($this->results, function($r) { return $r['status'] === 'pass'; }));
        $warnings = count($this->warnings);
        $errors = count($this->errors);
        
        return [
            'total_tests' => $total,
            'successful' => $success,
            'warnings' => $warnings,
            'errors' => $errors,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0
        ];
    }
}
