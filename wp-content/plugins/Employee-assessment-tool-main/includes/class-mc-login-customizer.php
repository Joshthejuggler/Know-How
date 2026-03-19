<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Customizes the WordPress login and registration screens.
 */
class MC_Login_Customizer
{
    /**
     * Initialize the login customizer.
     */
    public static function init()
    {
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_login_styles']);
        add_filter('login_headerurl', [__CLASS__, 'login_logo_url']);
        add_filter('login_headertext', [__CLASS__, 'login_logo_title']);
        add_action('login_message', [__CLASS__, 'custom_login_message']);

        // Custom login redirect logic
        add_filter('login_redirect', [__CLASS__, 'custom_login_redirect'], 999, 3);

        // Auto-login registration hooks
        add_action('register_form', [__CLASS__, 'add_password_fields']);
        add_filter('registration_errors', [__CLASS__, 'validate_password_fields'], 10, 3);
        add_action('user_register', [__CLASS__, 'save_password_and_login']);

        // Track user login
        add_action('wp_login', [__CLASS__, 'track_user_login'], 10, 2);
    }

    /**
     * Track user login and update status.
     */
    public static function track_user_login($user_login, $user)
    {
        // Update last login timestamp
        update_user_meta($user->ID, 'mc_last_login', current_time('mysql'));

        // If user is an employer and status is pending, set to active
        if (in_array(MC_Roles::ROLE_EMPLOYER, (array) $user->roles)) {
            $status = get_user_meta($user->ID, 'mc_employer_status', true);
            if (!$status || $status === 'pending') {
                update_user_meta($user->ID, 'mc_employer_status', 'active');
            }
        }
    }

    /**
     * Redirect users to their appropriate dashboard after login.
     */
    /**
     * Redirect users to their appropriate dashboard after login.
     */
    public static function custom_login_redirect($redirect_to, $request, $user)
    {
        // Ensure user object is valid
        if (is_wp_error($user)) {
            return $redirect_to;
        }

        // If there is a specific redirect request (and it's not default admin), respect it.
        if (!empty($request) && strpos($request, 'wp-admin') === false && strpos($request, 'wp-login.php') === false) {
            return $request;
        }

        return self::get_redirect_url_for_user($user);
    }

