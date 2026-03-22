<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles Magic Login functionality.
 */
class MC_Magic_Login
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'handle_magic_login']);
    }

    /**
     * Process magic login link.
     */
    public static function handle_magic_login()
    {
        if (!isset($_GET['mc_auth_token'])) {
            return;
        }

        $token = sanitize_text_field($_GET['mc_auth_token']);

        // Find user by token
        $args = [
            'meta_key' => 'mc_magic_login_token',
            'meta_value' => $token,
            'number' => 1,
            'fields' => 'all'
        ];

        $users = get_users($args);

        if (empty($users)) {
            return; // Invalid token
        }

        $user = $users[0];

        // Check expiration
        $expires = get_user_meta($user->ID, 'mc_magic_login_expires', true);
        if ($expires < time()) {
            // Token expired
            return;
        }

        // Log user in
        wp_set_auth_cookie($user->ID);

        // Clear token
        delete_user_meta($user->ID, 'mc_magic_login_token');
        delete_user_meta($user->ID, 'mc_magic_login_expires');

        // Redirect to onboarding or dashboard
        $redirect_url = home_url();

        if (class_exists('MC_Login_Customizer')) {
            $redirect_url = MC_Login_Customizer::get_redirect_url_for_user($user);
        } else {
            // Fallback if class not loaded
            if (user_can($user, 'manage_options')) {
                $redirect_url = admin_url('admin.php?page=mc-super-admin');
            } elseif (class_exists('MC_Funnel')) {
                $page = MC_Funnel::find_page_by_shortcode('employer_onboarding');
                if ($page) {
                    $redirect_url = get_permalink($page);
                }
            }
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Generate a magic link for a user.
     * 
     * @param int $user_id User ID.
     * @param int $expiry_seconds Seconds until expiration (default 7 days).
     * @return string Magic link URL.
     */
    public static function generate_magic_link($user_id, $expiry_seconds = 604800)
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + $expiry_seconds;

        update_user_meta($user_id, 'mc_magic_login_token', $token);
        update_user_meta($user_id, 'mc_magic_login_expires', $expires);

        $url = home_url('/');
        return add_query_arg('mc_auth_token', $token, $url);
    }
}
