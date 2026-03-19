<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Helper class for managing the quiz funnel configuration and state
 */
class MC_Funnel
{
    const OPTION_KEY = 'mc_quiz_funnel_config';

    /**
     * Get the current funnel configuration with defaults
     * 
     * @return array Configuration array with steps, titles, and placeholder
     */
    public static function get_config()
    {
        $defaults = [
            'steps' => ['mi-quiz', 'cdt-quiz', 'bartle-quiz'],
            'titles' => [
                'mi-quiz' => 'Multiple Intelligences Assessment',
                'cdt-quiz' => 'Growth Strengths Assessment',
                'bartle-quiz' => 'Core Motivation Assessment'
            ],
            'placeholder' => [
                'title' => 'Advanced Self-Discovery Module',
                'description' => 'Coming soon - unlock deeper insights into your personal growth journey',
                'target' => '', // URL or page slug when ready
                'enabled' => false
            ]
        ];

        $config = get_option(self::OPTION_KEY, []);
        return wp_parse_args($config, $defaults);
    }

    /**
     * Save funnel configuration
     * 
     * @param array $config Configuration to save
     * @return bool Success/failure
     */
    public static function save_config($config)
    {
        $sanitized = self::sanitize_config($config);
        $result = update_option(self::OPTION_KEY, $sanitized);

        // Clear all user dashboard caches when config changes
        if ($result) {
            self::clear_all_dashboard_caches();
        }

        return $result;
    }

    /**
     * Get completion status for current user
     * 
     * @param int|null $user_id User ID, defaults to current user
     * @return array Completion status keyed by quiz slug
     */
    public static function get_completion_status($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        // Use the existing completion logic from dashboard
        $registered_quizzes = Micro_Coach_Core::get_quizzes();
        $completion_status = [];

        foreach ($registered_quizzes as $quiz_id => $quiz_info) {


            $meta_key = $quiz_info['results_meta_key'] ?? '';
            if ($meta_key) {
                $results = get_user_meta($user_id, $meta_key, true);
                $completion_status[$quiz_id] = !empty($results);
            } else {
                $completion_status[$quiz_id] = false;
            }
        }

        return $completion_status;
    }


    /**
     * Get unlock status for each step based on completion
     * 
     * @param int|null $user_id User ID, defaults to current user
     * @return array Unlock status keyed by quiz slug
     */
    public static function get_unlock_status($user_id = null)
    {
        $config = self::get_config();
        $unlock_status = [];

        // All quizzes are now available in any order
        foreach ($config['steps'] as $step_slug) {
            $unlock_status[$step_slug] = true;
        }

        return $unlock_status;
    }

    /**
     * Get the URL for a quiz step
     * 
     * @param string $step_slug Quiz slug
     * @return string|null URL or null if not found
     */
    public static function get_step_url($step_slug)
    {
        // Use existing logic to find quiz pages
        $registered_quizzes = Micro_Coach_Core::get_quizzes();
        if (!isset($registered_quizzes[$step_slug])) {
            return null;
        }

        $shortcode = $registered_quizzes[$step_slug]['shortcode'] ?? '';
        if (!$shortcode) {
            return null;
        }

        // Use the same page finding logic from Micro_Coach_Core
        return self::find_page_by_shortcode($shortcode);
    }

    /**
     * Get business-friendly translations for MI terms
     * 
     * @return array Map of slug => Business Competency
     */
    public static function get_mi_business_translations()
    {
        return [
            'linguistic' => 'Communication & Articulation',
            'logical-mathematical' => 'Data Analysis & Strategy',
            'spatial' => 'Design & Visualization',
            'bodily-kinesthetic' => 'Operational Execution',
            'musical' => 'Pattern Recognition & Flow',
            'interpersonal' => 'Leadership & Collaboration',
            'intrapersonal' => 'Self-Regulation & Autonomy',
            'naturalistic' => 'Systems Thinking',
            'existential' => 'Big Picture Strategy'
        ];
    }

