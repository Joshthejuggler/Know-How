<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles all AI-related features for the Micro-Coach platform.
 */
class Micro_Coach_AI
{
    // Stores the OpenAI (or compatible) API key for AI features
    const OPT_OPENAI_API_KEY = 'mc_openai_api_key';
    // Admin-editable, long-form system prompt used to steer idea generation
    const OPT_SYSTEM_INSTRUCTIONS = 'mc_ai_system_instructions';
    // Toggle admin-only debug capture and page
    const OPT_DEBUG_MODE = 'mc_ai_debug_mode';
    // Model selection (admins): 'gpt-4o-mini' or 'gpt-4o'
    const OPT_MODEL = 'mc_ai_model';
    // DB table names
    const TABLE_EXPERIMENTS = 'mc_ai_experiments';
    const TABLE_FEEDBACK = 'mc_ai_feedback';

    public function __construct()
    {
        if (is_admin()) {
            // Add admin settings fields to the main platform settings page.
            add_action('admin_init', [$this, 'register_settings']);
            // Add AI Debug submenu page
            add_action('admin_menu', [$this, 'add_admin_pages']);
        }

        // Ensure tables on admin load (idempotent)
        add_action('admin_init', [$this, 'maybe_create_tables']);

        // AJAX: AI Coach idea generation
        add_action('wp_ajax_mc_ai_generate_mves', [$this, 'ajax_ai_generate_mves']);
        // AJAX: Test API key
        add_action('wp_ajax_mc_ai_test_key', [$this, 'ajax_ai_test_key']);
        // AJAX: Save heart/rating feedback
        add_action('wp_ajax_mc_ai_feedback', [$this, 'ajax_ai_feedback']);
        // AJAX: List saved (liked) experiments for current user
        add_action('wp_ajax_mc_ai_saved_list', [$this, 'ajax_ai_saved_list']);

        // AJAX: Employer generates experiments for employee
        // AJAX: Employer generates experiments for employee
        add_action('wp_ajax_mc_employer_generate_experiments', [$this, 'ajax_employer_generate_experiments']);

        // AJAX: Employer manages experiments
        add_action('wp_ajax_mc_employer_get_experiments', [$this, 'ajax_employer_get_experiments']);
        add_action('wp_ajax_mc_employer_update_experiment_status', [$this, 'ajax_employer_update_experiment_status']);

        // AJAX: Generate analysis report manually
        add_action('wp_ajax_mc_generate_analysis_report', [$this, 'ajax_generate_analysis_report']);
        // AJAX: View analysis report (loads cached data with detailed_answers on-demand)
        add_action('wp_ajax_mc_view_analysis_report', [$this, 'ajax_view_analysis_report']);
    }

