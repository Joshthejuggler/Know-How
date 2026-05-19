<?php
/**
 * Recalculate Strain Index for All Users
 * 
 * This script iterates through all users who have completed the quizzes
 * and forces a recalculation of their Strain Index using the updated Scorer logic.
 * This fixes the "Zero Strain Index" issue for existing data.
 */

// Load WordPress environment
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Please log in as an admin.');
}

echo "<h1>Recalculating Strain Index for All Users</h1>";

// Find users with quiz results
$args = [
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'miq_quiz_results',
            'compare' => 'EXISTS'
        ],
        [
            'key' => 'cdt_quiz_results',
            'compare' => 'EXISTS'
        ],
        [
            'key' => 'bartle_quiz_results',
            'compare' => 'EXISTS'
        ]
    ],
    'fields' => 'ID'
];

$user_query = new WP_User_Query($args);
$users = $user_query->get_results();

if (empty($users)) {
    echo "<p>No users found with all 3 quizzes completed.</p>";
} else {
    echo "<p>Found " . count($users) . " users with completed quizzes.</p>";
    echo "<ul>";

    foreach ($users as $user_id) {
        $user_info = get_userdata($user_id);
        echo "<li>Processing <strong>" . $user_info->display_name . "</strong> (ID: $user_id)... ";

        if (class_exists('MC_Strain_Index_Scorer')) {
            $result = MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
            if ($result) {
                $overall = $result['strain_index']['overall_strain'];
                echo "<span style='color:green'>Success! Overall Strain: " . number_format($overall * 100, 1) . "%</span>";
            } else {
                echo "<span style='color:red'>Failed to calculate.</span>";
            }
        } else {
            echo "<span style='color:red'>Error: Scorer class not found.</span>";
        }
        echo "</li>";
    }
    echo "</ul>";
    echo "<h3>Done! Please check the Employer Dashboard.</h3>";
}
