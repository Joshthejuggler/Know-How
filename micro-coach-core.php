<?php
if (!defined('ABSPATH'))
    exit;

/**
 * The main Core class for the quiz platform.
 * Its job is to load shared services and discover/load all available quiz modules.
 */
class Micro_Coach_Core
{
    private $loaded_modules = [];
    private static $registered_quizzes = [];
    const OPT_GROUP = 'mc_quiz_platform_settings';
    const OPT_DESCRIPTIONS = 'mc_quiz_descriptions';

    public function __construct()
    {
        // Add the new shortcode for the quiz dashboard.
        add_shortcode('quiz_dashboard', [$this, 'render_quiz_dashboard']);
        add_shortcode('quiz_funnel', [$this, 'render_quiz_funnel']);

        if (is_admin()) {
            // Add admin settings page for the platform.
            add_action('admin_menu', [$this, 'add_settings_page'], 9);
            add_action('admin_init', [$this, 'register_settings']);
        }

        add_action('save_post', [$this, 'clear_shortcode_page_transients']);

        // AJAX endpoint for enhanced flow - mark AI coach as unlocked
        add_action('wp_ajax_mc_mark_ai_unlocked', [$this, 'ajax_mark_ai_unlocked']);

        // AJAX endpoints for funnel management
        add_action('wp_ajax_mc_save_funnel', [$this, 'ajax_save_funnel']);
        add_action('wp_ajax_get_johari_status', [$this, 'ajax_get_johari_status']);
        add_action('wp_ajax_nopriv_get_johari_status', [$this, 'ajax_get_johari_status']);

        // Add body class for enhanced flow (early hook)
        add_filter('body_class', [$this, 'add_enhanced_flow_body_class']);

        // Clear dashboard cache when user meta is updated
        add_action('updated_user_meta', [$this, 'clear_user_dashboard_cache'], 10, 4);
        add_action('added_user_meta', [$this, 'clear_user_dashboard_cache'], 10, 4);

        // Ensure user session is available before shortcodes render
        add_action('init', [$this, 'ensure_user_session'], 1);
        add_action('wp', [$this, 'ensure_user_session'], 1);
    }

    /**
     * Allows quiz modules to register themselves with the core platform.
     * @param string $id A unique ID for the quiz (e.g., 'mi-quiz').
     * @param array $args An array of quiz metadata.
     */
    public static function register_quiz($id, $args)
    {
        $defaults = [
            'title' => 'Untitled Quiz',
            'shortcode' => '',
            'results_meta_key' => '',
            'order' => 99,
            'description' => '',
            'description_completed' => '',
            'depends_on' => null,
        ];
        self::$registered_quizzes[$id] = wp_parse_args($args, $defaults);
    }

    /**
     * Returns the array of all registered quizzes.
     * @return array
     */
    public static function get_quizzes()
    {
        return self::$registered_quizzes;
    }

    /**
     * Adds the main settings page for the quiz platform.
     */
    public function add_settings_page()
    {
        add_menu_page(
            'Quiz Platform Settings',
            'Quiz Platform',
            'manage_options',
            'quiz-platform-settings',
            [$this, 'render_settings_page'],
            'dashicons-forms',
            58 // Position it near other quiz menus.
        );

        add_submenu_page(
            'quiz-platform-settings',
            'Quiz Platform Settings',
            'Settings',
            'manage_options',
            'quiz-platform-settings', // Use parent slug for the main settings page
            [$this, 'render_settings_page']
        );

        // Add funnel management page
        add_submenu_page(
            'quiz-platform-settings',
            'Quiz Funnel Configuration',
            'Funnel',
            'manage_options',
            'quiz-platform-funnel',
            [$this, 'render_funnel_admin_page']
        );
    }

    /**
     * Registers settings for the quiz platform, like descriptions.
     */
    public function register_settings()
    {
        // Intentionally left minimal: remove the "Quiz Descriptions" section
        // and fields from the admin UI per request. Other modules (e.g., AI)
        // still add their own settings/sections to this page.
    }

    public function sanitize_descriptions($input)
    {
        $sanitized = [];
        if (is_array($input)) {
            foreach ($input as $id => $desc) {
                $sanitized[sanitize_key($id)] = sanitize_textarea_field(stripslashes($desc));
            }
        }
        return $sanitized;
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Quiz Platform Settings</h1>
            <form method="post" action="options.php"><?php
            settings_fields(self::OPT_GROUP);
            do_settings_sections('quiz-platform-settings');
            submit_button();
            ?></form><?php
            // Allow other components (like the AI module) to add content here.
            do_action('mc_platform_settings_page_bottom');
            ?>
        </div><?php
    }

