<?php
/**
 * Debug script to check enhanced flow status
 * Place this in wp-content/plugins/MI/ and access via browser at:
 * yoursite.com/wp-content/plugins/MI/debug-enhanced-flow.php
 */

// Load WordPress
$wp_load_paths = [
    '../../../../wp-load.php',
    '../../../wp-load.php',
    '../../wp-load.php',
    '../wp-load.php'
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Admin access required');
}

$user_id = get_current_user_id();
echo "<h1>Enhanced Flow Debug</h1>";
echo "<h2>User ID: $user_id</h2>";

// Get Bartle results
$bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
echo "<h3>Bartle Quiz Results:</h3>";
echo "<pre>" . print_r($bartle_results, true) . "</pre>";

// Get AI coach unlocked status
$ai_unlocked = get_user_meta($user_id, 'ai_coach_unlocked', true);
echo "<h3>AI Coach Unlocked:</h3>";
echo "<pre>" . ($ai_unlocked ? 'YES' : 'NO') . " (value: " . print_r($ai_unlocked, true) . ")</pre>";

// Check enhanced flow logic
$enhanced = !empty($bartle_results['enhanced_flow']) && empty($ai_unlocked);
echo "<h3>Enhanced Flow Active:</h3>";
echo "<pre>" . ($enhanced ? 'YES' : 'NO') . "</pre>";

// Show constants
require_once 'quizzes/bartle-quiz/module.php';
echo "<h3>Enhanced Flow Start Constant:</h3>";
echo "<pre>" . Bartle_Quiz_Plugin::ENHANCED_FLOW_START . " (" . date('Y-m-d H:i:s', Bartle_Quiz_Plugin::ENHANCED_FLOW_START) . ")</pre>";

echo "<h3>Current Timestamp:</h3>";
echo "<pre>" . time() . " (" . date('Y-m-d H:i:s') . ")</pre>";

// Show if current timestamp would trigger enhanced flow
$would_trigger = time() >= Bartle_Quiz_Plugin::ENHANCED_FLOW_START;
echo "<h3>Would Current Completion Trigger Enhanced Flow:</h3>";
echo "<pre>" . ($would_trigger ? 'YES' : 'NO') . "</pre>";

// Show body class logic
$body_classes = get_body_class();
echo "<h3>Current Body Classes:</h3>";
echo "<pre>" . implode(', ', $body_classes) . "</pre>";

// Check global variable
global $mc_enhanced_flow_active;
echo "<h3>Global Enhanced Flow Variable:</h3>";
echo "<pre>" . ($mc_enhanced_flow_active ? 'SET' : 'NOT SET') . "</pre>";

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<a href='?action=clear_ai_unlock'>Clear AI Unlock Status</a> | ";
echo "<a href='?action=set_enhanced'>Set Enhanced Flow</a> | ";
echo "<a href='?action=clear_bartle'>Clear Bartle Results</a>";

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'clear_ai_unlock':
            delete_user_meta($user_id, 'ai_coach_unlocked');
            echo "<p style='color: green;'>AI Coach unlock status cleared!</p>";
            break;
        case 'set_enhanced':
            $current_bartle = get_user_meta($user_id, 'bartle_quiz_results', true) ?: [];
            $current_bartle['enhanced_flow'] = true;
            $current_bartle['completed_at'] = time();
            update_user_meta($user_id, 'bartle_quiz_results', $current_bartle);
            delete_user_meta($user_id, 'ai_coach_unlocked');
            echo "<p style='color: green;'>Enhanced flow enabled and AI unlock cleared!</p>";
            break;
        case 'clear_bartle':
            delete_user_meta($user_id, 'bartle_quiz_results');
            echo "<p style='color: green;'>Bartle results cleared!</p>";
            break;
    }
    echo "<p><a href='?'>Refresh to see changes</a></p>";
}
?>
