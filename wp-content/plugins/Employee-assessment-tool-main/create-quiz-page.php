<?php
require_once('../../../wp-load.php');

$slug = 'strain-index-quiz';
$title = 'Strain Index Assessment';
$content = '[strain_index_quiz]';

$page = get_page_by_path($slug);
if ($page) {
    echo "Page exists: " . get_permalink($page->ID) . "\n";
} else {
    $page_id = wp_insert_post([
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page'
    ]);
    echo "Page created: " . get_permalink($page_id) . "\n";
}
