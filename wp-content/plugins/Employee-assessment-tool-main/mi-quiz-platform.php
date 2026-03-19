<?php
/*
Plugin Name: Micro-Coach Quiz Platform
Description: A modular platform for hosting various quizzes with AI-powered insights and advanced caching.
Version: 1.2.1
Author: Your Name
License: GPL2
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Network: false
*/

if (!defined('ABSPATH'))
    exit;

/**
 * This is the main plugin file. It is responsible for loading all
 * components in the correct order.
 */

// Define constants
define('MC_QUIZ_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('MC_QUIZ_PLATFORM_VERSION', '1.3.3');
define('MC_QUIZ_PLATFORM_DB_VERSION', '1.2');

// Include the Composer autoloader for PHP libraries like Dompdf.
if (file_exists(MC_QUIZ_PLATFORM_PATH . 'vendor/autoload.php')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'vendor/autoload.php';
}

// Load utility classes first
if (!class_exists('MC_Security')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-security.php';
}
if (!class_exists('MC_Cache')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-cache.php';
}
if (!class_exists('MC_DB_Migration')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-db-migration.php';
}
if (!class_exists('MC_Helpers')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-helpers.php';
}
if (!class_exists('MC_Funnel')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-funnel.php';
}
if (!class_exists('MC_User_Profile')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-user-profile.php';
}
if (!class_exists('MC_Landing_Pages')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-landing-pages.php';
}
if (!class_exists('MC_Employer_Onboarding')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-employer-onboarding.php';
}
if (!class_exists('MC_Quiz_Dashboard')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-quiz-dashboard.php';
}
if (!class_exists('MC_Employer_Dashboard')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-employer-dashboard.php';
}
if (!class_exists('MC_Login_Customizer')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-login-customizer.php';
}
if (!class_exists('MC_Roles')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-roles.php';
}
if (!class_exists('MC_Super_Admin')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-super-admin.php';
}
if (!class_exists('MC_Strain_Index_Scorer')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-strain-index-scorer.php';
}
if (!class_exists('MC_Magic_Login')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-magic-login.php';
}
if (!class_exists('MC_User_Switcher')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-user-switcher.php';
}
if (!class_exists('MC_Report_Template')) {
    require_once MC_QUIZ_PLATFORM_PATH . 'includes/class-mc-report-template.php';
}

// Include all the necessary class files.
// These files should ONLY define classes, not run any code themselves.
require_once MC_QUIZ_PLATFORM_PATH . 'micro-coach-core.php';
require_once MC_QUIZ_PLATFORM_PATH . 'micro-coach-ai.php';
require_once MC_QUIZ_PLATFORM_PATH . 'micro-coach-ai-lab.php';
require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/module.php';
require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/module.php';
require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/module.php';


// Load admin activation helper for Johari MI
if (is_admin()) {
    require_once MC_QUIZ_PLATFORM_PATH . 'admin-activate.php';
    require_once MC_QUIZ_PLATFORM_PATH . 'admin-testing-page.php';

    add_action('admin_menu', function () {
        add_submenu_page(
            'mc-super-admin',
            'Admin Testing',
            'Admin Testing',
            'manage_options',
            'admin-testing-page',
            'mc_render_admin_testing_page'
        );
    });
}

/**
 * The main function to initialize the entire quiz platform.
 * This ensures all classes are loaded before we try to use them.
 */
function mc_quiz_platform_init()
{
    // Run database migrations if needed
    if (is_admin()) {
        MC_DB_Migration::maybe_migrate();
    }

    // Initialize user profile management
    MC_User_Profile::init();

    // Instantiate the core platform and AI services.
    new Micro_Coach_Core();
    new Micro_Coach_AI();
    new Micro_Coach_AI_Lab();

    // Instantiate each quiz module.
    new MI_Quiz_Plugin_AI();
    new CDT_Quiz_Plugin();
    new Bartle_Quiz_Plugin();


    // Initialize landing pages
    MC_Landing_Pages::init();

    // Initialize onboarding
    MC_Employer_Onboarding::init();
    MC_Quiz_Dashboard::init();
    MC_Employer_Dashboard::init();
    MC_Login_Customizer::init();
    MC_Roles::init();
    MC_Magic_Login::init();

    // Initialize Super Admin Dashboard
    if (is_admin() && current_user_can('manage_options')) {
        new MC_Super_Admin();
    }

    // Ensure user registration is enabled for the platform to work
    if (!get_option('users_can_register')) {
        update_option('users_can_register', 1);
    }
}
add_action('plugins_loaded', 'mc_quiz_platform_init');


/**
 * Activation hook for the entire platform.
 */
function mc_quiz_platform_activate()
{
    // Run activation tasks for each module if they exist.
    if (method_exists('MI_Quiz_Plugin_AI', 'activate')) {
        MI_Quiz_Plugin_AI::activate();
    }
    if (method_exists('CDT_Quiz_Plugin', 'activate')) {
        CDT_Quiz_Plugin::activate();
    }
    if (method_exists('Bartle_Quiz_Plugin', 'activate')) {
        Bartle_Quiz_Plugin::activate();
    }


    // Define pages to create
    $pages_to_create = [
        'employer-landing' => [
            'title' => 'Employer Assessment Platform',
            'content' => '[mc_employer_landing]',
            'shortcode' => 'mc_employer_landing'
        ],
        'employee-landing' => [
            'title' => 'Employee Assessment Portal',
            'content' => '[mc_employee_landing]',
            'shortcode' => 'mc_employee_landing'
        ],
        'employer-onboarding' => [
            'title' => 'Employer Onboarding',
            'content' => '[mc_employer_onboarding]',
            'shortcode' => 'mc_employer_onboarding'
        ],
        'employer-dashboard' => [
            'title' => 'Employer Dashboard',
            'content' => '[mc_employer_dashboard]',
            'shortcode' => 'mc_employer_dashboard'
        ],
        'quiz-dashboard' => [
            'title' => 'My Assessment Dashboard',
            'content' => '[quiz_dashboard]',
            'shortcode' => 'quiz_dashboard'
        ],
        'mi-quiz' => [
            'title' => 'Multiple Intelligences Assessment',
            'content' => '[mi_quiz]',
            'shortcode' => 'mi_quiz'
        ],
        'cdt-quiz' => [
            'title' => 'Growth Strengths Assessment',
            'content' => '[cdt_quiz]',
            'shortcode' => 'cdt_quiz'
        ],
        'bartle-quiz' => [
            'title' => 'Core Motivation Assessment',
            'content' => '[bartle_quiz]',
            'shortcode' => 'bartle_quiz'
        ],

    ];

    foreach ($pages_to_create as $slug => $data) {
        $existing = get_page_by_path($slug);
        if (!$existing) {
            // Check if page exists by title to avoid duplicates if slug changed
            $page_check = get_page_by_title($data['title']);
            if (!$page_check) {
                wp_insert_post([
                    'post_title' => $data['title'],
                    'post_name' => $slug,
                    'post_content' => $data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'meta_input' => [
                        '_wp_page_template' => 'elementor_canvas'
                    ]
                ]);
            }
        }
    }

    update_option('mc_pages_created_v2', true);

    // Run role migration once
    if (!get_option('mc_roles_migrated_v1')) {
        if (class_exists('MC_Roles')) {
            MC_Roles::register_roles(); // Register immediately for use
            MC_Roles::migrate_users();
            update_option('mc_roles_migrated_v1', true);
        }
    }
}
register_activation_hook(__FILE__, 'mc_quiz_platform_activate');

/**
 * Forces Elementor to render our quiz shortcodes inside its Shortcode widget.
 * Some themes/plugins interfere with Elementor's shortcode processing; this
 * filter ensures our shortcodes are executed reliably.
 *
 * @param string $widget_content The HTML content of the widget.
 * @param \Elementor\Widget_Base $widget The widget instance.
 * @return string The processed content.
 */
function mc_force_render_quiz_shortcodes_in_elementor($widget_content, $widget)
{
    if (is_object($widget) && method_exists($widget, 'get_name') && 'shortcode' === $widget->get_name()) {
        $content = (string) $widget_content;
        $shortcodes = [
            'quiz_dashboard',
            'mi_quiz',
            'mi-quiz',
            'cdt_quiz',
            'cdt-quiz',
            'bartle_quiz',
            'bartle-quiz',

        ];
        foreach ($shortcodes as $sc) {
            if (has_shortcode($content, $sc)) {
                return do_shortcode($content);
            }
        }
    }
    return $widget_content;
}
add_filter('elementor/widget/render_content', 'mc_force_render_quiz_shortcodes_in_elementor', 11, 2);

/**
 * Enqueue styles for landing pages and dashboard.
 */
function mc_enqueue_landing_page_styles()
{
    wp_enqueue_style('mc-landing-pages', plugins_url('assets/landing-pages.css', __FILE__), [], '1.0.5');

    // Enqueue employer dashboard styles on dashboard page
    if (is_page() && has_shortcode(get_post()->post_content, 'mc_employer_dashboard')) {
        wp_enqueue_style('mc-employer-dashboard', plugins_url('assets/employer-dashboard.css', __FILE__), [], MC_QUIZ_PLATFORM_VERSION);
    }
}
add_action('wp_enqueue_scripts', 'mc_enqueue_landing_page_styles');



