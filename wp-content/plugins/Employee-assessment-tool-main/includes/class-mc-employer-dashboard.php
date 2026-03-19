<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles the Employer Dashboard.
 */
class MC_Employer_Dashboard
{
    public static function init()
    {
        add_shortcode('mc_employer_dashboard', [__CLASS__, 'render_dashboard']);
        wp_enqueue_style('mc-employer-dashboard-css', plugin_dir_url(__FILE__) . '../assets/employer-dashboard.css', [], time());
        add_action('wp_ajax_mc_save_employee_context', [__CLASS__, 'ajax_save_employee_context']);
    }

    public static function render_modals()
    {
        ?>
        <!-- Employee Context Modal -->
        <div id="mc-employee-modal" class="mc-modal" style="display:none; z-index:99999;">
            <div class="mc-modal-content" style="display:block; opacity:1;">
                <span class="mc-close" onclick="closeEmployeeModal()">&times;</span>
                <h2>Manage Employee Role</h2>
                <p id="mc-emp-name-display"></p>
                <form id="mc-role-form" onsubmit="event.preventDefault(); saveEmployeeContext(false);">
                    <input type="hidden" name="mc_employee_id" id="mc_emp_id_input">
                    <div class="mc-form-group">
                        <label>Role Title <span class="mc-required">*</span></label>
                        <input type="text" name="mc_role_title" id="mc_role_input" placeholder="e.g. Senior Developer" required>
                    </div>
                    <div class="mc-form-group">
                        <label>Key Responsibilities <span class="mc-required">*</span></label>
                        <textarea name="mc_responsibilities" id="mc_resp_input" rows="4"
                            placeholder="Key duties and expectations..." required></textarea>
                    </div>
                    <div class="mc-form-actions" style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                        <button type="button" class="mc-button secondary" onclick="closeEmployeeModal()">Cancel</button>
                        <button type="button" class="mc-button" id="mc-save-only-btn" onclick="saveEmployeeContext(false)">Save
                            Details</button>
                        <button type="button" class="mc-button primary" id="mc-save-generate-btn"
                            onclick="saveEmployeeContext(true)">Save & Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Analysis Modal -->
        <?php MC_Report_Template::render_analysis_modal(false); ?>
    <?php
    }

    public static function render_dashboard()
    {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);

        // RBAC Check: Ensure user has permission to manage employees
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
            // If they are logged in but don't have permission, they might be an employee
            // attempting to access the employer dashboard.

            // Optional: Redirect employees to their own dashboard if they stumble here
            if (current_user_can(MC_Roles::CAP_TAKE_ASSESSMENTS)) {
                $employee_dashboard_url = '#';
                if (class_exists('MC_Funnel')) {
                    $employee_dashboard_url = MC_Funnel::find_page_by_shortcode('quiz_dashboard');
                }
                if ($employee_dashboard_url) {
                    if (headers_sent()) {
                        return '<script>window.location.href="' . esc_url($employee_dashboard_url) . '";</script>';
                    }
                    wp_redirect($employee_dashboard_url);
                    exit;
                }
            }

