<?php
if (!defined('ABSPATH')) {
    exit;
}

class PRM_Plugin
{
    const MENU_SLUG = 'product-roadmap-manager';
    const SETTINGS_SLUG = 'product-roadmap-manager-settings';
    const OPT_ROADMAP_DATA = 'prm_task_data';
    const NONCE_ACTION = 'prm_task_data';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_prm_reset_data', [__CLASS__, 'handle_reset_data']);
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);

        add_action('wp_ajax_prm_toggle_item', [__CLASS__, 'ajax_toggle_item']);
        add_action('wp_ajax_prm_save_item', [__CLASS__, 'ajax_save_item']);
        add_action('wp_ajax_prm_delete_item', [__CLASS__, 'ajax_delete_item']);
        add_action('wp_ajax_prm_upload_task_image', [__CLASS__, 'ajax_upload_task_image']);
        add_action('wp_ajax_prm_delete_task_image', [__CLASS__, 'ajax_delete_task_image']);

        add_filter('plugin_action_links_' . plugin_basename(PRM_PLUGIN_FILE), [__CLASS__, 'plugin_action_links']);
    }

    public static function add_admin_page()
    {
        add_menu_page(
            'Product Roadmap',
            'Product Roadmap',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_admin_page'],
            'dashicons-list-view',
            59
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Product Roadmap',
            'Dashboard',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_admin_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Roadmap Settings',
            'Settings',
            'manage_options',
            self::SETTINGS_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function enqueue_assets($hook_suffix)
    {
        $allowed_hooks = [
            'toplevel_page_' . self::MENU_SLUG,
            self::MENU_SLUG . '_page_' . self::SETTINGS_SLUG,
        ];

        if (!in_array($hook_suffix, $allowed_hooks, true)) {
            return;
        }

        $css_path = PRM_PLUGIN_PATH . 'assets/product-roadmap.css';
        $js_path = PRM_PLUGIN_PATH . 'assets/product-roadmap.js';
        $css_version = file_exists($css_path) ? (string) filemtime($css_path) : PRM_PLUGIN_VERSION;
        $js_version = file_exists($js_path) ? (string) filemtime($js_path) : PRM_PLUGIN_VERSION;

        wp_enqueue_style(
            'prm-product-roadmap',
            PRM_PLUGIN_URL . 'assets/product-roadmap.css',
            [],
            $css_version
        );

        wp_enqueue_script(
            'prm-product-roadmap',
            PRM_PLUGIN_URL . 'assets/product-roadmap.js',
            [],
            $js_version,
            true
        );

        wp_localize_script(
            'prm-product-roadmap',
            'prmProductRoadmap',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'sections' => self::get_roadmap_data(),
            ]
        );
    }

    public static function plugin_action_links($links)
    {
        $open_url = admin_url('admin.php?page=' . self::MENU_SLUG);
        $settings_url = admin_url('admin.php?page=' . self::SETTINGS_SLUG);

        array_unshift(
            $links,
            '<a href="' . esc_url($open_url) . '">Open</a>',
            '<a href="' . esc_url($settings_url) . '">Settings</a>'
        );

        return $links;
    }

    public static function render_admin_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($_GET['prm_reset'])) {
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible">
            <p>Product Roadmap Manager data was reset.</p>
        </div>
        <?php
    }

    public static function render_admin_page()
    {
        ?>
        <div class="wrap prm-roadmap-admin">
            <div id="prm-product-roadmap-app"></div>
        </div>
        <?php
    }

    public static function render_settings_page()
    {
        $reset_url = wp_nonce_url(admin_url('admin-post.php?action=prm_reset_data'), 'prm_reset_data');
        ?>
        <div class="wrap">
            <h1>Product Roadmap Settings</h1>
            <div style="max-width:980px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:24px 28px;box-shadow:0 1px 2px rgba(0,0,0,.03);">
                    <h2 style="margin-top:0;">Data Storage</h2>
                    <p style="font-size:14px;line-height:1.7;margin-bottom:14px;">
                        This plugin stores its task data in one dedicated WordPress option. Any pasted screenshots are uploaded into the WordPress media library and linked back to their tasks.
                    </p>
                    <p style="font-size:14px;line-height:1.7;margin-bottom:14px;">
                        Using <strong>Reset Data</strong> removes all roadmap tasks and deletes any screenshots attached to them. Deleting the plugin removes the option and attached screenshots entirely via <code>uninstall.php</code>.
                    </p>
                    <p style="font-size:14px;line-height:1.7;margin-bottom:22px;">
                        This reset is destructive. It removes every saved task across all categories and cannot be undone.
                    </p>
                    <a class="button button-secondary" href="<?php echo esc_url($reset_url); ?>" onclick="return confirm('Reset all roadmap data? This will permanently remove every task in every category.');">Reset Data</a>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_reset_data()
    {
        self::assert_admin_permissions();
        check_admin_referer('prm_reset_data');

        self::reset_data();

        wp_safe_redirect(add_query_arg('prm_reset', '1', admin_url('admin.php?page=' . self::SETTINGS_SLUG)));
        exit;
    }

    public static function ajax_toggle_item()
    {
        self::assert_ajax_permissions();

        $item_id = isset($_POST['itemId']) ? sanitize_key(wp_unslash($_POST['itemId'])) : '';
        $done = isset($_POST['done']) ? wp_validate_boolean(wp_unslash($_POST['done'])) : false;

        if ($item_id === '') {
            wp_send_json_error(['message' => 'Missing task id.'], 400);
        }

        $sections = self::get_roadmap_data();
        $updated = false;

        foreach ($sections as &$section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as &$item) {
                if (($item['id'] ?? '') !== $item_id) {
                    continue;
                }

                $item['status'] = $done ? 'done' : (($item['status'] ?? 'todo') === 'done' ? 'todo' : $item['status']);
                $item['updatedAt'] = current_time('mysql');
                $updated = true;
                break 2;
            }
        }
        unset($section, $item);

        if (!$updated) {
            wp_send_json_error(['message' => 'Task not found.'], 404);
        }

        self::save_roadmap_data($sections);
        wp_send_json_success(['sections' => self::get_roadmap_data()]);
    }

    public static function ajax_save_item()
    {
        self::assert_ajax_permissions();

        $item_id = isset($_POST['itemId']) ? sanitize_key(wp_unslash($_POST['itemId'])) : '';
        $target_cat = isset($_POST['cat']) ? sanitize_key(wp_unslash($_POST['cat'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $desc = isset($_POST['desc']) ? sanitize_textarea_field(wp_unslash($_POST['desc'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'todo';
        $priority = isset($_POST['priority']) ? sanitize_key(wp_unslash($_POST['priority'])) : 'medium';
        $owner = isset($_POST['owner']) ? sanitize_text_field(wp_unslash($_POST['owner'])) : '';
        $due_date = isset($_POST['dueDate']) ? sanitize_text_field(wp_unslash($_POST['dueDate'])) : '';
        $completion_notes = isset($_POST['completionNotes']) ? sanitize_textarea_field(wp_unslash($_POST['completionNotes'])) : '';
        $attachments = [];

        if (isset($_POST['attachments'])) {
            $decoded = json_decode(wp_unslash($_POST['attachments']), true);
            if (is_array($decoded)) {
                $attachments = $decoded;
            }
        }

        if ($title === '') {
            wp_send_json_error(['message' => 'Task title is required.'], 400);
        }

        $schema = self::get_sections_schema();
        if (!isset($schema[$target_cat])) {
            wp_send_json_error(['message' => 'Invalid task category.'], 400);
        }

        $sections = self::get_roadmap_data();
        $existing_item = null;

        if ($item_id !== '') {
            foreach ($sections as &$section) {
                if (empty($section['items']) || !is_array($section['items'])) {
                    continue;
                }

                foreach ($section['items'] as $index => $item) {
                    if (($item['id'] ?? '') !== $item_id) {
                        continue;
                    }

                    $existing_item = $item;
                    unset($section['items'][$index]);
                    $section['items'] = array_values($section['items']);
                    break 2;
                }
            }
            unset($section);
        }

        $now = current_time('mysql');
        $task = self::sanitize_item([
            'id' => $item_id ?: 'roadmap-' . wp_generate_uuid4(),
            'title' => $title,
            'desc' => $desc,
            'status' => $status,
            'priority' => $priority,
            'owner' => $owner,
            'dueDate' => $due_date,
            'completionNotes' => $completion_notes,
            'attachments' => $attachments,
            'createdAt' => $existing_item['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]);

        if ($existing_item) {
            $existing_attachment_ids = self::extract_attachment_ids_from_item($existing_item);
            $new_attachment_ids = self::extract_attachment_ids_from_item($task);
            $removed_attachment_ids = array_diff($existing_attachment_ids, $new_attachment_ids);
            self::delete_attachment_ids($removed_attachment_ids);
        }

        foreach ($sections as &$section) {
            if (($section['cat'] ?? '') !== $target_cat) {
                continue;
            }

            $section['items'][] = $task;
            break;
        }
        unset($section);

        self::save_roadmap_data($sections);
        wp_send_json_success([
            'item' => $task,
            'sections' => self::get_roadmap_data(),
        ]);
    }

    public static function ajax_delete_item()
    {
        self::assert_ajax_permissions();

        $item_id = isset($_POST['itemId']) ? sanitize_key(wp_unslash($_POST['itemId'])) : '';
        if ($item_id === '') {
            wp_send_json_error(['message' => 'Missing task id.'], 400);
        }

        $sections = self::get_roadmap_data();
        $deleted = false;
        $attachment_ids_to_delete = [];

        foreach ($sections as &$section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $index => $item) {
                if (($item['id'] ?? '') !== $item_id) {
                    continue;
                }

                $attachment_ids_to_delete = self::extract_attachment_ids_from_item($item);
                unset($section['items'][$index]);
                $section['items'] = array_values($section['items']);
                $deleted = true;
                break 2;
            }
        }
        unset($section);

        if (!$deleted) {
            wp_send_json_error(['message' => 'Task not found.'], 404);
        }

        self::delete_attachment_ids($attachment_ids_to_delete);
        self::save_roadmap_data($sections);
        wp_send_json_success(['sections' => self::get_roadmap_data()]);
    }

    public static function ajax_upload_task_image()
    {
        self::assert_ajax_permissions();

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No image file received.'], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
        }

        $mime_type = get_post_mime_type($attachment_id);
        if (strpos((string) $mime_type, 'image/') !== 0) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(['message' => 'Only image uploads are allowed.'], 400);
        }

        $image_src = wp_get_attachment_image_src($attachment_id, 'medium');
        wp_send_json_success([
            'attachment' => [
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'thumbnailUrl' => $image_src ? $image_src[0] : wp_get_attachment_url($attachment_id),
                'filename' => basename(get_attached_file($attachment_id)),
                'mimeType' => $mime_type,
            ],
        ]);
    }

    public static function ajax_delete_task_image()
    {
        self::assert_ajax_permissions();

        $attachment_id = isset($_POST['attachmentId']) ? absint(wp_unslash($_POST['attachmentId'])) : 0;
        if ($attachment_id <= 0) {
            wp_send_json_error(['message' => 'Missing attachment id.'], 400);
        }

        if (get_post($attachment_id) === null) {
            wp_send_json_success(['deleted' => true]);
        }

        wp_delete_attachment($attachment_id, true);
        wp_send_json_success(['deleted' => true]);
    }

    public static function reset_data()
    {
        $sections = self::get_roadmap_data();
        self::delete_attachment_ids(self::collect_attachment_ids_from_sections($sections));
        update_option(self::OPT_ROADMAP_DATA, self::get_empty_roadmap_data(), false);
    }

    private static function cleanup()
    {
        $sections = get_option(self::OPT_ROADMAP_DATA, []);
        if (is_array($sections)) {
            self::delete_attachment_ids(self::collect_attachment_ids_from_sections(self::normalize_sections($sections)));
        }
        delete_option(self::OPT_ROADMAP_DATA);
    }

    private static function assert_admin_permissions()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
    }

    private static function assert_ajax_permissions()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    private static function get_roadmap_data()
    {
        $sections = get_option(self::OPT_ROADMAP_DATA, null);

        if (!is_array($sections) || empty($sections)) {
            $sections = self::get_default_roadmap_data();
            add_option(self::OPT_ROADMAP_DATA, $sections, '', 'no');
        }

        return self::normalize_sections($sections);
    }

    private static function save_roadmap_data(array $sections)
    {
        update_option(self::OPT_ROADMAP_DATA, self::normalize_sections($sections), false);
    }

    private static function normalize_sections(array $sections)
    {
        $schema = self::get_sections_schema();
        $input_by_cat = [];

        foreach ($sections as $section) {
            $cat = isset($section['cat']) ? sanitize_key($section['cat']) : '';
            if ($cat !== '') {
                $input_by_cat[$cat] = $section;
            }
        }

        $normalized = [];
        foreach ($schema as $cat => $meta) {
            $raw_items = $input_by_cat[$cat]['items'] ?? [];
            $items = [];

            if (is_array($raw_items)) {
                foreach ($raw_items as $item) {
                    $sanitized = self::sanitize_item($item);
                    if ($sanitized !== null) {
                        $items[] = $sanitized;
                    }
                }
            }

            $normalized[] = [
                'cat' => $cat,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'items' => $items,
            ];
        }

        return $normalized;
    }

    private static function sanitize_item($item)
    {
        if (!is_array($item)) {
            return null;
        }

        $item_id = isset($item['id']) ? sanitize_key($item['id']) : '';
        $title = isset($item['title']) ? sanitize_text_field($item['title']) : '';
        $desc = isset($item['desc']) ? sanitize_textarea_field($item['desc']) : '';
        $status = isset($item['status']) ? sanitize_key($item['status']) : '';
        $priority = isset($item['priority']) ? sanitize_key($item['priority']) : '';
        $owner = isset($item['owner']) ? sanitize_text_field($item['owner']) : '';
        $due_date = isset($item['dueDate']) ? sanitize_text_field($item['dueDate']) : '';
        $completion_notes = isset($item['completionNotes']) ? sanitize_textarea_field($item['completionNotes']) : '';
        $created_at = isset($item['createdAt']) ? sanitize_text_field($item['createdAt']) : '';
        $updated_at = isset($item['updatedAt']) ? sanitize_text_field($item['updatedAt']) : '';
        $attachments = [];

        if (isset($item['attachments']) && is_array($item['attachments'])) {
            foreach ($item['attachments'] as $attachment) {
                $sanitized_attachment = self::sanitize_attachment($attachment);
                if ($sanitized_attachment !== null) {
                    $attachments[] = $sanitized_attachment;
                }
            }
        }

        if ($item_id === '' || $title === '') {
            return null;
        }

        $allowed_statuses = ['todo', 'in_progress', 'blocked', 'done'];
        if ($status === '' && !empty($item['done'])) {
            $status = 'done';
        }
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'todo';
        }

        $allowed_priorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $allowed_priorities, true)) {
            $priority = 'medium';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            $due_date = '';
        }

        if ($created_at === '') {
            $created_at = current_time('mysql');
        }
        if ($updated_at === '') {
            $updated_at = $created_at;
        }

        return [
            'id' => $item_id,
            'title' => $title,
            'desc' => $desc,
            'status' => $status,
            'priority' => $priority,
            'owner' => $owner,
            'dueDate' => $due_date,
            'completionNotes' => $completion_notes,
            'attachments' => $attachments,
            'createdAt' => $created_at,
            'updatedAt' => $updated_at,
        ];
    }

    private static function sanitize_attachment($attachment)
    {
        if (!is_array($attachment)) {
            return null;
        }

        $attachment_id = isset($attachment['id']) ? absint($attachment['id']) : 0;
        if ($attachment_id <= 0 || get_post($attachment_id) === null) {
            return null;
        }

        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            return null;
        }

        $thumbnail = wp_get_attachment_image_src($attachment_id, 'medium');

        return [
            'id' => $attachment_id,
            'url' => esc_url_raw($url),
            'thumbnailUrl' => $thumbnail ? esc_url_raw($thumbnail[0]) : esc_url_raw($url),
            'filename' => sanitize_file_name($attachment['filename'] ?? basename(get_attached_file($attachment_id))),
            'mimeType' => sanitize_text_field($attachment['mimeType'] ?? get_post_mime_type($attachment_id)),
        ];
    }

    private static function collect_attachment_ids_from_sections(array $sections)
    {
        $attachment_ids = [];

        foreach ($sections as $section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $item) {
                $attachment_ids = array_merge($attachment_ids, self::extract_attachment_ids_from_item($item));
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $attachment_ids))));
    }

    private static function extract_attachment_ids_from_item($item)
    {
        if (!is_array($item) || empty($item['attachments']) || !is_array($item['attachments'])) {
            return [];
        }

        $attachment_ids = [];
        foreach ($item['attachments'] as $attachment) {
            $attachment_ids[] = absint($attachment['id'] ?? 0);
        }

        return array_values(array_filter($attachment_ids));
    }

    private static function delete_attachment_ids(array $attachment_ids)
    {
        foreach (array_unique(array_filter(array_map('absint', $attachment_ids))) as $attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }
    }

    private static function get_sections_schema()
    {
        return [
            'product' => ['label' => 'Product & App', 'color' => 'purple'],
            'ux' => ['label' => 'Report & UX', 'color' => 'teal'],
            'technical' => ['label' => 'Technical', 'color' => 'coral'],
            'zac' => ['label' => 'Zac Follow-Ups', 'color' => 'amber'],
            'josh' => ['label' => 'Josh Follow-Ups', 'color' => 'blue'],
            'future' => ['label' => 'Future Ideas', 'color' => 'pink'],
        ];
    }

    private static function get_default_roadmap_data()
    {
        return [
            [
                'cat' => 'product',
                'label' => 'Product & App',
                'color' => 'purple',
                'items' => [
                    self::item('product-allow-comparisons', 'Allow comparisons without candidate mode', "Currently people can only be compared if marked as a candidate. Fix so Zac doesn't have to keep toggling people into candidate mode.", 'high', 'Josh'),
                    self::item('product-hide-score', 'Hide or remove the overall CDT alignment score', 'The 1-100 score is causing people to over-focus on tiny numerical differences like 77 vs. 76, even though the number may look more scientific than it really is.', 'high', 'Josh'),
                    self::item('product-replace-score', 'Replace the score with a simpler category', 'Options include High / Medium / Low, five quadrants, or another non-numeric visual system.', 'high', 'Josh'),
                    self::item('product-rename-cdt', "Rename 'CDT' to 'Cognitive Flexibility'", "Zac feels 'Cognitive Flexibility' is much easier for clients to understand than 'Cognitive Dissonance Tolerance'.", 'high', 'Josh'),
                    self::item('product-review-report-structure', 'Review the PDF / report structure', "The data is useful but the presentation needs work. Highlight the most important insights at the top and make the 'what do I do with this?' section much clearer.", 'medium', 'Zac'),
                    self::item('product-prototype-summary-report', 'Prototype a stronger 2-page summary report', 'Zac will use Claude Cowork with the current PDF and his prompt to generate a possible format. Josh can then help turn the final version into the actual report format.', 'medium', 'Zac'),
                    self::item('product-progress-indicator', 'Add a progress indicator to questionnaires', 'Could show users how far they are through the quiz, or how far they are through each section.', 'medium', 'Josh'),
                    self::item('product-bite-sized-sections', 'Break questionnaires into bite-sized sections', 'May reduce fatigue and help people feel like the process is more manageable.', 'medium', 'Josh'),
                    self::item('product-rewrite-questions', 'Review and possibly rewrite questions to reduce gaming', 'Zac wants to revisit the questions before running a larger group, possibly before Prialto.', 'medium', 'Zac'),
                    self::item('product-cancel-warp', 'Cancel Warp if no longer needed', 'Josh noted that Claude, Codex, and Antigravity have mostly replaced Warp in the workflow.', 'low', 'Josh'),
                ],
            ],
            [
                'cat' => 'ux',
                'label' => 'Report & UX',
                'color' => 'teal',
                'items' => [
                    self::item('ux-actionable-report-top', 'Make the top of the report more useful and actionable', 'The first section should quickly answer: What matters most? What should the manager do? What risks or opportunities should they watch for?', 'high', 'Josh'),
                    self::item('ux-infographic-report', 'Consider a more infographic-style report', 'Zac described wanting something more bite-sized and easy to grab onto - more visual and less dense.', 'medium', 'Josh'),
                    self::item('ux-coaching-recommendations', 'Add specific coaching recommendations', 'Reports should help managers understand how to support someone, especially around onboarding, team dynamics, communication, and conflict.', 'high', 'Josh'),
                    self::item('ux-dynamics-to-watch', "Add 'dynamics to watch' sections", 'Explain what may happen when someone joins a team or works under a particular manager.', 'medium', 'Josh'),
                    self::item('ux-stronger-summary', 'Keep longer reports but strengthen the executive summary', 'Long is okay if the top section is immediately useful and actionable.', 'medium', 'Josh'),
                ],
            ],
            [
                'cat' => 'technical',
                'label' => 'Technical',
                'color' => 'coral',
                'items' => [
                    self::item('technical-load-ai-on-demand', 'Change AI-generated scorecard advice to load on demand', 'The manager/team scorecard is slower because it calls AI to generate advice. Better option: show a button users can click when they want that advice.', 'high', 'Josh'),
                    self::item('technical-loading-message', 'Add a friendly loading message', "Suggested tone: 'This is taking a moment because humans are complicated. You do not want a rushed answer here.'", 'medium', 'Josh'),
                    self::item('technical-avoid-loading-time', 'Avoid unnecessary loading time for managers', 'Especially for dashboards or scorecards managers may open regularly.', 'high', 'Josh'),
                    self::item('technical-code-review', 'Run a code review for scalability and streamlining', 'Use AI to inspect the code and suggest improvements, but avoid risky refactoring directly on the live branch.', 'medium', 'Josh'),
                    self::item('technical-stress-test', 'Consider a stress test', 'Current usage likely handles multiple users fine, but the system has not been tested under heavy simultaneous load.', 'medium', 'Josh'),
                    self::item('technical-separate-branch', 'Refactor on a separate branch if needed', 'Bigger structural improvements could break things, so they should be tested away from production.', 'medium', 'Josh'),
                ],
            ],
            [
                'cat' => 'zac',
                'label' => 'Zac Follow-Ups',
                'color' => 'amber',
                'items' => [
                    self::item('zac-onboard-test-users', 'Onboard a few more free test users', 'Zac wants more people using it before making too many major changes.', 'medium', 'Zac'),
                    self::item('zac-report-structure', 'Think through the new report structure', "Especially the 2-page format and the 'rock star' summary.", 'medium', 'Zac'),
                    self::item('zac-test-claude-cowork', 'Test Claude Cowork for report redesign', 'Feed it the current PDF and describe the desired format.', 'medium', 'Zac'),
                    self::item('zac-questionnaire-format', 'Keep thinking through the questionnaire format', 'Especially how to reduce gaming and improve the completion experience.', 'medium', 'Zac'),
                    self::item('zac-selection-process', 'Confirm how this fits into selection processes', 'Zac already has ideas about where this gets inserted into hiring / selection workflows.', 'medium', 'Zac'),
                ],
            ],
            [
                'cat' => 'josh',
                'label' => 'Josh Follow-Ups',
                'color' => 'blue',
                'items' => [
                    self::item('josh-send-debrief', 'Send Zac a debrief with action items and future ideas', 'This list can be used as the starting point.', 'high', 'Josh'),
                    self::item('josh-confirm-changes', 'Confirm the immediate requested changes before editing', 'Especially: candidate comparison issue, hide / replace CDT score, rename CDT to Cognitive Flexibility.', 'high', 'Josh'),
                    self::item('josh-dev-workflow-walkthrough', 'Give Zac another walkthrough of the dev workflow', 'The workflow has improved enough that Zac may be able to make minor tweaks himself.', 'medium', 'Josh'),
                    self::item('josh-cancel-warp', 'Look into cancelling Warp', "It is on Zac's card and no longer seems necessary.", 'low', 'Josh'),
                    self::item('josh-prialto-team-size', "Research Prialto's actual team size", "Uncertainty around whether the '100 employees' includes VAs or only full-time internal staff.", 'medium', 'Josh'),
                ],
            ],
            [
                'cat' => 'future',
                'label' => 'Future Ideas',
                'color' => 'pink',
                'items' => [
                    self::item('future-ai-portal', 'AI portal for interacting with results', 'A clickable link in the PDF that lets a manager quiz or interrogate the result through a chatbot.', 'medium', 'Zac'),
                    self::item('future-expand-beyond-hiring', 'Expand use beyond hiring', 'Expand from hiring into onboarding, coaching, performance reviews, annual check-ins, and team development.', 'medium', 'Zac'),
                    self::item('future-coaching-assistant', 'Manager coaching assistant', 'A manager could ask: "I am struggling to help Chelsea be more direct. Based on her results and my results, what should I try?"', 'high', 'Zac'),
                    self::item('future-manager-entered-data', 'Add manager-entered data points', 'Managers could log real workplace issues or coaching moments, giving the AI more context over time.', 'medium', 'Zac'),
                    self::item('future-development-paths', 'Build long-term employee development paths', 'The data could become the foundation for growth plans, not just selection decisions.', 'medium', 'Zac'),
                    self::item('future-team-benchmark', 'Use team data as a benchmark', 'Compare new candidates against existing team patterns to support integration and coaching.', 'medium', 'Zac'),
                    self::item('future-sticky-product', 'Make the product sticky through ongoing use', 'The moat may be the relationship, accumulated data, and integration into hiring / coaching workflows - not just the assessment itself.', 'medium', 'Zac'),
                    self::item('future-hr-integrations', 'Explore HR platform integrations', 'Humi was mentioned as an example. Could plug into HR workflows where companies already assign assessments like Enneagram or MBTI.', 'medium', 'Zac'),
                    self::item('future-custom-assessment-menu', 'Create a customizable assessment menu', 'Companies could choose which tools or modules they want to include in their hiring / coaching process.', 'medium', 'Zac'),
                    self::item('future-white-label-api', 'Position as a white-label assessment / coaching API', 'Long-term: companies interact with insights through their existing tools rather than logging into the software.', 'medium', 'Zac'),
                    self::item('future-voice-assessment', 'Explore voice-based assessment', 'Possibility of using voice AI to ask fixed questions verbally while preserving a baseline for comparison.', 'low', 'Zac'),
                    self::item('future-conversational-assessment', 'Consider conversational assessment as a later-stage tool', 'A freer voice conversation risks losing scientific consistency. May work better as a later step, not a replacement for the structured assessment.', 'low', 'Zac'),
                    self::item('future-generational-gap', 'Use the tool to bridge generational workplace gaps', 'Came up as a strong pain point, especially for managers struggling to understand younger workers.', 'medium', 'Zac'),
                    self::item('future-marketing-coaching', 'Focus marketing on coaching and manager effectiveness', 'The stronger value may be helping managers coach people better, not just helping companies hire better.', 'medium', 'Zac'),
                    self::item('future-target-complex-teams', 'Target companies with enough team complexity', 'Best fit may be companies with 10+ employees, several managers, active team dynamics, white-collar or advisory environments.', 'medium', 'Zac'),
                ],
            ],
        ];
    }

    private static function get_empty_roadmap_data()
    {
        $sections = [];

        foreach (self::get_sections_schema() as $cat => $meta) {
            $sections[] = [
                'cat' => $cat,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'items' => [],
            ];
        }

        return $sections;
    }

    private static function item($id, $title, $desc, $priority = 'medium', $owner = '', $status = 'todo', $due_date = '')
    {
        $now = current_time('mysql');

        return [
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'status' => $status,
            'priority' => $priority,
            'owner' => $owner,
            'dueDate' => $due_date,
            'completionNotes' => '',
            'attachments' => [],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }
}
