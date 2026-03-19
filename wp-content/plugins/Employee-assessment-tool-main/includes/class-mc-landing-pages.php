<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles the rendering of Employer and Employee landing pages.
 */
class MC_Landing_Pages
{

    private static $load_scripts = false;

    public static function init()
    {
        add_shortcode('mc_employer_landing', [__CLASS__, 'render_employer_landing']);
        add_shortcode('mc_employee_landing', [__CLASS__, 'render_employee_landing']);
        add_action('template_redirect', [__CLASS__, 'handle_invite_logic']);
        add_action('wp_footer', [__CLASS__, 'print_landing_scripts']);
    }

    /**
     * Handles invite logic (redirects, cookies) before headers are sent.
     */
    public static function handle_invite_logic()
    {
        // Only run if invite_code is present
        if (!isset($_GET['invite_code'])) {
            return;
        }

        // Check if we are on the employee landing page
        // Since we don't know the ID, we check if the content contains the shortcode
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'mc_employee_landing')) {
            return;
        }

        $code = sanitize_text_field($_GET['invite_code']);
        $employer_id = 0;
        $valid_token = false;

        // Check format: ID-HASH (Unique Token) or just HASH (Company Share Code)
        if (strpos($code, '-') !== false) {
            $parts = explode('-', $code);
            if (count($parts) >= 2 && is_numeric($parts[0])) {
                $check_id = intval($parts[0]);
                // Verify this token exists in the employer's invites
                $invited = get_user_meta($check_id, 'mc_invited_employees', true);
                if (is_array($invited)) {
                    foreach ($invited as $inv) {
                        if (is_array($inv) && isset($inv['token']) && $inv['token'] === $code) {
                            $employer_id = $check_id;
                            $valid_token = true;
                            // Set unique token cookie
                            if (!headers_sent()) {
                                setcookie('mc_invite_token', $code, time() + 3600, '/');
                                // Set email cookie for robustness in registration
                                if (!empty($inv['email'])) {
                                    setcookie('mc_invite_email', $inv['email'], time() + 3600, '/');
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }

        // Fallback or Legacy: Treat as Company Share Code
        if (!$valid_token) {
            if (!headers_sent()) {
                setcookie('mc_invite_code', $code, time() + 3600, '/');
            }
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
            }
        }

        if ($employer_id > 0) {

            // If user is logged in, link them immediately, assign role, and redirect to dashboard
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();

                // Prevent employer from linking to themselves
                if ($user_id == $employer_id) {
                    // Do nothing, let them view the page
                } else {
                    // Check if already linked to avoid re-running logic unnecessarily
                    $current_linked_employer = get_user_meta($user_id, 'mc_linked_employer_id', true);

                    if ($current_linked_employer != $employer_id) {
                        update_user_meta($user_id, 'mc_linked_employer_id', $employer_id);

                        // Transfer Role & Responsibilities from Invite
                        $invited_employees = get_user_meta($employer_id, 'mc_invited_employees', true);
                        if (is_array($invited_employees)) {
                            $current_user = wp_get_current_user();
                            foreach ($invited_employees as $invite) {
                                $inv_email = is_array($invite) ? $invite['email'] : $invite;
                                if (strtolower($inv_email) === strtolower($current_user->user_email)) {
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

                        // Assign Employee Role ONLY if they don't have a higher role
                        $is_employer = get_user_meta($user_id, 'mc_company_name', true);

                        if (!$is_employer && class_exists('MC_Roles')) {
                            $u = new WP_User($user_id);
                            $u->add_role(MC_Roles::ROLE_EMPLOYEE);
                        }
                    }

                    // Redirect to dashboard
                    if (class_exists('MC_Funnel')) {
                        $dashboard_url = MC_Funnel::find_page_by_shortcode('quiz_dashboard');
                        if ($dashboard_url) {
                            wp_redirect($dashboard_url);
                            exit;
                        }
                    }
                }
            }
        }
    }

    /**
     * Renders the Employer Landing Page.
     */
    public static function render_employer_landing()
    {
        self::$load_scripts = true;
        ob_start();
        ?>
        <div class="mc-landing-page mc-employer-landing">
            <header class="mc-site-header">
                <div class="mc-logo">The Science of Teamwork</div>
                <div class="mc-nav">
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo wp_logout_url(get_permalink()); ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a>
                    <?php endif; ?>
                </div>
            </header>

            <header class="mc-landing-hero">
                <div class="mc-hero-content">
                    <h1>Unlock Your Team's True Potential</h1>
                    <p class="mc-landing-subtitle">Understand your employees' skill sets and core motivators. Promote people
                        into positions they will excel in and motivate them in ways that appeal to them the most.</p>
                    <?php
                    $onboarding_url = '#';
                    if (class_exists('MC_Funnel')) {
                        $onboarding_url = MC_Funnel::find_page_by_shortcode('mc_employer_onboarding') ?: '#';
                    }
                    ?>
                    <div class="mc-hero-cta">
                        <a href="<?php echo esc_url($onboarding_url); ?>" class="mc-button mc-button-primary">Get Started</a>
                        <a href="#assessments" class="mc-button mc-button-secondary">Learn More</a>
                    </div>
                </div>
            </header>

            <section class="mc-landing-section mc-stats-section">
                <div class="mc-container">
                    <div class="mc-stats-grid">
                        <div class="mc-stat-item">
                            <div class="mc-stat-number">4</div>
                            <div class="mc-stat-label">Psychometric Assessments</div>
                        </div>
                        <div class="mc-stat-item">
                            <div class="mc-stat-number">AI</div>
                            <div class="mc-stat-label">Generated Growth Plans</div>
                        </div>
                        <div class="mc-stat-item">
                            <div class="mc-stat-number">360°</div>
                            <div class="mc-stat-label">Team Feedback</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-value-prop">
                <div class="mc-container mc-container-narrow">
                    <h2>Why This Matters</h2>
                    <p class="mc-lead-text">Build a growth path for your employees that matters to them. This is a massive
                        retention and productivity play.</p>
                    <div class="mc-value-boxes">
                        <div class="mc-value-box">
                            <svg class="mc-value-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <polyline points="17 11 19 13 23 9"></polyline>
                            </svg>
                            <h3>Assign With Precision</h3>
                            <p>Match tasks to natural strengths</p>
                        </div>
                        <div class="mc-value-box">
                            <svg class="mc-value-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M2 12h20"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>
                            <h3>Identify Leaders</h3>
                            <p>Spot high-growth potential early</p>
                        </div>
                        <div class="mc-value-box">
                            <svg class="mc-value-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <h3>Boost Retention</h3>
                            <p>Create growth paths that matter</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="assessments" class="mc-landing-section mc-assessments-section">
                <div class="mc-container">
                    <div class="mc-section-header">
                        <h2>The Assessments</h2>
                        <p class="mc-section-subtitle">Four powerful tools to understand your team's unique profile</p>
                    </div>
                    <div class="mc-assessments-grid">
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-intelligence">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                            </div>
                            <h3>Multiple Intelligences (MI)</h3>
                            <p class="mc-card-description">Understand what dynamics they should be involved in within your
                                business.</p>
                            <ul class="mc-card-benefits">
                                <li>Identify optimal team dynamics</li>
                                <li>Match natural strengths to roles</li>
                                <li>Maximize contribution</li>
                            </ul>
                        </div>
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-cognitive">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                </svg>
                            </div>
                            <h3>Growth Strengths</h3>
                            <p class="mc-card-description">Measure how fast they can get feedback and grow.</p>
                            <ul class="mc-card-benefits">
                                <li>Assess feedback receptivity</li>
                                <li>Predict growth velocity</li>
                                <li>Identify coaching needs</li>
                            </ul>
                        </div>
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-bartle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 6v6l4 2"></path>
                                </svg>
                            </div>
                            <h3>Core Motivations</h3>
                            <p class="mc-card-description">Discover exactly how they are motivated.</p>
                            <ul class="mc-card-benefits">
                                <li>Uncover core drivers</li>
                                <li>Tailor incentives effectively</li>
                                <li>Boost engagement</li>
                            </ul>
                        </div>
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-johari">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="3" y1="12" x2="21" y2="12"></line>
                                    <line x1="12" y1="3" x2="12" y2="21"></line>
                                </svg>
                            </div>
                            <h3>Johari Window</h3>
                            <p class="mc-card-description">Corroborates their Multiple Intelligences profile.</p>
                            <ul class="mc-card-benefits">
                                <li>Validate self-perception</li>
                                <li>Reveal blind spots</li>
                                <li>Enhance self-awareness</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-action-section">
                <div class="mc-container">
                    <div class="mc-action-content">
                        <div class="mc-action-text">
                            <span class="mc-badge">AI-Powered</span>
                            <h2>From Insight to Action</h2>
                            <p class="mc-lead-text">Once the Psychometric Profile is complete, our AI generates personalized
                                Experiments—specific growth challenges and management strategies designed for each employee's
                                unique blend of motivators and cognitive style.</p>
                            <div class="mc-action-features">
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Tailored to individual profiles</span>
                                </div>
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Actionable from day one</span>
                                </div>
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Backed by psychometric data</span>
                                </div>
                            </div>
                        </div>
                        <div class="mc-action-visual">
                            <div class="mc-visual-placeholder">
                                <svg viewBox="0 0 200 200" fill="none">
                                    <circle cx="100" cy="100" r="80" stroke="rgba(255, 255, 255, 0.3)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="60" stroke="rgba(255, 255, 255, 0.4)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="40" stroke="rgba(255, 255, 255, 0.5)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="8" fill="rgba(255, 255, 255, 0.95)" />
                                    <line x1="100" y1="100" x2="140" y2="60" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="3" />
                                    <circle cx="140" cy="60" r="6" fill="rgba(255, 255, 255, 0.95)" />
                                    <line x1="100" y1="100" x2="60" y2="60" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="3" />
                                    <circle cx="60" cy="60" r="6" fill="rgba(255, 255, 255, 0.95)" />
                                    <line x1="100" y1="100" x2="140" y2="140" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="3" />
                                    <circle cx="140" cy="140" r="6" fill="rgba(255, 255, 255, 0.95)" />
                                    <line x1="100" y1="100" x2="60" y2="140" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="3" />
                                    <circle cx="60" cy="140" r="6" fill="rgba(255, 255, 255, 0.95)" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-final-cta">
                <div class="mc-container mc-container-narrow">
                    <div class="mc-cta-box">
                        <h2>Ready to Build Stronger Teams?</h2>
                        <p class="mc-cta-subtitle">Increase retention, reduce friction, and unlock hidden strengths in your
                            workforce.</p>
                        <div class="mc-cta-buttons">
                            <a href="<?php echo esc_url($onboarding_url); ?>"
                                class="mc-button mc-button-primary mc-button-large">Get Started</a>
                            <button onclick="openSampleReportModal()" class="mc-button mc-button-outline mc-button-large"
                                type="button">View Sample Report</button>
                        </div>
                        <p class="mc-cta-note">No credit card required • 5-minute setup</p>
                    </div>
                </div>
            </section>

            <footer class="mc-landing-footer">
                <div class="mc-container">
                    <p>© <?php echo date('Y'); ?> The Science of Teamwork. All rights reserved.</p>
                </div>
            </footer>

            <!-- Sample Report Modal (Replicating Real Report Structure) -->
            <div id="mc-sample-report-modal" class="mc-modal" style="display: none; z-index: 9999;">
                <div class="mc-modal-content"
                    style="max-width: 1100px; width: 95%; height: 90vh; overflow-y: auto; padding: 0; background: #fff;">
                    <div
                        style="position: sticky; top: 0; background: #fff; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; z-index: 100;">
                        <h3 style="margin:0; font-size: 1.2rem; color: #0f172a;">Sample Analysis Report</h3>
                        <span class="mc-close" onclick="closeSampleReportModal()"
                            style="font-size: 2rem; cursor: pointer; line-height: 1;">&times;</span>
                    </div>

                    <div class="mc-dashboard-wrapper" style="padding: 20px;">
                        <!-- Hero Section -->
                        <div class="mc-report-hero">
                            <div class="mc-report-header-row"
                                style="display: flex; justify-content: space-between; align-items: flex-start; padding-top: 0.5rem;">
                                <div class="mc-header-left">
                                    <h2
                                        style="margin: 0; font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.2; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                        <span
                                            style="font-size: 1.5rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">ACME
                                            CORP</span>
                                        <span style="font-size: 1.5rem; color: #cbd5e1; font-weight: 400;">—</span>
                                        <span style="color: #0f172a;">Alex Morgan</span>
                                    </h2>
                                    <div style="margin-top: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 1rem; color: #475569; font-weight: 600;">Role Analysis:
                                                Senior Product Manager</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mc-header-right" style="display: flex; align-items: flex-start; gap: 12px;">
                                    <button class="mc-btn mc-btn-secondary mc-btn-sm"
                                        style="display: flex; align-items: center; gap: 6px; margin-top: 0; cursor: not-allowed; opacity: 0.7;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        PDF
                                    </button>
                                </div>
                            </div>

                            <div
                                style="padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; margin-top: 0.5rem;">
                                <p style="margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.5;">
                                    This report analyzes the employee's fit for the specific role based on their assessment
                                    results. It synthesizes data from Motivational, Personality, and Cognitive assessments to
                                    predict performance, cultural fit, and leadership potential.
                                </p>
                            </div>

                            <!-- Company Culture Fit -->
                            <div class="mc-hero-scores" style="width: 100%; margin-bottom: 2rem;">
                                <div class="mc-score-card mc-fit-card"
                                    style="display: flex; flex-direction: column; gap: 1rem; padding: 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <h4
                                                style="margin:0; font-size:1.1rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; font-weight: 600;">
                                                Company Culture Fit</h4>
                                        </div>
                                        <div style="display:flex; align-items:baseline; gap:8px;">
                                            <span
                                                style="font-size:3em; font-weight:800; color:#166534; line-height:1;">88</span>
                                            <span style="font-size:1.2em; color:#94a3b8; font-weight:500;">/ 100</span>
                                        </div>
                                    </div>
                                    <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                                        <p style="margin: 0; font-size: 1.05rem; line-height: 1.6; color: #334155;">Strong
                                            alignment with role requirements and team culture. Alex demonstrates the strategic
                                            foresight and operational discipline needed for this position.</p>
                                    </div>
                                    <div
                                        style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-left: 4px solid #2563eb; border-radius: 0 4px 4px 0;">
                                        <p style="margin: 0; color: #334155; line-height: 1.6;"><strong>Context
                                                Summary:</strong> The candidate demonstrates exceptional capability in
                                            <strong>Systems Thinking</strong> and <strong>Operational Execution</strong>. They
                                            are likely to thrive in a collaborative environment that values autonomy and clear
                                            communication.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Leadership Strip -->
                            <div class="mc-hero-leadership-strip">
                                <div class="mc-leadership-header">
                                    <h4>Leadership Potential</h4>
                                    <div class="mc-leadership-spectrum">
                                        <div class="mc-spectrum-track">
                                            <div class="mc-spectrum-segment">Individual</div>
                                            <div class="mc-spectrum-segment">Emerging</div>
                                            <div class="mc-spectrum-segment">Developing</div>
                                            <div class="mc-spectrum-segment active"
                                                style="background: #2563eb; color: white; border-color: #2563eb;">Strong</div>
                                            <div class="mc-spectrum-segment">Rockstar Fit</div>
                                        </div>
                                    </div>
                                </div>
                                <p>Alex shows strong leadership indicators, particularly in guiding teams through complex
                                    problem-solving and maintaining operational stability.</p>
                            </div>

                            <div class="mc-hero-main-stack" style="display: flex; flex-direction: column; gap: 2rem;">
                                <!-- Insights Row (3 Columns) -->
                                <div class="mc-hero-insights"
                                    style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                                    <div class="mc-insight-box"
                                        style="background:#f8fafc; border-radius:12px; padding:1.5rem; border: 1px solid #e2e8f0;">
                                        <h4
                                            style="margin-top:0; color:#475569; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; font-weight: 700;">
                                            Top Strengths</h4>
                                        <ul class="mc-pill-list">
                                            <li>Systems Thinking</li>
                                            <li>Operational Execution</li>
                                            <li>Strategic Planning</li>
                                        </ul>
                                    </div>
                                    <div class="mc-insight-box"
                                        style="background:#fff1f2; border-radius:12px; padding:1.5rem; border: 1px solid #ffe4e6;">
                                        <h4
                                            style="margin-top:0; color:#9f1239; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; font-weight: 700;">
                                            Potential Blindspots</h4>
                                        <ul class="mc-pill-list">
                                            <li>Risk Aversion</li>
                                            <li>Delegation Hesitancy</li>
                                        </ul>
                                    </div>
                                    <div class="mc-insight-box"
                                        style="background:#f0f9ff; border-radius:12px; padding:1.5rem; border: 1px solid #e0f2fe;">
                                        <h4
                                            style="margin-top:0; color:#0369a1; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; font-weight: 700;">
                                            Key Motivators</h4>
                                        <ul class="mc-pill-list">
                                            <li>Autonomy</li>
                                            <li>Impact</li>
                                            <li>Mastery</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mc-report-body">
                            <!-- Left Column: Guide & Coaching -->
                            <div class="mc-report-main">
                                <!-- Communication Playbook -->
                                <div class="mc-section-card">
                                    <div class="mc-section-header">
                                        <h3>Communication Playbook</h3>
                                        <span class="mc-section-icon">💬</span>
                                    </div>
                                    <div class="mc-playbook-grid">
                                        <div class="mc-playbook-col mc-do">
                                            <h4>Do This</h4>
                                            <ul>
                                                <li>Provide clear, structured documentation.</li>
                                                <li>Focus on practical outcomes and execution details.</li>
                                                <li>Allow time for processing complex information.</li>
                                            </ul>
                                        </div>
                                        <div class="mc-playbook-col mc-avoid">
                                            <h4>Avoid This</h4>
                                            <ul>
                                                <li>Vague or abstract conceptual discussions without action items.</li>
                                                <li>Micromanaging their workflow once parameters are set.</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="mc-playbook-footer">
                                        <strong>Preferred Format:</strong> Written, asynchronous communication with scheduled
                                        syncs.
                                    </div>
                                </div>

                                <!-- Motivation & Work Style -->
                                <div class="mc-section-card">
                                    <div class="mc-section-header">
                                        <h3>Motivation & Work Style</h3>
                                        <span class="mc-section-icon">⚡</span>
                                    </div>
                                    <div class="mc-grid-2">
                                        <div>
                                            <h4>Energizers</h4>
                                            <ul class="mc-check-list">
                                                <li>Solving complex systemic problems</li>
                                                <li>Optimizing workflows</li>
                                                <li>Clear ownership of projects</li>
                                            </ul>
                                        </div>
                                        <div>
                                            <h4>Drainers</h4>
                                            <ul class="mc-cross-list">
                                                <li>Ambiguous requirements</li>
                                                <li>Repetitive manual tasks</li>
                                                <li>Unnecessary meetings</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="mc-divider"></div>
                                    <div class="mc-work-style-box">
                                        <p><strong>Work Style:</strong> Methodical and analytical, preferring to understand the
                                            whole system before diving into details.</p>
                                        <div class="mc-grid-2">
                                            <div>
                                                <small>Best When:</small>
                                                <ul class="mc-sm-list">
                                                    <li>Given autonomy to structure their work</li>
                                                    <li>Working on long-term strategic goals</li>
                                                </ul>
                                            </div>
                                            <div>
                                                <small>Struggles When:</small>
                                                <ul class="mc-sm-list">
                                                    <li>Forced to switch contexts rapidly</li>
                                                    <li>Dealing with high emotional volatility</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Strain Index Analysis -->
                                <div class="mc-section-card" id="mc-strain-section" style="display:block;">
                                    <div class="mc-section-header"
                                        style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <h3>Strain Index Analysis</h3>
                                            <span class="mc-section-icon">🧠</span>
                                        </div>
                                        <button class="mc-btn mc-btn-secondary mc-btn-sm" onclick="openSampleStrainDetails()"
                                            style="font-size:0.85rem; padding:4px 12px; font-weight:500;">View More</button>
                                    </div>
                                    <div class="mc-strain-grid"
                                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <!-- Overall Score -->
                                        <div class="mc-strain-overall"
                                            style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px;">
                                            <h4 style="margin: 0 0 10px 0; color: #64748b;">Overall Strain</h4>
                                            <div class="mc-strain-gauge"
                                                style="position: relative; width: 120px; height: 60px; margin: 0 auto; overflow: hidden;">
                                                <div class="mc-gauge-bg"
                                                    style="width: 100%; height: 100%; background: #e2e8f0; border-top-left-radius: 60px; border-top-right-radius: 60px;">
                                                </div>
                                                <div class="mc-gauge-fill"
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #22c55e; border-top-left-radius: 60px; border-top-right-radius: 60px; transform-origin: bottom center; transform: rotate(-144deg); transition: transform 1s;">
                                                </div>
                                            </div>
                                            <div style="font-size: 2em; font-weight: 800; color: #0f172a; margin-top: -10px;">
                                                20.0</div>
                                        </div>
                                        <!-- Sub-Indices -->
                                        <div class="mc-strain-breakdown">
                                            <div class="mc-strain-row" style="margin-bottom: 15px;">
                                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                    <span style="font-weight: 600; color: #475569;">Rumination</span>
                                                    <span style="font-weight: 700;">15%</span>
                                                </div>
                                                <div
                                                    style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                    <div style="height: 100%; background: #22c55e; width: 15%;"></div>
                                                </div>
                                            </div>
                                            <div class="mc-strain-row" style="margin-bottom: 15px;">
                                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                    <span style="font-weight: 600; color: #475569;">Avoidance</span>
                                                    <span style="font-weight: 700;">10%</span>
                                                </div>
                                                <div
                                                    style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                    <div style="height: 100%; background: #22c55e; width: 10%;"></div>
                                                </div>
                                            </div>
                                            <div class="mc-strain-row">
                                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                    <span style="font-weight: 600; color: #475569;">Emotional Flood</span>
                                                    <span style="font-weight: 700;">35%</span>
                                                </div>
                                                <div
                                                    style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                    <div style="height: 100%; background: #f59e0b; width: 35%;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-top: 15px; font-size: 0.9em; color: #64748b; font-style: italic;">
                                        * Strain Index metrics are internal-only and not visible to the employee.
                                    </div>
                                </div>

                                <!-- Coaching Recommendations -->
                                <div class="mc-section-card">
                                    <div class="mc-section-header">
                                        <h3>Coaching Recommendations</h3>
                                        <span class="mc-section-icon">🎯</span>
                                    </div>
                                    <div class="mc-cards-container">
                                        <div class="mc-card mc-coaching-card">
                                            <h4>Enhance Strategic Autonomy</h4>
                                            <p class="mc-card-rationale">To move from execution to leadership, Alex needs to
                                                build confidence in making decisions without immediate validation.</p>
                                            <div class="mc-card-example">
                                                <strong>Try:</strong> Assign a small, low-risk project where they have full
                                                authority over the budget and timeline, with only one final review.
                                            </div>
                                        </div>
                                        <div class="mc-card mc-coaching-card">
                                            <h4>Encourage Rapid Prototyping</h4>
                                            <p class="mc-card-rationale">Alex tends to over-analyze before acting. Encouraging
                                                imperfect first drafts will speed up iteration cycles.</p>
                                            <div class="mc-card-example">
                                                <strong>Try:</strong> Set a "rough draft" deadline that is 50% of the usual
                                                timeline, explicitly asking for an incomplete but directional output.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Stretch Assignments -->
                                <div class="mc-section-card">
                                    <div class="mc-section-header">
                                        <h3>Stretch Assignments</h3>
                                        <span class="mc-section-icon">📈</span>
                                    </div>
                                    <div class="mc-cards-container"
                                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <!-- Card 1 -->
                                        <div class="mc-card" style="display: flex; flex-direction: column; height: 100%;">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 1rem; color: #1e293b;">Lead a High-Stakes
                                                    Project</h4>
                                                <span class="mc-badge"
                                                    style="background: #fef3c7; color: #b45309; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 600;">MODERATE
                                                    RISK</span>
                                            </div>
                                            <p style="margin: 0 0 12px 0; font-size: 0.9rem; color: #475569; flex-grow: 1;">
                                                <strong>Action:</strong> Take charge of a challenging project with tight
                                                deadlines to enhance decision-making skills.
                                            </p>
                                            <p
                                                style="margin: 0; font-size: 0.85rem; color: #64748b; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                                Builds: Improved responsiveness and stress management under pressure.
                                            </p>
                                        </div>
                                        <!-- Card 2 -->
                                        <div class="mc-card" style="display: flex; flex-direction: column; height: 100%;">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 1rem; color: #1e293b;">Facilitate Team
                                                    Workshops</h4>
                                                <span class="mc-badge"
                                                    style="background: #dcfce7; color: #15803d; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 600;">LOW
                                                    RISK</span>
                                            </div>
                                            <p style="margin: 0 0 12px 0; font-size: 0.9rem; color: #475569; flex-grow: 1;">
                                                <strong>Action:</strong> Lead workshops to improve team collaboration and
                                                communication.
                                            </p>
                                            <p
                                                style="margin: 0; font-size: 0.85rem; color: #64748b; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                                                Builds: Enhanced interpersonal skills and confidence in leadership.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Team & Leadership -->
                                <div class="mc-section-card">
                                    <div class="mc-section-header">
                                        <h3>Team & Leadership</h3>
                                        <span class="mc-section-icon">👥</span>
                                    </div>
                                    <div class="mc-grid-2">
                                        <!-- Collaboration -->
                                        <div>
                                            <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 12px;">Collaboration</h4>
                                            <div style="margin-bottom: 16px;">
                                                <strong
                                                    style="display: block; color: #475569; margin-bottom: 4px; font-size: 0.9rem;">Thrives
                                                    with:</strong>
                                                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Team members who are
                                                    proactive and clear communicators.</p>
                                            </div>
                                            <div>
                                                <strong
                                                    style="display: block; color: #475569; margin-bottom: 4px; font-size: 0.9rem;">Friction
                                                    with:</strong>
                                                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Individuals who are
                                                    overly critical or disorganized.</p>
                                            </div>
                                        </div>
                                        <!-- Ideal Conditions -->
                                        <div>
                                            <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 12px;">Ideal Conditions
                                            </h4>
                                            <p style="margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.6;">
                                                A structured environment with clear expectations and the opportunity for
                                                occasional breaks to manage workload effectively.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Manager Fast Guide -->
                            <div class="mc-report-sidebar">
                                <div class="mc-fast-guide">
                                    <div class="mc-guide-header">
                                        <h3>Manager Fast Guide</h3>
                                        <small>Print / Save</small>
                                    </div>
                                    <div class="mc-guide-body">
                                        <div class="mc-guide-item">
                                            <strong>Top Strengths</strong>
                                            <ul>
                                                <li>Systems Thinking</li>
                                                <li>Operational Execution</li>
                                                <li>Strategic Planning</li>
                                            </ul>
                                        </div>
                                        <div class="mc-guide-item">
                                            <strong>Key Motivators</strong>
                                            <ul>
                                                <li>Autonomy</li>
                                                <li>Impact</li>
                                                <li>Mastery</li>
                                            </ul>
                                        </div>
                                        <div class="mc-guide-item">
                                            <strong>Communication</strong>
                                            <ul>
                                                <li>Be direct and concise</li>
                                                <li>Focus on outcomes</li>
                                            </ul>
                                        </div>
                                        <div class="mc-guide-item">
                                            <strong>Coaching Moves</strong>
                                            <ul>
                                                <li>Delegate authority, not just tasks</li>
                                                <li>Encourage 80/20 thinking</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="mc-meta-box">
                                    <h4>Conflict & Stress</h4>
                                    <p><strong>Handling:</strong> Withdraws to analyze before engaging.</p>
                                    <p><strong>Signs:</strong> Becomes overly quiet or focuses excessively on minor details.</p>
                                    <p><strong>Support:</strong> Give space to process, then ask for their proposed solution.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sample Strain Details Modal -->
            <div id="mc-sample-strain-details-modal" class="mc-modal" style="display: none; z-index: 2147483647;">
                <div class="mc-modal-content"
                    style="max-width: 800px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 0; background: #fff; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                    <div
                        style="position: sticky; top: 0; background: #fff; padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; z-index: 100;">
                        <div>
                            <h3 id="mc-sample-strain-details-title"
                                style="margin:0; font-size: 1.25rem; color: #0f172a; font-weight: 700;">Strain Index Deep Dive
                            </h3>
                            <p style="margin:4px 0 0 0; font-size:0.9rem; color:#64748b;">Detailed breakdown of friction points
                            </p>
                        </div>
                        <span class="mc-close" onclick="closeSampleStrainDetails()"
                            style="font-size: 2rem; cursor: pointer; line-height: 1; color: #94a3b8;">&times;</span>
                    </div>
                    <div id="mc-sample-strain-details-body" style="padding: 24px;">
                        <!-- Content injected via JS -->
                    </div>
                    <div style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
                        <button class="mc-button mc-button-secondary" onclick="closeSampleStrainDetails()">Close</button>
                    </div>
                </div>
            </div>

            <style>
                /* Modal Overlay Styles */
                .mc-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: none;
                    /* Hidden by default */
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    backdrop-filter: blur(4px);
                }

                /* Dashboard Styles for Sample Report */
                #mc-sample-report-modal .mc-dashboard-wrapper {
                    font-family: 'Inter', system-ui, sans-serif;
                    color: #334155;
                }

                #mc-sample-report-modal h2,
                #mc-sample-report-modal h3,
                #mc-sample-report-modal h4 {
                    color: #0f172a;
                }

                .mc-report-hero {
                    margin-bottom: 30px;
                }

                .mc-hero-leadership-strip {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 1.5rem;
                    margin-bottom: 2rem;
                }

                .mc-leadership-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                }

                .mc-leadership-header h4 {
                    margin: 0;
                    font-size: 1.1rem;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    font-weight: 600;
                }

                .mc-leadership-spectrum {
                    flex: 1;
                    max-width: 500px;
                    margin-left: 2rem;
                    position: relative;
                }

                .mc-spectrum-track {
                    display: flex;
                    background: #e2e8f0;
                    border-radius: 20px;
                    overflow: hidden;
                    height: 32px;
                }

                .mc-spectrum-segment {
                    flex: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: #64748b;
                    border-right: 1px solid #cbd5e1;
                    transition: all 0.3s;
                }

                .mc-spectrum-segment:last-child {
                    border-right: none;
                }

                .mc-spectrum-segment.active {
                    background: #2563eb;
                    color: white;
                }

                .mc-report-body {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 30px;
                }

                .mc-section-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 25px;
                    margin-bottom: 30px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                }

                .mc-section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #f1f5f9;
                }

                .mc-section-header h3 {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 700;
                }

                .mc-section-icon {
                    font-size: 1.5rem;
                }

                .mc-playbook-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 20px;
                }

                .mc-playbook-col h4 {
                    margin-top: 0;
                    margin-bottom: 15px;
                    font-size: 1rem;
                }

                .mc-do h4 {
                    color: #166534;
                }

                .mc-avoid h4 {
                    color: #991b1b;
                }

                .mc-playbook-col ul {
                    padding-left: 20px;
                    margin: 0;
                }

                .mc-playbook-col li {
                    margin-bottom: 8px;
                    font-size: 0.95rem;
                    line-height: 1.5;
                }

                .mc-grid-2 {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                }

                .mc-check-list,
                .mc-cross-list,
                .mc-sm-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .mc-check-list li,
                .mc-cross-list li {
                    padding-left: 24px;
                    position: relative;
                    margin-bottom: 8px;
                    font-size: 0.95rem;
                }

                .mc-check-list li::before {
                    content: "✓";
                    position: absolute;
                    left: 0;
                    color: #22c55e;
                    font-weight: bold;
                }

                .mc-cross-list li::before {
                    content: "✕";
                    position: absolute;
                    left: 0;
                    color: #ef4444;
                    font-weight: bold;
                }

                .mc-divider {
                    height: 1px;
                    background: #f1f5f9;
                    margin: 20px 0;
                }

                .mc-work-style-box {
                    background: #f8fafc;
                    padding: 15px;
                    border-radius: 8px;
                }

                .mc-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 15px;
                }

                .mc-coaching-card h4 {
                    margin: 0 0 8px 0;
                    color: #2563eb;
                }

                .mc-card-rationale {
                    margin: 0 0 10px 0;
                    font-size: 0.9rem;
                    color: #64748b;
                }

                .mc-card-example {
                    background: #f0f9ff;
                    padding: 10px;
                    border-radius: 4px;
                    font-size: 0.9rem;
                    color: #0369a1;
                }

                .mc-report-sidebar {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }

                .mc-fast-guide {
                    background: #1e293b;
                    color: #fff;
                    border-radius: 12px;
                    padding: 20px;
                }

                .mc-guide-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #334155;
                }

                .mc-guide-header h3 {
                    color: #fff !important;
                    margin: 0;
                    font-size: 1.1rem;
                }

                .mc-guide-item {
                    margin-bottom: 20px;
                }

                .mc-guide-item strong {
                    display: block;
                    color: #94a3b8;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                }

                .mc-guide-item ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .mc-guide-item li {
                    margin-bottom: 5px;
                    font-size: 0.9rem;
                    padding-left: 15px;
                    position: relative;
                }

                .mc-guide-item li::before {
                    content: "•";
                    position: absolute;
                    left: 0;
                    color: #38bdf8;
                }

                .mc-meta-box {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 20px;
                }

                .mc-meta-box h4 {
                    margin: 0 0 15px 0;
                    color: #64748b;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                }

                .mc-meta-box p {
                    margin: 0 0 10px 0;
                    font-size: 0.9rem;
                    line-height: 1.5;
                }

                .mc-pill-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .mc-pill-list li {
                    background: #fff;
                    padding: 4px 10px;
                    border-radius: 15px;
                    font-size: 0.85rem;
                    font-weight: 500;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                    border: 1px solid rgba(0, 0, 0, 0.05);
                }

                @media (max-width: 768px) {
                    .mc-report-body {
                        grid-template-columns: 1fr;
                    }

                    .mc-hero-insights {
                        grid-template-columns: 1fr !important;
                    }

                    .mc-playbook-grid,
                    .mc-grid-2,
                    .mc-strain-grid {
                        grid-template-columns: 1fr !important;
                    }

                    .mc-leadership-spectrum {
                        margin-left: 0;
                        margin-top: 1rem;
                        max-width: 100%;
                    }
                }
            </style>

            <script>
                function openSam                               pleReportModal() {
                    document.getElementById('mc-sample-report-modal').style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }

                function closeSampleReportModal() {
                    document.getElementById('mc-sample-report-modal').style.display = 'none';
                    document.body.style.overflow = 'auto';
                }

                // Close modal when clicking outside
                document.addEventListener('click', function (event) {
                    const modal = document.getElementById('mc-sample-report-modal');
                    if (event.target === modal) {
                        closeSampleReportModal();
                    }
                });

                // Close modal on Escape key
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeSampleReportModal();
                    }
                });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the Employee Landing Page.
     */
    public static function render_employee_landing()
    {
        // Try to find linked employer to get logo
        $employer_id = 0;
        $invited_email = '';

        // 1. Check Invite Code
        if (isset($_GET['invite_code'])) {
            $code = sanitize_text_field($_GET['invite_code']);
            $found = false;

            // Try Unique Token Format: ID-HASH
            if (strpos($code, '-') !== false) {
                $parts = explode('-', $code);
                if (count($parts) >= 2 && is_numeric($parts[0])) {
                    $check_id = intval($parts[0]);
                    $invited = get_user_meta($check_id, 'mc_invited_employees', true);
                    if (is_array($invited)) {
                        foreach ($invited as $inv) {
                            if (is_array($inv) && isset($inv['token']) && $inv['token'] === $code) {
                                $employer_id = $check_id;
                                $invited_email = $inv['email'] ?? '';
                                $found = true;
                                break;
                            }
                        }
                    }
                }
            }

            // Fallback: Legacy Share Code
            if (!$found) {
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
                }
            }
        }

        // 2. Check Logged In User Link
        if (!$employer_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $employer_id = get_user_meta($user_id, 'mc_linked_employer_id', true);
        }

        $logo_url = '';
        $company_name = '';
        if ($employer_id) {
            $logo_id = get_user_meta($employer_id, 'mc_company_logo_id', true);
            if ($logo_id) {
                $logo_url = wp_get_attachment_url($logo_id);
            }
            $company_name = get_user_meta($employer_id, 'mc_company_name', true);
        }

        $has_invite = isset($_GET['invite_code']) && $employer_id;

        ob_start();
        ?>
        <div class="mc-landing-page mc-employee-landing">
            <?php if (!$has_invite): ?>
            <header class="mc-site-header">
                <div class="mc-logo">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Company Logo" style="max-height: 40px;">
                    <?php else: ?>
                        The Science of Teamwork
                    <?php endif; ?>
                </div>
                <div class="mc-nav">
                    <?php if (is_user_logged_in()): ?>
                        <?php
                        if (current_user_can('manage_options') || current_user_can('mc_employer')) {
                            $employer_dashboard_url = '#';
                            if (class_exists('MC_Funnel')) {
                                $employer_dashboard_url = MC_Funnel::find_page_by_shortcode('mc_employer_dashboard');
                            }
                            if ($employer_dashboard_url) {
                                echo '<a href="' . esc_url($employer_dashboard_url) . '" style="margin-right: 15px; font-weight: 500;">Switch to Employer View</a>';
                            }
                        }
                        ?>
                        <a href="<?php echo wp_logout_url(get_permalink()); ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a>
                    <?php endif; ?>
                </div>
            </header>
            <?php endif; ?>

            <section class="mc-landing-hero mc-employee-hero">
                <div class="mc-container">
                    <div class="mc-hero-content">
                        <?php if ($has_invite): ?>
                            <?php if ($logo_url): ?>
                                <div style="margin-bottom: 2rem;">
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>" style="max-height: 160px; max-width: 400px; object-fit: contain; background: rgba(255,255,255,0.95); padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">
                                </div>
                            <?php endif; ?>
                            <span class="mc-badge mc-badge-gradient">You're Invited</span>
                            <h1><?php echo $company_name ? esc_html($company_name) . ' Wants to' : 'Your employer wants to'; ?> Help
                                You Grow</h1>
                            <p class="mc-hero-subtitle">You've been invited to discover your unique strengths and accelerate your
                                professional development with science-backed assessments.</p>
                        <?php else: ?>
                            <span class="mc-badge mc-badge-gradient">Personal Growth Platform</span>
                            <h1>Discover What Makes You Exceptional</h1>
                            <p class="mc-hero-subtitle">Unlock your unique strengths, understand your motivation, and accelerate
                                your career growth with science-backed assessments.</p>
                        <?php endif; ?>
                        <?php
                        $dashboard_url = '#';
                        if (class_exists('MC_Funnel')) {
                            $dashboard_url = MC_Funnel::find_page_by_shortcode('quiz_dashboard') ?: '#';
                        }

                        $button_url = $dashboard_url;
                        $button_text = $has_invite ? 'Accept Invitation & Start' : 'Begin Your Journey';

                        // Logic for button URL
                        if ($has_invite && !is_user_logged_in()) {
                            $button_text = 'Accept Invitation & Start';
                            $button_url = wp_registration_url();
                            // Pass invite params
                            $button_url = add_query_arg('invite_code', sanitize_text_field($_GET['invite_code']), $button_url);
                            if (!empty($invited_email)) {
                                $button_url = add_query_arg('user_email', $invited_email, $button_url);
                            }
                        } elseif (isset($_GET['invite_code'])) {
                            // Logged in user or general case
                            $button_url = add_query_arg('invite_code', sanitize_text_field($_GET['invite_code']), $button_url);
                        }

                        // If not logged in and no invite, generic
                        if (!$has_invite && !is_user_logged_in()) {
                            $button_text = 'Create Free Account';
                            $button_url = wp_registration_url();
                        }
                        ?>
                        <div class="mc-hero-actions">
                            <a href="<?php echo esc_url($button_url); ?>"
                                class="mc-button mc-button-primary mc-button-large"><?php echo esc_html($button_text); ?></a>
                        </div>
                        <div class="mc-hero-stats">
                            <div class="mc-stat-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                                <span>15 min to complete</span>
                            </div>
                            <div class="mc-stat-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Science-backed results</span>
                            </div>
                            <div class="mc-stat-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                </svg>
                                <span>100% confidential</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-benefits-section">
                <div class="mc-container">
                    <div class="mc-section-header">
                        <?php if ($has_invite): ?>
                            <h2>What You'll Discover</h2>
                            <p class="mc-section-subtitle">A complete picture of your strengths, motivations, and growth potential
                            </p>
                        <?php else: ?>
                            <h2>Your Personal Growth Roadmap</h2>
                            <p class="mc-section-subtitle">Understand yourself better to work smarter and grow faster</p>
                        <?php endif; ?>
                    </div>
                    <div class="mc-benefits-grid">
                        <div class="mc-benefit-card">
                            <div class="mc-benefit-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <h3>Discover Your Strengths</h3>
                            <p>Identify your natural talents and learn how to leverage them in your daily work. Stop fighting
                                your weaknesses and start amplifying what you do best.</p>
                        </div>
                        <div class="mc-benefit-card">
                            <div class="mc-benefit-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <h3>Understand Your Motivation</h3>
                            <p>Learn what truly drives you—whether it's achievement, connection, or exploration—and design a
                                work life that energizes rather than drains you.</p>
                        </div>
                        <div class="mc-benefit-card">
                            <div class="mc-benefit-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                            </div>
                            <h3>Accelerate Your Growth</h3>
                            <p>Get personalized development recommendations based on your cognitive style. Focus your energy on
                                growth areas that will have the biggest impact on your career.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="assessments" class="mc-landing-section mc-assessments-section mc-employee-assessments">
                <div class="mc-container">
                    <div class="mc-section-header">
                        <h2>Three Assessments, One Complete Picture</h2>
                        <p class="mc-section-subtitle">Science-backed tools to help you understand your unique professional
                            profile</p>
                    </div>
                    <div class="mc-assessments-grid">
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-intelligence">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                            </div>
                            <h3>Multiple Intelligences</h3>
                            <p class="mc-card-description">Discover your unique cognitive strengths</p>
                            <ul class="mc-card-benefits">
                                <li>Find out if you're Word Smart, Logic Smart, People Smart, or more</li>
                                <li>Choose projects where you'll naturally excel</li>
                                <li>Communicate your strengths to managers and colleagues</li>
                            </ul>
                        </div>
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-cognitive">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                </svg>
                            </div>
                            <h3>Growth Strengths</h3>
                            <p class="mc-card-description">Measure your capacity for growth and complexity</p>
                            <ul class="mc-card-benefits">
                                <li>Understand how you handle challenges and uncertainty</li>
                                <li>Identify your leadership potential and readiness</li>
                                <li>Learn how receptive you are to feedback</li>
                            </ul>
                        </div>
                        <div class="mc-assessment-card">
                            <div class="mc-card-icon mc-icon-bartle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 6v6l4 2"></path>
                                </svg>
                            </div>
                            <h3>Core Motivations</h3>
                            <p class="mc-card-description">Reveal what makes work fulfilling for you</p>
                            <ul class="mc-card-benefits">
                                <li>Discover if you're an Achiever, Explorer, Socializer, or Killer</li>
                                <li>Design a workday that aligns with your motivation</li>
                                <li>Find roles and projects that energize you</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-action-section mc-employee-action">
                <div class="mc-container">
                    <div class="mc-action-content">
                        <div class="mc-action-text">
                            <span class="mc-badge">AI-Powered</span>
                            <h2>From Insight to Action</h2>
                            <p class="mc-lead-text">After completing your assessments, our AI coach generates personalized
                                "Minimum Viable Experiments"—small, practical growth challenges tailored to your unique
                                strengths, motivations, and development areas.</p>
                            <div class="mc-action-features">
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Customized to your profile</span>
                                </div>
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Actionable in your daily work</span>
                                </div>
                                <div class="mc-feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Science-backed recommendations</span>
                                </div>
                            </div>
                        </div>
                        <div class="mc-action-visual">
                            <div class="mc-visual-placeholder">
                                <svg viewBox="0 0 200 200" fill="none">
                                    <circle cx="100" cy="100" r="80" stroke="rgba(255, 255, 255, 0.15)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="60" stroke="rgba(255, 255, 255, 0.15)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="40" stroke="rgba(255, 255, 255, 0.15)" stroke-width="2" />
                                    <circle cx="100" cy="100" r="8" fill="rgba(255, 255, 255, 0.9)" />
                                    <line x1="100" y1="100" x2="140" y2="60" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="2" />
                                    <circle cx="140" cy="60" r="6" fill="rgba(255, 255, 255, 0.9)" />
                                    <line x1="100" y1="100" x2="60" y2="60" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="2" />
                                    <circle cx="60" cy="60" r="6" fill="rgba(255, 255, 255, 0.9)" />
                                    <line x1="100" y1="100" x2="140" y2="140" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="2" />
                                    <circle cx="140" cy="140" r="6" fill="rgba(255, 255, 255, 0.9)" />
                                    <line x1="100" y1="100" x2="60" y2="140" stroke="rgba(255, 255, 255, 0.9)"
                                        stroke-width="2" />
                                    <circle cx="60" cy="140" r="6" fill="rgba(255, 255, 255, 0.9)" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-landing-section mc-final-cta mc-employee-cta">
                <div class="mc-container mc-container-narrow">
                    <div class="mc-cta-box">
                        <?php if ($has_invite): ?>
                            <h2>Ready to Get Started?</h2>
                            <p class="mc-cta-subtitle">
                                <?php echo $company_name ? esc_html($company_name) . ' is' : 'Your employer is'; ?> investing in
                                your growth. Take the first step today.
                            </p>
                            <div class="mc-cta-buttons">
                                <a href="<?php echo esc_url($button_url); ?>"
                                    class="mc-button mc-button-primary mc-button-large"><?php echo esc_html($button_text); ?></a>
                            </div>
                            <p class="mc-cta-note">Completely confidential • 15 minutes • Results shared with you first</p>
                        <?php else: ?>
                            <h2>Ready to Unlock Your Potential?</h2>
                            <p class="mc-cta-subtitle">Join thousands discovering their unique strengths and accelerating their
                                career growth.</p>
                            <div class="mc-cta-buttons">
                                <a href="<?php echo esc_url($button_url); ?>"
                                    class="mc-button mc-button-primary mc-button-large"><?php echo esc_html($button_text); ?></a>
                            </div>
                            <p class="mc-cta-note">Free to use • 15 minutes • Completely confidential</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <footer class="mc-landing-footer">
                <div class="mc-container">
                    <p>© <?php echo date('Y'); ?> The Science of Teamwork. All rights reserved.</p>
                </div>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function print_landing_scripts()
    {
        if (!self::$load_scripts) {
            return;
        }
        ?>
        <script>
            console.log('MC Landing Page Script Loaded (Footer)');

            window.openSampleReportModal = function () {
                console.log('Attempting to open sample report modal...');
                var modal = document.getElementById('mc-sample-report-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    console.log('Modal opened');
                } else {
                    console.error('Sample report modal element not found!');
                }
            }

            window.closeSampleReportModal = function () {
                var modal = document.getElementById('mc-sample-report-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }

            // Close modal when clicking outside
            document.addEventListener('click', function (event) {
                const modal = document.getElementById('mc-sample-report-modal');
                if (modal && event.target === modal) {
                    window.closeSampleReportModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    window.closeSampleReportModal();
                    window.closeSampleStrainDetails();
                }
            });

            // Sample Data mimicking real assessment results
            const sampleStrainData = {
                score: 20.0,
                risk_level: 'Low Risk',
                risk_color: '#22c55e',
                rationale: 'Healthy engagement. Few to no strain markers.',
                breakdown: {
                    'Rumination': { score: 1.5, percent: 15, color: '#22c55e' },
                    'Avoidance': { score: 1.0, percent: 10, color: '#22c55e' },
                    'Emotional Flood': { score: 3.5, percent: 35, color: '#f59e0b' }
                },
                detailed_answers: {
                    'Rumination': {
                        'MI': {
                            'When I face a difficult decision, I need extra time to figure out the "right" move.': '2',
                            'I replay conversations or choices in my head to see what I could have done differently.': '1'
                        }
                    },
                    'Avoidance': {
                        'CDT': {
                            'I tend to put off tasks that feel overwhelming or unclear.': '1',
                            'I prefer to stick to what I know rather than risk looking incompetent.': '1'
                        }
                    },
                    'Emotional Flood': {
                        'Bartle': {
                            'I sometimes feel mentally "stuck" when two of my thoughts or values clash.': '4',
                            'High-pressure situations make it hard for me to think clearly.': '3'
                        }
                    }
                }
            };

            window.openSampleStrainDetails = function () {
                const modal = document.getElementById('mc-sample-strain-details-modal');
                if (!modal) return;

                modal.style.display = 'flex';
                // modal.style.zIndex = '2147483647'; // Ensure on top

                const body = document.getElementById('mc-sample-strain-details-body');

                let html = '';

                // Explainer
                html += `<div style="background:#f1f5f9; padding:16px; border-radius:8px; margin-bottom:24px; border:1px solid #e2e8f0;">
                    <p style="margin:0 0 12px 0; font-size:0.9rem; color:#475569;">
                        <strong>Scoring Context:</strong> Individual questions are scored on a scale of 1 to 5, where <strong>5 indicates the highest level of strain</strong> (strongest agreement with a strain marker).
                    </p>
                    <div style="display:flex; gap:12px; font-size:0.85rem; margin-bottom:12px;">
                        <span style="color:#166534; font-weight:600;">0% - 33% (Low Risk)</span>
                        <span style="color:#ca8a04; font-weight:600;">33% - 66% (Moderate Risk)</span>
                        <span style="color:#dc2626; font-weight:600;">66% - 100% (High Risk)</span>
                    </div>
                    <p style="margin:0; font-size:0.85rem; color:#64748b; font-style:italic; border-top:1px solid #e2e8f0; padding-top:10px;">
                        Note: The actual surveys contain 30 questions; what appears here is just a sample of the kind of questions used to measure a strain index.
                    </p>
                </div>`;

                // Categories
                for (const [key, category] of Object.entries(sampleStrainData.breakdown)) {
                    html += `<h3 style="margin:0 0 12px 0; font-size:1.1rem; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">${key} (${category.percent}%)</h3>`;

                    if (sampleStrainData.detailed_answers[key]) {
                        for (const [quiz, answers] of Object.entries(sampleStrainData.detailed_answers[key])) {
                            html += `<div style="margin-bottom:16px;">`;
                            html += `<h4 style="margin:0 0 8px 0; font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Source: ${quiz} Quiz</h4>`;
                            html += `<ul style="list-style:none; padding:0; margin:0;">`;

                            for (const [q, a] of Object.entries(answers)) {
                                let ansColor = '#22c55e'; // Low
                                const val = parseFloat(a);
                                if (val >= 4) ansColor = '#dc2626';
                                else if (val >= 2) ansColor = '#ca8a04';

                                html += `<li style="margin-bottom:8px; padding:12px; background:#fff; border:1px solid #f1f5f9; border-radius:6px;">
                                    <strong style="display:block; margin-bottom:4px; color:#334155; font-size:0.95rem;">${q}</strong>
                                    <span style="font-weight:600; color:${ansColor}; font-size:0.9rem;">User Answer: ${a} / 5</span>
                                </li>`;
                            }
                            html += `</ul></div>`;
                        }
                    }
                }

                body.innerHTML = html;
            };

            window.closeSampleStrainDetails = function () {
                const modal = document.getElementById('mc-sample-strain-details-modal');
                if (modal) modal.style.display = 'none';
            };
        </script>
        <?php
    }
}