    /**
     * Check if all assessments are complete and trigger notifications/AI analysis
     * 
     * @param int $user_id User ID
     * @return void
     */
    public static function check_completion_and_notify($user_id)
    {
        // Clear cache to ensure fresh completion status
        self::clear_all_dashboard_caches();

        $config = self::get_config();

        // Attempt to calculate Strain Index if not already present
        if (class_exists('MC_Strain_Index_Scorer')) {
            MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
        }

        $completion = self::get_completion_status($user_id);

        // Check if ALL quizzes are complete
        $all_complete = true;
        foreach (($config['steps'] ?? []) as $slug) {
            if (empty($completion[$slug])) {
                $all_complete = false;
                break;
            }
        }

        if ($all_complete) {
            // Check if we've already handled this completion to avoid duplicates
            $already_handled = get_user_meta($user_id, 'mc_all_assessments_completed', true);
            if ($already_handled) {
                return;
            }

            // Mark as handled
            update_user_meta($user_id, 'mc_all_assessments_completed', time());

            // 1. Generate AI Analysis
            $analysis = null;
            if (class_exists('Micro_Coach_AI')) {
                $analysis = Micro_Coach_AI::generate_analysis_on_completion($user_id);
            }

            // Fallback: If generation failed (e.g. no key) or returned false, check if we have a saved analysis
            if (empty($analysis)) {
                $analysis = get_user_meta($user_id, 'mc_assessment_analysis', true);
            }

            // 2. Prepare Data for Email
            $admin_email = get_option('admin_email');
            $user_info = get_userdata($user_id);
            $user_name = $user_info ? $user_info->display_name : 'User #' . $user_id;
            $user_email = $user_info ? $user_info->user_email : '';

            // Find linked employer
            $linked_employer_id = get_user_meta($user_id, 'mc_linked_employer_id', true);
            $employer_email = $linked_employer_id ? get_userdata($linked_employer_id)->user_email : $admin_email;

            // Fetch Raw Results
            $all_results = self::get_all_assessment_results($user_id);

            // Extract & Translate Key Metrics
            $mi_map = self::get_mi_business_translations();
            $mi_top3 = $all_results['mi']['top3'] ?? [];
            $mi_labels = array_map(function ($slug) use ($mi_map) {
                return $mi_map[$slug] ?? ucwords(str_replace('-', ' ', $slug));
            }, $mi_top3);

            // Format MI list with bullets if more than one, or comma separated
            // Let's use a nice list for the email

            $cdt_score = $all_results['cdt']['sortedScores'][0][1] ?? 0;
            $cdt_label = $all_results['cdt']['sortedScores'][0][0] ?? 'N/A';
            $cdt_summary = ucwords(str_replace('-', ' ', $cdt_label)); // Removed score as per feedback

            $bartle_type = $all_results['bartle']['sortedScores'][0][0] ?? 'N/A';
            $bartle_summary = ucwords($bartle_type);

            $subject = 'Assessment Insights: ' . $user_name;

            $dashboard_url = self::find_page_by_shortcode('mc_employer_dashboard');
            if (!$dashboard_url) {
                $dashboard_url = home_url();
            }

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            // 3. Build HTML Email
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>

            <body
                style='font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f3f4f6; margin: 0; padding: 20px;'>
                <div
                    style='max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;'>

                    <!-- Header -->
                    <div
                        style='background-color: #1e293b; padding: 32px 24px; text-align: center; border-bottom: 4px solid #3b82f6;'>
                        <h2 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;'>Assessment
                            Completed</h2>
                        <p style='color: #94a3b8; margin: 8px 0 0 0; font-size: 16px;'><?php echo esc_html($user_name); ?></p>
                    </div>

                    <div style='padding: 32px 24px;'>

                        <?php if (!empty($analysis) && !empty($analysis['executive_snapshot'])): ?>
                            <?php $snapshot = $analysis['executive_snapshot']; ?>

                            <!-- AI Executive Summary Card -->
                            <div
                                style='background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 24px; margin-bottom: 32px;'>
                                <div style='display: flex; align-items: center; margin-bottom: 12px;'>
                                    <span style='font-size: 20px; margin-right: 8px;'>💡</span>
                                    <h3 style='margin: 0; color: #1e40af; font-size: 18px; font-weight: 700;'>Executive Snapshot</h3>
                                </div>
                                <p style='margin: 0; color: #1e3a8a; font-size: 16px; font-style: italic; line-height: 1.6;'>
                                    "<?php echo esc_html($snapshot['context_summary'] ?? 'Analysis complete.'); ?>"
                                </p>
                            </div>

                        <?php else: ?>
                            <div style='background-color: #eff6ff; padding: 16px; border-radius: 8px; border: 1px solid #bfdbfe; margin-bottom: 24px;'>
                                <p style='margin: 0; color: #1e3a8a; font-size: 14px;'>
                                    <strong>AI Insights Ready:</strong> Unlock the full AI-synthesized Executive Summary and detailed insights on your dashboard.
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Core Competencies (MI Transformed) -->
                        <div style='margin-bottom: 32px;'>
                            <h3
                                style='color: #0f172a; font-size: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;'>
                                Top Business Competencies
                            </h3>
                            <div style='display: grid; grid-gap: 12px;'>
                                <?php foreach ($mi_labels as $label): ?>
                                    <div
                                        style='background: #f8fafc; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #3b82f6; font-weight: 600; color: #334155;'>
                                        <?php echo esc_html($label); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Secondary Metrics Grid -->
                        <div style='display: table; width: 100%; border-spacing: 0; margin-bottom: 24px;'>
                            <div
                                style='display: table-cell; width: 48%; vertical-align: top; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                                <strong
                                    style='display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 8px;'>Change
                                    Style</strong>
                                <span
                                    style='font-size: 16px; font-weight: 600; color: #0f172a;'><?php echo esc_html($cdt_summary); ?></span>
                            </div>
                            <div style='display: table-cell; width: 4%;'></div> <!-- Spacer -->
                            <div
                                style='display: table-cell; width: 48%; vertical-align: top; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                                <strong
                                    style='display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 8px;'>Motivation</strong>
                                <span
                                    style='font-size: 16px; font-weight: 600; color: #0f172a;'><?php echo esc_html($bartle_summary); ?></span>
                            </div>
                        </div>

                        <div style='text-align: center; margin-top: 40px; padding-top: 24px; border-top: 1px solid #e2e8f0;'>
                            <a href='<?php echo esc_url($dashboard_url); ?>'
                                style='background-color: #2563eb; color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); transition: all 0.2s;'>
                                Access Full Employee Report
                            </a>
                            <p style='margin-top: 16px; font-size: 13px; color: #94a3b8;'>
                                View detailed breakdown, coaching tips, and growth edges on the dashboard.
                            </p>
                        </div>
                    </div>

                    <div style='background-color: #f1f5f9; padding: 24px; text-align: center; border-top: 1px solid #e2e8f0;'>
                        <p style='margin: 0; color: #94a3b8; font-size: 12px;'>The Science of Teamwork</p>
                    </div>
                </div>
            </body>

            </html>
            <?php
            $message = ob_get_clean();

            wp_mail($employer_email, $subject, $message, $headers);
        }
    }

