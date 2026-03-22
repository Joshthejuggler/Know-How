<?php

if (!defined('ABSPATH'))
    exit;

class Bartle_Quiz_Plugin
{
    const VERSION = '1.1';
    const META_KEY = 'bartle_quiz_results';
    const SHORTCODE = 'bartle_quiz';

    // Enhanced flow feature flag - users completing Bartle after this date get new UX
    const ENHANCED_FLOW_START = 1704067200; // 2024-01-01 00:00 UTC

    public function __construct()
    {
        // 1. Register this quiz with the core platform.
        if (class_exists('Micro_Coach_Core')) {
            Micro_Coach_Core::register_quiz('bartle-quiz', [
                'title' => 'Core Motivation Assessment',
                'shortcode' => self::SHORTCODE,
                'results_meta_key' => self::META_KEY,
                'order' => 50,
                'description' => 'The Bartle Player Type Quiz is designed to uncover what truly motivates you when you engage with games, challenges, or even everyday learning.',
                'description_completed' => 'This assessment reveals your primary player type, offering insights into your core motivations.',
            ]);
        }

        // 2. Add the shortcode to display the quiz.
        add_shortcode(self::SHORTCODE, [$this, 'render_quiz']);
        add_shortcode('bartle-quiz', [$this, 'render_quiz']); // Add alias for convenience.

        // 3. Enqueue this quiz's specific assets.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // 4. Add an AJAX endpoint to save results.
        add_action('wp_ajax_bartle_save_user_results', [$this, 'ajax_save_user_results']);

        // 5. Add an AJAX endpoint to delete results for testing.
        add_action('wp_ajax_bartle_delete_user_results', [$this, 'ajax_delete_user_results']);

        // 6. Add an AJAX endpoint for PDF generation.
        add_action('wp_ajax_bartle_generate_pdf', [$this, 'ajax_generate_pdf']);
    }

    public static function activate()
    {
        // Nothing needed for this quiz.
    }

