<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles the Employer Onboarding Flow.
 */
class MC_Employer_Onboarding
{
    public static function init()
    {
        add_shortcode('mc_employer_onboarding', [__CLASS__, 'render_onboarding']);
        add_action('init', [__CLASS__, 'handle_form_submission']);
        add_action('template_redirect', [__CLASS__, 'check_access']);
    }

    /**
     * Redirect non-logged in users to login page.
     * Gate access: only users with the employer role (or a valid employer_code) can proceed.
     */
    public static function check_access()
    {
        if (!is_page()) {
            return;
        }

        global $post;
        if (!$post || !has_shortcode($post->post_content, 'mc_employer_onboarding')) {
            return;
        }

        // Redirect non-logged in users to login (preserving employer_code in redirect)
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            if (isset($_GET['employer_code'])) {
                $login_url = add_query_arg('redirect_to', urlencode(add_query_arg('employer_code', sanitize_text_field($_GET['employer_code']), get_permalink())), wp_login_url());
            }
            wp_redirect($login_url);
            exit;
        }

        // If user is already an employer, allow through
        $user = wp_get_current_user();
        if (in_array(MC_Roles::ROLE_EMPLOYER, (array) $user->roles) || current_user_can('manage_options')) {
            return;
        }

        // Check for employer_code and validate
        if (isset($_GET['employer_code'])) {
            $code = sanitize_text_field($_GET['employer_code']);
            $employer_id = self::validate_employer_invite_code($code);

            if ($employer_id) {
                // Promote this user to employer and transfer the pre-created company data
                self::claim_employer_invite($user->ID, $employer_id);
                // Reload the page without the code param so they land clean
                wp_redirect(remove_query_arg('employer_code'));
                exit;
            }
        }

