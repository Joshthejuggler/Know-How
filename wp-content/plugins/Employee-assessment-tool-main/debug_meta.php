<?php
require_once('../../../wp-load.php');

$user = get_user_by('email', 'direct@test.com');
if (!$user) {
    echo "User direct@test.com not found.";
} else {
    echo "User ID: " . $user->ID . "\n";
    $meta = get_user_meta($user->ID, 'miq_quiz_results', true);
    echo "Meta 'miq_quiz_results':\n";
    var_dump($meta);
}
