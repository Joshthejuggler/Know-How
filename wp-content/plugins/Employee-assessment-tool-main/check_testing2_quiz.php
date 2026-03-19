<?php
/**
 * Diagnostic script to check Testing2's MI quiz status
 * Run this at: http://employee-assessment-tool.local/wp-content/plugins/Employee-assessment-tool-main/check_testing2_quiz.php
 */

require_once('../../../../../wp-load.php');

// Check if Testing2 (user_id 46) has saved MI quiz results
$user_id = 46; // Testing2
$saved_results = get_user_meta($user_id, 'miq_quiz_results', true);

echo "<h2>MI Quiz Status for Testing2 (User ID: {$user_id})</h2>";

if ($saved_results) {
    echo "<p style='color:red;'><strong>❌ QUIZ ALREADY COMPLETED</strong></p>";
    echo "<p>This is why the quiz page appears blank - the user has already taken the quiz.</p>";
    echo "<h3>Saved Results:</h3>";
    echo "<pre>" . print_r($saved_results, true) . "</pre>";

    echo "<h3>Options:</h3>";
    echo "<ol>";
    echo "<li><strong>Delete results:</strong> <a href='?delete=1&user_id={$user_id}'>Click here to delete Testing2's MI quiz results</a></li>";
    echo "<li><strong>View results:</strong> The quiz should show results instead of blank page</li>";
    echo "</ol>";
} else {
    echo "<p style='color:green;'><strong>✅ NO SAVED RESULTS</strong></p>";
    echo "<p>Testing2 has not completed the MI quiz yet. The quiz should be visible.</p>";
}

// Handle delete request
if (isset($_GET['delete']) && $_GET['delete'] == '1' && isset($_GET['user_id'])) {
    $uid = intval($_GET['user_id']);
    delete_user_meta($uid, 'miq_quiz_results');
    echo "<p style='background:yellow;padding:10px;'><strong>✅ Deleted MI quiz results for user {$uid}</strong></p>";
    echo "<p><a href='?'>Refresh to verify</a></p>";
}
