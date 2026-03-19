<?php
/**
 * Admin Testing Page
 * 
 * Quick testing interface for creating employers/employees and testing the assessment workflow.
 * Admin-only access with auto-fill and bulk delete features.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle User Switching Fallback
add_action('admin_init', 'mc_handle_user_switch_fallback');

function mc_handle_user_switch_fallback()
{
    if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'mc_switch_user' && isset($_GET['user_id']) && isset($_GET['_wpnonce'])) {
        $user_id = intval($_GET['user_id']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'mc_switch_user_' . $user_id) && current_user_can('manage_options')) {
            wp_set_auth_cookie($user_id);

            $user = get_userdata($user_id);
            $redirect_url = home_url();

            if ($user && !is_wp_error($user)) {
                $roles = (array) $user->roles;
                if (in_array('administrator', $roles)) {
                    $redirect_url = admin_url('admin.php?page=mc-super-admin');
                } elseif (class_exists('MC_Roles') && in_array(MC_Roles::ROLE_EMPLOYER, $roles)) {
                    $redirect_url = home_url('/employer-dashboard/');
                } elseif (class_exists('MC_Roles') && in_array(MC_Roles::ROLE_EMPLOYEE, $roles)) {
                    $redirect_url = home_url('/quiz-dashboard/');
                }
            }

            wp_redirect($redirect_url);
            exit;
        }
    }
}

function mc_render_admin_testing_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_test_user' && isset($_POST['user_id'])) {
        check_admin_referer('mc_admin_testing_nonce', 'nonce');
        $user_id = intval($_POST['user_id']);
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        if (wp_delete_user($user_id)) {
            wp_send_json_success(['message' => 'User deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete user']);
        }
        wp_die();
    }

    $message = '';
    $message_type = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('mc_admin_testing_action', 'mc_admin_testing_nonce');

        if (isset($_POST['bulk_delete_test_users'])) {
            $base_email = sanitize_email($_POST['base_email_for_delete']);
            if (!empty($base_email) && is_email($base_email)) {
                list($local, $domain) = explode('@', $base_email);
                $all_users = get_users(['fields' => ['ID', 'user_email']]);
                $deleted_count = 0;
                foreach ($all_users as $user) {
                    if (preg_match('/^' . preg_quote($local, '/') . '\+.+@' . preg_quote($domain, '/') . '$/', $user->user_email)) {
                        require_once(ABSPATH . 'wp-admin/includes/user.php');
                        if (wp_delete_user($user->ID)) {
                            $deleted_count++;
                        }
                    }
                }
                $message = "Deleted $deleted_count test user(s) matching pattern: {$local}+*@{$domain}";
                $message_type = 'success';
            } else {
                $message = 'Please enter a valid base email address';
                $message_type = 'error';
            }
        }

        if (isset($_POST['create_employer'])) {
            $email = sanitize_email($_POST['employer_email']);
            $first_name = sanitize_text_field($_POST['employer_first_name']);
            $last_name = sanitize_text_field($_POST['employer_last_name']);
            $company_name = sanitize_text_field($_POST['employer_company_name']);

            if (empty($email) || !is_email($email)) {
                $message = 'Valid email address is required';
                $message_type = 'error';
            } elseif (email_exists($email)) {
                $message = 'A user with this email already exists';
                $message_type = 'error';
            } else {
                $password = wp_generate_password(12, true, true);
                $user_id = wp_create_user($email, $password, $email);
                if (is_wp_error($user_id)) {
                    $message = 'Error creating employer: ' . $user_id->get_error_message();
                    $message_type = 'error';
                } else {
                    if ($first_name)
                        update_user_meta($user_id, 'first_name', $first_name);
                    if ($last_name)
                        update_user_meta($user_id, 'last_name', $last_name);
                    if ($company_name)
                        update_user_meta($user_id, 'mc_company_name', $company_name);
                    update_user_meta($user_id, 'mc_age_group', 'adult');
                    delete_user_meta($user_id, 'mc_needs_age_group');
                    $user = new WP_User($user_id);
                    $user->set_role(MC_Roles::ROLE_EMPLOYER);
                    update_user_meta($user_id, 'mc_employer_status', 'active');
                    update_user_meta($user_id, 'mc_subscription_plan', 'free');
                    $share_code = strtoupper(wp_generate_password(8, false));
                    update_user_meta($user_id, 'mc_company_share_code', $share_code);
                    $message = "Employer created! Email: $email | Password: $password | Share Code: $share_code";
                    $message_type = 'success';
                }
            }
        }

        if (isset($_POST['create_employee'])) {
            $employer_id = intval($_POST['employer_id']);
            $email = sanitize_email($_POST['employee_email']);
            $first_name = sanitize_text_field($_POST['employee_first_name']);
            $last_name = sanitize_text_field($_POST['employee_last_name']);

            if (empty($employer_id)) {
                $message = 'Please select an employer';
                $message_type = 'error';
            } elseif (empty($email) || !is_email($email)) {
                $message = 'Valid email address is required';
                $message_type = 'error';
            } elseif (email_exists($email)) {
                $message = 'A user with this email already exists';
                $message_type = 'error';
            } else {
                $password = 'password123';
                $user_id = wp_create_user($email, $password, $email);
                if (is_wp_error($user_id)) {
                    $message = 'Error creating employee: ' . $user_id->get_error_message();
                    $message_type = 'error';
                } else {
                    if ($first_name)
                        update_user_meta($user_id, 'first_name', $first_name);
                    if ($last_name)
                        update_user_meta($user_id, 'last_name', $last_name);
                    update_user_meta($user_id, 'mc_age_group', 'adult');
                    delete_user_meta($user_id, 'mc_needs_age_group');
                    $user = new WP_User($user_id);
                    $user->set_role(MC_Roles::ROLE_EMPLOYEE);
                    update_user_meta($user_id, 'mc_linked_employer_id', $employer_id);
                    $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                    if (!is_array($invited_employees))
                        $invited_employees = [];
                    $invited_employees[] = ['email' => $email, 'name' => trim($first_name . ' ' . $last_name)];
                    update_user_meta($employer_id, 'mc_invited_employees', $invited_employees);
                    $message = "Employee created! Email: $email | Password: $password";
                    $message_type = 'success';
                }
            }
        }
    }

    $employers = get_users(['role' => MC_Roles::ROLE_EMPLOYER, 'orderby' => 'registered', 'order' => 'DESC', 'number' => 50]);

    $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : 'all';

    $per_page = 15;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $query_args = [
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => $per_page,
        'offset' => $offset,
        'count_total' => true
    ];

    if ($role_filter === 'administrator') {
        $query_args['role'] = 'administrator';
    } elseif ($role_filter === 'employer' && class_exists('MC_Roles')) {
        $query_args['role'] = MC_Roles::ROLE_EMPLOYER;
    } elseif ($role_filter === 'employee' && class_exists('MC_Roles')) {
        $query_args['role'] = MC_Roles::ROLE_EMPLOYEE;
    }

    $user_query = new WP_User_Query($query_args);
    $recent_users = $user_query->get_results();
    $total_users = $user_query->get_total();
    $total_pages = ceil($total_users / $per_page);
    ?>

    <style>
        .wrap.mc-admin-testing-page {
            max-width: none !important;
            width: auto !important;
            margin-right: 20px;
        }

        /* Override WordPress admin content width */
        body.toplevel_page_admin-testing-page #wpcontent,
        body.toplevel_page_admin-testing-page #wpbody-content {
            overflow-x: auto;
        }

        .mc-admin-testing-page .mc-forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
            max-width: 1200px;
        }

        .mc-admin-testing-page .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            padding: 15px;
        }

        .mc-admin-testing-page>.card {
            width: 100% !important;
            max-width: none !important;
            box-sizing: border-box;
            overflow-x: auto;
        }

        #users-table {
            min-width: 900px;
        }

        .mc-admin-testing-page .card h2 {
            margin: 0 0 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .compact-form th {
            padding: 8px 0;
            font-size: 12px;
            width: 70px;
        }

        .compact-form td {
            padding: 8px 0;
        }

        .compact-form input,
        .compact-form select {
            width: 100%;
            font-size: 13px;
        }

        .card .submit {
            margin: 12px 0 0;
            padding: 0;
        }

        .card .button {
            font-size: 12px;
            height: 28px;
            line-height: 26px;
            padding: 0 10px;
            margin-right: 5px;
        }

        .mc-role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .mc-role-administrator {
            background: #fee2e2;
            color: #991b1b;
        }

        .mc-role-employer {
            background: #dbeafe;
            color: #1e40af;
        }

        .mc-role-employee {
            background: #dcfce7;
            color: #166534;
        }

        .mc-role-subscriber {
            background: #f3f4f6;
            color: #6b7280;
        }

        .mc-quiz-count {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #dcfce7;
            color: #166534;
        }

        .mc-quiz-none {
            background: #f3f4f6;
            color: #9ca3af;
        }

        .mc-role-filter select {
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #c3c4c7;
        }

        .mc-row-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .mc-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: #f6f7f7;
            color: #50575e;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .mc-action-btn:hover {
            background: #f0f0f1;
            border-color: #8c8f94;
            color: #1d2327;
        }

        .mc-action-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
            cursor: not-allowed;
            filter: grayscale(100%);
            background: #f6f7f7;
            border-color: #c3c4c7;
            color: #a7aaad;
        }

        .mc-action-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .mc-btn-switch {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-color: #1d4ed8;
            color: #fff;
        }

        .mc-btn-switch:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            border-color: #1e40af;
            color: #fff;
        }

        .mc-btn-report {
            background: linear-gradient(135deg, #059669, #047857);
            border-color: #047857;
            color: #fff;
        }

        .mc-btn-report:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            border-color: #065f46;
            color: #fff;
        }

        .mc-btn-generate {
            background: linear-gradient(135deg, #d97706, #b45309);
            border-color: #b45309;
            color: #fff;
        }

        .mc-btn-generate:hover {
            background: linear-gradient(135deg, #b45309, #92400e);
            border-color: #92400e;
            color: #fff;
        }

        .mc-btn-delete:hover {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }

        .mc-admin-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mc-admin-modal-content {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .mc-admin-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .mc-admin-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #1e293b;
        }

        .mc-admin-modal-close {
            font-size: 28px;
            cursor: pointer;
            color: #64748b;
            line-height: 1;
        }

        .mc-admin-modal-close:hover {
            color: #1e293b;
        }

        .mc-admin-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .mc-report-section {
            margin-bottom: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
        }

        .mc-report-section h3 {
            margin: 0 0 12px;
            color: #1e293b;
            font-size: 1rem;
        }

        .mc-report-section p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }

        .mc-report-score {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .mc-report-score-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #2563eb;
        }

        .mc-report-score-label {
            font-size: 0.9rem;
            color: #64748b;
        }

        .mc-report-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

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

        .mc-action-btn-styled {
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

        .mc-action-btn-styled:hover {
            color: #2563eb;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .mc-action-btn-styled svg {
            width: 18px;
            height: 18px;
            display: block;
            /* Ensures no weird spacing */
        }

        /* Fix for the analysis button spacing */
        .mc-actions-secondary .mc-action-btn-styled {
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

        /* Modal Styles removed and loaded from employer-dashboard.css */
    </style>

    <div class="wrap mc-admin-testing-page">
        <h1>Admin Testing Page</h1>
        <p class="description">Quick testing interface for the employee assessment workflow.</p>

        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 20px; background: #e7f3ff; border-left: 4px solid #2563eb; max-width: 600px;">
            <h2>Base Email Configuration</h2>
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th style="width: 200px;"><label for="base_email">Base Email Address</label></th>
                    <td>
                        <input type="email" id="base_email" class="regular-text" placeholder="your.email@gmail.com">
                        <p class="description">Used for auto-fill. Example: josh@gmail.com → josh+employer1@gmail.com</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mc-forms-grid">
            <div class="card">
                <h2>Add Employer</h2>
                <form method="post" id="employer-form">
                    <?php wp_nonce_field('mc_admin_testing_action', 'mc_admin_testing_nonce'); ?>
                    <table class="form-table compact-form">
                        <tr>
                            <th><label for="employer_email">Email *</label></th>
                            <td><input type="email" name="employer_email" id="employer_email" required></td>
                        </tr>
                        <tr>
                            <th><label for="employer_first_name">First</label></th>
                            <td><input type="text" name="employer_first_name" id="employer_first_name"></td>
                        </tr>
                        <tr>
                            <th><label for="employer_last_name">Last</label></th>
                            <td><input type="text" name="employer_last_name" id="employer_last_name"></td>
                        </tr>
                        <tr>
                            <th><label for="employer_company_name">Company</label></th>
                            <td><input type="text" name="employer_company_name" id="employer_company_name"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" class="button" onclick="autoFillEmployer()">Auto-Fill</button>
                        <button type="submit" name="create_employer" class="button button-primary">Create</button>
                    </p>
                </form>
            </div>

            <div class="card">
                <h2>Add Employee</h2>
                <form method="post" id="employee-form">
                    <?php wp_nonce_field('mc_admin_testing_action', 'mc_admin_testing_nonce'); ?>
                    <table class="form-table compact-form">
                        <tr>
                            <th><label for="employer_id">Employer *</label></th>
                            <td><select name="employer_id" id="employer_id" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($employers as $employer):
                                        $company = get_user_meta($employer->ID, 'mc_company_name', true) ?: 'No Company'; ?>
                                        <option value="<?php echo esc_attr($employer->ID); ?>"><?php echo esc_html($company); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select></td>
                        </tr>
                        <tr>
                            <th><label for="employee_email">Email *</label></th>
                            <td><input type="email" name="employee_email" id="employee_email" required></td>
                        </tr>
                        <tr>
                            <th><label for="employee_first_name">First</label></th>
                            <td><input type="text" name="employee_first_name" id="employee_first_name"></td>
                        </tr>
                        <tr>
                            <th><label for="employee_last_name">Last</label></th>
                            <td><input type="text" name="employee_last_name" id="employee_last_name"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" class="button" onclick="autoFillEmployee()">Auto-Fill</button>
                        <button type="submit" name="create_employee" class="button button-primary">Create</button>
                    </p>
                </form>
            </div>

            <div class="card" style="background: #ffe7e7; border-left: 4px solid #dc2626;">
                <h2>Delete Tests</h2>
                <form method="post" onsubmit="return confirm('Delete all test users? This cannot be undone!');">
                    <?php wp_nonce_field('mc_admin_testing_action', 'mc_admin_testing_nonce'); ?>
                    <table class="form-table compact-form">
                        <tr>
                            <th><label for="base_email_for_delete">Base Email</label></th>
                            <td>
                                <input type="email" name="base_email_for_delete" id="base_email_for_delete" required>
                                <p class="description" style="margin-top: 5px;">Deletes all matching yourname+*@domain.com
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" name="bulk_delete_test_users"
                            class="button button-secondary">Delete All</button></p>
                </form>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Recent Users</h2>
                <div class="mc-role-filter">
                    <label for="role_filter" style="margin-right: 8px;">Filter by Role:</label>
                    <select id="role_filter" onchange="filterByRole(this.value)">
                        <option value="all" <?php selected($role_filter, 'all'); ?>>All Users</option>
                        <option value="administrator" <?php selected($role_filter, 'administrator'); ?>>Administrators
                        </option>
                        <option value="employer" <?php selected($role_filter, 'employer'); ?>>Employers</option>
                        <option value="employee" <?php selected($role_filter, 'employee'); ?>>Employees</option>
                    </select>
                </div>
            </div>

            <table class="wp-list-table widefat striped" id="users-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Company/Employer</th>
                        <th>Quizzes</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user):
                            $roles = (array) $user->roles;
                            $role = 'none';

                            // Prioritize our custom roles for display
                            if (in_array(MC_Roles::ROLE_EMPLOYER, $roles)) {
                                $role = MC_Roles::ROLE_EMPLOYER;
                            } elseif (in_array(MC_Roles::ROLE_EMPLOYEE, $roles)) {
                                $role = MC_Roles::ROLE_EMPLOYEE;
                            } else {
                                $role = !empty($roles) ? $roles[0] : 'none';
                            }

                            $company = '';
                            if ($role === MC_Roles::ROLE_EMPLOYER) {
                                $company = get_user_meta($user->ID, 'mc_company_name', true) ?: '-';
                            } elseif ($role === MC_Roles::ROLE_EMPLOYEE) {
                                $emp_id = get_user_meta($user->ID, 'mc_linked_employer_id', true);
                                if ($emp_id) {
                                    $emp = get_userdata($emp_id);
                                    $company = $emp ? get_user_meta($emp_id, 'mc_company_name', true) ?: $emp->user_email : 'Employer #' . $emp_id;
                                } else {
                                    $company = '-';
                                }
                            }

                            $switch_url = class_exists('MC_User_Switcher') ? MC_User_Switcher::get_switch_url($user->ID) : add_query_arg(['action' => 'mc_switch_user', 'user_id' => $user->ID, '_wpnonce' => wp_create_nonce('mc_switch_user_' . $user->ID)], admin_url('admin.php?page=admin-testing-page'));

                            $completed_quizzes = 0;
                            $quiz_metas = ['miq_quiz_results', 'cdt_quiz_results', 'bartle_quiz_results'];
                            foreach ($quiz_metas as $meta_key) {
                                if (get_user_meta($user->ID, $meta_key, true)) {
                                    $completed_quizzes++;
                                }
                            }

                            $analysis = get_user_meta($user->ID, 'mc_assessment_analysis', true);
                            $strain_results = get_user_meta($user->ID, 'strain_index_results', true);

                            // Calculate strain_breakdown for the View Report button
                            $mi = get_user_meta($user->ID, 'miq_quiz_results', true);
                            $cdt = get_user_meta($user->ID, 'cdt_quiz_results', true);
                            $bartle = get_user_meta($user->ID, 'bartle_quiz_results', true);
                            if (!is_array($mi))
                                $mi = [];
                            if (!is_array($cdt))
                                $cdt = [];
                            if (!is_array($bartle))
                                $bartle = [];

                            $get_si_val = function ($arr, $key) {
                                if (isset($arr['part1Scores'][$key]))
                                    return intval($arr['part1Scores'][$key]);
                                if (isset($arr['scores'][$key]))
                                    return intval($arr['scores'][$key]);
                                return 0;
                            };

                            $strain_breakdown = [
                                'rumination' => ['MI' => $get_si_val($mi, 'si-rumination'), 'CDT' => $get_si_val($cdt, 'si-rumination'), 'Bartle' => $get_si_val($bartle, 'si-rumination')],
                                'avoidance' => ['MI' => $get_si_val($mi, 'si-avoidance'), 'CDT' => $get_si_val($cdt, 'si-avoidance'), 'Bartle' => $get_si_val($bartle, 'si-avoidance')],
                                'flood' => ['MI' => $get_si_val($mi, 'si-emotional-flood'), 'CDT' => $get_si_val($cdt, 'si-emotional-flood'), 'Bartle' => $get_si_val($bartle, 'si-emotional-flood')]
                            ];

                            // Note: For detailed_answers we'd need to load question files which is expensive for a table render
                            // So we provide empty arrays for now - the full details are available via Regenerate
                            $detailed_answers = [
                                'rumination' => ['MI' => [], 'CDT' => [], 'Bartle' => []],
                                'avoidance' => ['MI' => [], 'CDT' => [], 'Bartle' => []],
                                'flood' => ['MI' => [], 'CDT' => [], 'Bartle' => []]
                            ];

                            $has_analysis = !empty($analysis);

                            $role_display = $role;
                            if ($role === MC_Roles::ROLE_EMPLOYER)
                                $role_display = 'employer';
                            elseif ($role === MC_Roles::ROLE_EMPLOYEE)
                                $role_display = 'employee';
                            ?>
                            <tr>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><span
                                        class="mc-role-badge mc-role-<?php echo esc_attr($role_display); ?>"><?php echo esc_html($role_display); ?></span>
                                </td>
                                <td><?php echo esc_html($company); ?></td>
                                <td>
                                    <?php if ($completed_quizzes > 0): ?>
                                        <span class="mc-quiz-count"><?php echo $completed_quizzes; ?>/3</span>
                                    <?php else: ?>
                                        <span class="mc-quiz-count mc-quiz-none">0/3</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($user->user_registered), current_time('timestamp')) . ' ago'); ?>
                                </td>
                                <td>
                                    <div class="mc-row-actions">
                                        <!-- Switch User (Always Active) -->
                                        <a href="<?php echo esc_url($switch_url); ?>" class="mc-action-btn mc-btn-switch"
                                            title="Switch To User">
                                            <span class="dashicons dashicons-migrate"></span>
                                        </a>

                                        <!-- Add Test Data (Only for Employees) -->
                                        <?php if ($role === MC_Roles::ROLE_EMPLOYEE): ?>
                                            <button class="mc-action-btn mc-btn-test-data"
                                                onclick="openAddTestDataModal(<?php echo $user->ID; ?>, '<?php echo esc_js($user->display_name); ?>')"
                                                title="Add Test Data">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                            </button>
                                        <?php else: ?>
                                            <button class="mc-action-btn disabled" title="Add Test Data (Employees Only)">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Report Actions -->
                                        <?php if ($completed_quizzes > 0): ?>
                                            <?php if ($has_analysis): ?>
                                                <!-- View Report -->
                                                <button class="mc-action-btn mc-btn-report"
                                                    onclick="viewAnalysisReport(<?php echo $user->ID; ?>, this)" title="View Report">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                </button>
                                                <!-- Strain Details -->
                                                <button class="mc-action-btn"
                                                    style="color: #ea580c; border-color: #fdba74; background: #fff7ed;"
                                                    onclick="viewStrainDetails(<?php echo $user->ID; ?>, this)" title="View Strain Details">
                                                    <span class="dashicons dashicons-chart-bar"></span>
                                                </button>
                                            <?php else: ?>
                                                <!-- Generate Report -->
                                                <button class="mc-action-btn mc-btn-generate"
                                                    onclick="generateAnalysisReport(<?php echo $user->ID; ?>, this)"
                                                    title="Generate Report">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                </button>
                                                <!-- Strain Placeholder -->
                                                <button class="mc-action-btn disabled" title="Stain Details (Report Required)">
                                                    <span class="dashicons dashicons-chart-bar"></span>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- No Report Available -->
                                            <button class="mc-action-btn disabled" title="Generate Report (Quizzes Incomplete)">
                                                <span class="dashicons dashicons-media-document"></span>
                                            </button>
                                            <!-- Strain Placeholder -->
                                            <button class="mc-action-btn disabled" title="Strain Details (Report Required)">
                                                <span class="dashicons dashicons-chart-bar"></span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Delete User (Always Active) -->
                                        <a href="#" class="mc-action-btn mc-btn-delete"
                                            onclick="deleteTestUser(<?php echo esc_js($user->ID); ?>, '<?php echo esc_js($user->user_email); ?>'); return false;"
                                            title="Delete User">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html($total_users); ?> items</span>
                        <?php if ($current_page > 1): ?>
                            <a class="prev-page button"
                                href="<?php echo esc_url(add_query_arg(['paged' => $current_page - 1, 'role_filter' => $role_filter])); ?>">&laquo;</a>
                        <?php endif; ?>
                        <span class="paging-input"><?php echo esc_html($current_page); ?> of
                            <?php echo esc_html($total_pages); ?></span>
                        <?php if ($current_page < $total_pages): ?>
                            <a class="next-page button"
                                href="<?php echo esc_url(add_query_arg(['paged' => $current_page + 1, 'role_filter' => $role_filter])); ?>">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Analysis Modal (Copied from Employer Dashboard) -->
    <?php MC_Report_Template::render_analysis_modal(true); ?>

    <!-- Add Test Data Modal -->
    <div id="mc-add-test-data-modal" class="mc-admin-modal" style="display: none;">
        <div class="mc-admin-modal-content" style="max-width: 500px;">
            <div class="mc-admin-modal-header">
                <h2 id="mc-add-test-data-title">Add Test Data</h2>
                <span class="mc-admin-modal-close" onclick="closeAddTestDataModal()">&times;</span>
            </div>
            <div class="mc-admin-modal-body" style="padding: 20px;">
                <p>Select the type of data to generate for <strong id="mc-test-data-user-name"></strong>:</p>

                <!-- Custom Role Context Inputs -->
                <div
                    style="background: #f0f4f8; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #dbeafe;">
                    <h4 style="margin: 0 0 10px; font-size: 14px; color: #1e3a8a;">Optional: Define Target Role</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Role
                                Title</label>
                            <input type="text" id="mc-test-role-title" placeholder="e.g. Senior Sales Manager"
                                style="width: 100%; font-size: 13px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Responsibilities</label>
                            <input type="text" id="mc-test-role-resp" placeholder="e.g. Lead team, meet quotas"
                                style="width: 100%; font-size: 13px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                    </div>
                </div>

                <div class="mc-test-data-options" style="display: flex; flex-direction: column; gap: 10px;">
                    <button class="button" onclick="submitTestData('poor')" style="text-align: left; padding: 10px;">
                        <strong>Add Below Average Data</strong><br>
                        <small>Generates low resilience scores and high strain indicators.</small>
                    </button>
                    <button class="button" onclick="submitTestData('average')" style="text-align: left; padding: 10px;">
                        <strong>Add Average Data</strong><br>
                        <small>Generates moderate scores and balanced strain indicators.</small>
                    </button>
                    <button class="button" onclick="submitTestData('excellent')" style="text-align: left; padding: 10px;">
                        <strong>Add Excellent Data</strong><br>
                        <small>Generates high resilience scores and low strain indicators.</small>
                    </button>
                </div>
                <div id="mc-test-data-loading" style="display: none; text-align: center; margin-top: 20px;">
                    <div class="spinner is-active" style="float: none;"></div>
                    <p>Generating data for all 3 quizzes...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Strain Details Modal -->
    <div id="mc-strain-details-modal" class="mc-admin-modal"
        style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="mc-admin-modal-content"
            style="background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="mc-admin-modal-header"
                style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h2 id="mc-strain-details-title" style="margin: 0; font-size: 20px; color: #1e293b;">Strain Index Deep Dive
                </h2>
                <span class="mc-admin-modal-close" onclick="closeStrainDetailsModal()"
                    style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            <div class="mc-admin-modal-body" id="mc-strain-details-body" style="padding: 24px;">
                <!-- Content injected via JS -->
            </div>
            <div class="mc-admin-modal-footer"
                style="padding: 16px 24px; border-top: 1px solid #e2e8f0; text-align: right; background-color: #f8fafc; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <button class="button button-primary" onclick="closeStrainDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        let employerCounter = 1, employeeCounter = 1;
        document.getElementById('base_email').addEventListener('input', function () { document.getElementById('base_email_for_delete').value = this.value; });

        function autoFillEmployer() {
            const baseEmail = document.getElementById('base_email').value;
            if (!baseEmail) { alert('Please entera base email address first'); return; }
            const [local, domain] = baseEmail.split('@');
            if (!local || !domain) { alert('Please enter a valid email address'); return; }
            const timestamp = Date.now().toString().slice(-4);
            document.getElementById('employer_email').value = `${local}+employer${employerCounter}_${timestamp}@${domain}`;
            document.getElementById('employer_first_name').value = 'Test';
            document.getElementById('employer_last_name').value = `Employer ${employerCounter}`;
            document.getElementById('employer_company_name').value = `Test Company ${employerCounter}`;
            employerCounter++;
        }

        function autoFillEmployee() {
            const baseEmail = document.getElementById('base_email').value;
            if (!baseEmail) { alert('Please enter a base email address first'); return; }
            const [local, domain] = baseEmail.split('@');
            if (!local || !domain) { alert('Please enter a valid email address'); return; }
            if (!document.getElementById('employer_id').value) { alert('Please select an employer first'); return; }
            const timestamp = Date.now().toString().slice(-4);
            document.getElementById('employee_email').value = `${local}+employee${employeeCounter}_${timestamp}@${domain}`;
            document.getElementById('employee_first_name').value = 'Test';
            document.getElementById('employee_last_name').value = `Employee ${employeeCounter}`;
            employeeCounter++;
        }

        function deleteTestUser(userId, userEmail) {
            if (!confirm(`Are you sure you want to delete ${userEmail}?`)) return;
            fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'delete_test_user', user_id: userId, nonce: '<?php echo wp_create_nonce('mc_admin_testing_nonce'); ?>' }) })
                .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert('Error: ' + (d.data.message || 'Failed')); }).catch(e => { alert('Error'); console.error(e); });
        }

        function filterByRole(role) {
            const url = new URL(window.location.href);
            url.searchParams.set('role_filter', role);
            url.searchParams.delete('paged');
            window.location.href = url.toString();
        }


        const MC_COMPANY_NAME = 'Admin View';

        function openAnalysisModal(data) {
            console.log('DEBUG: openAnalysisModal called (Admin)');
            console.log('DEBUG: Full data object:', JSON.stringify(data, null, 2));
            // Reset loading s             tate
            const loadingDiv = document.getElementById('mc-report-loading');
            const contentDiv = document.getElementById('mc-report-content');
            const modal = document.getElementById('mc-analysis-modal');
            if (modal) {
                modal.style.setProperty('display', 'block', 'important');
                modal.style.setProperty('z-index', '2147483647', 'important');
                modal.style.setProperty('visibility', 'visible', 'important');
                modal.style.setProperty('opacity', '1', 'important');
            }
            if (loadingDiv) loadingDiv.style.display = 'none';
            if (contentDiv) contentDiv.style.display = 'block';

            // Basic Info
            const nameEl = document.getElementById('mc-analysis-name');
            if (nameEl) nameEl.textContent = data.name;

            // --- TEST DATA METADATA (Admin Only) ---
            const metaContainer = document.getElementById('mc-test-metadata-container');
            const metaText = document.getElementById('mc-test-metadata-text');
            if (metaContainer && metaText) {
                if (data.test_data_type) {
                    const typeCap = data.test_data_type.charAt(0).toUpperCase() + data.test_data_type.slice(1);
                    let metaInfo = `Type: <strong>${typeCap}</strong>`;

                    if (data.test_data_timestamp) {
                        metaInfo += ` &bull; ${data.test_data_timestamp}`;
                    }

                    // --- STALE CHECK ---
                    if (data.analysis_timestamp && data.test_data_timestamp) {
                        const analysisTime = new Date(data.analysis_timestamp.replace(' ', 'T')); // Handle SQL format
                        const testTime = new Date(data.test_data_timestamp.replace(' ', 'T'));

                        // Add a small buffer (e.g. 5 seconds) to avoid false positives due to race conditions
                        // If Analysis is OLDER than Test Data (by > 2 seconds), it's Stale
                        if (analysisTime < new Date(testTime.getTime() - 2000)) {
                            metaInfo += ` <span style="background: #dc2626; color: white; padding: 2px 6px; border-radius: 4px; border: 1px solid #991b1b; margin-left:8px; font-weight:bold; font-size:11px;">STALE REPORT (Regenerate Required)</span>`;
                            metaContainer.style.background = '#fef2f2'; // Reddish tint
                            metaContainer.style.borderColor = '#ef4444';
                        } else {
                            metaInfo += ` <span style="background: #166534; color: white; padding: 2px 6px; border-radius: 4px; border: 1px solid #166534; margin-left:8px; font-weight:bold; font-size:11px;">ACTIVE</span>`;
                            metaContainer.style.background = '#fff8cc'; // Back to yellow
                            metaContainer.style.borderColor = '#e1b93f';
                        }
                    } else {
                        // Default style if no timestamp comparison possible
                        metaContainer.style.background = '#fff8cc';
                        metaContainer.style.borderColor = '#e1b93f';
                    }

                    if (data.role_context && data.role_context.role) {
                        metaInfo += `<br>Role: <strong>${data.role_context.role}</strong>`;
                    }

                    if (data.role_context && data.role_context.responsibilities) {
                        metaInfo += ` &bull; Resp: ${data.role_context.responsibilities}`;
                    }

                    metaText.innerHTML = metaInfo;
                    metaContainer.style.display = 'block';
                } else {
                    metaContainer.style.display = 'none';
                }
            }

            // Company Name
            const companyLabel = document.getElementById('mc-analysis-company');
            if (companyLabel) {
                // Use the company defined in global scope or fallback
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
                if (heroFitRationale) {
                    // Make it clearer that this is the AI's specific reasoning
                    heroFitRationale.innerHTML = '<strong>AI Rationale:</strong> ' + (fitRationale || 'Analysis based on provided role & workplace context.');
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

            // --- UPDATE METADATA WITH RATIONALE (DEBUGGING VIEW) ---
            // We already populated metaText earlier in the function, but let's append the rationale here if it exists.
            if (metaContainer && metaText && fitRationale) {
                metaText.innerHTML += `<div style="margin-top:8px; border-top:1px solid #e1b93f; padding-top:4px; font-size:11px; color:#555;"><strong>AI Score Rationale:</strong> ${fitRationale}</div>`;
            }
            // Ensure we don't duplicate if function is called multiple times (though content matches)
            // This block runs after the initial meta setup, so it simply adds to the DOM element.

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
            // Leadership Potential
            const leadSummary = document.getElementById('mc-hero-leadership-summary');
            const leadership = data.analysis.leadership_potential || {};

            if (leadSummary) leadSummary.textContent = leadership.summary || '--';

            // Ideal Conditions
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
            const strainSection = document.getElementById('mc-strain-section');
            if (data.strain_results && data.strain_results.strain_index) {
                const si = data.strain_results.strain_index;
                const norm = si.normalized || {};
                const overall = si.overall_strain !== undefined ? si.overall_strain : 0;

                if (strainSection) {
                    strainSection.style.display = 'block';
                }

                // Update Overall Score
                const overallScoreEl = document.getElementById('mc-strain-overall-score');
                if (overallScoreEl) overallScoreEl.textContent = (overall * 100).toFixed(1);

                // Update Gauge
                const gaugeFill = document.getElementById('mc-strain-gauge-fill');
                if (gaugeFill) {
                    const rotation = -180 + (overall * 180);
                    gaugeFill.style.transform = `rotate(${rotation}deg)`;
                    if (overall < 0.33) gaugeFill.style.backgroundColor = '#22c55e'; // Green
                    else if (overall < 0.66) gaugeFill.style.backgroundColor = '#f59e0b'; // Yellow
                    else gaugeFill.style.backgroundColor = '#ef4444'; // Red
                }

                // Update Sub-Indices
                const updateBar = (key, val) => {
                    const valEl = document.getElementById(`mc-strain-${key}-val`);
                    const barEl = document.getElementById(`mc-strain-${key}-bar`);
                    if (valEl) valEl.textContent = (val * 100).toFixed(0) + '%';
                    if (barEl) {
                        barEl.style.width = (val * 100) + '%';
                        if (val < 0.33) barEl.style.backgroundColor = '#22c55e';
                        else if (val < 0.66) barEl.style.backgroundColor = '#f59e0b';
                        else barEl.style.backgroundColor = '#ef4444';
                    }
                };

                updateBar('rumination', norm.rumination || 0);
                updateBar('avoidance', norm.avoidance || 0);
                updateBar('flood', norm.emotional_flood || 0);

                // Update Breakdown Accordion
                console.log('DEBUG: strain_breakdown =', data.strain_breakdown);
                console.log('DEBUG: detailed_answers =', data.detailed_answers);
                if (data.strain_breakdown) {
                    const bd = data.strain_breakdown;
                    const da = data.detailed_answers || {};

                    const row = (label, val, answers) => {
                        let answerHtml = '';
                        if (answers && typeof answers === 'object' && Object.keys(answers).length > 0) {
                            answerHtml = '<ul class="strain-answer-list" style="margin: 5px 0 0 15px; font-size: 0.9em; color: #555; list-style-type: disc;">';
                            Object.entries(answers).forEach(([q, v]) => {
                                answerHtml += `<li><span class="q-text" style="font-style: italic;">"${q}"</span> <span class="q-val badge badge-secondary" style="font-weight: bold; margin-left: 5px;">(${v})</span></li>`;
                            });
                            answerHtml += '</ul>';
                        }

                        return `
                            <tr>
                                <td style="padding:4px 0; vertical-align: top;"><strong>${label}</strong></td>
                                <td style="padding:4px 0; text-align:right; vertical-align: top; font-weight: bold;">${val}</td>
                            </tr>
                            ${answerHtml ? `<tr><td colspan="2" style="padding-bottom: 8px;">${answerHtml}</td></tr>` : ''}
                        `;
                    };

                    const fillTable = (id, obj, ansObj) => {
                        const el = document.getElementById(id);
                        if (el) el.innerHTML = `
                            <table style="width:100%; font-size:0.85em; color:#64748b; border-collapse: collapse;">
                                ${row('MI Quiz', obj.MI, ansObj ? ansObj.MI : null)}
                                ${row('Growth Strengths', obj.CDT, ansObj ? ansObj.CDT : null)}
                                ${row('Bartle', obj.Bartle, ansObj ? ansObj.Bartle : null)}
                                <tr style="border-top:1px solid #cbd5e1; font-weight:700; color: #334155;">
                                    <td style="padding-top:4px;">Total</td>
                                    <td style="padding-top:4px; text-align:right;">${parseInt(obj.MI || 0) + parseInt(obj.CDT || 0) + parseInt(obj.Bartle || 0)}</td>
                                </tr>
                            </table>
                        `;
                    };

                    fillTable('mc-breakdown-rumination', bd.rumination, da.rumination);
                    fillTable('mc-breakdown-avoidance', bd.avoidance, da.avoidance);
                    fillTable('mc-breakdown-flood', bd.flood, da.flood);

                    // Show accordion container
                    const acc = document.getElementById('mc-strain-breakdown-accordion');
                    if (acc) acc.style.display = 'block';
                }

            } else {
                if (strainSection) strainSection.style.display = 'none';
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
            // const lead = data.analysis.leadership_potential || {}; // Already got lead earlier

            if (document.getElementById('mc-team-thrives')) document.getElementById('mc-team-thrives').textContent = team.thrives_with || '--';
            if (document.getElementById('mc-team-friction')) document.getElementById('mc-team-friction').textContent = team.friction_with || '--';

            // Leadership Potential Spectrum
            const leadershipRating = (data.analysis.leadership_potential && data.analysis.leadership_potential.rating) ? data.analysis.leadership_potential.rating.toLowerCase() : '';
            // const leadershipSummary = (data.analysis.leadership_potential && data.analysis.leadership_potential.summary) ? data.analysis.leadership_potential.summary : 'No data available.';
            // document.getElementById('mc-hero-leadership-summary').textContent = leadershipSummary; // Already set

            // Reset Spectrum
            document.querySelectorAll('.mc-spectrum-segment').forEach(el => el.classList.remove('active'));

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
                    content.style.setProperty('z-index', '2147483648', 'important');
                }

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

        // Helper to get robust API URL
        function getApiUrl() {
            if (typeof ajaxurl !== 'undefined') return ajaxurl;
            if (typeof mcSuperAdmin !== 'undefined' && mcSuperAdmin.ajaxUrl) return mcSuperAdmin.ajaxUrl;
            return '/wp-admin/admin-ajax.php';
        }

        function viewStrainDetails(userId, btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin"></span>';
            btn.disabled = true;

            fetch(getApiUrl(), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'mc_view_analysis_report', user_id: userId }) })
                .then(r => r.json()).then(result => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    if (result.success) {
                        openStrainDetailsModal(result.data);
                    } else {
                        alert('Error: ' + (result.data?.message || 'Failed to load strain details'));
                    }
                }).catch(e => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    alert('Network error.');
                    console.error(e);
                });
        }

        function openStrainDetailsModal(data) {
            const modal = document.getElementById('mc-strain-details-modal');
            if (modal) {
                document.body.appendChild(modal); // Move to body to avoid z-index trapping
                modal.style.setProperty('z-index', '2147483647', 'important'); // Max Int (Valid)
                modal.style.display = 'block';
            }
            const body = document.getElementById('mc-strain-details-body');
            const title = document.getElementById('mc-strain-details-title');

            if (title) title.textContent = 'Strain Index Analysis: ' + (data.name || 'Employee');

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
        }

        function closeStrainDetailsModal() {
            document.getElementById('mc-strain-details-modal').style.display = 'none';
        }

        // View existing analysis report (AJAX-based for on-demand detailed_answers loading)
        function viewAnalysisReport(userId, btn) {
            try {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="dashicons dashicons-update spin"></span>';
                btn.disabled = true;

                const modal = document.getElementById('mc-analysis-modal');
                const loading = document.getElementById('mc-report-loading');
                const content = document.getElementById('mc-report-content');

                // Show modal in loading state
                // Show modal in loading state
                modal.style.setProperty('display', 'block', 'important');
                modal.style.setProperty('z-index', '2147483647', 'important');
                modal.style.setProperty('visibility', 'visible', 'important');
                modal.style.setProperty('opacity', '1', 'important');
                if (loading) loading.style.display = 'block';
                if (content) content.style.display = 'none';

                fetch(getApiUrl(), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'mc_view_analysis_report', user_id: userId }) })
                    .then(r => r.json()).then(result => {
                        console.log('DEBUG: View Report AJAX result =', result);
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        if (result.success) {
                            openAnalysisModal(result.data);
                        } else {
                            closeAnalysisModal();
                            alert('Error: ' + (result.data?.message || 'Failed to load report'));
                        }
                    }).catch(e => {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        closeAnalysisModal();
                        alert('Network error loading report.');
                        console.error(e);
                    });
            } catch (err) {
                console.error('Synchronous Error in viewAnalysisReport:', err);
                alert('An unexpected error occurred: ' + err.message);
            }
        }

        function generateAnalysisReport(userId, btn) {
            try {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="dashicons dashicons-update spin"></span>';
                btn.disabled = true;

                const modal = document.getElementById('mc-analysis-modal');
                const loading = document.getElementById('mc-report-loading');
                const content = document.getElementById('mc-report-content');

                // Show modal in loading state
                // Show modal in loading state
                modal.style.setProperty('display', 'block', 'important');
                modal.style.setProperty('z-index', '2147483647', 'important');
                modal.style.setProperty('visibility', 'visible', 'important');
                modal.style.setProperty('opacity', '1', 'important');
                if (loading) loading.style.display = 'block';
                if (content) content.style.display = 'none';

                fetch(getApiUrl(), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'mc_generate_analysis_report', user_id: userId }) })
                    .then(r => r.json()).then(result => {
                        console.log('DEBUG: Raw AJAX result =', result);
                        console.log('DEBUG: result.data keys =', Object.keys(result.data || {}));
                        console.log('DEBUG: debug_answers =', result.data?.debug_answers);
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        if (result.success) {
                            openAnalysisModal(result.data);
                        } else {
                            closeAnalysisModal();
                            alert('Error: ' + (result.data?.message || 'Failed'));
                        }
                    }).catch(e => {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        closeAnalysisModal();
                        alert('Network error.');
                        console.error(e);
                    });
            } catch (err) {
                console.error('Synchronous Error in generateAnalysisReport:', err);
                alert('An unexpected error occurred: ' + err.message);
            }
        }

        function downloadReportPDF(btn) {
            const element = document.getElementById('mc-report-content');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Downloading...';
            btn.disabled = true;

            // Hide buttons for PDF - simplified selector for hero controls
            const heroControls = element.querySelector('.mc-hero-controls') || element.querySelector('.mc-header-right');
            if (heroControls) heroControls.style.display = 'none';

            // Also hide the close button explicitly if needed, though it might be outside mc-report-content depending on structure.
            // In the copied HTML, mc-hero-controls (if present) contains the PDF/Regen buttons.
            // Let's check if we have mc-hero-controls. The previous view showed:
            // <div class="mc-hero-controls" ...>

            // If we don't find mc-hero-controls, try to find the button's parent container
            const btnContainer = btn.closest('.mc-hero-controls') || btn.closest('.mc-header-right');
            if (btnContainer) btnContainer.style.display = 'none';

            const opt = {
                margin: 0,
                filename: 'Employee_Analysis_Report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, letterRendering: true },
                jsPDF: { unit: 'px', format: [element.scrollWidth, element.scrollHeight + 40], orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function () {
                if (heroControls) heroControls.style.display = 'flex';
                if (btnContainer) btnContainer.style.display = 'flex';
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(function (err) {
                console.error(err);
                alert('Error generating PDF');
                if (heroControls) heroControls.style.display = 'flex';
                if (btnContainer) btnContainer.style.display = 'flex';
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }



        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (typeof closeAnalysisModal === 'function') closeAnalysisModal();
                closeAddTestDataModal();
            }
        });

        let currentTestDataUserId = 0;

        function openAddTestDataModal(userId, userName) {
            currentTestDataUserId = userId;
            document.getElementById('mc-test-data-user-name').textContent = userName;
            document.getElementById('mc-add-test-data-modal').style.display = 'flex';
            document.getElementById('mc-test-data-loading').style.display = 'none';
            document.querySelector('.mc-test-data-options').style.display = 'flex';

            const modal = document.getElementById('mc-add-test-data-modal');
            modal.onclick = e => { if (e.target === modal) closeAddTestDataModal(); };
        }

        function closeAddTestDataModal() {
            document.getElementById('mc-add-test-data-modal').style.display = 'none';
            currentTestDataUserId = 0;
        }

        function submitTestData(type) {
            try {
                if (!currentTestDataUserId) return;
                if (!confirm('This will overwrite any existing quiz results for this user. Continue?')) return;

                document.querySelector('.mc-test-data-options').style.display = 'none';
                document.getElementById('mc-test-data-loading').style.display = 'block';

                const data = new URLSearchParams({
                    action: 'mc_generate_test_data',
                    user_id: currentTestDataUserId,
                    type: type,
                    role_title: document.getElementById('mc-test-role-title').value,
                    role_resp: document.getElementById('mc-test-role-resp').value,
                    nonce: '<?php echo wp_create_nonce('mc_admin_testing_nonce'); ?>'
                });

                fetch(getApiUrl(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data
                })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success) {
                            const loadingDiv = document.getElementById('mc-test-data-loading');
                            loadingDiv.innerHTML = '<div style="color: #46b450; font-size: 16px; font-weight: bold; padding: 20px;">✅ Data Generated Successfully!<br><span style="font-size:13px; font-weight:normal; color:#666;">Reloading page...</span></div>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                            closeAddTestDataModal();
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('An unexpected error occurred.');
                        closeAddTestDataModal();
                    });
            } catch (err) {
                console.error('Synchronous Error in submitTestData:', err);
                alert('An unexpected error occurred: ' + err.message);
            }
        }
    </script>
<?php }





