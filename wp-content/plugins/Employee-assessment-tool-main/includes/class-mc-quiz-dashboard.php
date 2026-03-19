<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles the Quiz Dashboard.
 */
class MC_Quiz_Dashboard
{
    public static function init()
    {
        add_shortcode('quiz_dashboard', [__CLASS__, 'render_dashboard']);
    }

    public static function render_dashboard()
    {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);

        // Get quiz statuses
        $completion_status = MC_Funnel::get_completion_status($user_id);
        $config = MC_Funnel::get_config();

        // Handle Invite Code (URL, POST, or Cookie)
        $invite_message = '';
        $code = '';

        if (isset($_POST['mc_invite_code'])) {
            $code = sanitize_text_field($_POST['mc_invite_code']);
        } elseif (isset($_GET['invite_code'])) {
            $code = sanitize_text_field($_GET['invite_code']);
        } elseif (isset($_COOKIE['mc_invite_code'])) {
            $code = sanitize_text_field($_COOKIE['mc_invite_code']);
            // Clear the cookie after use
            if (!headers_sent()) {
                setcookie('mc_invite_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }

        if ($code) {
            // Find employer with this code
            $args = [
                'meta_key' => 'mc_company_share_code',
                'meta_value' => $code,
                'number' => 1,
                'fields' => 'ID'
            ];
            $employer_query = new WP_User_Query($args);
            $employers = $employer_query->get_results();

            if (!empty($employers)) {
                $employer_id = $employers[0];
                update_user_meta($user_id, 'mc_linked_employer_id', $employer_id);
                $invite_message = '<div class="mc-alert success">Successfully joined the team!</div>';
            } else {
                $invite_message = '<div class="mc-alert error">Invalid invite code.</div>';
            }
        }

        // Check if already linked
        $linked_employer_id = get_user_meta($user_id, 'mc_linked_employer_id', true);
        $linked_employer_name = $linked_employer_id ? get_user_meta($linked_employer_id, 'mc_company_name', true) : '';

        ob_start();
        ?>
        <div class="mc-dashboard-wrapper">
            <header class="mc-site-header">
                <div class="mc-logo">The Science of Teamwork</div>
                <div class="mc-nav">
                    <span class="mc-user-greeting">Hello, <?php echo esc_html($user_info->display_name); ?></span>
                    <?php
                    // Show "Go to Employer Dashboard" button for employers
                    $is_employer_check = !empty(get_user_meta($user_id, 'mc_invited_employees', true))
                        || !empty(get_user_meta($user_id, 'mc_company_share_code', true));
                    if ($is_employer_check) {
                        $employer_dashboard_url = MC_Funnel::find_page_by_shortcode('mc_employer_dashboard');
                        if ($employer_dashboard_url) {
                            echo '<a href="' . esc_url($employer_dashboard_url) . '" style="margin-right: 15px; font-weight: 500;">Go to Employer Dashboard</a>';
                        }
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
                <?php echo $invite_message; ?>


                <?php
                // Check if user is an employer
                $is_employer = !empty(get_user_meta($user_id, 'mc_invited_employees', true))
                    || !empty(get_user_meta($user_id, 'mc_company_share_code', true));

                // Only show the team status bar if:
                // 1. They're linked to an employer, OR
                // 2. They're NOT an employer (i.e., they're an employee who might need to join)
                if ($linked_employer_id || !$is_employer):
                    ?>
                    <div class="mc-team-status-bar">
                        <?php if ($linked_employer_id): ?>
                            <div class="mc-team-connected">
                                <?php
                                $employer_logo_id = get_user_meta($linked_employer_id, 'mc_company_logo_id', true);
                                $employer_logo_url = $employer_logo_id ? wp_get_attachment_url($employer_logo_id) : '';
                                if ($employer_logo_url):
                                    ?>
                                    <img src="<?php echo esc_url($employer_logo_url); ?>"
                                        alt="<?php echo esc_attr($linked_employer_name); ?> Logo" class="mc-team-logo-small">
                                <?php endif; ?>
                                <span class="mc-team-text">Linked to
                                    <strong><?php echo esc_html($linked_employer_name); ?></strong></span>
                                <span class="mc-team-badge success">✓ Connected</span>
                            </div>
                        <?php else: ?>
                            <div class="mc-team-invite">
                                <span class="mc-team-text">Have a company invite code?</span>
                                <form method="post" class="mc-invite-form-inline">
                                    <input type="text" name="mc_invite_code" placeholder="Enter Code (e.g. TEAM-123)" required>
                                    <button type="submit" class="mc-button small">Join Team</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mc-dashboard-header-row">
                    <div>
                        <h1>My Assessment Dashboard</h1>
                        <p class="mc-dashboard-intro">Track your progress and complete your assessments below.</p>
                    </div>
                    <?php
                    // Load quiz question files to access category data
                    $mi_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
                    if (file_exists($mi_questions_file)) {
                        require $mi_questions_file;
                    }
                    $cdt_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
                    if (file_exists($cdt_questions_file)) {
                        require $cdt_questions_file;
                    }
                    $bartle_questions_file = MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';
                    if (file_exists($bartle_questions_file)) {
                        require $bartle_questions_file;
                    }
                    ?>

                </div>

                <?php
                // Calculate completion for Motivational UI
                $total_steps = count($config['steps']);
                // Filter completion status to only count truthy values (actually completed steps)
                $completed_steps = count(array_filter($completion_status));
                $progress_percent = $total_steps > 0 ? ($completed_steps / $total_steps) * 100 : 0;
                $all_complete = $completed_steps >= $total_steps;

                if ($all_complete && $linked_employer_id):
                    ?>
                    <div class="mc-dashboard-unlock-card" style="border-color: #c6f6d5; background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);">
                        <div class="mc-unlock-content" style="text-align: center;">
                            <div style="font-size: 2.5em; margin-bottom: 0.25em;">✅</div>
                            <h3 style="font-size: 1.4em; margin: 0 0 0.5em;">You're All Done!</h3>
                            <p style="color: #4a5568; font-size: 1.05em; margin: 0 0 0.5em;">Thank you for completing all of your assessments.</p>
                            <p style="color: #4a5568; font-size: 1.05em; margin: 0 0 1.25em;">Your team lead will review your results and discuss them with you.</p>
                            <a href="<?php echo wp_logout_url(home_url()); ?>" class="mc-button" style="display: inline-block;">Log Out</a>
                        </div>
                    </div>
                <?php elseif (!$all_complete && !$linked_employer_id): ?>
                    <div class="mc-dashboard-unlock-card">
                        <div class="mc-unlock-content">
                            <div class="mc-unlock-icon">🧪</div>
                            <div class="mc-unlock-text">
                                <h3>Unlock Lab Mode</h3>
                                <p>Complete all <strong><?php echo $total_steps; ?></strong> assessments to unlock your personalized
                                    experiment lab.</p>
                                <div class="mc-progress-bar">
                                    <div class="mc-progress-fill" style="width: <?php echo intval($progress_percent); ?>%;"></div>
                                </div>
                                <p class="mc-progress-text"><?php echo $completed_steps; ?>/<?php echo $total_steps; ?> Completed
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mc-dashboard-tabs">
                    <button class="mc-tab-btn active" onclick="openTab(event, 'tab-assessments')"
                        data-tab="tab-assessments">Assessments</button>
                    <?php if (!$linked_employer_id): ?>
                    <button class="mc-tab-btn" onclick="openTab(event, 'tab-experiments')" data-tab="tab-experiments">Assigned
                        Experiments</button>
                    <?php endif; ?>
                    <?php
                    $custom_tabs = apply_filters('mc_dashboard_custom_tabs', []);
                    foreach ($custom_tabs as $tab_id => $tab_label) {
                        echo '<button class="mc-tab-btn" onclick="openTab(event, \'' . esc_attr($tab_id) . '\')" data-tab="' . esc_attr($tab_id) . '">' . esc_html($tab_label) . '</button>';
                    }
                    ?>
                </div>

                <div class="mc-dashboard-layout">
                    <div class="mc-dashboard-main">
                        <!-- Assessments Tab -->
                        <div id="tab-assessments" class="mc-tab-content" style="display: block;">
                            <style>
                                .mc-dashboard-grid {
                                    display: flex !important;
                                    flex-direction: column !important;
                                    gap: 24px !important;
                                    width: 100% !important;
                                }

                                .mc-dashboard-card {
                                    width: 100% !important;
                                    display: flex;
                                    flex-direction: column;
                                    padding: 24px;
                                    border: 1px solid #e0e0e0;
                                    border-radius: 12px;
                                    background: #fff;
                                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                                    transition: transform 0.2s, box-shadow 0.2s;
                                    box-sizing: border-box !important;
                                }

                                .mc-dashboard-card:hover {
                                    transform: translateY(-2px);
                                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                                }

                                .mc-card-header {
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                    margin-bottom: 15px;
                                }

                                .mc-card-title-group {
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                }

                                .mc-card-icon {
                                    font-size: 24px;
                                    width: 48px;
                                    height: 48px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    background: #f0f4f8;
                                    border-radius: 50%;
                                    flex-shrink: 0;
                                }

                                .mc-card-title-text h3 {
                                    margin: 0 0 4px 0;
                                    font-size: 1.2em;
                                    color: #333;
                                }

                                .mc-card-status-badge {
                                    font-size: 0.85em;
                                    padding: 4px 12px;
                                    border-radius: 20px;
                                    font-weight: 500;
                                }

                                .mc-card-status-badge.completed {
                                    background: #e8f5e9;
                                    color: #2e7d32;
                                }

                                .mc-card-status-badge.pending {
                                    background: #f5f5f5;
                                    color: #757575;
                                }

                                .mc-card-summary {
                                    margin-top: 5px;
                                    padding-top: 15px;
                                    border-top: 1px solid #eee;
                                }

                                .mc-summary-label {
                                    font-size: 0.9em;
                                    font-weight: 600;
                                    color: #555;
                                    margin-bottom: 8px;
                                    display: block;
                                }

                                .mc-tag-group {
                                    display: flex;
                                    flex-wrap: wrap;
                                    gap: 8px;
                                    margin-bottom: 10px;
                                }

                                .mc-tag {
                                    background: #e3f2fd;
                                    color: #1565c0;
                                    padding: 6px 12px;
                                    border-radius: 16px;
                                    font-size: 0.9em;
                                    font-weight: 500;
                                }

                                .mc-tag.strength {
                                    background: #e8f5e9;
                                    color: #2e7d32;
                                }

                                .mc-tag.growth {
                                    background: #fff3e0;
                                    color: #ef6c00;
                                }

                                .mc-description {
                                    font-size: 0.95em;
                                    color: #666;
                                    line-height: 1.5;
                                    margin-top: 10px;
                                }

                                .mc-card-actions {
                                    margin-top: 20px;
                                    display: flex;
                                    justify-content: flex-end;
                                }
                            </style>
                            <div class="mc-dashboard-grid"
                                style="display: flex; flex-direction: column; gap: 24px; width: 100%;">
                                <?php
                                $prev_completed = true; // First quiz is always unlocked
                                foreach ($config['steps'] as $slug):
                                    $title = $config['titles'][$slug] ?? ucfirst(str_replace('-', ' ', $slug));
                                    $is_completed = !empty($completion_status[$slug]);
                                    $is_locked = !$is_completed && !$prev_completed;
                                    $url = MC_Funnel::get_step_url($slug);

                                    // Determine Icon
                                    $icon = '📝'; // Default
                                    if ($slug === 'mi-quiz')
                                        $icon = '🧠';
                                    elseif ($slug === 'cdt-quiz')
                                        $icon = '🛡️';
                                    elseif ($slug === 'bartle-quiz')
                                        $icon = '🎮';
                                    elseif ($slug === 'johari-mi-quiz')
                                        $icon = '🪟';
                                    ?>
                                    <div class="mc-dashboard-card <?php echo $is_completed ? 'completed' : ($is_locked ? 'locked' : ''); ?>"
                                        style="width: 100%; box-sizing: border-box;<?php echo $is_locked ? ' opacity: 0.55; pointer-events: none;' : ''; ?>">
                                        <div class="mc-card-header">
                                            <div class="mc-card-title-group">
                                                <div class="mc-card-icon"><?php echo $is_locked ? '🔒' : $icon; ?></div>
                                                <div class="mc-card-title-text">
                                                    <h3><?php echo esc_html($title); ?></h3>
                                                    <?php if ($is_completed): ?>
                                                        <span class="mc-card-status-badge completed">Completed</span>
                                                    <?php elseif ($is_locked): ?>
                                                        <span class="mc-card-status-badge pending">Locked</span>
                                                    <?php else: ?>
                                                        <span class="mc-card-status-badge pending">Not Started</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="mc-card-actions">
                                            <?php if ($is_locked): ?>
                                                <span class="mc-button small disabled">Complete previous assessment first</span>
                                            <?php elseif ($is_completed && $linked_employer_id): ?>
                                                <!-- Employees don't see View Results -->
                                            <?php elseif ($url): ?>
                                                <a href="<?php echo esc_url($url); ?>" class="mc-button small">
                                                    <?php echo $is_completed ? 'View Results' : 'Start Assessment'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="mc-button small disabled">Unavailable</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php
                                    $prev_completed = $is_completed;
                                endforeach;
                                ?>
                            </div>
                        </div>

                        <!-- Experiments Tab -->
                        <div id="tab-experiments" class="mc-tab-content" style="display: none;">
                            <div class="mc-tab-intro">
                                <h3>About Assigned Experiments</h3>
                                <p>Experiments are micro-actions designed to help you apply your assessment insights in the real
                                    world. Your employer or coach may assign these to help you grow specific skills or adapt to
                                    your team's needs.</p>
                            </div>
                            <?php
                            $assigned_experiments = get_user_meta($user_id, 'mc_assigned_experiments', true);
                            if (empty($assigned_experiments) || !is_array($assigned_experiments)):
                                ?>
                                <div class="mc-empty-state">
                                    <p>You don't have any assigned experiments yet. Check back later!</p>
                                </div>
                            <?php else: ?>
                                <div class="mc-experiments-list">
                                    <?php foreach ($assigned_experiments as $exp):
                                        // Filter out drafts
                                        if (($exp['status'] ?? 'assigned') === 'draft')
                                            continue;
                                        ?>
                                        <div class="mc-experiment-card">
                                            <div class="mc-exp-header">
                                                <span class="mc-exp-lens"><?php echo esc_html($exp['lens']); ?> Lens</span>
                                                <span
                                                    class="mc-exp-date"><?php echo date('M j, Y', strtotime($exp['assigned_at'] ?? 'now')); ?></span>
                                            </div>
                                            <h3><?php echo esc_html($exp['title']); ?></h3>
                                            <div class="mc-exp-micro">
                                                <?php echo esc_html($exp['micro_description']); ?>
                                            </div>

                                            <div class="mc-exp-meta">
                                                <span><strong>Time:</strong>
                                                    <?php echo esc_html($exp['estimated_time'] ?? 'N/A'); ?></span>
                                                <span><strong>Energy:</strong>
                                                    <?php echo esc_html($exp['energy_level'] ?? 'N/A'); ?></span>
                                            </div>

                                            <details>
                                                <summary>View Details</summary>
                                                <div class="mc-exp-steps">
                                                    <p><strong>Why this fits you:</strong>
                                                        <?php echo esc_html($exp['why_it_fits'] ?? ''); ?></p>

                                                    <?php if (!empty($exp['steps'])): ?>
                                                        <h4>Steps:</h4>
                                                        <ul>
                                                            <?php foreach ($exp['steps'] as $step): ?>
                                                                <li><?php echo esc_html($step); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>

                                                    <?php if (!empty($exp['reflection_questions'])): ?>
                                                        <h4>Reflection:</h4>
                                                        <ul>
                                                            <?php foreach ($exp['reflection_questions'] as $q): ?>
                                                                <li><?php echo esc_html($q); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Render Custom Tabs Content
                        if (!empty($custom_tabs)) {
                            foreach ($custom_tabs as $tab_id => $tab_label) {
                                echo '<div id="' . esc_attr($tab_id) . '" class="mc-tab-content" style="display: none;">';
                                do_action('mc_dashboard_custom_tab_content', $tab_id);
                                echo '</div>';
                            }
                        }
                        ?>

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

                .mc-dashboard-container {
                    padding: 80px 0 40px 0;
                }

                .mc-dashboard-layout {
                    display: block;
                }

                @media (max-width: 900px) {
                    .mc-dashboard-layout {
                        grid-template-columns: 1fr;
                    }
                }

                .mc-dashboard-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                    gap: 20px;
                }

                .mc-dashboard-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                    display: flex;
                    flex-direction: column;
                }

                .mc-dashboard-card h3 {
                    margin-top: 0;
                    font-size: 1.1rem;
                    color: #1e293b;
                }

                .mc-card-status {
                    margin-bottom: 20px;
                }

                .mc-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    background: #f1f5f9;
                    color: #64748b;
                }

                .mc-badge.success {
                    background: #dcfce7;
                    color: #166534;
                }

                .mc-card-actions {
                    margin-top: auto;
                }

                .mc-button.small {
                    display: inline-block;
                    padding: 8px 16px;
                    background: #2563eb;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 6px;
                    font-size: 0.9rem;
                    transition: background 0.2s;
                    border: none;
                    cursor: pointer;
                }

                .mc-button.small:hover {
                    background: #1d4ed8;
                }

                .mc-button.disabled {
                    background: #cbd5e1;
                    cursor: not-allowed;
                }

                .mc-user-greeting {
                    margin-right: 15px;
                    color: #64748b;
                }

                .mc-dashboard-header-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                    flex-wrap: wrap;
                    gap: 20px;
                }

