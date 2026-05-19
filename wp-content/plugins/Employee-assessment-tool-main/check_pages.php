<?php
require_once('../../../../../wp-load.php');

$pages = get_pages();
foreach ($pages as $page) {
    echo "ID: " . $page->ID . "\n";
    echo "Title: " . $page->post_title . "\n";
    echo "Slug: " . $page->post_name . "\n";
    echo "Content: " . $page->post_content . "\n";
    echo "--------------------------------\n";
}
