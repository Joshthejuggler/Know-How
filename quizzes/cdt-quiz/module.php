<?php

if (!defined('ABSPATH'))
    exit;

class CDT_Quiz_Plugin
{
    const VERSION = '1.0';
    const META_KEY = 'cdt_quiz_results';
    const SHORTCODE = 'cdt_quiz';

    public function __construct()
    {
        // 1. Register this quiz with the core platform.
        if (class_exists('Micro_Coach_Core')) {
            Micro_Coach_Core::register_quiz('cdt-quiz', [
                'title' => 'Growth Strengths',
                'shortcode' => self::SHORTCODE,
                'results_meta_key' => self::META_KEY,
                'order' => 30,
                'description' => 'Measure your ability to handle conflicting beliefs and discover how it impacts your decision-making and personal growth.',
                'description_completed' => 'This assessment measures your capacity to navigate conflicting values and ideas, a key skill for personal and professional growth.',
            ]);
        }

        // 2. Add the shortcode to display the quiz.
        add_shortcode(self::SHORTCODE, [$this, 'render_quiz']);
        add_shortcode('cdt-quiz', [$this, 'render_quiz']); // Add alias for convenience.

        // 3. Enqueue this quiz's specific assets.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // 4. Add an AJAX endpoint to save results.
        add_action('wp_ajax_cdt_save_user_results', [$this, 'ajax_save_user_results']);

        // 5. Add an AJAX endpoint to delete results for testing.
        add_action('wp_ajax_cdt_delete_user_results', [$this, 'ajax_delete_user_results']);

        // 6. Add an AJAX endpoint for PDF generation.
        add_action('wp_ajax_cdt_generate_pdf', [$this, 'ajax_generate_pdf']);
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
        wp_enqueue_style('cdt-quiz-css', plugins_url('quiz.css', __FILE__), [], time());
        // Enqueue shared About card styles
        $base_url = plugin_dir_url(MC_QUIZ_PLATFORM_PATH . 'mi-quiz-platform.php');
        wp_enqueue_style('mc-about-cards', $base_url . 'assets/about-cards.css', [], '1.0.0');
        // Enqueue MI quiz CSS for form styling
        wp_enqueue_style('mi-quiz-css', $base_url . 'quizzes/mi-quiz/css/mi-quiz.css', [], '1.0.0');
        wp_register_script('cdt-quiz-js', plugins_url('quiz.js', __FILE__), [], time(), true);

        // Load this quiz's questions.
        require __DIR__ . '/questions.php';
        require __DIR__ . '/details.php';

        $user_data = null;
        $user_age_group = null;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_data = [
                'id' => $user->ID,
                'firstName' => $user->first_name,
                'savedResults' => get_user_meta($user->ID, self::META_KEY, true) ?: null,
                'isAdmin' => current_user_can('manage_options'),
                'isEmployee' => !empty(get_user_meta($user->ID, 'mc_linked_employer_id', true)),
            ];
            if (class_exists('MC_User_Profile')) {
                $user_age_group = MC_User_Profile::get_user_age_group($user->ID);
            }
        }

        $prediction_data = [];
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
            $cdt_results = get_user_meta($user_id, self::META_KEY, true);