    /**
     * Renders the [quiz_dashboard] shortcode content.
     */
    public function render_quiz_dashboard()
    {
        $quizzes = self::get_quizzes();
        if (empty($quizzes)) {
            return current_user_can('manage_options') ? '<p><em>Quiz Dashboard: No quizzes have been registered.</em></p>' : '';
        }

        $user_id = get_current_user_id();
        wp_enqueue_style('dashicons');

        ob_start();

        if ($user_id) { // Logged-in user view
            // Use cached dashboard data for better performance
            $dashboard_data = MC_Cache::get_dashboard_data($user_id);
            $activity_feed = $dashboard_data['activity_feed'];
            $completion_status = $dashboard_data['completion_status'];

            // --- Calculate Progress & Next Step ---
            $u = wp_get_current_user();
            if (!empty($u->first_name)) {
                $first = $u->first_name;
            } elseif (!empty($u->display_name)) {
                $first = $u->display_name;
            } else {
                $first = "Friend";
            }
            $greetings = [
                "Welcome back, <span class='name-emphasis'>{$first}</span> — what will you discover today?",
                "Good to see you, <span class='name-emphasis'>{$first}</span>. Your journey continues.",
                "Hi <span class='name-emphasis'>{$first}</span>, ready for another step in self-discovery?",
                "<span class='name-emphasis'>{$first}</span>, small steps lead to big insights."
            ];
            $greeting = $greetings[array_rand($greetings)];

            $latest_insight_html = '<p class="placeholder-text"><em>Complete an assessment to see your first insight here!</em></p>';
            $latest_activity = null;
            // Find the most recent *quiz* activity for the insight panel
            foreach ($activity_feed as $activity) {
                if ($activity['quiz_id'] !== 'account_creation') {
                    $latest_activity = $activity;
                    break;
                }
            }

            // --- Sort Quizzes for Display ---
            // Add ID to each quiz for sorting purposes
            foreach ($quizzes as $id => &$quiz) {
                $quiz['id'] = $id;
            }
            unset($quiz); // Unset reference

            // Sort by completion status first, then by order.
            uasort($quizzes, function ($a, $b) use ($completion_status) {
                $completed_a = $completion_status[$a['id']] ?? false;
                $completed_b = $completion_status[$b['id']] ?? false;

                if ($completed_a === $completed_b) {
                    return ($a['order'] ?? 99) <=> ($b['order'] ?? 99); // Secondary sort: by order
                }

                return $completed_a <=> $completed_b; // Primary sort: incomplete (false) before complete (true)
            });

            $total_quizzes = count($quizzes);
            $completed_quizzes = count(array_filter($completion_status));
            $progress_pct = ($total_quizzes > 0) ? round(($completed_quizzes / $total_quizzes) * 100) : 0;

            $next_step_url = '';
            $next_step_title = 'All Quizzes Complete!';
            $next_step_action = '';
            if ($progress_pct >= 100) {
                $next_step_title = 'Try Your AI Coach';
                $next_step_action = 'switch-to-ai-tab';
            }
            if ($progress_pct < 100) {
                // Find the first incomplete quiz (no dependency check - users can take in any order)
                foreach ($quizzes as $id => $quiz) {
                    if (!($completion_status[$id] ?? false)) {
                        $next_step_url = $this->find_page_by_shortcode($quiz['shortcode']);
                        $next_step_title = 'Next Step: ' . $quiz['title'];
                        break;
                    }
                }
            }
            ?>
            <div class="quiz-dashboard-container">
                <div class="dashboard-header">
                    <div class="site-branding">
                        <?php echo MC_Helpers::logo_img(['class' => 'site-logo', 'alt' => 'Skill of Self-Discovery Logo']); ?>
                        <span class="site-title">Skill of Self-Discovery</span>
                    </div>
                    <!-- User menu can be added here -->
                </div>
                <!-- Hero / Greeting Row -->
                <div class="quiz-dashboard-hero">
                    <div class="quiz-dashboard-hero-greeting">
                        <h2 class="greeting-title"><?php echo wp_kses($greeting, ['span' => ['class' => []]]); ?></h2>
                        <p class="greeting-subtitle">Your journey of self-discovery is a marathon, not a sprint. Each step reveals
                            something new.</p>
                    </div>
                </div>

                <!-- Funnel Section (hidden when all quizzes complete) -->
                <?php if ($completed_quizzes < $total_quizzes): ?>
                    <div class="quiz-dashboard-funnel-section">
                        <?php
                        // Render the quiz funnel with dashboard styling
                        echo $this->render_quiz_funnel([
                            'show_description' => 'false',
                            'style' => 'dashboard'
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_id && $completed_quizzes === $total_quizzes && $total_quizzes > 0):
                    // --- All quizzes are complete, render the tabbed interface ---
                    require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                    require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                    require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';

                    $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
                    $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
                    $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

                    // Always enqueue dashboard CSS for completed users
                    wp_enqueue_style('dashboard-enhanced-css', plugins_url('assets/dashboard.css', __FILE__), [], '1.0.4');

                    // --- Enhanced Flow Detection ---
                    $enhanced = !empty($bartle_results['enhanced_flow']) && empty(get_user_meta($user_id, 'ai_coach_unlocked', true));

                    // Debug: Add console logging for troubleshooting
                    if (current_user_can('manage_options')) {
                        add_action('wp_footer', function () use ($enhanced, $bartle_results, $user_id) {
                            echo '<script>console.log("Enhanced Flow Debug:", ' . json_encode([
                                'enhanced' => $enhanced,
                                'bartle_enhanced_flow' => !empty($bartle_results['enhanced_flow']),
                                'ai_coach_unlocked' => !empty(get_user_meta($user_id, 'ai_coach_unlocked', true)),
                                'bartle_results' => $bartle_results
                            ]) . ');</script>';
                        });
                    }
                    if ($enhanced) {
                        // Add CSS class for enhanced profile-only mode
                        add_action('wp_head', function () {
                            echo '<style>
                                body.mc-enhanced-profile-only .dashboard-tabs { display: none !important; }
                                body.mc-enhanced-profile-only .tab-content-wrapper { border-top: none !important; margin-top: 0 !important; padding-top: 0 !important; }
                                body.mc-enhanced-profile-only .quiz-dashboard-hero { display: none !important; }
                                body.mc-enhanced-profile-only .quiz-dashboard-section-title,
                                body.mc-enhanced-profile-only .quiz-dashboard-lower-grid,
                                body.mc-enhanced-profile-only .quiz-dashboard-resources { display: none !important; }
                            </style>';
                        });

                        // Enqueue enhanced dashboard script and CSS
                        wp_enqueue_script('dashboard-enhanced-js', plugins_url('assets/dashboard-enhanced.js', __FILE__), ['jquery'], '1.0.0', true);
                        wp_enqueue_style('dashboard-enhanced-css', plugins_url('assets/dashboard.css', __FILE__), [], '1.0.5');
                        wp_localize_script('dashboard-enhanced-js', 'dashboard_enhanced_data', [
                            'ajaxUrl' => admin_url('admin-ajax.php'),
                            'nonce' => wp_create_nonce('mc_dash')
                        ]);
                    }

                    // --- Process Data for Identity Card ---
                    $mi_top3_names = array_map(function ($slug) use ($mi_categories) {
                        return $mi_categories[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
                    }, $mi_results['top3'] ?? []);
                    $cdt_scores_by_slug = array_column($cdt_results['sortedScores'] ?? [], 1, 0);
                    $cdt_slug_map = ['ambiguity-tolerance', 'value-conflict-navigation', 'self-confrontation-capacity', 'discomfort-regulation', 'growth-orientation'];
                    $bartle_scores_by_slug = array_column($bartle_results['sortedScores'] ?? [], 1, 0);
                    $primary_bartle_slug = $bartle_results['sortedScores'][0][0] ?? '';
                    $secondary_bartle_slug = $bartle_results['sortedScores'][1][0] ?? '';
                    $primary_bartle_pct = round(($bartle_scores_by_slug[$primary_bartle_slug] ?? 0) / 50 * 100);
                    $secondary_bartle_pct = round(($bartle_scores_by_slug[$secondary_bartle_slug] ?? 0) / 50 * 100);

                    // CDT top and bottom two scores
                    $cdt_sorted = $cdt_results['sortedScores'] ?? [];
                    $cdt_top_slug = !empty($cdt_sorted[0][0]) ? $cdt_sorted[0][0] : null;
                    $cdt_bottom_slug = null;
                    $cdt_second_bottom_slug = null;
                    if (!empty($cdt_sorted)) {
                        // Get the bottom two scores (lowest two)
                        $sorted_count = count($cdt_sorted);
                        if ($sorted_count >= 1) {
                            $last = $cdt_sorted[$sorted_count - 1];
                            if (!empty($last[0])) {
                                $cdt_bottom_slug = $last[0];
                            }
                        }
                        if ($sorted_count >= 2) {
                            $second_last = $cdt_sorted[$sorted_count - 2];
                            if (!empty($second_last[0]) && $second_last[0] !== $cdt_bottom_slug) {
                                $cdt_second_bottom_slug = $second_last[0];
                            }
                        }
                    }

                    // --- Build radar chart data ---
                    $chart_labels = ['Linguistic', 'Logical', 'Spatial', 'Bodily', 'Musical', 'Interpersonal', 'Intrapersonal', 'Naturalistic', 'Ambiguity', 'Value Conflict', 'Self-Confront', 'Discomfort Reg', 'Growth', 'Explorer', 'Achiever', 'Socializer', 'Strategist'];
                    $mi_chart_data = array_fill(0, 17, null);
                    $mi_slug_map = ['linguistic', 'logical-mathematical', 'spatial', 'bodily-kinesthetic', 'musical', 'interpersonal', 'intrapersonal', 'naturalistic'];
                    foreach ($mi_slug_map as $i => $slug) {
                        $mi_chart_data[$i] = round(($mi_results['part1Scores'][$slug] ?? 0) / 40 * 100);
                    }
                    $cdt_chart_data = array_fill(0, 17, null);
                    $cdt_slug_map = ['ambiguity-tolerance', 'value-conflict-navigation', 'self-confrontation-capacity', 'discomfort-regulation', 'growth-orientation'];
                    foreach ($cdt_slug_map as $i => $slug) {
                        $cdt_chart_data[8 + $i] = round((($cdt_scores_by_slug[$slug] ?? 0) / 50) * 100);
                    }
                    $pt_chart_data = array_fill(0, 17, null);
                    $pt_slug_map = ['explorer', 'achiever', 'socializer', 'strategist'];
                    foreach ($pt_slug_map as $i => $slug) {
                        $pt_chart_data[13 + $i] = round((($bartle_scores_by_slug[$slug] ?? 0) / 50) * 100);
                    }

                    // Build result page URLs for deep links
                    $mi_url = $this->find_page_by_shortcode('mi_quiz') ?: $this->find_page_by_shortcode('mi-quiz');
                    $cdt_url = $this->find_page_by_shortcode('cdt_quiz') ?: $this->find_page_by_shortcode('cdt-quiz');
                    $bartle_url = $this->find_page_by_shortcode('bartle_quiz') ?: $this->find_page_by_shortcode('bartle-quiz');

                    // --- Process Data for Radar Chart ---
                    $chart_labels = ['Linguistic', 'Logical', 'Spatial', 'Bodily', 'Musical', 'Interpersonal', 'Intrapersonal', 'Naturalistic', 'Ambiguity', 'Value Conflict', 'Self-Confront', 'Discomfort Reg', 'Growth', 'Explorer', 'Achiever', 'Socializer', 'Strategist'];
                    $mi_chart_data = array_fill(0, 17, null);
                    $mi_slug_map = ['linguistic', 'logical-mathematical', 'spatial', 'bodily-kinesthetic', 'musical', 'interpersonal', 'intrapersonal', 'naturalistic'];
                    foreach ($mi_slug_map as $i => $slug) {
                        $mi_chart_data[$i] = round(($mi_results['part1Scores'][$slug] ?? 0) / 40 * 100);
                    }
                    $cdt_chart_data = array_fill(0, 17, null);
                    foreach ($cdt_slug_map as $i => $slug) {
                        $cdt_chart_data[8 + $i] = round(($cdt_scores_by_slug[$slug] ?? 0) / 50 * 100);
                    }
                    $pt_chart_data = array_fill(0, 17, null);
                    $pt_slug_map = ['explorer', 'achiever', 'socializer', 'strategist'];
                    foreach ($pt_slug_map as $i => $slug) {
                        $pt_chart_data[13 + $i] = round(($bartle_scores_by_slug[$slug] ?? 0) / 50 * 100);
                    }
                    ?>
                    <div class="dashboard-tabs">
                        <?php
                        // Check if Lab Mode is available (all assessments complete)
                        $custom_tabs = apply_filters('mc_dashboard_custom_tabs', []);
                        $lab_mode_available = isset($custom_tabs['tab-lab']);
                        ?>
                        <?php if ($lab_mode_available): ?>
                            <button class="tab-link active" data-tab="tab-lab">🧪 Lab Mode</button>
                            <button class="tab-link" data-tab="tab-composite">🧩 Your Self-Discovery Profile</button>
                            <button class="tab-link" data-tab="tab-path">📊 Detailed Results</button>
                            <button class="tab-link" data-tab="tab-ai">🤖 AI Coach</button>
                        <?php else: ?>
                            <button class="tab-link" data-tab="tab-composite">🧩 Your Self-Discovery Profile</button>
                            <button class="tab-link" data-tab="tab-path">📊 Detailed Results</button>
                            <button class="tab-link active" data-tab="tab-ai">🤖 AI Coach</button>
                        <?php endif; ?>
                        <?php
                        // Add other custom tabs (excluding lab which we handled above)
                        foreach ($custom_tabs as $tab_id => $tab_label) {
                            if ($tab_id !== 'tab-lab') {
                                echo '<button class="tab-link" data-tab="' . esc_attr($tab_id) . '">' . esc_html($tab_label) . '</button>';
                            }
                        }
                        ?>
                        <button class="tab-link" data-tab="tab-saved">❤️ Saved</button>
                    </div>
                    <div class="tab-content-wrapper">
                        <?php if ($lab_mode_available): ?>
                            <div id="tab-lab" class="tab-content active"><?php do_action('mc_dashboard_custom_tab_content', 'tab-lab'); ?>
                            </div>
                            <div id="tab-composite" class="tab-content">
                            <?php else: ?>
                                <div id="tab-composite" class="tab-content active">
                                <?php endif; ?>
                                <?php
                                // Prepare dynamic values for the card
                                $pt_slug = $primary_bartle_slug ?: '';
                                $pt_title = ucfirst($pt_slug);
                                $pt_theme_class = $pt_slug ? 'pt--' . sanitize_html_class($pt_slug) : '';
                                $pt_pct = (int) $primary_bartle_pct;
                                $pt_emoji = '🎯';
                                if ($pt_slug === 'explorer')
                                    $pt_emoji = '🧭';
                                elseif ($pt_slug === 'achiever')
                                    $pt_emoji = '🏆';
                                elseif ($pt_slug === 'socializer')
                                    $pt_emoji = '🤝';
                                elseif ($pt_slug === 'strategist')
                                    $pt_emoji = '♟️';

                                // MI top 3 with percents and icons
                                $mi_top3 = [];
                                if (!empty($mi_results['top3']) && !empty($mi_results['part1Scores'])) {
                                    foreach ($mi_results['top3'] as $mi_slug) {
                                        $label = $mi_categories[$mi_slug] ?? ucfirst(str_replace('-', ' ', $mi_slug));
                                        $score = (int) ($mi_results['part1Scores'][$mi_slug] ?? 0);
                                        $pct = max(0, min(100, round(($score / 40) * 100)));
                                        // Icon mapping
                                        $icon = '⭐';
                                        $l = $label;
                                        $ll = strtolower($l);
                                        if (strpos($ll, 'linguistic') !== false)
                                            $icon = '📚';
                                        elseif (strpos($ll, 'logical') !== false)
                                            $icon = '🧩';
                                        elseif (strpos($ll, 'spatial') !== false)
                                            $icon = '🧭';
                                        elseif (strpos($ll, 'bodily') !== false)
                                            $icon = '🏃';
                                        elseif (strpos($ll, 'musical') !== false)
                                            $icon = '🎵';
                                        elseif (strpos($ll, 'interpersonal') !== false)
                                            $icon = '🤝';
                                        elseif (strpos($ll, 'intrapersonal') !== false)
                                            $icon = '🧠';
                                        elseif (strpos($ll, 'naturalistic') !== false)
                                            $icon = '🌿';
                                        $mi_top3[] = compact('label', 'pct', 'icon');
                                    }
                                }

                                // CDT top and bottom two
                                $cdt_top = null;
                                $cdt_bottom = null;
                                $cdt_second_bottom = null;

                                // Debug: Output CDT data for troubleshooting
                                if (current_user_can('manage_options')) {
                                    echo '<!-- CDT Debug: ';
                                    echo 'cdt_results exist: ' . (!empty($cdt_results) ? 'yes' : 'no') . ', ';
                                    echo 'cdt_categories exist: ' . (isset($cdt_categories) ? 'yes' : 'no') . ', ';
                                    echo 'cdt_top_slug: ' . ($cdt_top_slug ?? 'null') . ', ';
                                    echo 'cdt_bottom_slug: ' . ($cdt_bottom_slug ?? 'null') . ', ';
                                    echo 'cdt_second_bottom_slug: ' . ($cdt_second_bottom_slug ?? 'null') . ', ';
                                    echo 'cdt_sorted count: ' . count($cdt_sorted) . ', ';
                                    echo 'cdt_sorted: ' . json_encode($cdt_sorted) . ' -->';
                                }
                                if (!empty($cdt_top_slug)) {
                                    $t_pct = max(0, min(100, round((($cdt_scores_by_slug[$cdt_top_slug] ?? 0) / 50) * 100)));
                                    $cdt_top = ['label' => $cdt_categories[$cdt_top_slug] ?? ucfirst(str_replace('-', ' ', $cdt_top_slug)), 'pct' => $t_pct];
                                }
                                if (!empty($cdt_bottom_slug)) {
                                    $b_pct = max(0, min(100, round((($cdt_scores_by_slug[$cdt_bottom_slug] ?? 0) / 50) * 100)));
                                    $cdt_bottom = ['label' => $cdt_categories[$cdt_bottom_slug] ?? ucfirst(str_replace('-', ' ', $cdt_bottom_slug)), 'pct' => $b_pct];
                                }
                                if (!empty($cdt_second_bottom_slug)) {
                                    $sb_pct = max(0, min(100, round((($cdt_scores_by_slug[$cdt_second_bottom_slug] ?? 0) / 50) * 100)));
                                    $cdt_second_bottom = ['label' => $cdt_categories[$cdt_second_bottom_slug] ?? ucfirst(str_replace('-', ' ', $cdt_second_bottom_slug)), 'pct' => $sb_pct];
                                }

                                // Debug: Output final CDT data objects
                                if (current_user_can('manage_options')) {
                                    echo '<!-- CDT Data Objects: ';
                                    echo 'cdt_top: ' . json_encode($cdt_top) . ', ';
                                    echo 'cdt_bottom: ' . json_encode($cdt_bottom) . ', ';
                                    echo 'cdt_second_bottom: ' . json_encode($cdt_second_bottom) . ' -->';
                                }
                                ?>

                                <!-- Self-Discovery Player Card -->
                                <article class="sosd-playercard <?php echo esc_attr($pt_theme_class); ?>"
                                    data-player-type="<?php echo esc_attr($pt_title); ?>"
                                    aria-label="Self-Discovery Player Card: <?php echo esc_attr($pt_title); ?>"
                                    style="--pct:<?php echo $pt_pct; ?>">
                                    <div class="pc-mainhead">Self-Discovery Profile Card</div>
                                    <header class="pc-head pc-head--center">
                                        <h2 class="pc-title">Player Type</h2>
                                    </header>
                                    <p class="pc-caption" style="text-align:center; margin:6px 0 10px;">Why I’m motivated to engage at
                                        all</p>

                                    <?php
                                    $sec_slug = $secondary_bartle_slug ?: '';
                                    $sec_title = $sec_slug ? ucfirst($sec_slug) : '';
                                    $sec_pct = (int) ($secondary_bartle_pct ?? 0);
                                    $sec_emoji = '🎯';
                                    if ($sec_slug === 'explorer')
                                        $sec_emoji = '🧭';
                                    elseif ($sec_slug === 'achiever')
                                        $sec_emoji = '🏆';
                                    elseif ($sec_slug === 'socializer')
                                        $sec_emoji = '🤝';
                                    elseif ($sec_slug === 'strategist')
                                        $sec_emoji = '♟️';

                                    // Player Type caption (dynamic)
                                    $pt_phrases = [
                                        'explorer' => 'Explorers stay motivated by curiosity, discovery, and learning through new experiences.',
                                        'achiever' => 'Achievers thrive by setting goals and measuring progress — momentum and milestones keep them moving.',
                                        'socializer' => 'Socializers gain energy from collaboration, relationships, and shared growth.',
                                        'strategist' => 'Strategists find motivation in analysis, challenge, and mastering complex systems.'
                                    ];
                                    $pt_caption = '';
                                    if ($pt_slug) {
                                        $pt_caption = 'Your dominant player type shows how you stay motivated. ' . ($pt_phrases[$pt_slug] ?? 'Your primary style reveals what keeps you engaged and moving forward.');
                                        if ($sec_slug) {
                                            $pt_caption .= ' Your secondary type adds nuance — ' . ($pt_phrases[$sec_slug] ?? 'it complements your primary style') . '.';
                                        }
                                    }
                                    ?>
                                    <section class="pc-pt">
                                        <div class="pc-pt-grid">
                                            <div class="pt-col primary">
                                                <div class="pc-emblem" role="img" aria-label="<?php echo esc_attr($pt_title); ?> emblem"
                                                    style="--pct:<?php echo $pt_pct; ?>">
                                                    <div class="pc-emblem-ring"></div>
                                                    <div class="pc-emoji" aria-hidden="true"><?php echo esc_html($pt_emoji); ?></div>
                                                    <span class="pc-pct pc-pct--overlay"><?php echo $pt_pct; ?>%</span>
                                                </div>
                                                <div class="pt-name"><?php echo esc_html($pt_title); ?></div>
                                            </div>
                                            <?php if ($sec_slug): ?>
                                                <div class="pt-col secondary">
                                                    <div class="pc-emblem" role="img"
                                                        aria-label="<?php echo esc_attr($sec_title); ?> emblem"
                                                        style="--pct:<?php echo $sec_pct; ?>">
                                                        <div class="pc-emblem-ring"></div>
                                                        <div class="pc-emoji" aria-hidden="true"><?php echo esc_html($sec_emoji); ?></div>
                                                        <span class="pc-pct pc-pct--overlay"><?php echo $sec_pct; ?>%</span>
                                                    </div>
                                                    <div class="pt-name"><?php echo esc_html($sec_title); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($pt_caption)): ?>
                                            <p class="pc-caption"><?php echo esc_html($pt_caption); ?></p>
                                        <?php endif; ?>
                                    </section>

                                    <section class="pc-mi">
                                        <h3 class="pc-kicker">Top Multiple Intelligences</h3>
                                        <p class="pc-caption" style="text-align:center; margin:4px 0 10px;">What I’m naturally good at
                                        </p>
                                        <ul class="pc-mi-grid">
                                            <?php foreach ($mi_top3 as $mi): ?>
                                                <li class="mi" style="--val:<?php echo (int) $mi['pct']; ?>">
                                                    <span class="mi-gauge" data-val="<?php echo (int) $mi['pct']; ?>"></span>
                                                    <span class="mi-icon" aria-hidden="true"><?php echo esc_html($mi['icon']); ?></span>
                                                    <span class="mi-label"><?php echo esc_html($mi['label']); ?></span>
                                                    <span class="mi-val"><?php echo (int) $mi['pct']; ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php
                                        // MI caption based on top 2–3
                                        $mi_phrase = [
                                            'Linguistic' => 'strength with language and clear expression',
                                            'Logical' => 'analytical thinking and structured problem‑solving',
                                            'Spatial' => 'seeing patterns and thinking visually',
                                            'Bodily' => 'learning through action and movement',
                                            'Musical' => 'rhythm, pattern, and sound‑based learning',
                                            'Interpersonal' => 'learning through relationships and collaboration',
                                            'Intrapersonal' => 'self‑reflection and inner motivation',
                                            'Naturalistic' => 'noticing patterns in nature and systems'
                                        ];
                                        $mi_labels = array_map(function ($m) {
                                            return $m['label'];
                                        }, $mi_top3);
                                        $mi_caption = '';
                                        if (!empty($mi_labels)) {
                                            $topA = $mi_labels[0] ?? '';
                                            $topB = $mi_labels[1] ?? '';
                                            $topC = $mi_labels[2] ?? '';
                                            $descA = $mi_phrase[explode(' ', $topA)[0]] ?? 'your natural way of learning';
                                            $descB = $mi_phrase[explode(' ', $topB)[0]] ?? '';
                                            $descC = $mi_phrase[explode(' ', $topC)[0]] ?? '';
                                            $mi_caption = 'These are your strongest ways of learning and expressing intelligence. ';
                                            if ($topA && $topB) {
                                                $mi_caption .= $topA . ' and ' . $topB . ' suggest ' . $descA . ($descB ? ' and ' . $descB : '') . ', ';
                                            } elseif ($topA) {
                                                $mi_caption .= $topA . ' suggests ' . $descA . ', ';
                                            }
                                            if ($topC) {
                                                $mi_caption .= 'while ' . $topC . ' adds ' . ($descC ?: 'additional strength') . '.';
                                            } else {
                                                $mi_caption = rtrim($mi_caption, ', ') . '.';
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($mi_caption)): ?>
                                            <p class="pc-caption"><?php echo esc_html($mi_caption); ?></p>
                                        <?php endif; ?>
                                    </section>

                                    <?php
                                    $johari_profile = get_user_meta($user_id, 'johari_mi_profile', true);
                                    if (is_array($johari_profile)) {
                                        $j_open = isset($johari_profile['open']) ? count((array) $johari_profile['open']) : 0;
                                        $j_blind = isset($johari_profile['blind']) ? count((array) $johari_profile['blind']) : 0;
                                        $j_hidden = isset($johari_profile['hidden']) ? count((array) $johari_profile['hidden']) : 0;
                                        $j_unknown = isset($johari_profile['unknown']) ? count((array) $johari_profile['unknown']) : 0;
                                        ?>
                                        <section class="pc-johari">
                                            <h3 class="pc-kicker">Johari Window</h3>
                                            <p class="pc-caption" style="text-align:center; margin:4px 0 10px;">How I see myself vs. how
                                                others see me</p>
                                            <p class="pc-caption" style="text-align:center; font-weight:600; margin-bottom:10px;">
                                                Open: <?php echo (int) $j_open; ?> • Blind: <?php echo (int) $j_blind; ?> • Hidden:
                                                <?php echo (int) $j_hidden; ?> • Unknown: <?php echo (int) $j_unknown; ?>
                                            </p>
                                            <?php
                                            $open_adj = array_slice(array_values((array) ($johari_profile['open'] ?? [])), 0, 6);
                                            $blind_adj = array_slice(array_values((array) ($johari_profile['blind'] ?? [])), 0, 6);
                                            $hidden_adj = array_slice(array_values((array) ($johari_profile['hidden'] ?? [])), 0, 6);
                                            $unknown_adj = array_slice(array_values((array) ($johari_profile['unknown'] ?? [])), 0, 6);
                                            $renderChips = function ($list) {
                                                echo '<div class="quiz-dashboard-chips">';
                                                foreach ($list as $w) {
                                                    echo '<span class="chip">' . esc_html($w) . '</span>';
                                                }echo '</div>';
                                            };
                                            ?>
                                            <div class="pc-johari-lists"
                                                style="display:grid; gap:10px; grid-template-columns:repeat(2, minmax(0,1fr));">
                                                <div class="johari-q">
                                                    <div class="pc-caption" style="font-weight:700; margin-bottom:6px;">Open</div>
                                                    <?php $renderChips($open_adj); ?>
                                                </div>
                                                <div class="johari-q">
                                                    <div class="pc-caption" style="font-weight:700; margin-bottom:6px;">Blind</div>
                                                    <?php $renderChips($blind_adj); ?>
                                                </div>
                                                <div class="johari-q">
                                                    <div class="pc-caption" style="font-weight:700; margin-bottom:6px;">Hidden</div>
                                                    <?php $renderChips($hidden_adj); ?>
                                                </div>
                                                <div class="johari-q">
                                                    <div class="pc-caption" style="font-weight:700; margin-bottom:6px;">Unknown</div>
                                                    <?php $renderChips($unknown_adj); ?>
                                                </div>
                                            </div>
                                        </section>
                                    <?php } ?>

                                    <section class="pc-cdt">
                                        <h3 class="pc-kicker">CDT Highlights</h3>
                                        <p class="pc-caption" style="text-align:center; margin:4px 0 10px;">How I handle discomfort or
                                            internal conflict</p>
                                        <div class="pc-cdt-grid">
                                            <?php if ($cdt_top): ?>
                                                <div class="cdt-chip good">
                                                    <span class="cdt-tag">Strongest</span>
                                                    <span class="cdt-name"><?php echo esc_html($cdt_top['label']); ?></span>
                                                    <span class="cdt-meter" style="--val:<?php echo (int) $cdt_top['pct']; ?>"></span>
                                                    <span class="cdt-val"><?php echo (int) $cdt_top['pct']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($cdt_bottom): ?>
                                                <div class="cdt-chip edge">
                                                    <span class="cdt-tag">Growth Area</span>
                                                    <span class="cdt-name"><?php echo esc_html($cdt_bottom['label']); ?></span>
                                                    <span class="cdt-meter" style="--val:<?php echo (int) $cdt_bottom['pct']; ?>"></span>
                                                    <span class="cdt-val"><?php echo (int) $cdt_bottom['pct']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($cdt_second_bottom): ?>
                                                <div class="cdt-chip edge">
                                                    <span class="cdt-tag">Growth Area</span>
                                                    <span class="cdt-name"><?php echo esc_html($cdt_second_bottom['label']); ?></span>
                                                    <span class="cdt-meter"
                                                        style="--val:<?php echo (int) $cdt_second_bottom['pct']; ?>"></span>
                                                    <span class="cdt-val"><?php echo (int) $cdt_second_bottom['pct']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        // CDT caption mapping
                                        $cdt_phrase = [
                                            'Ambiguity Tolerance' => 'you can stay open and flexible when outcomes are unclear.',
                                            'Value Conflict Navigation' => 'you work through differences in values without shutting down.',
                                            'Self-Confrontation Capacity' => 'you reflect and adjust when actions and values diverge.',
                                            'Discomfort Regulation' => 'you remain steady when conversations get tense.',
                                            'Conflict Resolution Tolerance' => 'you stay engaged through conflict to reach resolution.'
                                        ];
                                        $cdt_caption = '';
                                        if ($cdt_top || $cdt_bottom || $cdt_second_bottom) {
                                            if ($cdt_top) {
                                                $nm = $cdt_top['label'];
                                                $cdt_caption .= 'Your strongest area, ' . $nm . ', suggests ' . ($cdt_phrase[$nm] ?? 'a reliable strength under pressure') . ' ';
                                            }
                                            $growth_areas = [];
                                            if ($cdt_bottom) {
                                                $growth_areas[] = $cdt_bottom['label'];
                                            }
                                            if ($cdt_second_bottom) {
                                                $growth_areas[] = $cdt_second_bottom['label'];
                                            }
                                            if (!empty($growth_areas)) {
                                                if (count($growth_areas) == 1) {
                                                    $cdt_caption .= 'Your growth area, ' . $growth_areas[0] . ', points to where focused practice can help.';
                                                } else {
                                                    $cdt_caption .= 'Your growth areas, ' . $growth_areas[0] . ' and ' . $growth_areas[1] . ', point to where focused practice can help.';
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($cdt_caption)): ?>
                                            <p class="pc-caption"><?php echo esc_html($cdt_caption); ?></p>
                                        <?php endif; ?>
                                    </section>
                                    <!-- Radar Overview inside the card -->
                                    <section class="pc-spider">
                                        <header class="pc-spider-head">
                                            <h3 class="pc-kicker pc-spider-title">How It All Fits</h3>
                                        </header>
                                        <div class="pc-spider-canvas">
                                            <canvas id="compositeRadar" aria-label="Combined Profile Radar" role="img"></canvas>
                                        </div>
                                        <p class="pc-caption pc-spider-desc">How my profile looks as one picture.</p>
                                    </section>
                                </article>

                                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                                <script>
                                                                                             (function () {
                                                        const el = document.getElementById('compositeRadar');
                                                        if (!el || typeof Chart === 'undefined') return;
                                                        const chartLabels = <?php echo json_encode(array_map(function ($l) {
                                                            return explode(' ', $l)[0];
                                                        }, $chart_labels)); ?>;
                                                        const dataMI = <?php echo json_encode($mi_chart_data); ?>;
                                                        const dataCDT = <?php echo json_encode($cdt_chart_data); ?>;
                                                        const dataPT = <?php echo json_encode($pt_chart_data); ?>;
                                                        const miHighlight = new Array(17).fill(null);
                                                        const cdtHighlight = new Array(17).fill(null);
                                                        const ptHighlight = new Array(17).fill(null);
                                                        const miTopIdx = dataMI.slice(0, 8).map((v, i) => ({ v, i })).sort((a, b) => (b.v || 0) - (a.v || 0)).slice(0, 3).map(o => o.i);
                                                        miTopIdx.forEach(i => miHighlight[i] = dataMI[i]);
                                                        const cdtSlice = dataCDT.slice(8, 13).map((v, i) => ({ v, i: i + 8 })).sort((a, b) => (b.v || 0) - (a.v || 0));
                                                        if (cdtSlice.length && cdtSlice[0].v != null) cdtHighlight[cdtSlice[0].i] = dataCDT[cdtSlice[0].i];
                                                        for (let i = 13; i <= 16; i++) ptHighlight[i] = dataPT[i];
                                                        const zonesBg = { id: 'zonesBg', beforeDraw(chart) { const { ctx, scales: { r } } = chart; if (!r) return; const band = (a, b, c) => { const inner = r.getDistanceFromCenterForValue(a), outer = r.getDistanceFromCenterForValue(b); ctx.save(); ctx.fillStyle = c; ctx.beginPath(); ctx.arc(r.xCenter, r.yCenter, outer, 0, 2 * Math.PI); ctx.arc(r.xCenter, r.yCenter, inner, 0, 2 * Math.PI, true); ctx.closePath(); ctx.fill(); ctx.restore(); }; band(70, 100, 'rgba(16,185,129,0.06)'); band(40, 70, 'rgba(245,158,11,0.06)'); band(0, 40, 'rgba(239,68,68,0.05)'); } };
                                                        new Chart(el, {
                                                            type: 'radar',
                                                            data: {
                                                                labels: chartLabels, datasets: [
                                                                    { label: 'MI', data: dataMI, fill: true, borderWidth: 1, pointRadius: 0, backgroundColor: 'rgba(54,162,235,0.08)', borderColor: 'rgba(54,162,235,0.5)' },
                                                                    { label: 'CDT', data: dataCDT, fill: true, borderWidth: 1, pointRadius: 0, backgroundColor: 'rgba(255,99,132,0.08)', borderColor: 'rgba(255,99,132,0.5)' },
                                                                    { label: 'Player', data: dataPT, fill: true, borderWidth: 1, pointRadius: 0, backgroundColor: 'rgba(75,192,192,0.08)', borderColor: 'rgba(75,192,192,0.5)' },
                                                                    { label: 'Top MI', data: miHighlight, fill: true, borderWidth: 3, pointRadius: 2, backgroundColor: 'rgba(54,162,235,0.25)', borderColor: 'rgb(54,162,235)' },
                                                                    { label: 'Top CDT', data: cdtHighlight, fill: true, borderWidth: 3, pointRadius: 2, backgroundColor: 'rgba(255,99,132,0.25)', borderColor: 'rgb(255,99,132)' },
                                                                    { label: 'Player (hi)', data: ptHighlight, fill: true, borderWidth: 3, pointRadius: 2, backgroundColor: 'rgba(75,192,192,0.20)', borderColor: 'rgb(75,192,192)' }
                                                                ]
                                                            },
                                                            options: { responsive: true, maintainAspectRatio: false, layout: { padding: { left: 0, right: 0 } }, elements: { line: { tension: 0.2 } }, scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { stepSize: 25, backdropColor: 'transparent' }, grid: { color: 'rgba(100,116,139,0.15)' }, angleLines: { color: 'rgba(100,116,139,0.15)' }, pointLabels: { font: { size: 14 } } } }, plugins: { legend: { position: 'bottom', align: 'center', labels: { filter: i => ['MI', 'CDT', 'Player'].includes(i.text) } } } },
                                                            plugins: [zonesBg]
                                                        });
                                                    })();
                                                </script>

                                                <div class="composite-cta profile-links" style="text-align:center"><em>
                                                        <?php if ($mi_url): ?><a class="inline-link" href="<?php echo esc_url($mi_url); ?>">View MI
                                                                    results</a> · <?php endif; ?>
                                                        <?php if ($cdt_url): ?><a class="inline-link" href="<?php echo esc_url($cdt_url); ?>">View Growth Strengths
                                                                    results</a> · <?php endif; ?>
                                                        <?php if ($bartle_url): ?><a class="inline-link" href="<?php echo esc_url($bartle_url); ?>">View
                                                                    Core Motivation results</a><?php endif; ?>
                                                    </em></div>
                                            </div>
                                            <div id="tab-path" class="tab-content">
                                                <div class="quiz-dashboard-grid">
                                                    <?php
                                                    foreach ($quizzes as $id => $quiz):
                                                        $has_results = $completion_status[$id] ?? false;
                                                        $quiz_page_url = $this->find_page_by_shortcode($quiz['shortcode']);
                                                        // All quizzes are available in any order
                                                        $dependency_met = true;
                                                        $dependency_title = '';
                                                        $description = ($has_results && !empty($quiz['description_completed'])) ? $quiz['description_completed'] : (!empty($saved_descriptions[$id]) ? $saved_descriptions[$id] : $quiz['description']);

                                                        if ($has_results && $id === 'mi-quiz') {
                                                            // MI Quiz Card
                                                        } elseif ($has_results && $id === 'cdt-quiz') {
                                                            // CDT Quiz Card
                                                            if (!empty($cdt_prediction)) {
                                                                echo '<p class="quiz-dashboard-description" style="margin-top:8px; font-style:italic; font-size:0.9em;">' . esc_html($cdt_prediction) . '</p>';
                                                            }
                                                        } elseif ($has_results && $id === 'bartle-quiz') {
                                                            // Bartle Quiz Card
                                                            if (!empty($bartle_prediction)) {
                                                                echo '<p class="quiz-dashboard-description" style="margin-top:8px; font-style:italic; font-size:0.9em;">' . esc_html($bartle_prediction) . '</p>';
                                                            }
                                                        }
                                                        ?>
                                                        </div>
                                                        <div class="quiz-dashboard-actions">
                                                            <a href="<?php echo esc_url($quiz_page_url); ?>"
                                                                class="quiz-dashboard-button quiz-dashboard-button-secondary">View Results</a>
                                                        </div>
                                                    </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <!-- AI Coach Tab -->
                                    <div id="tab-ai" class="tab-content active">
                                        <?php
                                        if (class_exists('Micro_Coach_AI')) {
                                            (new Micro_Coach_AI())->render_ai_coach_tab();
                                        }
                                        ?>
                                    </div>
                                    <!-- Saved Experiments Tab -->
                                    <div id="tab-saved" class="tab-content">
                                        <h3 class="ai-title">Saved Experiments</h3>
                                        <p class="ai-sub">Your liked experiments appear here. Click a card to view details or un‑heart to
                                            remove.</p>
                                        <div class="ai-results-grid" id="ai-saved"></div>
                                    </div>
                                    <?php
                                    // Allow plugins to add custom tab content
                                    // Loop through custom tabs and create their content divs
                                    foreach ($custom_tabs as $tab_id => $tab_label) {
                                        if ($tab_id !== 'tab-lab') {
                                            echo '<div id="' . esc_attr($tab_id) . '" class="tab-content">';
                                            do_action('mc_dashboard_custom_tab_content', $tab_id);
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                        <?php else: ?>
                                <!-- Your Path Section (for users who have NOT completed all quizzes) -->
                                <h2 class="quiz-dashboard-section-title" style="display:none;">Your Path</h2>
                                <div class="quiz-dashboard-grid" style="display:none;">
                                    <?php
                                    foreach ($quizzes as $id => $quiz):
                                        $has_results = $completion_status[$id] ?? false;
                                        $quiz_page_url = $this->find_page_by_shortcode($quiz['shortcode']);
                                        // All quizzes are available in any order
                                        $dependency_met = true;
                                        $dependency_title = '';
                                        $description = ($has_results && !empty($quiz['description_completed'])) ? $quiz['description_completed'] : (!empty($saved_descriptions[$id]) ? $saved_descriptions[$id] : $quiz['description']);
                                        $prediction_paragraph = '';
                                        if ($id === 'cdt-quiz' && ($completion_status['mi-quiz'] ?? false)) {
                                            $mi_prompts_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-cdt-prompts.php';
                                            if (file_exists($mi_prompts_file)) {
                                                require $mi_prompts_file;
                                                $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
                                                if (is_array($mi_results) && empty($mi_results['top3']) && !empty($mi_results['part1Scores']) && is_array($mi_results['part1Scores'])) {
                                                    $scores = $mi_results['part1Scores'];
                                                    arsort($scores);
                                                    $mi_results['top3'] = array_keys(array_slice($scores, 0, 3, true));
                                                }
                                                if (!empty($mi_results['top3']) && count($mi_results['top3']) >= 3 && isset($mi_cdt_prompts)) {
                                                    $top3_keys = $mi_results['top3'];
                                                    sort($top3_keys);
                                                    $prompt_key = implode('_', $top3_keys);
                                                    if (isset($mi_cdt_prompts[$prompt_key]['prompt'])) {
                                                        $prediction_paragraph = $mi_cdt_prompts[$prompt_key]['prompt'];
                                                    }
                                                }
                                            }
                                        }
                                        $bartle_prediction_paragraph = '';
                                        if ($id === 'bartle-quiz' && ($completion_status['mi-quiz'] ?? false) && ($completion_status['cdt-quiz'] ?? false)) {
                                            $bartle_predictions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/predictions.php';
                                            if (file_exists($bartle_predictions_file)) {
                                                require $bartle_predictions_file;
                                                $mi_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                                                if (file_exists($mi_questions_file)) {
                                                    require_once $mi_questions_file;
                                                }
                                                $cdt_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                                                if (file_exists($cdt_questions_file)) {
                                                    require_once $cdt_questions_file;
                                                }
                                                $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
                                                $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
                                                if (is_array($mi_results) && is_array($cdt_results) && isset($player_type_templates)) {
                                                    $bartle_scores = ['explorer' => 0, 'achiever' => 0, 'socializer' => 0, 'strategist' => 0];
                                                    $mi_map = ['logical-mathematical' => ['achiever', 'strategist'], 'linguistic' => ['socializer'], 'spatial' => ['explorer'], 'bodily-kinesthetic' => ['achiever'], 'musical' => ['explorer', 'socializer'], 'interpersonal' => ['socializer', 'strategist'], 'intrapersonal' => ['explorer'], 'naturalistic' => ['explorer'],];
                                                    $cdt_map = ['ambiguity-tolerance' => ['explorer'], 'value-conflict-navigation' => ['socializer'], 'self-confrontation-capacity' => ['achiever'], 'discomfort-regulation' => ['achiever', 'strategist'], 'growth-orientation' => ['explorer']];
                                                    if (!empty($mi_results['top3'])) {
                                                        $points = 3;
                                                        foreach ($mi_results['top3'] as $mi_slug) {
                                                            if (isset($mi_map[$mi_slug])) {
                                                                foreach ($mi_map[$mi_slug] as $bartle_type) {
                                                                    $bartle_scores[$bartle_type] += $points;
                                                                }
                                                            }
                                                            $points--;
                                                        }
                                                    }
                                                    if (!empty($cdt_results['sortedScores'][0])) {
                                                        $cdt_slug = $cdt_results['sortedScores'][0][0];
                                                        if (isset($cdt_map[$cdt_slug])) {
                                                            foreach ($cdt_map[$cdt_slug] as $bartle_type) {
                                                                $bartle_scores[$bartle_type] += 3;
                                                            }
                                                        }
                                                    }
                                                    arsort($bartle_scores);
                                                    $predicted_type = key($bartle_scores);
                                                    if (isset($player_type_templates[$predicted_type])) {
                                                        $template = $player_type_templates[$predicted_type][array_rand($player_type_templates[$predicted_type])];
                                                        $mi_names = array_map(function ($slug) use ($mi_categories) {
                                                            return $mi_categories[$slug] ?? '';
                                                        }, $mi_results['top3']);
                                                        $mi_strengths_str = 'a combination of ' . implode(', ', array_filter($mi_names));
                                                        $cdt_slug = $cdt_results['sortedScores'][0][0];
                                                        $cdt_strengths_str = 'a high capacity for ' . ($cdt_categories[$cdt_slug] ?? 'navigating challenges');
                                                        $bartle_prediction_paragraph = str_replace(['{mi_strengths}', '{cdt_strengths}'], [$mi_strengths_str, $cdt_strengths_str], $template);
                                                    }
                                                }
                                            }
                                        }
                                        $mi_profile_content = '';
                                        if ($has_results && $id === 'mi-quiz') {
                                            $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
                                            $mi_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                                            if (file_exists($mi_questions_file)) {
                                                require_once $mi_questions_file;
                                                if (!empty($mi_results['top3']) && isset($mi_categories)) {
                                                    $mi_profile_content = array_map(function ($slug) use ($mi_categories) {
                                                        return $mi_categories[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
                                                    }, $mi_results['top3']);
                                                }
                                            }
                                        }
                                        // Minimal CDT summary (top + growth)
                                        $cdt_summary = [];
                                        if ($has_results && $id === 'cdt-quiz') {
                                            $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
                                            $cdt_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                                            if (file_exists($cdt_questions_file)) {
                                                require_once $cdt_questions_file;
                                            }
                                            if (!empty($cdt_results['sortedScores'][0]) && isset($cdt_categories)) {
                                                $sorted = $cdt_results['sortedScores'];
                                                $top_slug = $sorted[0][0];
                                                $low_slug = end($sorted)[0];
                                                $cdt_summary['top'] = $cdt_categories[$top_slug] ?? ucfirst(str_replace('-', ' ', $top_slug));
                                                $cdt_summary['low'] = $cdt_categories[$low_slug] ?? ucfirst(str_replace('-', ' ', $low_slug));
                                            }
                                        }
                                        $bartle_profile_content = '';
                                        $bartle_profile_description = '';
                                        if ($has_results && $id === 'bartle-quiz') {
                                            $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
                                            $bartle_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';
                                            if (file_exists($bartle_questions_file)) {
                                                require_once $bartle_questions_file;
                                                if (!empty($bartle_results['sortedScores'][0]) && isset($bartle_categories) && isset($bartle_descriptions)) {
                                                    $top_slug = $bartle_results['sortedScores'][0][0];
                                                    $bartle_profile_content = $bartle_categories[$top_slug] ?? ucfirst(str_replace('-', ' ', $top_slug));
                                                    $bartle_profile_description = $bartle_descriptions[$top_slug] ?? '';
                                                }
                                            }
                                        }
                                        // Minimal Johari snapshot + adjectives
                                        $johari_snapshot = [];
                                        $johari_open_chips = [];
                                        $johari_blind_chips = [];
                                        if ($has_results && $id === 'johari-mi-quiz') {
                                            $johari = get_user_meta($user_id, 'johari_mi_profile', true);
                                            if (is_array($johari)) {
                                                $johari_snapshot['open'] = isset($johari['open']) ? count((array) $johari['open']) : 0;
                                                $johari_snapshot['blind'] = isset($johari['blind']) ? count((array) $johari['blind']) : 0;
                                                $johari_snapshot['hidden'] = isset($johari['hidden']) ? count((array) $johari['hidden']) : 0;
                                                $johari_snapshot['unknown'] = isset($johari['unknown']) ? count((array) $johari['unknown']) : 0;
                                                $johari_open_chips = array_slice(array_values((array) ($johari['open'] ?? [])), 0, 5);
                                                $johari_blind_chips = array_slice(array_values((array) ($johari['blind'] ?? [])), 0, 3);
                                            }
                                        }
                                        ?>
                                            <div class="quiz-dashboard-item <?php if (!$dependency_met)
                                                echo 'is-locked'; ?>">
                                                <div class="quiz-dashboard-item-header">
                                                    <h3 class="quiz-dashboard-title"><?php echo esc_html($quiz['title']); ?></h3>
                                                    <?php if ($has_results): ?><span class="quiz-dashboard-status-badge completed">Completed</span>
                                                    <?php elseif (!$dependency_met): ?><span class="quiz-dashboard-status-badge locked">Locked</span>
                                                    <?php else: ?><span class="quiz-dashboard-status-badge not-started">Not
                                                                Started</span><?php endif; ?>
                                                </div>
                                                <div class="quiz-dashboard-item-body">
                                                    <p class="quiz-dashboard-description"><?php echo esc_html($description); ?></p>
                                                    <?php if (!empty($mi_profile_content)): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-profile">
                                                                <h4 class="insight-panel-title">Your Top Intelligences</h4>
                                                                <div class="quiz-dashboard-chips"><?php foreach ($mi_profile_content as $name): ?><span
                                                                                class="chip"><?php echo esc_html($name); ?></span><?php endforeach; ?></div>
                                                            </div>
                                                    <?php endif; ?>
                                                    <?php if ($has_results && $id === 'cdt-quiz'): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-profile">
                                                                <h4 class="insight-panel-title">Your Resilience Snapshot</h4>
                                                                <?php if (!empty($cdt_summary)): ?>
                                                                        <p style="margin:0;">Strength: <strong><?php echo esc_html($cdt_summary['top']); ?></strong></p>
                                                                        <p style="margin:6px 0 0 0;">Growth Focus:
                                                                            <strong><?php echo esc_html($cdt_summary['low']); ?></strong>
                                                                        </p>
                                                                <?php else: ?>
                                                                        <p style="margin:0;">Results ready — view details to see your strongest dimension and growth
                                                                            focus.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($bartle_profile_content)): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-profile">
                                                                <h4 class="insight-panel-title">Your Primary Player Type</h4>
                                                                <div class="quiz-dashboard-chips"><span
                                                                        class="chip"><?php echo esc_html($bartle_profile_content); ?></span></div>
                                                                <?php if (!empty($bartle_profile_description)): ?>
                                                                        <p style="margin-top: 8px;"><?php echo esc_html($bartle_profile_description); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                    <?php endif; ?>
                                                    <?php if ($has_results && $id === 'johari-mi-quiz'): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-profile">
                                                                <h4 class="insight-panel-title">Johari Snapshot</h4>
                                                                <?php if (!empty($johari_snapshot)): ?>
                                                                        <p style="margin:0 0 8px 0;">Open:
                                                                            <strong><?php echo (int) $johari_snapshot['open']; ?></strong>
                                                                            • Blind: <strong><?php echo (int) $johari_snapshot['blind']; ?></strong> • Hidden:
                                                                            <strong><?php echo (int) $johari_snapshot['hidden']; ?></strong> • Unknown:
                                                                            <strong><?php echo (int) $johari_snapshot['unknown']; ?></strong>
                                                                        </p>
                                                                <?php else: ?>
                                                                        <p style="margin:0 0 8px 0;">Results ready — view details to see your Johari Window.</p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($johari_open_chips)): ?>
                                                                        <div class="quiz-dashboard-chips" style="margin-top:6px;">
                                                                            <?php foreach ($johari_open_chips as $w): ?><span
                                                                                        class="chip"><?php echo esc_html($w); ?></span><?php endforeach; ?>
                                                                        </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($johari_blind_chips)): ?>
                                                                        <p style="margin:10px 0 4px 0; font-weight:600;">Peers see (Blind):</p>
                                                                        <div class="quiz-dashboard-chips">
                                                                            <?php foreach ($johari_blind_chips as $w): ?><span
                                                                                        class="chip"><?php echo esc_html($w); ?></span><?php endforeach; ?>
                                                                        </div>
                                                                <?php endif; ?>
                                                            </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($bartle_prediction_paragraph)): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-prediction">
                                                                <h4 class="insight-panel-title">Your Personalized Bartle Prediction</h4>
                                                                <p><?php echo wp_kses_post($bartle_prediction_paragraph); ?></p>
                                                            </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($prediction_paragraph)): ?>
                                                            <div class="quiz-dashboard-insight-panel insight-panel-prediction">
                                                                <h4 class="insight-panel-title">Your Personalized Growth Strengths Prediction</h4>
                                                                <p><?php echo wp_kses_post($prediction_paragraph); ?></p>
                                                            </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="quiz-dashboard-actions">
                                                    <?php if ($dependency_met): ?>
                                                            <?php if ($quiz_page_url): ?>
                                                                    <a href="<?php echo esc_url($quiz_page_url); ?>"
                                                                        class="quiz-dashboard-button <?php if ($has_results)
                                                                            echo 'quiz-dashboard-button-secondary'; ?>"><?php echo $has_results ? 'View Results' : 'Start Assessment'; ?></a>
                                                            <?php else: ?>
                                                                    <span class="quiz-dashboard-button is-disabled"
                                                                        title="<?php printf(esc_attr__('Admin: Please create a page with the shortcode [%s] for this quiz.'), esc_attr($quiz['shortcode'])); ?>"><?php _e('Not Linked'); ?></span>
                                                            <?php endif; ?>
                                                    <?php else: ?>
                                                            <span class="quiz-dashboard-button is-disabled"
                                                                title="<?php printf(esc_attr__('Please complete "%s" first.'), esc_attr($dependency_title)); ?>"><?php _e('Locked'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php endif; ?>

                        <?php if ($completed_quizzes === $total_quizzes && $total_quizzes > 0): ?>
                                <!-- Insights & Activity Section (only when all quizzes complete) -->
                                <h2 class="quiz-dashboard-section-title">Insights &amp; Activity</h2>
                                <div class="quiz-dashboard-lower-grid">
                                    <div class="insight-panel">
                                        <h3 class="panel-title">Latest Insight</h3>
                                        <?php
                                        if ($latest_activity) {
                                            // Load data files needed for insights
                                            $mi_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                                            if (file_exists($mi_questions_file)) {
                                                require_once $mi_questions_file;
                                            }
                                            $cdt_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                                            if (file_exists($cdt_questions_file)) {
                                                require_once $cdt_questions_file;
                                            }
                                            $cdt_details_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/details.php';
                                            if (file_exists($cdt_details_file)) {
                                                require_once $cdt_details_file;
                                            }

                                            switch ($latest_activity['quiz_id']) {
                                                case 'mi-quiz':
                                                    // The top sub-skill is the first item in the 'top5' array.
                                                    if (!empty($latest_activity['results']['top5'][0])) {
                                                        $top_sub_skill = $latest_activity['results']['top5'][0];
                                                        $age_group = $latest_activity['results']['age'] ?? 'adult';

                                                        $sub_skill_name = $top_sub_skill['name'];
                                                        $parent_slug = $top_sub_skill['slug'];

                                                        $leverage_tip = '';
                                                        if (isset($mi_leverage_tips[$age_group][$parent_slug][$sub_skill_name]) && is_array($mi_leverage_tips[$age_group][$parent_slug][$sub_skill_name])) {
                                                            $tips = $mi_leverage_tips[$age_group][$parent_slug][$sub_skill_name];
                                                            $leverage_tip = $tips[array_rand($tips)]; // Pick a random tip
                                                        }

                                                        $latest_insight_html = '<p>Your top MI strength is <strong>' . esc_html($sub_skill_name) . '</strong>. This is a key part of your ' . esc_html($top_sub_skill['parent']) . '.</p>';
                                                        if ($leverage_tip) {
                                                            $latest_insight_html .= '<p class="insight-leverage-tip"><strong>To leverage this:</strong> ' . esc_html($leverage_tip) . '</p>';
                                                        }

                                                        // Add growth area
                                                        if (!empty($latest_activity['results']['bottom3'][0])) {
                                                            $lowest_sub_skill = $latest_activity['results']['bottom3'][0];
                                                            $sub_skill_name = $lowest_sub_skill['name'];
                                                            $parent_slug = $lowest_sub_skill['slug'];

                                                            $growth_tip = '';
                                                            if (isset($mi_growth_tips[$age_group][$parent_slug][$sub_skill_name]) && is_array($mi_growth_tips[$age_group][$parent_slug][$sub_skill_name])) {
                                                                $tips = $mi_growth_tips[$age_group][$parent_slug][$sub_skill_name];
                                                                $growth_tip = $tips[array_rand($tips)];
                                                            }

                                                            if ($growth_tip) {
                                                                $latest_insight_html .= '<p class="insight-growth-tip"><strong>An area for growth:</strong> In ' . esc_html($sub_skill_name) . ', try to ' . esc_html(lcfirst($growth_tip)) . '</p>';
                                                            }
                                                        }
                                                    }
                                                    break;
                                                case 'cdt-quiz':
                                                    if (!empty($latest_activity['results']['sortedScores'][0]) && isset($cdt_categories)) {
                                                        $top_cdt_slug = $latest_activity['results']['sortedScores'][0][0];
                                                        $top_cdt_name = $cdt_categories[$top_cdt_slug] ?? 'Unknown';
                                                        $latest_insight_html = '<p>Your Growth Strengths Assessment results indicate a high capacity for <strong>' . esc_html($top_cdt_name) . '</strong>. This is a key skill for navigating complex challenges.</p>';

                                                        // Add growth area
                                                        $sorted_scores = $latest_activity['results']['sortedScores'];
                                                        if (!empty($sorted_scores) && isset($cdt_dimension_details)) {
                                                            $lowest_cdt_slug = end($sorted_scores)[0];
                                                            $age_group = $latest_activity['results']['ageGroup'] ?? 'adult';

                                                            if (isset($cdt_dimension_details[$lowest_cdt_slug])) {
                                                                $details = $cdt_dimension_details[$lowest_cdt_slug];
                                                                $age_details = $details[$age_group] ?? $details['adult'];

                                                                if (!empty($age_details['growth']) && is_array($age_details['growth'])) {
                                                                    $growth_tip = $age_details['growth'][array_rand($age_details['growth'])];
                                                                    $lowest_cdt_name = $details['title'] ?? $cdt_categories[$lowest_cdt_slug] ?? 'Unknown';
                                                                    $latest_insight_html .= '<p class="insight-growth-tip"><strong>An area for growth:</strong> In ' . esc_html($lowest_cdt_name) . ', try to ' . esc_html(lcfirst($growth_tip)) . '</p>';
                                                                }
                                                            }
                                                        }
                                                    }
                                                    break;
                                            }
                                        }
                                        echo $latest_insight_html;
                                        ?>
                                    </div>
                                    <div class="activity-panel">
                                        <h3 class="panel-title">Recent Activity</h3>
                                        <ul class="activity-list">
                                            <?php foreach (array_slice($activity_feed, 0, 5) as $activity): ?>
                                                    <li><span class="activity-date"><?php echo esc_html(human_time_diff($activity['timestamp'])); ?>
                                                            ago:</span> <?php echo esc_html($activity['quiz_title']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                        <?php endif; ?>

                        <!-- Resources Section (Placeholder) -->
                        <div class="quiz-dashboard-resources">
                            <a href="#" class="resource-link"><span class="dashicons dashicons-editor-help"></span> Help Center</a>
                            <a href="#" class="resource-link"><span class="dashicons dashicons-info"></span> FAQs</a>
                            <a href="#" class="resource-link"><span class="dashicons dashicons-email-alt"></span> Contact Us</a>
                        </div>
                        </div>

                        <!-- AI Drawer (global overlay, positioned outside tab containers) -->
                        <?php if ($user_id && $completed_quizzes === $total_quizzes && $total_quizzes > 0 && class_exists('Micro_Coach_AI')): ?>
                                <div id="ai-drawer" class="ai-drawer" aria-hidden="true">
                                    <div class="ai-drawer-backdrop" id="ai-drawer-backdrop" tabindex="-1" aria-hidden="true"></div>
                                    <!-- Nav buttons placed outside the panel so they can float next to it -->
                                    <button type="button" class="ai-drawer-nav" id="ai-drawer-prev" aria-label="Previous">‹</button>
                                    <button type="button" class="ai-drawer-nav" id="ai-drawer-next" aria-label="Next">›</button>
                                    <aside class="ai-drawer-panel" role="dialog" aria-labelledby="ai-drawer-title">
                                        <button type="button" class="ai-drawer-close" id="ai-drawer-close" aria-label="Close">×</button>
                                        <header class="ai-drawer-header">
                                            <h3 id="ai-drawer-title" class="ai-drawer-title">Idea</h3>
                                            <span id="ai-drawer-lens" class="ai-drawer-lens"></span>
                                        </header>
                                        <p id="ai-drawer-micro" class="ai-drawer-micro"></p>
                                        <div class="ai-drawer-effort">
                                            <div class="eff"><span class="icn">💰</span><span id="eff-cost" class="bar"></span><span id="eff-cost-num"
                                                    class="num">0</span></div>
                                            <div class="eff"><span class="icn">⏳</span><span id="eff-time" class="bar"></span><span id="eff-time-num"
                                                    class="num">0</span></div>
                                            <div class="eff"><span class="icn">⚡️</span><span id="eff-energy" class="bar"></span><span
                                                    id="eff-energy-num" class="num">0</span></div>
                                            <div class="eff"><span class="icn">🎲</span><span id="eff-variety" class="bar"></span><span
                                                    id="eff-variety-num" class="num">0</span></div>
                                        </div>
                                        <section class="ai-drawer-section">
                                            <h4>🎯 Why this fits you</h4>
                                            <p id="ai-drawer-why"></p>
                                        </section>
                                        <section class="ai-drawer-section">
                                            <h4>▶️ Prompt to start</h4>
                                            <div class="ai-prompt-row">
                                                <textarea id="ai-prompt-input" class="ai-prompt-input ai-prompt-area" rows="3" readonly></textarea>
                                                <button id="ai-prompt-copy" class="ai-prompt-copy" type="button">Copy</button>
                                            </div>
                                            <p id="ai-drawer-prompt" class="ai-drawer-prompt" style="display:none;"></p>
                                        </section>
                                        <section class="ai-drawer-section">
                                            <h4>✅ Steps</h4>
                                            <ul id="ai-drawer-steps" class="ai-drawer-steps"></ul>
                                        </section>
                                        <section class="ai-drawer-section">
                                            <h4>👀 What to watch for</h4>
                                            <p id="ai-drawer-signal"></p>
                                        </section>
                                        <section class="ai-drawer-section">
                                            <h4>💭 Reflection</h4>
                                            <ul id="ai-drawer-reflect" class="ai-drawer-reflect"></ul>
                                        </section>
                                        <section class="ai-drawer-section" id="ai-drawer-safety-wrap" style="display:none;">
                                            <h4>⚠️ Safety notes</h4>
                                            <p id="ai-drawer-safety"></p>
                                        </section>
                                        <footer class="ai-drawer-footer">
                                            <div id="ai-drawer-chips" class="ai-drawer-chips"></div>
                                        </footer>
                                    </aside>
                                </div>
                        <?php endif; ?>

                <?php } else { // Logged-out user view
            // Ensure welcome styles are available for logged-out visitors
            wp_enqueue_style('dashboard-enhanced-css', plugins_url('assets/dashboard.css', __FILE__), [], '1.0.5');
            // Find the URL for the primary starting quiz.
            $mi_quiz_url = $this->find_page_by_shortcode('mi_quiz');
            ?>
                        <div class="dashboard-header">
                            <div class="site-branding">
                                <?php echo MC_Helpers::logo_img(['class' => 'site-logo', 'alt' => 'Skill of Self-Discovery Logo']); ?>
                                <span class="site-title">Skill of Self-Discovery</span>
                            </div>
                        </div>
                        <style>
                            .quiz-dashboard-auth-wrapper {
                                max-width: 900px;
                                margin: 2em auto;
                                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                            }

                            .dashboard-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding-bottom: 1.5em;
                                margin-bottom: 1.5em;
                                border-bottom: 1px solid #e2e8f0;
                            }

                            .site-branding {
                                display: flex;
                                align-items: center;
                                gap: 20px;
                                text-decoration: none;
                            }

                            .site-logo {
                                max-height: 100px;
                                width: auto;
                                height: auto;
                                max-width: 100%;
                            }

                            .site-title {
                                font-size: 1.8em;
                                font-weight: 600;
                                color: #1a202c;
                                line-height: 1.2;
                            }

                            .quiz-dashboard-auth-prompt {
                                background: #fff;
                                border: 1px solid #e2e8f0;
                                border-radius: 12px;
                                padding: 24px 32px;
                                text-align: center;
                                max-width: 500px;
                                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                            }

                            .quiz-dashboard-auth-prompt h2 {
                                font-size: 1.5em;
                                margin-top: 0;
                                color: #1a202c;
                            }

                            .quiz-dashboard-auth-prompt p {
                                font-size: 1em;
                                color: #4a5568;
                                margin-bottom: 1.5em;
                            }

                            .quiz-dashboard-auth-actions {
                                display: flex;
                                gap: 12px;
                                justify-content: center;
                            }

                            .quiz-dashboard-auth-prompt .quiz-dashboard-button-secondary {
                                background: #f1f3f4;
                                color: #2d3748;
                                border: 1px solid #e2e8f0;
                            }

                            .quiz-dashboard-auth-prompt .quiz-dashboard-button-secondary:hover {
                                background: #e2e8f0;
                                color: #1a202c;
                            }

                            @media (max-width: 600px) {
                                .quiz-dashboard-auth-actions {
                                    flex-direction: column;
                                    align-items: stretch;
                                }

                                .dashboard-header {
                                    justify-content: center;
                                }

                                .site-branding {
                                    flex-direction: column;
                                    gap: 10px;
                                }
                            }

                            .quiz-dashboard-admin-notice {
                                margin-top: 1.5em;
                                padding: 0.75em;
                                background-color: #fffbe6;
                                border: 1px solid #fde68a;
                                border-radius: 8px;
                                font-size: 0.9em;
                                text-align: left;
                                color: #92400e;
                            }
                        </style>
                        <?php /* Removed legacy hero block in favor of new sosd-wrap hero */ ?>
                        <style>
                            /* Public landing page (logged-out) */
                            .sosd-wrap {
                                --primary: #5C7CFA;
                                --primary600: #4A6EF2;
                                --ink: #0E1B3D;
                                --muted: #5C6B8A;
                                --line: #E9ECF8;
                                --bg: #FAFBFF;
                            }

                            .sosd-wrap .container {
                                max-width: 1120px;
                                margin: 0 auto;
                                padding: 0 24px;
                            }

                            .sosd-nav {
                                position: sticky;
                                top: 0;
                                z-index: 50;
                                background: #fff;
                                transition: background .2s, box-shadow .2s;
                                border-bottom: 1px solid var(--line);
                            }

                            .sosd-nav.scrolled {
                                background: #fff;
                                border-bottom-color: var(--line);
                                box-shadow: 0 2px 8px rgba(20, 30, 70, .06);
                            }

                            .sosd-nav.scrolled {
                                background: #fff;
                                border-bottom-color: var(--line);
                                box-shadow: 0 2px 8px rgba(20, 30, 70, .06);
                            }

                            .sosd-nav .container {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                padding: 14px 24px;
                            }

                            .sosd-nav .brand {
                                display: flex;
                                align-items: center;
                                gap: 12px;
                            }

                            .sosd-nav .brand__logo {
                                height: 48px;
                                width: auto;
                                border-radius: 10px;
                                box-shadow: 0 4px 20px rgba(92, 124, 250, .25);
                            }

                            .sosd-nav .brand__name {
                                font-weight: 700;
                                font-size: 20px;
                                color: var(--ink);
                                letter-spacing: .2px;
                            }

                            .sosd-nav .nav-ctas .btn {
                                margin-left: 8px;
                            }

                            .sosd-wrap .hero {
                                background: linear-gradient(180deg, #ffffff 0%, #f7f9ff 70%);
                                padding: 56px 0 24px;
                            }

                            /* Full-width hero content (right preview removed) */
                            .sosd-wrap .hero__grid {
                                display: grid;
                                grid-template-columns: 1fr;
                                gap: 32px;
                                align-items: center;
                            }

                            .sosd-wrap .hero h1 {
                                font-size: 40px;
                                line-height: 48px;
                                color: var(--ink);
                                margin: 0 0 12px;
                                font-weight: 600;
                            }

                            .sosd-wrap .hero .sub {
                                color: var(--muted);
                                font-size: 16px;
                                line-height: 24px;
                                margin: 0 0 16px;
                            }

                            .sosd-wrap .hero__ctas {
                                display: flex;
                                gap: 12px;
                                flex-wrap: wrap;
                            }

                            .sosd-wrap .btn {
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                padding: 12px 18px;
                                border-radius: 12px;
                                font-weight: 600;
                                text-decoration: none;
                                border: 1px solid transparent;
                            }

                            .sosd-wrap .btn--primary {
                                background: var(--primary);
                                color: #fff;
                            }

                            .sosd-wrap .btn--primary:hover {
                                background: var(--primary600);
                            }

                            .sosd-wrap .btn--ghost {
                                background: #fff;
                                color: #1f2937;
                                border-color: var(--line);
                            }

                            .sosd-wrap .micro-trust {
                                margin-top: 10px;
                                color: #6b7a99;
                                font-size: 13px;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                            }

                            .sosd-wrap .micro-trust .lock {
                                font-size: 14px;
                            }

                            /* Remove right preview box for now */
                            .sosd-wrap .hero__right {
                                display: none;
                            }

                            .sosd-wrap .id-row {
                                height: 14px;
                                border-radius: 8px;
                                background: #eef1fb;
                                margin: 10px 0;
                            }

                            .sosd-wrap .id-row.short {
                                width: 60%;
                            }

                            .sosd-wrap .id-head {
                                height: 64px;
                                border-radius: 12px;
                                background: #eef1fb;
                                margin-bottom: 10px;
                            }

                            .sosd-wrap .shimmer {
                                position: relative;
                                overflow: hidden;
                            }

                            .sosd-wrap .shimmer:after {
                                content: '';
                                position: absolute;
                                inset: 0;
                                background: linear-gradient(100deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .6) 50%, rgba(255, 255, 255, 0) 100%);
                                transform: translateX(-100%);
                                animation: sh 1.8s infinite;
                            }

                            @keyframes sh {
                                to {
                                    transform: translateX(100%);
                                }
                            }

                            /* No outer box */
                            .sosd-column-card {
                                background: transparent;
                                border: none;
                                box-shadow: none;
                                padding: 0;
                                margin: 24px auto 8px;
                                max-width: 1120px;
                            }

                            /* Headline-format section heading */
                            .sosd-column-card .intro-heading {
                                margin: 0 0 16px;
                                font-size: 40px;
                                line-height: 48px;
                                color: var(--ink);
                                font-weight: 600;
                            }

                            .flow {
                                display: grid;
                                gap: 28px;
                            }

                            .flow .step {
                                display: grid;
                                grid-template-columns: 72px 1fr;
                                gap: 18px;
                                align-items: start;
                            }

                            .step__icon {
                                width: 72px;
                                height: 72px;
                                display: grid;
                                place-items: center;
                                background: #f2f5ff;
                                border: 1px solid var(--line);
                                border-radius: 16px;
                            }

                            .step-kicker {
                                color: #6b7a99;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: .06em;
                                font-size: 12px;
                            }

                            .step h2 {
                                margin: 6px 0 8px;
                                font-size: 20px;
                                color: var(--ink);
                            }

                            .step .sub {
                                color: #5c6b8a;
                                margin: 0 0 10px;
                            }

                            .cards {
                                display: grid;
                                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                                gap: 14px;
                            }

                            /* Match funnel card look */
                            .card {
                                border: 2px solid #e5e7eb;
                                border-radius: 12px;
                                padding: 14px;
                                background: #fff;
                                transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
                            }

                            .card:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 4px 12px rgba(59, 130, 246, .12);
                                border-color: #3b82f6;
                            }

                            .card__left {
                                display: flex;
                                gap: 10px;
                                align-items: flex-start;
                            }

                            .card__icon {
                                width: 28px;
                                height: 28px;
                                display: grid;
                                place-items: center;
                                background: #eef1fb;
                                border-radius: 8px;
                            }

                            .card__title {
                                font-weight: 600;
                                color: #1a2238;
                            }

                            .card__sub {
                                color: #56627f;
                                font-size: 14px;
                                line-height: 20px;
                            }

                            /* Full-width horizontal cards */
                            .cards--full {
                                grid-template-columns: 1fr;
                            }

                            .cards--full .card {
                                display: flex;
                                align-items: flex-start;
                                padding: 16px;
                            }

                            .cards--full .card__left {
                                gap: 14px;
                            }

                            .cards--full .card__icon {
                                width: 40px;
                                height: 40px;
                                font-size: 18px;
                            }

                            .cards--full .card__title {
                                font-size: 16px;
                            }

                            .cards--full .card__sub {
                                font-size: 15px;
                                line-height: 22px;
                            }

                            .cta-bottom {
                                text-align: center;
                                padding: 8px 24px 32px;
                            }

                            /* Mobile adjustments */
                            @media (max-width: 720px) {
                                .sosd-nav .brand__logo {
                                    height: 56px;
                                }

                                .sosd-wrap .hero__grid {
                                    grid-template-columns: 1fr;
                                }

                                .sosd-wrap .hero {
                                    padding-top: 36px;
                                }

                                .sosd-wrap .hero__ctas .btn {
                                    width: 100%;
                                }

                                .sosd-nav .nav-ctas .btn {
                                    display: none;
                                }

                                /* Stack step icon above title on mobile */
                                .flow .step {
                                    grid-template-columns: 1fr;
                                    text-align: center;
                                }

                                .flow .step .step__icon {
                                    margin: 0 auto 10px;
                                }

                                .flow .step .sub {
                                    text-align: center;
                                }
                            }
                        </style>
                        <div id="mc-age-gate-overlay"
                            style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
                            <style>
                                #mc-age-gate-overlay .mc-age-opt {
                                    color: #1f2937 !important;
                                    background: #fff;
                                    border: 2px solid #e2e8f0;
                                    border-radius: 8px;
                                    padding: 12px;
                                    text-align: left;
                                    cursor: pointer;
                                }

                                #mc-age-gate-overlay .mc-age-opt:hover {
                                    background: #f8fafc;
                                    border-color: #3b82f6;
                                    color: #1f2937 !important;
                                }

