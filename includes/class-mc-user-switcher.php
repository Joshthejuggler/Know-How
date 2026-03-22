<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Custom User Switching functionality.
 * Allows admins to switch to other user accounts and back.
 * Provides full control over redirects based on user roles.
 */
class MC_User_Switcher
{
    const COOKIE_NAME = 'mc_switched_from';
    const COOKIE_ORIGIN_URL = 'mc_switch_origin_url';
    const COOKIE_EXPIRATION = DAY_IN_SECONDS;
    const ACTION_SWITCH = 'mc_switch_user';
    const ACTION_SWITCH_BACK = 'mc_switch_back';

    /**
     * Initialize the user switcher.
     */
    public static function init()
    {
        // Handle switch actions
        add_action('admin_init', [__CLASS__, 'handle_switch_actions'], 1);
        add_action('init', [__CLASS__, 'handle_switch_actions'], 1);

        // Add admin bar menu
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_menu'], 999);

        // Add admin bar styles
        add_action('wp_head', [__CLASS__, 'add_switch_back_styles']);
        add_action('admin_head', [__CLASS__, 'add_switch_back_styles']);
    }

    /**
     * Handle switch user actions.
     */
    public static function handle_switch_actions()
    {
        // Handle switch to user
        if (isset($_GET['action']) && $_GET['action'] === self::ACTION_SWITCH && isset($_GET['user_id'])) {
            self::process_switch_to_user();
        }

        // Handle switch back
        if (isset($_GET['action']) && $_GET['action'] === self::ACTION_SWITCH_BACK) {
            self::process_switch_back();
        }
    }

