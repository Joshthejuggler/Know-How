<?php
// Adjust path to wp-load.php assuming we are in wp-content/plugins/Employee-assessment-tool-main
require_once(dirname(__FILE__) . '/../../../../wp-load.php');

if (!is_user_logged_in()) {
    // If running from CLI or without session, try to get user by ID 1 or specific user if known, 
    // but for now let's just list all users with this meta if not logged in.
    echo "Not logged in. Listing users with cdt_quiz_results:\n";
    $users = get_users(['meta_key' => 'cdt_quiz_results']);
    foreach ($users as $user) {
        echo "User ID: " . $user->ID . " (" . $user->user_login . ")\n";
        $res = get_user_meta($user->ID, 'cdt_quiz_results', true);
        print_r($res);
        echo "\n----------------\n";
    }
    exit;
}

$user_id = get_current_user_id();
$meta_key = 'cdt_quiz_results';
$results = get_user_meta($user_id, $meta_key, true);

echo "User ID: " . $user_id . "\n";
echo "Meta Key: " . $meta_key . "\n";
echo "Raw Results:\n";
var_dump($results);

if ($results) {
    echo "\nJSON Encoded Results:\n";
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo "\nNo results found for this user.\n";
}
