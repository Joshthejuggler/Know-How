<?php
/**
 * Test script for debugging the Custom Request AJAX endpoint
 * 
 * This script can be run from the WordPress admin or as a standalone test
 * to verify that the AJAX endpoint is properly registered and functioning.
 * 
 * Usage: Add this to your WordPress plugin directory and call it via browser
 * or include it in another PHP file for debugging.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress context, load WordPress
    require_once(__DIR__ . '/../../../../../../wp-config.php');
}

class CustomRequestTester {
    
    public function __construct() {
        add_action('wp_ajax_test_lab_iterate', array($this, 'test_ajax_endpoint'));
        add_action('wp_ajax_nopriv_test_lab_iterate', array($this, 'test_ajax_endpoint'));
    }
    
    /**
     * Test the AJAX endpoint registration
     */
    public function test_ajax_endpoint() {
        $results = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'tests' => array()
        );
        
        // Test 1: Check if main plugin class exists
        $results['tests']['plugin_class_exists'] = class_exists('Micro_Coach_AI_Lab_Mode');
        
        // Test 2: Check if AJAX action is registered
        global $wp_filter;
        $ajax_actions = isset($wp_filter['wp_ajax_mc_lab_iterate']) ? 
            count($wp_filter['wp_ajax_mc_lab_iterate']->callbacks) : 0;
        $results['tests']['ajax_action_registered'] = $ajax_actions > 0;
        $results['tests']['ajax_action_count'] = $ajax_actions;
        
        // Test 3: Check current user permissions
        $results['tests']['user_logged_in'] = is_user_logged_in();
        $results['tests']['current_user_id'] = get_current_user_id();
        $results['tests']['user_can_manage_options'] = current_user_can('manage_options');
        
        // Test 4: Check if OpenAI API key is configured
        if (class_exists('Micro_Coach_AI')) {
            $api_key = Micro_Coach_AI::get_openai_api_key();
            $results['tests']['openai_api_key_configured'] = !empty($api_key);
            $results['tests']['openai_api_key_length'] = !empty($api_key) ? strlen($api_key) : 0;
        } else {
            $results['tests']['openai_api_key_configured'] = false;
            $results['tests']['main_plugin_class_missing'] = true;
        }
        
        // Test 5: Simulate a custom request payload
        $sample_experiment = array(
            'title' => 'Test Experiment',
            'steps' => array('Step 1: Test', 'Step 2: Verify'),
            'successCriteria' => array('Success 1', 'Success 2'),
            'effort' => array('timeHours' => 2, 'budgetUSD' => 0),
            'archetype' => 'Test',
            'rationale' => 'Testing purposes'
        );
        
        $sample_modifier = array(
            'kind' => 'Custom',
            'value' => 'Make this experiment more collaborative and team-focused'
        );
        
        $sample_context = array(
            'mi_top3' => array(),
            'cdt_bottom2' => array(),
            'curiosities' => array(),
            'roleModels' => array()
        );
        
        $results['tests']['sample_data'] = array(
            'experiment_valid' => is_array($sample_experiment) && !empty($sample_experiment['title']),
            'modifier_valid' => is_array($sample_modifier) && !empty($sample_modifier['value']),
            'context_valid' => is_array($sample_context)
        );
        
        // Test 6: Check WordPress environment
        $results['tests']['wp_environment'] = array(
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'is_admin' => is_admin(),
            'doing_ajax' => wp_doing_ajax(),
            'current_url' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        );
        
        // Test 7: Check for common issues
        $results['tests']['common_issues'] = array(
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'curl_enabled' => function_exists('curl_init'),
            'json_enabled' => function_exists('json_encode'),
            'openssl_enabled' => extension_loaded('openssl')
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Run tests programmatically
     */
    public static function run_tests() {
        $tester = new self();
        
        echo "<h2>Custom Request AJAX Endpoint Test Results</h2>";
        echo "<pre>";
        
        // Simulate AJAX environment
        $_POST['action'] = 'test_lab_iterate';
        
        ob_start();
        $tester->test_ajax_endpoint();
        $output = ob_get_clean();
        
        echo htmlspecialchars($output);
        echo "</pre>";
        
        // Additional direct tests
        echo "<h3>Direct Tests</h3>";
        echo "<ul>";
        
        if (class_exists('Micro_Coach_AI_Lab_Mode')) {
            echo "<li>✅ Micro_Coach_AI_Lab_Mode class exists</li>";
            
            $lab_mode = new Micro_Coach_AI_Lab_Mode();
            if (method_exists($lab_mode, 'ajax_iterate')) {
                echo "<li>✅ ajax_iterate method exists</li>";
            } else {
                echo "<li>❌ ajax_iterate method missing</li>";
            }
        } else {
            echo "<li>❌ Micro_Coach_AI_Lab_Mode class not found</li>";
        }
        
        echo "</ul>";
    }
}

// If accessed directly (not via AJAX), run tests
if (!wp_doing_ajax() && isset($_GET['run_tests'])) {
    CustomRequestTester::run_tests();
} else {
    // Register for AJAX testing
    new CustomRequestTester();
}

// Usage instructions
if (!wp_doing_ajax() && !isset($_GET['run_tests'])) {
    echo "<h2>Custom Request Debug Tester</h2>";
    echo "<p>This script helps debug the Custom Request functionality in the Lab Mode plugin.</p>";
    echo "<h3>Usage:</h3>";
    echo "<ul>";
    echo "<li><a href='?run_tests=1'>Run Direct Tests</a></li>";
    echo "<li>Use browser developer tools to test AJAX endpoint: <code>wp-admin/admin-ajax.php?action=test_lab_iterate</code></li>";
    echo "</ul>";
}
?>