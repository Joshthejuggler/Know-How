<?php

if (!defined('ABSPATH'))
    exit;

// Data is now loaded "just-in-time" within the methods that need it for maximum reliability.

class MI_Quiz_Plugin_AI
{
    const VERSION = '9.9.7';
    const OPT_GROUP = 'miq_settings';
    const OPT_BCC = 'miq_bcc_emails';
    const OPT_ANTITHREAD = 'miq_antithread';
    const TABLE_SUBSCRIBERS = 'miq_subscribers';
    const TRANSIENT_QUIZ_RESULTS_PREFIX = 'miq_quiz_results_';

    public function __construct()
    {
        // Register this quiz with the core platform to appear in the dashboard.
        if (class_exists('Micro_Coach_Core')) {
            Micro_Coach_Core::register_quiz('mi-quiz', [
                'title' => 'Multiple Intelligences Quiz',
                'shortcode' => 'mi_quiz',
                'results_meta_key' => 'miq_quiz_results',
                'order' => 10, // Controls the display order in the dashboard.
                'description' => 'Discover your unique blend of intelligences and unlock your full potential with a personalized action plan.',
                'description_completed' => 'This assessment reveals your unique profile of intelligences, providing insights into your natural strengths and learning styles.',
            ]);
        }

        // Frontend & admin hooks
        add_shortcode('mi_quiz', [$this, 'render_quiz']);
        add_shortcode('mi-quiz', [$this, 'render_quiz']); // Add alias for convenience.

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_settings_pages']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_settings_pages']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        // Register assets early (global availability) but do not enqueue them yet.
        // This is safe to run everywhere and ensures WP knows about the scripts.
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX hooks
        add_action('wp_ajax_miq_email_results', [$this, 'ajax_email_results']);
        add_action('wp_ajax_nopriv_miq_email_results', [$this, 'ajax_email_results']);
        add_action('wp_ajax_nopriv_miq_magic_register', [$this, 'ajax_magic_register']);
        add_action('wp_ajax_miq_delete_subs', [$this, 'ajax_delete_subs']);
        add_action('wp_ajax_miq_export_subs', [$this, 'ajax_export_subs']);
        add_action('wp_ajax_miq_save_user_results', [$this, 'ajax_save_user_results']);
        add_action('wp_ajax_miq_delete_user_results', [$this, 'ajax_delete_user_results']);
        add_action('wp_ajax_miq_generate_pdf', [$this, 'ajax_generate_pdf']);

        // Hook into WordPress user deletion to clean up our custom table.
        add_action('delete_user', [$this, 'handle_user_deletion']);