        // No valid code and not an employer — redirect to employer landing with error
        $landing_url = home_url('/employer-landing/');
        if (class_exists('MC_Funnel')) {
            $found = MC_Funnel::find_page_by_shortcode('mc_employer_landing');
            if ($found) {
                $landing_url = $found;
            }
        }
        wp_redirect(add_query_arg('access_denied', '1', $landing_url));
        exit;
    }

    /**
     * Validate an employer invite code against user meta.
     *
     * @param string $code The invite code to validate.
     * @return int|false The employer user ID if valid, false otherwise.
     */
    public static function validate_employer_invite_code($code)
    {
        if (empty($code)) {
            return false;
        }

        $args = [
            'meta_key' => 'mc_employer_invite_code',
            'meta_value' => $code,
            'number' => 1,
            'fields' => 'ID'
        ];
        $query = new WP_User_Query($args);
        $results = $query->get_results();

        return !empty($results) ? intval($results[0]) : false;
    }

    /**
     * Claim an employer invite: promote user to employer and transfer company data.
     *
     * @param int $user_id The user being promoted.
     * @param int $employer_id The pre-created employer account.
     */
    private static function claim_employer_invite($user_id, $employer_id)
    {
        // If the invite was created for a different user (admin pre-created), transfer data
        if ($user_id !== $employer_id) {
            // Transfer company meta from pre-created account
            $meta_keys = [
                'mc_company_name',
                'mc_company_share_code',
                'mc_company_logo_id',
                'mc_employer_status',
                'mc_subscription_plan',
                'mc_employer_invite_code',
                'mc_workplace_industry',
                'mc_workplace_values',
                'mc_workplace_culture',
                'mc_workplace_context',
            ];

            foreach ($meta_keys as $key) {
                $value = get_user_meta($employer_id, $key, true);
                if ($value !== '' && $value !== false) {
                    update_user_meta($user_id, $key, $value);
                }
            }
        }

        // Assign employer role
        $user = new WP_User($user_id);
        $user->add_role(MC_Roles::ROLE_EMPLOYER);

        // Ensure status is set
        if (!get_user_meta($user_id, 'mc_employer_status', true)) {
            update_user_meta($user_id, 'mc_employer_status', 'active');
        }

        // Mark the invite code as claimed
        update_user_meta($user_id, 'mc_employer_invite_claimed', current_time('mysql'));
    }

    /**
     * Renders the onboarding shortcode.
     */
    public static function render_onboarding()
    {
        if (!is_user_logged_in()) {
            return ''; // Should have been redirected
        }

        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;

        // Auto-skip onboarding if the employer already has at least one invited employee
        // Only redirect on step 1 (default entry point); step 2 is also used from the
        // dashboard's "Invite More Employees" link so we allow it through.
        if ($step === 1) {
            $user_id = get_current_user_id();
            $invited = get_user_meta($user_id, 'mc_invited_employees', true);
            $has_linked = get_users([
                'meta_key' => 'mc_linked_employer_id',
                'meta_value' => $user_id,
                'fields' => 'ID',
                'number' => 1
            ]);
            if ((!empty($invited) && is_array($invited)) || !empty($has_linked)) {
                $dashboard_url = home_url('/employer-dashboard/');
                if (class_exists('MC_Funnel')) {
                    $found = MC_Funnel::find_page_by_shortcode('mc_employer_dashboard');
                    if ($found) {
                        $dashboard_url = $found;
                    }
                }
                if (!headers_sent()) {
                    wp_redirect($dashboard_url);
                    exit;
                }
                return '<script>window.location.href="' . esc_url($dashboard_url) . '";</script>';
            }
        }

        ob_start();
        ?>
        <div class="mc-onboarding-wrapper">
            <header class="mc-site-header">
                <div class="mc-logo">The Science of Teamwork</div>
                <div class="mc-nav">
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>">Logout</a>
                </div>
            </header>

            <div class="mc-onboarding-container">
                <?php if ($step === 1): ?>
                    <?php self::render_step_1(); ?>
                <?php elseif ($step === 2): ?>
                    <?php self::render_step_2(); ?>
                <?php else: ?>
                    <?php self::render_complete(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Step 1: Whitelabeling (Company Name)
     */
    private static function render_step_1()
    {
        $user_id = get_current_user_id();
        $company_name = get_user_meta($user_id, 'mc_company_name', true);
        $logo_id = get_user_meta($user_id, 'mc_company_logo_id', true);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
        $industry = get_user_meta($user_id, 'mc_workplace_industry', true);
        $values = get_user_meta($user_id, 'mc_workplace_values', true);
        $culture = get_user_meta($user_id, 'mc_workplace_culture', true);
        ?>
        <div class="mc-onboarding-step">
            <div class="mc-step-indicator">
                <div class="mc-step-badge">Step 1 of 2</div>
            </div>
            <h2>Setup Your Company</h2>
            <p class="mc-step-description">Complete your company profile to help us provide contextual insights for your team.
            </p>

            <form method="post" action="" enctype="multipart/form-data" class="mc-onboarding-form">
                <?php wp_nonce_field('mc_onboarding_step_1', 'mc_onboarding_nonce'); ?>
                <input type="hidden" name="mc_onboarding_step" value="1">

                <div class="mc-form-group">
                    <label for="company_name">Company Name <span class="mc-required">*</span></label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name); ?>"
                        required class="mc-input" placeholder="Acme Corporation">
                </div>

                <div class="mc-form-group">
                    <label for="company_logo">Company Logo <span class="mc-optional">(Optional)</span></label>
                    <?php if ($logo_url): ?>
                        <div class="mc-current-logo">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Current Logo">
                            <span class="mc-logo-label">Current Logo</span>
                        </div>
                    <?php endif; ?>
                    <div class="mc-file-input-wrapper">
                        <input type="file" id="company_logo" name="company_logo" accept="image/png,image/jpeg,image/jpg"
                            class="mc-file-input">
                        <label for="company_logo" class="mc-file-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <span class="mc-file-text">Choose File</span>
                        </label>
                    </div>
                    <p class="mc-input-helper">Upload a PNG or JPG file (max 2MB). This will appear on employee invitations.</p>
                </div>

                <div class="mc-form-group">
                    <label for="industry">Industry / Sector <span class="mc-required">*</span></label>
                    <input type="text" id="industry" name="industry" value="<?php echo esc_attr($industry); ?>" required
                        class="mc-input" placeholder="e.g. Tech, Healthcare, Retail">
                    <p class="mc-input-helper">This helps us provide industry-specific insights.</p>
                </div>

                <div class="mc-form-group">
                    <label for="values">Company Values <span class="mc-required">*</span></label>
                    <textarea id="values" name="values" rows="3" required class="mc-input"
                        placeholder="e.g. Innovation, Integrity, Customer Obsession"><?php echo esc_textarea($values); ?></textarea>
                    <p class="mc-input-helper">List your core values that define your company culture.</p>
                </div>

                <div class="mc-form-group">
                    <label for="culture">Company Culture / About <span class="mc-required">*</span></label>
                    <textarea id="culture" name="culture" rows="3" required class="mc-input"
                        placeholder="Briefly describe your work environment..."><?php echo esc_textarea($culture); ?></textarea>
                    <p class="mc-input-helper">Help us understand your team's working environment.</p>
                </div>

                <div class="mc-form-actions">
                    <button type="submit" class="mc-button mc-button-primary mc-button-large">
                        Next: Invite Employees
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Step 2: Invite Employees
     */
    private static function render_step_2()
    {
        ?>
        <div class="mc-onboarding-step">
            <div class="mc-step-indicator">
                <div class="mc-step-badge">Step 2 of 2</div>
            </div>
            <h2>Invite Your Team</h2>
            <p class="mc-step-description">Send assessment invitations to your employees. You'll be able to:</p>

            <div class="mc-benefits-grid">
                <div class="mc-benefit-card">
                    <div class="mc-benefit-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                    </div>
                    <h3>Track Progress</h3>
                    <p>See who has completed their assessments</p>
                </div>
                <div class="mc-benefit-card">
                    <div class="mc-benefit-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                    </div>
                    <h3>View Results</h3>
                    <p>Gain insights into strengths and working styles</p>
                </div>
                <div class="mc-benefit-card">
                    <div class="mc-benefit-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <h3>Foster Growth</h3>
                    <p>Help employees develop and succeed</p>
                </div>
            </div>

            <form method="post" action="" class="mc-onboarding-form">
                <?php wp_nonce_field('mc_onboarding_step_2', 'mc_onboarding_nonce'); ?>
                <input type="hidden" name="mc_onboarding_step" value="2">

                <div class="mc-form-group">
                    <label>Employee Details</label>
                    <p class="mc-input-helper">Enter the name and email for each employee you'd like to invite. At least one is
                        required.</p>

                    <div id="mc-invite-rows" class="mc-invite-container">
                        <div class="mc-invite-group">
                            <h4 class="mc-invite-group-title">Employee 1</h4>
                            <div class="mc-invite-row">
                                <input type="text" name="employees[0][name]" placeholder="Full Name" class="mc-input" required>
                                <input type="email" name="employees[0][email]" placeholder="email@company.com" class="mc-input"
                                    required>
                                <input type="text" name="employees[0][role]" placeholder="Role Title"
                                    class="mc-input mc-full-width" required>
                                <textarea name="employees[0][responsibilities]" placeholder="Key Responsibilities&#10;• "
                                    class="mc-input mc-full-width" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="mc-invite-group">
                            <h4 class="mc-invite-group-title">Employee 2</h4>
                            <div class="mc-invite-row">
                                <input type="text" name="employees[1][name]" placeholder="Full Name" class="mc-input">
                                <input type="email" name="employees[1][email]" placeholder="email@company.com" class="mc-input">
                                <input type="text" name="employees[1][role]" placeholder="Role Title"
                                    class="mc-input mc-full-width">
                                <textarea name="employees[1][responsibilities]" placeholder="Key Responsibilities&#10;• "
                                    class="mc-input mc-full-width" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="mc-invite-group">
                            <h4 class="mc-invite-group-title">Employee 3</h4>
                            <div class="mc-invite-row">
                                <input type="text" name="employees[2][name]" placeholder="Full Name" class="mc-input">
                                <input type="email" name="employees[2][email]" placeholder="email@company.com" class="mc-input">
                                <input type="text" name="employees[2][role]" placeholder="Role Title"
                                    class="mc-input mc-full-width">
                                <textarea name="employees[2][responsibilities]" placeholder="Key Responsibilities&#10;• "
                                    class="mc-input mc-full-width" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="mc-add-row-btn" class="mc-button-add-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                        Add Another Employee
                    </button>
                </div>

                <div class="mc-form-actions">
                    <a href="?step=1" class="mc-button mc-button-secondary mc-button-large">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back
                    </a>
                    <button type="submit" class="mc-button mc-button-primary mc-button-large">
                        Send Invites & Finish
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </form>

            <script>
                document.getElementById('mc-add-row-btn').addEventListener('click', function () {
                    const container = document.getElementById('mc-invite-rows');
                    const index = container.children.length;
                    const group = document.createElement('div');
                    group.className = 'mc-invite-group';
                    group.innerHTML = `
                        <h4 class="mc-invite-group-title">Employee ${index + 1}</h4>
                        <div class="mc-invite-row">
                            <input type="text" name="employees[${index}][name]" placeholder="Full Name" class="mc-input">
                            <input type="email" name="employees[${index}][email]" placeholder="email@company.com" class="mc-input">
                            <input type="text" name="employees[${index}][role]" placeholder="Role Title" class="mc-input mc-full-width">
                            <textarea name="employees[${index}][responsibilities]" placeholder="Key Responsibilities\n• " class="mc-input mc-full-width" rows="4"></textarea>
                        </div>
                    `;
                    container.appendChild(group);
                });

                // File input display
                const fileInput = document.getElementById('company_logo');
                if (fileInput) {
                    fileInput.addEventListener('change', function (e) {
                        const fileName = e.target.files[0]?.name;
                        const label = document.querySelector('.mc-file-text');
                        if (fileName && label) {
                            label.textContent = fileName;
                        }
                    });
                }
            </script>
        </div>
        <?php
    }

    /**
     * Completion Screen
     */
    private static function render_complete()
    {
        ?>
        <div class="mc-onboarding-step mc-success-step">
            <div class="mc-success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 12l3 3 5-6"></path>
                </svg>
            </div>
            <h2>You're All Set!</h2>
            <p class="mc-success-message">Your company profile is set up and invitation emails have been sent to your team.</p>
            <div class="mc-success-details">
                <div class="mc-success-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Company profile created</span>
                </div>
                <div class="mc-success-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Team invitations sent</span>
                </div>
                <div class="mc-success-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Dashboard ready to use</span>
                </div>
            </div>
            <div class="mc-success-actions">
                <a href="<?php echo esc_url(MC_Funnel::find_page_by_shortcode('mc_employer_dashboard')); ?>"
                    class="mc-button mc-button-primary mc-button-large">
                    Go to Dashboard
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form submissions
     */
    public static function handle_form_submission()
    {
        if (!isset($_POST['mc_onboarding_step']) || !isset($_POST['mc_onboarding_nonce'])) {
            return;
        }

        $step = intval($_POST['mc_onboarding_step']);
        if (!wp_verify_nonce($_POST['mc_onboarding_nonce'], 'mc_onboarding_step_' . $step)) {
            return;
        }

        $user_id = get_current_user_id();

        if ($step === 1) {
            if (isset($_POST['company_name'])) {
                update_user_meta($user_id, 'mc_company_name', sanitize_text_field($_POST['company_name']));

                // Save workplace context (both individual keys for backward compat and the
                // combined array that the employer dashboard reads)
                $industry = isset($_POST['industry']) ? sanitize_text_field($_POST['industry']) : '';
                $values   = isset($_POST['values'])   ? sanitize_textarea_field($_POST['values']) : '';
                $culture  = isset($_POST['culture'])   ? sanitize_textarea_field($_POST['culture']) : '';

                update_user_meta($user_id, 'mc_workplace_industry', $industry);
                update_user_meta($user_id, 'mc_workplace_values', $values);
                update_user_meta($user_id, 'mc_workplace_culture', $culture);

                update_user_meta($user_id, 'mc_workplace_context', [
                    'industry' => $industry,
                    'values'   => $values,
                    'culture'  => $culture,
                ]);

                // Handle Logo Upload
                if (!empty($_FILES['company_logo']['name'])) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');

                    $attachment_id = media_handle_upload('company_logo', 0);

                    if (!is_wp_error($attachment_id)) {
                        update_user_meta($user_id, 'mc_company_logo_id', $attachment_id);
                    }
                }

                wp_redirect(add_query_arg('step', 2));
                exit;
            }
        } elseif ($step === 2) {
            if (isset($_POST['employees']) && is_array($_POST['employees'])) {
                $invited_employees = [];
                $emails_to_invite = [];

                foreach ($_POST['employees'] as $employee) {
                    $email = sanitize_email($employee['email']);
                    $name = sanitize_text_field($employee['name']);
                    $role = sanitize_text_field($employee['role'] ?? '');
                    $responsibilities = sanitize_textarea_field($employee['responsibilities'] ?? '');

                    if (is_email($email)) {
                        $invited_employees[] = [
                            'email' => $email,
                            'name' => $name,
                            'role' => $role,
                            'responsibilities' => $responsibilities
                        ];
                        $emails_to_invite[] = [
                            'email' => $email,
                            'name' => $name
                        ];
                    }
                }

                if (!empty($invited_employees)) {
                    // Save invited employees to meta (merging with existing if needed, but for onboarding usually overwrites or appends)
                    // For simplicity in this flow, we'll append to existing invites to avoid wiping out previous ones if they go back
                    $existing_invites = get_user_meta($user_id, 'mc_invited_employees', true);
                    if (!is_array($existing_invites)) {
                        $existing_invites = [];
                    }

                    // Merge logic: Add new ones, avoid duplicates based on email
                    foreach ($invited_employees as &$new_invite) {
                        $new_invite['token'] = $user_id . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);

                        $exists = false;
                        foreach ($existing_invites as $key => $existing) {
                            // Handle backward compatibility where existing might be just a string (email)
                            $existing_email = is_array($existing) ? $existing['email'] : $existing;

                            if ($existing_email === $new_invite['email']) {
                                // Update name if it was missing or changed
                                if (is_array($existing)) {
                                    $existing_invites[$key]['name'] = $new_invite['name'];
                                    // Preserve existing token if present, otherwise use new one
                                    if (isset($existing_invites[$key]['token'])) {
                                        $new_invite['token'] = $existing_invites[$key]['token'];
                                    } else {
                                        $existing_invites[$key]['token'] = $new_invite['token'];
                                    }
                                } else {
                                    // Convert string to array
                                    $existing_invites[$key] = $new_invite;
                                }
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $existing_invites[] = $new_invite;
                        }
                    }
                    unset($new_invite); // break reference

                    update_user_meta($user_id, 'mc_invited_employees', $existing_invites);

                    // Generate Company Share Code if not exists
                    $share_code = get_user_meta($user_id, 'mc_company_share_code', true);
                    if (!$share_code) {
                        $share_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                        update_user_meta($user_id, 'mc_company_share_code', $share_code);
                    }

                    // Link the employer to themselves (so they don't see the "Join Team" prompt)
                    update_user_meta($user_id, 'mc_linked_employer_id', $user_id);

                    // Send invites
                    $company_name = get_user_meta($user_id, 'mc_company_name', true) ?: 'Our Company';
                    $logo_id = get_user_meta($user_id, 'mc_company_logo_id', true);
                    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
                    $subject = "You've been invited to join " . $company_name . "'s Assessment Platform";
                    $headers = ['Content-Type: text/html; charset=UTF-8'];

                    $employee_landing_url = '#';
                    if (class_exists('MC_Funnel')) {
                        $employee_landing_url = MC_Funnel::find_page_by_shortcode('mc_employee_landing') ?: home_url();
                    }

                    // Loop through the *original* new invites list to send emails
                    // We need to match them back to get their tokens if we want to be precise, 
                    // but since we assigned tokens to $new_invite in the loop above, $invited_employees has them.

                    foreach ($invited_employees as $invite) {
                        $email = $invite['email'];
                        $name = $invite['name'];
                        // Use the token if available, fall back to share code (should practically always have token now)
                        $code_to_use = $invite['token'] ?? $share_code;

                        // Add invite code to URL
                        $invite_link = add_query_arg('invite_code', $code_to_use, $employee_landing_url);

                        $greeting = $name ? "Hello " . esc_html($name) . "!" : "Hello!";

                        $message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; padding: 20px; }
                                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
                                .email-header { background: #2563eb; padding: 30px; text-align: center; }
                                .email-header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
                                .email-body { padding: 40px 30px; }
                                .email-body h2 { color: #1e293b; margin-top: 0; font-size: 22px; }
                                .email-body p { margin-bottom: 20px; font-size: 16px; color: #4a5568; }
                                .btn { display: inline-block; background-color: #2563eb; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; margin-top: 10px; }
                                .btn:hover { background-color: #1d4ed8; }
                                .invite-code-box { background: #f1f5f9; padding: 15px; border-radius: 6px; text-align: center; margin: 20px 0; border: 1px dashed #cbd5e1; }
                                .invite-code-label { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px; }
                                .invite-code { font-size: 24px; font-weight: 700; color: #1e293b; letter-spacing: 2px; margin-top: 5px; display: block; }
                                .email-footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; }
                                .alt-link { font-size: 12px; color: #94a3b8; margin-top: 20px; }
                                .alt-link a { color: #2563eb; text-decoration: underline; word-break: break-all; }
                            </style>
                        </head>
                        <body>
                            <div class='email-container'>
                                <div class='email-header'>
                                    " . ($logo_url ? "<img src='" . esc_url($logo_url) . "' alt='" . esc_attr($company_name) . "' style='max-height: 60px; max-width: 200px; margin-bottom: 16px; display: block; margin-left: auto; margin-right: auto;'>" : "") . "
                                    <h1>The Science of Teamwork</h1>
                                </div>
                                <div class='email-body'>
                                    <h2>" . $greeting . "</h2>
                                    <p>You have been invited by <strong>" . esc_html($company_name) . "</strong> to take your employee assessments.</p>
                                    <p>These assessments are designed to help uncover your unique strengths, working style, and potential for growth.</p>
                                    <p>To get started, you'll need to create a unique profile. Click the button below and follow the steps to set up your account.</p>
                                    
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='" . esc_url($invite_link) . "' class='btn'>Start Your Assessment</a>
                                    </div>

                                    <p class='alt-link'>If the button above doesn't work, copy and paste this link into your browser:<br>
                                    <a href='" . esc_url($invite_link) . "'>" . esc_url($invite_link) . "</a></p>
                                </div>
                                <div class='email-footer'>
                                    &copy; " . date('Y') . " The Science of Teamwork. All rights reserved.
                                </div>
                            </div>
                        </body>
                        </html>
                        ";

                        wp_mail($email, $subject, $message, $headers);
                    }
                }

                // Set employer status to active
                update_user_meta($user_id, 'mc_employer_status', 'active');

                // Redirect straight to the dashboard (skip completion screen)
                $dashboard_url = home_url('/employer-dashboard/');
                if (class_exists('MC_Funnel')) {
                    $found = MC_Funnel::find_page_by_shortcode('mc_employer_dashboard');
                    if ($found) {
                        $dashboard_url = $found;
                    }
                }
                wp_redirect($dashboard_url);
                exit;
            }
        }
    }
}
