<?php
// Debug script to check user preferences table state
require_once(__DIR__ . '/../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;
$table = $wpdb->prefix . 'mc_lab_user_preferences';
$user_id = get_current_user_id();

echo "<h2>User Preferences Debug</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>Table:</strong> $table</p>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
echo "<p><strong>Table exists:</strong> " . ($table_exists ? 'Yes' : 'No') . "</p>";

if ($table_exists) {
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $structure = $wpdb->get_results("DESCRIBE $table");
    echo "<pre>";
    foreach ($structure as $col) {
        echo $col->Field . " | " . $col->Type . " | " . $col->Null . " | " . $col->Default . "\n";
    }
    echo "</pre>";
    
    // Check current user's preferences
    echo "<h3>Current User Preferences:</h3>";
    $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE user_id = %d", $user_id), ARRAY_A);
    if ($current) {
        echo "<pre>";
        print_r($current);
        echo "</pre>";
    } else {
        echo "<p>No preferences found for current user.</p>";
    }
    
    // Show all preferences
    echo "<h3>All User Preferences:</h3>";
    $all = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
    echo "<pre>";
    print_r($all);
    echo "</pre>";
} else {
    echo "<p>Creating tables...</p>";
    
    // Show current WordPress database error reporting
    echo "<p><strong>WordPress DB Error Reporting:</strong> " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "</p>";
    echo "<p><strong>Current DB Error:</strong> " . ($wpdb->last_error ?: 'None') . "</p>";
    
    // Check if required WP functions are available
    echo "<p><strong>dbDelta function available:</strong> " . (function_exists('dbDelta') ? 'Yes' : 'No') . "</p>";
    
    // Try to create the tables
    try {
        $lab_mode = new Micro_Coach_AI_Lab();
        $lab_mode->maybe_create_tables();
    } catch (Exception $e) {
        echo "<p><strong>Exception during table creation:</strong> " . $e->getMessage() . "</p>";
    }
    
    echo "<p><strong>DB Error after table creation:</strong> " . ($wpdb->last_error ?: 'None') . "</p>";
    
    $table_exists_now = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    echo "<p><strong>Table created:</strong> " . ($table_exists_now ? 'Yes' : 'No') . "</p>";
    
    if (!$table_exists_now) {
        echo "<h3>Manual Table Creation Test</h3>";
        
        // Try creating the table manually with simpler SQL
        $charset = $wpdb->get_charset_collate();
        $simple_sql = "CREATE TABLE IF NOT EXISTS `$table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            contexts LONGTEXT NOT NULL DEFAULT '{}',
            risk_bias DECIMAL(3,2) DEFAULT 0.00,
            solo_group_bias DECIMAL(3,2) DEFAULT 0.00,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset;";
        
        echo "<p><strong>Trying manual creation with SQL:</strong></p>";
        echo "<pre>" . htmlspecialchars($simple_sql) . "</pre>";
        
        $result = $wpdb->query($simple_sql);
        echo "<p><strong>Manual creation result:</strong> " . ($result !== false ? 'Success' : 'Failed') . "</p>";
        echo "<p><strong>DB Error after manual creation:</strong> " . ($wpdb->last_error ?: 'None') . "</p>";
        
        $table_exists_after_manual = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        echo "<p><strong>Table exists after manual creation:</strong> " . ($table_exists_after_manual ? 'Yes' : 'No') . "</p>";
        
        // Test database connection
        echo "<h3>Database Connection Test</h3>";
        $test_query = $wpdb->get_var("SELECT 1");
        echo "<p><strong>Basic query test:</strong> " . ($test_query === '1' ? 'Success' : 'Failed') . "</p>";
        
        // Show database info
        echo "<p><strong>DB Name:</strong> " . DB_NAME . "</p>";
        echo "<p><strong>Table Prefix:</strong> " . $wpdb->prefix . "</p>";
        echo "<p><strong>Charset Collate:</strong> " . $charset . "</p>";
    }
}