    public function enqueue_assets()
    {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, self::SHORTCODE)) {
            return;
        }

        $this->load_quiz_assets();
    }

    /**
     * Load assets for the quiz.
     * Separated to allow manual loading from render_quiz if auto-detection fails.
     */
    public function load_quiz_assets()
    {
        wp_enqueue_style('bartle-quiz-css', plugins_url('quiz.css', __FILE__), [], self::VERSION);
        // Enqueue shared About card styles
        $base_url = plugin_dir_url(MC_QUIZ_PLATFORM_PATH . 'mi-quiz-platform.php');
        wp_enqueue_style('mc-about-cards', $base_url . 'assets/about-cards.css', [], '1.0.0');
        // Enqueue MI quiz CSS for form styling
        wp_enqueue_style('mi-quiz-css', $base_url . 'quizzes/mi-quiz/css/mi-quiz.css', [], '1.0.0');
        wp_register_script('bartle-quiz-js', plugins_url('quiz.js', __FILE__), ['jquery'], self::VERSION, true);

        // Load this quiz's questions.
        require __DIR__ . '/questions.php';

        $user_data = null;
        $user_age_group = null;
        $needs_age_group = false;

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_data = [
                'id' => $user->ID,
                'firstName' => $user->first_name,
                'savedResults' => get_user_meta($user->ID, self::META_KEY, true) ?: null,
                'isEmployee' => !empty(get_user_meta($user->ID, 'mc_linked_employer_id', true)),
            ];

            // Get user's age group from profile
            if (class_exists('MC_User_Profile')) {
                if (MC_User_Profile::has_age_group($user->ID)) {
                    $user_age_group = MC_User_Profile::get_user_age_group($user->ID);
                } else {
                    $needs_age_group = true;
                }
            }
        }

        $dashboard_url = $this->_find_page_by_shortcode('quiz_dashboard');
        // Determine completion state of other assessments and Johari URL
        $mi_complete = false;
        $cdt_complete = false;
        $johari_complete = false;
        $johari_url = $this->_find_page_by_shortcode('johari_mi_quiz') ?: $this->_find_page_by_shortcode('johari-mi-quiz');
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $mi_complete = !empty(get_user_meta($uid, 'miq_quiz_results', true));
            $cdt_complete = !empty(get_user_meta($uid, 'cdt_quiz_results', true));
            $johari_complete = !empty(get_user_meta($uid, 'johari_mi_profile', true));
        }

        // Pass data to our JavaScript file.
        wp_localize_script('bartle-quiz-js', 'bartle_quiz_data', [
            'currentUser' => $user_data,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('bartle_nonce'),
            'miqNonce' => wp_create_nonce('miq_nonce'), // For registration endpoint
            'loginUrl' => wp_login_url(get_permalink()),
            'logoutUrl' => wp_logout_url(home_url()),
            'logoUrl' => MC_Helpers::logo_url(),
            'dashboardUrl' => $dashboard_url,
            'userAgeGroup' => $user_age_group,
            'needsAgeGroup' => $needs_age_group,
            'progress' => [
                'miComplete' => $mi_complete,
                'cdtComplete' => $cdt_complete,
                'johariComplete' => $johari_complete,
                'johariUrl' => $johari_url,
            ],
            'data' => [
                'cats' => $bartle_categories ?? [],
                'questions' => $bartle_questions ?? [],
                'likert' => [1 => 'Not at all like me', 2 => 'Not really like me', 3 => 'Somewhat like me', 4 => 'Mostly like me', 5 => 'Very much like me'],
            ],
        ]);
        wp_enqueue_script('bartle-quiz-js');
    }

    public function render_quiz()
    {
        // Force-load assets to ensure they are present even if the shortcode detection in enqueue_assets() failed.
        $this->load_quiz_assets();

        $dashboard_url = $this->_find_page_by_shortcode('quiz_dashboard');
        $user_id = get_current_user_id();
        $needs_age_group = false;

        if ($user_id && class_exists('MC_User_Profile')) {
            $needs_age_group = !MC_User_Profile::has_age_group($user_id);
        }

        $has_results = false;
        if ($user_id) {
            $has_results = !empty(get_user_meta($user_id, self::META_KEY, true));
        }

        ob_start();
        ?>
        <div class="quiz-wrapper">
            <?php if ($dashboard_url): ?>
                <div class="back-bar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="back-link">&larr; Return to Dashboard</a>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if (function_exists('current_user_switched') && current_user_switched()): ?>
                            <a href="<?php echo esc_url(user_switching_get_switch_back_url()); ?>"
                                class="bartle-quiz-button bartle-quiz-button-secondary" style="padding:6px 12px;font-size:0.9em;">
                                ← Switch Back to Admin
                            </a>
                        <?php endif; ?>
                        <button type="button" id="bartle-about-top"
                            class="bartle-quiz-button bartle-quiz-button-secondary">About</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Staging screen: Why it matters + funnel + Start button -->
            <div id="bartle-stage" class="bartle-quiz-card">
                <?php
                $is_employee_user = is_user_logged_in() && !empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true));
                ?>
                <?php if ($is_employee_user): ?>
                    <h2>What Motivates You at Work</h2>
                    <div class="bartle-intro-content">
                        <p>This assessment helps your team lead understand what drives and energizes you, so they can align your work with what motivates you most.</p>
                        <h3>How It Works</h3>
                        <ul class="player-types-list">
                            <li>Answer 40 quick statements (about 4–6 minutes).</li>
                            <li>Your results help your manager understand how to best engage and empower you.</li>
                            <li>All responses are confidential and used solely to support your professional growth.</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <h2>Discover What Truly Drives You</h2>
                    <div class="bartle-intro-content">
                        <p>Your Player Type reveals the motivations that keep you engaged — curiosity, progress, connection, or
                            challenge. Knowing this helps you choose learning paths and experiments you'll actually finish.</p>
                        <h3>How It Works</h3>
                        <ul class="player-types-list">
                            <li>Answer 40 quick statements (about 4–6 minutes).</li>
                            <li>See your primary and secondary Player Types with clear, plain‑language insights.</li>
                            <li>Get practical guidance for studying, building skills, and collaborating based on what drives you.</li>
                        </ul>
                    </div>
                <?php endif; ?>
                <div style="text-align:center; margin: 1em 0 0.5em;">
                    <button type="button" id="bartle-start-btn" class="bartle-quiz-button bartle-quiz-button-primary">Start
                        Assessment</button>
                </div>
            </div>

            <!-- Deprecated toolbar (kept for safety, hidden) -->
            <div id="bartle-toolbar" style="display:none; text-align:right; padding: 0 2em 0.75em;"></div>

            <!-- Bartle Quiz Introduction -->
            <div id="bartle-about-modal" class="bartle-quiz-intro quiz-about-card" style="display:none;">
                <h2>Core Motivation Assessment</h2>
                <div class="bartle-intro-content">
                    <p>The Core Motivation Assessment is designed to uncover what truly motivates you when you engage with complex environments and challenges. Originally created by game researcher Richard Bartle, the model has been widely used to understand different kinds of players. Today, this assessment is used for hiring and internal team development to ensure individuals are placed in roles that align with their natural drivers.</p>

                    <h3>The Four Player Types</h3>
                    <ul class="player-types-list">
                        <li><strong>Explorer (Discovery):</strong> Motivated by curiosity, learning, and uncovering hidden
                            possibilities.</li>
                        <li><strong>Achiever (Achievement):</strong> Motivated by goals, progress, and measurable success.</li>
                        <li><strong>Socializer (Social):</strong> Motivated by relationships, teamwork, and shared growth.</li>
                        <li><strong>Strategist (Competition):</strong> Motivated by challenge, analysis, and proving oneself.
                        </li>
                    </ul>

                    <p><strong>Ready to discover your player type?</strong> You'll answer 40 statements rated on a 1–5 scale to
                        reveal your primary motivations.</p>
                </div>
            </div>

            <?php if (current_user_can('manage_options') && empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true))): ?>
            <div id="bartle-dev-tools" style="display:none;">
                <strong>Dev tools:</strong>
                <button type="button" id="bartle-autofill-run" class="bartle-quiz-button bartle-quiz-button-small">Auto-Fill
                    (Bartle Only)</button>
                <button type="button" id="bartle-autofill-avg" class="bartle-quiz-button bartle-quiz-button-small"
                    style="background:#ffeb3b;color:#000;">All Average</button>
                <button type="button" id="bartle-autofill-neg" class="bartle-quiz-button bartle-quiz-button-small"
                    style="background:#f44336;color:#fff;">All Negative (High Strain)</button>
                <button type="button" id="bartle-autofill-exc" class="bartle-quiz-button bartle-quiz-button-small"
                    style="background:#4CAF50;color:#fff;">All Excellent (Low Strain)</button>
            </div>
            <?php endif; ?>
            <div id="bartle-quiz-container" style="display:none;">
                <div class="bartle-quiz-card">
                    <p>Loading Quiz...</p>
                </div>
            </div>

            <!-- Age gate removed -->
        </div>

        <?php if (!$is_employee_user): ?>
        <div class="quiz-wrapper quiz-funnel-card" style="margin: 2em auto;">
            <div class="bartle-quiz-card">
                <h2 class="bartle-section-title">Your Progress So Far</h2>
                <?php echo do_shortcode('[quiz_funnel show_description="false" style="dashboard"]'); ?>
            </div>
        </div>
        <?php endif; ?>
        <style>
            #bartle-about-modal {
                position: relative;
            }

            #bartle-about-modal .bartle-quiz-intro {
                text-align: left;
            }

            .quiz-funnel-intro {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 1.5rem;
                margin-bottom: 2rem;
            }

            .quiz-funnel-intro h2 {
                margin: 0 0 1rem 0;
                color: #1a202c;
                font-size: 1.25rem;
                text-align: center;
            }

            .bartle-quiz-intro {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 2rem;
                margin-bottom: 2rem;
            }

            .bartle-quiz-intro h2 {
                margin: 0 0 1rem 0;
                color: #1a202c;
                font-size: 1.75rem;
            }

            .bartle-quiz-intro h3 {
                margin: 1.5rem 0 0.75rem 0;
                color: #2d3748;
                font-size: 1.25rem;
            }

            .player-types-list {
                margin: 0.75rem 0 1.5rem 1.5rem;
            }

            .player-types-list li {
                margin-bottom: 0.5rem;
                line-height: 1.5;
            }

            .bartle-intro-content p {
                line-height: 1.6;
                margin-bottom: 1rem;
                color: #4a5568;
            }
        </style>
        <script>
            (function () {
                var startBtn = document.getElementById('bartle-start-btn');
                var stage = document.getElementById('bartle-stage');
                var container = document.getElementById('bartle-quiz-container');
                var toolbar = document.getElementById('bartle-toolbar');
                var aboutBtn = document.getElementById('bartle-about-btn');
                var aboutBtnTop = document.getElementById('bartle-about-top');
                var aboutModal = document.getElementById('bartle-about-modal');
                if (startBtn && stage && container) {
                    startBtn.addEventListener('click', function () {
                        stage.style.display = 'none';
                        container.style.display = 'block';
                        if (toolbar) toolbar.style.display = 'block';
                    });
                }
                function toggleAbout(e) {
                    if (e && e.preventDefault) e.preventDefault();
                    if (!aboutModal) return false;
                    var cont = document.getElementById('bartle-quiz-container');
                    var stg = document.getElementById('bartle-stage');
                    var tlb = document.getElementById('bartle-toolbar');
                    var show = (aboutModal.style.display === 'none' || !aboutModal.style.display);
                    if (show) {
                        if (cont) { aboutModal.dataset.prevCont = cont.style.display || ''; cont.style.display = 'none'; }
                        if (stg) { aboutModal.dataset.prevStage = stg.style.display || ''; stg.style.display = 'none'; }
                        if (tlb) { aboutModal.dataset.prevTool = tlb.style.display || ''; tlb.style.display = 'none'; }
                        aboutModal.style.display = 'block';
                        try { aboutModal.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (err) { }
                    } else {
                        aboutModal.style.display = 'none';
                        if (cont && ('prevCont' in aboutModal.dataset)) cont.style.display = aboutModal.dataset.prevCont;
                        if (stg && ('prevStage' in aboutModal.dataset)) stg.style.display = aboutModal.dataset.prevStage;
                        if (tlb && ('prevTool' in aboutModal.dataset)) tlb.style.display = aboutModal.dataset.prevTool;
                    }
                    return false;
                }
                if (aboutBtn && !aboutBtn.getAttribute('data-about-bound')) { aboutBtn.addEventListener('click', toggleAbout); aboutBtn.setAttribute('data-about-bound', '1'); }
                if (aboutBtnTop && !aboutBtnTop.getAttribute('data-about-bound')) { aboutBtnTop.addEventListener('click', toggleAbout); aboutBtnTop.setAttribute('data-about-bound', '1'); }
            })();
        </script>
        <style>
            /* Unified About card styling (shared across quizzes) */
            .quiz-about-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 2rem;
                text-align: left;
            }

            .quiz-about-card h2 {
                margin: 0 0 1rem 0;
                color: #1a202c;
                font-size: 1.75rem;
            }

            .quiz-about-card h3 {
                margin: 1.5rem 0 0.75rem 0;
                color: #2d3748;
                font-size: 1.25rem;
            }

            .quiz-about-card p {
                line-height: 1.6;
                margin-bottom: 1rem;
                color: #4a5568;
            }

            .quiz-about-card ul {
                margin: 0.75rem 0 1.5rem 1.5rem;
            }

            .quiz-about-card li {
                margin-bottom: 0.5rem;
                line-height: 1.5;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    public function ajax_save_user_results()
    {
        check_ajax_referer('bartle_nonce');
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $results = isset($_POST['results']) ? json_decode(stripslashes($_POST['results']), true) : [];

        if (!$user_id || !is_array($results) || !current_user_can('edit_user', $user_id)) {
            wp_send_json_error('Invalid data or permissions.');
        }

        // Sanitize results before saving
        $sanitized_results = [];
        $sanitized_results['completed_at'] = time(); // Add timestamp
        $sanitized_results['enhanced_flow'] = ($sanitized_results['completed_at'] >= self::ENHANCED_FLOW_START);
        if (isset($results['ageGroup'])) {
            $sanitized_results['ageGroup'] = sanitize_text_field($results['ageGroup']);
        }
        if (isset($results['sortedScores']) && is_array($results['sortedScores'])) {
            $sanitized_results['sortedScores'] = array_map(function ($item) {
                return [sanitize_text_field($item[0]), intval($item[1])];
            }, $results['sortedScores']);
        }

        update_user_meta($user_id, self::META_KEY, $sanitized_results);

        // For testing: If this is enhanced flow, clear AI coach unlock status
        // so user can experience the progressive reveal again
        if ($sanitized_results['enhanced_flow']) {
            delete_user_meta($user_id, 'ai_coach_unlocked');
        }

        // Debug logging for admins
        if (current_user_can('manage_options')) {
            error_log('Bartle Quiz Saved - Enhanced Flow: ' . ($sanitized_results['enhanced_flow'] ? 'YES' : 'NO') .
                ', Timestamp: ' . $sanitized_results['completed_at'] .
                ', Cutoff: ' . self::ENHANCED_FLOW_START);
        }

        // Trigger centralized completion check
        if (class_exists('MC_Funnel')) {
            MC_Funnel::check_completion_and_notify($user_id);
        }

        // Trigger Strain Index Calculation
        if (class_exists('MC_Strain_Index_Scorer')) {
            MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
        }

        wp_send_json_success('Results saved.');
    }

    public function ajax_delete_user_results()
    {
        check_ajax_referer('bartle_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to delete results.');
        }

        $user_id = get_current_user_id();

        if (delete_user_meta($user_id, self::META_KEY)) {
            wp_send_json_success('Your results have been deleted.');
        } else {
            wp_send_json_success('No results found to delete, or they were already deleted.');
        }
    }

    public function ajax_generate_pdf()
    {
        check_ajax_referer('bartle_nonce');

        if (!class_exists('Dompdf\Dompdf')) {
            wp_send_json_error('PDF library is not available.');
        }

        $results_html = isset($_POST['results_html']) ? wp_kses_post(wp_unslash($_POST['results_html'])) : '';
        if (empty($results_html)) {
            wp_send_json_error('No results data provided.');
        }

        $full_html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $css_path = plugin_dir_path(__FILE__) . 'quiz.css';
        if (file_exists($css_path)) {
            $full_html .= '<style>' . file_get_contents($css_path) . '</style>';
        }
        $full_html .= '</head><body style="padding: 1em;">' . $results_html . '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($full_html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $dompdf->stream(
            'bartle-quiz-results-' . date('Y-m-d') . '.pdf',
            ['Attachment' => true]
        );
        exit;
    }

    /**
     * Finds the permalink of the first page that contains a given shortcode.
     */
    private function _find_page_by_shortcode($shortcode_tag)
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
        set_transient($transient_key, $url, DAY_IN_SECONDS);
        return $url;
    }
}
