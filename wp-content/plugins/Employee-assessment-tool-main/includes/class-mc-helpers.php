<?php
/**
 * Helper Utilities for Micro-Coach Platform
 * 
 * @package MicroCoach
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

class MC_Helpers {
    
    /**
     * Find page URL by shortcode with caching
     * 
     * @param string $shortcode_tag Shortcode to search for
     * @return string|null URL or null if not found
     */
    public static function find_page_by_shortcode($shortcode_tag) {
        if (empty($shortcode_tag)) {
            return null;
        }
        
        $transient_key = 'mc_page_url_for_' . sanitize_key($shortcode_tag);
        $cached_url = get_transient($transient_key);
        
        if (false !== $cached_url) {
            return $cached_url;
        }
        
        $query = new WP_Query([
            'post_type' => ['page', 'post'], 
            'post_status' => 'publish', 
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mc_contains_shortcode_' . $shortcode_tag,
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        $url = null;
        
        // If we don't have meta cached, fall back to content search
        if (!$query->have_posts()) {
            $query = new WP_Query([
                'post_type' => ['page', 'post'], 
                'post_status' => 'publish', 
                'posts_per_page' => -1,
                's' => '[' . $shortcode_tag
            ]);
        }
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                if (has_shortcode($post->post_content, $shortcode_tag)) {
                    $url = get_permalink($post->ID);
                    // Cache the shortcode presence for faster future lookups
                    update_post_meta($post->ID, '_mc_contains_shortcode_' . $shortcode_tag, 1);
                    break;
                }
            }
        }
        
        // Cache for 1 day
        set_transient($transient_key, $url, DAY_IN_SECONDS);
        
        return $url;
    }
    
    /**
     * Clear shortcode page cache
     * 
     * @param string $shortcode_tag Shortcode tag to clear cache for
     */
    public static function clear_shortcode_page_cache($shortcode_tag) {
        if (empty($shortcode_tag)) {
            return;
        }
        
        $transient_key = 'mc_page_url_for_' . sanitize_key($shortcode_tag);
        delete_transient($transient_key);
    }
    
    /**
     * Clear all shortcode page caches
     */
    public static function clear_all_shortcode_caches() {
        $quizzes = Micro_Coach_Core::get_quizzes();
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['shortcode'])) {
                self::clear_shortcode_page_cache($quiz['shortcode']);
            }
        }
    }
    
    /**
     * Get user's friendly name
     * 
     * @param WP_User|int $user User object or ID
     * @param string $fallback Fallback name if no display name
     * @return string
     */
    public static function get_user_friendly_name($user = null, $fallback = 'Friend') {
        if (is_numeric($user)) {
            $user = get_userdata($user);
        }
        
        if (!$user instanceof WP_User) {
            $user = wp_get_current_user();
        }
        
        if (!$user->exists()) {
            return $fallback;
        }
        
        if (!empty($user->first_name)) {
            return $user->first_name;
        }
        
        if (!empty($user->display_name)) {
            return $user->display_name;
        }
        
        return $fallback;
    }
    
    /**
     * Generate random greeting message
     * 
     * @param string $name User's name
     * @return string
     */
    public static function get_random_greeting($name = 'Friend') {
        $greetings = [
            "Welcome back, {$name} — what will you discover today?",
            "Good to see you, {$name}. Your journey continues.",
            "Hi {$name}, ready for another step in self-discovery?",
            "{$name}, small steps lead to big insights.",
            "Hello {$name}, your growth journey continues.",
            "Welcome back, {$name}. Ready to explore more about yourself?"
        ];
        
        return $greetings[array_rand($greetings)];
    }
    
    /**
     * Sanitize quiz results based on quiz type
     * 
     * @param array $results Raw results
     * @param string $quiz_type Quiz type (mi, cdt, bartle)
     * @return array|WP_Error Sanitized results or error
     */
    public static function sanitize_quiz_results($results, $quiz_type) {
        return MC_Security::validate_quiz_results($results, $quiz_type);
    }
    
    /**
     * Calculate completion percentage
     * 
     * @param array $completion_status Array of completion statuses
     * @return int Percentage (0-100)
     */
    public static function calculate_completion_percentage($completion_status) {
        if (empty($completion_status)) {
            return 0;
        }
        
        $total_quizzes = count($completion_status);
        $completed_quizzes = count(array_filter($completion_status));
        
        return ($total_quizzes > 0) ? round(($completed_quizzes / $total_quizzes) * 100) : 0;
    }
    
    /**
     * Get next incomplete quiz
     * 
     * @param array $quizzes Available quizzes
     * @param array $completion_status Completion status
     * @return array|null Next quiz or null if all complete
     */
    public static function get_next_incomplete_quiz($quizzes, $completion_status) {
        foreach ($quizzes as $id => $quiz) {
            // Check if dependencies are met
            $dependency_met = true;
            if (!empty($quiz['depends_on'])) {
                $dependency_met = $completion_status[$quiz['depends_on']] ?? false;
            }
            
            // If dependencies are met and quiz is not complete
            if ($dependency_met && !($completion_status[$id] ?? false)) {
                return array_merge($quiz, ['id' => $id]);
            }
        }
        
        return null;
    }
    
    /**
     * Enhanced Flow Detection
     * 
     * @param int $user_id User ID
     * @return bool True if user should see enhanced flow
     */
    public static function is_enhanced_flow_user($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
        $ai_unlocked = get_user_meta($user_id, 'ai_coach_unlocked', true);
        
        return !empty($bartle_results['enhanced_flow']) && empty($ai_unlocked);
    }
    
    /**
     * Format human readable time difference
     * 
     * @param int $timestamp Unix timestamp
     * @return string Human readable time
     */
    public static function human_time_diff_enhanced($timestamp) {
        $time_diff = time() - $timestamp;
        
        if ($time_diff < MINUTE_IN_SECONDS) {
            return 'just now';
        }
        
        if ($time_diff < HOUR_IN_SECONDS) {
            $minutes = floor($time_diff / MINUTE_IN_SECONDS);
            return $minutes === 1 ? '1 minute' : $minutes . ' minutes';
        }
        
        if ($time_diff < DAY_IN_SECONDS) {
            $hours = floor($time_diff / HOUR_IN_SECONDS);
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }
        
        if ($time_diff < WEEK_IN_SECONDS) {
            $days = floor($time_diff / DAY_IN_SECONDS);
            return $days === 1 ? '1 day' : $days . ' days';
        }
        
        return human_time_diff($timestamp);
    }

    /**
     * Get the absolute URL for the site's logo.
     * Uses the Custom Logo if set, otherwise falls back to the Site Icon,
     * and finally returns null if neither is available.
     *
     * @return string|null
     */
    public static function logo_url() {
        // Custom Logo (Customizer)
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($url) return $url;
        }
        // Site Icon (favicon) fallback
        $site_icon = get_site_icon_url();
        if (!empty($site_icon)) {
            return $site_icon;
        }
        return null;
    }

    /**
     * Render a logo <img> tag with sensible defaults.
     * Example: echo MC_Helpers::logo_img(['class' => 'site-logo', 'alt' => 'Brand']);
     *
     * @param array $attrs
     * @return string
     */
    public static function logo_img($attrs = []) {
        $src = self::logo_url();
        if (!$src) return '';
        $defaults = [
            'class' => 'site-logo',
            'alt'   => get_bloginfo('name'),
            'height'=> ''
        ];
        $a = wp_parse_args($attrs, $defaults);
        $height_attr = $a['height'] !== '' ? ' height="' . esc_attr($a['height']) . '"' : '';
        return '<img src="' . esc_url($src) . '" alt="' . esc_attr($a['alt']) . '" class="' . esc_attr($a['class']) . '"' . $height_attr . ' />';
    }
    
    /**
     * Get plugin version
     * 
     * @return string Version number
     */
    public static function get_plugin_version() {
        return defined('MC_QUIZ_PLATFORM_VERSION') ? MC_QUIZ_PLATFORM_VERSION : '1.0.0';
    }
    
    /**
     * Check if user has completed all core assessments
     * 
     * @param int $user_id User ID
     * @return bool True if all assessments complete
     */
    public static function has_completed_all_assessments($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $required_meta_keys = [
            'miq_quiz_results',
            'cdt_quiz_results', 
            'bartle_quiz_results'
        ];
        
        foreach ($required_meta_keys as $meta_key) {
            $results = get_user_meta($user_id, $meta_key, true);
            if (empty($results) || !is_array($results)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Log plugin activity (if WP_DEBUG_LOG is enabled)
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     * @param array $context Additional context
     */
    public static function log($message, $level = 'info', $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'plugin_version' => self::get_plugin_version()
        ];
        
        error_log('MC_Platform: ' . wp_json_encode($log_entry));
    }
    
    /**
     * Get system information for debugging
     * 
     * @return array System information
     */
    public static function get_system_info() {
        global $wpdb;
        
        return [
            'plugin_version' => self::get_plugin_version(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'memory_limit' => ini_get('memory_limit'),
            'cache_enabled' => wp_using_ext_object_cache(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'registered_quizzes' => count(Micro_Coach_Core::get_quizzes())
        ];
    }
}