            return '<p>Access Denied: You do not have permission to view this dashboard.</p>';
        }

        $company_name = get_user_meta($user_id, 'mc_company_name', true) ?: 'Your Company';

        // Get invited employees
        $invited_emails = get_user_meta($user_id, 'mc_invited_employees', true);
        if (!is_array($invited_emails)) {
            $invited_emails = [];
        }

        // Get quiz config for headers
        $config = MC_Funnel::get_config();
        $quiz_steps = $config['steps'];

        // Handle Archive Action
        if (isset($_POST['mc_archive_user'])) {
            $email_to_archive = sanitize_email($_POST['mc_archive_user']);
            if (is_email($email_to_archive)) {
                // Check if it's a registered user
                $user = get_user_by('email', $email_to_archive);
                if ($user) {
                    update_user_meta($user->ID, 'mc_employment_status', 'archived');
                } else {
                    // It's a pending invite - we need to track archived invites separately
                    $archived_invites = get_user_meta($user_id, 'mc_archived_invites', true);
                    if (!is_array($archived_invites)) {
                        $archived_invites = [];
                    }
                    if (!in_array($email_to_archive, $archived_invites)) {
                        $archived_invites[] = $email_to_archive;
                        update_user_meta($user_id, 'mc_archived_invites', $archived_invites);
                    }
                }
            }
        }

        // Handle Restore Action
        if (isset($_POST['mc_restore_user'])) {
            $email_to_restore = sanitize_email($_POST['mc_restore_user']);
            if (is_email($email_to_restore)) {
                // Check if it's a registered user
                $user = get_user_by('email', $email_to_restore);
                if ($user) {
                    delete_user_meta($user->ID, 'mc_employment_status'); // Remove 'archived' status
                } else {
                    // It's a pending invite
                    $archived_invites = get_user_meta($user_id, 'mc_archived_invites', true);
                    if (is_array($archived_invites)) {
                        $key = array_search($email_to_restore, $archived_invites);
                        if ($key !== false) {
                            unset($archived_invites[$key]);
                            update_user_meta($user_id, 'mc_archived_invites', array_values($archived_invites));
                        }
                    }
                }
            }
        }

        // Handle Delete Action
        if (isset($_POST['mc_delete_user'])) {
            $email_to_delete = sanitize_email($_POST['mc_delete_user']);
            if (is_email($email_to_delete)) {
                // Check if it's a registered user
                $user = get_user_by('email', $email_to_delete);
                if ($user) {
                    delete_user_meta($user->ID, 'mc_linked_employer_id');
                    delete_user_meta($user->ID, 'mc_employment_status');
                    delete_user_meta($user->ID, 'mc_employee_role_context');
                }

                // Remove from invited employees list
                $current_invites = get_user_meta($user_id, 'mc_invited_employees', true);
                if (is_array($current_invites)) {
                    $new_invites = [];
                    $changed = false;
                    foreach ($current_invites as $invite) {
                        $inv_email = is_array($invite) ? $invite['email'] : $invite;
                        if ($inv_email !== $email_to_delete) {
                            $new_invites[] = $invite;
                        } else {
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        update_user_meta($user_id, 'mc_invited_employees', $new_invites);
                        // Update local variable so the list refreshes correctly
                        $invited_emails = $new_invites;
                    }
                }

                // Remove from archived invites
                $archived_invites = get_user_meta($user_id, 'mc_archived_invites', true);
                if (is_array($archived_invites)) {
                    $key = array_search($email_to_delete, $archived_invites);
                    if ($key !== false) {
                        unset($archived_invites[$key]);
                        update_user_meta($user_id, 'mc_archived_invites', array_values($archived_invites));
                    }
                }
            }
        }

        // Handle Resend Invite
        $resend_message = '';
        if (isset($_POST['mc_resend_invite'])) {
            $email_to_resend = sanitize_email($_POST['mc_resend_invite']);
            // ... (existing resend logic stays same, omitted for brevity if not changing) ...
            if (is_email($email_to_resend) && in_array($email_to_resend, $invited_emails)) {
                $token = '';
                $invites_updated = false;

                // Find and update the specific invite with a token if needed
                foreach ($invited_emails as $key => $invite_arr) {
                    $inv_email = is_array($invite_arr) ? $invite_arr['email'] : $invite_arr;
                    if ($inv_email === $email_to_resend) {
                        if (is_array($invite_arr)) {
                            if (empty($invite_arr['token'])) {
                                $token = $user_id . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
                                $invited_emails[$key]['token'] = $token;
                                $invites_updated = true;
                            } else {
                                $token = $invite_arr['token'];
                            }
                        }
                        break;
                    }
                }

                if ($invites_updated) {
                    update_user_meta($user_id, 'mc_invited_employees', $invited_emails);
                }

                $share_code = get_user_meta($user_id, 'mc_company_share_code', true);
                $employee_landing_url = '#';
                if (class_exists('MC_Funnel')) {
                    $employee_landing_url = MC_Funnel::find_page_by_shortcode('mc_employee_landing') ?: home_url();
                }

                // Use token if we have one, otherwise fallback to share code
                $code_to_use = $token ?: $share_code;
                $invite_link = add_query_arg('invite_code', $code_to_use, $employee_landing_url);

                $subject = "You've been invited to join " . $company_name . "'s Assessment Platform";
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $logo_id = get_user_meta($user_id, 'mc_company_logo_id', true);
                $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

                // Modern HTML email with dark mode support
                $message = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="color-scheme" content="light dark">
                    <meta name="supported-color-schemes" content="light dark">
                    <title>' . esc_html($subject) . '</title>
                    <style>
                        @media (prefers-color-scheme: dark) {
                            .email-body { background-color: #0f172a !important; }
                            .email-card { background-color: #1e293b !important; border: 1px solid #334155 !important; }
                            .email-text { color: #e2e8f0 !important; }
                            .email-text-secondary { color: #94a3b8 !important; }
                            .email-footer { background-color: #1e293b !important; border-top-color: #334155 !important; }
                            .email-footer-text { color: #64748b !important; }
                        }
                    </style>
                </head>
                <body class="email-body" style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f8fafc; line-height: 1.6;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-body" style="background-color: #f8fafc; padding: 40px 20px;">
                        <tr>
                            <td align="center">
                                <table width="600" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                                    <!-- Header -->
                                    <tr>
                                        <td style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); padding: 40px 40px 30px; text-align: center;">
                                            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . '" style="max-height: 60px; max-width: 200px; margin-bottom: 16px; display: block; margin-left: auto; margin-right: auto;">' : '') . '
                                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">You\'re Invited!</h1>
                                            <p style="margin: 12px 0 0; font-size: 16px; color: rgba(255, 255, 255, 0.95);">Join Your Team\'s Assessment Platform</p>
                                        </td>
                                    </tr>
                                    
                                    <!-- Body -->
                                    <tr>
                                        <td style="padding: 40px;">
                                            <p class="email-text" style="margin: 0 0 20px; font-size: 16px; color: #0f172a;">Hello,</p>
                                            
                                            <p class="email-text-secondary" style="margin: 0 0 24px; font-size: 16px; color: #475569; line-height: 1.6;"><strong>' . esc_html($company_name) . '</strong> has invited you to discover your unique strengths and accelerate your professional development through comprehensive assessments.</p>
                                            
                                            <p class="email-text-secondary" style="margin: 0 0 24px; font-size: 16px; color: #475569; line-height: 1.6;">To get started, you\'ll need to create a unique profile. Click the button below and follow the steps to set up your account.</p>
                                            
                                            <div style="margin: 32px 0; text-align: center;">
                                                <a href="' . esc_url($invite_link) . '" style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">Start Your Assessment →</a>
                                            </div>
                                            
                                            <div style="background-color: #f0f9ff; border-left: 4px solid #2563eb; padding: 16px; margin: 24px 0; border-radius: 4px;">
                                                <p class="email-text" style="margin: 0 0 8px; font-size: 14px; color: #0f172a; font-weight: 600;">What you\'ll discover:</p>
                                                <ul class="email-text-secondary" style="margin: 0; padding: 0 0 0 20px; color: #475569; font-size: 14px;">
                                                    <li style="margin-bottom: 4px;">Your unique cognitive strengths</li>
                                                    <li style="margin-bottom: 4px;">What motivates and energizes you</li>
                                                    <li style="margin-bottom: 4px;">Personalized growth recommendations</li>
                                                </ul>
                                            </div>
                                            
                            <p class="email-text-secondary" style="margin: 20px 0 0; font-size: 14px; color: #64748b; text-align: center;">Takes about 60 minutes • 100% confidential</p>
                                        </td>
                                    </tr>
                                    
                                    <!-- Footer -->
                                    <tr>
                                        <td class="email-footer" style="background-color: #f8fafc; padding: 24px 40px; text-align: center; border-top: 1px solid #e2e8f0;">
                                            <p class="email-text-secondary" style="margin: 0 0 4px; font-size: 13px; color: #64748b;">Powered by</p>
                                            <p class="email-text" style="margin: 0; font-size: 14px; color: #0f172a; font-weight: 700;">The Science of Teamwork</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Footer Note -->
                                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin-top: 20px;">
                                    <tr>
                                        <td class="email-footer-text" style="text-align: center; padding: 20px; font-size: 12px; color: #94a3b8;">
                                            <p style="margin: 0;">© ' . date('Y') . ' The Science of Teamwork. All rights reserved.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
                ';

                if (wp_mail($email_to_resend, $subject, $message, $headers)) {
                    $resend_message = '<div class="mc-alert success">Invite resent to ' . esc_html($email_to_resend) . '</div>';
                } else {
                    $resend_message = '<div class="mc-alert error">Failed to send email.</div>';
                }
            }
        }

        // Handle Edit Invite Email
        if (isset($_POST['mc_edit_invite_email_old']) && isset($_POST['mc_edit_invite_email_new'])) {
            $old_email = sanitize_email($_POST['mc_edit_invite_email_old']);
            $new_email = sanitize_email($_POST['mc_edit_invite_email_new']);

            if (is_email($new_email) && !empty($old_email)) {
                $exists = get_user_by('email', $new_email);
                if ($exists) {
                    $resend_message = '<div class="mc-alert error">User with this email already exists.</div>';
                } else {
                    $current_invites = get_user_meta($user_id, 'mc_invited_employees', true);
                    $updated = false;
                    if (is_array($current_invites)) {
                        foreach ($current_invites as $key => $invite) {
                            $inv_email = is_array($invite) ? $invite['email'] : $invite;
                            if ($inv_email === $old_email) {
                                // Update Email
                                if (is_array($invite)) {
                                    $current_invites[$key]['email'] = $new_email;
                                    // Generate new token to invalidate old link
                                    $current_invites[$key]['token'] = $user_id . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
                                } else {
                                    // Handle legacy string format
                                    $current_invites[$key] = [
                                        'email' => $new_email,
                                        'name' => '', // Unknown if legacy
                                        'token' => $user_id . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8)
                                    ];
                                }
                                $updated = true;
                                break;
                            }
                        }
                    }

                    if ($updated) {
                        update_user_meta($user_id, 'mc_invited_employees', $current_invites);
                        $invited_emails = $current_invites; // Refresh local list
                        $resend_message = '<div class="mc-alert success">Email updated to ' . esc_html($new_email) . '. Please resend the invite.</div>';
                    } else {
                        $resend_message = '<div class="mc-alert error">Could not find invite to update.</div>';
                    }
                }
            } else {
                $resend_message = '<div class="mc-alert error">Invalid email address.</div>';
            }
        }

        // Handle Save Workplace Context
        if (isset($_POST['mc_save_workplace_context'])) {
            $company_name_input = sanitize_text_field($_POST['mc_company_name_update']);
            $industry = sanitize_text_field($_POST['mc_industry']);
            $values = sanitize_textarea_field($_POST['mc_values']);
            $culture = sanitize_textarea_field($_POST['mc_culture']);

            if (empty($company_name_input) || empty($industry) || empty($values) || empty($culture)) {
                $resend_message = '<div class="mc-alert error">Please fill in all workplace settings fields.</div>';
            } else {
                $context = [
                    'industry' => $industry,
                    'values' => $values,
                    'culture' => $culture
                ];

                update_user_meta($user_id, 'mc_workplace_context', $context);
                update_user_meta($user_id, 'mc_company_name', $company_name_input);

                // Update local variable for immediate display update
                $company_name = $company_name_input;

                $resend_message = '<div class="mc-alert success">Workplace context saved.</div>';
            }
        }

        // Handle Save Employee Context
        if (isset($_POST['mc_save_employee_context'])) {
            $emp_id = intval($_POST['mc_employee_id']);
            $role = sanitize_text_field($_POST['mc_role_title']);
            $responsibilities = sanitize_textarea_field($_POST['mc_responsibilities']);

            if (empty($role) || empty($responsibilities)) {
                $resend_message = '<div class="mc-alert error">Please fill in the role title and responsibilities.</div>';
            } else {
                // Verify ownership
                $linked_employer = get_user_meta($emp_id, 'mc_linked_employer_id', true);
                if (intval($linked_employer) === $user_id || current_user_can('manage_options')) {
                    $context = [
                        'role' => $role,
                        'responsibilities' => $responsibilities
                    ];
                    update_user_meta($emp_id, 'mc_employee_role_context', $context);
                    $resend_message = '<div class="mc-alert success">Employee context saved.</div>';
                }
            }
        }

        ob_start();

        // Get Workplace Context (check combined array first, fall back to individual keys from onboarding)
        $workplace_context = get_user_meta($user_id, 'mc_workplace_context', true) ?: [];
        $wp_industry = $workplace_context['industry'] ?? '';
        $wp_values = $workplace_context['values'] ?? '';
        $wp_culture = $workplace_context['culture'] ?? '';

        // Fallback: read from individual meta keys saved by onboarding
        if (empty($wp_industry)) {
            $wp_industry = get_user_meta($user_id, 'mc_workplace_industry', true) ?: '';
        }
        if (empty($wp_values)) {
            $wp_values = get_user_meta($user_id, 'mc_workplace_values', true) ?: '';
        }
        if (empty($wp_culture)) {
            $wp_culture = get_user_meta($user_id, 'mc_workplace_culture', true) ?: '';
        }

        // Format default workplace context from existing settings
        $default_workplace = '';
        if (!empty($wp_industry) || !empty($wp_values) || !empty($wp_culture)) {
            $parts = [];
            if (!empty($wp_industry))
                $parts[] = "Industry: " . $wp_industry;
            if (!empty($wp_values))
                $parts[] = "Values: " . $wp_values;
            if (!empty($wp_culture))
                $parts[] = "Culture: " . $wp_culture;
            $default_workplace = implode(". ", $parts);
        }

        $default_role = get_option('mc_default_role_context', '');
        ?>
        <div id="mc-dashboard-wrapper" class="mc-dashboard-wrapper"
            data-default-workplace="<?php echo esc_attr($default_workplace); ?>"
            data-default-role="<?php echo esc_attr($default_role); ?>">
            <?php echo $resend_message; ?>
            <header class="mc-site-header">
                <div class="mc-logo">The Science of Teamwork</div>
                <div class="mc-nav">
                    <span class="mc-user-greeting"><?php echo esc_html($company_name); ?></span>
                    <?php
                    $employee_dashboard_url = '#';
                    if (class_exists('MC_Funnel')) {
                        $employee_dashboard_url = MC_Funnel::find_page_by_shortcode('quiz_dashboard');
                    }
                    if ($employee_dashboard_url) {
                        echo '<a href="' . esc_url($employee_dashboard_url) . '" style="margin-right: 15px; font-weight: 500;">Switch to Employee View</a>';
                    }

                    if (function_exists('current_user_switched') && current_user_switched()) {
                        $switch_back_url = false;
                        if (function_exists('user_switching_get_switch_back_url')) {
                            $switch_back_url = user_switching_get_switch_back_url();
                        } elseif (class_exists('user_switching')) {
                            if (method_exists('user_switching', 'get_switch_back_url')) {
                                $switch_back_url = user_switching::get_switch_back_url();
                            } elseif (method_exists('user_switching', 'get_old_user')) {
                                $old_user = user_switching::get_old_user();
                                if ($old_user) {
                                    $switch_back_url = add_query_arg([
                                        'action' => 'switch_to_olduser',
                                        'nr' => 1
                                    ], admin_url('users.php'));
                                    $switch_back_url = wp_nonce_url($switch_back_url, 'switch_to_olduser_' . $old_user->ID);
                                }
                            }
                        }

                        if ($switch_back_url) {
                            echo '<a href="' . esc_url($switch_back_url) . '" style="margin-right: 15px; font-weight: 500; color: #fca5a5;">← Switch Back to Admin</a>';
                        }
                    }
                    ?>
                    <a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a>
                </div>
            </header>

            <div class="mc-dashboard-container">
                <div class="mc-dashboard-header-row">
                    <div>
                        <h1>Employer Dashboard</h1>
                        <p class="mc-dashboard-intro">Manage your team and track their assessment progress.</p>
                    </div>
                    <div class="mc-header-actions">
                        <button class="mc-button secondary" onclick="openWorkplaceModal()">Workplace Settings</button>
                        <a href="<?php echo esc_url(MC_Funnel::find_page_by_shortcode('mc_employer_onboarding')); ?>?step=2"
                            class="mc-button">Invite More Employees</a>
                    </div>
                </div>

                <div class="mc-filters">
                    <button class="mc-filter-btn active" onclick="filterTeam('all', this)">All</button>
                    <button class="mc-filter-btn" onclick="filterTeam('active', this)">Active</button>
                    <button class="mc-filter-btn" onclick="filterTeam('complete', this)">Complete</button>
                    <button class="mc-filter-btn" onclick="filterTeam('pending', this)">Pending</button>
                    <button class="mc-filter-btn" onclick="filterTeam('archived', this)">Archived</button>
                </div>

                <div class="mc-team-list">
                    <?php
                    // Get users linked via ID
                    $args = [
                        'meta_key' => 'mc_linked_employer_id',
                        'meta_value' => $user_id,
                        'fields' => 'all'
                    ];
                    $linked_users_query = new WP_User_Query($args);
                    $linked_users = $linked_users_query->get_results();

                    // Map linked users by email for easy lookup
                    $linked_emails = [];
                    foreach ($linked_users as $user) {
                        $linked_emails[$user->user_email] = $user;
                    }

                    // Get archived invites
                    $archived_invites = get_user_meta($user_id, 'mc_archived_invites', true);
                    if (!is_array($archived_invites)) {
                        $archived_invites = [];
                    }

                    // Merge lists (Invited Emails + Linked Users)
                    $display_list = [];

                    // Add all linked users first
                    foreach ($linked_users as $user) {
                        $status = 'Registered';
                        $emp_status = get_user_meta($user->ID, 'mc_employment_status', true);
                        if ($emp_status === 'archived') {
                            $status = 'Archived';
                        } elseif ($status === 'Registered') {
                            // Check if all assessments are complete
                            $completion = MC_Funnel::get_completion_status($user->ID);
                            $all_complete = true;
                            foreach ($quiz_steps as $slug) {
                                if (empty($completion[$slug])) {
                                    $all_complete = false;
                                    break;
                                }
                            }
                            if ($all_complete) {
                                $status = 'All assessments complete';
                            }
                        }

                        $display_list[$user->user_email] = [
                            'email' => $user->user_email,
                            'user' => $user,
                            'status' => $status
                        ];
                    }

                    // Add pending invites
                    foreach ($invited_emails as $invite_data) {
                        // Handle backward compatibility (string vs array)
                        $email = is_array($invite_data) ? $invite_data['email'] : $invite_data;
                        $name = is_array($invite_data) ? $invite_data['name'] : '';
                        $token = (is_array($invite_data) && !empty($invite_data['token'])) ? $invite_data['token'] : '';

                        if (isset($invite_data['claimed_by'])) {
                            continue;
                        }

                        if (!isset($display_list[$email])) {
                            $status = 'Pending';
                            if (in_array($email, $archived_invites)) {
                                $status = 'Archived';
                            }

                            $user = get_user_by('email', $email);
                            if ($user && !isset($display_list[$email])) {
                                // Registered but not linked
                                // Check if archived
                                $emp_status = get_user_meta($user->ID, 'mc_employment_status', true);
                                if ($emp_status === 'archived') {
                                    $status = 'Archived';
                                } else {
                                    $status = 'Registered'; // Soft link
                                }

                                $status = $status; // Default
            
                                // Check if all assessments are complete
                                if ($status === 'Registered') {
                                    $completion = MC_Funnel::get_completion_status($user->ID);
                                    $all_complete = true;
                                    foreach ($quiz_steps as $slug) {
                                        if (empty($completion[$slug])) {
                                            $all_complete = false;
                                            break;
                                        }
                                    }
                                    if ($all_complete) {
                                        $status = 'All assessments complete';
                                    }
                                }

                                $display_list[$email] = [
                                    'email' => $email,
                                    'user' => $user,
                                    'status' => $status,
                                    'invited_name' => $name,
                                    'token' => $token
                                ];
                            } elseif (!isset($display_list[$email])) {
                                $display_list[$email] = [
                                    'email' => $email,
                                    'user' => null,
                                    'status' => $status,
                                    'invited_name' => $name,
                                    'token' => $token
                                ];
                            }
                        }
                    }
                    ?>

                    <?php if (empty($display_list)): ?>
                        <div class="mc-empty-state">
                            <p>You haven't invited any employees yet.</p>
                            <a href="<?php echo esc_url(MC_Funnel::find_page_by_shortcode('mc_employer_onboarding')); ?>?step=2"
                                class="mc-button small">Invite Employees</a>
                        </div>
                    <?php else: ?>
                        <div class="mc-table-responsive">
                            <table class="mc-team-table" id="mc-team-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Status</th>
                                        <?php foreach ($quiz_steps as $slug): ?>
                                            <th><?php echo esc_html($config['titles'][$slug] ?? $slug); ?></th>
                                        <?php endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($display_list as $item):
                                        $email = $item['email'];
                                        $employee_user = $item['user'];
                                        $status = $item['status'];
                                        $completion = $employee_user ? MC_Funnel::get_completion_status($employee_user->ID) : [];

                                        // Generate Invite Link for this specific user (if pending)
                                        $invite_link = '';
                                        if ($status === 'Pending') {
                                            $share_code = get_user_meta($user_id, 'mc_company_share_code', true);
                                            // Prefer unique token if available
                                            $code_to_use = !empty($item['token']) ? $item['token'] : $share_code;

                                            $employee_landing_url = '#';
                                            if (class_exists('MC_Funnel')) {
                                                $employee_landing_url = MC_Funnel::find_page_by_shortcode('mc_employee_landing') ?: home_url();
                                            }
                                            $invite_link = add_query_arg('invite_code', $code_to_use, $employee_landing_url);
                                        }

                                        // Determine row class for filtering
                                        $row_class = strtolower($status);
                                        $badge_class = sanitize_title($status);
                                        // Map 'Registered' to 'active' for filter consistency if desired, or keep as is.
                                        // Let's keep status as class name.
                                        ?>
                                        <tr class="mc-team-row <?php echo esc_attr($row_class); ?>"
                                            data-status="<?php echo esc_attr($row_class); ?>">
                                            <td>
                                                <div class="mc-employee-info">
                                                    <span class="mc-employee-email"><?php echo esc_html($email); ?></span>
                                                    <?php if ($employee_user): ?>
                                                        <span
                                                            class="mc-employee-name"><?php echo esc_html($employee_user->display_name); ?></span>
                                                    <?php elseif (!empty($item['invited_name'])): ?>
                                                        <span class="mc-employee-name" style="color: #64748b; font-style: italic;">
                                                            <?php echo esc_html($item['invited_name']); ?>
                                                            (Invited)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="mc-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status); ?></span>
                                            </td>
                                            <?php foreach ($quiz_steps as $slug):
                                                $is_completed = !empty($completion[$slug]);
                                                ?>
                                                <td class="mc-text-center">
                                                    <?php if ($status === 'Pending'): ?>
                                                        <span class="mc-status-dot pending" title="Pending Registration"></span>
                                                    <?php elseif ($is_completed): ?>
                                                        <span class="mc-status-dot completed" title="Completed">✓</span>
                                                    <?php else: ?>
                                                        <span class="mc-status-dot incomplete" title="Not Started"></span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <div class="mc-actions">
                                                    <?php if ($status === 'Pending'): ?>
                                                        <!-- Pending user actions: Copy link and Resend -->
                                                        <button class="mc-action-btn mc-tooltip"
                                                            onclick="copyInviteLink('<?php echo esc_url($invite_link); ?>', this)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                                            </svg>
                                                            <span class="tooltiptext">Copy Invite Link</span>
                                                        </button>
                                                        <form method="post" style="display:inline;"
                                                            onsubmit="return confirm('Resend invite to <?php echo esc_attr($email); ?>?');">
                                                            <input type="hidden" name="mc_resend_invite"
                                                                value="<?php echo esc_attr($email); ?>">
                                                            <button type="submit" class="mc-action-btn mc-tooltip">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                                                </svg>
                                                                <span class="tooltiptext">Resend Invite</span>
                                                            </button>
                                                        </form>
                                                        <!--  1.5 Edit Email (New) -->
                                                        <button type="button" class="mc-action-btn mc-tooltip"
                                                            onclick="openEmailEditModal('<?php echo esc_js($email); ?>')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                            </svg>
                                                            <span class="tooltiptext">Edit Email</span>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($status !== 'Pending' && $status !== 'Archived'): ?>
                                                        <!-- Active user actions in logical order -->

                                                        <!-- 1. Manage Role -->
                                                        <button class="mc-action-btn mc-tooltip" onclick='openEmployeeModal(<?php echo htmlspecialchars(json_encode([
                                                            "id" => $employee_user->ID,
                                                            "name" => $employee_user->display_name,
                                                            "context" => get_user_meta($employee_user->ID, "mc_employee_role_context", true) ?: []
                                                        ]), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                            </svg>
                                                            <span class="tooltiptext">Manage Role</span>
                                                        </button>

                                                        <!-- 2. Generate Experiments -->
                                                        <button class="mc-action-btn mc-tooltip"
                                                            onclick="generateExperiments(<?php echo $employee_user->ID; ?>, this)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                                                            </svg>
                                                            <span class="tooltiptext">Generate Experiments</span>
                                                        </button>

                                                        <!-- 3. View Experiments -->
                                                        <button class="mc-action-btn mc-tooltip" onclick='openExperimentsModal(<?php echo htmlspecialchars(json_encode([
                                                            "id" => $employee_user->ID,
                                                            "name" => $employee_user->display_name
                                                        ]), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                            <span class="tooltiptext">View Experiments</span>
                                                        </button>

                                                        <!-- 4. View/Generate Report -->
                                                        <?php
                                                        $analysis = get_user_meta($employee_user->ID, 'mc_assessment_analysis', true);
                                                        $strain_results = get_user_meta($employee_user->ID, 'strain_index_results', true);

                                                        if ($analysis):
                                                            ?>
                                                            <button class="mc-action-btn mc-tooltip" onclick='openAnalysisModal(<?php echo htmlspecialchars(json_encode([
                                                                "id" => $employee_user->ID,
                                                                "name" => $employee_user->display_name,
                                                                "analysis" => $analysis,
                                                                "strain_results" => $strain_results
                                                            ]), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                </svg>
                                                                <span class="tooltiptext">View Report</span>
                                                            </button>
                                                        <?php else:
                                                            // Check if employee has completed at least one assessment
                                                            $has_results = false;
                                                            if (class_exists('MC_Funnel')) {
                                                                $results = MC_Funnel::get_all_assessment_results($employee_user->ID);
                                                                $has_results = !empty($results);
                                                            }
                                                            if ($has_results):
                                                                ?>
                                                                <?php
                                                                $role_ctx = get_user_meta($employee_user->ID, 'mc_employee_role_context', true);
                                                                // $has_role = !empty($role_ctx) && !empty($role_ctx['role']);
                                                                $has_role = !empty($role_ctx) && !empty($role_ctx['role']);
                                                                ?>
                                                                <button class="mc-action-btn mc-tooltip"
                                                                    onclick="generateAnalysisReport(<?php echo $employee_user->ID; ?>, this)"
                                                                    id="generate-report-btn-<?php echo $employee_user->ID; ?>"
                                                                    data-has-role="<?php echo $has_role ? 'true' : 'false'; ?>"
                                                                    data-emp-name="<?php echo esc_attr($employee_user->display_name); ?>"
                                                                    data-emp-context='<?php echo htmlspecialchars(json_encode(["id" => $employee_user->ID, "name" => $employee_user->display_name, "context" => $role_ctx ?: []]), ENT_QUOTES, 'UTF-8'); ?>'>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                        stroke-width="1.5" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                    </svg>
                                                                    <span class="tooltiptext">Generate Report</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- Archive/Restore button comes LAST -->
                                                    <?php if ($status !== 'Archived'): ?>
                                                        <form method="post" style="display:inline;"
                                                            onsubmit="return confirm('Archive <?php echo esc_attr($email); ?>?');">
                                                            <input type="hidden" name="mc_archive_user"
                                                                value="<?php echo esc_attr($email); ?>">
                                                            <button type="submit" class="mc-action-btn mc-tooltip">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                                                </svg>
                                                                <span class="tooltiptext">Archive</span>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" style="display:inline;"
                                                            onsubmit="return confirm('Restore <?php echo esc_attr($email); ?>?');">
                                                            <input type="hidden" name="mc_restore_user"
                                                                value="<?php echo esc_attr($email); ?>">
                                                            <button type="submit" class="mc-action-btn mc-tooltip">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                                                </svg>
                                                                <span class="tooltiptext">Restore</span>
                                                            </button>
                                                        </form>
                                                        <form method="post" style="display:inline;"
                                                            onsubmit="return confirm('Permanently delete <?php echo esc_attr($email); ?>? This cannot be undone.');">
                                                            <input type="hidden" name="mc_delete_user"
                                                                value="<?php echo esc_attr($email); ?>">
                                                            <button type="submit" class="mc-action-btn mc-tooltip" style="color: #ef4444;">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                                </svg>
                                                                <span class="tooltiptext">Delete</span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Workplace Context Modal -->
        <div id="mc-workplace-modal" class="mc-modal">
            <div class="mc-modal-content">
                <span class="mc-close" onclick="closeWorkplaceModal()">&times;</span>
                <h2>Workplace Settings</h2>
                <form method="post">
                    <input type="hidden" name="mc_save_workplace_context" value="1">
                    <div class="mc-form-group">
                        <label>Company Name <span class="mc-required">*</span></label>
                        <input type="text" name="mc_company_name_update" value="<?php echo esc_attr($company_name); ?>"
                            placeholder="e.g. Acme Corp" required>
                    </div>
                    <div class="mc-form-group">
                        <label>Industry / Sector <span class="mc-required">*</span></label>
                        <input type="text" name="mc_industry" value="<?php echo esc_attr($wp_industry); ?>"
                            placeholder="e.g. Tech, Healthcare, Retail" required>
                    </div>
                    <div class="mc-form-group">
                        <label>Company Values <span class="mc-required">*</span></label>
                        <textarea name="mc_values" rows="3" placeholder="e.g. Innovation, Integrity, Customer Obsession"
                            required><?php echo esc_textarea($wp_values); ?></textarea>
                    </div>
                    <div class="mc-form-group">
                        <label>Company Culture / About <span class="mc-required">*</span></label>
                        <textarea name="mc_culture" rows="3" placeholder="Briefly describe your work environment..."
                            required><?php echo esc_textarea($wp_culture); ?></textarea>
                    </div>
                    <button type="submit" class="mc-button">Save Context</button>
                </form>
            </div>
        </div>

        <!-- Modals are now rendered in the footer -->
        <?php
        add_action('wp_footer', [__CLASS__, 'render_modals']);
        ?>
        </div>

        <!-- Experiments Modal -->
        <div id="mc-experiments-modal" class="mc-modal">
            <div class="mc-modal-content" style="max-width: 800px;">
                <span class="mc-close" onclick="closeExperimentsModal()">&times;</span>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 id="mc-exp-modal-title" style="margin:0;">Experiments</h2>
                    <button id="mc-modal-generate-btn" class="mc-button">Generate New</button>
                </div>
                <div id="mc-experiments-list">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>

        <style>
            .mc-dashboard-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                font-family: 'Inter', system-ui, sans-serif;
            }

            .mc-dashboard-header-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
                gap: 20px;
            }

            .mc-team-table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
                overflow: hidden;
            }

            .mc-team-table th,
            .mc-team-table td {
                padding: 15px 20px;
                text-align: left;
                border-bottom: 1px solid #e2e8f0;
            }

            .mc-team-table th {
                background: #f1f5f9;
                font-weight: 700;
                color: #475569;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .mc-employee-email {
                display: block;
                font-weight: 600;
                color: #0f172a;
            }

            .mc-employee-name {
                font-size: 0.85rem;
                color: #64748b;
            }

            .mc-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.75rem;
                font-weight: 700;
            }

            .mc-badge.registered {
                background: #dcfce7;
                color: #15803d;
                border: 1px solid #bbf7d0;
            }

            .mc-badge.pending {
                background: #fef9c3;
                color: #a16207;
                border: 1px solid #fde047;
            }

            .mc-badge.all-assessments-complete {
                background: #dbeafe;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }

            .mc-status-dot {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                font-size: 16px;
            }

            .mc-status-dot.completed {
                background: #22c55e;
                color: white;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }

            .mc-status-dot.incomplete {
                background: #f1f5f9;
                border: 2px solid #cbd5e1;
            }

            .mc-status-dot.pending {
                background: #fff;
                border: 2px dashed #cbd5e1;
            }

            .mc-text-center {
                text-align: center !important;
            }

            .mc-empty-state {
                text-align: center;
                padding: 40px;
                background: #f8fafc;
                border-radius: 8px;
                color: #64748b;
                border: 1px dashed #cbd5e1;
            }

            .mc-actions {
                display: flex;
                gap: 8px;
            }

            .mc-action-btn {
                background: #fff;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                cursor: pointer;
                padding: 6px;
                color: #64748b;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
            }

            .mc-action-btn:hover {
                color: #2563eb;
                border-color: #2563eb;
                background: #eff6ff;
            }

            .mc-action-btn svg {
                width: 18px;
                height: 18px;
                display: block;
                /* Ensures no weird spacing */
            }

            /* Fix for the analysis button spacing */
            .mc-actions-secondary .mc-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                min-width: 32px;
                min-height: 32px;
                max-width: 32px;
                max-height: 32px;
                padding: 0;
                /* Remove padding to let flex center the SVG */
                overflow: hidden;
                /* Prevent content from expanding button */
            }


            .mc-tooltip {
                position: relative;
            }


            .mc-tooltip .tooltiptext {
                visibility: hidden;
                width: 120px;
                background-color: #1e293b;
                color: #fff;
                text-align: center;
                border-radius: 6px;
                padding: 6px 8px;
                position: absolute;
                z-index: 10;
                bottom: 130%;
                left: 50%;
                transform: translateX(-50%);
                opacity: 0;
                transition: opacity 0.2s;
                font-size: 12px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                pointer-events: none;
            }

            .mc-tooltip .tooltiptext::after {
                content: "";
                position: absolute;
                top: 100%;
                left: 50%;
                margin-left: -5px;
                border-width: 5px;
                border-style: solid;
                border-color: #1e293b transparent transparent transparent;
            }

            .mc-tooltip:hover .tooltiptext {
                visibility: visible;
                opacity: 1;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            .spin {
                animation: spin 1s linear infinite;
            }

            .mc-filters {
                margin-bottom: 20px;
                display: flex;
                gap: 10px;
            }

            .mc-filter-btn {
                background: #fff;
                border: 1px solid #cbd5e1;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                color: #64748b;
                transition: all 0.2s;
            }

            .mc-filter-btn:hover {
                background: #f8fafc;
                color: #475569;
            }

            .mc-filter-btn.active {
                background: #2563eb;
                color: #fff;
                border-color: #2563eb;
            }

            .mc-team-row {
                transition: all 0.3s ease;
            }

            /* Modal Styles */
            .mc-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.4);
            }

            .mc-modal-content {
                background-color: #fefefe;
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 90%;
                max-width: 500px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }



            .spin {
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }

                to {
                    transform: rotate(360deg);
                }
            }

            .mc-form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #334155;
            }

            .mc-form-group input[type="text"],
            .mc-form-group textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                font-family: inherit;
            }

            .mc-button.secondary {
                background: #fff;
                color: #2563eb;
                border: 1px solid #2563eb;
            }

            .mc-button.secondary:hover {
                background: #eff6ff;
            }

            .mc-exp-actions {
                margin-bottom: 20px;
            }
        </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const MC_COMPANY_NAME = '<?php echo esc_js($company_name); ?>';
            const MC_IS_ADMIN = <?php echo (current_user_can('manage_options') || (function_exists('current_user_switched') && current_user_switched())) ? 'true' : 'false'; ?>;

            function downloadReportPDF(btn) {
                const element = document.getElementById('mc-report-content');
                const originalText = btn.innerHTML;
                btn.innerHTML = 'Downloading...';
                btn.disabled = true;

                // Hide buttons for PDF
                const headerRight = element.querySelector('.mc-header-right');
                if (headerRight) headerRight.style.display = 'none';

                // Calculate dimensions for single page
                const width = element.scrollWidth;
                const height = element.scrollHeight;

                const opt = {
                    margin: 0,
                    filename: 'Employee_Analysis_Report.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, letterRendering: true },
                    // Use custom format [width, height] in pixels to create one long page
                    jsPDF: { unit: 'px', format: [width, height + 40], orientation: 'portrait' }
                };

                html2pdf().set(opt).from(element).save().then(function () {
                    // Restore buttons
                    if (headerRight) headerRight.style.display = 'flex';
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }).catch(function (err) {
                    console.error(err);
                    alert('Error generating PDF');
                    if (headerRight) headerRight.style.display = 'flex';
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }


            function openWorkplaceModal() {
                document.getElementById('mc-workplace-modal').style.display = 'block';
            }
            function closeWorkplaceModal() {
                document.getElementById('mc-workplace-modal').style.display = 'none';
            }

            function openEmployeeModal(data) {
                console.log('DEBUG: openEmployeeModal called with', data);
                const modal = document.getElementById('mc-employee-modal');
                if (modal) {
                    modal.style.display = 'block';
                    modal.style.zIndex = '9999'; // Safety net
                }

                // Force opacity on content just in case
                const content = modal ? modal.querySelector('.mc-modal-content') : null;
                if (content) {
                    content.style.opacity = '1';
                    content.style.display = 'block';
                }
                // Restore form population logic
                document.getElementById('mc_emp_id_input').value = data.id;
                document.getElementById('mc-emp-name-display').textContent = 'For: ' + data.name;

                // Safety check for context
                const context = data.context || {};
                document.getElementById('mc_role_input').value = context.role || '';
                document.getElementById('mc_resp_input').value = context.responsibilities || '';
            }
            function closeEmployeeModal() {
                document.getElementById('mc-employee-modal').style.display = 'none';
            }

            let currentExpEmployeeId = null;

            function closeExperimentsModal() {
                document.getElementById('mc-experiments-modal').style.display = 'none';
                currentExpEmployeeId = null;
            }

            function openContextModal(empId) {
                const modal = document.getElementById('mc-context-modal');
                const genBtn = document.getElementById('mc-modal-generate-btn'); // Changed from mc-generate-btn

                // Pre-fill values
                const wrapper = document.getElementById('mc-dashboard-wrapper');
                const defaultRole = wrapper ? wrapper.dataset.defaultRole : '';
                const defaultWorkplace = wrapper ? wrapper.dataset.defaultWorkplace : '';

                document.getElementById('mc-context-role').value = genBtn.dataset.roleContext || defaultRole || '';
                document.getElementById('mc-context-workplace').value = genBtn.dataset.workplaceContext || defaultWorkplace || '';

                modal.style.display = 'block'; // Changed from 'flex' to 'block' to match original

                const confirmBtn = document.getElementById('mc-context-confirm-btn'); // Changed from mc-confirm-generate-btn
                confirmBtn.onclick = function () {
                    const roleCtx = document.getElementById('mc-context-role').value;
                    const workplaceCtx = document.getElementById('mc-context-workplace').value;
                    submitContextAndGenerate(empId, roleCtx, workplaceCtx, confirmBtn);
                };
            }

            function closeContextModal() {
                document.getElementById('mc-context-modal').style.display = 'none';
            }

            function openAnalysisModal(data) {
                console.log('DEBUG: openAnalysisModal called - VERSION 1.2.9');
                const modal = document.getElementById('mc-analysis-modal');
                if (modal) {
                    modal.style.zIndex = '9999';
                    modal.style.setProperty('display', 'block', 'important');
                    modal.style.setProperty('visibility', 'visible', 'important');
                    modal.style.setProperty('opacity', '1', 'important');

                    // Force content visibility
                    const content = modal.querySelector('.mc-modal-content');
                    if (content) {
                        content.style.setProperty('display', 'block', 'important');
                        content.style.setProperty('opacity', '1', 'important');
                        content.style.setProperty('visibility', 'visible', 'important');
                        // content.style.setProperty('z-index', '2147483648', 'important'); // Content higher - REMOVED for stacking
                    }
                }
                // Reset loading state
                const loadingDiv = document.getElementById('mc-report-loading');
                const contentDiv = document.getElementById('mc-report-content');
                if (loadingDiv) loadingDiv.style.display = 'none';
                if (contentDiv) contentDiv.style.display = 'block';

                // Basic Info
                document.getElementById('mc-analysis-name').textContent = data.name;

                // Company Name
                const companyLabel = document.getElementById('mc-analysis-company');
                if (companyLabel) {
                    companyLabel.textContent = (typeof MC_COMPANY_NAME !== 'undefined' && MC_COMPANY_NAME) ? MC_COMPANY_NAME : 'CONFIDENTIAL REPORT';
                }

                // Suitability/Fit Score
                const scoreBadge = document.getElementById('mc-suitability-score-badge');
                const scoreValue = document.getElementById('mc-suitability-score-value');

                // Handle new overall_fit structure or fallback to old suitability_score
                let fitScore = data.analysis.suitability_score;
                let fitRationale = '';

                if (data.analysis.overall_fit) {
                    fitScore = data.analysis.overall_fit.score;
                    fitRationale = data.analysis.overall_fit.rationale;
                }
                if (fitScore) {
                    // Update Hero Fit Card
                    const heroFitScore = document.getElementById('mc-hero-fit-score');
                    const heroFitRationale = document.getElementById('mc-hero-fit-rationale');
                    if (heroFitScore) heroFitScore.textContent = fitScore;
                    // Only show specific AI rationale to Admins (Debug Info)
                    if (heroFitRationale) {
                        // Only show specific AI rationale to Admins (Debug Info)
                        if (typeof MC_IS_ADMIN !== 'undefined' && MC_IS_ADMIN) {
                            heroFitRationale.innerHTML = '<strong>AI Rationale (Admin Only):</strong> ' + (fitRationale || 'Analysis based on provided role & workplace context.');
                            heroFitRationale.style.display = 'block';
                        } else {
                            heroFitRationale.textContent = 'Based on role & workplace context.';
                        }
                    }

                    // Color coding logic...
                    const score = parseInt(fitScore);
                    if (score >= 80) {
                        if (heroFitScore) heroFitScore.style.color = '#166534';
                    } else if (score >= 60) {
                        if (heroFitScore) heroFitScore.style.color = '#854d0e';
                    } else {
                        if (heroFitScore) heroFitScore.style.color = '#991b1b';
                    }
                }
                // --- HERO SECTION ---
                const execSnapshot = data.analysis.executive_snapshot || {};

                // Context Summary
                const contextSummary = document.getElementById('mc-hero-context-summary');
                if (contextSummary) {
                    contextSummary.textContent = execSnapshot.context_summary || 'Analysis based on provided role and workplace context.';
                }

                // Top Strengths
                const strengthsList = document.getElementById('mc-hero-strengths');
                strengthsList.innerHTML = '';
                if (data.analysis.executive_snapshot.top_strengths) {
                    data.analysis.executive_snapshot.top_strengths.forEach(str => {
                        const li = document.createElement('li');
                        li.textContent = str;
                        strengthsList.appendChild(li);
                    });
                }

                // Potential Blindspots (Weaknesses)
                const weaknessesList = document.getElementById('mc-hero-weaknesses');
                if (weaknessesList) {
                    weaknessesList.innerHTML = '';
                    if (data.analysis.executive_snapshot.top_weaknesses) {
                        data.analysis.executive_snapshot.top_weaknesses.forEach(wk => {
                            const li = document.createElement('li');
                            li.textContent = wk;
                            weaknessesList.appendChild(li);
                        });
                    } else {
                        // Fallback if no top_weaknesses (e.g. old report)
                        const li = document.createElement('li');
                        li.textContent = "Regenerate report to see blindspots";
                        li.style.fontStyle = "italic";
                        li.style.color = "#94a3b8";
                        li.style.background = "none";
                        li.style.boxShadow = "none";
                        li.style.border = "none";
                        li.style.paddingLeft = "0";
                        weaknessesList.appendChild(li);
                    }
                }

                // Key Motivators
                const heroMotivators = document.getElementById('mc-hero-motivators');
                if (heroMotivators) {
                    heroMotivators.innerHTML = '';
                    (execSnapshot.key_motivators || []).forEach(m => {
                        heroMotivators.innerHTML += `<li>${m}</li>`;
                    });
                }
                // Leadership Potential (Moved to Hero)
                const leadRating = document.getElementById('mc-hero-leadership-rating');
                const leadSummary = document.getElementById('mc-hero-leadership-summary');
                const leadership = data.analysis.leadership_potential || {};

                if (leadRating) {
                    leadRating.textContent = leadership.rating || 'Emerging';
                    // Style badge based on rating
                    const r = (leadership.rating || '').toLowerCase();
                    if (r.includes('strong') || r.includes('high')) {
                        leadRating.className = 'mc-badge registered'; // Greenish
                    } else if (r.includes('emerging')) {
                        leadRating.className = 'mc-badge pending'; // Yellowish
                    } else {
                        leadRating.className = 'mc-badge'; // Gray
                    }
                }
                if (leadSummary) leadSummary.textContent = leadership.summary || '--';

                // Ideal Conditions (Moved from Hero to Team section)
                const conditions = document.getElementById('mc-hero-conditions');
                if (conditions) conditions.textContent = execSnapshot.ideal_conditions || '--';


                // --- COMMUNICATION PLAYBOOK ---
                const comms = data.analysis.communication_playbook || {};

                const commDo = document.getElementById('mc-comm-do');
                if (commDo) {
                    commDo.innerHTML = '';
                    (comms.do || []).forEach(item => commDo.innerHTML += `<li>${item}</li>`);
                }

                const commAvoid = document.getElementById('mc-comm-avoid');
                if (commAvoid) {
                    commAvoid.innerHTML = '';
                    (comms.avoid || []).forEach(item => commAvoid.innerHTML += `<li>${item}</li>`);
                }

                const commFormat = document.getElementById('mc-comm-format');
                if (commFormat) commFormat.textContent = comms.format || '--';


                // --- MOTIVATION & WORK STYLE ---
                const motiv = data.analysis.motivation_profile || {};
                const work = data.analysis.work_style || {};

                const energizers = document.getElementById('mc-motiv-energizers');
                if (energizers) {
                    energizers.innerHTML = '';
                    (motiv.energizers || []).forEach(item => energizers.innerHTML += `<li>${item}</li>`);
                }

                const drainers = document.getElementById('mc-motiv-drainers');
                if (drainers) {
                    drainers.innerHTML = '';
                    (motiv.drainers || []).forEach(item => drainers.innerHTML += `<li>${item}</li>`);
                }

                const workApproach = document.getElementById('mc-work-approach');
                if (workApproach) workApproach.textContent = work.approach || '--';

                const workBest = document.getElementById('mc-work-best');
                if (workBest) {
                    workBest.innerHTML = '';
                    (work.best_when || []).forEach(item => workBest.innerHTML += `<li>${item}</li>`);
                }

                const workStruggle = document.getElementById('mc-work-struggle');
                if (workStruggle) {
                    workStruggle.innerHTML = '';
                    (work.struggles_when || []).forEach(item => workStruggle.innerHTML += `<li>${item}</li>`);
                }

                // --- STRAIN INDEX SECTION ---
                // --- STRAIN INDEX SECTION ---
                const strainSection = document.getElementById('mc-strain-section');
                try {
                    console.log('DEBUG: Strain Index Logic Start', data.strain_results);

                    if (data.strain_results && data.strain_results.strain_index) {
                        const si = data.strain_results.strain_index;
                        const norm = si.normalized || {};
                        const overall = si.overall_strain !== undefined ? si.overall_strain : 0;

                        console.log('DEBUG: Strain Index Data Found', { si, norm, overall });

                        if (strainSection) {
                            strainSection.style.display = 'block';
                            console.log('DEBUG: Strain Section Display Set to Block');
                        } else {
                            console.error('DEBUG: Strain Section Element NOT Found');
                        }

                        // Update Overall Score
                        const overallScoreEl = document.getElementById('mc-strain-overall-score');
                        if (overallScoreEl) overallScoreEl.textContent = (overall * 100).toFixed(1);

                        // Update Gauge
                        const gaugeFill = document.getElementById('mc-strain-gauge-fill');
                        if (gaugeFill) {
                            // Map 0-1 to 0-180 degrees
                            // 0 = -180deg (empty), 1 = 0deg (full)
                            const rotation = -180 + (overall * 180);
                            gaugeFill.style.transform = `rotate(${rotation}deg)`;

                            // Color based on severity
                            if (overall < 0.33) gaugeFill.style.backgroundColor = '#22c55e'; // Green (Low Strain)
                            else if (overall < 0.66) gaugeFill.style.backgroundColor = '#f59e0b'; // Yellow (Med Strain)
                            else gaugeFill.style.backgroundColor = '#ef4444'; // Red (High Strain)
                        }

                        // Update Sub-Indices
                        const updateBar = (key, val) => {
                            const valEl = document.getElementById(`mc-strain-${key}-val`);
                            const barEl = document.getElementById(`mc-strain-${key}-bar`);
                            if (valEl) valEl.textContent = (val * 100).toFixed(0) + '%';
                            if (barEl) {
                                barEl.style.width = (val * 100) + '%';
                                // Color logic
                                if (val < 0.33) barEl.style.backgroundColor = '#22c55e';
                                else if (val < 0.66) barEl.style.backgroundColor = '#f59e0b';
                                else barEl.style.backgroundColor = '#ef4444';
                            }
                        };

                        updateBar('rumination', norm.rumination || 0);
                        updateBar('avoidance', norm.avoidance || 0);
                        updateBar('flood', norm.emotional_flood || 0);

                    } else {
                        console.log('DEBUG: No Strain Index Data');
                        if (strainSection) strainSection.style.display = 'none';
                    }
                } catch (e) {
                    console.error('DEBUG: Error in Strain Index Logic', e);
                    // Ensure it doesn't stay in a broken state if possible, or maybe hide it?
                    // if (strainSection) strainSection.style.display = 'none'; 
                }


                // --- COACHING RECOMMENDATIONS (Cards) ---
                const coachingContainer = document.getElementById('mc-coaching-container');
                if (coachingContainer) {
                    coachingContainer.innerHTML = '';
                    (data.analysis.coaching_recommendations || []).forEach(rec => {
                        const card = document.createElement('div');
                        card.className = 'mc-card mc-coaching-card';
                        card.innerHTML = `
                            <h4>${rec.title}</h4>
                            <p class="mc-card-rationale">${rec.rationale}</p>
                            <div class="mc-card-example">
                                <strong>Try:</strong> ${rec.example}
                            </div>
                        `;
                        coachingContainer.appendChild(card);
                    });
                }


                // --- GROWTH EDGES (Cards) ---
                const growthContainer = document.getElementById('mc-growth-container');
                if (growthContainer) {
                    growthContainer.innerHTML = '';
                    (data.analysis.growth_edges || []).forEach(edge => {
                        const card = document.createElement('div');
                        card.className = 'mc-card mc-growth-card';
                        card.innerHTML = `
                            <div class="mc-card-header">
                                <h4>${edge.assignment}</h4>
                                <span class="mc-badge mc-badge-${(edge.risk_level || 'low').toLowerCase()}">${edge.risk_level} Risk</span>
                            </div>
                            <p><strong>Action:</strong> ${edge.action}</p>
                            <p class="mc-card-meta">Builds: ${edge.capacity}</p>
                        `;
                        growthContainer.appendChild(card);
                    });
                }


                // --- TEAM & LEADERSHIP ---
                const team = data.analysis.team_collaboration || {};
                const lead = data.analysis.leadership_potential || {};

                if (document.getElementById('mc-team-thrives')) document.getElementById('mc-team-thrives').textContent = team.thrives_with || '--';
                if (document.getElementById('mc-team-friction')) document.getElementById('mc-team-friction').textContent = team.friction_with || '--';

                // Leadership Potential Spectrum
                const leadershipRating = (data.analysis.leadership_potential && data.analysis.leadership_potential.rating) ? data.analysis.leadership_potential.rating.toLowerCase() : '';
                const leadershipSummary = (data.analysis.leadership_potential && data.analysis.leadership_potential.summary) ? data.analysis.leadership_potential.summary : 'No data available.';

                document.getElementById('mc-hero-leadership-summary').textContent = leadershipSummary;

                // Reset Spectrum
                document.querySelectorAll('.mc-spectrum-segment').forEach(el => el.classList.remove('active'));

                // Activate Segment
                // Activate Segment
                if (leadershipRating.includes('rockstar')) {
                    document.querySelector('.mc-spectrum-segment[data-level="rockstar"]')?.classList.add('active');
                } else if (leadershipRating.includes('strong') || leadershipRating.includes('high')) {
                    document.querySelector('.mc-spectrum-segment[data-level="strong"]')?.classList.add('active');
                } else if (leadershipRating.includes('developing')) {
                    document.querySelector('.mc-spectrum-segment[data-level="developing"]')?.classList.add('active');
                } else if (leadershipRating.includes('emerging')) {
                    document.querySelector('.mc-spectrum-segment[data-level="emerging"]')?.classList.add('active');
                } else {
                    // Default to Individual (covers "Individual Focus" or fallback)
                    document.querySelector('.mc-spectrum-segment[data-level="individual"]')?.classList.add('active');
                }

                // --- MANAGER FAST GUIDE (Sidebar) ---
                const guide = data.analysis.manager_fast_guide || {};

                const guideStrengths = document.getElementById('mc-guide-strengths');
                if (guideStrengths) {
                    guideStrengths.innerHTML = '';
                    (guide.strengths || []).forEach(item => guideStrengths.innerHTML += `<li>${item}</li>`);
                }

                const guideMotivators = document.getElementById('mc-guide-motivators');
                if (guideMotivators) {
                    guideMotivators.innerHTML = '';
                    (guide.motivators || []).forEach(item => guideMotivators.innerHTML += `<li>${item}</li>`);
                }

                const guideComm = document.getElementById('mc-guide-comm');
                if (guideComm) {
                    guideComm.innerHTML = '';
                    (guide.communication || []).forEach(item => guideComm.innerHTML += `<li>${item}</li>`);
                }

                const guideCoaching = document.getElementById('mc-guide-coaching');
                if (guideCoaching) {
                    guideCoaching.innerHTML = '';
                    (guide.coaching_moves || []).forEach(item => guideCoaching.innerHTML += `<li>${item}</li>`);
                }

                if (document.getElementById('mc-guide-growth')) document.getElementById('mc-guide-growth').textContent = guide.growth_edge || '--';


                // --- CONFLICT & STRESS ---
                const stress = data.analysis.conflict_stress || {};
                if (document.getElementById('mc-stress-handling')) document.getElementById('mc-stress-handling').textContent = stress.handling || '--';
                if (document.getElementById('mc-stress-signs')) document.getElementById('mc-stress-signs').textContent = stress.signs || '--';
                if (document.getElementById('mc-stress-support')) document.getElementById('mc-stress-support').textContent = stress.support || '--';


                // --- REGENERATE BUTTON HANDLER ---
                const oldRegenBtn = document.getElementById('mc-regenerate-report-btn');
                if (oldRegenBtn) {
                    const regenBtn = oldRegenBtn.cloneNode(true);
                    oldRegenBtn.parentNode.replaceChild(regenBtn, oldRegenBtn);
                    regenBtn.disabled = false;
                    regenBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Regenerate
                    `;
                    regenBtn.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        generateAnalysisReport(data.id, this);
                    };
                }

                // --- DEEP DIVE BUTTON HANDLER ---
                const deepDiveBtn = document.getElementById('mc-strain-deep-dive-btn');
                if (deepDiveBtn) {
                    deepDiveBtn.onclick = function (e) {
                        e.preventDefault();
                        openStrainDetailsModal(data);
                    };
                }

                // Show Modal
                // Show Modal
                // Show Modal Phase 2 (Force again)
                if (modal) {
                    modal.style.zIndex = '9999';
                    modal.style.setProperty('display', 'block', 'important');
                    // Add click outside listener
                    setTimeout(() => {
                        window.addEventListener('click', closeAnalysisOnClickOutside);
                    }, 10);
                }
            }

            function closeAnalysisOnClickOutside(e) {
                const modal = document.getElementById('mc-analysis-modal');
                if (e.target == modal) {
                    closeAnalysisModal();
                }
            }

            function closeAnalysisModal() {
                document.getElementById('mc-analysis-modal').style.display = 'none';
                window.removeEventListener('click', closeAnalysisOnClickOutside);
            }

            function generateAnalysisReport(userId, btn) {
                // Check for Role Context first
                if (btn.getAttribute('data-has-role') === 'false') {
                    // Automatically open the Manage Role modal
                    const dataVal = btn.getAttribute('data-emp-context');
                    if (dataVal) {
                        try {
                            openEmployeeModal(JSON.parse(dataVal));
                            // Optional: Highlight the "Save & Generate" button
                            const saveGenBtn = document.getElementById('mc-save-generate-btn');
                            if (saveGenBtn) saveGenBtn.focus();
                        } catch (e) {
                            console.error('Error parsing context data', e);
                            alert('Please define the Role & Responsibilities first.');
                        }
                    } else {
                        alert('Please define the Role & Responsibilities first.');
                    }
                    return;
                }

                const originalHTML = btn.innerHTML;
                // Update button state with spinner and text
                // Update button state with spinner and compact text
                btn.innerHTML = '<svg class="spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>';
                btn.disabled = true;

                const data = new URLSearchParams();
                data.append('action', 'mc_generate_analysis_report');
                data.append('user_id', userId);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // Update the modal with the new data
                            openAnalysisModal(result.data);

                            // Reset button state
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                        } else {
                            alert(result.data.message || 'Failed to generate report. Please try again.');
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    });
            }

            function submitContextAndGenerate(empId, roleCtx, workplaceCtx, btn) {
                const originalText = btn.textContent;
                btn.textContent = 'Generating...';
                btn.disabled = true;

                const data = new URLSearchParams();
                data.append('action', 'mc_employer_generate_experiments');
                data.append('employee_id', empId);
                data.append('role_context', roleCtx);
                data.append('workplace_context', workplaceCtx);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            closeContextModal();
                            if (currentExpEmployeeId && currentExpEmployeeId == empId) {
                                loadExperiments(empId);
                            }
                            alert(res.data.message || 'Experiments generated successfully!');
                        } else {
                            alert(res.data.message || 'Error generating experiments.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Network error.');
                    })
                    .finally(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
            }

            function generateExperiments(empId, btn) {
                openContextModal(empId);
            }

            function copyInviteLink(link, btn) {
                if (!link) {
                    console.error('No link to copy');
                    return;
                }

                // Try modern API first
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(link).then(function () {
                        showCopySuccess(btn);
                    }, function (err) {
                        console.error('Could not copy text: ', err);
                        fallbackCopyTextToClipboard(link, btn);
                    });
                } else {
                    // Fallback
                    fallbackCopyTextToClipboard(link, btn);
                }
            }

            function fallbackCopyTextToClipboard(text, btn) {
                var textArea = document.createElement("textarea");
                textArea.value = text;

                // Avoid scrolling to bottom
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.position = "fixed";

                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    var successful = document.execCommand('copy');
                    if (successful) {
                        showCopySuccess(btn);
                    } else {
                        console.error('Fallback: Copying text command was unsuccessful');
                    }
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }

                document.body.removeChild(textArea);
            }

            function showCopySuccess(btn) {
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                btn.classList.add('success');
                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.classList.remove('success');
                }, 2000);
            }

            function filterTeam(status, btn) {
                // Update active button
                document.querySelectorAll('.mc-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Filter rows
                const rows = document.querySelectorAll('.mc-team-row');
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    if (status === 'all') {
                        // Show all except archived
                        if (rowStatus === 'archived') {
                            row.style.display = 'none';
                        } else {
                            row.style.display = '';
                        }
                    } else if (status === 'active') {
                        // Active shows both 'registered' and 'all-assessments-complete'
                        if (rowStatus === 'registered' || rowStatus === 'all-assessments-complete') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    } else if (status === 'complete') {
                        // Complete shows only 'all-assessments-complete'
                        if (rowStatus === 'all-assessments-complete') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    } else if (status === 'archived') {
                        // Archived shows only archived
                        if (rowStatus === 'archived') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    } else {
                        // For other statuses (pending, etc.), match exactly
                        if (rowStatus === status) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            }

            // Initialize filter to hide archived by default
            document.addEventListener('DOMContentLoaded', function () {
                const rows = document.querySelectorAll('.mc-team-row');
                rows.forEach(row => {
                    if (row.getAttribute('data-status') === 'archived') {
                        row.style.display = 'none';
                    }
                });
            });
            function openExperimentsModal(data) {
                currentExpEmployeeId = data.id;
                document.getElementById('mc-experiments-modal').style.display = 'block';
                document.getElementById('mc-exp-modal-title').textContent = 'Experiments for ' + data.name;

                // Update Generate button in modal to point to this employee
                const genBtn = document.getElementById('mc-modal-generate-btn');
                if (genBtn) {
                    genBtn.onclick = function () { generateExperiments(data.id, this); };
                }

                loadExperiments(data.id);
            }

            function loadExperiments(empId) {
                const list = document.getElementById('mc-experiments-list');
                list.innerHTML = '<p>Loading...</p>';

                const data = new URLSearchParams();
                data.append('action', 'mc_employer_get_experiments');
                data.append('employee_id', empId);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            renderExperimentsList(res.data.experiments, empId);
                        } else {
                            list.innerHTML = '<p>Error loading experiments.</p>';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        list.innerHTML = '<p>Network error.</p>';
                    });
            }

            function renderExperimentsList(experiments, empId) {
                const list = document.getElementById('mc-experiments-list');
                if (!experiments || experiments.length === 0) {
                    list.innerHTML = '<p>No experiments generated yet.</p>';
                    return;
                }

                let html = '<div class="mc-exp-list">';
                experiments.forEach(exp => {
                    const isShared = exp.status === 'assigned';
                    const statusLabel = isShared ? '<span class="mc-badge success">Shared</span>' : '<span class="mc-badge warning">Draft</span>';
                    const toggleLabel = isShared ? 'Unshare' : 'Share';
                    const toggleAction = isShared ? 'draft' : 'assigned';

                    html += `
                        <div class="mc-exp-item" style="border:1px solid #eee; padding:10px; margin-bottom:10px; border-radius:4px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <strong style="font-size:1.1em;">${exp.title}</strong>
                                <div>${statusLabel}</div>
                            </div>
                            <p style="margin:5px 0; color:#666; font-size:0.9em;">${exp.lens} | ${exp.micro_description}</p>
                            ${exp.why_this_fits_you ? `<p style="margin-top:8px; font-style:italic; color:#64748b; font-size:0.9em; background:#f8fafc; padding:8px; border-radius:4px;"><strong>Why:</strong> ${exp.why_this_fits_you}</p>` : ''}
                            <div style="margin-top:10px; text-align:right;">
                                <button class="mc-button small" onclick="toggleExperimentStatus('${exp.hash}', '${toggleAction}', ${empId}, this)">
                                    ${toggleLabel}
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                list.innerHTML = html;
            }

            function toggleExperimentStatus(hash, newStatus, empId, btn) {
                const originalText = btn.innerText;
                btn.innerText = '...';
                btn.disabled = true;

                const data = new URLSearchParams();
                data.append('action', 'mc_employer_update_experiment_status');
                data.append('employee_id', empId);
                data.append('hash', hash);
                data.append('status', newStatus);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            loadExperiments(empId); // Reload list
                        } else {
                            alert('Error: ' + res.data.message);
                            btn.innerText = originalText;
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Network error');
                        btn.innerText = originalText;
                        btn.disabled = false;
                    });
            }

            function saveEmployeeContext(generateAfter) {
                const empId = document.getElementById('mc_emp_id_input').value;
                const role = document.getElementById('mc_role_input').value;
                const resp = document.getElementById('mc_resp_input').value;

                if (!role || !resp) {
                    alert('Please fill in both Role Title and Responsibilities.');
                    return;
                }

                const btnId = generateAfter ? 'mc-save-generate-btn' : 'mc-save-only-btn';
                const btn = document.getElementById(btnId);
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Saving...';

                const data = new URLSearchParams();
                data.append('action', 'mc_save_employee_context');
                data.append('employee_id', empId);
                data.append('role', role);
                data.append('responsibilities', resp);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            // Update UI to reflect that role is now present
                            const genBtn = document.getElementById('generate-report-btn-' + empId);
                            if (genBtn) {
                                genBtn.setAttribute('data-has-role', 'true');
                                // Update the context data on the button too so re-opening works
                                try {
                                    const currentData = JSON.parse(genBtn.getAttribute('data-emp-context'));
                                    currentData.context = { role: role, responsibilities: resp };
                                    genBtn.setAttribute('data-emp-context', JSON.stringify(currentData));
                                } catch (e) { }
                            }

                            closeEmployeeModal();

                            // If "Save & Generate", trigger the generation
                            if (generateAfter && genBtn) {
                                // Small delay to allow modal close
                                setTimeout(() => {
                                    generateAnalysisReport(empId, genBtn);
                                }, 100);
                            } else {
                                alert('Role details saved successfully.');
                            }
                        } else {
                            alert(res.data.message || 'Error saving details.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Network error occurred.');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            }
        </script>
        <!-- Context Configuration Modal -->
        <div id="mc-context-modal" class="mc-modal">
            <div class="mc-modal-content">
                <span class="mc-close" onclick="closeContextModal()">&times;</span>
                <h3>Configure Experiment Context</h3>
                <p style="color:#666; font-size:0.9em; margin-bottom:15px;">
                    To generate high-quality experiments, the AI needs to understand the employee's role and your workplace
                    environment.
                </p>

                <div class="mc-form-group">
                    <label for="mc-context-role">Employee Role Context</label>
                    <textarea id="mc-context-role" rows="3"
                        placeholder="e.g. Senior Developer responsible for backend architecture and mentoring juniors."></textarea>
                </div>

                <div class="mc-form-group">
                    <label for="mc-context-workplace">Workplace Context (Company Settings)</label>
                    <textarea id="mc-context-workplace" rows="3"
                        placeholder="e.g. Fast-paced startup culture, remote-first, values autonomy and rapid iteration."></textarea>
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button class="mc-button secondary" onclick="closeContextModal()">Cancel</button>
                    <button id="mc-context-confirm-btn" class="mc-button primary">Confirm & Generate</button>
                </div>
            </div>
        </div>
        <!-- Strain Details Modal -->
        <div id="mc-strain-details-modal" class="mc-modal" style="display:none; z-index:2147483650;">
            <div class="mc-modal-content"
                style="max-width:800px; margin:50px auto; padding:0; border-radius:12px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="mc-modal-header"
                    style="background:#fff; padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                    <h2 id="mc-strain-details-title" style="margin:0; font-size:18px; color:#1e293b;">Strain Index Details</h2>
                    <span class="mc-close" onclick="closeStrainDetailsModal()"
                        style="cursor:pointer; font-size:24px; color:#94a3b8;">&times;</span>
                </div>
                <div id="mc-strain-details-body" style="padding:20px; overflow-y:auto; max-height:80vh; background:#f8fafc;">
                    <!-- Content injected via JS -->
                </div>
            </div>
        </div>

        <script>
            // --- STRAIN DETAILED MODAL LOGIC ---
            function openStrainDetailsModal(data) {
                const modal = document.getElementById('mc-strain-details-modal');
                if (modal) {
                    document.body.appendChild(modal);
                    modal.style.setProperty('z-index', '2147483647', 'important'); // Max Int (Valid)
                    modal.style.display = 'block';
                }
                const body = document.getElementById('mc-strain-details-body');
                const title = document.getElementById('mc-strain-details-title');

                if (title) title.textContent = 'Strain Index Analysis: ' + (data.name || 'Employee');

                // Check if data contains detailed answers. If not, fetch them.
                if (!data.detailed_answers) {
                    if (body) {
                        body.innerHTML = '<div style="text-align:center; padding:40px;"><span class="dashicons dashicons-update spin" style="font-size:40px; color:#cbd5e1;"></span><p style="margin-top:20px; color:#64748b;">Loading deep dive data...</p></div>';
                    }

                    const formData = new URLSearchParams();
                    formData.append('action', 'mc_view_analysis_report');
                    formData.append('user_id', data.id); // Assuming data.id is passed

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                // Merge fetched data
                                data.detailed_answers = result.data.detailed_answers;
                                // Ensure strain_results is also up to date if needed
                                if (result.data.strain_breakdown) {
                                    data.strain_results = result.data.strain_breakdown;
                                }
                                // Re-render with full data
                                openStrainDetailsModal(data);
                            } else {
                                if (body) body.innerHTML = '<p style="color:red; text-align:center;">Failed to load details. ' + (result.data.message || '') + '</p>';
                            }
                        })
                        .catch(e => {
                            console.error(e);
                            if (body) body.innerHTML = '<p style="color:red; text-align:center;">Error loading details.</p>';
                        });
                    return; // Stop execution, wait for fetch
                }

                let html = '';

                // Overall Score Rationale
                const si = data.strain_results?.strain_index || {};
                const overall = si.overall_strain !== undefined ? si.overall_strain : 0;
                const scorePct = (overall * 100).toFixed(1);

                let color = '#22c55e';
                let level = 'Low Risk';
                let rationale = 'This score indicates the employee is showing healthy levels of engagement and resilience.';

                if (overall >= 0.66) {
                    color = '#dc2626';
                    level = 'High Risk';
                    rationale = 'This score indicates significant strain markers. The employee is likely experiencing high levels of rumination, avoidance, or emotional flooding which may lead to burnout.';
                } else if (overall >= 0.33) {
                    color = '#ca8a04';
                    level = 'Moderate Risk';
                    rationale = 'This score indicates emerging strain markers. While not critical, monitor for signs of increased stress or disengagement.';
                }

                html += `
                    <div style="display:flex; align-items:center; gap:20px; padding:20px; background:#f8fafc; border-radius:8px; margin-bottom:24px; border-left:4px solid ${color};">
                        <div style="text-align:center;">
                            <div style="font-size:32px; font-weight:800; color:${color};">${scorePct}%</div>
                            <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b;">Strain Index</div>
                        </div>
                        <div>
                            <h3 style="margin:0 0 8px; color:${color};">${level}</h3>
                            <p style="margin:0; color:#475569; font-size:14px; line-height:1.5;">${rationale}</p>
                        </div>
                    </div>
                `;

                // Scoring Explanation Legend
                html += `
                    <div style="margin-bottom:20px; padding:15px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; color:#64748b;">
                        <h4 style="margin:0 0 8px; color:#475569; font-size:13px; text-transform:uppercase;">About Strain Scoring</h4>
                        <p style="margin:0 0 8px;">The Strain Index measures <strong>cognitive and emotional friction</strong>—the mental effort required to maintain engagement. It is an aggregate score derived from 30 specific questions across the MI, CDT, and Bartle assessments.</p>
                        <p style="margin:0 0 8px;"><strong>Scoring Context:</strong> Individual questions are scored on a scale of 1 to 5, where <strong>5 indicates the highest level of strain</strong> (strongest agreement with a strain marker).</p>
                        <ul style="margin:0; padding-left:16px; line-height:1.5;">
                            <li><span style="color:#22c55e; font-weight:bold;">0% - 33% (Low Risk):</span> Healthy engagement. Few to no strain markers.</li>
                            <li><span style="color:#ca8a04; font-weight:bold;">33% - 66% (Moderate Risk):</span> Emerging strain. Some conflicting motivations or avoidance behaviors present.</li>
                            <li><span style="color:#dc2626; font-weight:bold;">66% - 100% (High Risk):</span> Significant strain. High potential for burnout or disengagement.</li>
                        </ul>
                    </div>
                `;

                // Detailed Answers Breakdown
                const details = data.detailed_answers || {};
                const cats = {
                    'rumination': 'Processing Style (Rumination)',
                    'avoidance': 'Decision Dynamics (Avoidance)',
                    'flood': 'Engagement Style (Emotional Flood)'
                };

                html += '<div class="mc-strain-accordion">';

                for (const [key, label] of Object.entries(cats)) {
                    if (!details[key]) continue;

                    html += `<h3 style="margin:20px 0 12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px; color:#1e293b;">${label}</h3>`;

                    ['MI', 'CDT', 'Bartle'].forEach(quiz => {
                        const answers = details[key][quiz];
                        if (answers && Object.keys(answers).length > 0) {
                            html += `<div style="margin-bottom:12px;">`;
                            html += `<h4 style="margin:0 0 8px; font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Source: ${quiz} Quiz</h4>`;
                            html += `<ul style="list-style:none; margin:0; padding:0;">`;

                            for (const [q, a] of Object.entries(answers)) {
                                let ansColor = '#22c55e'; // Low (1)
                                const val = parseFloat(a);
                                if (val >= 4) {
                                    ansColor = '#dc2626'; // High (4-5)
                                } else if (val >= 2) {
                                    ansColor = '#ca8a04'; // Moderate (2-3)
                                }

                                html += `<li style="margin-bottom:8px; font-size:13px; color:#334155; padding:8px; background:#fff; border:1px solid #f1f5f9; border-radius:4px;">
                                    <strong style="display:block; margin-bottom:4px; color:#0f172a;">${q}</strong>
                                    <span style="color:${ansColor}; font-weight:600;">User Answer: ${a} / 5</span>
                                </li>`;
                            }

                            html += `</ul></div>`;
                        }
                    });
                }
                html += '</div>';

                if (body) body.innerHTML = html;
                if (modal) modal.style.display = 'block';

                // Add click outside to close
                window.onclick = function (event) {
                    if (event.target == modal) {
                        closeStrainDetailsModal();
                    }
                }
            }

            function closeStrainDetailsModal() {
                const modal = document.getElementById('mc-strain-details-modal');
                if (modal) modal.style.display = 'none';
            }
        </script>
        <!-- Edit Email Modal -->
        <div id="mc-edit-email-modal" class="mc-modal">
            <div class="mc-modal-content">
                <span class="mc-close" onclick="closeEmailEditModal()">&times;</span>
                <h2>Edit Invite Email</h2>
                <form method="post">
                    <input type="hidden" name="mc_edit_invite_email_old" id="mc_edit_email_old_input">
                    <div class="mc-form-group">
                        <label>New Email Address <span class="mc-required">*</span></label>
                        <input type="email" name="mc_edit_invite_email_new" id="mc_edit_email_new_input" required>
                    </div>
                    <div class="mc-form-actions" style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                        <button type="button" class="mc-button secondary" onclick="closeEmailEditModal()">Cancel</button>
                        <button type="submit" class="mc-button primary">Update Email</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openEmailEditModal(oldEmail) {
                document.getElementById('mc_edit_email_old_input').value = oldEmail;
                document.getElementById('mc_edit_email_new_input').value = oldEmail;
                document.getElementById('mc-edit-email-modal').style.display = 'block';
            }
            function closeEmailEditModal() {
                document.getElementById('mc-edit-email-modal').style.display = 'none';
            }
        </script>
        <?php
        return ob_get_clean();
    }

    public static function ajax_save_employee_context()
    {
        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES)) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $emp_id = intval($_POST['employee_id']);
        $role = sanitize_text_field($_POST['role']);
        $resp = sanitize_textarea_field($_POST['responsibilities']);

        if (!$emp_id || empty($role) || empty($resp)) {
            $current_user_id = get_current_user_id(); // Define $current_user_id here for the error message
            wp_send_json_error(['message' => "Missing required fields. ID: $emp_id ($current_user_id), Role: " . substr($role, 0, 10) . ", Resp: " . substr($resp, 0, 10)]);
        }

        // Verify ownership (same logic as before)
        $current_user_id = get_current_user_id();
        $linked_employer = get_user_meta($emp_id, 'mc_linked_employer_id', true);

        if (intval($linked_employer) !== $current_user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not authorized to manage this employee.'], 403);
        }

        $context = [
            'role' => $role,
            'responsibilities' => $resp
        ];

        update_user_meta($emp_id, 'mc_employee_role_context', $context);

        wp_send_json_success(['message' => 'Context saved.']);
    }
}