    /**
     * Process switching to a user.
     */
    private static function process_switch_to_user()
    {
        $user_id = intval($_GET['user_id']);
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, self::ACTION_SWITCH . '_' . $user_id)) {
            wp_die('Security check failed. Please try again.');
        }

        // Only admins can switch
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to switch users.');
        }

        // Get target user
        $target_user = get_userdata($user_id);
        if (!$target_user) {
            wp_die('User not found.');
        }

        // Store original admin ID in cookie
        $current_user_id = get_current_user_id();
        setcookie(
            self::COOKIE_NAME,
            $current_user_id,
            time() + self::COOKIE_EXPIRATION,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        // Store the origin URL (where the admin is switching FROM)
        $origin_url = wp_get_referer();
        if (!$origin_url) {
            // Fallback to current request URL without the switch action params
            $origin_url = admin_url('admin.php?page=mc-super-admin');
        }
        setcookie(
            self::COOKIE_ORIGIN_URL,
            $origin_url,
            time() + self::COOKIE_EXPIRATION,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        // Switch to the target user
        wp_clear_auth_cookie();
        wp_set_auth_cookie($user_id, false);
        wp_set_current_user($user_id);

        // Redirect based on user role
        $redirect_url = self::get_redirect_url_for_user($target_user);
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Process switching back to original admin.
     */
    private static function process_switch_back()
    {
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, self::ACTION_SWITCH_BACK)) {
            wp_die('Security check failed. Please try again.');
        }

        // Get original admin ID from cookie
        $original_admin_id = isset($_COOKIE[self::COOKIE_NAME]) ? intval($_COOKIE[self::COOKIE_NAME]) : 0;

        if (!$original_admin_id) {
            wp_die('No original user to switch back to.');
        }

        // Verify original user exists and is admin
        $original_user = get_userdata($original_admin_id);
        if (!$original_user || !user_can($original_user, 'manage_options')) {
            wp_die('Invalid original user.');
        }

        // Get the origin URL before clearing cookies
        $origin_url = isset($_COOKIE[self::COOKIE_ORIGIN_URL]) ? $_COOKIE[self::COOKIE_ORIGIN_URL] : '';

        // Clear the switch cookies
        setcookie(
            self::COOKIE_NAME,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
        setcookie(
            self::COOKIE_ORIGIN_URL,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        // Switch back to original admin
        wp_clear_auth_cookie();
        wp_set_auth_cookie($original_admin_id, false);
        wp_set_current_user($original_admin_id);

        // Redirect to origin URL if valid, otherwise Super Admin Dashboard
        if (!empty($origin_url) && wp_validate_redirect($origin_url, false)) {
            $redirect_url = $origin_url;
        } else {
            $redirect_url = admin_url('admin.php?page=mc-super-admin');
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Get the redirect URL based on user role.
     */
    public static function get_redirect_url_for_user($user)
    {
        if (!($user instanceof WP_User)) {
            return home_url();
        }

        $roles = (array) $user->roles;

        // Admin -> Super Admin Dashboard
        if (in_array('administrator', $roles) || user_can($user, 'manage_options')) {
            return admin_url('admin.php?page=mc-super-admin');
        }

        // Employer -> Employer Dashboard
        if (class_exists('MC_Roles') && in_array(MC_Roles::ROLE_EMPLOYER, $roles)) {
            return home_url('/employer-dashboard/');
        }

        // Employee -> Quiz Dashboard
        if (class_exists('MC_Roles') && in_array(MC_Roles::ROLE_EMPLOYEE, $roles)) {
            return home_url('/quiz-dashboard/');
        }

        return home_url();
    }

    /**
     * Generate a switch URL for a specific user.
     */
    public static function get_switch_url($user_id)
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => self::ACTION_SWITCH,
                'user_id' => $user_id
            ], admin_url('admin.php')),
            self::ACTION_SWITCH . '_' . $user_id
        );
    }

    /**
     * Generate a switch back URL.
     */
    public static function get_switch_back_url()
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => self::ACTION_SWITCH_BACK
            ], admin_url('admin.php')),
            self::ACTION_SWITCH_BACK
        );
    }

    /**
     * Check if user is currently switched.
     */
    public static function is_switched()
    {
        return isset($_COOKIE[self::COOKIE_NAME]) && intval($_COOKIE[self::COOKIE_NAME]) > 0;
    }

    /**
     * Get the original admin user if switched.
     */
    public static function get_original_user()
    {
        if (!self::is_switched()) {
            return null;
        }

        $original_id = intval($_COOKIE[self::COOKIE_NAME]);
        return get_userdata($original_id);
    }

    /**
     * Add switch back button to admin bar.
     */
    public static function add_admin_bar_menu($wp_admin_bar)
    {
        if (!self::is_switched()) {
            return;
        }

        $original_user = self::get_original_user();
        if (!$original_user) {
            return;
        }

        $switch_back_url = self::get_switch_back_url();

        $wp_admin_bar->add_node([
            'id' => 'mc-switch-back',
            'title' => '<span class="ab-icon dashicons dashicons-undo"></span> Switch Back to ' . esc_html($original_user->display_name),
            'href' => esc_url($switch_back_url),
            'meta' => [
                'class' => 'mc-switch-back-btn',
                'title' => 'Switch back to your admin account'
            ]
        ]);
    }

    /**
     * Add styles for switch back button.
     */
    public static function add_switch_back_styles()
    {
        if (!self::is_switched()) {
            return;
        }
        ?>
        <style>
            #wpadminbar #wp-admin-bar-mc-switch-back>a {
                background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
                color: #fff !important;
                font-weight: 600;
            }

            #wpadminbar #wp-admin-bar-mc-switch-back>a:hover {
                background: linear-gradient(135deg, #c0392b, #a93226) !important;
            }

            #wpadminbar #wp-admin-bar-mc-switch-back .ab-icon {
                margin-right: 5px;
            }

            #wpadminbar #wp-admin-bar-mc-switch-back .ab-icon:before {
                content: "\f171";
                top: 3px;
            }
        </style>
        <?php
    }
}

// Initialize
MC_User_Switcher::init();