        // Fix for User Switching plugin compatibility
        // Force shortcode processing and clear caches when user switching is active
        add_action('init', [$this, 'fix_user_switching_compatibility'], 99);
        add_filter('the_content', [$this, 'force_shortcode_on_quiz_page'], 99);
    }

    /** Create/upgrade the subscribers table */
    public function ensure_tables()
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name  VARCHAR(100) DEFAULT '',
            email      VARCHAR(190) NOT NULL,
            ip         VARCHAR(64)  DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Activation hook */
    public static function activate()
    {
        (new self())->ensure_tables();
    }

    /** Admin: settings + subscribers pages */
    public function add_settings_pages()
    {
        // The subscribers page is now a submenu of the main Quiz Platform.
        add_submenu_page(
            'quiz-platform-settings',      // Parent slug
            'MI Quiz Subscribers',         // Page title
            'MI Quiz Subs',                // Menu title
            'manage_options',              // Capability
            'mi-quiz-subs',                // Menu slug
            [$this, 'render_subs_page']   // Function
        );
    }

    public function register_settings()
    {
        // Register settings with the core platform's group.
        register_setting('mc_quiz_platform_settings', self::OPT_BCC);
        register_setting('mc_quiz_platform_settings', self::OPT_ANTITHREAD);

        // Add a new section to the main Quiz Platform settings page.
        add_settings_section(
            'miq_main',                      // Section ID
            'MI Quiz Settings',              // Section Title
            function () {
                echo '<p>Settings specific to the Multiple Intelligences Quiz, such as email notifications.</p>';
            },
            'quiz-platform-settings'         // Page slug
        );

        add_settings_field(
            self::OPT_BCC,
            'BCC Results Email',
            function () {
                $v = esc_attr(get_option(self::OPT_BCC, ''));
                echo '<input type="text" style="width:480px" name="' . esc_attr(self::OPT_BCC) . '" value="' . $v . '" placeholder="admin@example.com, another@example.com">';
                echo '<p class="description">Admins to notify with a copy of results. Comma-separated.</p>';
            },
            'quiz-platform-settings',
            'miq_main'
        );

        // Remove the UI for the anti-threading option per request, but
        // leave functionality intact. The underlying option continues
        // to be read by maybe_antithread() with a default of enabled.
    }

    public function render_subs_page()
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY id DESC LIMIT 5000", ARRAY_A);
        $export_url = wp_nonce_url(admin_url('admin-ajax.php?action=miq_export_subs'), 'miq_nonce');
        echo '<div class="wrap"><h1>MI Quiz Subscribers</h1>';
        echo '<p><a class="button button-secondary" href="' . esc_url($export_url) . '">Download CSV</a></p>';
        if (empty($rows)) {
            echo '<p>No subscribers yet.</p></div>';
            return;
        }
        echo '<div class="tablenav top"><div class="alignleft actions bulkactions">
                <button type="button" class="button" id="miq-select-all-btn">Select All</button>
                <button type="button" class="button" id="miq-deselect-all-btn">Deselect All</button>
                <button type="button" class="button button-primary" id="miq-del-selected">Delete Selected</button>
              </div></div>';
        echo '<table class="widefat striped"><thead><tr>
                <th class="miq-subs-checkbox-col"><input type="checkbox" id="miq-check-all"></th>
                <th>ID</th><th>Date</th><th>First</th><th>Last</th><th>Email</th><th>IP</th>
              </tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>' .
                '<th scope="row" class="check-column"><input type="checkbox" class="miq-row" value="' . intval($r['id']) . '"></th>' .
                '<td>' . intval($r['id']) . '</td>' .
                '<td>' . esc_html($r['created_at']) . '</td>' .
                '<td>' . esc_html($r['first_name']) . '</td>' .
                '<td>' . esc_html($r['last_name']) . '</td>' .
                '<td>' . esc_html($r['email']) . '</td>' .
                '<td>' . esc_html($r['ip']) . '</td>' .
                '</tr>';
        }
        echo '</tbody></table></div>'; ?>
        <script>
            (function () {
                const $ = s => document.querySelector(s);
                const $$ = s => Array.from(document.querySelectorAll(s));
                const nonce = '<?php echo esc_js(wp_create_nonce('miq_nonce')); ?>';
                const selectAllBtn = $('#miq-select-all-btn');
                const deselectAllBtn = $('#miq-deselect-all-btn');

                const setChecks = (checked) => {
                    $$('.miq-row').forEach(cb => cb.checked = checked);
                    $('#miq-check-all').checked = checked;
                };

                const all = $('#miq-check-all');
                all && all.addEventListener('change', () => setChecks(all.checked));
                selectAllBtn && selectAllBtn.addEventListener('click', () => setChecks(true));
                deselectAllBtn && deselectAllBtn.addEventListener('click', () => setChecks(false));
                const del = $('#miq-del-selected');
                del && del.addEventListener('click', () => {
                    const ids = $$('.miq-row').filter(c => c.checked).map(c => c.value);
                    if (!ids.length) return alert('Select rows first');
                    if (!confirm('Delete selected subscribers?')) return;
                    fetch(ajaxurl, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'miq_delete_subs', _ajax_nonce: nonce, ids })
                    }).then(r => r.json()).then(j => {
                        if (j.success) location.reload(); else alert('Delete failed');
                    });
                });
            })();
        </script>
        <?php
    }

    public function ajax_delete_subs()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No permission');
        }
        check_ajax_referer('miq_nonce');

        // The 'ids' parameter from JS will be a comma-separated string.
        $ids_raw = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : '';
        if (empty($ids_raw)) {
            wp_send_json_error('No IDs provided.');
        }

        // Explode the string into an array, then sanitize each ID to ensure they are integers.
        $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
        if (empty($ids)) {
            wp_send_json_error('No valid IDs provided.');
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $res = $wpdb->query($wpdb->prepare("DELETE FROM `$table` WHERE id IN ($placeholders)", $ids));

        wp_send_json_success(['deleted' => (int) $res]);
    }

    public function ajax_export_subs()
    {
        if (!current_user_can('manage_options'))
            wp_die('No permission');
        check_admin_referer('miq_nonce');

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $rows = $wpdb->get_results("SELECT id,created_at,first_name,last_name,email,ip FROM `$table` ORDER BY id DESC", ARRAY_A);

        $filename = 'miq-subscribers-' . date('Ymd-His') . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['id', 'created_at', 'first_name', 'last_name', 'email', 'ip']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['created_at'], $r['first_name'], $r['last_name'], $r['email'], $r['ip']]);
        }
        fclose($out);
        exit;
    }

    /**
     * When a WordPress user is deleted, remove them from our subscriber list.
     * This is important for data privacy and hygiene.
     *
     * @param int $user_id The ID of the user being deleted.
     */
    public function handle_user_deletion($user_id)
    {
        // Get user object before it's deleted to retrieve the email.
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;

        global $wpdb;
        $wpdb->delete($wpdb->prefix . self::TABLE_SUBSCRIBERS, ['email' => $email], ['%s']);
    }

    public function ajax_magic_register()
    {
        check_ajax_referer('miq_nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $results_html = isset($_POST['results_html']) ? wp_kses_post(wp_unslash($_POST['results_html'])) : '';
        $results_data = isset($_POST['results_data']) ? json_decode(stripslashes($_POST['results_data']), true) : [];

        if (!is_email($email)) {
            wp_send_json_error('Please provide a valid email address.');
        }
        if (empty($first_name)) {
            wp_send_json_error('Please provide your first name.');
        }

        if (email_exists($email)) {
            $login_url = wp_login_url(get_permalink());
            wp_send_json_error(sprintf(
                'This email is already registered. <a href="%s">Please log in</a> to see your results.',
                esc_url($login_url)
            ));
        }

        $username_base = sanitize_user(explode('@', $email)[0], true);
        $username = $username_base;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . $i;
            $i++;
        }

        $password = wp_generate_password(24, true, true);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error('Could not create an account. Please try again.');
        }

        wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'display_name' => $first_name]);
        wp_set_current_user($user_id, $username);
        wp_set_auth_cookie($user_id, true);

        // Save the quiz results to the new user's meta.
        if (!empty($results_data) && is_array($results_data)) {
            $results_data['completed_at'] = time(); // Add timestamp
            update_user_meta($user_id, 'miq_quiz_results', $results_data);
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `$table` (created_at, first_name, email, ip)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE 
                created_at = VALUES(created_at),
                first_name = VALUES(first_name),
                ip         = VALUES(ip)", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            current_time('mysql'),
            $first_name,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ));

        // Send a single, comprehensive welcome email with results and a password reset link.
        if (!empty($results_html)) { // Only proceed if there are results to show/send.
            $pdf_attachment_path = $this->_generate_pdf_for_attachment($results_html);
            $attachments = [];
            if ($pdf_attachment_path) {
                $attachments[] = $pdf_attachment_path;
            }

            // Get user data to generate the password reset link.
            $user = get_userdata($user_id);
            $key = get_password_reset_key($user);
            $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

            $branding_logo = MC_Helpers::logo_url();
            $branding_html = '<div style="text-align:center; padding: 20px 0; border-bottom: 1px solid #ddd;">'
                . '<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;"><tbody><tr>'
                . '<td style="vertical-align:middle; padding-left:15px;"><span style="font-size: 1.3em; font-weight: 600; color: #1a202c; line-height: 1.2;">The Science of Teamwork</span></td>'
                . '</tr></tbody></table></div>';

            // Build a more helpful email body for the user.
            $user_email_body = '<!DOCTYPE html><html><body style="font-family: sans-serif; color: #333; background-color: #f4f4f4; padding: 20px;">';
            $user_email_body .= '<div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">';
            $user_email_body .= $branding_html;
            $user_email_body .= '<div style="background-color: #f7f7f7; padding: 20px; border-bottom: 1px solid #ddd;">';
            $user_email_body .= sprintf('<h1 style="margin: 0; color: #1a202c; font-size: 24px;">Welcome, %s!</h1>', esc_html($first_name));
            $user_email_body .= '</div>';
            $user_email_body .= '<div style="padding: 20px;">';
            $user_email_body .= '<p style="margin: 0 0 1em 0;">Thank you for taking the Multiple Intelligences Quiz! Your account has been created, and you are now logged in on this device.</p>';
            $user_email_body .= '<h2 style="margin-top: 1.5em; color: #1a202c; font-size: 20px;">Secure Your Account</h2>';
            $user_email_body .= '<p>To access your account from other devices or to log in again later, you must set a password. Please click the secure link below to do so now:</p>';
            $user_email_body .= sprintf('<p style="text-align:center; margin: 2em 0;"><a href="%s" style="background-color:#1e40af;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;">Set Your Password</a></p>', esc_url($reset_url));
            $user_email_body .= '<p style="font-size: 0.9em; color: #666;">This link is valid for 24 hours. If it expires, you can use the "Lost your password?" link on the login page.</p>';
            $user_email_body .= '</div>';
            $user_email_body .= '<div style="padding: 20px; border-top: 1px solid #eee; background-color: #f7f7f7;">';
            $user_email_body .= '<h2 style="margin-top: 0; color: #1a202c; font-size: 20px;">Your Quiz Results</h2>';
            if ($pdf_attachment_path) {
                $user_email_body .= '<p style="margin:0;">A PDF copy of your full results is attached to this email for your records. You can also view them on the platform at any time.</p>';
            } else {
                $user_email_body .= '<p style="margin:0;">You can view your full results on the platform at any time by logging into your new account.</p>';
            }
            $user_email_body .= '</div>';
            $user_email_body .= '</div></body></html>';

            $subject_user = $this->maybe_antithread('Welcome! Your MI Quiz Results & Account Info');
            $headers_user = [
                'Content-Type: text/html; charset=UTF-8',
                sprintf('Reply-To: "%s" <%s>', $first_name, $email),
            ];
            wp_mail($email, $subject_user, $user_email_body, $headers_user, $attachments);

            // Clean up the temporary PDF file
            if ($pdf_attachment_path && file_exists($pdf_attachment_path)) {
                unlink($pdf_attachment_path);
            }

            // Send a separate, simpler email to the admin/BCC list.
            $admin_list_raw = array_filter(array_map('trim', explode(',', get_option(self::OPT_BCC, ''))));
            if (!empty($admin_list_raw)) {
                $admin_body = '<html><body style="font-family: sans-serif;">' . $branding_html;
                $admin_body .= '<h1>Quiz results for ' . esc_html($first_name) . ' (' . esc_html($email) . ')</h1>' . $results_html . '</body></html>';
                $subject_admin = $this->maybe_antithread(sprintf('[MI Quiz] Results for %s <%s>', $first_name, $email));
                $headers_admin = [
                    'Content-Type: text/html; charset=UTF-8',
                    sprintf('Reply-To: "%s" <%s>', $first_name, $email),
                    'Bcc: ' . implode(', ', $admin_list_raw),
                ];
                wp_mail($admin_list_raw[0], $subject_admin, $admin_body, $headers_admin, $attachments);
            }
        }

        wp_send_json_success('Account created! Your results have been emailed. Loading them now...');
    }




    /**
     * Register assets globally.
     */
    public function register_assets()
    {
        wp_register_style('mi-quiz-css', plugins_url('css/mi-quiz.css', __FILE__), [], self::VERSION);
        wp_register_script('mi-quiz-js', plugins_url('mi-quiz.js', __FILE__), [], self::VERSION, true);
    }

    /**
     * Enqueue assets for the quiz (JIT).
     * Now only handles enqueueing and localization.
     */
    public function load_quiz_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MI Quiz: load_quiz_assets called via JIT.");
        }

        wp_enqueue_style('mi-quiz-css');

        // --- Load data just-in-time ---
        $questions_file = __DIR__ . '/mi-questions.php';
        if (file_exists($questions_file)) {
            require $questions_file; // defines $mi_categories, $mi_questions, etc. in *this* function's scope
        }

        // Load the new CDT prompts.
        $prompts_file = __DIR__ . '/mi-cdt-prompts.php';
        $mi_cdt_prompts = [];
        if (file_exists($prompts_file)) {
            require $prompts_file;

        }

        // Prefer local vars (from the require), fall back to $GLOBALS, else empty array.
        $cats = $mi_categories ?? $GLOBALS['mi_categories'] ?? [];
        $q1 = $mi_questions ?? $GLOBALS['mi_questions'] ?? [];
        $q2 = $mi_part_two_questions ?? $GLOBALS['mi_part_two_questions'] ?? [];
        $career = $mi_career_suggestions ?? $GLOBALS['mi_career_suggestions'] ?? [];
        $lev = $mi_leverage_tips ?? $GLOBALS['mi_leverage_tips'] ?? [];
        $grow = $mi_growth_tips ?? $GLOBALS['mi_growth_tips'] ?? [];
        $skills = $mi_potential_skills ?? $GLOBALS['mi_potential_skills'] ?? [];
        $pairs = $mi_pair_library ?? $GLOBALS['mi_pair_library'] ?? [];

        $cdt_quiz_url = $this->_find_page_by_shortcode('cdt_quiz');

        $user_data = null;
        $user_age_group = null;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $saved_results = get_user_meta($user->ID, 'miq_quiz_results', true);
            $user_data = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'savedResults' => is_array($saved_results) && !empty($saved_results) ? $saved_results : null,
                'isEmployee' => !empty(get_user_meta($user->ID, 'mc_linked_employer_id', true)),
            ];
            if (class_exists('MC_User_Profile')) {
                $user_age_group = MC_User_Profile::get_user_age_group($user->ID);
            }
        }

        // Determine the next step URL/title based on funnel completion
        $next_step_url = '';
        $next_step_title = '';
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            if (class_exists('MC_Funnel')) {
                $config = MC_Funnel::get_config();
                $completion = MC_Funnel::get_completion_status($uid);
                // Find first incomplete step in configured order
                foreach (($config['steps'] ?? []) as $slug) {
                    if (empty($completion[$slug])) {
                        $maybe = MC_Funnel::get_step_url($slug);
                        if ($maybe) {
                            $next_step_url = $maybe;
                            $next_step_title = 'Next Step: ' . ($config['titles'][$slug] ?? ucfirst(str_replace('-', ' ', $slug)));
                            break;
                        }
                    }
                }
            }
            // If none found, send to dashboard
            if (empty($next_step_url)) {
                $next_step_url = $this->_find_page_by_shortcode('quiz_dashboard');
                if ($next_step_url) {
                    $next_step_title = 'View Your Self-Discovery Profile';
                }
            }
        }

        // Enqueue shared About card styles
        $base_url = plugin_dir_url(MC_QUIZ_PLATFORM_PATH . 'mi-quiz-platform.php');
        wp_enqueue_style('mc-about-cards', $base_url . 'assets/about-cards.css', [], '1.0.0');

        $localized_data = [
            'currentUser' => $user_data,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('miq_nonce'),
            'loginUrl' => wp_login_url(get_permalink()),
            'cdtQuizUrl' => $cdt_quiz_url,
            'nextStepUrl' => $next_step_url,
            'nextStepTitle' => $next_step_title,
            'ageGroup' => $user_age_group,
            'ageNonce' => wp_create_nonce('mc_age_group'),
            'data' => [
                'cats' => $cats,
                'q1' => $q1,
                'q2' => $q2,
                'career' => $career,
                'lev' => $lev,
                'grow' => $grow,
                'skills' => $skills,
                'pairs' => $pairs,
                'likert' => [1 => 'Not at all like me', 2 => 'Not really like me', 3 => 'Somewhat like me', 4 => 'Mostly like me', 5 => 'Very much like me'],
                'cdtPrompts' => $mi_cdt_prompts,
            ],
        ];

        // Manual Injection: Return data directly
        return $localized_data;
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

    private function maybe_antithread($subject)
    {
        if (!get_option(self::OPT_ANTITHREAD, '1'))
            return $subject;
        $zw = "\xE2\x80\x8B";
        return $subject . str_repeat($zw, wp_rand(1, 3));
    }

    public function ajax_email_results()
    {
        check_ajax_referer('miq_nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $last_name = $last_name ?: '';
        $results_html = isset($_POST['results_html']) ? wp_kses_post(wp_unslash($_POST['results_html'])) : '';

        if (!is_email($email))
            wp_send_json_error('Invalid email address.');
        if (empty($first_name))
            wp_send_json_error('Please enter your first name.');
        if (empty($results_html))
            wp_send_json_error('No results data to send.');

        $body = '<html><body><h1>Here are your quiz results:</h1>' . $results_html . '<p>Thank you for taking the quiz!</p></body></html>';

        // Skip emailing results to employees — employer gets results via admin/funnel emails.
        $is_employee = false;
        if (is_user_logged_in()) {
            $is_employee = !empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true));
        }

        $sent_user = false;
        if (!$is_employee) {
            $subject_user = $this->maybe_antithread(sprintf('Your MI Quiz Results — %s %s', $first_name, $last_name));
            $headers_user = [
                'Content-Type: text/html; charset=UTF-8',
                sprintf('Reply-To: "%s %s" <%s>', $first_name, $last_name, $email),
            ];
            $sent_user = wp_mail($email, $subject_user, $body, $headers_user);
        }

        $admin_list_raw = array_filter(array_map('trim', explode(',', get_option(self::OPT_BCC, ''))));
        $admin_list_raw = array_unique($admin_list_raw);

        $admin_to = '';
        foreach ($admin_list_raw as $addr) {
            if (strcasecmp($addr, $email) !== 0) {
                $admin_to = $addr;
                break;
            }
        }
        if (empty($admin_to)) {
            $fallback = get_option('admin_email');
            if (is_email($fallback) && strcasecmp($fallback, $email) !== 0) {
                $admin_to = $fallback;
            }
        }

        $bcc = array_values(array_filter($admin_list_raw, function ($addr) use ($admin_to, $email) {
            return strcasecmp($addr, $admin_to) !== 0 && strcasecmp($addr, $email) !== 0;
        }));

        if (!empty($admin_to)) {
            $subject_admin = $this->maybe_antithread(sprintf('[MI Quiz] Results for %s %s <%s>', $first_name, $last_name, $email));
            $headers_admin = [
                'Content-Type: text/html; charset=UTF-8',
                sprintf('Reply-To: "%s %s" <%s>', $first_name, $last_name, $email),
            ];
            if (!empty($bcc)) {
                $headers_admin[] = 'Bcc: ' . implode(', ', $bcc);
            }
            wp_mail($admin_to, $subject_admin, $body, $headers_admin);
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `$table` (created_at, first_name, last_name, email, ip)
             VALUES (%s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE 
                created_at = VALUES(created_at),
                first_name = VALUES(first_name),
                last_name  = VALUES(last_name),
                ip         = VALUES(ip)", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            current_time('mysql'),
            $first_name,
            $last_name,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ));

        if ($is_employee) {
            wp_send_json_success('Results saved.');
        } elseif ($sent_user) {
            wp_send_json_success('Your results have been sent! Check your inbox.');
        }
        wp_send_json_error('The email could not be sent. Please try again later.');
    }

    /** Shortcode output (HTML only) */
    public function render_quiz()
    {
        // Assets are now enqueued conditionally in load_quiz_assets(),
        // but we also FORCE PRINT them here to ensure they are output immediately
        // bypassing any footer-hook issues caused by User Switching.
        // Capture data for manual injection
        $quiz_data = $this->load_quiz_assets();

        $dashboard_url = $this->_find_page_by_shortcode('quiz_dashboard');

        $questions_file_path = dirname(__FILE__) . '/mi-questions.php';
        if (!file_exists($questions_file_path)) {
            $error_message = '<strong>Quiz Error:</strong> The required questions file was not found at the expected location: <code>' . esc_html($questions_file_path) . '</code>. Please ensure the file exists.';
            return '<div style="border:2px solid red; padding:1em; background:#fbeaea; color: #000;">' . $error_message . '</div>';
        }

        ob_start();
        ?>
        <!-- DEBUG: START RENDER -->
        <div class="quiz-wrapper">
            <?php if ($dashboard_url): ?>
                <div class="back-bar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="back-link">&larr; Return to Dashboard</a>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <!-- DEBUG: INSIDE BACK BAR -->
                        <?php if (function_exists('current_user_switched') && current_user_switched()): ?>
                            <a href="<?php echo esc_url(user_switching_get_switch_back_url()); ?>"
                                class="mi-quiz-button mi-quiz-button-secondary" style="padding:6px 12px;font-size:0.9em;">
                                ← Switch Back to Admin
                            </a>
                        <?php endif; ?>
                        <button type="button" id="mi-about-top" class="mi-quiz-button mi-quiz-button-secondary"
                            onclick="return window._miAboutToggle ? window._miAboutToggle(event) : (function(e){ e&&e.preventDefault&&e.preventDefault(); var m=document.getElementById('mi-about-modal'), r=document.getElementById('mi-quiz-results'), c=document.getElementById('mi-quiz-container'); if(!m) return false; var show=(m.style.display==='none'||!m.style.display); if(show){ if(r){ m.dataset.prevRes=(r.style.display||''); r.style.display='none'; } if(c){ m.dataset.prevCont=(c.style.display||''); c.style.display='none'; } m.style.display='block'; } else { m.style.display='none'; if(r && ('prevRes' in m.dataset)) r.style.display=m.dataset.prevRes; if(c && ('prevCont' in m.dataset)) c.style.display=m.dataset.prevCont; } return false; })(event)">About</button>
                    </div>
                </div>
            <?php endif; ?>
            <!-- DEBUG: AFTER BACK BAR -->
            <!-- Staging screen -->
            <div id="mi-stage" class="mi-quiz-card">
                <h2 class="mi-section-title">Map Your Natural Strengths</h2>
                <div class="stage-intro">
                    <?php
                    $is_employee_user = is_user_logged_in() && !empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true));
                    ?>
                    <?php if ($is_employee_user): ?>
                        <p>This assessment helps your team lead understand how you learn and problem‑solve best, so they can coach, guide, and empower you more effectively.</p>
                        <h3>How It Works</h3>
                        <ul>
                            <li>Answer quick statements about how you prefer to learn and problem‑solve.</li>
                            <li>Your results help your manager tailor coaching and projects to your natural strengths.</li>
                            <li>All responses are confidential and used solely to support your professional growth.</li>
                        </ul>
                    <?php else: ?>
                        <p>Discover how you learn best across 8 intelligences. Your MI profile powers better decisions and feeds Lab Mode (AI‑assisted experiments).</p>
                        <h3>How It Works</h3>
                        <ul>
                            <li>Answer quick statements about how you prefer to learn and problem‑solve.</li>
                            <li>We identify your top intelligences and connect them to strengths you can use right away.</li>
                            <li>Finish to unlock Lab Mode — AI‑assisted, short experiments tailored to your profile.</li>
                        </ul>
                    <?php endif; ?>
                </div>
                <div style="text-align:center; margin-top: 1em;">
                    <button type="button" id="mi-stage-start" class="mi-quiz-button mi-quiz-button-primary">Start MI
                        Quiz</button>
                </div>
            </div>

            <div id="mi-quiz-container" style="display:none;">
                <div id="mi-age-gate">
                    <div class="mi-quiz-card">
                        <h2 class="mi-section-title">Welcome!</h2>
                        <p>To tailor the questions for you, please select the option that best describes you:</p>
                        <div class="mi-age-options">
                            <button type="button" class="mi-quiz-button" data-age-group="teen">Teen / High School</button>
                            <button type="button" class="mi-quiz-button" data-age-group="graduate">Student / Recent
                                Graduate</button>
                            <button type="button" class="mi-quiz-button" data-age-group="adult">Adult / Professional</button>
                        </div>
                        <div class="mi-quiz-notice">
                            <?php if (is_user_logged_in()): ?>
                                <p>Welcome back! At the end of the quiz, your results will be automatically emailed to your account
                                    address and saved to your profile.</p>
                            <?php else: ?>
                                <p>This quiz is the first step toward unlocking your potential. At the end, you'll receive a
                                    comprehensive report detailing your top intelligences and a personalized action plan to help you
                                    grow. To view your results and save your progress, simply enter your name and email to create
                                    your free account.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (current_user_can('manage_options') && empty(get_user_meta(get_current_user_id(), 'mc_linked_employer_id', true))): ?>
                <div id="mi-dev-tools" style="display:none;">
                    <strong>Dev tools:</strong>
                    <button type="button" id="mi-autofill-run" class="mi-quiz-button mi-quiz-button-small">Auto-Fill (MI
                        Only)</button>
                    <button type="button" id="mi-autofill-avg" class="mi-quiz-button mi-quiz-button-small"
                        style="background:#ffeb3b;color:#000;">All Average</button>
                    <button type="button" id="mi-autofill-neg" class="mi-quiz-button mi-quiz-button-small"
                        style="background:#f44336;color:#fff;">All Negative (High Strain)</button>
                    <button type="button" id="mi-autofill-exc" class="mi-quiz-button mi-quiz-button-small"
                        style="background:#4CAF50;color:#fff;">All Excellent (Low Strain)</button>
                </div>
                <?php endif; ?>

                <form id="mi-quiz-form-part1" style="display:none;"></form>

                <div id="mi-quiz-intermission" style="display:none;">
                    <h2 class="mi-section-title">Your Top Intelligences</h2>
                    <p>Based on your answers, these are your top three intelligences:</p>
                    <ul id="mi-top3-list"></ul>
                    <p>Now, let's explore these three areas in more detail.</p>
                    <button type="button" id="mi-start-part2" class="mi-quiz-button">Start Part 2</button>
                </div>

                <form id="mi-quiz-form-part2" style="display:none;"></form>
                <div id="mi-quiz-results" style="display:none;"></div>
                <!-- Lab Mode Updated: <?php echo time(); ?> -->

                <!-- Lab Mode Section -->
                <div id="mi-lab-mode" style="display:none;">
                    <div class="mi-quiz-card">
                        <h2 class="mi-section-title">🧪 Lab Mode: Design Your Learning Experiments</h2>
                        <p>Based on your intelligence profile, design personalized experiments to explore and develop your
                            strengths.</p>

                        <!-- Lab Mode Form -->
                        <div id="mi-lab-form-container">
                            <form id="mi-lab-form">
                                <div class="mi-form-section">
                                    <h3>Setup Your Learning Environment</h3>

                                    <div class="mi-form-field">
                                        <label for="mi-curiosity-1">What are you most curious about right now? (3 areas)</label>
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-curiosity-1"
                                                placeholder="e.g., How to improve my communication skills">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="curiosity">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-curiosity-2"
                                                placeholder="e.g., Understanding different learning styles">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="curiosity">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-curiosity-3"
                                                placeholder="e.g., Exploring creative problem-solving techniques">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="curiosity">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <label for="mi-role-models">Who are some people you admire? (3 role models)</label>
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-role-model-1" placeholder="e.g., Maya Angelou">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="roleModel">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-role-model-2" placeholder="e.g., Steve Jobs">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="roleModel">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-role-model-3" placeholder="e.g., Marie Curie">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="roleModel">💡</button>
                                        </div>
                                    </div>

                                    <div class="mi-form-field">
                                        <label for="mi-context-tags">Context Tags (comma-separated)</label>
                                        <div class="mi-input-group">
                                            <input type="text" id="mi-context-tags"
                                                placeholder="e.g., creative, analytical, social, hands-on">
                                            <button type="button" class="mi-quick-select-btn"
                                                data-examples="contextTag">💡</button>
                                        </div>
                                        <small>Add tags that describe your preferred learning contexts and approaches</small>
                                    </div>
                                </div>

                                <!-- CDT Qualifiers Section -->
                                <div class="mi-form-section">
                                    <h3>Cognitive Diversity Growth Areas</h3>
                                    <p>For your lowest 2 CDT dimensions (growth areas):</p>
                                    <p><em>Select the challenge that resonates most for each dimension:</em></p>

                                    <div id="mi-cdt-challenges-container">
                                        <!-- CDT challenge options will be populated by JavaScript -->
                                    </div>
                                </div>

                                <div class="mi-form-actions">
                                    <button type="button" id="mi-generate-experiments"
                                        class="mi-quiz-button mi-quiz-button-primary">Generate Learning Experiments</button>
                                    <div id="mi-form-validation-message" class="form-validation-message"></div>
                                </div>
                            </form>
                        </div>

                        <!-- Generated Experiments Display -->
                        <div id="mi-experiments-container" style="display:none;">
                            <div class="mi-experiments-header">
                                <h3>Your Personalized Learning Experiments</h3>
                                <div class="mi-experiments-actions">
                                    <button type="button" id="mi-modify-constraints"
                                        class="mi-quiz-button mi-quiz-button-secondary">Modify Constraints & Regenerate
                                        All</button>
                                    <button type="button" id="mi-start-over"
                                        class="mi-quiz-button mi-quiz-button-secondary">Start Over</button>
                                </div>
                            </div>
                            <div id="mi-experiments-list"></div>
                        </div>

                        <!-- Running Experiment Display -->
                        <div id="mi-running-experiment" style="display:none;">
                            <div class="mi-experiment-header">
                                <button type="button" id="mi-back-to-experiments"
                                    class="mi-quiz-button mi-quiz-button-secondary">&larr; Back to Experiments</button>
                                <h3 id="mi-running-experiment-title"></h3>
                            </div>
                            <div id="mi-running-experiment-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="mi-about-modal" class="mi-quiz-card mi-quiz-intro quiz-about-card"
            style="display:none; text-align:left; max-width:840px; margin:16px auto;">
            <h2 class="mi-section-title">About the MI Quiz</h2>
            <p>The Multiple Intelligences (MI) quiz helps identify the kinds of problems you’re best at solving by mapping
                your strengths across eight intelligences. For organizations, this tool supports hiring decisions, internal team development, and role alignment by clarifying which projects and environments naturally energize you.</p>

            <h3>What it measures</h3>
            <ul>
                <li><strong>Linguistic</strong> — words, expression, storytelling, reading/writing.</li>
                <li><strong>Logical–Mathematical</strong> — patterns, analysis, systems, problem‑solving.</li>
                <li><strong>Visual–Spatial</strong> — mental models, diagrams, maps, design.</li>
                <li><strong>Bodily–Kinesthetic</strong> — building, prototyping, learning by doing.</li>
                <li><strong>Musical</strong> — rhythm, tone, structure, auditory patterns.</li>
                <li><strong>Interpersonal</strong> — collaboration, empathy, social cues.</li>
                <li><strong>Intrapersonal</strong> — reflection, self‑awareness, independent study.</li>
                <li><strong>Naturalistic</strong> — observation, categorization, patterns in nature.</li>
            </ul>

            <h3>How it works</h3>
            <ul>
                <li>Quick statements rated 1–5 produce a clear profile of strengths.</li>
                <li>Top strengths surface sub‑skills and real‑world examples.</li>
                <li>Results connect to personalized suggestions and next steps.</li>
            </ul>

            <h3>What you’ll get</h3>
            <ul>
                <li>A clear snapshot of your top intelligences.</li>
                <?php if ($is_employee_user): ?>
                    <li>An automated summary upon finishing all your assigned assessments.</li>
                <?php else: ?>
                    <li>Two short sections: what to leverage now and where to grow.</li>
                    <li>Links into related assessments and next steps.</li>
                <?php endif; ?>
            </ul>

            <h3>Time & tips</h3>
            <ul>
                <li>Most people finish in 4–6 minutes.</li>
                <li>Answer by instinct — there are no “right” answers.</li>
            </ul>
        </div>
        <?php if (!$is_employee_user): ?>
        <div class="quiz-wrapper quiz-funnel-card" style="margin: 2em auto;">
            <div class="mi-quiz-card">
                <h2 class="mi-section-title">Your Progress So Far</h2>
                <?php echo do_shortcode('[quiz_funnel show_description="false" style="dashboard"]'); ?>
            </div>
        </div>
        <?php endif; ?>
        <script>
            (function () {
                function getModal() { return document.getElementById('mi-about-modal'); }
                function toggle() {
                    var m = getModal(); if (!m) return false;
                    var results = document.getElementById('mi-quiz-results');
                    var container = document.getElementById('mi-quiz-container');
                    var show = (m.style.display === 'none' || !m.style.display);
                    if (show) {
                        if (results) { m.dataset.prevRes = results.style.display || ''; results.style.display = 'none'; }
                        if (container) { m.dataset.prevCont = container.style.display || ''; container.style.display = 'none'; }
                        m.style.display = 'block';
                    } else {
                        m.style.display = 'none';
                        if (results && ('prevRes' in m.dataset)) results.style.display = m.dataset.prevRes;
                        if (container && ('prevCont' in m.dataset)) container.style.display = m.dataset.prevCont;
                    }
                    return false;
                }
                window._miAboutToggle = toggle;
            })();
        </script>
        <style>
            .quiz-about-card ul {
                margin: 0.75rem 0 1.5rem 1.5rem;
            }

            .quiz-about-card li {
                margin-bottom: 0.5rem;
                line-height: 1.5;
            }
        </style>
        <?php
        // Manual CSS Injection to bypass WP queue issues
        $css_url = plugin_dir_url(__FILE__) . 'css/mi-quiz.css';
        echo '<link rel="stylesheet" id="mi-quiz-css-manual" href="' . esc_url($css_url) . '" type="text/css" media="all" />';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MI Quiz: Manual Asset Injection Triggered.");
        }

        $js_file = plugin_dir_path(__FILE__) . 'mi-quiz.js';
        if (file_exists($js_file)) {
            // Base64 encode and eval to bypass any potential output filtering
            $js_content = file_get_contents($js_file);
            // Also embed the data directly into this block to ensure availability
            $data_json = json_encode($quiz_data);
            if ($data_json === false) {
                $json_error = json_last_error_msg();
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MI Quiz JSON Encode Error: ' . $json_error);
                }
                $data_json = '{}'; // Fallback to empty object to prevent SyntaxError
                echo '<script>console.error("MI Quiz Server Data Error: ' . esc_js($json_error) . '");</script>';
            }

            echo '<script>';
            echo 'try {';
            // 1. Define data safely using Base64
            echo '  var dataB64 = "' . base64_encode($data_json) . '";';
            echo '  window.miq_quiz_data = JSON.parse(atob(dataB64));';

            // 2. Then decode and eval the script
            echo '  var decoded = atob("' . base64_encode($js_content) . '");';
            echo '  eval(decoded + "\n//# sourceURL=evaluated-mi-quiz.js");';
            echo '} catch(e) { console.error("MI Quiz JS Injection Error:", e); }';
            echo '</script>';
        } else {
            echo '<script>console.error("MI Quiz JS file not found.");</script>';
        }

        return ob_get_clean();
    }

    public function ajax_save_user_results()
    {
        check_ajax_referer('miq_nonce');

        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $results = isset($_POST['results']) ? json_decode(stripslashes($_POST['results']), true) : [];

        if (!$userId || !is_array($results)) {
            wp_send_json_error('Invalid user ID or results data.');
        }

        $results['completed_at'] = time(); // Add timestamp
        update_user_meta($userId, 'miq_quiz_results', $results);

        // Calculate next step URL based on updated completion status
        $next_step_url = '';
        $next_step_title = '';
        if (class_exists('MC_Funnel')) {
            // Clear cache to ensure fresh completion status
            MC_Funnel::clear_all_dashboard_caches();

            $config = MC_Funnel::get_config();
            $completion = MC_Funnel::get_completion_status($userId);

            foreach (($config['steps'] ?? []) as $slug) {
                if (empty($completion[$slug])) {
                    $maybe = MC_Funnel::get_step_url($slug);
                    if ($maybe) {
                        $next_step_url = $maybe;
                        $next_step_title = 'Next Step: ' . ($config['titles'][$slug] ?? ucfirst(str_replace('-', ' ', $slug)));
                        break;
                    }
                }
            }

            // Trigger centralized completion check
            MC_Funnel::check_completion_and_notify($userId);
        }

        // Trigger Strain Index Calculation
        if (class_exists('MC_Strain_Index_Scorer')) {
            MC_Strain_Index_Scorer::calculate_from_user_meta($userId);
        }

        wp_send_json_success([
            'message' => 'Results saved to user profile.',
            'nextStepUrl' => $next_step_url,
            'nextStepTitle' => $next_step_title
        ]);
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
        $css_path = plugin_dir_path(__FILE__) . 'css/mi-quiz.css';
        if (file_exists($css_path)) {
            // Embed CSS directly for better compatibility
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

        // Save to a temporary file in the uploads directory
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'mi-quiz-pdfs';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $filepath = trailingslashit($temp_dir) . 'mi-results-' . wp_generate_password(12, false) . '.pdf';
        return file_put_contents($filepath, $pdf_content) ? $filepath : null;
    }

    public function ajax_generate_pdf()
    {
        check_ajax_referer('miq_nonce');

        if (!class_exists('Dompdf\Dompdf')) {
            wp_send_json_error('PDF library is not available.');
        }

        $results_html = isset($_POST['results_html']) ? wp_kses_post(wp_unslash($_POST['results_html'])) : '';
        if (empty($results_html)) {
            wp_send_json_error('No results data provided.');
        }

        $full_html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $css_path = plugin_dir_path(__FILE__) . 'css/mi-quiz.css';
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
            'mi-quiz-results-' . date('Y-m-d') . '.pdf',
            ['Attachment' => true]
        );
        exit;
    }

    public function ajax_delete_user_results()
    {
        check_ajax_referer('miq_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to delete results.');
        }

        $user_id = get_current_user_id();

        if (delete_user_meta($user_id, 'miq_quiz_results')) {
            wp_send_json_success('Your results have been deleted.');
        } else {
            // This can happen if meta didn't exist, which is not an error in this context.
            wp_send_json_success('No results found to delete, or they were already deleted.');
        }
    }

    /**
     * Fix User Switching plugin compatibility
     * Clear caches and force fresh page load when user switching is detected
     */
    public function fix_user_switching_compatibility()
    {
        // Check if user switching is active
        if (function_exists('current_user_switched') && current_user_switched()) {
            // Force WordPress to reprocess shortcodes by flushing object cache
            wp_cache_flush();

            // Remove any page caching that might interfere
            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
            }
        }
    }

    /**
     * Force shortcode processing on MI quiz page during user switching
     * Ensures the quiz shortcode is always processed even when switching users
     */
    public function force_shortcode_on_quiz_page($content)
    {
        // Only apply on the MI quiz page
        if (is_page() && has_shortcode(get_post()->post_content, 'mi_quiz')) {
            // If user switching is active, force shortcode processing
            if (function_exists('current_user_switched') && current_user_switched()) {
                // Remove any filters that might strip shortcodes
                remove_filter('the_content', 'wpautop');

                // Ensure shortcode is processed
                $content = do_shortcode($content);

                // Re-add wpautop after shortcode processing
                add_filter('the_content', 'wpautop');
            }
        }

        return $content;
    }
}
