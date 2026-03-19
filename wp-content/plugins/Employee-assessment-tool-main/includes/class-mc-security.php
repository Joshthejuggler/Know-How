<?php
/**
 * Security and Validation Utilities for Micro-Coach Platform
 * 
 * @package MicroCoach
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

class MC_Security {
    
    /**
     * Verify user has required capabilities for quiz operations
     * 
     * @param int $user_id User ID to check permissions for
     * @param string $operation Type of operation (view, edit, delete)
     * @return bool
     */
    public static function verify_user_permissions($user_id = null, $operation = 'edit') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        
        // User can always access their own data
        if ($current_user_id === $user_id) {
            return true;
        }
        
        // Admins can access any user's data
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // For operations on other users, require edit_users capability
        if ($operation === 'edit' || $operation === 'delete') {
            return current_user_can('edit_users');
        }
        
        return false;
    }
    
    /**
     * Validate and sanitize quiz results data
     * 
     * @param array $results Raw results data
     * @param string $quiz_type Type of quiz (mi, cdt, bartle)
     * @return array Sanitized results or WP_Error on validation failure
     */
    public static function validate_quiz_results($results, $quiz_type) {
        if (!is_array($results)) {
            return new WP_Error('invalid_data', 'Results must be an array');
        }
        
        $sanitized = [];
        
        // Common validation
        if (isset($results['completed_at'])) {
            $sanitized['completed_at'] = absint($results['completed_at']);
        } else {
            $sanitized['completed_at'] = time();
        }
        
        // Quiz-specific validation
        switch ($quiz_type) {
            case 'mi':
                return self::validate_mi_results($results, $sanitized);
            case 'cdt':
                return self::validate_cdt_results($results, $sanitized);
            case 'bartle':
                return self::validate_bartle_results($results, $sanitized);
            default:
                return new WP_Error('invalid_quiz_type', 'Unknown quiz type');
        }
    }
    
    /**
     * Validate MI quiz results
     */
    private static function validate_mi_results($results, $sanitized) {
        // Validate scores array
        if (isset($results['scores']) && is_array($results['scores'])) {
            $sanitized['scores'] = [];
            foreach ($results['scores'] as $intelligence => $score) {
                $intelligence = sanitize_key($intelligence);
                $score = absint($score);
                if ($score >= 0 && $score <= 100) {
                    $sanitized['scores'][$intelligence] = $score;
                }
            }
        }
        
        // Validate sorted scores
        if (isset($results['sortedScores']) && is_array($results['sortedScores'])) {
            $sanitized['sortedScores'] = [];
            foreach ($results['sortedScores'] as $item) {
                if (is_array($item) && count($item) === 2) {
                    $intelligence = sanitize_key($item[0]);
                    $score = absint($item[1]);
                    if ($score >= 0 && $score <= 100) {
                        $sanitized['sortedScores'][] = [$intelligence, $score];
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate CDT quiz results
     */
    private static function validate_cdt_results($results, $sanitized) {
        if (isset($results['ageGroup'])) {
            $age_groups = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
            $age_group = sanitize_text_field($results['ageGroup']);
            if (in_array($age_group, $age_groups, true)) {
                $sanitized['ageGroup'] = $age_group;
            }
        }
        
        if (isset($results['sortedScores']) && is_array($results['sortedScores'])) {
            $sanitized['sortedScores'] = [];
            foreach ($results['sortedScores'] as $item) {
                if (is_array($item) && count($item) === 2) {
                    $dimension = sanitize_key($item[0]);
                    $score = absint($item[1]);
                    if ($score >= 1 && $score <= 5) {
                        $sanitized['sortedScores'][] = [$dimension, $score];
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate Bartle quiz results
     */
    private static function validate_bartle_results($results, $sanitized) {
        // Enhanced flow flag
        if (isset($results['enhanced_flow'])) {
            $sanitized['enhanced_flow'] = (bool) $results['enhanced_flow'];
        }
        
        // Validate sorted scores
        if (isset($results['sortedScores']) && is_array($results['sortedScores'])) {
            $valid_types = ['explorer', 'achiever', 'socializer', 'strategist'];
            $sanitized['sortedScores'] = [];
            
            foreach ($results['sortedScores'] as $item) {
                if (is_array($item) && count($item) === 2) {
                    $type = sanitize_key($item[0]);
                    $score = absint($item[1]);
                    if (in_array($type, $valid_types, true) && $score >= 0 && $score <= 100) {
                        $sanitized['sortedScores'][] = [$type, $score];
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize AI experiment data
     * 
     * @param array $experiment_data Raw experiment data
     * @return array Sanitized experiment data
     */
    public static function sanitize_experiment_data($experiment_data) {
        if (!is_array($experiment_data)) {
            return [];
        }
        
        $sanitized = [];
        
        // Basic fields
        $text_fields = ['title', 'rationale', 'archetype'];
        foreach ($text_fields as $field) {
            if (isset($experiment_data[$field])) {
                $sanitized[$field] = sanitize_text_field($experiment_data[$field]);
            }
        }
        
        // Steps array
        if (isset($experiment_data['steps']) && is_array($experiment_data['steps'])) {
            $sanitized['steps'] = array_map('sanitize_text_field', $experiment_data['steps']);
        }
        
        // Success criteria array
        if (isset($experiment_data['success_criteria']) && is_array($experiment_data['success_criteria'])) {
            $sanitized['success_criteria'] = array_map('sanitize_text_field', $experiment_data['success_criteria']);
        }
        
        // Numeric fields
        $numeric_fields = ['effort_time', 'effort_budget', 'risk_level'];
        foreach ($numeric_fields as $field) {
            if (isset($experiment_data[$field])) {
                $sanitized[$field] = floatval($experiment_data[$field]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Rate limiting for AI API calls
     * 
     * @param int $user_id User ID
     * @param string $endpoint Endpoint being called
     * @param int $max_calls Maximum calls per time period
     * @param int $time_window Time window in seconds
     * @return bool True if within limits, false if rate limited
     */
    public static function check_rate_limit($user_id, $endpoint, $max_calls = 10, $time_window = 3600) {
        $cache_key = "mc_rate_limit_{$user_id}_{$endpoint}";
        $calls = get_transient($cache_key) ?: [];
        
        $now = time();
        
        // Remove old calls outside the time window
        $calls = array_filter($calls, function($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) < $time_window;
        });
        
        // Check if limit exceeded
        if (count($calls) >= $max_calls) {
            return false;
        }
        
        // Add current call
        $calls[] = $now;
        set_transient($cache_key, $calls, $time_window);
        
        return true;
    }
    
    /**
     * Verify AJAX nonce and permissions
     * 
     * @param string $action Action name
     * @param string $capability Required capability
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function verify_ajax_request($action, $capability = 'read') {
        // Check nonce
        $nonce_field = $action . '_nonce';
        if (!isset($_POST['nonce']) && !isset($_POST['_ajax_nonce'])) {
            return new WP_Error('missing_nonce', 'Security token missing');
        }
        
        $nonce = $_POST['nonce'] ?? $_POST['_ajax_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, $action)) {
            return new WP_Error('invalid_nonce', 'Security token invalid');
        }
        
        // Check capability
        if (!current_user_can($capability)) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions');
        }
        
        return true;
    }
    
    /**
     * Log security events
     * 
     * @param string $event Event type
     * @param string $message Event message
     * @param array $context Additional context
     */
    public static function log_security_event($event, $message, $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ];
        
        error_log('MC_Security: ' . wp_json_encode($log_entry));
    }
}