                .mc-button.outline {
                    background: transparent;
                    border: 1px solid #2563eb;
                    color: #2563eb;
                    padding: 8px 16px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-size: 0.9rem;
                    transition: all 0.2s;
                }

                .mc-button.outline:hover {
                    background: #eff6ff;
                }

                /* Sidebar Styles */
                .mc-sidebar-card {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 20px;
                }

                .mc-sidebar-logo {
                    text-align: center;
                    margin-bottom: 15px;
                }

                .mc-sidebar-logo img {
                    max-width: 100%;
                    max-height: 80px;
                    height: auto;
                }

                .mc-sidebar-card h3 {
                    margin-top: 0;
                    font-size: 1.1rem;
                    color: #1e293b;
                    margin-bottom: 15px;
                }

                .mc-invite-form input {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    margin-bottom: 10px;
                    box-sizing: border-box;
                }

                .mc-invite-form button {
                    width: 100%;
                }

                .mc-alert {
                    padding: 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                }

                .mc-alert.success {
                    background: #dcfce7;
                    color: #166534;
                    border: 1px solid #bbf7d0;
                }

                .mc-alert.error {
                    background: #fee2e2;
                    color: #991b1b;
                    border: 1px solid #fecaca;
                }

                .mc-linked-status {
                    color: #166534;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .mc-linked-status {
                    color: #166534;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                /* Tabs */
                .mc-dashboard-tabs {
                    margin-bottom: 20px;
                    border-bottom: 1px solid #e2e8f0;
                }

                .mc-tab-btn {
                    background: none;
                    border: none;
                    padding: 10px 20px;
                    font-size: 1rem;
                    font-weight: 600;
                    color: #64748b;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    margin-bottom: -1px;
                    transition: all 0.2s ease;
                    border-radius: 4px 4px 0 0;
                }

                .mc-tab-btn.active {
                    color: #2563eb;
                    border-bottom-color: #2563eb;
                    background: #eff6ff;
                }

                .mc-tab-btn:hover {
                    color: #1e293b;
                    background: #f8fafc;
                }

                /* Experiment Cards */
                .mc-experiments-list {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }

                .mc-experiment-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 20px;
                }

