<?php
// Debug script to check JMI database data for User 1
// Run this from WordPress admin or via WP-CLI

// Ensure we're in WordPress context
if (!defined('ABSPATH')) {
    // If running from command line, load WordPress
    require_once __DIR__ . '/../../../wp-load.php';
}

global $wpdb;

$user_id = 1; // Admin user
$self_uuid = get_user_meta($user_id, 'jmi_self_uuid', true);

echo "=== JMI Debug for User $user_id ===\n";
echo "self_uuid from meta: " . ($self_uuid ?: 'NULL') . "\n\n";

// Check self table
$self_table = $wpdb->prefix . 'jmi_self';
$self_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$self_table` WHERE user_id = %d", $user_id));
echo "Self table entries for user $user_id:\n";
if (empty($self_rows)) {
    echo "  - None found\n";
} else {
    foreach ($self_rows as $row) {
        echo "  - ID: {$row->id}, UUID: {$row->uuid}, Created: {$row->created_at}\n";
    }
}

// If we have a self_uuid from meta, check if it exists in database
if ($self_uuid) {
    $specific_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$self_table` WHERE uuid = %s", $self_uuid));
    echo "\nSpecific row for meta UUID ($self_uuid):\n";
    if ($specific_row) {
        echo "  - Found: ID={$specific_row->id}, user_id={$specific_row->user_id}\n";
        
        // Check peer links for this assessment
        $links_table = $wpdb->prefix . 'jmi_peer_links';
        $links = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$links_table` WHERE self_id = %d", $specific_row->id));
        echo "  - Peer links:\n";
        if (empty($links)) {
            echo "    - None found\n";
        } else {
            foreach ($links as $link) {
                $expired = strtotime($link->expires_at) < time() ? ' (EXPIRED)' : ' (ACTIVE)';
                echo "    - UUID: {$link->uuid}, Expires: {$link->expires_at}{$expired}\n";
            }
        }
        
        // Check peer feedback
        $feedback_table = $wpdb->prefix . 'jmi_peer_feedback';
        $feedback_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$feedback_table` WHERE self_id = %d", $specific_row->id));
        echo "  - Peer feedback count: $feedback_count\n";
        
    } else {
        echo "  - NOT FOUND in database!\n";
    }
}

echo "\n=== Raw Database Queries ===\n";
echo "Self table query: SELECT * FROM `$self_table` WHERE user_id = $user_id\n";
if ($self_uuid) {
    echo "Meta UUID query: SELECT * FROM `$self_table` WHERE uuid = '$self_uuid'\n";
}