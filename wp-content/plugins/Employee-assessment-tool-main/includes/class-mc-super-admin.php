<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Super Admin Dashboard for managing employers and subscriptions.
 */
class MC_Super_Admin
{
    public function __construct()
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu'], 8);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

            // AJAX handlers
            add_action('wp_ajax_mc_create_employer', [$this, 'ajax_create_employer']);
            add_action('wp_ajax_mc_update_employer_status', [$this, 'ajax_update_employer_status']);
            add_action('wp_ajax_mc_delete_employer', [$this, 'ajax_delete_employer']);
            add_action('wp_ajax_mc_send_employer_invite', [$this, 'ajax_send_employer_invite']);
            add_action('wp_ajax_mc_reassign_company', [$this, 'ajax_reassign_company']);
            add_action('wp_ajax_mc_delete_employee', [$this, 'ajax_delete_employee']);
            add_action('wp_ajax_delete_test_user', [$this, 'ajax_delete_test_user']);
            add_action('wp_ajax_mc_generate_test_data', [$this, 'ajax_generate_test_data']);

        }
        // Note: Switch back button is now handled by MC_User_Switcher class
    }

    /**
     * Add admin menu for super admin.
     */
    public function add_admin_menu()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            'Super Admin',
            'Super Admin',
            'manage_options',
            'mc-super-admin',
            [$this, 'render_dashboard'],
            'dashicons-admin-multisite',
            3
        );

        add_submenu_page(
            'mc-super-admin',
            'Employer Management',
            'Employers',
            'manage_options',
            'mc-super-admin',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'mc-super-admin',
            'Subscription Management',
            'Subscriptions',
            'manage_options',
            'mc-super-admin-subscriptions',
            [$this, 'render_subscriptions']
        );


    }

    /**
     * Renders the admin testing page.
     */
    public function render_admin_testing_page()
    {
        require_once MC_QUIZ_PLATFORM_PATH . 'admin-testing-page.php';
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'mc-super-admin') === false) {
            return;
        }

        wp_enqueue_style(
            'mc-super-admin-css',
            plugin_dir_url(__DIR__) . 'assets/super-admin.css',
            [],
            '1.0.14'
        );

        // Enqueue employer dashboard styles for the report modal on Admin Testing page
        if (strpos($hook, 'admin-testing-page') !== false) {
            wp_enqueue_style(
                'mc-employer-dashboard',
                plugin_dir_url(__DIR__) . 'assets/employer-dashboard.css',
                [],
                MC_QUIZ_PLATFORM_VERSION
            );
        }

        wp_enqueue_script(
            'mc-super-admin-js',
            plugin_dir_url(__DIR__) . 'assets/super-admin.js',
            ['jquery'],
            time(),
            true
        );

        wp_localize_script('mc-super-admin-js', 'mcSuperAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_super_admin_nonce'),
        ]);
    }

    /**
     * Render the main dashboard.
     */
    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Get all employers
        $employers = get_users([
            'role' => MC_Roles::ROLE_EMPLOYER,
            'orderby' => 'registered',
            'order' => 'DESC'
        ]);

        // Get stats
        $total_employers = count($employers);
        $active_employers = 0;
        $total_employees = 0;

        foreach ($employers as $employer) {
            $status = get_user_meta($employer->ID, 'mc_employer_status', true);
            if ($status === 'active') {
                $active_employers++;
            }

            // Count employees linked to this employer
            $employees = get_users([
                'meta_key' => 'mc_linked_employer_id',
                'meta_value' => $employer->ID,
                'fields' => 'ID'
            ]);
            $total_employees += count($employees);
        }

        ?>
        <div class="wrap mc-super-admin-wrap">
            <h1 class="mc-super-admin-title">
                <span class="dashicons dashicons-admin-multisite"></span>
                Super Admin Dashboard
            </h1>

            <!-- Stats Cards -->
            <div class="mc-stats-grid">
                <div class="mc-stat-card">
                    <div class="mc-stat-icon mc-stat-primary">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="mc-stat-content">
                        <div class="mc-stat-value"><?php echo esc_html($total_employers); ?></div>
                        <div class="mc-stat-label">Total Employers</div>
                    </div>
                </div>
                <div class="mc-stat-card">
                    <div class="mc-stat-icon mc-stat-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mc-stat-content">
                        <div class="mc-stat-value"><?php echo esc_html($active_employers); ?></div>
                        <div class="mc-stat-label">Active Employers</div>
                    </div>
                </div>
                <div class="mc-stat-card">
                    <div class="mc-stat-icon mc-stat-info">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="mc-stat-content">
                        <div class="mc-stat-value"><?php echo esc_html($total_employees); ?></div>
                        <div class="mc-stat-label">Total Employees</div>
                    </div>
                </div>
                <div class="mc-stat-card">
                    <div class="mc-stat-icon mc-stat-warning">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="mc-stat-content">
                        <div class="mc-stat-value">$0</div>
                        <div class="mc-stat-label">Monthly Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="mc-actions-bar">
                <button type="button" class="button button-primary button-large mc-btn-create-employer">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Invite New Employer
                </button>
            </div>

            <!-- Employers Table -->
            <div class="mc-card">
                <div class="mc-card-header">
                    <h2>Employer Management</h2>
                    <div class="mc-search-box">
                        <input type="text" id="mc-employer-search" placeholder="Search employers..." class="mc-search-input">
                    </div>
                </div>
                <div class="mc-table-container">
                    <table class="wp-list-table widefat fixed striped mc-employers-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>
                                    Employees
                                    <span class="mc-tooltip-icon" data-tooltip="Active (Logged in) / Invited (Total)">
                                        <span class="dashicons dashicons-info"></span>
                                    </span>
                                </th>
                                <th>Status</th>
                                <th>Subscription</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employers)): ?>
                                <tr>
                                    <td colspan="8" class="mc-empty-state">
                                        <div class="mc-empty-icon">
                                            <span class="dashicons dashicons-businessman"></span>
                                        </div>
                                        <p>No employers yet. Create your first employer invitation!</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employers as $employer):
                                    $company_name = get_user_meta($employer->ID, 'mc_company_name', true);
                                    $status = get_user_meta($employer->ID, 'mc_employer_status', true) ?: 'pending';
                                    $subscription = get_user_meta($employer->ID, 'mc_subscription_plan', true) ?: 'free';

                                    $employees = get_users([
                                        'meta_key' => 'mc_linked_employer_id',
                                        'meta_value' => $employer->ID,
                                        'fields' => 'all'
                                    ]);
                                    $total_employees = count($employees);
                                    $active_employees = 0;

                                    foreach ($employees as $employee) {
                                        if ($this->is_user_active($employee->ID)) {
                                            $active_employees++;
                                        }
                                    }
                                    ?>
                                    <tr data-employer-id="<?php echo esc_attr($employer->ID); ?>">
                                        <td>
                                            <strong><?php echo esc_html($company_name ?: 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo esc_html($employer->display_name); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($employer->user_email); ?>">
                                                <?php echo esc_html($employer->user_email); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="mc-employee-count-wrapper">
                                                <span class="mc-badge mc-badge-count">
                                                    <?php echo esc_html($active_employees . ' / ' . $total_employees); ?>
                                                </span>
                                                <?php if ($total_employees > 0): ?>
                                                    <button type="button" class="mc-accordion-toggle" title="View Employees">
                                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = 'mc-badge-';
                                            $status_label = ucfirst($status);
                                            switch ($status) {
                                                case 'active':
                                                    $status_class .= 'success';
                                                    break;
                                                case 'pending':
                                                    $status_class .= 'warning';
                                                    break;
                                                case 'suspended':
                                                    $status_class .= 'error';
                                                    break;
                                                default:
                                                    $status_class .= 'default';
                                            }
                                            ?>
                                            <span class="mc-badge <?php echo esc_attr($status_class); ?>">
                                                <?php echo esc_html($status_label); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="mc-badge mc-badge-default">
                                                <?php echo esc_html(ucfirst($subscription)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo esc_html(date('M j, Y', strtotime($employer->user_registered))); ?>
                                        </td>
                                        <td>
                                            <div class="mc-row-actions">
                                                <?php
                                                // Generate custom switch URL using MC_User_Switcher
                                                $switch_url = MC_User_Switcher::get_switch_url($employer->ID);
                                                ?>
                                                <a href="<?php echo esc_url($switch_url); ?>" class="mc-action-btn mc-btn-switch"
                                                    title="Run As User">
                                                    <span class="dashicons dashicons-migrate"></span>
                                                </a>
                                                <button type="button" class="mc-action-btn mc-btn-view"
                                                    data-employer-id="<?php echo esc_attr($employer->ID); ?>" title="View Details">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                                <button type="button" class="mc-action-btn mc-btn-send-invite"
                                                    data-employer-id="<?php echo esc_attr($employer->ID); ?>"
                                                    data-employer-email="<?php echo esc_attr($employer->user_email); ?>"
                                                    title="Send Invite">
                                                    <span class="dashicons dashicons-email"></span>
                                                </button>
                                                <button type="button" class="mc-action-btn mc-btn-edit"
                                                    data-employer-id="<?php echo esc_attr($employer->ID); ?>" title="Edit">
                                                    <span class="dashicons dashicons-edit"></span></button>
                                                <button type="button" class="mc-action-btn mc-btn-reassign"
                                                    data-employer-id="<?php echo esc_attr($employer->ID); ?>"
                                                    data-company-name="<?php echo esc_attr($company_name ?: 'N/A'); ?>"
                                                    title="Reassign Company">
                                                    <span class="dashicons dashicons-randomize"></span>
                                                </button>
                                                <button type="button" class="mc-action-btn mc-btn-delete"
                                                    data-employer-id="<?php echo esc_attr($employer->ID); ?>" title="Delete">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Employee Details Row -->
                                    <tr class="mc-details-row" style="display: none;">
                                        <td colspan="8">
                                            <div class="mc-details-content">
                                                <h4>Employees</h4>
                                                <?php if (empty($employees)): ?>
                                                    <p>No employees found.</p>
                                                <?php else: ?>
                                                    <div class="mc-nested-grid">
                                                        <div class="mc-grid-head">Name</div>
                                                        <div class="mc-grid-head">Email</div>
                                                        <div class="mc-grid-head">Status</div>
                                                        <div class="mc-grid-head">Quizzes</div>
                                                        <div class="mc-grid-head">Last Login</div>
                                                        <div class="mc-grid-head">Actions</div>

                                                        <?php foreach ($employees as $employee):
                                                            $last_login = get_user_meta($employee->ID, 'mc_last_login', true);
                                                            $is_active = $this->is_user_active($employee->ID);
                                                            $emp_status = $is_active ? 'Active' : 'Invited';
                                                            $emp_status_class = $is_active ? 'mc-badge-success' : 'mc-badge-warning';

                                                            // Calculate completed quizzes
                                                            $completed_quizzes = 0;
                                                            $quiz_metas = ['miq_quiz_results', 'cdt_quiz_results', 'bartle_quiz_results'];
                                                            foreach ($quiz_metas as $meta_key) {
                                                                if (get_user_meta($employee->ID, $meta_key, true)) {
                                                                    $completed_quizzes++;
                                                                }
                                                            }
                                                            ?>
                                                            <div class="mc-grid-cell"><?php echo esc_html($employee->display_name); ?></div>
                                                            <div class="mc-grid-cell">
                                                                <a href="mailto:<?php echo esc_attr($employee->user_email); ?>"
                                                                    title="<?php echo esc_attr($employee->user_email); ?>">
                                                                    <?php echo esc_html($employee->user_email); ?>
                                                                </a>
                                                            </div>
                                                            <div class="mc-grid-cell">
                                                                <span class="mc-badge <?php echo esc_attr($emp_status_class); ?>">
                                                                    <?php echo esc_html($emp_status); ?>
                                                                </span>
                                                            </div>
                                                            <div class="mc-grid-cell">
                                                                <span class="mc-badge mc-badge-info">
                                                                    <?php echo esc_html($completed_quizzes . ' / 3'); ?>
                                                                </span>
                                                            </div>
                                                            <div class="mc-grid-cell">
                                                                <?php echo $last_login ? esc_html(date('M j, Y', strtotime($last_login))) : 'Never'; ?>
                                                            </div>
                                                            <div class="mc-grid-cell">
                                                                <div class="mc-row-actions">
                                                                    <?php
                                                                    // Generate custom switch URL using MC_User_Switcher
                                                                    $switch_url = MC_User_Switcher::get_switch_url($employee->ID);
                                                                    ?>
                                                                    <a href="<?php echo esc_url($switch_url); ?>"
                                                                        class="mc-action-btn mc-btn-switch" title="Run As User">
                                                                        <span class="dashicons dashicons-migrate"></span>
                                                                    </a>
                                                                    <button type="button" class="mc-action-btn mc-btn-delete-employee"
                                                                        data-employee-id="<?php echo esc_attr($employee->ID); ?>"
                                                                        data-employee-name="<?php echo esc_attr($employee->display_name); ?>"
                                                                        title="Delete Employee">
                                                                        <span class="dashicons dashicons-trash"></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reassign Company Modal -->
        <div id="mc-reassign-modal" class="mc-modal" style="display: none;">
            <div class="mc-modal-overlay"></div>
            <div class="mc-modal-content">
                <div class="mc-modal-header">
                    <h2>Reassign Company</h2>
                    <button type="button" class="mc-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="mc-modal-body">
                    <p>Transfer <strong id="mc-reassign-company-name"></strong> and all its linked employees to a different user account.</p>
                    <p class="description" style="margin-top: 4px;">If the target user is already an employer, the two companies (and their employees) will be <strong>swapped</strong>.</p>
                    <form id="mc-reassign-form">
                        <input type="hidden" id="mc-reassign-employer-id" value="">
                        <div class="mc-form-row">
                            <div class="mc-form-group">
                                <label for="mc-reassign-email">New Owner Email <span class="required">*</span></label>
                                <input type="email" id="mc-reassign-email" name="new_email" required class="mc-input"
                                    placeholder="newadmin@example.com">
                                <p class="description">The user must already exist in WordPress. They will be assigned the Employer role automatically.</p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="mc-modal-footer">
                    <button type="button" class="button mc-modal-close">Cancel</button>
                    <button type="button" class="button button-primary" id="mc-submit-reassign">Reassign Company</button>
                </div>
            </div>
        </div>

        <!-- Create Employer Modal -->
        <div id="mc-create-employer-modal" class="mc-modal" style="display: none;">
            <div class="mc-modal-overlay"></div>
            <div class="mc-modal-content">
                <div class="mc-modal-header">
                    <h2>Invite New Employer</h2>
                    <button type="button" class="mc-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="mc-modal-body">
                    <form id="mc-create-employer-form">
                        <div class="mc-form-row">
                            <div class="mc-form-group">
                                <label for="employer_email">Email Address <span class="required">*</span></label>
                                <input type="email" id="employer_email" name="employer_email" required class="mc-input">
                            </div>
                        </div>
                        <div class="mc-form-row">
                            <div class="mc-form-group">
                                <label for="employer_first_name">First Name</label>
                                <input type="text" id="employer_first_name" name="employer_first_name" class="mc-input">
                            </div>
                            <div class="mc-form-group">
                                <label for="employer_last_name">Last Name</label>
                                <input type="text" id="employer_last_name" name="employer_last_name" class="mc-input">
                            </div>
                        </div>
                        <div class="mc-form-row">
                            <div class="mc-form-group">
                                <label for="company_name">Company Name</label>
                                <input type="text" id="company_name" name="company_name" class="mc-input">
                            </div>
                        </div>
                        <div class="mc-form-row">
                            <div class="mc-form-group">
                                <label class="mc-checkbox-label">
                                    <input type="checkbox" id="send_invite" name="send_invite" checked>
                                    <span>Send invitation email immediately</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="mc-modal-footer">
                    <button type="button" class="button mc-modal-close">Cancel</button>
                    <button type="button" class="button button-primary" id="mc-submit-employer">Create Employer</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render subscriptions page.
     */
    public function render_subscriptions()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        ?>
        <div class="wrap mc-super-admin-wrap">
            <h1 class="mc-super-admin-title">
                <span class="dashicons dashicons-money-alt"></span>
                Subscription Management
            </h1>

            <div class="mc-card">
                <div class="mc-card-header">
                    <h2>Subscription Plans</h2>
                    <button type="button" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Add Plan
                    </button>
                </div>
                <div class="mc-subscription-plans">
                    <div class="mc-plan-card">
                        <div class="mc-plan-header">
                            <h3>Free</h3>
                            <div class="mc-plan-price">$0<span>/month</span></div>
                        </div>
                        <div class="mc-plan-features">
                            <ul>
                                <li><span class="dashicons dashicons-yes"></span> Up to 5 employees</li>
                                <li><span class="dashicons dashicons-yes"></span> Basic assessments</li>
                                <li><span class="dashicons dashicons-yes"></span> Email support</li>
                            </ul>
                        </div>
                        <div class="mc-plan-stats">
                            <span class="mc-badge mc-badge-info">0 Active</span>
                        </div>
                    </div>

                    <div class="mc-plan-card mc-plan-featured">
                        <div class="mc-plan-badge">Popular</div>
                        <div class="mc-plan-header">
                            <h3>Professional</h3>
                            <div class="mc-plan-price">$99<span>/month</span></div>
                        </div>
                        <div class="mc-plan-features">
                            <ul>
                                <li><span class="dashicons dashicons-yes"></span> Up to 50 employees</li>
                                <li><span class="dashicons dashicons-yes"></span> All assessments</li>
                                <li><span class="dashicons dashicons-yes"></span> AI-powered insights</li>
                                <li><span class="dashicons dashicons-yes"></span> Priority support</li>
                            </ul>
                        </div>
                        <div class="mc-plan-stats">
                            <span class="mc-badge mc-badge-info">0 Active</span>
                        </div>
                    </div>

                    <div class="mc-plan-card">
                        <div class="mc-plan-header">
                            <h3>Enterprise</h3>
                            <div class="mc-plan-price">Custom</div>
                        </div>
                        <div class="mc-plan-features">
                            <ul>
                                <li><span class="dashicons dashicons-yes"></span> Unlimited employees</li>
                                <li><span class="dashicons dashicons-yes"></span> Custom integrations</li>
                                <li><span class="dashicons dashicons-yes"></span> Dedicated support</li>
                                <li><span class="dashicons dashicons-yes"></span> White-label options</li>
                            </ul>
                        </div>
                        <div class="mc-plan-stats">
                            <span class="mc-badge mc-badge-info">0 Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mc-card" style="margin-top: 32px;">
                <div class="mc-card-header">
                    <h2>Recent Transactions</h2>
                </div>
                <div class="mc-empty-state" style="padding: 60px;">
                    <div class="mc-empty-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <p>No transactions yet. Payment integration coming soon.</p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Create new employer.
     */
    public function ajax_create_employer()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $email = sanitize_email($_POST['employer_email'] ?? '');
        $first_name = sanitize_text_field($_POST['employer_first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['employer_last_name'] ?? '');
        $company_name = sanitize_text_field($_POST['company_name'] ?? '');
        $send_invite = isset($_POST['send_invite']) && $_POST['send_invite'] === 'true';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => 'Valid email address is required']);
        }

        // Check if user already exists
        $existing_user_id = email_exists($email);
        if ($existing_user_id) {
            $existing_user = new WP_User($existing_user_id);

            // Already an employer
            if (in_array(MC_Roles::ROLE_EMPLOYER, (array) $existing_user->roles, true)) {
                wp_send_json_error(['message' => 'This user is already an employer.']);
            }

            // Promote existing user to employer (preserve any existing roles)
            $existing_user->add_role(MC_Roles::ROLE_EMPLOYER);

            if ($first_name) {
                update_user_meta($existing_user_id, 'first_name', $first_name);
            }
            if ($last_name) {
                update_user_meta($existing_user_id, 'last_name', $last_name);
            }
            if ($company_name) {
                update_user_meta($existing_user_id, 'mc_company_name', $company_name);
            }

            if (!get_user_meta($existing_user_id, 'mc_employer_status', true)) {
                update_user_meta($existing_user_id, 'mc_employer_status', 'pending');
            }
            if (!get_user_meta($existing_user_id, 'mc_subscription_plan', true)) {
                update_user_meta($existing_user_id, 'mc_subscription_plan', 'free');
            }
            if (!get_user_meta($existing_user_id, 'mc_company_share_code', true)) {
                $share_code = strtoupper(wp_generate_password(8, false));
                update_user_meta($existing_user_id, 'mc_company_share_code', $share_code);
            }

            // Existing users keep their own password
            if ($send_invite) {
                $this->send_employer_welcome_email($existing_user_id, $email);
            }

            wp_send_json_success([
                'message' => 'Existing user promoted to employer successfully.',
                'user_id' => $existing_user_id,
                'redirect' => admin_url('admin.php?page=mc-super-admin')
            ]);
        }

        // New user path
        $password = wp_generate_password(12, true, true);
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        if ($first_name) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if ($last_name) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        if ($company_name) {
            update_user_meta($user_id, 'mc_company_name', $company_name);
        }

        $user = new WP_User($user_id);
        $user->set_role(MC_Roles::ROLE_EMPLOYER);

        update_user_meta($user_id, 'mc_employer_status', 'pending');
        update_user_meta($user_id, 'mc_subscription_plan', 'free');

        $share_code = strtoupper(wp_generate_password(8, false));
        update_user_meta($user_id, 'mc_company_share_code', $share_code);

        if ($send_invite) {
            $this->send_employer_welcome_email($user_id, $email, $password);
        }

        wp_send_json_success([
            'message' => 'Employer created successfully.',
            'user_id' => $user_id,
            'redirect' => admin_url('admin.php?page=mc-super-admin')
        ]);
    }

    /**
     * AJAX: Update employer status.
     */
    public function ajax_update_employer_status()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $employer_id = intval($_POST['employer_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$employer_id || !in_array($status, ['active', 'pending', 'suspended'])) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        update_user_meta($employer_id, 'mc_employer_status', $status);

        wp_send_json_success(['message' => 'Status updated successfully']);
    }

    /**
     * AJAX: Delete employer.
     */
    public function ajax_delete_employer()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $employer_id = intval($_POST['employer_id'] ?? 0);

        if (!$employer_id) {
            wp_send_json_error(['message' => 'Invalid employer ID']);
        }

        // Check if employer has employees
        $employees = get_users([
            'meta_key' => 'mc_linked_employer_id',
            'meta_value' => $employer_id,
            'fields' => 'ID'
        ]);

        if (!empty($employees)) {
            wp_send_json_error([
                'message' => 'Cannot delete employer with linked employees. Please remove employees first.'
            ]);
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($employer_id);

        if (!$result) {
            wp_send_json_error(['message' => 'Failed to delete employer']);
        }

        wp_send_json_success(['message' => 'Employer deleted successfully']);
    }

    /**
     * AJAX: Delete an employee (unlink from employer and delete WP user).
     */
    public function ajax_delete_employee()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $employee_id = intval($_POST['employee_id'] ?? 0);

        if (!$employee_id) {
            wp_send_json_error(['message' => 'Invalid employee ID']);
        }

        $employee = get_userdata($employee_id);
        if (!$employee) {
            wp_send_json_error(['message' => 'Employee not found.']);
        }

        // Prevent deleting admins through this endpoint
        if (user_can($employee, 'manage_options')) {
            wp_send_json_error(['message' => 'Cannot delete an administrator through this action.']);
        }

        // Clean up: remove from employer's invited list if present
        $linked_employer_id = get_user_meta($employee_id, 'mc_linked_employer_id', true);
        if ($linked_employer_id) {
            $invited = get_user_meta($linked_employer_id, 'mc_invited_employees', true);
            if (is_array($invited)) {
                $updated = false;
                foreach ($invited as $key => $invite) {
                    $inv_email = is_array($invite) ? $invite['email'] : $invite;
                    if ($inv_email === $employee->user_email) {
                        unset($invited[$key]);
                        $updated = true;
                        break;
                    }
                }
                if ($updated) {
                    update_user_meta($linked_employer_id, 'mc_invited_employees', array_values($invited));
                }
            }
        }

        // Delete the WordPress user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($employee_id);

        if (!$result) {
            wp_send_json_error(['message' => 'Failed to delete employee.']);
        }

        wp_send_json_success(['message' => 'Employee deleted successfully.']);
    }

    /**
     * AJAX: Send employer invite.
     */
    public function ajax_send_employer_invite()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $employer_id = intval($_POST['employer_id'] ?? 0);

        if (!$employer_id) {
            wp_send_json_error(['message' => 'Invalid employer ID']);
        }

        $user = get_userdata($employer_id);
        if (!$user) {
            wp_send_json_error(['message' => 'Employer not found']);
        }

        // Send invite email
        $this->send_employer_welcome_email($employer_id, $user->user_email);

        wp_send_json_success(['message' => 'Invitation sent successfully']);
    }

    /**
     * Check if a user is active (logged in or completed a quiz).
     *
     * @param int $user_id User ID
     * @return bool True if active
     */
    private function is_user_active($user_id)
    {
        // Check if user has logged in
        if (get_user_meta($user_id, 'mc_last_login', true)) {
            return true;
        }

        // Check if user has completed any quiz
        if (class_exists('MC_Funnel')) {
            $completion = MC_Funnel::get_completion_status($user_id);
            foreach ($completion as $is_complete) {
                if ($is_complete) {
                    return true;
                }
            }
        } else {
            // Fallback if MC_Funnel not available
            $quiz_metas = ['miq_quiz_results', 'cdt_quiz_results', 'bartle_quiz_results'];
            foreach ($quiz_metas as $meta_key) {
                if (get_user_meta($user_id, $meta_key, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Send welcome email to employer.
     */
    private function send_employer_welcome_email($user_id, $email, $password = null)
    {
        $user = get_userdata($user_id);
        $company_name = get_user_meta($user_id, 'mc_company_name', true);

        $onboarding_url = home_url('/employer-onboarding/');
        if (class_exists('MC_Funnel')) {
            $page = MC_Funnel::find_page_by_shortcode('employer_onboarding');
            if ($page) {
                $onboarding_url = get_permalink($page);
            }
        }

        $subject = 'Welcome to The Science of Teamwork - Employee Assessment Platform';
        $first_name = $user->first_name ?: 'there';

        // Build HTML email
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
                    .email-box { background-color: #334155 !important; border-color: #475569 !important; }
                    .email-info-box { background-color: #1e3a5f !important; border-left-color: #3b82f6 !important; }
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
                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 40px 30px; text-align: center;">
                                    <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">Welcome to The Science of Teamwork</h1>
                                    <p style="margin: 12px 0 0; font-size: 16px; color: rgba(255, 255, 255, 0.95);">Employee Assessment Platform</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style="padding: 40px;">
                                    <p class="email-text" style="margin: 0 0 20px; font-size: 16px; color: #0f172a;">Hello ' . esc_html($first_name) . ',</p>
                                    
                                    <p class="email-text-secondary" style="margin: 0 0 24px; font-size: 16px; color: #475569; line-height: 1.6;">Your employer account has been created. You\'re now ready to unlock the full potential of your team through comprehensive psychometric assessments.</p>
                                    ';

        if ($password) {
            $magic_link = '';
            if (class_exists('MC_Magic_Login')) {
                $magic_link = MC_Magic_Login::generate_magic_link($user_id);
            }

            $message .= '
                                    <div class="email-box" style="background-color: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 24px; margin: 24px 0;">
                                        <h2 class="email-text" style="margin: 0 0 16px; font-size: 18px; font-weight: 700; color: #0f172a;">🔑 Login Credentials</h2>
                                        <p class="email-text-secondary" style="margin: 0 0 8px; font-size: 14px; color: #64748b;"><strong class="email-text" style="color: #0f172a;">Email:</strong> ' . esc_html($email) . '</p>
                                        <p class="email-text-secondary" style="margin: 0 0 8px; font-size: 14px; color: #64748b;"><strong class="email-text" style="color: #0f172a;">Password:</strong> <code style="background: rgba(37, 99, 235, 0.1); padding: 2px 8px; border-radius: 4px; font-family: monospace; color: #2563eb;">' . esc_html($password) . '</code></p>
                                        
                                        ' . ($magic_link ? '
                                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
                                            <p class="email-text-secondary" style="margin: 0 0 12px; font-size: 14px; color: #64748b;">Or log in instantly without a password:</p>
                                            <a href="' . esc_url($magic_link) . '" style="display: inline-block; background-color: #ffffff; color: #2563eb; border: 1px solid #2563eb; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px;">✨ Magic Login Link</a>
                                            <p class="email-text-secondary" style="margin: 8px 0 0; font-size: 12px; color: #94a3b8;">(Valid for 7 days)</p>
                                        </div>
                                        ' : '') . '

                                        <p style="margin: 16px 0 0; font-size: 13px; color: #f59e0b; font-weight: 500;">⚠️ Please change your password after your first login.</p>
                                    </div>
                                    ';
        }

        $message .= '
                                    <div style="margin: 32px 0;">
                                        <h2 class="email-text" style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #0f172a;">🚀 Get Started</h2>
                                        <p class="email-text-secondary" style="margin: 0 0 20px; font-size: 15px; color: #475569;">Complete your company profile to begin inviting team members:</p>
                                        <a href="' . esc_url($onboarding_url) . '" style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">Complete Your Profile →</a>
                                    </div>
                                    
                                    <div class="email-info-box" style="background-color: #f0f9ff; border-left: 4px solid #2563eb; padding: 20px; margin: 32px 0; border-radius: 4px;">
                                        <h3 class="email-text" style="margin: 0 0 12px; font-size: 16px; font-weight: 700; color: #0f172a;">Once set up, you\'ll be able to:</h3>
                                        <ul class="email-text-secondary" style="margin: 0; padding: 0 0 0 20px; color: #475569; font-size: 15px;">
                                            <li style="margin-bottom: 8px;">✅ Invite employees to take assessments</li>
                                            <li style="margin-bottom: 8px;">✅ View comprehensive team insights</li>
                                            <li style="margin-bottom: 8px;">✅ Generate AI-powered development recommendations</li>
                                        </ul>
                                    </div>
                                    
                                    <p class="email-text-secondary" style="margin: 32px 0 0; font-size: 15px; color: #64748b;">If you have any questions, please don\'t hesitate to reach out.</p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td class="email-footer" style="background-color: #f8fafc; padding: 32px 40px; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <p class="email-text-secondary" style="margin: 0 0 8px; font-size: 14px; color: #64748b; font-weight: 600;">Best regards,</p>
                                    <p class="email-text" style="margin: 0; font-size: 14px; color: #0f172a; font-weight: 700;">The The Science of Teamwork Team</p>
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

        // Set content type to HTML
        add_filter('wp_mail_content_type', function () {
            return 'text/html';
        });

        wp_mail($email, $subject, $message);

        // Reset content type
        remove_filter('wp_mail_content_type', function () {
            return 'text/html';
        });
    }

    /**
     * AJAX: Delete test user.
     */
    public function ajax_delete_test_user()
    {
        check_ajax_referer('mc_admin_testing_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);

        if (!$result) {
            wp_send_json_error(['message' => 'Failed to delete user']);
        }

        wp_send_json_success(['message' => 'User deleted successfully']);
    }


    /**
     * AJAX: Reassign company to a different user.
     */
    public function ajax_reassign_company()
    {
        check_ajax_referer('mc_super_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $old_employer_id = intval($_POST['employer_id'] ?? 0);
        $new_email = sanitize_email($_POST['new_email'] ?? '');

        if (!$old_employer_id || !is_email($new_email)) {
            wp_send_json_error(['message' => 'Valid employer ID and email are required.']);
        }

        // Look up target user
        $new_user = get_user_by('email', $new_email);
        if (!$new_user) {
            wp_send_json_error(['message' => 'No user found with that email address.']);
        }

        if ($new_user->ID === $old_employer_id) {
            wp_send_json_error(['message' => 'New user is the same as the current employer.']);
        }

        // Determine if this should be a swap (target already owns a company) or one-way transfer
        $existing_company = get_user_meta($new_user->ID, 'mc_company_name', true);
        $is_swap = !empty($existing_company);

        // Company meta keys to transfer/swap
        $meta_keys = [
            'mc_company_name',
            'mc_company_share_code',
            'mc_company_logo_id',
            'mc_invited_employees',
            'mc_employer_status',
            'mc_subscription_plan',
        ];

        if ($is_swap) {
            // SWAP: exchange all company meta between users
            $old_meta = [];
            $new_meta = [];
            foreach ($meta_keys as $key) {
                $old_meta[$key] = get_user_meta($old_employer_id, $key, true);
                $new_meta[$key] = get_user_meta($new_user->ID, $key, true);
            }

            foreach ($meta_keys as $key) {
                // Put target's company data onto old employer
                if ($new_meta[$key] !== '' && $new_meta[$key] !== false) {
                    update_user_meta($old_employer_id, $key, $new_meta[$key]);
                } else {
                    delete_user_meta($old_employer_id, $key);
                }

                // Put old employer's company data onto target user
                if ($old_meta[$key] !== '' && $old_meta[$key] !== false) {
                    update_user_meta($new_user->ID, $key, $old_meta[$key]);
                } else {
                    delete_user_meta($new_user->ID, $key);
                }
            }

            // Swap employee ownership links
            $old_employees = get_users([
                'meta_key' => 'mc_linked_employer_id',
                'meta_value' => $old_employer_id,
                'fields' => 'ID'
            ]);
            $new_employees = get_users([
                'meta_key' => 'mc_linked_employer_id',
                'meta_value' => $new_user->ID,
                'fields' => 'ID'
            ]);

            foreach ($old_employees as $emp_id) {
                update_user_meta($emp_id, 'mc_linked_employer_id', $new_user->ID);
            }
            foreach ($new_employees as $emp_id) {
                update_user_meta($emp_id, 'mc_linked_employer_id', $old_employer_id);
            }

            // Ensure both users are employers after swap
            $new_user_obj = new WP_User($new_user->ID);
            $new_user_obj->add_role(MC_Roles::ROLE_EMPLOYER);
            $old_user_obj = new WP_User($old_employer_id);
            $old_user_obj->add_role(MC_Roles::ROLE_EMPLOYER);

            $old_user_data = get_userdata($old_employer_id);
            $old_company = $old_meta['mc_company_name'] ?: 'N/A';
            $new_company = $new_meta['mc_company_name'] ?: 'N/A';

            wp_send_json_success([
                'message' => sprintf(
                    'Companies swapped! \"%s\" is now assigned to %s and \"%s\" is now assigned to %s. %d + %d employee(s) updated.',
                    $old_company,
                    $new_user->display_name,
                    $new_company,
                    $old_user_data ? $old_user_data->display_name : 'the original user',
                    count($old_employees),
                    count($new_employees)
                ),
                'redirect' => admin_url('admin.php?page=mc-super-admin')
            ]);
        }

        // ONE-WAY TRANSFER: target has no company
        foreach ($meta_keys as $key) {
            $value = get_user_meta($old_employer_id, $key, true);
            if ($value !== '' && $value !== false) {
                update_user_meta($new_user->ID, $key, $value);
            }
            delete_user_meta($old_employer_id, $key);
        }

        // Set employer role on new user, remove from old
        $new_user_obj = new WP_User($new_user->ID);
        $new_user_obj->add_role(MC_Roles::ROLE_EMPLOYER);

        $old_user_obj = new WP_User($old_employer_id);
        $old_user_obj->remove_role(MC_Roles::ROLE_EMPLOYER);
        if (empty($old_user_obj->roles)) {
            $old_user_obj->set_role('subscriber');
        }

        // Update all employees to point to new employer
        $employees = get_users([
            'meta_key' => 'mc_linked_employer_id',
            'meta_value' => $old_employer_id,
            'fields' => 'ID'
        ]);

        foreach ($employees as $emp_id) {
            update_user_meta($emp_id, 'mc_linked_employer_id', $new_user->ID);
        }

        $company_name = get_user_meta($new_user->ID, 'mc_company_name', true);
        wp_send_json_success([
            'message' => sprintf(
                'Company \"%s\" reassigned to %s (%s). %d employee(s) updated.',
                $company_name,
                $new_user->display_name,
                $new_email,
                count($employees)
            ),
            'redirect' => admin_url('admin.php?page=mc-super-admin')
        ]);
    }

    /**
     * Add "Switch Back to Admin" button to admin bar
     */
    public function add_switch_back_button($wp_admin_bar)
    {
        // Check if we are in a switched state using WP User Switch plugin's cookie
        if (!function_exists('wpus_get_switched_user')) {
            return;
        }

        $original_user = wpus_get_switched_user();

        // Only show if we have a stored original user (meaning we're in a switched session)
        if (!$original_user) {
            return;
        }

        // Hide if the current user is the same as the original user (not switched)
        if ($original_user->ID === get_current_user_id()) {
            return;
        }

        // Create switch back URL using WP User Switch plugin format
        $switch_back_url = admin_url('admin.php?page=wp-userswitch&wpus_username=' . sanitize_user($original_user->user_login) . '&wpus_userid=' . $original_user->ID . '&redirect=' . urlencode(admin_url('admin.php?page=mc-super-admin')) . '&wpus_nonce=' . wp_create_nonce('wp_user_switch_req'));

        // Add button to admin bar
        $wp_admin_bar->add_node(array(
            'id' => 'mc-switch-back',
            'title' => '<span class="ab-icon dashicons dashicons-undo"></span> Switch Back to ' . esc_html($original_user->display_name),
            'href' => esc_url($switch_back_url),
            'meta' => array(
                'class' => 'mc-switch-back-btn',
                'title' => 'Switch back to your original account'
            )
        ));
    }

    /**
     * AJAX: Generate test data for user.
     */
    public function ajax_generate_test_data()
    {
        check_ajax_referer('mc_admin_testing_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'average');

        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }

        // Helper to generate answers based on strain level (base_score)
        // 1 = Excellent (Low Strain) -> Ans: 1-2 (mostly 1)
        // 3 = Average (Mod Strain)   -> Ans: 2-3 (mostly 2, some 3) - was too high
        // 5 = Poor (High Strain)     -> Ans: 4-5
        $get_ans = function ($base) {
            if ($base == 1)
                return rand(1, 2); // Mostly 1-2
            if ($base == 5)
                return rand(4, 5); // Mostly 4-5
            return rand(2, 3); // Spread 2-3 (lowered from 2-4 to avoid 75% strain)
        };

        // Helper for capability scores (add variation)
        $get_score = function ($min, $max) {
            return rand($min, $max);
        };

        $base_score = 3;
        $score_min = 12; // Moderate skills
        $score_max = 24;

        if ($type === 'excellent') {
            $base_score = 1;
            $score_min = 18; // High skills
            $score_max = 30; // Max
        }
        if ($type === 'poor') {
            $base_score = 5;
            $score_min = 5; // Low skills
            $score_max = 16;
        }

        // 1. Generate MI Data (Range 0-30 usually)
        $mi_scores = [
            'linguistic' => $get_score($score_min, $score_max),
            'logical' => $get_score($score_min, $score_max),
            'spatial' => $get_score($score_min, $score_max),
            'musical' => $get_score($score_min, $score_max),
            'kinesthetic' => $get_score($score_min, $score_max),
            'interpersonal' => $get_score($score_min, $score_max),
            'intrapersonal' => $get_score($score_min, $score_max),
            'naturalist' => $get_score($score_min, $score_max),
            // Strain placeholders (Corrected multipliers based on question counts)
            // MI: Rumination (4), Avoidance (3), Emotional Flood (3)
            'si-rumination' => $get_ans($base_score) * 4,
            'si-avoidance' => $get_ans($base_score) * 3,
            'si-emotional-flood' => $get_ans($base_score) * 3
        ];

        $mi_answers = [
            "When I face a difficult decision, I need extra time to figure out the 'right' move." => $get_ans($base_score),
            "I replay conversations or choices in my head to see what I could have done differently." => $get_ans($base_score),
            "I sometimes feel mentally 'stuck' when two of my thoughts or values clash." => $get_ans($base_score),
            "I often need to think through many possibilities before I can focus." => $get_ans($base_score),
            "I often feel a strong urge to step away from a problem when it becomes confusing." => $get_ans($base_score),
            "If a task feels uncertain, I tend to put it off until I feel more ready." => $get_ans($base_score),
            "I prefer learning environments where I can step away and come back when it feels right." => $get_ans($base_score),
            "When trying something new, my emotions can shift suddenly depending on how it goes." => $get_ans($base_score),
            "When too much is happening at once, I feel overwhelmed before I can think clearly." => $get_ans($base_score),
            "Strong feelings can suddenly disrupt my ability to stay engaged with a task." => $get_ans($base_score)
        ];

        $mi_results = [
            'scores' => $mi_scores,
            'sortedScores' => [],
            'ageGroup' => 'adult',
            'part1Scores' => $mi_scores,
            'answers' => $mi_answers
        ];

        update_user_meta($user_id, 'miq_quiz_results', $mi_results);


        // 2. Generate CDT Data (Range 0-20ish)
        // Tune range: if score_max is 24 (MI), CDT max is usually 20. Let's scale slightly or use direct variation.
        $cdt_min = intval($score_min * 0.8);
        $cdt_max = intval($score_max * 0.8);

        $cdt_scores = [
            'ambiguity-tolerance' => $get_score($cdt_min, $cdt_max),
            'value-conflict-navigation' => $get_score($cdt_min, $cdt_max),
            'self-confrontation-capacity' => $get_score($cdt_min, $cdt_max),
            'discomfort-regulation' => $get_score($cdt_min, $cdt_max),
            'conflict-resolution-tolerance' => $get_score($cdt_min, $cdt_max),
            // CDT: Rumination (3), Avoidance (4), Emotional Flood (3)
            'si-rumination' => $get_ans($base_score) * 3,
            'si-avoidance' => $get_ans($base_score) * 4,
            'si-emotional-flood' => $get_ans($base_score) * 3
        ];

        $cdt_answers = [
            "When things do not make sense yet, I keep turning the problem over in my mind." => $get_ans($base_score),
            "I often try to mentally solve confusion before I move forward." => $get_ans($base_score),
            "Even after making a decision, I sometimes revisit it in my mind repeatedly." => $get_ans($base_score),
            "When I face two good options, I sometimes freeze and struggle to choose." => $get_ans($base_score),
            "When I am unsure what is right, I tend to delay taking action." => $get_ans($base_score),
            "When things feel uncertain, I prefer to step back rather than push ahead." => $get_ans($base_score),
            "If something feels ambiguous, I may wait for clarity instead of acting right away." => $get_ans($base_score),
            "Unexpected changes can hit me emotionally before I can think them through." => $get_ans($base_score),
            "Contradictions or mixed signals can feel emotionally intense for me." => $get_ans($base_score),
            "Confusing situations can create a rush of feelings that make clarity hard." => $get_ans($base_score)
        ];

        $cdt_results = [
            'scores' => $cdt_scores,
            'sortedScores' => [],
            'ageGroup' => 'adult',
            'part1Scores' => $cdt_scores,
            'answers' => $cdt_answers
        ];

        update_user_meta($user_id, 'cdt_quiz_results', $cdt_results);


        // 3. Generate Bartle Data
        $bartle_scores = [
            'explorer' => $get_score($cdt_min, $cdt_max),
            'achiever' => $get_score($cdt_min, $cdt_max),
            'socializer' => $get_score($cdt_min, $cdt_max),
            'strategist' => $get_score($cdt_min, $cdt_max),
            // Bartle: Rumination (4), Avoidance (3), Emotional Flood (3)
            'si-rumination' => $get_ans($base_score) * 4,
            'si-avoidance' => $get_ans($base_score) * 3,
            'si-emotional-flood' => $get_ans($base_score) * 3
        ];

        $bartle_answers = [
            "After a challenge or interaction, I often think about it long after it is over." => $get_ans($base_score),
            "I often explore ideas deeply because I want to understand every angle first." => $get_ans($base_score),
            "I sometimes research or plan so much that starting becomes difficult." => $get_ans($base_score),
            "I often revisit past choices to hunt for patterns or lessons." => $get_ans($base_score),
            "I sometimes hesitate to start something new if I am not sure I will do it well." => $get_ans($base_score),
            "When competition or pressure rises, I might withdraw instead of pushing harder." => $get_ans($base_score),
            "When tasks feel overwhelming, I tend to step away for a break." => $get_ans($base_score),
            "In group settings, strong emotions can throw me off track." => $get_ans($base_score),
            "If something unexpected happens, my emotions can spike quickly." => $get_ans($base_score),
            "My reactions can become intense when something important feels threatened." => $get_ans($base_score)
        ];

        $bartle_results = [
            'scores' => $bartle_scores,
            'sortedScores' => [],
            'ageGroup' => 'adult',
            'part1Scores' => $bartle_scores,
            'answers' => $bartle_answers
        ];

        update_user_meta($user_id, 'bartle_quiz_results', $bartle_results);

        // 3a. Save Metadata for Admin Report
        update_user_meta($user_id, 'mc_last_test_data_type', $type);
        update_user_meta($user_id, 'mc_last_test_data_timestamp', current_time('mysql'));

        // 3b. Handle Role Context (Vital for AI "Fit" Score)
        $custom_title = sanitize_text_field($_POST['role_title'] ?? '');
        $custom_resp = sanitize_text_field($_POST['role_resp'] ?? '');

        if (!empty($custom_title) || !empty($custom_resp)) {
            $custom_role = [
                'role' => $custom_title ?: 'Standard Role (Test)',
                'responsibilities' => $custom_resp ?: 'General duties.',
                'environment' => 'General professional environment.'
            ];
            update_user_meta($user_id, 'mc_employee_role_context', $custom_role);
        } else {
            // If no custom role provided, ensure at least a default exists
            $existing_role = get_user_meta($user_id, 'mc_employee_role_context', true);
            if (empty($existing_role)) {
                $mock_role = [
                    'role' => 'Growth Operations Lead (Test)',
                    'responsibilities' => 'Handle high-pressure deadlines, make autonomous decisions, manage complex stakeholder conflicts.',
                    'environment' => 'Fast-paced, high-autonomy, resilience-critical.'
                ];
                update_user_meta($user_id, 'mc_employee_role_context', $mock_role);
            }
        }

        // 4. Trigger Completion & Strain Index & Force AI Analysis
        try {
            if (class_exists('MC_Funnel')) {
                // This checks completion status and sends email if first time
                MC_Funnel::check_completion_and_notify($user_id);
            }

            if (class_exists('MC_Strain_Index_Scorer')) {
                MC_Strain_Index_Scorer::calculate_from_user_meta($user_id);
            }

            // FORCE fresh analysis for test data (bypasses "already completed" check in funnel)
            if (class_exists('Micro_Coach_AI')) {
                Micro_Coach_AI::generate_analysis_on_completion($user_id);
            }
        } catch (Throwable $e) {
            error_log('MC Test Data Generation Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Error generating test data: ' . $e->getMessage()]);
        }

        wp_send_json_success(['message' => 'Test data generated successfully']);
    }

}
