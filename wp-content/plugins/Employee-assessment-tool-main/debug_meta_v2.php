<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$users = get_users(['number' => 5, 'orderby' => 'ID', 'order' => 'DESC']);

foreach ($users as $u) {
    echo "ID: " . $u->ID . " | Email: " . $u->user_email . " | Roles: " . implode(',', $u->roles) . "\n";
    $meta = get_user_meta($u->ID, 'miq_quiz_results', true);
    echo "Meta 'miq_quiz_results' (" . gettype($meta) . "): ";
    var_dump($meta);
    echo "--------------------------\n";
}