                                #mc-age-gate-overlay #mc-age-cancel {
                                    color: #1f2937;
                                }
                            </style>
                            <div
                                style="background:#fff; border-radius:12px; padding:24px; max-width:520px; width:92%; box-shadow:0 20px 40px rgba(0,0,0,0.1);">
                                <h3 style="margin:0 0 8px; color:#1a202c;">Tell us about yourself</h3>
                                <p style="margin:0 0 16px; color:#4b5563;">Pick the option that best fits you to tailor questions.</p>
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <button data-age="teen" class="mc-age-opt">Teen / High School</button>
                                    <button data-age="graduate" class="mc-age-opt">Student / Recent Graduate</button>
                                    <button data-age="adult" class="mc-age-opt">Adult / Professional</button>
                                </div>
                                <div style="text-align:center; margin-top:12px;">
                                    <button id="mc-age-cancel"
                                        style="background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; padding:8px 14px; cursor:pointer;">Cancel</button>
                                </div>
                            </div>
                        </div>
                <?php } ?>

                <script>
                    // Simple tabs controller for the dashboard tabs
                    document.addEventListener('DOMContentLoaded', function () {
                        // Age gate on Start Journey (logged-out landing)
                        var startBtn = document.getElementById('mc-start-journey');
                        var startBtnHero = document.querySelector('.sosd-wrap .hero .btn--primary');
                        var overlay = document.getElementById('mc-age-gate-overlay');
                        function attach(btn) {
                            if (!btn || !overlay) return;
                            btn.addEventListener('click', function (e) {
                                e.preventDefault();
                                overlay.style.display = 'flex';
                            });
                        }
                        attach(startBtn); attach(startBtnHero);
                        if (overlay) {
                            overlay.addEventListener('click', function (ev) { if (ev.target === overlay) overlay.style.display = 'none'; });
                            var cancelBtn = document.getElementById('mc-age-cancel');
                            if (cancelBtn) cancelBtn.addEventListener('click', function () { overlay.style.display = 'none'; });
                            var opts = overlay.querySelectorAll('.mc-age-opt');
                            opts.forEach(function (opt) {
                                opt.addEventListener('click', function () {
                                    var age = opt.getAttribute('data-age') || 'adult';
                                    try { localStorage.setItem('mc_age_group', age); } catch (e) { }
                                    var target = (startBtnHero && startBtnHero.getAttribute('href')) || (startBtn && startBtn.getAttribute('href')) || '#';
                                    var sep = target.indexOf('?') === -1 ? '?' : '&';
                                    window.location.href = target + sep + 'age=' + encodeURIComponent(age);
                                });
                            });
                        }

                        const tabContainer = document.querySelector('.dashboard-tabs');
                        if (!tabContainer) return;
                        const tabLinks = tabContainer.querySelectorAll('.tab-link');
                        const tabContents = document.querySelectorAll('.tab-content-wrapper .tab-content');
                        tabLinks.forEach(link => {
                            link.addEventListener('click', function (e) {
                                e.preventDefault();
                                const tabId = this.dataset.tab;
                                tabLinks.forEach(l => l.classList.remove('active'));
                                tabContents.forEach(c => c.classList.remove('active'));
                                this.classList.add('active');
                                const activeContent = document.getElementById(tabId);
                                if (activeContent) activeContent.classList.add('active');
                            });
                        });
                        // Optional: CTA link to switch to details tab
                        const cta = document.getElementById('go-to-details-tab');
                        if (cta) {
                            cta.addEventListener('click', function (e) {
                                e.preventDefault();
                                const detailsBtn = tabContainer.querySelector('.tab-link[data-tab="tab-path"]');
                                if (detailsBtn) detailsBtn.click();
                            });
                        }

                        // Logged-out landing sticky nav behavior
                        const nav = document.getElementById('sosdNav');
                        if (nav) {
                            const onScroll = () => {
                                if (window.scrollY > 24) nav.classList.add('scrolled');
                                else nav.classList.remove('scrolled');
                            };
                            onScroll();
                            window.addEventListener('scroll', onScroll, { passive: true });
                        }

                        // CDT Grid Layout Helper (for browsers without :has() support)
                        const cdtGrid = document.querySelector('.pc-cdt-grid');
                        if (cdtGrid) {
                            const chipCount = cdtGrid.querySelectorAll('.cdt-chip').length;
                            if (chipCount === 2) {
                                cdtGrid.classList.add('two-items');
                            }
                        }
                    });
                </script>
                <style>
                    /* ===== Self-Discovery Player Card (Baseball Card) ===== */
                    :root {
                        --pc-surface: #ffffff;
                        --pc-text: #0f172a;
                        --pc-muted: #6b7280;
                        --pc-line: #e5e7eb;
                        --pc-accent: #7aa2ff;
                        --pc-good: #28c596;
                        --pc-edge: #ff7a7a;
                        --pc-shadow: 0 14px 40px rgba(2, 6, 23, .10), 0 4px 10px rgba(2, 6, 23, .06);
                    }

                    @media (prefers-color-scheme: dark) {
                        :root {
                            --pc-surface: #0f1220;
                            --pc-text: #e6e9f2;
                            --pc-muted: #9aa3b2;
                            --pc-line: #26304a;
                            --pc-shadow: 0 18px 44px rgba(0, 0, 0, .55);
                        }
                    }

                    .sosd-playercard {
                        width: min(720px, 98%);
                        margin: 14px auto;
                        background: var(--pc-surface);
                        color: var(--pc-text);
                        border: 1px solid var(--pc-line);
                        border-radius: 20px;
                        box-shadow: var(--pc-shadow);
                        padding: 14px 16px 18px;
                        text-align: center;
                        position: relative;
                        overflow: hidden;
                    }

                    .pc-head {
                        display: grid;
                        grid-template-columns: 1fr auto 1fr;
                        align-items: end;
                        gap: 8px;
                        margin-bottom: 2px;
                    }

                    .pc-rank {
                        justify-self: start;
                        font-size: 12px;
                        letter-spacing: .08em;
                        text-transform: uppercase;
                        color: var(--pc-muted);
                    }

                    .pc-title {
                        margin: 0;
                        font-size: clamp(26px, 4vw, 34px);
                        line-height: 1.1;
                    }

                    .pc-pct {
                        font-weight: 800;
                        padding: 6px 10px;
                        border-radius: 999px;
                        background: color-mix(in oklab, var(--pc-accent) 16%, transparent);
                        border: 1px solid color-mix(in oklab, var(--pc-accent) 40%, var(--pc-line));
                    }

                    .pc-emblem {
                        --pct: 72;
                        width: min(260px, 70vw);
                        aspect-ratio: 1;
                        margin: 8px auto 10px;
                        position: relative;
                        display: grid;
                        place-items: center;
                    }

                    .pc-emblem::after {
                        content: "";
                        position: absolute;
                        inset: -10px;
                        border-radius: 50%;
                        background: conic-gradient(var(--pc-accent) calc(var(--pct, 0)*1%), transparent 0), radial-gradient(circle at 50% 50%, transparent 64%, rgba(0, 0, 0, .05) 65%, transparent 66%);
                        mask: radial-gradient(circle at 50% 50%, transparent 60%, #000 61%);
                    }

                    .pc-emblem-ring {
                        position: absolute;
                        inset: 0;
                        border-radius: 50%;
                        background: radial-gradient(closest-side, color-mix(in oklab, var(--pc-accent) 18%, transparent), transparent 70%), conic-gradient(from 0turn, color-mix(in oklab, var(--pc-accent) 12%, transparent), transparent);
                        border: 1px solid var(--pc-line);
                        box-shadow: inset 0 0 60px color-mix(in oklab, var(--pc-accent) 14%, transparent);
                    }

                    .pc-emoji {
                        font-size: clamp(56px, 9vw, 84px);
                        filter: drop-shadow(0 6px 16px rgba(0, 0, 0, .12));
                    }

                    .pc-pct--overlay {
                        position: absolute;
                        top: 4px;
                        right: 4px;
                        font-size: 13px;
                        backdrop-filter: blur(2px);
                    }

                    .pc-kicker {
                        margin: 4px 0 8px;
                        font-size: 12px;
                        letter-spacing: .12em;
                        text-transform: uppercase;
                        color: var(--pc-muted);
                    }

                    .pc-mi-grid {
                        list-style: none;
                        padding: 0;
                        margin: 0;
                        display: grid;
                        gap: 10px;
                        grid-template-columns: repeat(3, 1fr);
                    }

                    @media (max-width:560px) {
                        .pc-mi-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    .mi {
                        background: linear-gradient(180deg, color-mix(in oklab, var(--pc-accent) 8%, transparent), transparent);
                        border: 1px solid var(--pc-line);
                        border-radius: 14px;
                        padding: 12px 10px 10px;
                        display: grid;
                        grid-template-columns: 52px 1fr auto;
                        grid-template-rows: auto auto;
                        gap: 6px 8px;
                        align-items: center;
                    }

                    .mi-gauge {
                        grid-row: 1 / span 2;
                        width: 46px;
                        aspect-ratio: 1;
                        border-radius: 50%;
                        position: relative;
                        display: grid;
                        place-items: center;
                        font-weight: 900;
                        color: #0f172a;
                        background: conic-gradient(color-mix(in oklab, var(--pc-accent) 35%, transparent) calc(var(--val, 0)*1%), rgba(127, 141, 170, .12) 0), radial-gradient(circle at 50% 50%, #fff 52%, transparent 53%);
                        border: 1px solid var(--pc-line);
                    }

                    .mi-gauge::after {
                        content: attr(data-val);
                        font-size: 14px;
                    }

                    .mi-icon {
                        font-size: 20px;
                    }

                    .mi-label {
                        font-weight: 700;
                        text-align: left;
                    }

                    /* Johari section styles */
                    .pc-johari {
                        border: none;
                        border-radius: 14px;
                        padding: 12px 14px;
                        background: #f8fafc;
                        margin: 10px 0 16px;
                    }

                    @media (prefers-color-scheme: dark) {
                        .pc-johari {
                            background: #111827;
                        }
                    }

                    .pc-johari-lists .johari-q {
                        background: #fff;
                        border: 1px solid #e6e9f2;
                        border-radius: 10px;
                        padding: 10px;
                    }

                    @media (prefers-color-scheme: dark) {
                        .pc-johari-lists .johari-q {
                            background: #0f1220;
                            border-color: #26304a;
                        }
                    }

                    .pc-johari+.pc-cdt {
                        border-top: none;
                        padding-top: 18px;
                        margin-top: 10px;
                    }

                    /* Increase contrast of CDT chip titles */
                    .sosd-playercard .cdt-chip .cdt-name {
                        color: #0f172a;
                        font-weight: 700;
                    }

                    @media (prefers-color-scheme: dark) {
                        .sosd-playercard .cdt-chip .cdt-name {
                            color: #e6e9f2;
                        }
                    }

                    .mi-val {
                        font-weight: 800;
                        justify-self: end;
                    }

                    .pc-cdt {
                        margin-top: 10px;
                    }

                    .pc-cdt-grid {
                        display: grid;
                        gap: 10px;
                        grid-template-columns: 1fr 1fr 1fr;
                    }

                    @media (max-width:768px) {
                        .pc-cdt-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    /* Special layout when only 2 items (1 strength + 1 growth) */
                    .pc-cdt-grid:has(.cdt-chip:nth-child(2):last-child) {
                        grid-template-columns: 1fr 1fr;
                    }

                    @supports not selector(:has(*)) {
                        .pc-cdt-grid.two-items {
                            grid-template-columns: 1fr 1fr;
                        }
                    }

                    .cdt-chip {
                        display: grid;
                        grid-template-columns: auto 1fr auto;
                        grid-template-rows: auto auto;
                        align-items: center;
                        gap: 6px 8px;
                        border: 1px solid var(--pc-line);
                        border-radius: 12px;
                        padding: 10px 12px;
                        background: #f8fafc;
                    }

                    @media (prefers-color-scheme: dark) {
                        .cdt-chip {
                            background: #121627;
                        }
                    }

                    .cdt-tag {
                        font-size: 12px;
                        font-weight: 800;
                        text-transform: uppercase;
                        letter-spacing: .08em;
                        color: var(--pc-muted);
                        grid-column: 1 / span 1;
                        padding: 2px 6px;
                        border-radius: 999px;
                    }

                    .cdt-chip.good .cdt-tag {
                        background: color-mix(in oklab, var(--pc-good) 20%, transparent);
                        color: #065f46;
                    }

                    .cdt-chip.edge .cdt-tag {
                        background: color-mix(in oklab, var(--pc-edge) 20%, transparent);
                        color: #7f1d1d;
                    }

                    .cdt-name {
                        font-weight: 800;
                        grid-column: 2 / span 1;
                        text-align: left;
                    }

                    .cdt-val {
                        font-weight: 800;
                        grid-column: 3 / span 1;
                    }

                    .cdt-meter {
                        grid-column: 1 / span 3;
                        height: 8px;
                        border-radius: 999px;
                        background: #e5e7eb;
                        position: relative;
                        overflow: hidden;
                    }

                    .cdt-meter::after {
                        content: "";
                        position: absolute;
                        inset: 0 0 0 0;
                        width: calc(var(--val, 0) * 1%);
                        background: var(--pc-good);
                    }

                    .cdt-chip.edge .cdt-meter::after {
                        background: var(--pc-edge);
                    }

                    .sosd-playercard.pt--explorer {
                        --pc-accent: #42c6ff;
                    }

                    .sosd-playercard.pt--achiever {
                        --pc-accent: #7aa2ff;
                    }

                    .sosd-playercard.pt--socializer {
                        --pc-accent: #60d394;
                    }

                    .sosd-playercard.pt--strategist {
                        --pc-accent: #ff9f43;
                    }

                    /* === SOSD Card Cleanup Patch === */
                    /* 1) Global rhythm & container breathing */
                    .sosd-playercard {
                        padding: 20px 22px 22px;
                        border-radius: 18px;
                        box-shadow: 0 10px 28px rgba(2, 6, 23, .08), 0 2px 6px rgba(2, 6, 23, .06);
                    }

                    .sosd-playercard .pc-title {
                        margin: 6px 0 4px;
                    }

                    .sosd-playercard .pc-emblem {
                        margin: 8px auto 18px;
                    }

                    .pc-kicker {
                        margin: 14px 0 10px;
                        letter-spacing: .14em;
                    }

                    /* optional: subtle divider between sections */
                    .sosd-playercard .pc-mi,
                    .sosd-playercard .pc-cdt {
                        position: relative;
                        padding-top: 6px;
                    }

                    .sosd-playercard .pc-mi::before,
                    .sosd-playercard .pc-cdt::before {
                        content: "";
                        position: absolute;
                        left: 0;
                        right: 0;
                        top: -6px;
                        height: 1px;
                        background: rgba(15, 23, 42, .06);
                    }

                    /* 2) MI tiles — remove redundancy & rebalance */
                    .sosd-playercard .pc-mi-grid {
                        gap: 14px;
                    }

                    .sosd-playercard .mi {
                        grid-template-columns: 44px 1fr auto;
                        padding: 12px 14px;
                    }

                    /* keep the score INSIDE the dial; hide the extra number below */
                    .sosd-playercard .mi-val {
                        display: none;
                    }

                    .sosd-playercard .mi-gauge {
                        width: 40px;
                        color: #0f172a;
                        background:
                            conic-gradient(color-mix(in oklab, var(--pc-accent) 60%, #cbd5e1) calc(var(--val, 0)*1%), #e8edf4 0),
                            radial-gradient(circle at 50% 50%, #fff 58%, transparent 59%);
                        border: 1px solid #e6e9f2;
                    }

                    .sosd-playercard .mi-gauge::after {
                        font-size: 12px;
                    }

                    .sosd-playercard .mi-label {
                        line-height: 1.2;
                    }

                    /* 3) CDT chips — more contrast, steady spacing, accommodates up to 3 items */
                    .sosd-playercard .pc-cdt-grid {
                        gap: 14px;
                        align-items: stretch;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    }

                    .sosd-playercard .cdt-chip {
                        padding: 12px;
                        background: #f9fafb;
                        border: 1px solid #e6e9f2;
                        display: grid;
                        grid-template-columns: 1fr auto;
                        grid-template-rows: auto auto auto;
                        row-gap: 6px;
                        column-gap: 8px;
                        min-height: 110px;
                    }

                    /* Bottom tag: centered title (row 1), meter+score (row 2), tag footer (row 3) */
                    .sosd-playercard .cdt-name {
                        grid-column: 1 / span 2;
                        font-size: 16px;
                        font-weight: 800;
                        margin: 0;
                        text-align: center;
                        grid-row: 1;
                    }

                    .sosd-playercard .cdt-meter {
                        grid-column: 1 / span 1;
                        align-self: center;
                        grid-row: 2;
                    }

                    .sosd-playercard .cdt-val {
                        grid-column: 2 / span 1;
                        align-self: center;
                        justify-self: end;
                        padding-left: 8px;
                        font-weight: 800;
                        font-variant-numeric: tabular-nums;
                        grid-row: 2;
                    }

                    .sosd-playercard .cdt-tag {
                        grid-column: 1 / span 2;
                        align-self: center;
                        justify-self: center;
                        margin-top: 6px;
                        grid-row: 3;
                    }

                    /* badges: tint only the tag; keep rest light */
                    .sosd-playercard .cdt-chip.good .cdt-tag {
                        background: #e8f8f2;
                        color: #0f5132;
                        padding: 2px 8px;
                        border-radius: 999px;
                        font-weight: 800;
                        font-size: 11px;
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-tag {
                        background: #ffe9ea;
                        color: #7f1d1d;
                        padding: 2px 8px;
                        border-radius: 999px;
                        font-weight: 800;
                        font-size: 11px;
                    }

                    .sosd-playercard .cdt-tag::before {
                        margin-right: 4px;
                    }

                    .sosd-playercard .cdt-chip.good .cdt-tag::before {
                        content: "▲";
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-tag::before {
                        content: "▽";
                    }

                    /* meters: same track; only fill color varies */
                    .sosd-playercard .cdt-meter {
                        height: 10px;
                        border-radius: 999px;
                        background: #e2e8f0;
                        position: relative;
                        overflow: hidden;
                    }

                    .sosd-playercard .cdt-chip.good .cdt-meter::after {
                        content: "";
                        position: absolute;
                        inset: 0;
                        width: calc(var(--val, 0)*1%);
                        background: #10b981;
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-meter::after {
                        content: "";
                        position: absolute;
                        inset: 0;
                        width: calc(var(--val, 0)*1%);
                        background: #ef4444;
                    }

                    /* 4) Emblem % pill positioning (keep inside ring) and soften ring */
                    .sosd-playercard .pc-emblem {
                        position: relative;
                    }

                    .sosd-playercard .pc-emblem .pc-pct {
                        position: absolute;
                        right: 16px;
                        top: 12px;
                        padding: 6px 10px;
                        border-radius: 999px;
                        font-weight: 800;
                        background: color-mix(in oklab, var(--pc-accent) 18%, #fff);
                        border: 1px solid color-mix(in oklab, var(--pc-accent) 40%, #e6e9f2);
                    }

                    .sosd-playercard .pc-emblem::after {
                        inset: -8px;
                        background:
                            conic-gradient(var(--pc-accent) calc(var(--pct, 0)*1%), transparent 0),
                            radial-gradient(circle at 50% 50%, transparent 66%, rgba(0, 0, 0, .04) 67%, transparent 68%);
                        mask: radial-gradient(circle at 50% 50%, transparent 62%, #000 63%);
                    }

                    /* 5) Link row — extra spacing so the card can breathe */
                    .sosd-playercard+.profile-links,
                    .sosd-playercard~.profile-links {
                        margin-top: 10px;
                    }

                    /* Player Type section grid and labels */
                    .sosd-playercard .pc-head.pc-head--center {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                    }

                    .sosd-playercard .pc-pt-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 16px;
                        align-items: start;
                        margin: 8px 0 18px;
                    }

                    @media (max-width:560px) {
                        .sosd-playercard .pc-pt-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    .sosd-playercard .pt-name {
                        font-weight: 800;
                        margin-top: 6px;
                    }

                    .sosd-playercard .pc-mi {
                        margin-top: 12px;
                        margin-bottom: 18px;
                        padding-top: 12px;
                    }

                    .sosd-playercard .pc-caption {
                        font-size: 13px;
                        color: #6b7280;
                        line-height: 1.4;
                        margin: 8px 0 0;
                    }

                    /* Unified section headings and main banner */
                    .sosd-playercard .pc-mainhead {
                        background: #f7fafc;
                        color: #0f172a;
                        border: 1px solid #e2e8f0;
                        border-radius: 10px;
                        padding: 10px 14px;
                        text-align: center;
                        font-weight: 800;
                        font-size: 32px;
                        margin-bottom: 12px;
                    }

                    .sosd-playercard .pc-title,
                    .sosd-playercard .pc-kicker {
                        font-size: 18px;
                        font-weight: 800;
                        letter-spacing: 0;
                        text-transform: none;
                        color: #0f172a;
                    }

                    .sosd-playercard .pc-kicker {
                        margin: 12px 0 10px;
                    }

                    /* De-emphasize secondary player type visually */
                    .sosd-playercard .pt-col.secondary .pc-emblem::after {
                        opacity: .75;
                        filter: saturate(.85) brightness(1.02);
                    }

                    .sosd-playercard .pt-col.secondary .pc-emoji {
                        opacity: .8;
                        filter: saturate(.85);
                    }

                    .sosd-playercard .pt-col.secondary .pc-emblem .pc-pct {
                        background: color-mix(in oklab, var(--pc-accent) 10%, #fff);
                        border: 1px solid color-mix(in oklab, var(--pc-accent) 25%, #e6e9f2);
                        color: #334155;
                    }

                    .sosd-playercard .pt-col.secondary .pt-name {
                        color: #475569;
                        font-weight: 700;
                    }

                    /* ===== Self-Discovery Player Card refinements (scoped) ===== */
                    /* Anchor % inside the emblem */
                    .sosd-playercard .pc-head {
                        grid-template-columns: 1fr auto 1fr;
                    }

                    .sosd-playercard .pc-pct {
                        position: absolute;
                        right: 10px;
                        top: 10px;
                        z-index: 2;
                    }

                    .sosd-playercard .pc-emblem {
                        position: relative;
                        margin-top: 6px;
                    }

                    .sosd-playercard .pc-emblem .pc-pct {
                        position: absolute;
                        right: 12px;
                        top: 12px;
                        background: color-mix(in oklab, var(--pc-accent) 16%, #fff);
                        border: 1px solid color-mix(in oklab, var(--pc-accent) 40%, var(--pc-line));
                        color: var(--pc-text);
                        padding: 6px 10px;
                        border-radius: 999px;
                        font-weight: 800;
                    }

                    /* Tighten vertical rhythm */
                    .sosd-playercard {
                        padding-top: 14px;
                        border-radius: 16px;
                        box-shadow: 0 10px 28px rgba(2, 6, 23, .08), 0 2px 6px rgba(2, 6, 23, .06);
                    }

                    .sosd-playercard .pc-title {
                        margin: 6px 0 0;
                    }

                    .sosd-playercard .pc-emblem {
                        margin: 6px auto 12px;
                        width: min(260px, 70vw);
                    }

                    /* MI tile tweaks: smaller, softer dial; bolder number */
                    .sosd-playercard .mi {
                        grid-template-columns: 42px 1fr auto;
                        padding: 10px 12px;
                    }

                    .sosd-playercard .mi-gauge {
                        width: 38px;
                        background:
                            conic-gradient(color-mix(in oklab, var(--pc-accent) 65%, #cbd5e1) calc(var(--val, 0)*1%), #e5e7eb 0),
                            radial-gradient(circle at 50% 50%, #fff 58%, transparent 59%);
                        color: #0f172a;
                        font-weight: 800;
                    }

                    .sosd-playercard .mi-gauge::after {
                        font-size: 11px;
                    }

                    .sosd-playercard .mi-label {
                        line-height: 1.2;
                        font-weight: 700;
                        color: #0f172a;
                    }

                    .sosd-playercard .mi-val {
                        font-weight: 800;
                        font-size: 15px;
                    }

                    /* CDT chip contrast: clearer strongest vs edge */
                    .sosd-playercard .pc-cdt-grid {
                        align-items: stretch;
                    }

                    .sosd-playercard .cdt-chip {
                        background: #f9fafb;
                        border: 1px solid #e6e9f2;
                    }

                    .sosd-playercard .cdt-chip.good .cdt-tag {
                        background: #e8f8f2;
                        color: #0f5132;
                        padding: 4px 8px;
                        border-radius: 999px;
                        font-weight: 800;
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-tag {
                        background: #ffe9ea;
                        color: #7f1d1d;
                        padding: 4px 8px;
                        border-radius: 999px;
                        font-weight: 800;
                    }

                    .sosd-playercard .cdt-tag::before {
                        margin-right: 4px;
                    }

                    .sosd-playercard .cdt-chip.good .cdt-tag::before {
                        content: "▲";
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-tag::before {
                        content: "▽";
                    }

                    .sosd-playercard .cdt-meter {
                        height: 8px;
                        background: #eceff3;
                        border-radius: 999px;
                        overflow: hidden;
                        position: relative;
                    }

                    .sosd-playercard .cdt-chip.good .cdt-meter::after {
                        content: "";
                        position: absolute;
                        inset: 0;
                        width: calc(var(--val, 0)*1%);
                        background: #28c596;
                    }

                    .sosd-playercard .cdt-chip.edge .cdt-meter::after {
                        content: "";
                        position: absolute;
                        inset: 0;
                        width: calc(var(--val, 0)*1%);
                        background: #ff7a7a;
                    }

                    /* Ring thickness + softness */
                    .sosd-playercard .pc-emblem::after {
                        inset: -8px;
                        background:
                            conic-gradient(var(--pc-accent) calc(var(--pct, 0)*1%), transparent 0),
                            radial-gradient(circle at 50% 50%, transparent 66%, rgba(0, 0, 0, .04) 67%, transparent 68%);
                        mask: radial-gradient(circle at 50% 50%, transparent 62%, #000 63%);
                    }

                    .quiz-dashboard-container {
                        max-width: 900px;
                        margin: 2em auto;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    }

                    .dashboard-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding-bottom: 1.5em;
                        margin-bottom: 1.5em;
                        border-bottom: 1px solid #e2e8f0;
                    }

                    .site-branding {
                        display: flex;
                        align-items: center;
                        gap: 20px;
                        text-decoration: none;
                    }

                    .site-logo {
                        max-height: 100px;
                        width: auto;
                        height: auto;
                        max-width: 100%;
                    }

                    .site-title {
                        font-size: 1.8em;
                        font-weight: 600;
                        color: #1a202c;
                        line-height: 1.2;
                    }

                    .quiz-dashboard-hero {
                        display: flex;
                        align-items: stretch;
                        gap: 24px;
                        margin-bottom: 2em;
                    }

                    .quiz-dashboard-hero-greeting {
                        flex-grow: 1;
                    }

                    .greeting-title {
                        font-size: 1.8em;
                        font-weight: 600;
                        color: #1a202c;
                        margin: 0 0 0.25em 0;
                    }

                    .greeting-subtitle {
                        font-size: 1em;
                        color: #4a5568;
                        margin: 0;
                    }

                    .quiz-dashboard-hero-progress-card {
                        flex-basis: 320px;
                        flex-shrink: 0;
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                    }

                    .progress-card-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: baseline;
                        margin-bottom: 8px;
                    }

                    .progress-card-title {
                        font-size: 1em;
                        font-weight: 600;
                        margin: 0;
                        color: #2d3748;
                    }

                    .progress-card-percent {
                        font-size: 0.9em;
                        font-weight: 600;
                        color: #4a5568;
                    }

                    .progress-bar-container {
                        width: 100%;
                        background: #e2e8f0;
                        border-radius: 99px;
                        height: 8px;
                        overflow: hidden;
                        margin-bottom: 20px;
                    }

                    .progress-bar-fill {
                        background: #4CAF50;
                        height: 100%;
                        transition: width 0.5s ease-in-out;
                    }

                    .progress-card-next-step-btn {
                        margin-top: auto;
                    }

                    .quiz-dashboard-section-title {
                        margin-top: 2.5em;
                        margin-bottom: 1em;
                        font-size: 1.5em;
                        font-weight: 600;
                        color: #1a202c;
                        padding-bottom: 0.5em;
                        border-bottom: 1px solid #e2e8f0;
                    }

                    /* Tabs + Composite card styles */
                    .dashboard-tabs {
                        position: sticky;
                        top: 0;
                        z-index: 5;
                        background: #fff;
                        display: flex;
                        gap: 8px;
                        flex-wrap: nowrap;
                        overflow-x: auto;
                        -webkit-overflow-scrolling: touch;
                        border-bottom: 1px solid #e2e8f0;
                        padding: 8px 0;
                        margin-bottom: 1em;
                    }

                    .dashboard-tabs::-webkit-scrollbar {
                        height: 8px;
                    }

                    .dashboard-tabs::-webkit-scrollbar-thumb {
                        background: #e5e7eb;
                        border-radius: 4px;
                    }

                    .tab-link {
                        flex: 0 0 auto;
                        background: #f8fafc;
                        color: #51607a;
                        border: 1px solid transparent;
                        border-radius: 8px;
                        padding: 8px 14px;
                        font-weight: 600;
                        font-size: 15px;
                        line-height: 1.2;
                        cursor: pointer;
                        user-select: none;
                        transition: all .2s ease;
                    }

                    @media (max-width: 720px) {
                        .dashboard-tabs {
                            flex-direction: column;
                            align-items: stretch;
                            gap: 10px;
                            overflow: visible;
                        }

                        .tab-link {
                            width: 100%;
                            text-align: center;
                        }
                    }

                    .tab-link.active {
                        color: #fff;
                        background: #1e40af;
                    }

                    .tab-content {
                        display: none;
                    }

                    .tab-content.active {
                        display: block;
                    }

                    .identity-card {
                        padding: clamp(12px, 1.6vw, 20px);
                    }

                    .identity-card .card-header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        margin-bottom: 8px;
                    }

                    .identity-card .card-header h2 {
                        font-size: clamp(18px, 2vw, 22px);
                        margin: 0;
                    }

                    .block {
                        padding: 14px 0;
                        border-top: 1px solid #e2e8f0;
                    }

                    .block:first-of-type {
                        border-top: 0;
                    }

                    .block-title {
                        font-size: 14px;
                        letter-spacing: .02em;
                        text-transform: uppercase;
                        color: #51607a;
                        margin: 0 0 10px;
                    }

                    .badges {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 6px;
                    }

                    .badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        background: #edf1fb;
                        color: #1b2437;
                        border: 1px solid #e6e9f2;
                        padding: 5px 9px;
                        border-radius: 999px;
                        font-weight: 600;
                    }

                    .badge::before {
                        content: "";
                        width: 8px;
                        height: 8px;
                        border-radius: 50%;
                        background: #7aa2ff;
                    }

                    .bars {
                        list-style: none;
                        margin: 0;
                        padding: 0;
                        display: grid;
                        gap: 10px;
                    }

                    .bar {
                        display: grid;
                        grid-template-columns: 1fr auto;
                        grid-template-rows: auto 6px;
                        gap: 6px 10px;
                        align-items: center;
                    }

                    .bar .label {
                        grid-column: 1 / span 1;
                        font-weight: 600;
                        color: #111826;
                    }

                    .bar .value {
                        grid-column: 2 / span 1;
                        color: #51607a;
                        font-variant-numeric: tabular-nums;
                    }

                    .bar .meter {
                        grid-column: 1 / span 2;
                        height: 6px;
                        background: #f1f3f4;
                        border: 1px solid #e6e9f2;
                        border-radius: 999px;
                        position: relative;
                        overflow: hidden;
                    }

                    .bar .fill {
                        --clr: #ffcc66;
                        position: absolute;
                        inset: 0 auto 0 0;
                        width: calc(var(--val, 0) * 1%);
                        border-radius: 999px;
                        background: var(--clr);
                    }

                    .bar[data-band="lower"] .fill {
                        --clr: #ff7a7a;
                    }

                    .bar[data-band="moderate"] .fill {
                        --clr: #ffcc66;
                    }

                    .bar[data-band="higher"] .fill {
                        --clr: #33d69f;
                    }

                    .player-card {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 10px;
                        align-items: center;
                    }

                    .player-card .primary,
                    .player-card .secondary {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        background: #f7f8fb;
                        border: 1px solid #e6e9f2;
                        border-radius: 12px;
                        padding: 10px 12px;
                    }

                    .player-card .secondary {
                        background: transparent;
                    }

                    .pill {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 6px 10px;
                        border-radius: 999px;
                        border: 1px solid #e6e9f2;
                        background: #edf1fb;
                        font-weight: 700;
                        color: #1b2437;
                    }

                    .pill-primary {
                        border-color: #a3bffa;
                        background: #dbeafe;
                    }

                    .pct {
                        font-weight: 800;
                        letter-spacing: .02em;
                        color: #111826;
                    }

                    .pct.subtle {
                        color: #51607a;
                    }

                    .microcopy {
                        font-size: 13px;
                        color: #51607a;
                        margin: 8px 0 0;
                    }

                    .profile-grid {
                        display: grid;
                        grid-template-columns: 1fr;
                        gap: clamp(16px, 3vw, 32px);
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 24px;
                    }

                    .chart-panel {
                        min-height: 420px;
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 16px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -2px rgba(0, 0, 0, 0.04);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    /* In-card radar spacing and centering */
                    .sosd-playercard .pc-spider {
                        margin: 24px 0 10px;
                        padding-top: 16px;
                        position: relative;
                    }

                    .sosd-playercard .pc-spider::before {
                        content: "";
                        position: absolute;
                        left: 0;
                        right: 0;
                        top: 0;
                        height: 1px;
                        background: rgba(15, 23, 42, .06);
                    }

                    .sosd-playercard .pc-spider-head {
                        text-align: center;
                        margin: 0;
                    }

                    .sosd-playercard .pc-spider-title {
                        margin: 0 0 6px;
                    }

                    .sosd-playercard .pc-spider-canvas {
                        max-width: 720px;
                        width: 100%;
                        height: 360px;
                        margin: 10px auto 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .sosd-playercard .pc-spider-desc {
                        margin: 12px auto 0;
                        max-width: 60ch;
                        text-align: center;
                    }

                    #compositeRadar {
                        width: 100% !important;
                        height: 100% !important;
                        display: block;
                    }

                    .composite-head {
                        margin: 0 0 12px 0;
                    }

                    .composite-title {
                        margin: 0 0 4px 0;
                        font-size: 1.35em;
                        font-weight: 700;
                        color: #111826;
                    }

                    .composite-subtitle {
                        margin: 0;
                        font-size: .95em;
                        color: #51607a;
                    }

                    .composite-cta {
                        margin-top: 12px;
                        color: #51607a;
                        font-size: .95em;
                    }

                    /* === AI Coach tab styles === */
                    .ai-alert {
                        background: #fff7ed;
                        border: 1px solid #fed7aa;
                        color: #7c2d12;
                        padding: 10px 12px;
                        border-radius: 8px;
                        margin-bottom: 12px;
                    }

                    .ai-filters {
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 16px;
                        margin-bottom: 16px;
                        box-shadow: 0 6px 14px rgba(15, 23, 42, .06);
                    }

                    .ai-banner {
                        background: #eff6ff;
                        border: 1px solid #bfdbfe;
                        color: #1e3a8a;
                        padding: 10px 12px;
                        border-radius: 8px;
                        margin-bottom: 12px;
                    }

                    .ai-banner.warning {
                        background: #fef3c7;
                        border: 1px solid #f59e0b;
                        color: #92400e;
                    }

                    .ai-banner.info {
                        background: #eff6ff;
                        border: 1px solid #60a5fa;
                        color: #1e40af;
                    }

                    .ai-section-sub {
                        margin: 4px 0 10px;
                        color: #475569;
                        font-size: 13px;
                    }

                    .ai-debug {
                        background: #fff7ed;
                        border: 1px solid #fed7aa;
                        color: #7c2d12;
                        padding: 10px 12px;
                        border-radius: 8px;
                        margin: 12px 0;
                    }

                    .ai-debug summary {
                        cursor: pointer;
                        font-weight: 600;
                    }

                    .ai-debug pre {
                        white-space: pre-wrap;
                        overflow: auto;
                        max-height: 260px;
                        background: #fff;
                        border: 1px solid #eee;
                        padding: 8px;
                        border-radius: 6px;
                    }

                    .ai-fheader {
                        display: flex;
                        gap: 10px;
                        align-items: flex-start;
                        margin-bottom: 6px;
                    }

                    .ai-title {
                        margin: 0 0 2px;
                        font-size: 1.1em;
                        font-weight: 800;
                    }

                    .ai-sub {
                        margin: 0 0 10px;
                        color: #64748b;
                    }

                    .ai-filter {
                        padding: 10px 0;
                        border-top: 1px solid #e8eef5;
                    }

                    .ai-filter:first-of-type {
                        border-top: 0;
                    }

                    /* Grid to show 2x2 sliders */
                    .ai-filter-grid {
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 12px 16px;
                    }

                    .ai-filter-grid .ai-filter {
                        border-top: 0;
                        padding: 8px 0;
                    }

                    @media (max-width: 720px) {
                        .ai-filter-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    .ai-filter-row {
                        display: grid;
                        grid-template-columns: 24px 1fr auto;
                        align-items: center;
                        gap: 8px;
                    }

                    .f-icn {
                        font-size: 18px;
                    }

                    .f-title {
                        font-weight: 700;
                        color: #0f172a;
                    }

                    .f-right {
                        font-size: 12px;
                        color: #64748b;
                    }

                    .f-sub {
                        color: #64748b;
                        font-size: 12px;
                        margin: 2px 0 6px;
                    }

                    .ai-filter input[type=range] {
                        width: 100%;
                        accent-color: #1e40af;
                    }

                    .ai-lenses {
                        display: flex;
                        gap: 16px;
                        flex-wrap: wrap;
                        margin-top: 12px;
                    }

                    .ai-lenses .lens-chip {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        border-radius: 999px;
                        padding: 6px 10px;
                        font-weight: 600;
                        cursor: pointer;
                        user-select: none;
                        border: 1px solid transparent;
                    }

                    .ai-lenses .lens-chip input {
                        position: absolute;
                        opacity: 0;
                        width: 1px;
                        height: 1px;
                        overflow: hidden;
                    }

                    .ai-lenses .lens-chip span {
                        position: relative;
                        padding-left: 18px;
                    }

                    .ai-lenses .lens-chip span::before {
                        content: "";
                        position: absolute;
                        left: 0;
                        top: 3px;
                        width: 12px;
                        height: 12px;
                        border-radius: 3px;
                        background: #fff;
                        border: 1px solid rgba(15, 23, 42, .25);
                    }

                    .ai-lenses .lens-chip input:checked+span::after {
                        content: "✓";
                        position: absolute;
                        left: 1px;
                        top: 0px;
                        font-size: 12px;
                        color: #111827;
                    }

                    /* Color variants matching lens badges */
                    .lens-curiosity {
                        background: #e0f2fe;
                        color: #075985;
                        border-color: #7dd3fc;
                    }

                    .lens-rolemodels {
                        background: #ede9fe;
                        color: #5b21b6;
                        border-color: #c4b5fd;
                    }

                    .lens-opposites {
                        background: #fee2e2;
                        color: #7f1d1d;
                        border-color: #fecaca;
                    }

                    .lens-adjacency {
                        background: #dcfce7;
                        color: #065f46;
                        border-color: #bbf7d0;
                    }

                    .ai-actions {
                        display: flex;
                        gap: 12px;
                        align-items: center;
                        margin-top: 12px;
                    }

                    .ai-actions .linklike {
                        background: none;
                        border: none;
                        color: #1e40af;
                        cursor: pointer;
                        padding: 0;
                    }

                    .ai-section {
                        margin: 12px 0 8px;
                        font-weight: 700;
                    }

                    .ai-results-grid {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: 12px;
                    }

                    @media (max-width: 768px) {
                        .ai-results-grid {
                            grid-template-columns: 1fr;
                        }

                        .ai-sliders {
                            grid-template-columns: 1fr 1fr;
                        }
                    }

                    /* Reserve room on the right for the heart button so text doesn't overlap */
                    .ai-card {
                        position: relative;
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 12px 48px 12px 12px;
                        min-height: 110px;
                        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
                    }

                    /* Arrow hint (hidden on skeletons). Circle badge appears on hover for emphasis. */
                    .ai-card:not(.skeleton)::before {
                        content: '';
                        position: absolute;
                        right: 6px;
                        bottom: 6px;
                        width: 28px;
                        height: 28px;
                        border-radius: 50%;
                        background: #3b82f6;
                        opacity: 0;
                        transform: scale(.8);
                        transition: opacity .16s ease, transform .16s ease;
                    }

                    .ai-card:not(.skeleton)::after {
                        content: '\2197';
                        /* ↗ */
                        position: absolute;
                        right: 13px;
                        bottom: 9px;
                        font-size: 16px;
                        color: #334155;
                        transition: color .16s ease, transform .16s ease;
                    }

                    .ai-card:hover:not(.skeleton)::before {
                        opacity: 1;
                        transform: scale(1.1);
                    }

                    .ai-card:hover:not(.skeleton)::after {
                        color: #fff;
                        transform: translateY(-1px) scale(1.1);
                    }

                    .ai-card h5 {
                        margin: 0 32px 4px 0;
                        font-size: 17px;
                        line-height: 1.2;
                        font-weight: 700;
                    }

                    .ai-lens-badge {
                        display: inline-block;
                        background: #eef2f7;
                        border-radius: 999px;
                        padding: 3px 10px;
                        font-size: 12px;
                        color: #334155;
                        margin: 0 0 6px;
                    }

                    .ai-heart {
                        position: absolute;
                        top: 8px;
                        right: 8px;
                        width: 28px;
                        height: 28px;
                        border-radius: 50%;
                        background: #f1f5f9;
                        color: #ef4444;
                        border: 1px solid #e5e7eb;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        font-size: 15px;
                        z-index: 1;
                        transition: all 0.2s ease;
                    }

                    .ai-heart.active {
                        background: #dc2626;
                        color: #ffffff;
                        border-color: #dc2626;
                        transform: scale(1.1);
                    }

                    .ai-heart:focus-visible {
                        outline: 2px solid #3b82f6;
                        outline-offset: 2px;
                    }

                    .lens-curiosity {
                        background: #e0f2fe !important;
                        color: #075985 !important;
                    }

                    .lens-rolemodels {
                        background: #ede9fe !important;
                        color: #5b21b6 !important;
                    }

                    .lens-opposites {
                        background: #fee2e2 !important;
                        color: #7f1d1d !important;
                    }

                    .lens-adjacency {
                        background: #dcfce7 !important;
                        color: #065f46 !important;
                    }

                    .ai-micro {
                        color: #475569;
                        margin: 0 8px 8px 0;
                    }

                    .ai-effrow {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }

                    .ai-eff {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        min-width: 0;
                        font-size: 13px;
                        color: #334155;
                    }

                    .eff-cost {
                        color: #f59e0b;
                    }

                    .eff-time {
                        color: #3b82f6;
                    }

                    .eff-energy {
                        color: #facc15;
                    }

                    .eff-variety {
                        color: #8b5cf6;
                    }

                    .ai-card.skeleton {
                        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
                        background-size: 400% 100%;
                        animation: shimmer 1.4s ease infinite;
                    }

                    @keyframes shimmer {
                        0% {
                            background-position: -400px 0;
                        }

                        100% {
                            background-position: 400px 0;
                        }
                    }

                    /* Hide hover arrow affordance for Saved grid */
                    #ai-saved .ai-card::before,
                    #ai-saved .ai-card::after {
                        display: none !important;
                        content: none !important;
                    }

                    /* Drawer styles */
                    .ai-drawer {
                        position: fixed;
                        inset: 0;
                        z-index: 1000;
                        display: none;
                    }

                    .ai-drawer.open {
                        display: block;
                    }

                    .ai-drawer-backdrop {
                        position: absolute;
                        inset: 0;
                        background: rgba(15, 23, 42, 0.35);
                    }

                    /* Centered modal instead of side drawer */
                    .ai-drawer-panel {
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: min(720px, 92vw);
                        max-height: 86vh;
                        overflow: auto;
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
                        padding: 18px 56px 24px;
                    }

                    .ai-drawer-close {
                        position: absolute;
                        top: 8px;
                        right: 10px;
                        border: none;
                        background: transparent;
                        font-size: 22px;
                        cursor: pointer;
                        color: #475569;
                    }

                    .ai-drawer-header {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }

                    .ai-drawer-title {
                        margin: 0 0 4px;
                    }

                    .ai-drawer-lens {
                        display: inline-block;
                        background: #eef2f7;
                        border-radius: 999px;
                        padding: 4px 12px;
                        font-size: 13px;
                        font-weight: 600;
                        color: #334155;
                    }

                    .ai-drawer-micro {
                        color: #475569;
                    }

                    .ai-drawer-effort {
                        display: flex;
                        gap: 10px;
                        margin: 8px 0;
                    }

                    .ai-drawer-effort .eff {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                    }

                    .ai-drawer-effort .bar {
                        --val: 0;
                        width: 100px;
                        height: 8px;
                        border-radius: 999px;
                        background: linear-gradient(90deg, #60a5fa var(--val), #e5e7eb var(--val));
                        outline: 1px solid #e5e7eb;
                    }

                    .ai-drawer-effort .num {
                        font-size: 12px;
                        color: #475569;
                    }

                    .ai-drawer-section {
                        margin-top: 12px;
                    }

                    .ai-drawer-section h4 {
                        margin: 0 0 6px;
                        font-size: 0.95em;
                    }

                    .ai-drawer-steps {
                        padding-left: 16px;
                    }

                    .ai-drawer-reflect {
                        padding-left: 16px;
                    }

                    .ai-drawer-prompt {
                        background: #eef2ff;
                        border: 1px solid #c7d2fe;
                        color: #1e3a8a;
                        padding: 8px 10px;
                        border-radius: 8px;
                    }

                    .ai-prompt-row {
                        display: flex;
                        gap: 8px;
                        align-items: center;
                    }

                    .ai-prompt-input {
                        flex: 1;
                        background: #fff;
                        border: 1px solid #c7d2fe;
                        border-radius: 8px;
                        padding: 8px 10px;
                        font-size: 14px;
                        resize: vertical;
                    }

                    .ai-prompt-area {
                        min-height: 64px;
                    }

                    .ai-stars {
                        display: flex;
                        gap: 4px;
                        align-items: center;
                    }

                    .ai-star {
                        font-size: 18px;
                        color: #d1d5db;
                        cursor: pointer;
                    }

                    .ai-star.active {
                        color: #f59e0b;
                    }

                    .ai-prompt-copy {
                        background: #3b82f6;
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        padding: 6px 10px;
                        cursor: pointer;
                    }

                    .ai-drawer-footer {
                        margin-top: 12px;
                    }

                    .ai-drawer-chips .chip {
                        margin-right: 8px;
                        background: #eef2f7;
                        border-radius: 999px;
                        padding: 4px 10px;
                        font-size: 14px;
                        display: inline-block;
                    }

                    /* Centered side nav arrows inside the modal */
                    /* Outside-floating nav arrows relative to viewport */
                    .ai-drawer-nav {
                        position: fixed;
                        top: 50%;
                        transform: translateY(-50%);
                        background: rgba(255, 255, 255, 0.98);
                        border: 1px solid #e2e8f0;
                        color: #334155;
                        width: 44px;
                        height: 44px;
                        border-radius: 50%;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
                        font-size: 22px;
                        transition: transform .15s ease, background-color .15s ease, color .15s ease, box-shadow .15s ease;
                        z-index: 1001;
                    }

                    .ai-drawer-nav:hover {
                        background: #3b82f6;
                        color: #fff;
                        transform: translateY(-50%) scale(1.08);
                        box-shadow: 0 8px 18px rgba(59, 130, 246, 0.25);
                    }

                    .ai-drawer-nav:active {
                        transform: translateY(-50%) scale(0.98);
                    }

                    .ai-drawer-nav:focus-visible {
                        outline: 2px solid #3b82f6;
                        outline-offset: 2px;
                    }

                    .ai-drawer-nav:disabled {
                        opacity: 0.35;
                        cursor: default;
                        pointer-events: none;
                    }

                    /* Position arrows just outside the modal width (min(720px, 92vw)) */
                    #ai-drawer-prev {
                        left: calc(50% - min(720px, 92vw)/2 - 28px);
                    }

                    #ai-drawer-next {
                        right: calc(50% - min(720px, 92vw)/2 - 28px);
                    }

                    @media (max-width: 640px) {

                        /* On small screens, keep arrows near viewport edges for reachability */
                        #ai-drawer-prev {
                            left: 8px;
                        }

                        #ai-drawer-next {
                            right: 8px;
                        }
                    }

                    .bars-grid {
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 12px;
                    }

                    .badge-icn {
                        margin-right: 6px;
                    }

                    .mi-badges {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: 8px;
                    }

                    .mi-badges .badge {
                        width: 100%;
                        justify-content: center;
                    }

                    .inline-link {
                        color: #1e40af;
                        text-decoration: none;
                    }

                    .inline-link:hover {
                        text-decoration: underline;
                    }

                    .ring {
                        --val: 0;
                        --size: 44px;
                        --track: #e5e7eb;
                        --fill: #1e40af;
                        width: var(--size);
                        height: var(--size);
                        display: inline-grid;
                        place-items: center;
                        border-radius: 50%;
                        background: conic-gradient(var(--fill) calc(var(--val) * 1%), var(--track) 0);
                        position: relative;
                    }

                    .ring::before {
                        content: "";
                        position: absolute;
                        inset: 4px;
                        background: #fff;
                        border-radius: 50%;
                    }

                    .ring .ring-label {
                        position: relative;
                        font-size: 0.8em;
                        font-weight: 700;
                        color: #111826;
                    }

                    .ring-subtle {
                        --fill: #64748b;
                    }

                    /* MI tiles: badge-style vertical layout (Emoji → Label → Gauge) */
                    .sosd-playercard .pc-mi-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 14px;
                    }

                    @media (max-width: 560px) {
                        .sosd-playercard .pc-mi-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    .sosd-playercard .pc-mi-grid .mi {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: flex-start;
                        padding: 18px 14px;
                        border: 1px solid #e6e9f2;
                        border-radius: 16px;
                        background: #fff;
                        box-shadow: 0 6px 14px rgba(15, 23, 42, .06);
                        transition: transform .15s ease, box-shadow .15s ease;
                        cursor: default;
                    }

                    .sosd-playercard .pc-mi-grid .mi:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 12px 24px rgba(15, 23, 42, .10);
                    }

                    .sosd-playercard .pc-mi-grid .mi-icon {
                        order: 1;
                        font-size: 72px;
                        line-height: 1;
                        margin: 0 0 6px;
                    }

                    .sosd-playercard .pc-mi-grid .mi-label {
                        order: 2;
                        font-weight: 800;
                        text-align: center;
                        margin: 0 0 6px;
                        line-height: 1.25;
                        min-height: 2.6em;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .sosd-playercard .pc-mi-grid .mi-gauge {
                        order: 3;
                        width: 60px;
                        aspect-ratio: 1;
                        border-radius: 50%;
                        display: grid;
                        place-items: center;
                        font-weight: 900;
                        color: #0a1325;
                        /* Lighter ring + larger white core for maximum contrast */
                        background:
                            conic-gradient(color-mix(in oklab, var(--mi-accent, #1e40af) 42%, #e7ecf3) calc(var(--val, 0)*1%), #f2f5f9 0),
                            radial-gradient(circle at 50% 50%, #ffffff 64%, transparent 65%);
                        border: 1px solid #d6dbe3;
                        position: relative;
                        overflow: hidden;
                    }

                    /* Extra white core to ensure legibility over the ring in all themes */
                    .sosd-playercard .pc-mi-grid .mi-gauge::before {
                        content: "";
                        position: absolute;
                        inset: 18%;
                        background: #fff;
                        border-radius: 50%;
                        z-index: 0;
                    }

                    .sosd-playercard .pc-mi-grid .mi-gauge::after {
                        content: attr(data-val);
                        font-size: clamp(14px, 2.6vw, 18px);
                        font-weight: 900;
                        z-index: 1;
                        /* Soft outline using multiple shadows to pop on any color */
                        text-shadow:
                            0 1px 0 #fff,
                            0 -1px 0 #fff,
                            1px 0 0 #fff,
                            -1px 0 0 #fff,
                            0 0 2px rgba(255, 255, 255, 0.7);
                    }

                    .sosd-playercard .pc-mi-grid .mi-val {
                        display: none;
                    }

                    /* Per-intelligence accents (optional) */
                    .sosd-playercard .pc-mi-grid .mi--interpersonal {
                        --mi-accent: #3b82f6;
                    }

                    .sosd-playercard .pc-mi-grid .mi--intrapersonal {
                        --mi-accent: #8b5cf6;
                    }

                    .sosd-playercard .pc-mi-grid .mi--bodily-kinesthetic {
                        --mi-accent: #22c55e;
                    }

                    .sosd-playercard .pc-mi-grid .mi--linguistic {
                        --mi-accent: #0ea5e9;
                    }

                    .sosd-playercard .pc-mi-grid .mi--logical-mathematical {
                        --mi-accent: #f59e0b;
                    }

                    .sosd-playercard .pc-mi-grid .mi--spatial {
                        --mi-accent: #06b6d4;
                    }

                    .sosd-playercard .pc-mi-grid .mi--musical {
                        --mi-accent: #e11d48;
                    }

                    .sosd-playercard .pc-mi-grid .mi--naturalistic {
                        --mi-accent: #10b981;
                    }

                    @media (max-width: 768px) {
                        .profile-grid {
                            grid-template-columns: 1fr;
                        }

                        .chart-panel {
                            min-height: 360px;
                        }
                    }

                    .quiz-dashboard-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 24px;
                        margin-bottom: 2.5em;
                    }

                    .quiz-dashboard-list {
                        list-style: none;
                        padding: 0;
                        margin: 1em 0;
                    }

                    .quiz-dashboard-item {
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 20px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                        display: flex;
                        flex-direction: column;
                        transition: all 0.2s ease-in-out;
                    }

                    .quiz-dashboard-item:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07);
                    }

                    .quiz-dashboard-item.is-locked {
                        opacity: 0.6;
                        background: #f8fafc;
                        pointer-events: none;
                    }

                    .quiz-dashboard-item.is-locked .quiz-dashboard-actions .quiz-dashboard-button {
                        pointer-events: auto;
                    }

                    /* Allow tooltip on button */

                    .quiz-dashboard-item-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        gap: 12px;
                        margin-bottom: 12px;
                    }

                    .quiz-dashboard-item-body {
                        border-top: 1px solid #e2e8f0;
                        padding-top: 12px;
                        flex-grow: 1;
                    }

                    .quiz-dashboard-actions {
                        margin-top: 16px;
                    }

                    .quiz-dashboard-title {
                        font-size: 1.1em;
                        font-weight: 600;
                        margin: 0;
                        color: #1a202c;
                    }

                    .quiz-dashboard-status-badge {
                        font-size: 0.75em;
                        font-weight: 600;
                        padding: 4px 8px;
                        border-radius: 9999px;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }

                    .quiz-dashboard-status-badge.completed {
                        background-color: #e6f4ea;
                        color: #34a853;
                    }

                    .quiz-dashboard-status-badge.locked {
                        background-color: #f1f3f4;
                        color: #5f6368;
                    }

                    .quiz-dashboard-status-badge.not-started {
                        background-color: #eef2ff;
                        color: #4f46e5;
                    }

                    .quiz-dashboard-description {
                        font-size: 0.9em;
                        color: #4a5568;
                        line-height: 1.5;
                        margin: 0;
                    }

                    .quiz-dashboard-button {
                        text-decoration: none;
                        background: #1e40af;
                        color: #fff;
                        padding: 8px 16px;
                        border-radius: 9999px;
                        font-weight: 600;
                        font-size: 0.9em;
                        transition: all 0.2s;
                        white-space: nowrap;
                        display: inline-block;
                        border: 1px solid transparent;
                    }

                    .quiz-dashboard-button:hover {
                        background: #1c358a;
                        color: #fff;
                        transform: translateY(-1px);
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
                    }

                    .quiz-dashboard-button.is-disabled {
                        background: #e2e8f0;
                        color: #a0aec0;
                        cursor: not-allowed;
                    }

                    .quiz-dashboard-button.is-disabled:hover {
                        background: #e2e8f0;
                        transform: none;
                        box-shadow: none;
                    }

                    .quiz-dashboard-button.quiz-dashboard-button-secondary {
                        background: #f1f3f4;
                        color: #2d3748;
                        border: 1px solid #e2e8f0;
                    }

                    .quiz-dashboard-button.quiz-dashboard-button-secondary:hover {
                        background: #e2e8f0;
                        color: #1a202c;
                        transform: none;
                        box-shadow: none;
                    }

                    .quiz-dashboard-insight-panel {
                        background-color: #f7fafc;
                        border: 1px solid #e2e8f0;
                        padding: 12px 16px;
                        border-radius: 8px;
                        margin-top: 12px;
                    }

                    .quiz-dashboard-insight-panel .insight-panel-title {
                        font-weight: 600;
                        margin: 0 0 8px 0;
                        font-size: 0.9em;
                        color: #2d3748;
                    }

                    .quiz-dashboard-insight-panel p {
                        font-size: 0.9em;
                        color: #4a5568;
                        line-height: 1.6;
                        max-width: 70ch;
                        margin: 0;
                    }

                    .insight-panel-prediction {
                        border-left: 4px solid #1e40af;
                    }

                    .insight-panel-profile {
                        border-left: 4px solid #4CAF50;
                    }

                    .quiz-dashboard-chips {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }

                    .chip {
                        background-color: #e2e8f0;
                        color: #2d3748;
                        padding: 4px 12px;
                        border-radius: 16px;
                        font-size: 0.85em;
                        font-weight: 500;
                    }

                    .quiz-dashboard-lower-grid {
                        display: grid;
                        grid-template-columns: 2fr 1fr;
                        gap: 24px;
                        align-items: start;
                    }

                    .panel-title {
                        font-size: 1.1em;
                        font-weight: 600;
                        margin: 0 0 1em 0;
                        color: #1a202c;
                    }

                    .insight-panel,
                    .activity-panel {
                        background: #fff;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        padding: 24px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                        transition: all 0.2s ease-in-out;
                    }

                    .insight-leverage-tip {
                        margin-top: 0.75em;
                        padding-top: 0.75em;
                        border-top: 1px dashed #e2e8f0;
                    }

                    .insight-growth-tip {
                        margin-top: 0.75em;
                        padding-top: 0.75em;
                        border-top: 1px dashed #f59e0b;
                    }

                    .placeholder-text {
                        color: #64748b;
                        font-style: italic;
                    }

                    .activity-list {
                        list-style: none;
                        padding: 0;
                        margin: 0;
                    }

                    .activity-list li {
                        padding: 8px 0;
                        border-bottom: 1px solid #f1f3f4;
                        font-size: 0.9em;
                        color: #4a5568;
                    }

                    .activity-list li:last-child {
                        border-bottom: none;
                    }

                    .activity-date {
                        font-weight: 500;
                        color: #2d3748;
                        margin-right: 8px;
                    }

                    .quiz-dashboard-resources {
                        display: flex;
                        gap: 24px;
                        justify-content: center;
                        margin-top: 2.5em;
                        padding-top: 1.5em;
                        border-top: 1px solid #e2e8f0;
                    }

                    .resource-link {
                        color: #4a5568;
                        text-decoration: none;
                        font-size: 0.9em;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                    }

                    .resource-link:hover {
                        color: #1a202c;
                    }

                    @media (max-width: 768px) {
                        .quiz-dashboard-hero {
                            flex-direction: column;
                        }

                        .quiz-dashboard-grid {
                            grid-template-columns: 1fr;
                        }

                        .quiz-dashboard-lower-grid {
                            grid-template-columns: 1fr;
                        }
                    }

                    @media (max-width: 600px) {
                        .dashboard-header {
                            justify-content: center;
                        }

                        .site-branding {
                            flex-direction: column;
                            gap: 10px;
                        }
                    }
                </style>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const tabContainer = document.querySelector('.dashboard-tabs');
                        if (!tabContainer) return;
                        const tabLinks = tabContainer.querySelectorAll('.tab-link');
                        const tabContents = document.querySelectorAll('.tab-content-wrapper .tab-content');
                        tabLinks.forEach(link => {
                            link.addEventListener('click', function (e) {
                                e.preventDefault();
                                const tabId = this.dataset.tab;
                                tabLinks.forEach(l => l.classList.remove('active'));
                                tabContents.forEach(c => c.classList.remove('active'));
                                this.classList.add('active');
                                const activeContent = document.getElementById(tabId);
                                if (activeContent) activeContent.classList.add('active');
                            });
                        });

                        // Switch to AI Coach tab button handler
                        const switchToAiBtn = document.getElementById('switch-to-ai-coach');
                        if (switchToAiBtn) {
                            switchToAiBtn.addEventListener('click', function (e) {
                                e.preventDefault();
                                // Find and click the AI Coach tab
                                const aiTabLink = tabContainer.querySelector('.tab-link[data-tab="tab-ai"]');
                                if (aiTabLink) {
                                    aiTabLink.click();
                                }
                            });
                        }

                        // AI Coach handlers
                        const ajaxUrl = '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>';
                        // Dynamic labels for filters
                        const labelMaps = {
                            cost: ['Free', 'Low', 'Medium', 'Higher', 'High'],
                            time: ['15–30m', '1–2h', 'Half‑day', 'Full day', 'Multi‑week'],
                            energy: ['Low‑focus', 'Moderate', 'Focused', 'High‑effort', 'Very high'],
                            variety: ['Near routine', 'Somewhat new', 'New context', 'Very new', 'Totally different']
                        };
                        function bindSlider(id, labelId, map) {
                            const s = document.getElementById(id), l = document.getElementById(labelId);
                            if (!s || !l) return; const upd = () => { const v = parseInt(s.value || 0, 10); l.textContent = map[Math.max(0, Math.min(4, v))] || ''; };
                            s.addEventListener('input', upd); upd();
                        }
                        bindSlider('ai-cost', 'ai-cost-label', labelMaps.cost);
                        bindSlider('ai-time', 'ai-time-label', labelMaps.time);
                        bindSlider('ai-energy', 'ai-energy-label', labelMaps.energy);
                        bindSlider('ai-variety', 'ai-variety-label', labelMaps.variety);
                        const applyBtn = document.getElementById('ai-apply');
                        const resetBtn = document.getElementById('ai-reset');
                        const shortlistEl = document.getElementById('ai-shortlist');
                        const moreWrap = document.getElementById('ai-more-wrap');
                        const moreEl = document.getElementById('ai-more');
                        const savedEl = document.getElementById('ai-saved');

                        function renderSkeleton(el, n = 6) { if (!el) return; el.innerHTML = ''; for (let i = 0; i < n; i++) { const d = document.createElement('div'); d.className = 'ai-card skeleton'; el.appendChild(d); } }
                        function chip(kind, val) {
                            const s = document.createElement('span'); s.className = 'chip';
                            const icons = { cost: '💰', time: '⏳', energy: '⚡️', variety: '🎲' };
                            const icon = icons[kind] || '';
                            s.textContent = (icon ? icon + ' ' : '') + String(val);
                            s.style.marginRight = '8px'; s.style.fontSize = '14px'; s.style.background = '#eef2f7'; s.style.borderRadius = '999px'; s.style.padding = '3px 10px';
                            s.setAttribute('aria-label', kind + ': ' + val);
                            return s;
                        }
                        let aiDefaults = { cost: 0, time: 1, energy: 1, variety: 2 };
                        // Track ideas for drawer navigation
                        let aiAllIdeas = [];
                        let aiActiveIndex = -1;
                        function toInt01(v, def) { const n = Number(v); const ok = Number.isFinite(n); const clamped = Math.max(0, Math.min(4, ok ? n : def)); return clamped; }
                        function renderIdeas(el, ideas) {
                            if (!el) return; el.innerHTML = '';
                            const drawer = document.getElementById('ai-drawer');
                            const closeBtn = document.getElementById('ai-drawer-close');
                            const backdrop = document.getElementById('ai-drawer-backdrop');
                            const set = (id, v) => { const n = document.getElementById(id); if (n) n.textContent = v || ''; };
                            const setList = (id, arr) => { const n = document.getElementById(id); if (!n) return; n.innerHTML = ''; (arr || []).forEach(x => { const li = document.createElement('li'); li.textContent = x; n.appendChild(li); }); };
                            const setListReflect = (id, arr) => { const n = document.getElementById(id); if (!n) return; n.innerHTML = ''; (arr || []).forEach(x => { const li = document.createElement('li'); li.textContent = '💬 ' + x; n.appendChild(li); }); };
                            const setChips = (id, tags) => { const n = document.getElementById(id); if (!n) return; n.innerHTML = ''; (tags || []).forEach(t => { const s = document.createElement('span'); s.className = 'chip'; s.textContent = t; n.appendChild(s); }); };
                            // Persist like/rating feedback for an item; calls onDone() after attempt
                            function postFeedback(item, opts, onDone) {
                                try {
                                    const body = new URLSearchParams({
                                        action: 'mc_ai_feedback',
                                        title: item.title || '',
                                        lens: item.lens || '',
                                        micro: item.micro_description || '',
                                        tags: JSON.stringify(item.tags || [])
                                    });
                                    if (opts && 'liked' in opts) body.append('liked', opts.liked ? 1 : 0);
                                    if (opts && 'rating' in opts && opts.rating != null) body.append('rating', String(opts.rating));
                                    if (item.why_this_fits_you) body.append('why', item.why_this_fits_you);
                                    if (item.prompt_to_start) body.append('prompt', item.prompt_to_start);
                                    if (item.signal_to_watch_for) body.append('signal', item.signal_to_watch_for);
                                    if (item.safety_notes) body.append('safety', item.safety_notes);
                                    if (Array.isArray(item.steps)) body.append('steps', JSON.stringify(item.steps));
                                    if (Array.isArray(item.reflection_questions)) body.append('reflect', JSON.stringify(item.reflection_questions));
                                    body.append('cost', aiDefaults.cost);
                                    body.append('time', aiDefaults.time);
                                    body.append('energy', aiDefaults.energy);
                                    body.append('variety', aiDefaults.variety);
                                    body.append('lenses', JSON.stringify(['Curiosity', 'Role Models', 'Opposites', 'Adjacency']));
                                    fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                                        .then(r => r.json())
                                        .then(j => { if (onDone) onDone(j && j.success); })
                                        .catch(() => { if (onDone) onDone(false); });
                                } catch (e) { if (onDone) onDone(false); }
                            }
                            function openDrawer(item) {
                                if (!drawer) return; drawer.classList.add('open');
                                // Update active index for nav controls
                                aiActiveIndex = aiAllIdeas.indexOf(item);
                                if (aiActiveIndex < 0) { aiActiveIndex = aiAllIdeas.findIndex(it => (it.title || '') === (item.title || '') && (it.lens || '') === (item.lens || '')); }
                                set('ai-drawer-title', item.title || 'Idea');
                                set('ai-drawer-lens', item.lens || '');
                                set('ai-drawer-micro', item.micro_description || '');
                                // Effort bars
                                const effort = {
                                    cost: toInt01(item.estimated_cost, aiDefaults.cost),
                                    time: toInt01(item.estimated_time, aiDefaults.time),
                                    energy: toInt01(item.estimated_energy, aiDefaults.energy),
                                    variety: toInt01(item.estimated_variety, aiDefaults.variety),
                                };
                                const bar = (id, val) => { const el = document.getElementById(id); if (el) el.style.setProperty('--val', (val * 25) + '%'); const num = document.getElementById(id + '-num'); if (num) num.textContent = String(val); };
                                bar('eff-cost', effort.cost); bar('eff-time', effort.time); bar('eff-energy', effort.energy); bar('eff-variety', effort.variety);
                                set('ai-drawer-why', item.why_this_fits_you || '');
                                set('ai-drawer-prompt', item.prompt_to_start || '');
                                const promptInput = document.getElementById('ai-prompt-input');
                                if (promptInput) {
                                    const steps = (item.steps || []).slice(0, 3).map((s, i) => `${i + 1}. ${s}`).join(' ');
                                    const parts = [
                                        (item.title ? item.title : 'Experiment'),
                                        (item.lens ? `(${item.lens})` : ''),
                                        (item.micro_description || ''),
                                        (item.prompt_to_start || ''),
                                        (steps ? `Steps: ${steps}` : '')
                                    ].filter(Boolean);
                                    promptInput.value = parts.join(' — ').trim();
                                    // Auto-size textarea to fit content up to a reasonable height
                                    try { promptInput.style.height = 'auto'; promptInput.style.height = Math.min(promptInput.scrollHeight, 240) + 'px'; } catch (e) { }
                                }
                                const promptCopy = document.getElementById('ai-prompt-copy'); if (promptCopy) { promptCopy.onclick = () => { if (promptInput) { promptInput.select(); document.execCommand('copy'); promptCopy.textContent = 'Copied'; setTimeout(() => promptCopy.textContent = 'Copy', 1000); } }; }
                                setList('ai-drawer-steps', Array.isArray(item.steps) ? item.steps : []);
                                set('ai-drawer-signal', item.signal_to_watch_for || '');
                                setListReflect('ai-drawer-reflect', Array.isArray(item.reflection_questions) ? item.reflection_questions : []);
                                const safetyWrap = document.getElementById('ai-drawer-safety-wrap');
                                if (safetyWrap) safetyWrap.style.display = item.safety_notes ? 'block' : 'none';
                                set('ai-drawer-safety', item.safety_notes || '');
                                setChips('ai-drawer-chips', item.tags || []);
                                // Hide sections if empty
                                const hideIfEmpty = (id) => { const el = document.getElementById(id); if (!el) return; const sec = el.closest('.ai-drawer-section'); if (!sec) return; const has = (Array.isArray(el.children) && el.children.length) || (el.textContent && el.textContent.trim().length); sec.style.display = has ? 'block' : 'none'; };
                                hideIfEmpty('ai-drawer-why');
                                hideIfEmpty('ai-drawer-steps');
                                hideIfEmpty('ai-drawer-signal');
                                hideIfEmpty('ai-drawer-reflect');
                                // Enable/disable nav buttons
                                const prevBtn = document.getElementById('ai-drawer-prev');
                                const nextBtn = document.getElementById('ai-drawer-next');
                                if (prevBtn) prevBtn.disabled = aiActiveIndex <= 0;
                                if (nextBtn) nextBtn.disabled = aiAllIdeas.length === 0 || aiActiveIndex >= aiAllIdeas.length - 1;
                                // Stars rating
                                let starsWrap = document.getElementById('ai-stars');
                                if (!starsWrap) {
                                    const sec = document.createElement('section'); sec.className = 'ai-drawer-section';
                                    const h4 = document.createElement('h4'); h4.textContent = 'Rate this'; sec.appendChild(h4);
                                    starsWrap = document.createElement('div'); starsWrap.className = 'ai-stars'; starsWrap.id = 'ai-stars'; sec.appendChild(starsWrap);
                                    const panel = document.querySelector('.ai-drawer-panel'); panel && panel.appendChild(sec);
                                }
                                starsWrap.innerHTML = '';
                                const current = (item._stats && item._stats.your_rating) ? item._stats.your_rating : 0;
                                for (let i = 1; i <= 5; i++) {
                                    const s = document.createElement('span');
                                    s.className = 'ai-star' + (i <= current ? ' active' : '');
                                    s.textContent = '★';
                                    s.title = i + ' stars';
                                    s.onclick = () => {
                                        const rating = i;
                                        const originalClasses = Array.from(starsWrap.children).map(star => star.classList.contains('active'));

                                        // Update UI optimistically but prepare for rollback
                                        for (let j = 0; j < starsWrap.children.length; j++) {
                                            starsWrap.children[j].classList.toggle('active', j < rating);
                                            starsWrap.children[j].style.opacity = '0.6'; // Show loading state
                                        }

                                        postFeedback(item, { rating }, (success) => {
                                            // Re-enable stars
                                            for (let j = 0; j < starsWrap.children.length; j++) {
                                                starsWrap.children[j].style.opacity = '';
                                            }

                                            if (success) {
                                                // Server confirmed - update data
                                                item._stats = item._stats || {};
                                                item._stats.your_rating = rating;
                                            } else {
                                                // Server failed - rollback UI changes
                                                for (let j = 0; j < starsWrap.children.length; j++) {
                                                    starsWrap.children[j].classList.toggle('active', originalClasses[j]);
                                                }
                                            }
                                        });
                                    };
                                    starsWrap.appendChild(s);
                                }
                            }
                            function closeDrawer() { if (drawer) drawer.classList.remove('open'); }
                            if (closeBtn) closeBtn.onclick = closeDrawer; if (backdrop) backdrop.onclick = closeDrawer; window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });
                            // Nav buttons bind
                            const _prevBtn = document.getElementById('ai-drawer-prev');
                            const _nextBtn = document.getElementById('ai-drawer-next');
                            if (_prevBtn) _prevBtn.onclick = () => { if (aiActiveIndex > 0) openDrawer(aiAllIdeas[aiActiveIndex - 1]); };
                            if (_nextBtn) _nextBtn.onclick = () => { if (aiActiveIndex < aiAllIdeas.length - 1) openDrawer(aiAllIdeas[aiActiveIndex + 1]); };

                            ideas.forEach(item => {
                                const c = document.createElement('div'); c.className = 'ai-card';
                                c.tabIndex = 0; c.style.cursor = 'pointer';
                                c.setAttribute('role', 'button');
                                c.setAttribute('aria-label', 'Open details for ' + (item.title || 'idea'));
                                c.title = 'Open details';
                                // Heart button
                                const heart = document.createElement('button'); heart.type = 'button'; heart.className = 'ai-heart'; heart.innerHTML = '❤';
                                if (item._stats && item._stats.liked_by_you) heart.classList.add('active');
                                heart.addEventListener('click', (ev) => {
                                    ev.stopPropagation();
                                    const wasActive = heart.classList.contains('active');
                                    const willBeLiked = !wasActive;

                                    // Disable heart button while request is in progress
                                    heart.disabled = true;
                                    heart.style.opacity = '0.6';

                                    postFeedback(item, { liked: willBeLiked }, (success) => {
                                        // Re-enable heart button
                                        heart.disabled = false;
                                        heart.style.opacity = '';

                                        if (success) {
                                            // Server confirmed - update UI and data
                                            heart.classList.toggle('active', willBeLiked);
                                            item._stats = item._stats || {};
                                            item._stats.liked_by_you = willBeLiked;

                                            // Handle removal from saved tab if unliking
                                            if (!willBeLiked && savedEl && c.parentElement && c.parentElement.id === 'ai-saved') {
                                                try {
                                                    c.remove();
                                                    if (!savedEl.querySelector('.ai-card')) {
                                                        savedEl.innerHTML = '<div class="ai-card">No saved experiments yet.</div>';
                                                    }
                                                } catch (e) { }
                                            }
                                        } else {
                                            // Server failed - show error feedback (optional)
                                            console.warn('Failed to save heart state for:', item.title || 'experiment');
                                        }
                                    });
                                });
                                const h = document.createElement('h5'); h.textContent = item.title || 'Idea';
                                const lens = document.createElement('div'); lens.className = 'ai-lens-badge'; lens.textContent = item.lens || '';
                                const lc = (item.lens || '').toLowerCase(); if (lc) { lens.classList.add('lens-' + lc.replace(/\s+/g, '-')); }
                                const micro = document.createElement('div'); micro.className = 'ai-micro'; micro.textContent = item.micro_description || '';
                                // Effort row: icons + numbers only
                                const row = document.createElement('div'); row.className = 'ai-effrow';
                                const mk = (cls, icon, val) => { const w = document.createElement('div'); w.className = 'ai-eff'; const i = document.createElement('span'); i.className = cls; i.textContent = icon; const n = document.createElement('span'); n.textContent = String(val); w.appendChild(i); w.appendChild(n); return w; };
                                const ec = toInt01(item.estimated_cost, aiDefaults.cost); const et = toInt01(item.estimated_time, aiDefaults.time); const ee = toInt01(item.estimated_energy, aiDefaults.energy); const ev = toInt01(item.estimated_variety, aiDefaults.variety);
                                row.appendChild(mk('eff-cost', '💰', ec)); row.appendChild(mk('eff-time', '⏳', et)); row.appendChild(mk('eff-energy', '⚡️', ee)); row.appendChild(mk('eff-variety', '🎲', ev));
                                c.appendChild(heart); c.appendChild(h); c.appendChild(lens); c.appendChild(micro); c.appendChild(row);
                                c.addEventListener('click', () => openDrawer(item));
                                c.addEventListener('keypress', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDrawer(item); } });
                                el.appendChild(c);
                            });
                        }

                        async function loadSaved() {
                            if (!savedEl) return;
                            renderSkeleton(savedEl, 6);
                            try {
                                const r = await fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'mc_ai_saved_list' }) });
                                const j = await r.json();
                                if (j && j.success) {
                                    const items = j.data.items || [];
                                    aiAllIdeas = items;
                                    renderIdeas(savedEl, items);
                                    if (!items.length) { savedEl.innerHTML = '<div class="ai-card">No saved experiments yet.</div>'; }
                                } else { savedEl.innerHTML = '<div class="ai-card">Could not load saved experiments.</div>'; }
                            } catch (e) { savedEl.innerHTML = '<div class="ai-card">Network error.</div>'; }
                        }

                        // Load saved when user opens Saved tab
                        tabLinks.forEach(link => {
                            link.addEventListener('click', function () {
                                if (this.dataset.tab === 'tab-saved') { loadSaved(); }
                            });
                        });

                        if (applyBtn) {
                            applyBtn.addEventListener('click', function (e) {
                                e.preventDefault();
                                // Show the results container
                                const resultsContainer = document.getElementById('ai-results-container');
                                if (resultsContainer) { resultsContainer.style.display = 'block'; }
                                renderSkeleton(shortlistEl, 6);
                                if (moreWrap) { moreWrap.style.display = 'none'; }
                                applyBtn.disabled = true; applyBtn.textContent = 'Generating…';
                                aiDefaults = {
                                    cost: parseInt(document.getElementById('ai-cost')?.value || 0, 10),
                                    time: parseInt(document.getElementById('ai-time')?.value || 1, 10),
                                    energy: parseInt(document.getElementById('ai-energy')?.value || 1, 10),
                                    variety: parseInt(document.getElementById('ai-variety')?.value || 2, 10),
                                };
                                const body = new URLSearchParams({
                                    action: 'mc_ai_generate_mves',
                                    cost: aiDefaults.cost,
                                    time: aiDefaults.time,
                                    energy: aiDefaults.energy,
                                    variety: aiDefaults.variety,
                                    quantity: 12,
                                    lens_curiosity: document.getElementById('lens-curiosity')?.checked ? 1 : 0,
                                    lens_rolemodels: document.getElementById('lens-rolemodels')?.checked ? 1 : 0,
                                    lens_opposites: document.getElementById('lens-opposites')?.checked ? 1 : 0,
                                    lens_adjacency: document.getElementById('lens-adjacency')?.checked ? 1 : 0,
                                });
                                // Optional admin-only model override
                                const modelSel = document.getElementById('ai-model');
                                if (modelSel && modelSel.value) body.append('model', modelSel.value);
                                fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                                    .then(r => {
                                        console.log('AI Coach AJAX response status:', r.status);
                                        return r.text(); // Get raw text first
                                    })
                                    .then(rawText => {
                                        console.log('AI Coach AJAX raw response:', rawText);
                                        try {
                                            const j = JSON.parse(rawText);
                                            console.log('AI Coach AJAX parsed response:', j);

                                            const banner = document.getElementById('ai-banner');
                                            if (j && j.success && j.data) {
                                                const shortlist = j.data.shortlist || []; const more = j.data.more || [];
                                                aiAllIdeas = [...shortlist, ...more];
                                                renderIdeas(shortlistEl, shortlist);
                                                if (Array.isArray(more) && more.length) { if (moreWrap) moreWrap.style.display = 'block'; renderIdeas(moreEl, more); } else { if (moreWrap) moreWrap.style.display = 'none'; if (moreEl) moreEl.innerHTML = ''; }
                                                if (banner) {
                                                    // Check for error message first (user-friendly)
                                                    if (j.data.error && j.data.error.trim()) {
                                                        banner.style.display = 'block';
                                                        banner.className = 'ai-banner warning';
                                                        banner.innerHTML = '⚠️ ' + j.data.error;
                                                    } else if (j.data.used_fallback) {
                                                        // Fallback to technical reason if no user-friendly error message
                                                        banner.style.display = 'block';
                                                        banner.className = 'ai-banner info';
                                                        const reason = j.data.fallback_reason || 'unknown';
                                                        banner.textContent = 'Using backup generator — ' + reason.replace(/_/g, ' ');
                                                    } else {
                                                        banner.style.display = 'none';
                                                        banner.textContent = '';
                                                        banner.className = 'ai-banner';
                                                    }
                                                }
                                                // Debug render (admins only)
                                                const dbgEl = document.getElementById('ai-debug');
                                                if (dbgEl) {
                                                    if (j.data.debug) {
                                                        const d = j.data.debug;
                                                        const pt = d?.response?.usage?.prompt_tokens ?? '';
                                                        const ct = d?.response?.usage?.completion_tokens ?? '';
                                                        const usd = d?.response?.cost_estimate_usd ?? '';
                                                        const mdl = d?.model ?? '';
                                                        function esc(s) { return String(s || '').replace(/[&<]/g, c => c === '&' ? '&amp;' : '&lt;'); }
                                                        let html = `<div><strong>Debug:</strong> ${esc(mdl)} · prompt ${esc(pt)} · completion ${esc(ct)} · $${esc(usd)}</div>`;
                                                        html += '<details style="margin-top:6px;"><summary>View request & response</summary>' +
                                                            `<div><h4 style=\"margin:8px 0 4px\">System</h4><pre>${esc(d?.request?.system)}</pre>` +
                                                            `<h4 style=\"margin:8px 0 4px\">User</h4><pre>${esc(d?.request?.user)}</pre>` +
                                                            `<h4 style=\"margin:8px 0 4px\">Response snippet</h4><pre>${esc(d?.response?.raw_snippet)}</pre></div>` +
                                                            '</details>';
                                                        dbgEl.innerHTML = html; dbgEl.style.display = 'block';
                                                    } else { dbgEl.style.display = 'none'; dbgEl.innerHTML = ''; }
                                                }
                                            }
                                            else {
                                                console.log('AI Coach AJAX response indicates failure:', j);
                                                if (shortlistEl) shortlistEl.innerHTML = '<div class="ai-card">We could not generate ideas just now.</div>';
                                                if (moreEl) moreEl.innerHTML = '';
                                                if (moreWrap) moreWrap.style.display = 'none';
                                                if (banner) { banner.style.display = 'block'; banner.textContent = 'Request failed — please try again.'; }
                                            }
                                        } catch (parseError) {
                                            console.error('AI Coach AJAX JSON parse error:', parseError);
                                            console.log('Failed to parse response:', rawText);
                                            if (shortlistEl) shortlistEl.innerHTML = '<div class="ai-card">Response parsing error. Check console for details.</div>';
                                            if (banner) { banner.style.display = 'block'; banner.textContent = 'Response parsing error — check console.'; }
                                        }
                                    })
                                    .catch(() => { shortlistEl.innerHTML = '<div class="ai-card">Network error. Please try again.</div>'; if (moreEl) moreEl.innerHTML = ''; if (moreWrap) moreWrap.style.display = 'none'; })
                                    .finally(() => { applyBtn.disabled = false; applyBtn.textContent = 'Show experiments that fit my settings'; });
                            });
                        }

                        if (resetBtn) {
                            resetBtn.addEventListener('click', function () {
                                const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                                set('ai-cost', 0); set('ai-time', 1); set('ai-energy', 1); set('ai-variety', 2);
                                ['lens-curiosity', 'lens-rolemodels', 'lens-opposites', 'lens-adjacency'].forEach(id => { const el = document.getElementById(id); if (el) el.checked = true; });
                            });
                        }
                    });
                </script>

                <?php if (!is_user_logged_in()): ?>
                        <section class="sosd-wrap">
                            <?php $mi_url = $this->find_page_by_shortcode('mi_quiz');
                            $login_url = wp_login_url(get_permalink()); ?>
                            <nav class="sosd-nav" id="sosdNav">
                                <div class="container">
                                    <div class="brand">
                                        <?php echo MC_Helpers::logo_img(['class' => 'brand__logo', 'alt' => 'Skill of Self-Discovery']); ?>
                                        <span class="brand__name">Skill of Self-Discovery</span>
                                    </div>
                                    <div class="nav-ctas">
                                        <?php if (!empty($mi_url)): ?><a class="btn btn--primary" href="<?php echo esc_url($mi_url); ?>">Sign up
                                                    free</a><?php endif; ?>
                                        <a class="btn btn--ghost" href="<?php echo esc_url($login_url); ?>">Log in</a>
                                    </div>
                                </div>
                            </nav>

                            <header class="hero">
                                <div class="container hero__grid">
                                    <div class="hero__left">
                                        <h1>Discover how you think, decide, and grow.</h1>
                                        <p class="sub">Take short assessments and get AI-generated experiments tailored to you. Start small,
                                            learn fast, and evolve with intention.</p>
                                        <div class="hero__ctas">
                                            <?php if ($mi_url): ?><a class="btn btn--primary" id="mc-start-journey"
                                                        href="<?php echo esc_url($mi_url); ?>">Start your journey (Free)</a><?php endif; ?>
                                        </div>
                                        <div class="micro-trust">
                                            <span class="lock" aria-hidden="true">🔒</span>
                                            <span>Private by default • No spam • 3–5 min to start</span>
                                        </div>
                                    </div>
                                    <div class="hero__right">
                                        <div class="id-card">
                                            <div class="id-head shimmer"></div>
                                            <div class="id-row shimmer"></div>
                                            <div class="id-row shimmer"></div>
                                            <div class="id-row short shimmer"></div>
                                        </div>
                                    </div>
                                </div>
                            </header>
                            <div class="sosd-column-card">
                                <section class="intro-blurb">
                                    <h2 class="intro-heading">How It Works</h2>
                                </section>

                                <div class="flow">
                                    <!-- Step 1: Assess -->
                                    <section class="step">
                                        <div class="step__icon" aria-hidden="true">
                                            <!-- Simple funnel SVG -->
                                            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 10h52l-22 20v16l-8 6V30L6 10z" fill="#c7d2fe" stroke="#6366f1" stroke-width="2" />
                                            </svg>
                                        </div>
                                        <div class="step__body">
                                            <div class="step-kicker">Step 1</div>
                                            <h2>Assess</h2>
                                            <p class="sub">Complete quick assessments to build your Self‑Discovery Profile.</p>
                                            <?php echo do_shortcode('[quiz_funnel show_description="true" style="dashboard"]'); ?>
                                        </div>
                                    </section>

                                    <!-- Step 2: Generate (Lab Mode) -->
                                    <section class="step">
                                        <div class="step__icon" aria-hidden="true">
                                            <svg viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg">
                                                <g fill="none" stroke="#4f46e5" stroke-width="2">
                                                    <path d="M22 10h28m-20 0v10l-14 24a12 12 0 0 0 10 18h24a12 12 0 0 0 10-18L46 20V10" />
                                                    <path d="M48 14l10-6" />
                                                    <path d="M56 22l6 10" />
                                                </g>
                                                <circle cx="36" cy="44" r="3" fill="#6366f1" />
                                                <circle cx="44" cy="50" r="2" fill="#6366f1" />
                                                <circle cx="28" cy="50" r="2" fill="#6366f1" />
                                            </svg>
                                        </div>
                                        <div class="step__body">
                                            <div class="step-kicker">Step 2</div>
                                            <h2>Generate Experiments with AI Lab</h2>
                                            <p class="sub">Create personalized experiments based on your unique strengths, curiosities, and
                                                growth areas.</p>
                                            <div class="cards cards--full">
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">🎯</div>
                                                        <div>
                                                            <div class="card__title">Tailored to You</div>
                                                            <div class="card__sub">Experiments are generated from your MI, CDT, and
                                                                motivator profile so they fit how you learn best.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">⏱️</div>
                                                        <div>
                                                            <div class="card__title">Quick & Focused</div>
                                                            <div class="card__sub">Small, time‑boxed reps (5–20 minutes) that are easy to
                                                                try and easy to repeat.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">🔁</div>
                                                        <div>
                                                            <div class="card__title">Iterative Learning</div>
                                                            <div class="card__sub">Each run captures what resonated so the next suggestion
                                                                adapts to your feedback.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">🧩</div>
                                                        <div>
                                                            <div class="card__title">Mix of Formats</div>
                                                            <div class="card__sub">Solo and social options, indoors or outdoors, with
                                                                varying energy and risk levels.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php /* No Lab Mode buttons on logged-out screen */ ?>
                                        </div>
                                    </section>

                                    <!-- Step 3: Reflect (four horizontal cards) -->
                                    <section class="step">
                                        <div class="step__icon" aria-hidden="true">
                                            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M18 20a14 14 0 1 1 10 24h-8" fill="none" stroke="#6366f1" stroke-width="2" />
                                                <path d="M18 20v-8m0 8h8" stroke="#6366f1" stroke-width="2" />
                                            </svg>
                                        </div>
                                        <div class="step__body">
                                            <div class="step-kicker">Step 3</div>
                                            <h2>Reflect, Reassess, Recalibrate, Repeat</h2>
                                            <p class="sub">Review what resonated, capture a quick note, and adjust your next rep — small,
                                                steady iterations build momentum.</p>
                                            <div class="cards cards--full">
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">📝</div>
                                                        <div>
                                                            <div class="card__title">Reflect</div>
                                                            <div class="card__sub">Capture one insight after each rep — what energized you
                                                                or felt easy to repeat?</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">🔍</div>
                                                        <div>
                                                            <div class="card__title">Reassess</div>
                                                            <div class="card__sub">Spot patterns across weeks: people vs. solo, short vs.
                                                                long, indoor vs. outdoor.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">⚙️</div>
                                                        <div>
                                                            <div class="card__title">Recalibrate</div>
                                                            <div class="card__sub">Tweak scope, time, or format to keep difficulty
                                                                just‑right and progress continuous.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card__left">
                                                        <div class="card__icon">🔁</div>
                                                        <div>
                                                            <div class="card__title">Repeat</div>
                                                            <div class="card__sub">Run another tiny rep this week and update your notes —
                                                                improvement is a loop.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <!-- Bottom call to action -->
                            <div class="hero__ctas cta-bottom">
                                <?php if (!empty($mi_url)): ?>
                                        <a class="btn btn--primary" href="<?php echo esc_url($mi_url); ?>">Start Your Journey (Free)</a>
                                <?php endif; ?>
                                <a class="btn btn--ghost" href="<?php echo esc_url($login_url); ?>">Returning User? Log in</a>
                            </div>
                        </section>
                <?php endif; ?>
                <?php
                return ob_get_clean();
    }

    /**
     * Renders the [quiz_funnel] shortcode content.
     */
    public function render_quiz_funnel($atts = [])
    {
        if (!class_exists('MC_Funnel')) {
            return current_user_can('manage_options') ? '<p><em>Funnel: MC_Funnel class not loaded.</em></p>' : '';
        }

        $atts = shortcode_atts([
            'show_description' => 'true',
            'style' => 'default',
            'context' => ''
        ], $atts, 'quiz_funnel');

        // Try multiple methods to get user ID
        $user_id = get_current_user_id();

        // Alternative methods if get_current_user_id() returns 0
        if ($user_id == 0) {
            // Try wp_get_current_user()
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID > 0) {
                $user_id = $current_user->ID;
            }

            // Try checking authentication cookies directly
            if ($user_id == 0 && isset($_COOKIE['wordpress_logged_in_' . COOKIEHASH])) {
                $user_id = wp_validate_auth_cookie($_COOKIE['wordpress_logged_in_' . COOKIEHASH], 'logged_in');
                if (!$user_id)
                    $user_id = 0;
            }

            // As last resort, try to determine user from session data
            if ($user_id == 0 && function_exists('wp_get_session_token')) {
                global $wp_current_user;
                if (isset($wp_current_user) && $wp_current_user->ID > 0) {
                    $user_id = $wp_current_user->ID;
                }
            }
        }

        $config = MC_Funnel::get_config();
        $completion = MC_Funnel::get_completion_status($user_id);
        $unlock = MC_Funnel::get_unlock_status($user_id);

        // Enqueue styles and scripts
        wp_enqueue_style('mc-funnel-css', plugins_url('assets/funnel.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('mc-funnel-js', plugins_url('assets/funnel.js', __FILE__), ['jquery'], '1.0.0', true);

        // Localize script data - include fallback for client-side user detection
        wp_localize_script('mc-funnel-js', 'mc_funnel_data', [
            'config' => $config,
            'completion' => $completion,
            'unlock' => $unlock,
            'user_id' => $user_id,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_funnel'),
            'johari_ajax_url' => admin_url('admin-ajax.php?action=get_johari_status')
        ]);

        ob_start();
        // Global, friendly step descriptions used everywhere
        $step_descriptions = [
            'mi-quiz' => 'Multiple Intelligences shows the kinds of problems you’re best at solving.',
            'cdt-quiz' => 'CDT reveals where you get stuck and how to move through it.',
            'bartle-quiz' => 'Core motivators (Player Type) highlight the fuel that keeps you engaged.',
            'johari-mi-quiz' => 'Johari Window surfaces blind spots and strengths others see in you.'
        ];
        ?>
                <div class="mc-funnel-container" data-style="<?php echo esc_attr($atts['style']); ?>">
                    <div class="mc-funnel">
                        <?php foreach ($config['steps'] as $index => $step_slug):
                            $is_completed = $completion[$step_slug] ?? false;
                            $is_unlocked = $unlock[$step_slug] ?? false;
                            $title = $config['titles'][$step_slug] ?? ucfirst(str_replace('-', ' ', $step_slug));

                            // Get detailed status for Johari quiz
                            $johari_status = null;
                            if ($step_slug === 'johari-mi-quiz') {
                                $johari_status = MC_Funnel::get_johari_status($user_id);
                                // Note: Johari is now always available like other quizzes
                                // The get_johari_status method handles its own internal states
                            }

                            $classes = ['mc-funnel-step'];
                            if ($is_completed)
                                $classes[] = 'completed';
                            if (!$is_unlocked)
                                $classes[] = 'locked';
                            if ($is_unlocked && !$is_completed)
                                $classes[] = 'available';
                            if ($step_slug === 'placeholder')
                                $classes[] = 'placeholder';

                            // Add special classes for Johari status
                            if ($johari_status) {
                                $classes[] = 'johari-' . $johari_status['status'];
                            }

                            $step_url = $is_unlocked ? MC_Funnel::get_step_url($step_slug) : null;
                            ?>
                                <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-slug="<?php echo esc_attr($step_slug); ?>"
                                    data-index="<?php echo esc_attr($index + 1); ?>" <?php
                                         // Always link to this step's own page. Completed steps
                                         // should open their results view rather than jumping
                                         // ahead to another assessment.
                                         $final_url = $step_url;
                                         ?>                         <?php if (!empty($final_url)): ?>data-url="<?php echo esc_url($final_url); ?>"
                                    <?php endif; ?>>

                                    <div class="mc-funnel-step-content">
                                        <div class="mc-funnel-step-icon">
                                            <?php if ($johari_status): ?>
                                                    <?php if ($johari_status['status'] === 'completed'): ?>
                                                            <span class="checkmark">✓</span>
                                                    <?php elseif ($johari_status['status'] === 'waiting'): ?>
                                                            <span class="waiting">⏳</span>
                                                    <?php elseif ($johari_status['status'] === 'ready'): ?>
                                                            <span class="ready">🎯</span>
                                                    <?php else: ?>
                                                            <span class="number"><?php echo $index + 1; ?></span>
                                                    <?php endif; ?>
                                            <?php elseif ($is_completed): ?>
                                                    <span class="checkmark">✓</span>
                                            <?php elseif (!$is_unlocked): ?>
                                                    <span class="lock">🔒</span>
                                            <?php else: ?>
                                                    <span class="number"><?php echo $index + 1; ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mc-funnel-step-info">
                                            <h3 class="mc-funnel-step-title"><?php echo esc_html($title); ?></h3>

                                            <?php if ($atts['show_description'] === 'true'): ?>
                                                    <div class="mc-funnel-step-description">
                                                        <?php if ($johari_status): ?>
                                                                <?php
                                                                // If still locked due to dependency, show locked copy.
                                                                if ($johari_status['status'] === 'locked') {
                                                                    $desc_text = 'Complete previous steps to unlock';
                                                                } else {
                                                                    // Show dynamic Johari text when actively in progress/ready/completed,
                                                                    // otherwise fall back to the global description for clarity.
                                                                    $j_status = $johari_status['status'];
                                                                    $use_dynamic = in_array($j_status, ['waiting', 'ready', 'completed'], true);
                                                                    $j_text = $use_dynamic ? ($johari_status['description'] ?? '') : '';
                                                                    $desc_text = $j_text ?: ($step_descriptions['johari-mi-quiz'] ?? '');
                                                                }
                                                                ?>
                                                                <p><?php echo esc_html($desc_text); ?></p>
                                                        <?php elseif ($step_slug === 'placeholder'): ?>
                                                                <p><?php echo esc_html($config['placeholder']['description']); ?></p>
                                                        <?php else:
                                                            $registered_quizzes = self::get_quizzes();
                                                            $quiz_info = $registered_quizzes[$step_slug] ?? [];
                                                            // Use global description map everywhere for consistency
                                                            $description = $step_descriptions[$step_slug] ?? (
                                                                $quiz_info['description'] ?? ''
                                                            );
                                                            ?>
                                                                <p><?php echo esc_html($description); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                            <?php endif; ?>

                                            <?php
                                            // --- Summary Info Injection Removed ---
                                            ?>

                                            <div class="mc-funnel-step-status">
                                                <?php if ($johari_status): ?>
                                                        <span class="status-badge <?php echo esc_attr($johari_status['status']); ?>">
                                                            <?php echo esc_html($johari_status['badge_text']); ?>
                                                        </span>
                                                <?php elseif ($is_completed): ?>
                                                        <span class="status-badge completed">Completed</span>
                                                <?php elseif ($is_unlocked): ?>
                                                        <span class="status-badge available">Available</span>
                                                <?php else: ?>
                                                        <span class="status-badge locked">Locked</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($index < count($config['steps']) - 1): ?>
                                            <div class="mc-funnel-connector"></div>
                                    <?php endif; ?>
                                </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                return ob_get_clean();
    }

    /**
     * Finds the permalink of the first page that contains a given shortcode.
     * Results are cached in a transient to improve performance.
     */
    private function find_page_by_shortcode($shortcode_tag)
    {
        if (empty($shortcode_tag))
            return null;
        $transient_key = 'page_url_for_' . $shortcode_tag;
        if (false !== ($cached_url = get_transient($transient_key)))
            return $cached_url;

        $query = new WP_Query(['post_type' => ['page', 'post'], 'post_status' => 'publish', 'posts_per_page' => -1, 's' => '[' . $shortcode_tag]);
        $url = null;
        if ($query->have_posts()) {
            foreach ($query->posts as $p) {
                if (has_shortcode($p->post_content, $shortcode_tag)) {
                    $url = get_permalink($p->ID);
                    break;
                }
            }
        }
        set_transient($transient_key, $url, DAY_IN_SECONDS); // Cache for 1 day.
        return $url;
    }

    /**
     * Clears page URL transients when a post is saved to keep the dashboard links fresh.
     */
    public function clear_shortcode_page_transients()
    {
        $quizzes = self::get_quizzes();
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['shortcode']))
                delete_transient('page_url_for_' . $quiz['shortcode']);
        }
    }

    /**
     * Add body class for enhanced flow mode.
     */
    public function add_enhanced_flow_body_class($classes)
    {
        // Only check on pages that might have the quiz dashboard
        if (is_admin())
            return $classes;

        $user_id = get_current_user_id();
        if (!$user_id)
            return $classes;

        // Check if we're on a page with the quiz dashboard shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'quiz_dashboard')) {
            return $classes;
        }

        // Check enhanced flow conditions
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
        $ai_unlocked = get_user_meta($user_id, 'ai_coach_unlocked', true);
        $enhanced = !empty($bartle_results['enhanced_flow']) && empty($ai_unlocked);

        if ($enhanced) {
            $classes[] = 'mc-enhanced-profile-only';

            // Check if Lab Mode is available (all assessments complete)
            $quizzes = self::get_quizzes();
            $all_complete = true;
            foreach ($quizzes as $quiz) {
                if (!empty($quiz['results_meta_key'])) {
                    $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                    if (empty($results)) {
                        $all_complete = false;
                        break;
                    }
                }
            }

            if ($all_complete) {
                $classes[] = 'has-lab-mode';
            }
        }

        return $classes;
    }

    /**
     * AJAX endpoint to mark the AI coach as unlocked for enhanced flow users.
     */
    public function ajax_mark_ai_unlocked()
    {
        check_ajax_referer('mc_dash');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in.');
        }

        $user_id = get_current_user_id();
        update_user_meta($user_id, 'ai_coach_unlocked', 1);

        wp_send_json_success('AI Coach unlocked successfully!');
    }

    /**
     * AJAX endpoint to save funnel configuration.
     */
    public function ajax_save_funnel()
    {
        check_ajax_referer('mc_funnel');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $config = [
            'steps' => $_POST['steps'] ?? [],
            'titles' => $_POST['titles'] ?? [],
            'placeholder' => [
                'title' => $_POST['placeholder_title'] ?? '',
                'description' => $_POST['placeholder_description'] ?? '',
                'target' => $_POST['placeholder_target'] ?? '',
                'enabled' => !empty($_POST['placeholder_enabled'])
            ]
        ];

        if (!class_exists('MC_Funnel')) {
            wp_send_json_error('MC_Funnel class not available.');
        }

        $result = MC_Funnel::save_config($config);

        if ($result) {
            wp_send_json_success('Funnel configuration saved successfully!');
        } else {
            wp_send_json_error('Failed to save funnel configuration.');
        }
    }

    /**
     * AJAX endpoint to get current Johari status.
     */
    public function ajax_get_johari_status()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        $user_id = get_current_user_id();

        if (!class_exists('MC_Funnel')) {
            wp_send_json_error('MC_Funnel class not available');
        }

        $johari_status = MC_Funnel::get_johari_status($user_id);

        wp_send_json_success([
            'user_id' => $user_id,
            'johari_status' => $johari_status
        ]);
    }

    /**
     * Render the funnel admin page.
     */
    public function render_funnel_admin_page()
    {
        if (!class_exists('MC_Funnel')) {
            echo '<div class="wrap"><h1>Quiz Funnel Configuration</h1><div class="notice notice-error"><p>MC_Funnel class not available.</p></div></div>';
            return;
        }

        $config = MC_Funnel::get_config();
        $registered_quizzes = self::get_quizzes();

        // Enqueue admin scripts
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('mc-funnel-admin', plugins_url('assets/funnel-admin.js', __FILE__), ['jquery', 'jquery-ui-sortable'], '1.0.0', true);
        wp_enqueue_style('mc-funnel-admin', plugins_url('assets/funnel-admin.css', __FILE__), [], '1.0.0');

        wp_localize_script('mc-funnel-admin', 'mc_funnel_admin_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_funnel'),
            'config' => $config,
            'registered_quizzes' => $registered_quizzes
        ]);

        ?>
                <div class="wrap">
                    <h1>Quiz Funnel Configuration</h1>
                    <p>Configure the order, titles, and settings for your quiz funnel. Users progress through the funnel step by
                        step, with each quiz unlocking the next.</p>

                    <div id="mc-funnel-admin-notices"></div>

                    <form id="mc-funnel-form" class="mc-funnel-admin">
                        <?php wp_nonce_field('mc_funnel'); ?>

                        <div class="mc-funnel-admin-section">
                            <h2>Quiz Steps</h2>
                            <p>Drag and drop to reorder the steps. Click on titles to edit them.</p>

                            <div id="mc-funnel-steps" class="mc-funnel-steps">
                                <?php foreach ($config['steps'] as $index => $step_slug):
                                    if ($step_slug === 'placeholder')
                                        continue; // Handle placeholder separately
                        
                                    $quiz_info = $registered_quizzes[$step_slug] ?? [];
                                    $title = $config['titles'][$step_slug] ?? $quiz_info['title'] ?? ucfirst(str_replace('-', ' ', $step_slug));
                                    $description = $quiz_info['description'] ?? '';
                                    ?>
                                        <div class="mc-funnel-step-admin" data-slug="<?php echo esc_attr($step_slug); ?>">
                                            <div class="mc-funnel-step-header">
                                                <span class="mc-funnel-step-drag">⋮⋮</span>
                                                <span class="mc-funnel-step-number"><?php echo $index + 1; ?></span>
                                                <div class="mc-funnel-step-info">
                                                    <input type="text" class="mc-funnel-step-title-input"
                                                        name="titles[<?php echo esc_attr($step_slug); ?>]"
                                                        value="<?php echo esc_attr($title); ?>" placeholder="Enter step title" />
                                                    <small class="mc-funnel-step-meta">
                                                        Quiz: <?php echo esc_html($quiz_info['title'] ?? $step_slug); ?>
                                                        <?php if ($description): ?>
                                                                | <?php echo esc_html(wp_trim_words($description, 10)); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <input type="hidden" name="steps[]" value="<?php echo esc_attr($step_slug); ?>" />
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mc-funnel-admin-section">
                            <h2>Placeholder Step (Optional)</h2>
                            <p>Configure an optional fourth step that appears after all quizzes are completed.</p>

                            <div class="mc-funnel-placeholder-config">
                                <label class="mc-funnel-checkbox-label">
                                    <input type="checkbox" name="placeholder_enabled" value="1" <?php checked($config['placeholder']['enabled']); ?> />
                                    Enable placeholder step
                                </label>

                                <div class="mc-funnel-placeholder-fields">
                                    <div class="mc-funnel-field">
                                        <label for="placeholder_title">Title:</label>
                                        <input type="text" id="placeholder_title" name="placeholder_title"
                                            value="<?php echo esc_attr($config['placeholder']['title']); ?>"
                                            placeholder="Advanced Self-Discovery Module" />
                                    </div>

                                    <div class="mc-funnel-field">
                                        <label for="placeholder_description">Description:</label>
                                        <textarea id="placeholder_description" name="placeholder_description"
                                            placeholder="Brief description of what's coming..."><?php echo esc_textarea($config['placeholder']['description']); ?></textarea>
                                    </div>

                                    <div class="mc-funnel-field">
                                        <label for="placeholder_target">Target URL (when ready):</label>
                                        <input type="url" id="placeholder_target" name="placeholder_target"
                                            value="<?php echo esc_attr($config['placeholder']['target']); ?>"
                                            placeholder="https://example.com/new-module" />
                                    </div>
                                </div>

                                <input type="hidden" name="steps[]" value="placeholder" />
                            </div>
                        </div>

                        <div class="mc-funnel-admin-section">
                            <h2>Preview</h2>
                            <p>This is how the funnel will appear to users:</p>

                            <div id="mc-funnel-preview">
                                <?php echo do_shortcode('[quiz_funnel]'); ?>
                            </div>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary" id="mc-funnel-save">Save Configuration</button>
                            <button type="button" class="button" id="mc-funnel-reset">Reset to Defaults</button>
                        </p>
                    </form>
                </div>
                <?php
    }

    /**
     * Clear dashboard cache when user meta is updated
     * 
     * @param int $meta_id Meta ID
     * @param int $user_id User ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public function clear_user_dashboard_cache($meta_id, $user_id, $meta_key, $meta_value)
    {
        // Only clear cache for quiz result meta keys
        $quiz_meta_keys = [
            'miq_quiz_results',
            'cdt_quiz_results',
            'bartle_quiz_results',
            'ai_coach_unlocked'
        ];

        if (in_array($meta_key, $quiz_meta_keys, true)) {
            MC_Cache::clear_dashboard_cache($user_id);
        }
    }

    /**
     * Ensure user session is properly initialized before shortcode rendering
     */
    public function ensure_user_session()
    {
        // Force WordPress to initialize user session early
        if (!did_action('wp_loaded')) {
            wp_get_current_user();
        }
    }

}
