<?php
/**
 * Admin Activation Script for Johari × MI Quiz
 * 
 * Add this to your WordPress admin by visiting:
 * wp-admin/admin.php?page=admin-activate-johari-mi
 */

// Hook into WordPress admin
add_action('admin_menu', function() {
    add_submenu_page(
        null, // No parent menu (hidden)
        'Activate Johari MI', 
        'Activate Johari MI', 
        'manage_options', 
        'admin-activate-johari-mi',
        'johari_mi_activation_page'
    );
});

function johari_mi_activation_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Johari × MI Quiz Activation</h1>';
    
    // Step 1: Activate the module
    if (class_exists('Johari_MI_Quiz_Module')) {
        echo '<p>✅ <strong>Module class loaded successfully</strong></p>';
        
        try {
            // Call activation method
            Johari_MI_Quiz_Module::activate();
            echo '<p>✅ <strong>Database tables created successfully</strong></p>';
            
            // Verify tables exist
            global $wpdb;
            $tables = [
                $wpdb->prefix . 'jmi_self',
                $wpdb->prefix . 'jmi_peer_links', 
                $wpdb->prefix . 'jmi_peer_feedback',
                $wpdb->prefix . 'jmi_aggregates'
            ];
            
            $all_exist = true;
            foreach ($tables as $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                echo '<p>' . ($exists ? '✅' : '❌') . ' Table: ' . $table . '</p>';
                if (!$exists) $all_exist = false;
            }
            
            if ($all_exist) {
                echo '<p>🎉 <strong>All database tables created successfully!</strong></p>';
            }
            
        } catch (Exception $e) {
            echo '<p>❌ <strong>Error during activation:</strong> ' . esc_html($e->getMessage()) . '</p>';
        }
        
    } else {
        echo '<p>❌ <strong>Module class not found</strong> - check if the plugin files are in the correct location.</p>';
    }
    
    // Step 2: Create quiz page
    echo '<hr><h2>Creating Quiz Page</h2>';
    
    // Check if page already exists
    $existing = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        's' => '[johari_mi_quiz]',
        'numberposts' => 1
    ]);
    
    if ($existing) {
        $page = $existing[0];
        if (has_shortcode($page->post_content, 'johari_mi_quiz')) {
            echo '<p>✅ <strong>Quiz page already exists:</strong> <a href="' . get_permalink($page->ID) . '" target="_blank">' . $page->post_title . '</a></p>';
        }
    } else {
        // Create the page
        $page_data = [
            'post_title'    => 'Johari × MI Assessment',
            'post_content'  => '[johari_mi_quiz]

<h2>About This Assessment</h2>
<p>The Johari × MI assessment combines the powerful insights of the Johari Window with Multiple Intelligence theory to provide a comprehensive peer-feedback experience.</p>

<h3>How It Works</h3>
<ol>
<li><strong>Self-Assessment:</strong> Select 6-10 adjectives that best describe you from our MI-based categories</li>
<li><strong>Peer Feedback:</strong> Share your unique link with 2-5 trusted friends, family, or colleagues</li>
<li><strong>Results:</strong> Discover your Johari Window with adjectives organized into four quadrants</li>
</ol>',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
            'comment_status' => 'closed',
            'ping_status'   => 'closed'
        ];
        
        $page_id = wp_insert_post($page_data);
        
        if (is_wp_error($page_id)) {
            echo '<p>❌ <strong>Error creating page:</strong> ' . $page_id->get_error_message() . '</p>';
        } else {
            // Add meta for caching
            update_post_meta($page_id, '_mc_contains_shortcode_johari_mi_quiz', 1);
            
            $page_url = get_permalink($page_id);
            echo '<p>✅ <strong>Quiz page created successfully!</strong></p>';
            echo '<p><strong>Page URL:</strong> <a href="' . $page_url . '" target="_blank">' . $page_url . '</a></p>';
        }
    }
    
    // Step 3: Clear caches
    echo '<hr><h2>Clearing Caches</h2>';
    
    if (class_exists('MC_Helpers')) {
        MC_Helpers::clear_shortcode_page_cache('johari_mi_quiz');
        echo '<p>✅ <strong>Shortcode cache cleared</strong></p>';
    }
    
    // Clear any transients
    delete_transient('mc_page_url_for_johari_mi_quiz');
    echo '<p>✅ <strong>Page URL transient cleared</strong></p>';
    
    // Step 4: Verify registration
    echo '<hr><h2>Module Registration Status</h2>';
    
    if (class_exists('Micro_Coach_Core')) {
        $quizzes = Micro_Coach_Core::get_quizzes();
        if (isset($quizzes['johari-mi-quiz'])) {
            echo '<p>✅ <strong>Module registered with platform</strong></p>';
            echo '<p><strong>Title:</strong> ' . esc_html($quizzes['johari-mi-quiz']['title']) . '</p>';
            echo '<p><strong>Shortcode:</strong> [' . esc_html($quizzes['johari-mi-quiz']['shortcode']) . ']</p>';
            echo '<p><strong>Order:</strong> ' . intval($quizzes['johari-mi-quiz']['order']) . '</p>';
        } else {
            echo '<p>❌ <strong>Module NOT registered</strong></p>';
            echo '<p>Available quizzes: ' . implode(', ', array_keys($quizzes)) . '</p>';
        }
    }
    
    echo '<hr>';
    echo '<h2>🎉 Activation Complete!</h2>';
    echo '<p><strong>Next Steps:</strong></p>';
    echo '<ol>';
    echo '<li>Visit your <strong>Quiz Dashboard</strong> - the Johari × MI quiz should now be available</li>';
    echo '<li>Test the quiz by clicking on it</li>';
    echo '<li>Complete the self-assessment and generate a peer sharing link</li>';
    echo '</ol>';
    
    echo '<p><strong>Note:</strong> You need at least 2 peer responses to see full Johari Window results.</p>';
    
    echo '</div>';
}

// Auto-run if we're accessing this page directly  
if (isset($_GET['page']) && $_GET['page'] === 'admin-activate-johari-mi') {
    // This will run when the admin page is accessed
}