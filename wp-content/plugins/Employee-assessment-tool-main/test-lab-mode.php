<?php
/**
 * Basic test script to validate Lab Mode implementation
 * Run this from WordPress admin to test core functionality
 */

// Ensure this is only run by administrators
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Test database table creation
global $wpdb;

echo "<h2>Lab Mode Implementation Test</h2>";

// 1. Test class existence
echo "<h3>1. Class Loading</h3>";
if (class_exists('Micro_Coach_AI_Lab')) {
    echo "✅ Micro_Coach_AI_Lab class exists<br>";
} else {
    echo "❌ Micro_Coach_AI_Lab class not found<br>";
}

// 2. Test database tables
echo "<h3>2. Database Tables</h3>";
$lab_tables = [
    'mc_lab_experiments',
    'mc_lab_feedback', 
    'mc_lab_user_preferences'
];

foreach ($lab_tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    
    if ($exists) {
        echo "✅ Table $table exists<br>";
    } else {
        echo "❌ Table $table missing<br>";
    }
}

// 3. Test content libraries
echo "<h3>3. Content Libraries</h3>";
$lib_files = [
    'assets/lab_libraries/mi_combinations.json',
    'assets/lab_libraries/archetype_templates.json'
];

$plugin_path = plugin_dir_path(__FILE__);
foreach ($lib_files as $file) {
    $full_path = $plugin_path . $file;
    if (file_exists($full_path)) {
        $content = json_decode(file_get_contents($full_path), true);
        if ($content) {
            echo "✅ $file exists and valid JSON<br>";
        } else {
            echo "⚠️ $file exists but invalid JSON<br>";
        }
    } else {
        echo "❌ $file missing<br>";
    }
}

// 4. Test feature flag
echo "<h3>4. Feature Flag</h3>";
$lab_enabled = get_option('mc_lab_mode_enabled', '0') === '1';
if ($lab_enabled) {
    echo "✅ Lab Mode enabled<br>";
} else {
    echo "⚠️ Lab Mode disabled (this is normal for fresh installs)<br>";
    echo "Enable it in Quiz Platform → Settings → AI Integration<br>";
}

// 5. Test AI integration
echo "<h3>5. AI Integration</h3>";
if (class_exists('Micro_Coach_AI')) {
    $api_key = Micro_Coach_AI::get_openai_api_key();
    if (!empty($api_key)) {
        echo "✅ OpenAI API key configured<br>";
    } else {
        echo "⚠️ OpenAI API key not configured<br>";
    }
} else {
    echo "❌ Micro_Coach_AI class not found<br>";
}

// 6. Test user capabilities
echo "<h3>6. User Permissions</h3>";
$user_id = get_current_user_id();
if ($user_id) {
    echo "✅ User logged in (ID: $user_id)<br>";
    
    if (current_user_can('edit_posts') || current_user_can('manage_options')) {
        echo "✅ User has Lab Mode access<br>";
    } else {
        echo "❌ User lacks Lab Mode access<br>";
    }
} else {
    echo "❌ User not logged in<br>";
}

// 7. Test assessment data (mock check)
echo "<h3>7. Assessment Data Structure</h3>";
$user_id = get_current_user_id();
$assessments = [
    'miq_quiz_results' => 'MI Quiz',
    'cdt_quiz_results' => 'CDT Quiz',
    'bartle_quiz_results' => 'Bartle Quiz'
];

foreach ($assessments as $meta_key => $name) {
    $results = get_user_meta($user_id, $meta_key, true);
    if (!empty($results)) {
        echo "✅ $name data exists<br>";
    } else {
        echo "⚠️ $name data not found (complete assessments to test)<br>";
    }
}

echo "<h3>Summary</h3>";
echo "<p>Lab Mode implementation is ready for testing. To fully test:</p>";
echo "<ol>";
echo "<li>Enable Lab Mode in Quiz Platform settings</li>";
echo "<li>Configure OpenAI API key</li>";
echo "<li>Complete all assessments (MI, CDT, Bartle)</li>";
echo "<li>Navigate to the dashboard to see the Lab Mode tab</li>";
echo "</ol>";

?>