    /**
     * Registers AI-related settings.
     */
    public function register_settings()
    {
        // AI Integration section (OpenAI API key)
        add_settings_section(
            'mc_quiz_ai_section',
            'AI Integration',
            function () {
                echo '<p>Configure AI behavior and credentials used to generate Minimum Viable Experiments (MVEs). Keys are stored as WordPress options.</p>';
            },
            'quiz-platform-settings' // This page is registered by Micro_Coach_Core
        );

        // Register and render the OpenAI API key field
        register_setting(Micro_Coach_Core::OPT_GROUP, self::OPT_OPENAI_API_KEY, function ($v) {
            // Keep it lean; accept plain text and trim
            return trim(sanitize_text_field($v));
        });
        // Admin-editable system instructions (fine-tuning prompt)
        register_setting(Micro_Coach_Core::OPT_GROUP, self::OPT_SYSTEM_INSTRUCTIONS, function ($v) {
            // Sanitize textarea content; allow basic punctuation and quotes
            $v = is_string($v) ? trim($v) : '';
            return sanitize_textarea_field($v);
        });
        // Debug toggle (admins only)
        register_setting(Micro_Coach_Core::OPT_GROUP, self::OPT_DEBUG_MODE, function ($v) {
            return $v ? '1' : '0';
        });
        // Model selection (admins only)
        register_setting(Micro_Coach_Core::OPT_GROUP, self::OPT_MODEL, function ($v) {
            $v = is_string($v) ? trim($v) : '';
            return in_array($v, ['gpt-4o-mini', 'gpt-4o'], true) ? $v : 'gpt-4o-mini';
        });
        add_settings_field(
            self::OPT_OPENAI_API_KEY,
            'OpenAI API Key',
            function () {
                $val = esc_attr(get_option(self::OPT_OPENAI_API_KEY, ''));
                echo '<input type="password" name="' . esc_attr(self::OPT_OPENAI_API_KEY) . '" value="' . $val . '" style="width: 480px;" placeholder="sk-..." autocomplete="new-password">';
                echo '<p class="description">Paste a server-side key (e.g., <code>sk-XXXX</code>). This key is never sent to the browser by this setting alone.</p>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );

        // Default Role Context
        register_setting(Micro_Coach_Core::OPT_GROUP, 'mc_default_role_context', function ($v) {
            return sanitize_textarea_field($v);
        });
        add_settings_field(
            'mc_default_role_context',
            'Default Role Context',
            function () {
                $val = get_option('mc_default_role_context', '');
                echo '<textarea name="mc_default_role_context" rows="3" style="width: 100%; max-width: 780px;" placeholder="e.g. Senior Developer...">' . esc_textarea($val) . '</textarea>';
                echo '<p class="description">This will pre-fill the "Role Context" field when generating experiments.</p>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );

        // Fine-tuning instructions textarea
        add_settings_field(
            self::OPT_SYSTEM_INSTRUCTIONS,
            'AI System Instructions',
            function () {
                $default = Micro_Coach_AI::get_default_system_instructions();
                $val = get_option(self::OPT_SYSTEM_INSTRUCTIONS, $default);
                $val_esc = esc_textarea($val);
                echo '<textarea name="' . esc_attr(self::OPT_SYSTEM_INSTRUCTIONS) . '" rows="10" style="width: 100%; max-width: 780px;">' . $val_esc . '</textarea>';
                echo '<p class="description">Guides the AI when generating MVEs. Edit to fine‑tune tone and constraints. Note: output remains constrained to JSON for parsing.</p>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );

        // Put the Test Connection control in the same AI section so all
        // AI settings are grouped together.
        add_settings_field(
            'mc_ai_test_connection',
            'Test Connection',
            [$this, 'render_test_connection_field'],
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );

        // Debug Mode
        add_settings_field(
            self::OPT_DEBUG_MODE,
            'Debug Mode (Admins Only)',
            function () {
                $enabled = get_option(self::OPT_DEBUG_MODE, '0') === '1' ? 'checked' : '';
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_DEBUG_MODE) . '" value="1" ' . $enabled . '> Capture last AI request/response + estimated cost for the AI Debug page.</label>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );

        // Model selection radios
        add_settings_field(
            self::OPT_MODEL,
            'Model',
            function () {
                $current = self::get_selected_model();
                $name = esc_attr(self::OPT_MODEL);
                $opt = function ($val, $label) use ($current, $name) {
                    $checked = $current === $val ? 'checked' : '';
                    echo '<label style="margin-right:16px;"><input type="radio" name="' . $name . '" value="' . esc_attr($val) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
                };
                echo '<div>';
                $opt('gpt-4o-mini', 'GPT‑4o mini (fast, cheaper)');
                $opt('gpt-4o', 'GPT‑4o (higher quality, pricier)');
                echo '</div>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );
    }

    /**
     * Renders the Test Connection field inside the AI settings section.
     */
    public function render_test_connection_field()
    {
        ?>
        <button class="button button-primary" id="mc-ai-test-btn">Test AI</button>
        <span id="mc-ai-test-status" style="margin-left: 8px;"></span>
        <script>
            (function () {
                const btn = document.getElementById('mc-ai-test-btn');
                const label = document.getElementById('mc-ai-test-status');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    btn.disabled = true; label.textContent = 'Testing…';
                    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'mc_ai_test_key' }) })
                        .then(r => r.json())
                        .then(j => {
                            if (j && j.success) { label.textContent = 'OK: ' + (j.data.model || 'connected'); }
                            else { label.textContent = 'Failed: ' + (j?.data?.message || 'unknown'); }
                        })
                        .catch(err => { label.textContent = 'Error: ' + (err?.message || 'network'); })
                        .finally(() => { btn.disabled = false; });
                });
            })();
        </script>
        <?php
    }

    /**
     * Returns the configured OpenAI-compatible API key (if set).
     * Consumers should call this to authenticate server-side requests.
     */
    public static function get_openai_api_key()
    {
        $key = get_option(self::OPT_OPENAI_API_KEY, '');
        return is_string($key) ? trim($key) : '';
    }

    /**
     * Default long-form system instructions used to steer idea generation.
     */
    public static function get_default_system_instructions()
    {
        return <<<'TXT'
Role: You generate personalized “minimum viable experiments” (MVEs) for self-discovery.

Inputs you will receive: A JSON payload with: MI top 3 (with scores), CDT subscale scores (plus strongest & growth edge), Bartle player type (primary and optional secondary), optional interests/context; and user-selected filters (cost, time, energy, variety), the brainstorming lenses to use (Curiosity, Role Models, Opposites, Adjacency), and a quantity target.

Task: Produce a diverse set of safe, low-stakes MVEs that the user can try within 7 days. Respect filters; tie each idea back to MI/CDT/Bartle.

Frameworks: Use TTCT (fluency/flexibility/originality/elaboration) for ideation; Fogg (Motivation × Ability × Prompt) for actionability; consider Gretchen Rubin’s Tendencies and Attachment/Interpersonal style for adherence & social fit.

Constraints:
– Specific, runnable steps (3–5), not generic advice.
– Calibrate cost/time/energy/variety to sliders; don’t exceed ±1 unless you add a tradeoff note.
– All ideas must be safe, legal, age-appropriate, and low-risk.
– Keep language warm, concrete, and non-judgmental.
TXT;
    }

    /**
     * Returns the system instructions from settings or the default value.
     */
    public static function get_system_instructions()
    {
        $v = get_option(self::OPT_SYSTEM_INSTRUCTIONS, '');
        $v = is_string($v) ? trim($v) : '';
        if ($v === '')
            return self::get_default_system_instructions();
        return $v;
    }

    /** Selected model (sanitized) with default */
    public static function get_selected_model()
    {
        $m = get_option(self::OPT_MODEL, 'gpt-4o-mini');
        return in_array($m, ['gpt-4o-mini', 'gpt-4o'], true) ? $m : 'gpt-4o-mini';
    }

    /** Adds the AI Debug submenu page under the main Quiz Platform menu. */
    public function add_admin_pages()
    {
        add_submenu_page(
            'quiz-platform-settings',
            'AI Debug',
            'AI Debug',
            'manage_options',
            'mc-ai-debug',
            [$this, 'render_debug_page']
        );
        add_submenu_page(
            'quiz-platform-settings',
            'AI Experiments',
            'AI Experiments',
            'manage_options',
            'mc-ai-experiments',
            [$this, 'render_experiments_admin']
        );
    }

    /** Render the AI Debug page. */
    public function render_debug_page()
    {
        if (!current_user_can('manage_options'))
            return;
        $uid = get_current_user_id();
        $last = get_transient('mc_ai_last_debug_' . $uid);
        echo '<div class="wrap"><h1>AI Debug</h1>';
        if (get_option(self::OPT_DEBUG_MODE, '0') !== '1') {
            echo '<div class="notice notice-warning"><p>Debug Mode is disabled. Enable it under <strong>Quiz Platform → Settings → AI Integration</strong>.</p></div>';
        }
        if (!$last) {
            echo '<p>No recent debug data found. Trigger an AI generation while logged in as an admin.</p></div>';
            return;
        }
        $esc = function ($s) {
            return esc_html(is_string($s) ? $s : wp_json_encode($s, JSON_PRETTY_PRINT));
        };
        echo '<h2>Summary</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>Timestamp</th><td>' . $esc($last['timestamp'] ?? '') . '</td></tr>';
        echo '<tr><th>Model</th><td>' . $esc($last['model'] ?? '') . '</td></tr>';
        echo '<tr><th>HTTP Code</th><td>' . $esc($last['response']['code'] ?? '') . '</td></tr>';
        echo '<tr><th>Prompt Tokens</th><td>' . $esc($last['response']['usage']['prompt_tokens'] ?? '') . '</td></tr>';
        echo '<tr><th>Completion Tokens</th><td>' . $esc($last['response']['usage']['completion_tokens'] ?? '') . '</td></tr>';
        echo '<tr><th>Estimated Cost (USD)</th><td>' . $esc($last['response']['cost_estimate_usd'] ?? '') . '</td></tr>';
        echo '<tr><th>Retry Attempted</th><td>' . $esc(!empty($last['retry_attempted']) ? 'yes' : 'no') . '</td></tr>';
        echo '<tr><th>Fallback</th><td>' . $esc((!empty($last['fallback']['used'])) ? ('yes — ' . ($last['fallback']['reason'] ?? '')) : 'no') . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Request</h2>';
        echo '<h3>System Instructions</h3><pre style="white-space:pre-wrap;">' . $esc($last['request']['system'] ?? '') . '</pre>';
        echo '<h3>User Message</h3><pre style="white-space:pre-wrap;">' . $esc($last['request']['user'] ?? '') . '</pre>';
        echo '<h3>Payload</h3><pre>' . $esc($last['request']['payload'] ?? []) . '</pre>';

        echo '<h2>Response Snippet</h2><pre>' . $esc($last['response']['raw_snippet'] ?? '') . '</pre>';
        echo '</div>';
    }

    /** Admin list of saved experiments with basic stats and a hide toggle */
    public function render_experiments_admin()
    {
        if (!current_user_can('manage_options'))
            return;
        $action = $_POST['mc_ai_bulk'] ?? '';
        if ($action === 'hide' && !empty($_POST['hash'])) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE_EXPERIMENTS;
            $hash = sanitize_text_field($_POST['hash']);
            $wpdb->update($table, ['hidden' => 1, 'updated_at' => current_time('mysql')], ['hash' => $hash]);
            echo '<div class="notice notice-success"><p>Experiment hidden.</p></div>';
        } elseif ($action === 'unhide' && !empty($_POST['hash'])) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE_EXPERIMENTS;
            $hash = sanitize_text_field($_POST['hash']);
            $wpdb->update($table, ['hidden' => 0, 'updated_at' => current_time('mysql')], ['hash' => $hash]);
            echo '<div class="notice notice-success"><p>Experiment unhidden.</p></div>';
        }
        global $wpdb;
        $exp = $wpdb->prefix . self::TABLE_EXPERIMENTS;
        $fb = $wpdb->prefix . self::TABLE_FEEDBACK;
        $rows = $wpdb->get_results("SELECT e.hash,e.title,e.lens,e.hidden,
            COALESCE(SUM(CASE WHEN f.liked=1 THEN 1 ELSE 0 END),0) like_count,
            ROUND(AVG(NULLIF(f.rating,0)),2) AS avg_rating
            FROM `$exp` e LEFT JOIN `$fb` f ON e.hash=f.hash
            GROUP BY e.id ORDER BY like_count DESC, avg_rating DESC LIMIT 300", ARRAY_A);
        echo '<div class="wrap"><h1>AI Experiments</h1>';
        echo '<table class="widefat striped"><thead><tr><th>Title</th><th>Lens</th><th>Likes</th><th>Avg Rating</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $form = '<form method="post" style="display:inline">'
                . '<input type="hidden" name="hash" value="' . esc_attr($r['hash']) . '">'
                . '<input type="hidden" name="mc_ai_bulk" value="' . ($r['hidden'] ? 'unhide' : 'hide') . '">'
                . '<button class="button">' . ($r['hidden'] ? 'Unhide' : 'Hide') . '</button>'
                . '</form>';
            echo '<tr>'
                . '<td>' . esc_html($r['title']) . '</td>'
                . '<td>' . esc_html($r['lens']) . '</td>'
                . '<td>' . intval($r['like_count']) . '</td>'
                . '<td>' . esc_html($r['avg_rating'] ?: '—') . '</td>'
                . '<td>' . ($r['hidden'] ? '<span class="dashicons dashicons-hidden"></span> Hidden' : 'Visible') . '</td>'
                . '<td>' . $form . '</td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';
    }

    /** Estimate cost in USD from usage for known models. */
    public static function estimate_cost_usd($model, $usage)
    {
        $in = 0.0005;
        $out = 0.0015; // defaults per 1K tokens
        $map = [
            'gpt-4o-mini' => ['in' => 0.00015, 'out' => 0.0006],
            'gpt-4o' => ['in' => 0.005, 'out' => 0.015],
        ];
        if (isset($map[$model])) {
            $in = $map[$model]['in'];
            $out = $map[$model]['out'];
        }
        $pt = is_array($usage) && isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0;
        $ct = is_array($usage) && isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0;
        $cost = ($pt / 1000.0) * $in + ($ct / 1000.0) * $out;
        return round($cost, 6);
    }

    /**
     * Create DB tables if missing (safe to call repeatedly).
     */
    public function maybe_create_tables()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $exp = $wpdb->prefix . self::TABLE_EXPERIMENTS;
        $fb = $wpdb->prefix . self::TABLE_FEEDBACK;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql1 = "CREATE TABLE IF NOT EXISTS `$exp` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hash VARCHAR(64) NOT NULL,
            title VARCHAR(200) NOT NULL,
            lens VARCHAR(40) NOT NULL,
            micro TEXT NULL,
            tags TEXT NULL,
            meta LONGTEXT NULL,
            hidden TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uhash (hash),
            KEY lens (lens),
            KEY hidden (hidden)
        ) $charset;";
        $sql2 = "CREATE TABLE IF NOT EXISTS `$fb` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hash VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            liked TINYINT(1) NOT NULL DEFAULT 0,
            rating TINYINT UNSIGNED NULL,
            profile LONGTEXT NULL,
            filters LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY h (hash),
            KEY u (user_id)
        ) $charset;";
        dbDelta($sql1);
        dbDelta($sql2);
    }

    private static function idea_hash($title, $lens)
    {
        return sha1(mb_strtolower(trim((string) $title)) . '|' . mb_strtolower(trim((string) $lens)));
    }

    private function upsert_experiment($hash, $title, $lens, $micro, $tags = [], $meta = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_EXPERIMENTS;
        $now = current_time('mysql');
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, meta FROM `$table` WHERE hash=%s", $hash), ARRAY_A);
        $exists = $row['id'] ?? null;
        // Merge meta if existing
        $existing_meta = [];
        if (!empty($row['meta'])) {
            $dec = json_decode($row['meta'], true);
            if (is_array($dec))
                $existing_meta = $dec;
        }
        if (!empty($meta) && is_array($meta)) {
            $existing_meta = array_merge($existing_meta, array_filter($meta, function ($v) {
                return !empty($v);
            }));
        }
        $meta_json = wp_json_encode($existing_meta);
        if ($exists) {
            $wpdb->update($table, ['title' => $title, 'lens' => $lens, 'micro' => $micro, 'tags' => wp_json_encode($tags), 'meta' => $meta_json, 'updated_at' => $now], ['id' => $exists]);
            return (int) $exists;
        }
        $wpdb->insert($table, ['hash' => $hash, 'title' => $title, 'lens' => $lens, 'micro' => $micro, 'tags' => wp_json_encode($tags), 'meta' => $meta_json ?: '{}', 'hidden' => 0, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $wpdb->insert_id;
    }

    private function save_feedback_row($hash, $user_id, $liked, $rating, $profile, $filters)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_FEEDBACK;
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'hash' => $hash,
            'user_id' => $user_id,
            'liked' => $liked ? 1 : 0,
            'rating' => is_null($rating) ? null : intval($rating),
            'profile' => wp_json_encode($profile),
            'filters' => wp_json_encode($filters),
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    private function experiment_stats_for_hashes($hashes, $user_id)
    {
        if (empty($hashes))
            return [];
        global $wpdb;
        $exp = $wpdb->prefix . self::TABLE_EXPERIMENTS;
        $fb = $wpdb->prefix . self::TABLE_FEEDBACK;
        $in = '(' . implode(',', array_fill(0, count($hashes), '%s')) . ')';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT e.hash,
            SUM(CASE WHEN f.liked=1 THEN 1 ELSE 0 END) AS like_count,
            AVG(NULLIF(f.rating,0)) AS avg_rating
            FROM `$exp` e LEFT JOIN `$fb` f ON e.hash=f.hash
            WHERE e.hash IN $in AND e.hidden=0 GROUP BY e.hash", $hashes), ARRAY_A);
        $stats = [];
        foreach ($rows as $r) {
            $stats[$r['hash']] = ['like_count' => intval($r['like_count']), 'avg_rating' => round(floatval($r['avg_rating']), 2)];
        }
        // user's latest like/rating per hash
        $mine_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT f.hash, f.liked, f.rating
             FROM `$fb` f
             INNER JOIN (
                SELECT hash, MAX(updated_at) AS latest
                FROM `$fb`
                WHERE user_id=%d AND hash IN $in
                GROUP BY hash
             ) m ON m.hash=f.hash AND f.updated_at=m.latest
             WHERE f.user_id=%d",
            array_merge([$user_id], $hashes, [$user_id])
        ), ARRAY_A);
        foreach ($mine_rows as $m) {
            $h = $m['hash'];
            if (!isset($stats[$h]))
                $stats[$h] = ['like_count' => 0, 'avg_rating' => 0];
            $stats[$h]['liked_by_you'] = (intval($m['liked']) === 1);
            $stats[$h]['your_rating'] = is_null($m['rating']) ? null : intval($m['rating']);
        }
        return $stats;
    }

    /**
     * AJAX: record heart/rating.
     */
    public function ajax_ai_feedback()
    {
        if (!is_user_logged_in())
            wp_send_json_error(['message' => 'Please sign in.'], 401);
        $user_id = get_current_user_id();
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lens = sanitize_text_field($_POST['lens'] ?? '');
        $micro = sanitize_textarea_field($_POST['micro'] ?? '');
        $tags = json_decode(stripslashes($_POST['tags'] ?? '[]'), true) ?: [];
        $liked = intval($_POST['liked'] ?? 0) ? 1 : 0;
        $rating = isset($_POST['rating']) ? max(0, min(5, intval($_POST['rating']))) : null;
        $meta = [
            'why_this_fits_you' => sanitize_textarea_field($_POST['why'] ?? ''),
            'prompt_to_start' => sanitize_textarea_field($_POST['prompt'] ?? ''),
            'signal_to_watch_for' => sanitize_textarea_field($_POST['signal'] ?? ''),
            'safety_notes' => sanitize_textarea_field($_POST['safety'] ?? ''),
        ];
        // Steps + reflection can be arrays via JSON
        $steps = json_decode(stripslashes($_POST['steps'] ?? '[]'), true);
        $reflect = json_decode(stripslashes($_POST['reflect'] ?? '[]'), true);
        if (is_array($steps))
            $meta['steps'] = array_values(array_map('sanitize_text_field', $steps));
        if (is_array($reflect))
            $meta['reflection_questions'] = array_values(array_map('sanitize_text_field', $reflect));
        $filters = [
            'cost' => intval($_POST['cost'] ?? 0),
            'time' => intval($_POST['time'] ?? 0),
            'energy' => intval($_POST['energy'] ?? 0),
            'variety' => intval($_POST['variety'] ?? 0),
            'lenses' => json_decode(stripslashes($_POST['lenses'] ?? '[]'), true) ?: []
        ];
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true) ?: [];
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true) ?: [];
        $pt_results = get_user_meta($user_id, 'bartle_quiz_results', true) ?: [];
        $profile = ['mi_top3' => $mi_results['top3'] ?? [], 'cdt_top' => $cdt_results['sortedScores'][0][0] ?? null, 'pt' => $pt_results['sortedScores'][0][0] ?? null];
        $hash = self::idea_hash($title, $lens);
        $this->maybe_create_tables();
        $this->upsert_experiment($hash, $title, $lens, $micro, $tags, $meta);
        $this->save_feedback_row($hash, $user_id, $liked, $rating, $profile, $filters);
        wp_send_json_success(['hash' => $hash]);
    }

    /**
     * AJAX: return saved (liked) experiments for current user
     */
    public function ajax_ai_saved_list()
    {
        if (!is_user_logged_in())
            wp_send_json_error(['message' => 'Please sign in.'], 401);
        $user_id = get_current_user_id();
        $this->maybe_create_tables();
        global $wpdb;
        $exp = $wpdb->prefix . self::TABLE_EXPERIMENTS;
        $fb = $wpdb->prefix . self::TABLE_FEEDBACK;
        // Only include items whose latest feedback row for this user is liked=1
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.hash,e.title,e.lens,e.micro,e.tags,e.meta,
                    f.updated_at as last_saved,
                    f.filters as last_filters
             FROM `$exp` e
             INNER JOIN (
                SELECT x.* FROM `$fb` x
                INNER JOIN (
                    SELECT hash, MAX(updated_at) AS latest
                    FROM `$fb`
                    WHERE user_id=%d
                    GROUP BY hash
                ) l ON l.hash=x.hash AND l.latest=x.updated_at
                WHERE x.user_id=%d AND x.liked=1
             ) f ON f.hash=e.hash
             WHERE e.hidden=0
             ORDER BY f.updated_at DESC
             LIMIT 300",
            $user_id,
            $user_id
        ), ARRAY_A);
        $items = [];
        $hashes = [];
        foreach ($rows as $r) {
            $filters = json_decode($r['last_filters'] ?: '{}', true) ?: [];
            $meta = json_decode($r['meta'] ?: '{}', true) ?: [];
            $items[] = [
                'title' => $r['title'],
                'lens' => $r['lens'],
                'micro_description' => $r['micro'],
                'tags' => (json_decode($r['tags'] ?: '[]', true) ?: []),
                'estimated_cost' => isset($filters['cost']) ? intval($filters['cost']) : 0,
                'estimated_time' => isset($filters['time']) ? intval($filters['time']) : 0,
                'estimated_energy' => isset($filters['energy']) ? intval($filters['energy']) : 0,
                'estimated_variety' => isset($filters['variety']) ? intval($filters['variety']) : 0,
                'hash' => $r['hash'],
                'why_this_fits_you' => $meta['why_this_fits_you'] ?? '',
                'prompt_to_start' => $meta['prompt_to_start'] ?? '',
                'steps' => $meta['steps'] ?? [],
                'signal_to_watch_for' => $meta['signal_to_watch_for'] ?? '',
                'reflection_questions' => $meta['reflection_questions'] ?? [],
                'safety_notes' => $meta['safety_notes'] ?? '',
            ];
            $hashes[] = $r['hash'];
        }
        $stats = $this->experiment_stats_for_hashes($hashes, $user_id);
        foreach ($items as &$it) {
            $h = $it['hash'];
            $it['_stats'] = $stats[$h] ?? ['like_count' => 0, 'avg_rating' => 0];
        }
        unset($it);
        wp_send_json_success(['items' => $items]);
    }

    /**
     * Admin AJAX: simple connectivity test for the AI key.
     */
    public function ajax_ai_test_key()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
        $api_key = self::get_openai_api_key();
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'No API key configured']);
        }
        $system = 'You are a healthcheck. Reply with a JSON object {"ok":true} only.';
        $model = self::get_selected_model();
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => 'ping']],
                'temperature' => 0,
            ]),
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => 'WP Error: ' . $resp->get_error_message()]);
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 200 && $code < 300) {
            $model = $body['model'] ?? ($body['choices'][0]['model'] ?? 'connected');
            wp_send_json_success(['model' => $model]);
        }
        wp_send_json_error(['message' => 'HTTP ' . $code . ' — ' . substr(wp_remote_retrieve_body($resp), 0, 200)]);
    }

    /**
     * AJAX: Employer gets experiments for an employee.
     */
    public function ajax_employer_get_experiments()
    {
        // Verify nonce? For now, just capability check
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
            wp_send_json_error(['message' => 'Not authorized'], 403);
        }

        $employer_id = get_current_user_id();
        $employee_id = intval($_POST['employee_id']);

        // Verify relationship
        $linked = get_user_meta($employee_id, 'mc_linked_employer_id', true);
        if (intval($linked) !== $employer_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not authorized']);
        }

        $assigned = get_user_meta($employee_id, 'mc_assigned_experiments', true) ?: [];

        // Return them (reversed so newest are at the top)
        // Also return current context so we can pre-fill the modal
        $role_context_data = get_user_meta($employee_id, 'mc_employee_role_context', true) ?: [];
        // We map the textarea to 'responsibilities'. The 'role' title is separate.
        $role_context = is_array($role_context_data) ? ($role_context_data['responsibilities'] ?? '') : '';

        $workplace_context_data = get_user_meta($employer_id, 'mc_workplace_context', true) ?: [];
        // Construct a formatted string for the workplace context
        $industry = $workplace_context_data['industry'] ?? '';
        $values = $workplace_context_data['values'] ?? '';
        $culture = $workplace_context_data['culture'] ?? '';

        $workplace_context = "Industry: $industry\nValues: $values\nCulture: $culture";

        wp_send_json_success([
            'experiments' => array_reverse($assigned),
            'role_context' => $role_context,
            'workplace_context' => $workplace_context
        ]);
    }

    /**
     * AJAX: Employer generates experiments for a specific employee.
     */
    public function ajax_employer_generate_experiments()
    {
        if (!is_user_logged_in() || !current_user_can(MC_Roles::CAP_GENERATE_EXPERIMENTS)) {
            wp_send_json_error(['message' => 'Not authorized.'], 403);
        }

        $employer_id = get_current_user_id();
        $employee_id = intval($_POST['employee_id'] ?? 0);

        if (!$employee_id) {
            wp_send_json_error(['message' => 'Invalid employee ID.']);

        }

        // Workplace Context
        if (isset($_POST['workplace_context'])) {
            $raw_text = sanitize_textarea_field($_POST['workplace_context']);

            // Parse the text back into fields
            $lines = explode("\n", $raw_text);
            $new_wp_data = [
                'industry' => '',
                'values' => '',
                'culture' => ''
            ];
            $current_key = 'culture'; // Default bucket if no labels found

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                if (stripos($line, 'Industry:') === 0) {
                    $new_wp_data['industry'] = trim(substr($line, 9));
                    $current_key = 'industry';
                } elseif (stripos($line, 'Values:') === 0) {
                    $new_wp_data['values'] = trim(substr($line, 7));
                    $current_key = 'values';
                } elseif (stripos($line, 'Culture:') === 0) {
                    $new_wp_data['culture'] = trim(substr($line, 8));
                    $current_key = 'culture';
                } else {
                    // Append to current key
                    if (!empty($new_wp_data[$current_key])) {
                        $new_wp_data[$current_key] .= "\n" . $line;
                    } else {
                        $new_wp_data[$current_key] = $line;
                    }
                }
            }

            // If parsing failed (everything in culture) and industry/values are empty, 
            // maybe we should try to preserve existing industry/values? 
            // But "save either direction" implies overwrite. 
            // Let's assume the user sees the pre-filled format and respects it, 
            // or if they wipe it, they intend to wipe it.

            update_user_meta($employer_id, 'mc_workplace_context', $new_wp_data);
        }

        // Get fresh context for AI
        $role_data = get_user_meta($employee_id, 'mc_employee_role_context', true) ?: [];
        $role_context_str = "Role: " . ($role_data['role'] ?? 'Employee') . "\nResponsibilities: " . ($role_data['responsibilities'] ?? '');

        $wp_data = get_user_meta($employer_id, 'mc_workplace_context', true) ?: [];
        $workplace_context_str = "Industry: " . ($wp_data['industry'] ?? '') . "\nValues: " . ($wp_data['values'] ?? '') . "\nCulture: " . ($wp_data['culture'] ?? '');

        // Verify relationship: Current user must be the employer of the target employee
        $linked_employer_id = get_user_meta($employee_id, 'mc_linked_employer_id', true);
        if (intval($linked_employer_id) !== $employer_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You are not authorized to generate experiments for this user.'], 403);
        }

        // Update Contexts if provided
        if (isset($_POST['role_context'])) {
            $role_ctx = sanitize_textarea_field(wp_unslash($_POST['role_context']));
            $existing_context = get_user_meta($employee_id, 'mc_employee_role_context', true) ?: [];
            if (!is_array($existing_context))
                $existing_context = [];

            $existing_context['responsibilities'] = $role_ctx;
            update_user_meta($employee_id, 'mc_employee_role_context', $existing_context);
        }
        if (isset($_POST['workplace_context'])) {
            $workplace_ctx = sanitize_textarea_field(wp_unslash($_POST['workplace_context']));

        }

        // Fetch Contexts
        $workplace_context = get_user_meta($employer_id, 'mc_workplace_context', true) ?: [];
        $employee_context = get_user_meta($employee_id, 'mc_employee_role_context', true) ?: [];

        // Fetch Assessment Results
        $results = MC_Funnel::get_all_assessment_results($employee_id);

        if (empty($results)) {
            wp_send_json_error(['message' => 'Employee has not completed assessments yet.']);
        }

        // Get Employee Name
        $employee_data = get_userdata($employee_id);
        $employee_name = $employee_data ? $employee_data->display_name : 'the employee';

        // Prepare Payload
        $payload = [
            'employee' => [
                'id' => (string) $employee_id,
                'name' => $employee_name,
                'results' => $results,
                'role_context' => $employee_context
            ],
            'employer' => [
                'workplace_context' => $workplace_context
            ]
        ];

        // Construct System Prompt
        $system = self::get_system_instructions() . ' ' .
            'You are acting as an AI Coach assisting an employer. ' .
            'Generate 3 specific, high-impact experiments for this employee to try in their specific workplace context. ' .
            'Focus on leveraging their strengths (MI, CDT, Bartle) to succeed in their role and align with company values. ' .
            'Always and only return a single JSON object with this top-level shape: {"ideas": [...]}. ' .
            'Do not include prose, markdown, or backticks.';

        $user_prompt = 'Employee & Workplace Data (JSON): ' . wp_json_encode($payload) . "\n\n" .
            'Generate 3 experiments. Each ideas[] item must have: ' .
            'title (<=60), lens (e.g., "Strength Application", "Role Alignment", "Value Integration"), micro_description (<=140), ' .
            'why_this_fits_you (explain connection to their profile AND workplace, using third person like "The employee, ' . $employee_name . '\'s exceptional skills..."), ' .
            'estimated_cost/time/energy/variety (ints 0..4), prompt_to_start, ' .
            'steps (3-5 strings), safety_notes (string), signal_to_watch_for (string), ' .
            'reflection_questions (2-3 strings), tags (array of short tokens). ' .
            'Return: {"ideas": [...]}';

        // Call AI API
        $api_key = self::get_openai_api_key();
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'AI service not configured.']);
        }

        $model = self::get_selected_model();
        $http_args = [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
            ]),
        ];

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $http_args);

        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => 'AI Request Failed: ' . $resp->get_error_message()]);
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode($content, true);

        if (empty($parsed['ideas'])) {
            // Try to parse if it's a bare array
            if (is_array($parsed) && isset($parsed[0]['title'])) {
                $parsed = ['ideas' => $parsed];
            } else {
                wp_send_json_error(['message' => 'AI returned invalid format.', 'debug' => $content]);
            }
        }

        // Save Experiments
        $assigned = get_user_meta($employee_id, 'mc_assigned_experiments', true) ?: [];

        foreach ($parsed['ideas'] as &$idea) {
            $idea['assigned_at'] = current_time('mysql');
            $idea['assigned_by'] = $employer_id;
            $idea['status'] = 'draft'; // Default to draft for manager review

            // Upsert to global table for tracking/analytics
            $hash = self::idea_hash($idea['title'], $idea['lens']);
            $idea['hash'] = $hash;
            $this->upsert_experiment($hash, $idea['title'], $idea['lens'], $idea['micro_description'], $idea['tags'] ?? [], $idea);

            // Add to user's list (avoid duplicates by hash)
            $exists = false;
            foreach ($assigned as $existing) {
                if (($existing['hash'] ?? '') === $hash) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $assigned[] = $idea;
            }
        }

        update_user_meta($employee_id, 'mc_assigned_experiments', $assigned);

        wp_send_json_success(['message' => 'Experiments generated as Drafts. Please review and share them.', 'count' => count($parsed['ideas'])]);

    }

    /**
     * AJAX: Generate MVEs (Minimum Viable Experiences) based on profile + filters.
     * Returns JSON with two arrays: shortlist and more (placeholders if API not configured).
     */
    public function ajax_ai_generate_mves()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please sign in to use the AI coach.'], 401);
        }

        $user_id = get_current_user_id();

        // Gather filters (0..4)
        $cost = max(0, min(4, intval($_POST['cost'] ?? 0)));
        $time = max(0, min(4, intval($_POST['time'] ?? 1)));
        $energy = max(0, min(4, intval($_POST['energy'] ?? 1)));
        $variety = max(0, min(4, intval($_POST['variety'] ?? 2)));
        $qty = max(4, min(24, intval($_POST['quantity'] ?? 12)));

        $lenses = [
            'Curiosity' => !empty($_POST['lens_curiosity']),
            'Role Models' => !empty($_POST['lens_rolemodels']),
            'Opposites' => !empty($_POST['lens_opposites']),
            'Adjacency' => !empty($_POST['lens_adjacency']),
        ];

        // Build profile snapshot (no PII)
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true) ?: [];
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true) ?: [];
        $pt_results = get_user_meta($user_id, 'bartle_quiz_results', true) ?: [];

        // MI top3 with 0..100 scores
        $mi_part1 = $mi_results['part1Scores'] ?? [];
        $mi_top3 = $mi_results['top3'] ?? [];
        $mi_profile = [];
        foreach ((array) $mi_top3 as $slug) {
            $score = isset($mi_part1[$slug]) ? round(($mi_part1[$slug] / 40) * 100) : 0;
            $mi_profile[] = ['slug' => $slug, 'score' => $score];
        }

        // CDT five subscales 0..100
        $cdt_sorted = $cdt_results['sortedScores'] ?? [];
        $cdt_scores = [];
        foreach ((array) $cdt_sorted as $pair) {
            if (!is_array($pair) || count($pair) < 2)
                continue;
            $cdt_scores[$pair[0]] = round(($pair[1] / 50) * 100);
        }
        $cdt_top = $cdt_sorted[0][0] ?? null;
        $cdt_bottom = end($cdt_sorted)[0] ?? null;

        // Player Type
        $pt_sorted = $pt_results['sortedScores'] ?? [];
        $primary_pt = $pt_sorted[0][0] ?? '';
        $primary_pt_p = isset($pt_sorted[0][1]) ? round(($pt_sorted[0][1] / 50) * 100) : 0;
        $secondary_pt = $pt_sorted[1][0] ?? '';
        $secondary_pt_p = isset($pt_sorted[1][1]) ? round(($pt_sorted[1][1] / 50) * 100) : 0;

        // Try to use OpenAI if configured; else produce a few placeholder ideas
        $ideas = [];
        $used_fallback = false;
        $fallback_reason = '';
        $api_key = self::get_openai_api_key();
        if (!empty($api_key)) {
            $debug_enabled = current_user_can('manage_options') && (get_option(self::OPT_DEBUG_MODE, '0') === '1');
            $debug_info = null;
            $retry_attempted = false;
            // Construct a compact payload for the model
            $payload = [
                'user' => [
                    'id' => (string) $user_id,
                    'mi_top3' => $mi_profile,
                    'cdt' => $cdt_scores,
                    'cdt_top' => $cdt_top,
                    'cdt_edge' => $cdt_bottom,
                    'player_type' => ['primary' => [$primary_pt, $primary_pt_p], 'secondary' => [$secondary_pt, $secondary_pt_p]],
                ],
                'filters' => [
                    'cost' => $cost,
                    'time' => $time,
                    'energy' => $energy,
                    'variety' => $variety,
                    'lenses' => array_keys(array_filter($lenses)),
                    'quantity' => $qty,
                ],
            ];

            // System instructions come from settings (admin fine‑tuning).
            $system = self::get_system_instructions() . ' '
                . 'Always and only return a single JSON object with this top-level shape: {"ideas": [...]}. '
                . 'Do not include prose, markdown, or backticks.';
            $user = 'Profile+filters JSON (no PII): ' . wp_json_encode($payload) . "\n\n" .
                'Each ideas[] item must have: '
                . 'title (<=60), lens (Curiosity|Role Models|Opposites|Adjacency), micro_description (<=140), '
                . 'why_this_fits_you, estimated_cost/time/energy/variety (ints 0..4), prompt_to_start, '
                . 'steps (3-5 strings), safety_notes (string), signal_to_watch_for (string), '
                . 'reflection_questions (2-3 strings), tags (array of short tokens). '
                . 'Return: {"ideas": [...]}';

            $model = self::get_selected_model();
            if (current_user_can('manage_options')) {
                $m = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
                if (in_array($m, ['gpt-4o-mini', 'gpt-4o'], true)) {
                    $model = $m;
                }
            }
            $http_args = [
                'timeout' => 45, // increase timeout to reduce curl 28 timeouts
                'redirection' => 3,
                'httpversion' => '1.1',
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                ]),
            ];

            // First attempt
            $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $http_args);
            // Simple retry on timeout or 5xx
            if (
                (is_wp_error($resp) && false !== stripos($resp->get_error_message(), 'timed out')) ||
                (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) >= 500)
            ) {
                error_log('MC AI: retry after timeout/5xx');
                // bump timeout a bit for the retry
                $http_args['timeout'] = 60;
                $retry_attempted = true;
                $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $http_args);
            }

            if (!is_wp_error($resp) && ($code = wp_remote_retrieve_response_code($resp)) >= 200 && $code < 300) {
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                $content = $body['choices'][0]['message']['content'] ?? '';
                // Primary parse
                $parsed = json_decode($content, true);
                // Secondary: try trimming to nearest JSON braces if needed
                if (!is_array($parsed)) {
                    $start = strpos($content, '{');
                    $end = strrpos($content, '}');
                    if ($start !== false && $end !== false && $end > $start) {
                        $content2 = substr($content, $start, $end - $start + 1);
                        $parsed = json_decode($content2, true);
                    }
                }
                if (is_array($parsed)) {
                    if (isset($parsed['ideas']) && is_array($parsed['ideas'])) {
                        $ideas = $parsed['ideas'];
                    } elseif (isset($parsed[0]) && is_array($parsed[0])) {
                        // Model returned a bare array; accept it
                        $ideas = $parsed;
                    } else {
                        $used_fallback = true;
                        $fallback_reason = 'json_object_missing_ideas';
                        error_log('MC AI: json_object_missing_ideas — first 400 chars: ' . substr($content, 0, 400));
                    }
                } else {
                    $used_fallback = true;
                    $fallback_reason = 'parse_error';
                    error_log('MC AI: parse_error — first 400 chars: ' . substr($content, 0, 400));
                }
            } else {
                $used_fallback = true;
                $fallback_reason = is_wp_error($resp) ? ('wp_error: ' . $resp->get_error_message()) : ('http_' . wp_remote_retrieve_response_code($resp));
                $snippet = is_wp_error($resp) ? '' : substr(wp_remote_retrieve_body($resp), 0, 400);
                error_log('MC AI: API error — ' . $fallback_reason . ' body: ' . $snippet);
            }

            // Capture debug info for admins if enabled
            if ($debug_enabled) {
                $resp_code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
                $resp_body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
                $resp_json = json_decode($resp_body, true);
                $usage = is_array($resp_json) ? ($resp_json['usage'] ?? []) : [];
                $model_used = is_array($resp_json) ? ($resp_json['model'] ?? ($resp_json['choices'][0]['model'] ?? 'gpt-4o-mini')) : 'gpt-4o-mini';
                $cost_est = self::estimate_cost_usd($model_used, $usage);
                $uid = get_current_user_id();
                $debug_payload = [
                    'timestamp' => current_time('mysql'),
                    'model' => $model_used,
                    'request' => [
                        'system' => $system,
                        'user' => $user,
                        'payload' => $payload,
                    ],
                    'response' => [
                        'code' => $resp_code,
                        'usage' => $usage,
                        'cost_estimate_usd' => $cost_est,
                        'raw_snippet' => substr($resp_body, 0, 1200),
                    ],
                    'retry_attempted' => $retry_attempted,
                    'fallback' => ['used' => $used_fallback, 'reason' => $fallback_reason],
                ];
                set_transient('mc_ai_last_debug_' . $uid, $debug_payload, HOUR_IN_SECONDS);
                $debug_info = $debug_payload;
            }
        }

        // Fallback proof‑of‑concept generator (heuristic ideas tied to profile)
        if (empty($ideas)) {
            if (!$used_fallback) {
                $used_fallback = true;
                $fallback_reason = empty($api_key) ? 'no_api_key' : 'poc_generated';
            }

            // Friendly names
            $mi_names = [
                'linguistic' => 'Linguistic',
                'logical-mathematical' => 'Logical–Mathematical',
                'spatial' => 'Spatial',
                'bodily-kinesthetic' => 'Bodily–Kinesthetic',
                'musical' => 'Musical',
                'interpersonal' => 'Interpersonal',
                'intrapersonal' => 'Intrapersonal',
                'naturalistic' => 'Naturalistic'
            ];
            $cdt_names = [
                'ambiguity-tolerance' => 'Ambiguity Tolerance',
                'value-conflict-navigation' => 'Value Conflict Navigation',
                'self-confrontation-capacity' => 'Self‑Confrontation Capacity',
                'discomfort-regulation' => 'Discomfort Regulation',
                'growth-orientation' => 'Growth Orientation'
            ];
            $pt_primary_name = $primary_pt ? ucfirst($primary_pt) : '';
            $mi_top1 = $mi_profile[0]['slug'] ?? '';
            $mi_top1_name = $mi_names[$mi_top1] ?? ($mi_top1 ?: 'Strength');
            $mi_top2 = $mi_profile[1]['slug'] ?? '';
            $mi_top2_name = $mi_names[$mi_top2] ?? ($mi_top2 ?: 'Skill');
            $cdt_top_name = $cdt_names[$cdt_top] ?? ($cdt_top ?: 'CDT Strength');
            $cdt_edge_name = $cdt_names[$cdt_bottom] ?? ($cdt_bottom ?: 'Growth Edge');

            $selected_lenses = array_keys(array_filter($lenses)) ?: ['Curiosity', 'Role Models', 'Opposites', 'Adjacency'];

            // Helper: clamp est within ±1 of filters for a comfortable fit
            $est = function ($target) {
                return max(0, min(4, $target));
            };

            // Templates per lens
            $make = function ($lens, $idx) use ($mi_top1_name, $mi_top2_name, $pt_primary_name, $cdt_top_name, $cdt_edge_name, $est, $cost, $time, $energy, $variety) {
                $title = $lens . ' – ';
                $desc = '';
                $why = '';
                $tags = ['learning'];
                switch ($lens) {
                    case 'Curiosity':
                        $title .= 'Micro‑scout in ' . $mi_top1_name;
                        $desc = 'Explore one tiny corner of ' . $mi_top1_name . '—20 minutes, one focused question.';
                        $why = 'Builds on your ' . $mi_top1_name . ' strength while keeping stakes low (' . $cdt_top_name . ').';
                        $tags = ['learning', 'solo'];
                        break;
                    case 'Role Models':
                        $title .= 'Shadow a mini role model';
                        $desc = 'Observe someone skilled for one session; debrief for 10 minutes after.';
                        $why = 'Fits your ' . $pt_primary_name . ' motivation and reinforces ' . $mi_top2_name . '.';
                        $tags = ['social', 'leadership'];
                        break;
                    case 'Opposites':
                        $title .= 'Opposite‑day in ' . $mi_top1_name;
                        $desc = 'Try the reverse of your usual approach once; capture one insight.';
                        $why = 'Gentle stretch toward ' . $cdt_edge_name . ' without heavy risk.';
                        $tags = ['creative'];
                        break;
                    case 'Adjacency':
                    default:
                        $title .= 'Adjacent step near ' . $mi_top2_name;
                        $desc = 'Add a tiny adjacent skill next to ' . $mi_top2_name . ' for one session.';
                        $why = 'Stays near strengths while opening new options (' . $cdt_top_name . ').';
                        $tags = ['learning', 'experimentation'];
                        break;
                }
                return [
                    'title' => $title,
                    'lens' => $lens,
                    'micro_description' => $desc,
                    'why_this_fits_you' => $why,
                    'estimated_cost' => $est($cost),
                    'estimated_time' => $est($time),
                    'estimated_energy' => $est($energy),
                    'estimated_variety' => $est($variety),
                    'prompt_to_start' => 'Block 20 minutes on your calendar and set a phone reminder.',
                    'steps' => ['Choose one target', 'Prepare 10 minutes', 'Run for 15–30 minutes', 'Capture one learning'],
                    'safety_notes' => '',
                    'signal_to_watch_for' => 'Did it energize you? What would you repeat?',
                    'reflection_questions' => ['What surprised you?', 'What would you tweak next time?'],
                    'tags' => $tags,
                ];
            };

            // Generate ideas rotating lenses
            $pool = [];
            for ($i = 0; $i < $qty; $i++) {
                $lens = $selected_lenses[$i % count($selected_lenses)];
                $pool[] = $make($lens, $i);
            }

            // Deterministic fit scoring
            $w = ['time' => 0.35, 'energy' => 0.35, 'cost' => 0.2, 'variety' => 0.1];
            foreach ($pool as &$it) {
                $dist = abs($it['estimated_time'] - $time) * $w['time'] + abs($it['estimated_energy'] - $energy) * $w['energy'] + abs($it['estimated_cost'] - $cost) * $w['cost'] + abs($it['estimated_variety'] - $variety) * $w['variety'];
                $it['fit_score'] = max(0, round(100 - 25 * $dist));
            }
            unset($it);
            usort($pool, function ($a, $b) {
                return ($b['fit_score'] ?? 0) <=> ($a['fit_score'] ?? 0);
            });

            $ideas = $pool;
        }

        // Annotate with community stats and user likes
        $hashes = [];
        foreach ($ideas as $it) {
            $hashes[] = self::idea_hash($it['title'] ?? '', $it['lens'] ?? '');
        }
        $stats = $this->experiment_stats_for_hashes($hashes, $user_id);
        foreach ($ideas as &$it) {
            $h = self::idea_hash($it['title'] ?? '', $it['lens'] ?? '');
            $it['hash'] = $h;
            $it['_stats'] = $stats[$h] ?? ['like_count' => 0, 'avg_rating' => 0];
        }
        unset($it);
        // Simple partition: shortlist 6, more next 6
        $shortlist = array_slice($ideas, 0, 6);
        $more = array_slice($ideas, 6, 12);
        $resp = [
            'shortlist' => $shortlist,
            'more' => $more,
            'used_fallback' => $used_fallback,
            'fallback_reason' => $fallback_reason,
        ];
        if (!empty($debug_info)) {
            $resp['debug'] = $debug_info;
        }
        wp_send_json_success($resp);
    }

    /**
     * Renders the AI Coach tab content for the dashboard.
     */
    public function render_ai_coach_tab()
    {
        // This method contains the HTML and JS for the AI Coach tab.
        // It's moved from Micro_Coach_Core::render_quiz_dashboard().
        ?>
        <?php $api_key_set = !empty(self::get_openai_api_key()); ?>
        <?php if (!$api_key_set): ?>
            <div class="ai-alert">
                <strong>Heads up:</strong> The AI key isn’t configured. Go to Quiz Platform → Settings → AI Integration to add your
                API key.
            </div>
        <?php endif; ?>
        <div id="ai-banner" class="ai-banner" style="display:none;"></div>
        <div class="ai-filters">
            <div class="ai-fheader">
                <span class="f-icn">🧩</span>
                <div>
                    <h3 class="ai-title">Choose your filters</h3>
                    <p class="ai-sub">to shape your filters to shape experiments you’ll see next.</p>
                </div>
            </div>
            <?php if (current_user_can('manage_options')): ?>
                <div class="ai-filter">
                    <div class="ai-filter-row">
                        <span class="f-icn">⚙️</span>
                        <div class="f-title">Model (admin)</div>
                        <div class="f-right">
                            <?php $sel = self::get_selected_model(); ?>
                            <select id="ai-model">
                                <option value="gpt-4o-mini" <?php selected($sel, 'gpt-4o-mini'); ?>>GPT‑4o mini</option>
                                <option value="gpt-4o" <?php selected($sel, 'gpt-4o'); ?>>GPT‑4o</option>
                            </select>
                        </div>
                    </div>
                    <div class="f-sub">Temporarily override the default model for this request.</div>
                </div>
            <?php endif; ?>
            <div class="ai-filter-grid">
                <div class="ai-filter">
                    <div class="ai-filter-row"><span class="f-icn">💰</span>
                        <div class="f-title">Cost</div>
                        <div class="f-right" id="ai-cost-label">Free</div>
                    </div>
                    <div class="f-sub">How much are you willing to spend?</div>
                    <input type="range" min="0" max="4" step="1" value="0" id="ai-cost">
                </div>
                <div class="ai-filter">
                    <div class="ai-filter-row"><span class="f-icn">⏳</span>
                        <div class="f-title">Time</div>
                        <div class="f-right" id="ai-time-label">15–30m</div>
                    </div>
                    <div class="f-sub">How much time can you commit?</div>
                    <input type="range" min="0" max="4" step="1" value="1" id="ai-time">
                </div>
                <div class="ai-filter">
                    <div class="ai-filter-row"><span class="f-icn">⚡️</span>
                        <div class="f-title">Energy</div>
                        <div class="f-right" id="ai-energy-label">Low‑focus</div>
                    </div>
                    <div class="f-sub">How much effort do you want to put in?</div>
                    <input type="range" min="0" max="4" step="1" value="1" id="ai-energy">
                </div>
                <div class="ai-filter">
                    <div class="ai-filter-row"><span class="f-icn">🎲</span>
                        <div class="f-title">Variety</div>
                        <div class="f-right" id="ai-variety-label">Near routine</div>
                    </div>
                    <div class="f-sub">How different from your routine should it be?</div>
                    <input type="range" min="0" max="4" step="1" value="2" id="ai-variety">
                </div>
            </div>
            <div class="ai-lenses">
                <label class="lens-chip lens-curiosity"><input type="checkbox" id="lens-curiosity"
                        checked><span>Curiosity</span></label>
                <label class="lens-chip lens-rolemodels"><input type="checkbox" id="lens-rolemodels" checked><span>Role
                        Models</span></label>
                <label class="lens-chip lens-opposites"><input type="checkbox" id="lens-opposites"
                        checked><span>Opposites</span></label>
                <label class="lens-chip lens-adjacency"><input type="checkbox" id="lens-adjacency"
                        checked><span>Adjacency</span></label>
            </div>
            <div class="ai-actions">
                <button class="quiz-dashboard-button" id="ai-apply">Show experiments that fit my settings</button>
                <button class="linklike" id="ai-reset" type="button">Reset</button>
            </div>
        </div>
        <div id="ai-debug" class="ai-debug" style="display:none;"></div>
        <div class="ai-results" id="ai-results-container" style="display:none;">
            <h4 class="ai-section">Shortlist</h4>
            <p class="ai-section-sub">Best‑fit ideas matched to your filters.</p>
            <div class="ai-results-grid" id="ai-shortlist"></div>
            <div id="ai-more-wrap" style="display:none;">
                <h4 class="ai-section">More options</h4>
                <p class="ai-section-sub">Additional ideas to explore if you want more variety.</p>
                <div class="ai-results-grid" id="ai-more"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Generates analysis (strengths & red flags) for an employee upon completion of all assessments.
     *
     * @param int $user_id The user ID.
     * @return array|false The analysis data or false on failure.
     */
    public static function generate_analysis_on_completion($user_id)
    {
        $api_key = self::get_openai_api_key();
        if (empty($api_key)) {
            return false;
        }

        // Gather Context
        $role_data = get_user_meta($user_id, 'mc_employee_role_context', true) ?: [];

        // PRE-CONDITION: Block if role or responsibilities are missing
        if (empty($role_data['role']) || empty($role_data['responsibilities'])) {
            error_log("MC AI: Blocked report generation for User $user_id due to missing Role Context.");
            return false;
        }

        $employer_id = get_user_meta($user_id, 'mc_linked_employer_id', true);
        $workplace_data = $employer_id ? (get_user_meta($employer_id, 'mc_workplace_context', true) ?: []) : [];

        // Gather Results
        $results = MC_Funnel::get_all_assessment_results($user_id);
        if (empty($results)) {
            return false;
        }

        // Construct Payload
        $payload = [
            'role' => $role_data,
            'workplace' => $workplace_data,
            'results' => $results
        ];

        // System Prompt
        $system = "You are a specialist assistant that generates manager-ready Employer Reports for individual team members.\n" .
            "Your job is to turn structured assessment data into a clear, practical report that helps managers coach, motivate, and retain employees.\n" .
            "The tone must be: Professional but warm, Coaching-oriented, Non-judgmental, and Focused on practical workplace actions.\n" .
            "Avoid psychological jargon. Do not mention Johari or tools not present in the input.\n" .
            "CRITICAL INSTRUCTION: You must TRANSLATE all 'Multiple Intelligences' (MI) terms into modern business competencies. NEVER use the raw MI terms in the output.\n" .
            "USE THIS TRANSLATION GUIDE:\n" .
            "- 'Verbal-Linguistic' -> 'Communication', 'Written Articulation', 'Persuasion', 'Documentation'.\n" .
            "- 'Logical-Mathematical' -> 'Data Analysis', 'Strategic Logic', 'Process Optimization', 'Financial Acumen'.\n" .
            "- 'Visual-Spatial' -> 'Design Thinking', 'Strategic Visualization', 'Layout & Planning', 'Visual Communication'.\n" .
            "- 'Bodily-Kinesthetic' -> 'Operational Execution', 'Field Operations', 'Action-Oriented', 'Hands-on Implementation'.\n" .
            "- 'Musical-Rhythmic' -> 'Pattern Recognition', 'Flow Management', 'Cadence & Timing', 'Event Sequencing'.\n" .
            "- 'Interpersonal' -> 'Team Leadership', 'Client Relations', 'Collaboration', 'Stakeholder Management'.\n" .
            "- 'Intrapersonal' -> 'Self-Regulation', 'Independent Focus', 'Resilience', 'Autonomy'.\n" .
            "- 'Naturalist' -> 'Systems Thinking', 'Organizational Awareness', 'Market Ecosystem Analysis', 'Categorization'.\n" .
            "EXAMPLE: Instead of 'High Bodily-Kinesthetic', say 'Strong aptitude for operational execution and field work'.\n" .
            "EXAMPLE: Instead of 'Musical Intelligence', say 'Keen ability to recognize patterns and manage workflow cadence'.\n\n" .
            "Return ONLY a JSON object with the following structure:\n" .
            "{\n" .
            "  \"executive_snapshot\": {\n" .
            "    \"context_summary\": \"Synthesize the role and workplace context into a 2-3 sentence summary that explains the basis of this analysis. Explicitly mention the Role Title and key context factors (e.g., 'Evaluating fitness for [Role Context Role] in a [Workplace Context Industry] environment...').\",\n" .
            "    \"top_strengths\": [\"Strength 1\", \"Strength 2\", \"Strength 3\"],\n" .
            "    \"top_weaknesses\": [\"Weakness 1\", \"Weakness 2\"],\n" .
            "    \"key_motivators\": [\"Motivator 1\", \"Motivator 2\"],\n" .
            "    \"growth_edge\": \"Primary growth edge description\",\n" .
            "    \"ideal_conditions\": \"Summary of ideal working conditions\"\n" .
            "  },\n" .
            "  \"overall_fit\": {\n" .
            "    \"score\": \"<<INTEGER 0-100>>\",\n" .
            "    \"rationale\": \"Detailed explanation (3-4 sentences) of why this fit score was assigned, referencing specific role requirements and workplace values. Explain the 'Why' behind the score so the employer understands the synthesis.\"\n" .
            "  },\n" .
            "  \"communication_playbook\": {\n" .
            "    \"do\": [\"Specific advice 1\", \"Specific advice 2\"],\n" .
            "    \"avoid\": [\"Thing to avoid 1\", \"Thing to avoid 2\"],\n" .
            "    \"format\": \"Preferred communication format\"\n" .
            "  },\n" .
            "  \"motivation_profile\": {\n" .
            "    \"energizers\": [\"Energizer 1\", \"Energizer 2\"],\n" .
            "    \"drainers\": [\"Drainer 1\", \"Drainer 2\"],\n" .
            "    \"rewards\": \"How they prefer to be recognized\"\n" .
            "  },\n" .
            "  \"work_style\": {\n" .
            "    \"approach\": \"Description of work style and problem solving\",\n" .
            "    \"best_when\": [\"Condition 1\", \"Condition 2\"],\n" .
            "    \"struggles_when\": [\"Condition 1\", \"Condition 2\"]\n" .
            "  },\n" .
            "  \"conflict_stress\": {\n" .
            "    \"handling\": \"How they handle disagreement\",\n" .
            "    \"signs\": \"Early signs of stress\",\n" .
            "    \"support\": \"How a manager can support them\"\n" .
            "  },\n" .
            "  \"coaching_recommendations\": [\n" .
            "    { \"title\": \"Action Title\", \"rationale\": \"Why this matters\", \"example\": \"Specific example\" },\n" .
            "    { \"title\": \"Action Title\", \"rationale\": \"Why this matters\", \"example\": \"Specific example\" },\n" .
            "    { \"title\": \"Action Title\", \"rationale\": \"Why this matters\", \"example\": \"Specific example\" },\n" .
            "    { \"title\": \"Action Title\", \"rationale\": \"Why this matters\", \"example\": \"Specific example\" }\n" .
            "  ],\n" .
            "  \"growth_edges\": [\n" .
            "    { \"assignment\": \"Stretch Assignment Name\", \"action\": \"What they would do\", \"capacity\": \"Capacity strengthened\", \"risk_level\": \"Low/Moderate\" },\n" .
            "    { \"assignment\": \"Stretch Assignment Name\", \"action\": \"What they would do\", \"capacity\": \"Capacity strengthened\", \"risk_level\": \"Low/Moderate\" }\n" .
            "  ],\n" .
            "  \"team_collaboration\": {\n" .
            "    \"thrives_with\": \"Teammates they thrive with\",\n" .
            "    \"friction_with\": \"Potential friction points\",\n" .
            "    \"placement_tips\": \"Tips for team placement\"\n" .
            "  },\n" .
            "  \"leadership_potential\": {\n" .
            "    \"summary\": \"2-3 sentences justifying the rating based on their assessment results.\",\n" .
            "    \"rating\": \"Individual Focus | Emerging | Developing | Strong\"\n" .
            "  },\n" .
            "  \"manager_fast_guide\": {\n" .
            "    \"strengths\": [\"Bullet 1\", \"Bullet 2\"],\n" .
            "    \"motivators\": [\"Bullet 1\", \"Bullet 2\"],\n" .
            "    \"communication\": [\"Bullet 1\", \"Bullet 2\"],\n" .
            "    \"coaching_moves\": [\"Bullet 1\", \"Bullet 2\"],\n" .
            "    \"growth_edge\": \"Bullet 1\"\n" .
            "  }\n" .
            "}";

        $user_prompt = "Employee Data: " . wp_json_encode($payload) . "\n\n" .
            "Generate the report. Be critical and specific. For 'Recommended Actions', focus on onboarding, project assignment, and mentorship. For 'Discussion Questions', focus on behavioral questions that reveal how they handle their specific weaknesses.";

        // Call OpenAI
        $model = self::get_selected_model();
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'temperature' => 0.5,
                'response_format' => ['type' => 'json_object']
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('MC AI Analysis Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        $analysis = json_decode($content, true);

        // Validation for new structure
        if (empty($analysis['executive_snapshot']) || empty($analysis['coaching_recommendations'])) {
            error_log('MC AI Analysis Failed to parse new structure: ' . $content);
            return false;
        }

        // Calculate Metadata
        $quiz_count = count($results);
        $peer_count = 0;

        // Check for Johari peer feedback count
        // Assuming Johari results structure or fetching from separate meta if needed
        // For now, let's try to extract it from the results if possible, or check the specific meta
        $johari_status = get_user_meta($user_id, 'johari_mi_status', true);
        if ($johari_status && isset($johari_status['peer_count'])) {
            $peer_count = intval($johari_status['peer_count']);
        } else {
            // Fallback: Check if we can count peers from the results payload if it contains raw data
            // Or check the specific Johari meta key if we know it
            $johari_results = get_user_meta($user_id, 'johari_mi_results', true);
            if ($johari_results && isset($johari_results['peer_feedback'])) {
                $peer_count = count($johari_results['peer_feedback']);
            }
        }

        $analysis['meta'] = [
            'quiz_count' => $quiz_count,
            'peer_count' => $peer_count,
            'generated_at' => current_time('mysql')
        ];

        // Save Analysis
        update_user_meta($user_id, 'mc_assessment_analysis', $analysis);
        update_user_meta($user_id, 'mc_analysis_last_generated', current_time('mysql')); // Save generation timestamp

        return $analysis;
    }

    /**
     * AJAX: Generate analysis report for an employee manually (called by employer)
     */
    public function ajax_generate_analysis_report()
    {
        // Check user is logged in and has permission
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
            wp_send_json_error(['message' => 'Not authorized'], 403);
        }

        try {
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if (!$user_id) {
                wp_send_json_error(['message' => 'Invalid user ID'], 400);
            }

            // Generate the analysis
            $success = self::generate_analysis_on_completion($user_id);
            if ($success === false) {
                // Check if it was blocked due to role context
                $ctx = get_user_meta($user_id, 'mc_employee_role_context', true);
                if (empty($ctx) || empty($ctx['role']) || empty($ctx['responsibilities'])) {
                    wp_send_json_error(['message' => 'Role Context is missing. Please define Role & Responsibilities first.'], 400);
                }
                wp_send_json_error(['message' => 'AI Generation Failed. Please try again.'], 500);
            }

            if ($success) {
                // Fetch the updated analysis to return to the frontend
                $updated_analysis = get_user_meta($user_id, 'mc_assessment_analysis', true);
                $strain_results = get_user_meta($user_id, 'strain_index_results', true);

                // Fix: Calculate Strain Index if missing (e.g. for manually generated test data)
                if (empty($strain_results) && class_exists('MC_Strain_Index_Scorer')) {
                    $strain_results = MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
                }

                // Add employee name for the modal
                $user_info = get_userdata($user_id);

                // Calculate Breakdown for Accordion
                $mi = get_user_meta($user_id, 'miq_quiz_results', true);
                $cdt = get_user_meta($user_id, 'cdt_quiz_results', true);
                $bartle = get_user_meta($user_id, 'bartle_quiz_results', true);

                // Ensure these are arrays for safe access
                if (!is_array($mi))
                    $mi = [];
                if (!is_array($cdt))
                    $cdt = [];
                if (!is_array($bartle))
                    $bartle = [];

                $get_val = function ($arr, $key) {
                    if (!is_array($arr))
                        return 0;
                    if (isset($arr['part1Scores'][$key]))
                        return intval($arr['part1Scores'][$key]);
                    if (isset($arr['scores'][$key]))
                        return intval($arr['scores'][$key]);
                    return 0;
                };

                $strain_breakdown = [
                    'rumination' => [
                        'MI' => $get_val($mi, 'si-rumination'),
                        'CDT' => $get_val($cdt, 'si-rumination'),
                        'Bartle' => $get_val($bartle, 'si-rumination')
                    ],
                    'avoidance' => [
                        'MI' => $get_val($mi, 'si-avoidance'),
                        'CDT' => $get_val($cdt, 'si-avoidance'),
                        'Bartle' => $get_val($bartle, 'si-avoidance')
                    ],
                    'flood' => [
                        'MI' => $get_val($mi, 'si-emotional-flood'),
                        'CDT' => $get_val($cdt, 'si-emotional-flood'),
                        'Bartle' => $get_val($bartle, 'si-emotional-flood')
                    ]
                ];


                // Extract detailed answers by matching strain index questions to the answers array
                // Load strain questions ONCE for all quizzes (outside the loop)
                $all_strain_questions = [];

                // Load MI questions
                $mi_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                if (file_exists($mi_quiz_path)) {
                    include $mi_quiz_path;
                    if (isset($mi_questions['adult'])) {
                        $all_strain_questions['mi'] = [];
                        foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                            $all_strain_questions['mi'][$cat] = $mi_questions['adult'][$cat] ?? [];
                        }
                    }
                }

                // Load CDT questions  
                $cdt_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                if (file_exists($cdt_quiz_path)) {
                    include $cdt_quiz_path;
                    if (isset($cdt_questions_base)) {
                        $all_strain_questions['cdt'] = [];
                        foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                            $qs = [];
                            foreach ($cdt_questions_base[$cat] ?? [] as $qItem) {
                                if (isset($qItem['text']))
                                    $qs[] = $qItem['text'];
                            }
                            $all_strain_questions['cdt'][$cat] = $qs;
                        }
                    }
                }

                // Load Bartle questions
                $bartle_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';
                if (file_exists($bartle_quiz_path)) {
                    include $bartle_quiz_path;
                    if (isset($bartle_questions['adult'])) {
                        $all_strain_questions['bartle'] = [];
                        foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                            $qs = [];
                            foreach ($bartle_questions['adult'][$cat] ?? [] as $qItem) {
                                if (isset($qItem['text']))
                                    $qs[] = $qItem['text'];
                            }
                            $all_strain_questions['bartle'][$cat] = $qs;
                        }
                    }
                }

                // Simple function to match answers to strain questions (no includes!)
                $extract_strain_answers = function ($answers, $strain_questions) {
                    $result = [
                        'si-rumination' => [],
                        'si-avoidance' => [],
                        'si-emotional-flood' => []
                    ];

                    if (empty($answers) || !is_array($answers) || empty($strain_questions)) {
                        return $result;
                    }

                    foreach ($strain_questions as $cat => $questions) {
                        foreach ($questions as $q) {
                            if (!is_string($q))
                                continue;

                            // Try the original question text first
                            if (isset($answers[$q])) {
                                $result[$cat][$q] = $answers[$q];
                            } else {
                                // Try with stripslashes (normalizes escaped quotes)
                                $normalized_q = stripslashes($q);
                                if (isset($answers[$normalized_q])) {
                                    $result[$cat][$q] = $answers[$normalized_q];
                                }
                            }
                        }
                    }

                    return $result;
                };

                $mi_strain = $extract_strain_answers($mi['answers'] ?? [], $all_strain_questions['mi'] ?? []);
                $cdt_strain = $extract_strain_answers($cdt['answers'] ?? [], $all_strain_questions['cdt'] ?? []);
                $bartle_strain = $extract_strain_answers($bartle['answers'] ?? [], $all_strain_questions['bartle'] ?? []);

                $detailed_answers = [
                    'rumination' => [
                        'MI' => $mi_strain['si-rumination'],
                        'CDT' => $cdt_strain['si-rumination'],
                        'Bartle' => $bartle_strain['si-rumination']
                    ],
                    'avoidance' => [
                        'MI' => $mi_strain['si-avoidance'],
                        'CDT' => $cdt_strain['si-avoidance'],
                        'Bartle' => $bartle_strain['si-avoidance']
                    ],
                    'flood' => [
                        'MI' => $mi_strain['si-emotional-flood'],
                        'CDT' => $cdt_strain['si-emotional-flood'],
                        'Bartle' => $bartle_strain['si-emotional-flood']
                    ]
                ];

                $response_data = [
                    'message' => 'Report generated successfully!',
                    'analysis' => $updated_analysis,
                    'strain_results' => $strain_results,
                    'strain_breakdown' => $strain_breakdown,
                    'detailed_answers' => $detailed_answers,
                    'name' => $user_info ? $user_info->display_name : 'Employee',
                    'id' => $user_id,
                    'debug_status' => 'success',
                ];
                // Add Metadata for Admin Report
                $response_data['test_data_type'] = get_user_meta($user_id, 'mc_last_test_data_type', true);
                $response_data['test_data_timestamp'] = get_user_meta($user_id, 'mc_last_test_data_timestamp', true);
                $response_data['role_context'] = get_user_meta($user_id, 'mc_employee_role_context', true);
                $response_data['analysis_timestamp'] = get_user_meta($user_id, 'mc_analysis_last_generated', true);

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error(['message' => 'Failed to generate report. Please try again.']);
            }
        } catch (Throwable $e) {
            error_log('MC AI Generate Report Fatal Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Critical error generating report.', 'debug' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: View existing analysis report (without regenerating)
     * Loads cached analysis and computes strain breakdown/detailed_answers on-demand
     */
    public function ajax_view_analysis_report()
    {
        // Check user is logged in and has permission
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
            wp_send_json_error(['message' => 'Not authorized'], 403);
        }

        try {
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if (!$user_id) {
                wp_send_json_error(['message' => 'Invalid user ID'], 400);
            }

            // Fetch the cached analysis
            $analysis = get_user_meta($user_id, 'mc_assessment_analysis', true);
            $strain_results = get_user_meta($user_id, 'strain_index_results', true);

            if (empty($analysis)) {
                wp_send_json_error(['message' => 'No analysis found. Please generate a report first.'], 404);
            }

            // Fix: Calculate Strain Index if missing
            if (empty($strain_results) && class_exists('MC_Strain_Index_Scorer')) {
                $strain_results = MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
            }

            // Add employee name
            $user_info = get_userdata($user_id);

            // Calculate Breakdown for Accordion
            $mi = get_user_meta($user_id, 'miq_quiz_results', true);
            $cdt = get_user_meta($user_id, 'cdt_quiz_results', true);
            $bartle = get_user_meta($user_id, 'bartle_quiz_results', true);

            // Ensure these are arrays for safe access
            if (!is_array($mi))
                $mi = [];
            if (!is_array($cdt))
                $cdt = [];
            if (!is_array($bartle))
                $bartle = [];

            $get_val = function ($arr, $key) {
                if (!is_array($arr))
                    return 0;
                if (isset($arr['part1Scores'][$key]))
                    return intval($arr['part1Scores'][$key]);
                if (isset($arr['scores'][$key]))
                    return intval($arr['scores'][$key]);
                return 0;
            };

            $strain_breakdown = [
                'rumination' => [
                    'MI' => $get_val($mi, 'si-rumination'),
                    'CDT' => $get_val($cdt, 'si-rumination'),
                    'Bartle' => $get_val($bartle, 'si-rumination')
                ],
                'avoidance' => [
                    'MI' => $get_val($mi, 'si-avoidance'),
                    'CDT' => $get_val($cdt, 'si-avoidance'),
                    'Bartle' => $get_val($bartle, 'si-avoidance')
                ],
                'flood' => [
                    'MI' => $get_val($mi, 'si-emotional-flood'),
                    'CDT' => $get_val($cdt, 'si-emotional-flood'),
                    'Bartle' => $get_val($bartle, 'si-emotional-flood')
                ]
            ];

            // Load strain questions and compute detailed answers ON-DEMAND
            // Wrapped in try-catch to prevent fatal errors from include conflicts
            $all_strain_questions = [];

            // Load MI questions
            $mi_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
            if (file_exists($mi_quiz_path)) {
                include $mi_quiz_path;
                if (isset($mi_questions['adult'])) {
                    $all_strain_questions['mi'] = [];
                    foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                        $all_strain_questions['mi'][$cat] = $mi_questions['adult'][$cat] ?? [];
                    }
                }
            }

            // Load CDT questions  
            $cdt_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
            if (file_exists($cdt_quiz_path)) {
                include $cdt_quiz_path;
                if (isset($cdt_questions_base)) {
                    $all_strain_questions['cdt'] = [];
                    foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                        $qs = [];
                        foreach ($cdt_questions_base[$cat] ?? [] as $qItem) {
                            if (isset($qItem['text']))
                                $qs[] = $qItem['text'];
                        }
                        $all_strain_questions['cdt'][$cat] = $qs;
                    }
                }
            }

            // Load Bartle questions
            $bartle_quiz_path = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';
            if (file_exists($bartle_quiz_path)) {
                include $bartle_quiz_path;
                if (isset($bartle_questions['adult'])) {
                    $all_strain_questions['bartle'] = [];
                    foreach (['si-rumination', 'si-avoidance', 'si-emotional-flood'] as $cat) {
                        $qs = [];
                        foreach ($bartle_questions['adult'][$cat] ?? [] as $qItem) {
                            if (isset($qItem['text']))
                                $qs[] = $qItem['text'];
                        }
                        $all_strain_questions['bartle'][$cat] = $qs;
                    }
                }
            }

            // Simple function to match answers to strain questions
            $extract_strain_answers = function ($answers, $strain_questions) {
                $result = [
                    'si-rumination' => [],
                    'si-avoidance' => [],
                    'si-emotional-flood' => []
                ];

                if (empty($answers) || !is_array($answers) || empty($strain_questions)) {
                    return $result;
                }

                foreach ($strain_questions as $cat => $questions) {
                    foreach ($questions as $q) {
                        if (!is_string($q))
                            continue;

                        // Try the original question text first
                        if (isset($answers[$q])) {
                            $result[$cat][$q] = $answers[$q];
                        } else {
                            // Try with stripslashes (normalizes escaped quotes)
                            $normalized_q = stripslashes($q);
                            if (isset($answers[$normalized_q])) {
                                $result[$cat][$q] = $answers[$normalized_q];
                            }
                        }
                    }
                }

                return $result;
            };

            $mi_strain = $extract_strain_answers($mi['answers'] ?? [], $all_strain_questions['mi'] ?? []);
            $cdt_strain = $extract_strain_answers($cdt['answers'] ?? [], $all_strain_questions['cdt'] ?? []);
            $bartle_strain = $extract_strain_answers($bartle['answers'] ?? [], $all_strain_questions['bartle'] ?? []);

            $detailed_answers = [
                'rumination' => [
                    'MI' => $mi_strain['si-rumination'],
                    'CDT' => $cdt_strain['si-rumination'],
                    'Bartle' => $bartle_strain['si-rumination']
                ],
                'avoidance' => [
                    'MI' => $mi_strain['si-avoidance'],
                    'CDT' => $cdt_strain['si-avoidance'],
                    'Bartle' => $bartle_strain['si-avoidance']
                ],
                'flood' => [
                    'MI' => $mi_strain['si-emotional-flood'],
                    'CDT' => $cdt_strain['si-emotional-flood'],
                    'Bartle' => $bartle_strain['si-emotional-flood']
                ]
            ];

            $response_data = [
                'message' => 'Report loaded successfully!',
                'analysis' => $analysis,
                'strain_results' => $strain_results,
                'strain_breakdown' => $strain_breakdown,
                'detailed_answers' => $detailed_answers,
                'name' => $user_info ? $user_info->display_name : 'Employee',
                'id' => $user_id,
                // Add Metadata for Admin Report
                'test_data_type' => get_user_meta($user_id, 'mc_last_test_data_type', true),
                'test_data_timestamp' => get_user_meta($user_id, 'mc_last_test_data_timestamp', true),
                'role_context' => get_user_meta($user_id, 'mc_employee_role_context', true),
                'analysis_timestamp' => get_user_meta($user_id, 'mc_analysis_last_generated', true)
            ];

            wp_send_json_success($response_data);
        } catch (Throwable $e) {
            error_log('MC AI View Report Fatal Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Critical error viewing report.', 'debug' => $e->getMessage()]);
        }
    }
}