            // Only try to predict if both previous quizzes are done.
            if (!empty($mi_results) && !empty($cdt_results)) {
                $bartle_predictions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/predictions.php';
                $mi_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                $cdt_questions_file = __DIR__ . '/questions.php'; // Path to this quiz's questions

                if (file_exists($bartle_predictions_file) && file_exists($mi_questions_file) && file_exists($cdt_questions_file)) {
                    require_once $bartle_predictions_file;
                    require_once $mi_questions_file;
                    // Note: We use require_once to be safe, even though it might be loaded above.
                    require_once $cdt_questions_file;

                    $prediction_data = [
                        'miResults' => $mi_results,
                        'cdtResults' => $cdt_results,
                        'templates' => $player_type_templates ?? [],
                        'miCategories' => $mi_categories ?? [],
                        'cdtCategories' => $cdt_categories ?? [],
                    ];
                }
            }
        }

        $next_step_url = '';
        $next_step_title = '';
        if (is_user_logged_in()) {
            $bartle_results = get_user_meta(get_current_user_id(), 'bartle_quiz_results', true);
            if (!empty($bartle_results)) {
                $next_step_url = $this->_find_page_by_shortcode('quiz_dashboard');
                $next_step_title = 'View Your Self-Discovery Profile';
            } else {
                $next_step_url = $this->_find_page_by_shortcode('bartle_quiz');
                $next_step_title = 'Take the Core Motivation Assessment';
            }
        }

        // Pass data to our JavaScript file.
        wp_localize_script('cdt-quiz-js', 'cdt_quiz_data', [
            'currentUser' => $user_data,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('cdt_nonce'),
            'miqNonce' => wp_create_nonce('miq_nonce'), // For registration endpoint
            'ageGroup' => $user_age_group,
            'ageNonce' => wp_create_nonce('mc_age_group'),
            'loginUrl' => wp_login_url(get_permalink()),
            'nextStepUrl' => $next_step_url,
            'nextStepTitle' => $next_step_title,
            'logoUrl' => MC_Helpers::logo_url(),
            'predictionData' => $prediction_data,
            'data' => [
                'cats' => $cdt_categories ?? [],
                'questions' => $cdt_questions ?? [],
                'likert' => [1 => 'Not at all like me', 2 => 'Not really like me', 3 => 'Somewhat like me', 4 => 'Mostly like me', 5 => 'Very much like me'],
                'dimensionDetails' => $cdt_dimension_details ?? [],
            ],
        ]);
        wp_enqueue_script('cdt-quiz-js');
    }

    public function render_quiz()
    {
        // Force-load assets to ensure they are present even if the shortcode detection in enqueue_assets() failed.
        $this->load_quiz_assets();

        $dashboard_url = $this->_find_page_by_shortcode('quiz_dashboard');

        // Debug output for admins
        if (current_user_can('manage_options')) {
            $user_id = get_current_user_id();
            $saved_results = get_user_meta($user_id, self::META_KEY, true);
            echo '<!-- CDT Debug: ';
            echo 'cdt_results exist: ' . ($saved_results ? 'yes' : 'no') . ', ';
            if ($saved_results) {
                echo 'keys: ' . implode(', ', array_keys($saved_results)) . ', ';
                if (isset($saved_results['sortedScores'])) {
                    echo 'sortedScores count: ' . count($saved_results['sortedScores']) . ', ';
                    if (!empty($saved_results['sortedScores'])) {
                        echo 'top_slug: ' . $saved_results['sortedScores'][0][0] . ', ';
                    }
                }
                if (isset($saved_results['scores'])) {
                    echo 'scores count: ' . count($saved_results['scores']);
                }
            }
            echo ' -->';
        }

        ob_start();
        ?>
        <div class="quiz-wrapper cdt-quiz-wrapper">
            <?php if ($dashboard_url): ?>
                <div class="back-bar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="back-link">&larr; Return to Dashboard</a>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if (function_exists('current_user_switched') && current_user_switched()): ?>
                            <a href="<?php echo esc_url(user_switching_get_switch_back_url()); ?>"
                                class="cdt-quiz-button cdt-quiz-button-secondary" style="padding:6px 12px;font-size:0.9em;">
                                ← Switch Back to Admin
                            </a>
                        <?php endif; ?>
                        <button type="button" id="cdt-about-top" class="cdt-quiz-button cdt-quiz-button-secondary">About</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (current_user_can('manage_options') && empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true))): ?>
            <div id="cdt-dev-tools" style="display:none;">
                <strong>Dev tools:</strong>
                <button type="button" id="cdt-autofill-run" class="mi-quiz-button mi-quiz-button-small">Auto-Fill (CDT
                    Only)</button>
                <button type="button" id="cdt-autofill-avg" class="mi-quiz-button mi-quiz-button-small"
                    style="background:#ffeb3b;color:#000;">All Average</button>
                <button type="button" id="cdt-autofill-neg" class="mi-quiz-button mi-quiz-button-small"
                    style="background:#f44336;color:#fff;">All Negative (High Strain)</button>
                <button type="button" id="cdt-autofill-exc" class="mi-quiz-button mi-quiz-button-small"
                    style="background:#4CAF50;color:#fff;">All Excellent (Low Strain)</button>
            </div>
            <?php endif; ?>
            <div id="cdt-stage" class="cdt-quiz-card">
                <h2 class="cdt-section-title">Build Real Resilience</h2>
                <div class="stage-intro">
                    <?php
                    $is_employee_user = is_user_logged_in() && !empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true));
                    ?>
                    <?php if ($is_employee_user): ?>
                        <p>This assessment helps your team lead understand how you handle challenges and uncertainty, so they can better support your development and put you in positions to succeed.</p>
                        <h3>How It Works</h3>
                        <ul>
                            <li>Answer realistic scenario prompts and quick ratings (about 6–8 minutes).</li>
                            <li>Your results help your manager understand your resilience strengths and growth areas.</li>
                            <li>All responses are confidential and used solely to support your professional growth.</li>
                        </ul>
                    <?php else: ?>
                        <p>CDT reveals how you handle uncertainty and conflict. It complements your MI profile and completes your
                            Self‑Discovery Profile to unlock Lab Mode.</p>
                        <h3>How It Works</h3>
                        <ul>
                            <li>Answer realistic scenario prompts and quick ratings (about 6–8 minutes).</li>
                            <li>See strengths and growth areas across five resilience dimensions.</li>
                            <li>Finish to unlock Lab Mode — AI‑assisted, short experiments that build resilience in context.</li>
                        </ul>
                    <?php endif; ?>
                </div>
                <div style="text-align:center; margin-top: 1em;">
                    <button type="button" id="cdt-start-btn" class="cdt-quiz-button cdt-quiz-button-primary">Start
                        Assessment</button>
                </div>
            </div>
        </div>

        <div id="cdt-toolbar" style="display:none; text-align:right; padding: 0 2em 0.75em;"></div>

        <div id="cdt-about-modal" class="cdt-quiz-card quiz-about-card" style="display:none; text-align:left;">
            <h2 class="cdt-section-title">About the Growth Strengths Assessment</h2>
            <p style="margin-bottom: 1.5rem; line-height: 1.6; color: #4a5568;">This assessment reveals how you handle uncertainty and conflict. For organizations, this tool supports hiring decisions and internal team development by profiling resilience, coaching needs, and conflict resolution styles.</p>

            <div style="margin-top:0.5rem;">
                <h3 class="cdt-section-title" style="font-size:1.1rem;">What it measures</h3>
                <ul style="margin-left:1.25rem; line-height:1.6;">
                    <li><strong>Ambiguity Tolerance</strong> — staying functional and creative without full clarity.</li>
                    <li><strong>Value Conflict Navigation</strong> — naming tradeoffs when principles collide.</li>
                    <li><strong>Self‑Confrontation Capacity</strong> — noticing gaps between what you say and what you do.
                    </li>
                    <li><strong>Discomfort Regulation</strong> — operating under pressure without shutting down or lashing
                        out.</li>
                    <li><strong>Growth Orientation</strong> — turning conflict into learning and momentum.</li>
                </ul>
                <?php if ($is_employee_user): ?>
                <h3 class="cdt-section-title" style="font-size:1.1rem;">What happens next</h3>
                <ul style="margin-left:1.25rem; line-height:1.6;">
                    <li>Your results are shared with your team lead to support coaching and development.</li>
                    <li>All responses are confidential — only aggregated insights are used.</li>
                    <li>Combined with your other assessments to build a complete picture of your strengths.</li>
                </ul>
                <?php else: ?>
                <h3 class="cdt-section-title" style="font-size:1.1rem;">What you'll get</h3>
                <ul style="margin-left:1.25rem; line-height:1.6;">
                    <li>Plain‑language snapshot of strengths and growth areas</li>
                    <li>Character sketch phrases that feel familiar in real life</li>
                    <li>Context tips for relationships, teams, and leadership</li>
                    <li>Quick prompts to practice this week</li>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if (current_user_can('manage_options') && empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true))): ?>
        <div id="cdt-dev-tools" style="display:none;">
            <strong>Dev tools:</strong>
            <button type="button" id="cdt-autofill-run" class="cdt-quiz-button cdt-quiz-button-small">Auto-Fill</button>
        </div>
        <?php endif; ?>
        <div id="cdt-quiz-container" style="display:none;">
            <div class="cdt-quiz-card">
                <p>Loading Quiz...</p>
            </div>
        </div>
        </div>
        <?php if (!$is_employee_user): ?>
        <div class="quiz-wrapper quiz-funnel-card" style="margin: 2em auto;">
            <div class="cdt-quiz-card">
                <h2 class="cdt-section-title">Your Progress So Far</h2>
                <?php echo do_shortcode('[quiz_funnel show_description="false" style="dashboard"]'); ?>
            </div>
        </div>
        <?php endif; ?>
        <script>
            (function () {
                // Fallback About toggle, guarded to avoid double-binding with main JS
                var aboutBtnTop = document.getElementById('cdt-about-top');
                var aboutModal = document.getElementById('cdt-about-modal');
                function toggle() {
                    if (!aboutModal) return false;
                    var c = document.getElementById('cdt-quiz-container');
                    var s = document.getElementById('cdt-stage');
                    var t = document.getElementById('cdt-toolbar');
                    var show = (aboutModal.style.display === 'none' || !aboutModal.style.display);
                    if (show) {
                        if (c) { aboutModal.dataset.prevCont = c.style.display || ''; c.style.display = 'none'; }
                        if (s) { aboutModal.dataset.prevStage = s.style.display || ''; s.style.display = 'none'; }
                        if (t) { aboutModal.dataset.prevTool = t.style.display || ''; t.style.display = 'none'; }
                        aboutModal.style.display = 'block';

                        setTimeout(function () { document.addEventListener('click', closeOnClickOutside); }, 10);
                    } else {
                        aboutModal.style.display = 'none';
                        if (c && ('prevCont' in aboutModal.dataset)) c.style.display = aboutModal.dataset.prevCont;
                        if (s && ('prevStage' in aboutModal.dataset)) s.style.display = aboutModal.dataset.prevStage;
                        if (t && ('prevTool' in aboutModal.dataset)) t.style.display = aboutModal.dataset.prevTool;

                        document.removeEventListener('click', closeOnClickOutside);
                    }
                    return false;
                }

                function closeOnClickOutside(e) {
                    if (!aboutModal || aboutModal.style.display === 'none') return;
                    if (aboutModal.contains(e.target)) return;
                    if (aboutBtnTop && aboutBtnTop.contains(e.target)) return;
                    toggle();
                }
                if (aboutBtnTop && !aboutBtnTop.getAttribute('data-cdt-about-bound')) {
                    aboutBtnTop.addEventListener('click', function (e) { e.preventDefault(); toggle(); });
                    aboutBtnTop.setAttribute('data-cdt-about-bound', '1');
                }
                window._cdtAboutToggle = function (e) { if (e && e.preventDefault) e.preventDefault(); return toggle(); };
            })();
        </script>
        <style>
            /* Unified About card styling (shared across quizzes) */
            .quiz-about-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 2rem;
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
        check_ajax_referer('cdt_nonce');
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $results = isset($_POST['results']) ? json_decode(stripslashes($_POST['results']), true) : [];
        $results_html = isset($_POST['results_html']) ? wp_kses_post(wp_unslash($_POST['results_html'])) : '';

        if (!$user_id || !is_array($results) || !current_user_can('edit_user', $user_id)) {
            wp_send_json_error('Invalid data or permissions.');
        }

        // Sanitize results before saving
        $sanitized_results = [];
        $sanitized_results['completed_at'] = time(); // Add timestamp
        if (isset($results['ageGroup'])) {
            $sanitized_results['ageGroup'] = sanitize_text_field($results['ageGroup']);
        }
        if (isset($results['sortedScores']) && is_array($results['sortedScores'])) {
            $sanitized_results['sortedScores'] = array_map(function ($item) {
                return [sanitize_text_field($item[0]), intval($item[1])];
            }, $results['sortedScores']);
        }
        // Also save raw scores for robustness
        if (isset($results['scores']) && is_array($results['scores'])) {
            $sanitized_results['scores'] = array_map('intval', $results['scores']);
        }

        update_user_meta($user_id, self::META_KEY, $sanitized_results);

        // Trigger centralized completion check
        if (class_exists('MC_Funnel')) {
            MC_Funnel::check_completion_and_notify($user_id);
        }

        // Trigger Strain Index Calculation
        if (class_exists('MC_Strain_Index_Scorer')) {
            MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
        }

        // Send email with PDF attachment if HTML is provided.
        if (!empty($results_html)) {
            $user = get_userdata($user_id);
            $pdf_attachment_path = $this->_generate_pdf_for_attachment($results_html);
            $attachments = $pdf_attachment_path ? [$pdf_attachment_path] : [];

            $branding_logo = MC_Helpers::logo_url();
            $branding_html = '<div style="text-align:center; padding: 20px 0; border-bottom: 1px solid #ddd;">'
                . '<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;"><tbody><tr>'
                . '<td style="vertical-align:middle; padding-left:15px;"><span style="font-size: 1.3em; font-weight: 600; color: #1a202c; line-height: 1.2;">The Science of Teamwork</span></td>'
                . '</tr></tbody></table></div>';

            // Skip emailing results to employees — employer gets results via admin/funnel emails.
            $is_employee = !empty(get_user_meta($user_id, 'mc_linked_employer_id', true));
            if (!$is_employee) {
                $user_subject = $this->maybe_antithread('Your Growth Strengths Assessment Results');
                $user_body = '<!DOCTYPE html><html><body style="font-family: sans-serif; color: #333; background-color: #f4f4f4; padding: 20px;">
                    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">'
                    . $branding_html .
                    '<div style="padding: 20px;">' .
                    sprintf('<h1 style="margin: 0 0 1em 0; color: #1a202c; font-size: 24px;">Hi %s,</h1>', esc_html($user->first_name)) .
                    '<p>Thank you for completing the Growth Strengths Assessment. A PDF of your results is attached for your records.</p>
                    <p>You can always view your results on your dashboard.</p>
                    </div></div></body></html>';
                $user_headers = ['Content-Type: text/html; charset=UTF-8'];
                wp_mail($user->user_email, $user_subject, $user_body, $user_headers, $attachments);
            }

            // Email to admin
            $admin_list_raw = array_filter(array_map('trim', explode(',', get_option('miq_bcc_emails', ''))));
            if (!empty($admin_list_raw)) {
                $admin_subject = $this->maybe_antithread(sprintf('[Growth Strengths] Results for %s', $user->display_name));
                $admin_body = '<html><body style="font-family: sans-serif;">' . $branding_html;
                $admin_body .= '<h1>Growth Strengths Assessment results for ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</h1>' . $results_html . '</body></html>';
                $admin_headers = [
                    'Content-Type: text/html; charset=UTF-8',
                    'Bcc: ' . implode(', ', $admin_list_raw),
                ];
                wp_mail($admin_list_raw[0], $admin_subject, $admin_body, $admin_headers, $attachments);
            }

            // Cleanup
            if ($pdf_attachment_path && file_exists($pdf_attachment_path)) {
                unlink($pdf_attachment_path);
            }
        }
        wp_send_json_success('Results saved.');
    }

    public function ajax_delete_user_results()
    {
        check_ajax_referer('cdt_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to delete results.');
        }

        $user_id = get_current_user_id();

        if (delete_user_meta($user_id, self::META_KEY)) {
            wp_send_json_success('Your results have been deleted.');
        } else {
            // This can happen if meta didn't exist, which is not an error in this context.
            // We can treat it as a success.
            wp_send_json_success('No results found to delete, or they were already deleted.');
        }
    }

    /**
     * Finds the permalink of the first page that contains a given shortcode.
     * A private copy of the core platform's method to avoid complex dependencies.
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

    /**
     * Adds an invisible character to email subjects to prevent threading.
     */
    private function maybe_antithread($subject)
    {
        if (!get_option('miq_antithread', '1'))
            return $subject;
        $zw = "\xE2\x80\x8B";
        return $subject . str_repeat($zw, wp_rand(1, 3));
    }

    public function ajax_generate_pdf()
    {
        check_ajax_referer('cdt_nonce');

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
        // Use a font that supports emojis and other special characters.
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($full_html);
        // Use a custom, very long page to prevent awkward page breaks.
        $dompdf->setPaper([0, 0, 612, 2400]);
        $dompdf->render();

        $dompdf->stream(
            'cdt-quiz-results-' . date('Y-m-d') . '.pdf',
            ['Attachment' => true]
        );
        exit;
    }

    /**
     * Generates a PDF from HTML content and saves it to a temporary file.
     *
     * @param string $results_html The HTML content of the results.
     * @return string|null The full server path to the generated PDF, or null on failure.
     */
    private function _generate_pdf_for_attachment($results_html)
    {
        if (!class_exists('Dompdf\Dompdf') || empty($results_html)) {
            return null;
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
        // Use a font that supports emojis and other special characters.
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($full_html);
        // Use a custom, very long page to prevent awkward page breaks.
        $dompdf->setPaper([0, 0, 612, 2400]);
        $dompdf->render();

        $pdf_content = $dompdf->output();

        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'cdt-quiz-pdfs';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $filepath = trailingslashit($temp_dir) . 'cdt-results-' . wp_generate_password(12, false) . '.pdf';
        return file_put_contents($filepath, $pdf_content) ? $filepath : null;
    }
}