                .mc-exp-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 0.85rem;
                    color: #64748b;
                }

                .mc-exp-lens {
                    background: #f1f5f9;
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-weight: 600;
                }

                .mc-exp-micro {
                    color: #475569;
                    margin-bottom: 15px;
                }

                .mc-exp-meta {
                    display: flex;
                    gap: 15px;
                    font-size: 0.85rem;
                    color: #64748b;
                    margin-bottom: 15px;
                }

                .mc-exp-steps {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e2e8f0;
                }

                /* Motivational Unlock Card */
                .mc-dashboard-unlock-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 25px;
                    color: #1e293b;
                    margin-bottom: 30px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                }

                .mc-unlock-content {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }

                .mc-unlock-icon {
                    font-size: 2.5rem;
                    background: #f1f5f9;
                    width: 60px;
                    height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                }

                .mc-unlock-text h3 {
                    margin: 0 0 5px 0;
                    color: #1e293b;
                    font-size: 1.25rem;
                }

                .mc-unlock-text p {
                    margin: 0 0 15px 0;
                    color: #64748b;
                    font-size: 0.95rem;
                }

                .mc-progress-bar {
                    background: #f1f5f9;
                    height: 8px;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-bottom: 5px;
                    width: 100%;
                    max-width: 300px;
                    border: 1px solid #e2e8f0;
                }

                .mc-progress-fill {
                    background: #2563eb;
                    height: 100%;
                    border-radius: 4px;
                    transition: width 0.5s ease;
                }

                .mc-progress-text {
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: #64748b;
                    margin: 0 !important;
                }

                @media (max-width: 600px) {
                    .mc-unlock-content {
                        flex-direction: column;
                        text-align: center;
                    }

                    .mc-progress-bar {
                        margin: 0 auto 10px auto;
                    }
                }

                details summary {
                    cursor: pointer;
                    color: #2563eb;
                    font-weight: 600;
                }

                /* Team Status Bar */
                .mc-team-status-bar {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 12px 20px;
                    margin-bottom: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .mc-team-connected,
                .mc-team-invite {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    width: 100%;
                }

                .mc-team-invite {
                    justify-content: space-between;
                }

                .mc-team-logo-small {
                    height: 40px;
                    width: auto;
                    max-width: 120px;
                    object-fit: contain;
                }

                .mc-team-text {
                    color: #475569;
                    font-size: 0.95rem;
                }

                .mc-team-badge {
                    background: #dcfce7;
                    color: #166534;
                    padding: 4px 10px;
                    border-radius: 999px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    margin-left: auto;
                }

                .mc-invite-form-inline {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }

                .mc-invite-form-inline input {
                    padding: 6px 12px;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    font-size: 0.9rem;
                }
            </style>
            <script>
                function openTab(evt, tabName) {
                    var i, tabcontent, tablinks;
                    tabcontent = document.getElementsByClassName("mc-tab-content");
                    for (i = 0; i < tabcontent.length; i++) {
                        tabcontent[i].style.display = "none";
                    }
                    tablinks = document.getElementsByClassName("mc-tab-btn");
                    for (i = 0; i < tablinks.length; i++) {
                        tablinks[i].className = tablinks[i].className.replace(" active", "");
                    }
                    document.getElementById(tabName).style.display = "block";
                    evt.currentTarget.className += " active";

                    // Dispatch custom event for other scripts to hook into
                    document.dispatchEvent(new CustomEvent('mc_tab_change', {
                        detail: {
                            tabId: tabName
                        }
                    }));
                }
            </script>
            <?php
            return ob_get_clean();
    }
}
