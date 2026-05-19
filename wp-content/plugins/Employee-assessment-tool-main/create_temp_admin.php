<?php
// Load WordPress (adjust path for plugin dir)
require_once(__DIR__ . '/../../../../wp-load.php');

$username = 'temp_qa_admin';
$password = 'password123';
$email = 'temp_qa_admin@example.com';

// Check if user exists
$user = get_user_by('login', $username);

if (!$user) {
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        echo "Error creating user: " . $user_id->get_error_message();
        exit;
    }
    $user = get_user_by('id', $user_id);
} else {
    wp_set_password($password, $user->ID);
}

// Make admin
$user->set_role('administrator');

echo "Admin user configured: {$username} / {$password}";
