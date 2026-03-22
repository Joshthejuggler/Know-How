<?php
if (!defined('ABSPATH')) exit;

/**
 * Talent Benchmarking & Comparison Dashboard
 * Compares candidates against "Rockstar" employees based on assessment data.
 */
class MC_Benchmarking {

    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu'], 20);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        } else {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets_frontend']);
            add_shortcode('mc_talent_comparison', [$this, 'render_shortcode']);
        }
        
        // AJAX handlers (accessible to any logged in user with permission)
        add_action('wp_ajax_mc_get_benchmark_data', [$this, 'ajax_get_benchmark_data']);
    }

    /**
     * Add sub-menu under the main Quiz Platform menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'quiz-platform-settings',
            'Talent Comparison & Benchmarking',
            'Talent Comparison',
            'manage_options',
            'mc-benchmarking',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue assets for front-end shortcode usage
     */
    public function enqueue_assets_frontend() {
        global $post;
        $has_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mc_talent_comparison');
        $is_employer_dash = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mc_employer_dashboard') && isset($_GET['view']) && $_GET['view'] === 'benchmarking';

        if (!$has_shortcode && !$is_employer_dash) return;

        $this->enqueue_assets('mc-benchmarking'); // Reuse the main enqueuer with a dummy hook
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_assets($hook) {
        if (!is_admin() && $hook !== 'mc-benchmarking') return;
        if (is_admin() && strpos($hook, 'mc-benchmarking') === false) return;

        wp_enqueue_style('mc-benchmarking-css', plugin_dir_url(dirname(__FILE__)) . 'assets/benchmarking.css', [], filemtime(dirname(__FILE__) . '/../assets/benchmarking.css'));
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        wp_enqueue_script('mc-benchmarking-js', plugin_dir_url(dirname(__FILE__)) . 'assets/benchmarking.js', ['jquery', 'chart-js'], filemtime(dirname(__FILE__) . '/../assets/benchmarking.js'), true);

        wp_localize_script('mc-benchmarking-js', 'mcBenchmarking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mc_benchmarking_nonce'),
            'cultureNonce' => wp_create_nonce('mc_culture_scorecard_nonce'),
        ]);
    }

    /**
     * Shortcode wrapper for the dashboard
     */
    public function render_shortcode($atts) {
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            return '<div class="mc-alert error">Access Denied: You do not have permission to view the comparison dashboard.</div>';
        }

        return $this->render_page(true);
    }

    /**
     * Render the Benchmarking Dashboard
     */
    public function render_page($return = false) {
        $user_id = get_current_user_id();
        // A "true" global admin is one who has manage_options but is NOT also an employer
        // (employers who were granted manage_options should still be scoped to their own team)
        $is_employer = current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES);
        $has_linked_employees = !empty(get_user_meta($user_id, 'mc_invited_employees', true)) 
                                || !empty(get_user_meta($user_id, 'mc_company_name', true));
        $is_global_admin = current_user_can('manage_options') && !$is_employer && !$has_linked_employees;


        // Scope to current employer if they are not a global admin
        if (!$is_global_admin) {
            // Current employees: must be linked to this employer AND (type='current' OR type not set)
            $args_current = [
                'fields'     => ['ID', 'display_name', 'user_email'],
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'   => 'mc_linked_employer_id',
                        'value' => $user_id
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key'     => 'mc_employment_type',
                            'value'   => 'current',
                            'compare' => '='
                        ],
                        [
                            'key'     => 'mc_employment_type',
                            'compare' => 'NOT EXISTS'
                        ]
                    ]
                ]
            ];

            // Candidates: must be linked to this employer AND type='potential'
            $args_candidates = [
                'fields'     => ['ID', 'display_name', 'user_email'],
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'   => 'mc_linked_employer_id',
                        'value' => $user_id
                    ],
                    [
                        'key'     => 'mc_employment_type',
                        'value'   => 'potential',
                        'compare' => '='
                    ]
                ]
            ];
        } else {
            // Global admin sees all users, filtered only by type
            $args_current = [
                'fields'     => ['ID', 'display_name', 'user_email'],
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key'     => 'mc_employment_type',
                        'value'   => 'current',
                        'compare' => '='
                    ],
                    [
                        'key'     => 'mc_employment_type',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];

            $args_candidates = [
                'fields'     => ['ID', 'display_name', 'user_email'],
                'meta_query' => [
                    [
                        'key'     => 'mc_employment_type',
                        'value'   => 'potential',
                        'compare' => '='
                    ]
                ]
            ];
        }

        $current_employees = get_users($args_current);
        $candidates = get_users($args_candidates);

        // Fetch Culture Scorecards
        $scorecards = [];
        if (class_exists('MC_Culture_Scorecard')) {
            $scorecards = MC_Culture_Scorecard::get_scorecards($user_id);
        }

        if ($return) ob_start();
        include MC_QUIZ_PLATFORM_PATH . 'admin-benchmarking.php';
        if ($return) return ob_get_clean();
    }

    /**
     * AJAX: Get aggregate data for selected "Rockstars" and comparison candidates
     */
    public function ajax_get_benchmark_data() {
        check_ajax_referer('mc_benchmarking_nonce', 'nonce');
        
        $rockstar_ids = isset($_POST['rockstar_ids']) ? array_map('intval', $_POST['rockstar_ids']) : [];
        $candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;

        if (empty($rockstar_ids)) {
            wp_send_json_error(['message' => 'No rockstars selected for benchmark.']);
        }

        $benchmark = $this->calculate_aggregate_scores($rockstar_ids);
        $candidate_data = null;
        $candidate_name = 'Candidate';
        
        if ($candidate_id) {
            $candidate_data = $this->get_user_scores($candidate_id);
            $user_info = get_userdata($candidate_id);
            if ($user_info) $candidate_name = $user_info->display_name;
        }

        if (!$candidate_data) {
            wp_send_json_error(['message' => 'Candidate assessment data not found.']);
        }

        $match_percent = $this->calculate_match_percent($benchmark, $candidate_data);
        
        // Build Labels mapping
        $labels_map = [
            'linguistic' => 'Linguistic', 'logical-mathematical' => 'Logical', 'spatial' => 'Spatial', 
            'bodily-kinesthetic' => 'Kinesthetic', 'musical' => 'Musical', 'interpersonal' => 'Interpersonal', 
            'intrapersonal' => 'Intrapersonal', 'naturalistic' => 'Naturalistic',
            'ambiguity-tolerance' => 'Ambiguity', 'value-conflict-navigation' => 'Conflict Nav', 
            'self-confrontation-capacity' => 'Self Aware', 'discomfort-regulation' => 'Regulation', 
            'growth-orientation' => 'Growth',
            'explorer' => 'Explorer', 'achiever' => 'Achiever', 'socializer' => 'Socializer', 'strategist' => 'Strategist'
        ];

        // Prepare chart data and breakdown
        $chart_labels = [];
        $bench_chart = [];
        $cand_chart = [];
        $trait_breakdown = [];

        foreach ($benchmark as $cat => $vals) {
            foreach ($vals as $slug => $bench_val) {
                $cand_val = $candidate_data[$cat][$slug] ?? 0;
                $label = $labels_map[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
                
                $chart_labels[] = $label;
                $bench_chart[] = $bench_val;
                $cand_chart[] = $cand_val;

                // Match per trait: 100 - diff
                $trait_match = max(0, 100 - abs($bench_val - $cand_val));
                
                $trait_breakdown[] = [
                    'name' => $label,
                    'benchmark' => $bench_val,
                    'candidate' => $cand_val,
                    'match' => round($trait_match)
                ];
            }
        }

        // Match Label
        $match_label = 'Limited Alignment';
        if ($match_percent > 85) $match_label = 'Resonant Match';
        else if ($match_percent > 75) $match_label = 'High Alignment';
        else if ($match_percent > 60) $match_label = 'Moderate Offset';

        wp_send_json_success([
            'candidate_name' => $candidate_name,
            'rockstar_count' => count($rockstar_ids),
            'match_percent' => $match_percent,
            'match_label' => $match_label,
            'trait_breakdown' => $trait_breakdown,
            'chart_data' => [
                'labels' => $chart_labels,
                'benchmark' => $bench_chart,
                'candidate' => $cand_chart
            ]
        ]);
    }

    /**
     * Calculate average scores across MI, CDT, and Bartle for a group of users
     */
    private function calculate_aggregate_scores($user_ids) {
        $dimensions = $this->get_dimensions_template();
        $count = 0;

        foreach ($user_ids as $uid) {
            $scores = $this->get_user_scores($uid);
            if (!$scores) continue;

            $count++;
            foreach ($scores as $cat => $vals) {
                foreach ($vals as $key => $val) {
                    $dimensions[$cat][$key] += $val;
                }
            }
        }

        if ($count > 0) {
            foreach ($dimensions as $cat => &$vals) {
                foreach ($vals as $key => &$val) {
                    $val = round($val / $count, 1);
                }
            }
        }

        return $dimensions;
    }

    /**
     * Get normalized scores (0-100) for a single user
     */
    private function get_user_scores($user_id) {
        $mi = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle = get_user_meta($user_id, 'bartle_quiz_results', true);

        if (empty($mi) && empty($cdt) && empty($bartle)) return null;

        $scores = $this->get_dimensions_template();

        // MI (Linguistic, etc.) - Max 40
        if (!empty($mi['part1Scores'])) {
            foreach ($mi['part1Scores'] as $slug => $val) {
                if (isset($scores['mi'][$slug])) {
                    $scores['mi'][$slug] = round($val / 40 * 100);
                }
            }
        }

        // CDT (Ambiguity, etc.) - Max 50
        if (!empty($cdt['sortedScores'])) {
            foreach ($cdt['sortedScores'] as $pair) {
                $slug = $pair[0];
                $val = $pair[1];
                if (isset($scores['cdt'][$slug])) {
                    $scores['cdt'][$slug] = round($val / 50 * 100);
                }
            }
        }

        // Bartle (Explorer, etc.) - Max 50
        if (!empty($bartle['sortedScores'])) {
            foreach ($bartle['sortedScores'] as $pair) {
                $slug = $pair[0];
                $val = $pair[1];
                if (isset($scores['bartle'][$slug])) {
                    $scores['bartle'][$slug] = round($val / 50 * 100);
                }
            }
        }

        return $scores;
    }

    private function get_dimensions_template() {
        return [
            'mi' => [
                'linguistic' => 0, 'logical-mathematical' => 0, 'spatial' => 0, 
                'bodily-kinesthetic' => 0, 'musical' => 0, 'interpersonal' => 0, 
                'intrapersonal' => 0, 'naturalistic' => 0
            ],
            'cdt' => [
                'ambiguity-tolerance' => 0, 'value-conflict-navigation' => 0, 
                'self-confrontation-capacity' => 0, 'discomfort-regulation' => 0, 
                'growth-orientation' => 0
            ],
            'bartle' => [
                'explorer' => 0, 'achiever' => 0, 'socializer' => 0, 'strategist' => 0
            ]
        ];
    }

    /**
     * Calculate how close a candidate is to the benchmark (%)
     */
    private function calculate_match_percent($benchmark, $candidate) {
        $total_diff = 0;
        $dims_count = 0;

        foreach ($benchmark as $cat => $vals) {
            foreach ($vals as $key => $target_val) {
                $candidate_val = $candidate[$cat][$key] ?? 0;
                $total_diff += abs($target_val - $candidate_val);
                $dims_count++;
            }
        }

        if ($dims_count === 0) return 0;

        // Average deviation per dimension (0-100 scale)
        $avg_dev = $total_diff / $dims_count;
        return max(0, 100 - round($avg_dev));
    }
}
