<?php
// Temporary login helper
require_once __DIR__ . '/../../../wp-load.php';

// Check for existing employer
$users = get_users(['role' => 'mc_employer', 'number' => 1]);
$user = null;

if (!empty($users)) {
    $user = $users[0];
    echo "Found existing employer: " . $user->user_login . "<br>";
} else {
    // Create one
    $username = 'employer_temp_' . time();
    $password = 'password123';
    $email = $username . '@example.com';
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        die('Failed to create user: ' . $user_id->get_error_message());
    }
    $user = get_user_by('id', $user_id);
    $user->set_role('mc_employer');
    echo "Created new employer: " . $username . "<br>";
}

// Log in
wp_set_current_user($user->ID, $user->user_login);
wp_set_auth_cookie($user->ID);
do_action('wp_login', $user->user_login, $user);

echo "Logged in as " . $user->user_login . "! Redirecting to dashboard...";

// Redirect
wp_redirect(home_url('/employer-dashboard/'));
exit;
