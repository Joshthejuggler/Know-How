<?php
require_once('../../../wp-load.php');

// Create test user if not exists
$email = 'magic_test@example.com';
$user = get_user_by('email', $email);
if (!$user) {
    $user_id = wp_create_user('magic_test', 'password123', $email);
    $user = get_userdata($user_id);
    $user->add_role('mc_employer');
}

// Generate Magic Link
if (class_exists('MC_Magic_Login')) {
    $link = MC_Magic_Login::generate_magic_link($user->ID);
    echo "Magic Link: " . $link . "\n";
} else {
    echo "MC_Magic_Login class not found.\n";
}
