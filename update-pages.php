<?php
require_once('../../../wp-load.php');

$pages = get_posts(['post_type' => 'page', 'posts_per_page' => -1]);
foreach($pages as $p) {
    if ($p->post_name === 'mi-quiz' || $p->post_name === 'cdt-quiz' || $p->post_name === 'bartle-quiz') {
        echo "PAGE: " . $p->post_title . "\n";
        echo $p->post_content . "\n";
        echo "------------------\n";
    }
}
echo "DONE";
