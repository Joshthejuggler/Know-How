<?php
require_once('../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'mc_strain_index_results';

echo "<pre>\n";
// Check DB Version
echo "DB Version: " . get_option('mc_db_version') . "\n";
echo "Checking Table: $table\n";

// List all tables
$tables = $wpdb->get_results("SHOW TABLES");
echo "Tables found:\n";
foreach ($tables as $t) {
    echo reset($t) . "\n";
}

// Check if table exists
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
echo "Table Exists: " . ($exists ? "YES" : "NO") . "\n";

if ($exists) {
    // Check for latest result
    $result = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");
    if ($result) {
        echo "Latest Result ID: " . $result->id . "\n";
        echo "Overall Strain: " . $result->overall_strain . "\n";
        echo "Raw Rumination: " . $result->raw_rumination . "\n";
    } else {
        echo "No results found yet.\n";
    }
}
echo "</pre>";
