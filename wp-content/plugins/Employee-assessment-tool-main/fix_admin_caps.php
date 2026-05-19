<?php
require_once __DIR__ . '/../../../wp-load.php';

$user = get_user_by('login', 'admin');
if (!$user) {
    die('User admin not found');
}

$user->add_role('administrator');
$user->add_cap('install_plugins');
$user->add_cap('activate_plugins');

echo "Updated capabilities for user: " . $user->user_login . "<br>";
echo "Roles: " . implode(', ', $user->roles) . "<br>";
echo '<a href="' . admin_url('plugin-install.php') . '">Go to Plugin Install</a>';
