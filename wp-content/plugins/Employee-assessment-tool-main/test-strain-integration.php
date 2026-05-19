<?php
// Load WordPress
require_once 'wp-load.php';

// Create a test user
$user_id = wp_create_user('strain_test_' . time(), 'password', 'strain_test_' . time() . '@example.com');
echo "Created Test User ID: $user_id\n";

// Simulate MI Results
// 4 Rumination, 3 Avoidance, 3 Flood
// Let's give max scores (5) to all to check max calculation
$mi_scores = [
    'naturalistic' => 20,
    'si-rumination' => 4 * 5, // 20
    'si-avoidance' => 3 * 5,  // 15
    'si-emotional-flood' => 3 * 5 // 15
];
update_user_meta($user_id, 'miq_quiz_results', ['scores' => $mi_scores]);
echo "Saved MI Results\n";

// Simulate CDT Results
// 3 Rumination, 4 Avoidance, 3 Flood
$cdt_scores = [
    'ambiguity-tolerance' => 20,
    'si-rumination' => 3 * 5, // 15
    'si-avoidance' => 4 * 5,  // 20
    'si-emotional-flood' => 3 * 5 // 15
];
update_user_meta($user_id, 'cdt_quiz_results', ['scores' => $cdt_scores]);
echo "Saved CDT Results\n";

// Simulate Bartle Results
// 4 Rumination, 3 Avoidance, 3 Flood
$bartle_scores = [
    'explorer' => 20,
    'si-rumination' => 4 * 5, // 20
    'si-avoidance' => 3 * 5,  // 15
    'si-emotional-flood' => 3 * 5 // 15
];
update_user_meta($user_id, 'bartle_quiz_results', ['scores' => $bartle_scores]);
echo "Saved Bartle Results\n";

// Trigger Calculation
if (class_exists('MC_Strain_Index_Scorer')) {
    echo "Triggering Scorer...\n";
    $result = MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);

    if ($result) {
        echo "Calculation Successful!\n";
        print_r($result);

        // Verify DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'mc_strain_index_results';
        $db_row = $wpdb->get_row("SELECT * FROM $table_name WHERE user_id = $user_id");
        if ($db_row) {
            echo "DB Insert Verified!\n";
            echo "Overall Strain in DB: " . $db_row->overall_strain . "\n";
        } else {
            echo "DB Insert FAILED!\n";
        }
    } else {
        echo "Calculation Failed (returned false)\n";
    }
} else {
    echo "Scorer Class Not Found!\n";
}

// Cleanup
wp_delete_user($user_id);
echo "Deleted Test User\n";
