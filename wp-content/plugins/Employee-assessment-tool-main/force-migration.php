<?php
require_once('../../../wp-load.php');

if (!class_exists('MC_DB_Migration')) {
    die('MC_DB_Migration not found');
}

echo "Current Version: " . get_option('mc_db_version') . "\n";

// Reset version to force run
update_option('mc_db_version', '1.1');
echo "Reset to 1.1\n";

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$strain_table = $wpdb->prefix . 'mc_strain_index_results';
$sql = "CREATE TABLE IF NOT EXISTS {$strain_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_id VARCHAR(64) DEFAULT NULL,
    raw_rumination INT NOT NULL DEFAULT 0,
    raw_avoidance INT NOT NULL DEFAULT 0,
    raw_emotional_flood INT NOT NULL DEFAULT 0,
    norm_rumination DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    norm_avoidance DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    norm_emotional_flood DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    overall_strain DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    payload_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at)
) {$charset_collate};";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$res = dbDelta($sql);
print_r($res);

if ($wpdb->last_error) {
    echo "DB Error: " . $wpdb->last_error . "\n";
}
