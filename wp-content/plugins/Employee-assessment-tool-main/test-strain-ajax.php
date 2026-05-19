<?php
require_once('../../../wp-load.php');

// Login as user
$user = get_user_by('email', 'magic_test@example.com');
if (!$user) {
    die("User not found");
}
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID);

$url = admin_url('admin-ajax.php');
$responses = [];
for ($i = 1; $i <= 30; $i++) {
    $responses[$i] = rand(1, 5);
}

$args = [
    'body' => [
        'action' => 'strain_index_complete',
        'nonce' => wp_create_nonce('strain_index_nonce'),
        'responses' => json_encode($responses)
    ],
    'cookies' => $_COOKIE
];

// We need to send the cookie we just set.
// Since wp_set_auth_cookie sets a cookie in the header, but wp_remote_post makes a new request,
// we need to manually pass the cookie.
// Actually, wp_remote_post from the same server might not share the session easily without grabbing the cookie.
// A better way is to instantiate the class and call the method directly, but that doesn't test the AJAX hook.

// Let's try calling the method directly first to verify the logic.
if (class_exists('Strain_Index_Quiz_Module')) {
    echo "Class exists.\n";
    // We can't easily call the method because it checks $_POST.
    // We can mock $_POST.
    $_POST['responses'] = json_encode($responses);

    // Create instance (it's already created in init, but we can create another or access it if it was a singleton, which it isn't)
    $module = new Strain_Index_Quiz_Module();

    // Capture output
    ob_start();
    $module->ajax_complete_assessment();
    $output = ob_get_clean();

    echo "Output: " . $output . "\n";
} else {
    echo "Class not found.\n";
}