    /**
     * Get the redirect URL based on user role.
     * 
     * @param WP_User $user The user object.
     * @return string The redirect URL.
     */
    public static function get_redirect_url_for_user($user)
    {
        if (!($user instanceof WP_User)) {
            return home_url();
        }

        // Check for Admin first - Force redirect to Super Admin Dashboard
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('administrator', $user->roles) || $user->has_cap('manage_options')) {
                return admin_url('admin.php?page=mc-super-admin');
            }
        }

        if (isset($user->roles) && is_array($user->roles)) {
            // Employers -> Employer Dashboard
            if (in_array(MC_Roles::ROLE_EMPLOYER, $user->roles)) {
                // Use hardcoded URL with fallback to shortcode lookup
                return home_url('/employer-dashboard/');
            }
            // Employees -> Assessment Dashboard
            if (in_array(MC_Roles::ROLE_EMPLOYEE, $user->roles)) {
                // Use hardcoded URL with fallback to shortcode lookup
                return home_url('/quiz-dashboard/');
            }
        }

        return home_url();
    }

    /**
     * Enqueue custom styles for the login page.
     */
    public static function enqueue_login_styles()
    {
        wp_enqueue_style('mc-login-custom', plugins_url('../assets/login-custom.css', __FILE__), [], '1.0.0');
    }

    /**
     * Change the logo link URL to the site home URL.
     */
    public static function login_logo_url()
    {
        return home_url();
    }

    /**
     * Change the logo title attribute.
     */
    public static function login_logo_title()
    {
        return get_bloginfo('name');
    }

    /**
     * Add a custom message or modify the login header.
     */
    public static function custom_login_message($message)
    {
        return $message;
    }

    /**
     * Add password fields to the registration form.
     */
    public static function add_password_fields()
    {
        // Check for invite cookie to display welcome message
        if (isset($_COOKIE['mc_invite_token'])) {
            $token = sanitize_text_field($_COOKIE['mc_invite_token']);
            $parts = explode('-', $token);
            if (count($parts) >= 2 && is_numeric($parts[0])) {
                $employer_id = intval($parts[0]);
                $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                $invite_name = '';
                if (is_array($invited_employees)) {
                    foreach ($invited_employees as $invite) {
                        if (is_array($invite) && isset($invite['token']) && $invite['token'] === $token) {
                            $invite_name = $invite['name'];
                            break;
                        }
                    }
                }

                if ($invite_name) {
                    $company_name = get_user_meta($employer_id, 'mc_company_name', true) ?: 'the team';
                    echo '<p class="message register" style="border-left: 4px solid #72aee6; padding: 12px; background: #fff; margin-bottom: 20px;">
                        Registering as: <strong>' . esc_html($invite_name) . '</strong><br>
                        Invited by: <strong>' . esc_html($company_name) . '</strong>
                    </p>';
                }
            }
        }

        // Pre-fill and Lock Email if provided via URL or Cookie
        $prefill_email = '';
        if (isset($_GET['user_email'])) {
            $prefill_email = sanitize_email($_GET['user_email']);
        } elseif (isset($_COOKIE['mc_invite_email'])) {
            $prefill_email = sanitize_email($_COOKIE['mc_invite_email']);
        }

        if ($prefill_email) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var emailField = document.getElementById('user_email');
                    if (emailField) {
                        emailField.value = '<?php echo esc_js($prefill_email); ?>';
                        emailField.setAttribute('readonly', 'readonly');
                        emailField.style.backgroundColor = '#f0f0f1';
                        emailField.style.cursor = 'not-allowed';
                        // Also add hidden input in case disabled fields aren't submitted (though readonly usually is)
                    }
                });
            </script>
            <?php
        }
        ?>
        <p>
            <label for="password">Password <br />
                <input type="password" name="password" id="password" class="input" value="" size="25" autocomplete="off" />
            </label>
        </p>
        <p>
            <label for="password_confirm">Confirm Password <br />
                <input type="password" name="password_confirm" id="password_confirm" class="input" value="" size="25"
                    autocomplete="off" />
            </label>
        </p>
        <?php
    }

    /**
     * Validate the password fields.
     */
    public static function validate_password_fields($errors, $sanitized_user_login, $user_email)
    {
        if (empty($_POST['password']) || empty($_POST['password_confirm'])) {
            $errors->add('password_error', __('<strong>ERROR</strong>: Please enter a password and confirm it.', 'mc-quiz'));
        } elseif ($_POST['password'] !== $_POST['password_confirm']) {
            $errors->add('password_mismatch', __('<strong>ERROR</strong>: Passwords do not match.', 'mc-quiz'));
        }

        // Enforce Email Match if Invite Token is present
        if (isset($_COOKIE['mc_invite_token'])) {
            $token = sanitize_text_field($_COOKIE['mc_invite_token']);
            // Redundant lookup but necessary for security
            $parts = explode('-', $token);
            if (count($parts) >= 2 && is_numeric($parts[0])) {
                $employer_id = intval($parts[0]);
                $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                if (is_array($invited_employees)) {
                    foreach ($invited_employees as $invite) {
                        if (is_array($invite) && isset($invite['token']) && $invite['token'] === $token) {
                            if (!empty($invite['email'])) {
                                // Check if submitted email matches invited email
                                if (strtolower(trim($user_email)) !== strtolower(trim($invite['email']))) {
                                    $errors->add('email_mismatch', __('<strong>ERROR</strong>: You must register with the email address you were invited with (' . esc_html($invite['email']) . ').', 'mc-quiz'));
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Save the password and auto-login the user.
     */
    public static function save_password_and_login($user_id)
    {
        if (isset($_POST['password'])) {
            wp_set_password($_POST['password'], $user_id);

            // Auto-login
            $user = get_user_by('id', $user_id);
            wp_set_current_user($user_id, $user->user_login);
            wp_set_auth_cookie($user_id);

            // Set last login for auto-login so they show as active immediately
            update_user_meta($user_id, 'mc_last_login', current_time('mysql'));

            // Handle Role Assignment based on invite cookie
            if (class_exists('MC_Roles')) {
                // Check for Unique Token First (Preferred)
                if (isset($_COOKIE['mc_invite_token'])) {
                    $token = sanitize_text_field($_COOKIE['mc_invite_token']);
                    $parts = explode('-', $token);
                    if (count($parts) >= 2 && is_numeric($parts[0])) {
                        $employer_id = intval($parts[0]);

                        // Link to employer
                        update_user_meta($user_id, 'mc_linked_employer_id', $employer_id);
                        $user->set_role(MC_Roles::ROLE_EMPLOYEE);

                        // Claim the invite
                        $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                        if (is_array($invited_employees)) {
                            $updated = false;
                            foreach ($invited_employees as $key => $invite) {
                                if (is_array($invite) && isset($invite['token']) && $invite['token'] === $token) {
                                    // Claim it!
                                    $invited_employees[$key]['claimed_by'] = $user_id;
                                    $invited_employees[$key]['registered_email'] = $user->user_email; // Track actual email
                                    $updated = true;

                                    // Transfer Context
                                    if (!empty($invite['role'])) {
                                        $context = [
                                            'role' => $invite['role'],
                                            'responsibilities' => $invite['responsibilities'] ?? ''
                                        ];
                                        update_user_meta($user_id, 'mc_employee_role_context', $context);
                                    }

                                    // Auto-fill Name from Invite
                                    if (!empty($invite['name'])) {
                                        $name_parts = explode(' ', trim($invite['name']), 2);
                                        $first_name = $name_parts[0];
                                        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

                                        wp_update_user([
                                            'ID' => $user_id,
                                            'first_name' => $first_name,
                                            'last_name' => $last_name,
                                            'display_name' => $invite['name']
                                        ]);
                                    }

                                    break;
                                }
                            }
                            if ($updated) {
                                update_user_meta($employer_id, 'mc_invited_employees', $invited_employees);
                            }
                        }
                    }
                }
                // Fallback to legacy Share Code if no unique token
                elseif (isset($_COOKIE['mc_invite_code'])) {
                    $user->set_role(MC_Roles::ROLE_EMPLOYEE);
                    // Link to employer (added logic here)
                    $invite_code = sanitize_text_field($_COOKIE['mc_invite_code']);
                    $args = [
                        'meta_key' => 'mc_company_share_code',
                        'meta_value' => $invite_code,
                        'number' => 1,
                        'fields' => 'ID'
                    ];
                    $employer_query = new WP_User_Query($args);
                    $employers = $employer_query->get_results();
                    if (!empty($employers)) {
                        $employer_id = $employers[0];
                        update_user_meta($user_id, 'mc_linked_employer_id', $employer_id);
                        // Transfer Role & Responsibilities from Invite (added logic here)
                        $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                        if (is_array($invited_employees)) {
                            foreach ($invited_employees as $invite) {
                                $inv_email = is_array($invite) ? $invite['email'] : $invite;
                                if (strtolower($inv_email) === strtolower($user->user_email)) {
                                    if (is_array($invite) && !empty($invite['role'])) {
                                        $context = [
                                            'role' => $invite['role'],
                                            'responsibilities' => $invite['responsibilities'] ?? ''
                                        ];
                                        update_user_meta($user_id, 'mc_employee_role_context', $context);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    // Default new registrations to Employer (if no invite code or token)
                    $user->set_role(MC_Roles::ROLE_EMPLOYER);
                }
            }

            // Determine redirect based on role
            $redirect_to = home_url();

            // Check role we just assigned
            if ($user->has_cap(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
                $dash = MC_Funnel::find_page_by_shortcode('mc_employer_dashboard');
                if ($dash)
                    $redirect_to = $dash;
            } elseif ($user->has_cap(MC_Roles::CAP_TAKE_ASSESSMENTS)) {
                $dash = MC_Funnel::find_page_by_shortcode('quiz_dashboard');
                if ($dash)
                    $redirect_to = $dash;
            }

            // If invite code cookie exists, logic might override (but we handled role assignment above)
            // But we can also check if there was a specific redirect_to posted
            if (!empty($_POST['redirect_to'])) {
                $redirect_to = $_POST['redirect_to'];
            }

            wp_safe_redirect($redirect_to);
            exit;
        }
    }
}
