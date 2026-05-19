<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (function_exists('get_option') && function_exists('wp_delete_attachment')) {
    $sections = get_option('prm_task_data', []);

    if (is_array($sections)) {
        foreach ($sections as $section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $item) {
                if (empty($item['attachments']) || !is_array($item['attachments'])) {
                    continue;
                }

                foreach ($item['attachments'] as $attachment) {
                    $attachment_id = absint($attachment['id'] ?? 0);
                    if ($attachment_id > 0) {
                        wp_delete_attachment($attachment_id, true);
                    }
                }
            }
        }
    }
}

delete_option('prm_task_data');
