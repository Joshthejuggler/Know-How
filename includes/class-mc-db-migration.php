<?php
/**
 * Database Migration Utilities for Micro-Coach Platform
 * 
 * @package MicroCoach
 * @since 1.2.0
 */

if (!defined('ABSPATH'))
    exit;

class MC_DB_Migration
{

    const OPTION_KEY = 'mc_db_version';

    /**
     * Run database migrations if needed
     */
    public static function maybe_migrate()
    {
        $current_version = get_option(self::OPTION_KEY, '1.0');
        $target_version = MC_QUIZ_PLATFORM_DB_VERSION;

        // Force check for Strain Index table
        global $wpdb;
        $strain_table = $wpdb->prefix . 'mc_strain_index_results';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$strain_table'") === $strain_table;

        if (version_compare($current_version, $target_version, '<') || !$table_exists) {
            self::run_migrations($current_version, $target_version);
        }
    }

    /**
     * Run migrations from current version to target version
     * 
     * @param string $from_version Current version
     * @param string $to_version Target version
     */
    private static function run_migrations($from_version, $to_version)
    {
        global $wpdb;

        // Migration to 1.1 - Add database indexes
        if (version_compare($from_version, '1.1', '<')) {
            self::migrate_to_1_1();
            update_option(self::OPTION_KEY, '1.1');
        }

        // Migration to 1.2 - Add Strain Index table
        if (version_compare($from_version, '1.2', '<')) {
            self::create_tables();
            update_option(self::OPTION_KEY, '1.2');
        }

        update_option(self::OPTION_KEY, $to_version);
    }

