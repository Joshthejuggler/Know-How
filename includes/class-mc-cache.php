<?php
/**
 * Caching and Performance Utilities for Micro-Coach Platform
 * 
 * @package MicroCoach
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

class MC_Cache {
    
    const CACHE_GROUP = 'micro_coach';
    const DEFAULT_EXPIRY = 3600; // 1 hour
    
    /**
     * Get user profile data with caching
     * 
     * @param int $user_id User ID
     * @param bool $force_refresh Force cache refresh
     * @return array User profile data
     */
    public static function get_user_profile($user_id, $force_refresh = false) {
        $cache_key = "user_profile_{$user_id}";
        
        if (!$force_refresh) {
            $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
            if (false !== $cached) {
                return $cached;
            }
        }
        
        // Build profile data
        $profile = [];
        
        // MI Results
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        if (!empty($mi_results) && is_array($mi_results)) {
            $profile['mi_results'] = $mi_results;
        }
        
        // CDT Results  
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        if (!empty($cdt_results) && is_array($cdt_results)) {
            $profile['cdt_results'] = $cdt_results;
        }
        
        // Bartle Results
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
        if (!empty($bartle_results) && is_array($bartle_results)) {
            $profile['bartle_results'] = $bartle_results;
        }
        
        // Cache for 1 hour
        wp_cache_set($cache_key, $profile, self::CACHE_GROUP, self::DEFAULT_EXPIRY);
        
        return $profile;
    }
    
    /**
     * Clear user profile cache
     * 
     * @param int $user_id User ID
     */
    public static function clear_user_profile($user_id) {
        $cache_key = "user_profile_{$user_id}";
        wp_cache_delete($cache_key, self::CACHE_GROUP);
    }
    
    /**
     * Get AI response with caching
     * 
     * @param string $prompt_hash Hash of the prompt
     * @param callable $api_callback Callback to make API call
     * @param int $expiry Cache expiry in seconds
     * @return mixed API response
     */
    public static function get_ai_response($prompt_hash, $api_callback, $expiry = null) {
        if (null === $expiry) {
            $expiry = self::DEFAULT_EXPIRY;
        }
        
        $cache_key = "ai_response_{$prompt_hash}";
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Make API call
        $response = call_user_func($api_callback);
        
        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, $expiry);
        }
        
        return $response;
    }
    
    /**
     * Get cached quiz questions
     * 
     * @param string $quiz_type Quiz type (mi, cdt, bartle)
     * @return array Questions data
     */
    public static function get_quiz_questions($quiz_type) {
        $cache_key = "quiz_questions_{$quiz_type}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $questions = [];
        
        switch ($quiz_type) {
            case 'mi':
                $file_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                break;
            case 'cdt':
                $file_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                break;
            case 'bartle':
                $file_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';
                break;
            default:
                return [];
        }
        
        if (file_exists($file_path)) {
            ob_start();
            include $file_path;
            ob_end_clean();
            
            // These variables should be set by the included file
            if (isset($mi_questions)) $questions = $mi_questions;
            if (isset($cdt_questions)) $questions = $cdt_questions;
            if (isset($bartle_questions)) $questions = $bartle_questions;
        }
        
        // Cache for 24 hours (questions rarely change)
        wp_cache_set($cache_key, $questions, self::CACHE_GROUP, DAY_IN_SECONDS);
        
        return $questions;
    }
    
    /**
     * Get lab mode content libraries with caching
     * 
     * @param string $library Library name (mi_combinations, archetype_templates)
     * @return array Library content
     */
    public static function get_lab_library($library) {
        $cache_key = "lab_library_{$library}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $file_path = MC_QUIZ_PLATFORM_PATH . "assets/lab_libraries/{$library}.json";
        $content = [];
        
        if (file_exists($file_path)) {
            $json_content = file_get_contents($file_path);
            $content = json_decode($json_content, true) ?: [];
        }
        
        // Cache for 24 hours (library content rarely changes)
        wp_cache_set($cache_key, $content, self::CACHE_GROUP, DAY_IN_SECONDS);
        
        return $content;
    }
    
    /**
     * Get dashboard data with caching
     * 
     * @param int $user_id User ID
     * @return array Dashboard data
     */
    public static function get_dashboard_data($user_id) {
        $cache_key = "dashboard_data_{$user_id}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $data = [];
        $quizzes = Micro_Coach_Core::get_quizzes();
        
        // Build activity feed
        $activity_feed = [];
        foreach ($quizzes as $id => $quiz) {
            if (!empty($quiz['results_meta_key'])) {
                $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                if (!empty($results) && is_array($results) && isset($results['completed_at'])) {
                    $activity_feed[] = [
                        'quiz_id'    => $id,
                        'quiz_title' => $quiz['title'],
                        'timestamp'  => $results['completed_at'],
                        'results'    => $results,
                    ];
                }
            }
        }
        
        // Add account creation
        $user_data = get_userdata($user_id);
        if ($user_data) {
            $activity_feed[] = [
                'quiz_id'    => 'account_creation',
                'quiz_title' => 'Created your account',
                'timestamp'  => strtotime($user_data->user_registered),
                'results'    => [],
            ];
        }
        
        // Sort by timestamp descending
        usort($activity_feed, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        $data['activity_feed'] = $activity_feed;
        $data['completion_status'] = [];
        
        // Calculate completion status
        foreach ($quizzes as $id => $quiz) {
            $data['completion_status'][$id] = !empty($quiz['results_meta_key']) && 
                                              !empty(get_user_meta($user_id, $quiz['results_meta_key'], true));
        }
        
        // Add funnel data if MC_Funnel class exists
        if (class_exists('MC_Funnel')) {
            $data['funnel_config'] = MC_Funnel::get_config();
            $data['funnel_completion'] = MC_Funnel::get_completion_status($user_id);
            $data['funnel_unlock'] = MC_Funnel::get_unlock_status($user_id);
        }
        
        // Cache for 15 minutes
        wp_cache_set($cache_key, $data, self::CACHE_GROUP, 900);
        
        return $data;
    }
    
    /**
     * Clear dashboard cache when user data changes
     * 
     * @param int $user_id User ID
     */
    public static function clear_dashboard_cache($user_id) {
        $cache_keys = [
            "dashboard_data_{$user_id}",
            "user_profile_{$user_id}"
        ];
        
        foreach ($cache_keys as $key) {
            wp_cache_delete($key, self::CACHE_GROUP);
        }
    }
    
    /**
     * Preload critical data for dashboard
     * 
     * @param int $user_id User ID
     */
    public static function preload_dashboard_data($user_id) {
        // Preload in background to warm cache
        self::get_user_profile($user_id);
        self::get_dashboard_data($user_id);
    }
    
    /**
     * Clear all plugin caches
     */
    public static function flush_all_caches() {
        wp_cache_flush_group(self::CACHE_GROUP);
        
        // Clear transients related to AI responses
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mc_%' OR option_name LIKE '_transient_timeout_mc_%'");
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public static function get_cache_stats() {
        // This would require object cache backend that supports stats
        // For now, return basic info
        return [
            'cache_enabled' => wp_using_ext_object_cache(),
            'cache_type' => wp_using_ext_object_cache() ? 'external' : 'internal',
            'group' => self::CACHE_GROUP
        ];
    }
}