    /**
     * Find a page containing a specific shortcode
     * 
     * @param string $shortcode Shortcode to search for
     * @return string|null Page URL or null if not found
     */
    public static function find_page_by_shortcode($shortcode)
    {
        $pages = get_pages([
            'meta_key' => '_wp_page_template',
            'hierarchical' => false,
            'number' => 100
        ]);

        // Also search all published pages
        $all_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 100,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_page_template',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_wp_page_template',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);

        $search_pages = array_merge($pages, $all_pages);

        foreach ($search_pages as $page) {
            if (has_shortcode($page->post_content, $shortcode)) {
                return get_permalink($page->ID);
            }
        }

        return null;
    }

    /**
     * Sanitize configuration input
     * 
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    private static function sanitize_config($config)
    {
        $registered_quizzes = Micro_Coach_Core::get_quizzes();
        $valid_slugs = array_keys($registered_quizzes);
        $valid_slugs[] = 'placeholder'; // Always allow placeholder

        $sanitized = [
            'steps' => [],
            'titles' => [],
            'placeholder' => [
                'title' => '',
                'description' => '',
                'target' => '',
                'enabled' => false
            ]
        ];

        // Sanitize steps - only allow valid quiz slugs and placeholder
        if (!empty($config['steps']) && is_array($config['steps'])) {
            foreach ($config['steps'] as $step) {
                $step = sanitize_key($step);
                if (in_array($step, $valid_slugs) && !in_array($step, $sanitized['steps'])) {
                    $sanitized['steps'][] = $step;
                }
            }
        }

        // Ensure we have at least the default steps if none provided
        if (empty($sanitized['steps'])) {
            $sanitized['steps'] = ['mi-quiz', 'cdt-quiz', 'bartle-quiz'];
        }

        // Sanitize titles
        if (!empty($config['titles']) && is_array($config['titles'])) {
            foreach ($config['titles'] as $slug => $title) {
                $slug = sanitize_key($slug);
                if (in_array($slug, $valid_slugs)) {
                    $sanitized['titles'][$slug] = sanitize_text_field($title);
                }
            }
        }

        // Sanitize placeholder config
        if (!empty($config['placeholder']) && is_array($config['placeholder'])) {
            $sanitized['placeholder']['title'] = sanitize_text_field($config['placeholder']['title'] ?? '');
            $sanitized['placeholder']['description'] = sanitize_textarea_field($config['placeholder']['description'] ?? '');
            $sanitized['placeholder']['target'] = esc_url_raw($config['placeholder']['target'] ?? '');
            $sanitized['placeholder']['enabled'] = !empty($config['placeholder']['enabled']);
        }

        return $sanitized;
    }

    /**
     * Clear dashboard caches for all users
     */
    public static function clear_all_dashboard_caches()
    {
        if (class_exists('MC_Cache')) {
            // Clear all dashboard caches - this is a bit brute force but ensures consistency
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mc_dashboard_data_%'");
        }
    }

    /**
     * Clear the funnel configuration cache and reset to defaults
     */
    public static function reset_to_defaults()
    {
        delete_option(self::OPTION_KEY);
        self::clear_all_dashboard_caches();
        return true;
    }

    /**
     * Aggregate all assessment results for a user into a single array
     * 
     * @param int $user_id User ID
     * @return array Aggregated results
     */
    public static function get_all_assessment_results($user_id)
    {
        $results = [];

        // MI Quiz
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        if (!empty($mi_results)) {
            $results['mi'] = $mi_results;
        }

        // CDT Quiz
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        if (!empty($cdt_results)) {
            $results['cdt'] = $cdt_results;
        }

        // Bartle Quiz
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
        if (!empty($bartle_results)) {
            $results['bartle'] = $bartle_results;
        }

        // Strain Index
        $strain_results = get_user_meta($user_id, 'strain_index_results', true);
        if (!empty($strain_results)) {
            $results['strain_index'] = $strain_results;
        }

        return $results;
    }
}