    /**
     * Migration to version 1.1 - Add database indexes
     */
    private static function migrate_to_1_1()
    {
        global $wpdb;

        // Add indexes to MI Quiz subscribers table
        $subscribers_table = $wpdb->prefix . 'miq_subscribers';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$subscribers_table}'") === $subscribers_table) {
            // Check if indexes don't already exist
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$subscribers_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_email', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$subscribers_table} ADD INDEX idx_email (email)");
            }
            if (!in_array('idx_created_at', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$subscribers_table} ADD INDEX idx_created_at (created_at)");
            }
        }

        // Add indexes to Lab Mode tables
        $experiments_table = $wpdb->prefix . 'mc_lab_experiments';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$experiments_table}'") === $experiments_table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$experiments_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_user_status', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$experiments_table} ADD INDEX idx_user_status (user_id, status)");
            }
            if (!in_array('idx_created_at', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$experiments_table} ADD INDEX idx_created_at (created_at)");
            }
        }

        $feedback_table = $wpdb->prefix . 'mc_lab_feedback';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$feedback_table}'") === $feedback_table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$feedback_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_experiment_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$feedback_table} ADD INDEX idx_experiment_id (experiment_id)");
            }
            if (!in_array('idx_user_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$feedback_table} ADD INDEX idx_user_id (user_id)");
            }
        }

        $preferences_table = $wpdb->prefix . 'mc_lab_user_preferences';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$preferences_table}'") === $preferences_table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$preferences_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_user_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$preferences_table} ADD INDEX idx_user_id (user_id)");
            }
        }

        // Add indexes to AI tables if they exist
        $ai_experiments_table = $wpdb->prefix . 'mc_ai_experiments';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$ai_experiments_table}'") === $ai_experiments_table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$ai_experiments_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_user_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$ai_experiments_table} ADD INDEX idx_user_id (user_id)");
            }
            if (!in_array('idx_created_at', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$ai_experiments_table} ADD INDEX idx_created_at (created_at)");
            }
        }

        $ai_feedback_table = $wpdb->prefix . 'mc_ai_feedback';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$ai_feedback_table}'") === $ai_feedback_table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$ai_feedback_table}");
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_experiment_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$ai_feedback_table} ADD INDEX idx_experiment_id (experiment_id)");
            }
            if (!in_array('idx_user_id', $existing_indexes)) {
                $wpdb->query("ALTER TABLE {$ai_feedback_table} ADD INDEX idx_user_id (user_id)");
            }
        }

        error_log('MC Platform: Database migration to v1.1 completed - Added indexes');
    }

    /**
     * Create all necessary database tables with proper indexes
     */
    public static function create_tables()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // MI Quiz subscribers table
        $subscribers_table = $wpdb->prefix . 'miq_subscribers';
        $sql = "CREATE TABLE IF NOT EXISTS {$subscribers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) DEFAULT '',
            email VARCHAR(190) NOT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql);

        // Lab Mode experiments table
        $experiments_table = $wpdb->prefix . 'mc_lab_experiments';
        $sql = "CREATE TABLE IF NOT EXISTS {$experiments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            experiment_data LONGTEXT NOT NULL,
            profile_data LONGTEXT DEFAULT NULL,
            archetype VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'Draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_status (user_id, status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql);

        // Lab Mode feedback table
        $feedback_table = $wpdb->prefix . 'mc_lab_feedback';
        $sql = "CREATE TABLE IF NOT EXISTS {$feedback_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            experiment_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            difficulty TINYINT NOT NULL,
            fit TINYINT NOT NULL,
            learning TINYINT NOT NULL,
            notes TEXT DEFAULT NULL,
            next_action VARCHAR(20) NOT NULL,
            evolve_notes TEXT DEFAULT NULL,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_experiment_id (experiment_id),
            KEY idx_user_id (user_id),
            FOREIGN KEY (experiment_id) REFERENCES {$experiments_table}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);

        // Lab Mode user preferences table
        $preferences_table = $wpdb->prefix . 'mc_lab_user_preferences';
        $sql = "CREATE TABLE IF NOT EXISTS {$preferences_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            contexts TEXT DEFAULT NULL,
            risk_bias DECIMAL(3,2) DEFAULT 0.00,
            solo_group_bias DECIMAL(3,2) DEFAULT 0.00,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_id (user_id)
        ) {$charset_collate};";
        dbDelta($sql);

        // AI experiments table (if AI module is active)
        if (class_exists('Micro_Coach_AI')) {
            $ai_experiments_table = $wpdb->prefix . 'mc_ai_experiments';
            $sql = "CREATE TABLE IF NOT EXISTS {$ai_experiments_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_created_at (created_at)
            ) {$charset_collate};";
            dbDelta($sql);

            $ai_feedback_table = $wpdb->prefix . 'mc_ai_feedback';
            $sql = "CREATE TABLE IF NOT EXISTS {$ai_feedback_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                experiment_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                rating TINYINT NOT NULL,
                feedback TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_experiment_id (experiment_id),
                KEY idx_user_id (user_id)
            ) {$charset_collate};";
            dbDelta($sql);
        }

        // Strain Index Results Table
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
        dbDelta($sql);

        // Update database version after creating tables
        update_option(self::OPTION_KEY, MC_QUIZ_PLATFORM_DB_VERSION);
    }

    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'miq_subscribers',
            $wpdb->prefix . 'mc_lab_experiments',
            $wpdb->prefix . 'mc_lab_feedback',
            $wpdb->prefix . 'mc_lab_user_preferences',
            $wpdb->prefix . 'mc_ai_experiments',
            $wpdb->prefix . 'mc_ai_feedback'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::OPTION_KEY);
    }

    /**
     * Get database status information
     * 
     * @return array Database status
     */
    public static function get_db_status()
    {
        global $wpdb;

        $status = [
            'version' => get_option(self::OPTION_KEY, '1.0'),
            'target_version' => MC_QUIZ_PLATFORM_DB_VERSION,
            'tables' => []
        ];

        $expected_tables = [
            $wpdb->prefix . 'miq_subscribers',
            $wpdb->prefix . 'mc_lab_experiments',
            $wpdb->prefix . 'mc_lab_feedback',
            $wpdb->prefix . 'mc_lab_user_preferences'
        ];

        foreach ($expected_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            $status['tables'][$table] = [
                'exists' => $exists,
                'row_count' => $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0
            ];
        }

        return $status;
    }
}