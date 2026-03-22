<?php
if (!defined('ABSPATH'))
    exit;

/**
 * AI Coach Lab Mode - Experimental workflow for data-rich, experiment-driven challenges.
 * 
 * This class provides a separate tab that guides users through:
 * Import assessment results → Add personal qualifiers → Generate tailored experiments → Execute → Reflect → Recalibrate
 */
class Micro_Coach_AI_Lab
{

    // Database table names
    const TABLE_LAB_EXPERIMENTS = 'mc_lab_experiments';
    const TABLE_LAB_FEEDBACK = 'mc_lab_feedback';
    const TABLE_LAB_USER_PREFERENCES = 'mc_lab_user_preferences';

    // Feature flag option
    const OPT_LAB_MODE_ENABLED = 'mc_lab_mode_enabled';

    public function __construct()
    {
        // Always register admin settings (needed for the feature flag to appear)
        if (is_admin()) {
            add_action('admin_init', [$this, 'register_lab_mode_settings']);
        }

        // Only initialize Lab Mode features if enabled
        if (!$this->is_lab_mode_enabled()) {
            return;
        }

        // Initialize database tables on admin load
        add_action('admin_init', [$this, 'maybe_create_tables']);

        // Register AJAX endpoints for Lab Mode functionality (with permission checks)
        add_action('wp_ajax_mc_lab_get_profile_data', [$this, 'ajax_get_profile_data']);
        add_action('wp_ajax_mc_lab_save_qualifiers', [$this, 'ajax_save_qualifiers']);
        add_action('wp_ajax_mc_lab_generate_experiments', [$this, 'ajax_generate_experiments']);
        add_action('wp_ajax_mc_lab_start_experiment', [$this, 'ajax_start_experiment']);
        add_action('wp_ajax_mc_lab_submit_reflection', [$this, 'ajax_submit_reflection']);
        add_action('wp_ajax_mc_lab_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_mc_lab_get_experiment', [$this, 'ajax_get_experiment']);
        add_action('wp_ajax_mc_lab_save_experiment', [$this, 'ajax_save_experiment']);
        add_action('wp_ajax_mc_lab_recalibrate', [$this, 'ajax_recalibrate']);
        add_action('wp_ajax_mc_lab_debug_user_data', [$this, 'ajax_debug_user_data']);
        add_action('wp_ajax_mc_lab_test_save_qualifiers', [$this, 'ajax_test_save_qualifiers']);
        add_action('wp_ajax_mc_lab_generate_ai_variant', [$this, 'ajax_generate_ai_variant']);
        add_action('wp_ajax_mc_lab_iterate', [$this, 'ajax_iterate']);
        add_action('wp_ajax_mc_lab_analyze_role_models', [$this, 'ajax_analyze_role_models']);
        add_action('wp_ajax_mc_lab_generate_career_map', [$this, 'ajax_generate_career_map']);
        add_action('wp_ajax_mc_lab_career_feedback', [$this, 'ajax_career_feedback']);
        add_action('wp_ajax_mc_lab_get_saved_careers', [$this, 'ajax_get_saved_careers']);
        add_action('wp_ajax_mc_lab_delete_saved_career', [$this, 'ajax_delete_saved_career']);
        add_action('wp_ajax_mc_lab_career_suggest', [$this, 'ajax_career_suggest']);
        add_action('wp_ajax_mc_lab_get_related_careers', [$this, 'ajax_get_related_careers']);

        // Hook into the main dashboard to add Lab Mode and Career Explorer tabs
        add_filter('mc_dashboard_custom_tabs', [$this, 'add_lab_mode_tab']);
        add_action('mc_dashboard_custom_tab_content', [$this, 'render_lab_mode_content']);
        add_action('mc_dashboard_custom_tab_content', [$this, 'render_career_explorer_content']);
    }

    /**
     * Check if current user has Lab Mode access
     */
    private function user_can_access_lab_mode()
    {
        // Allow any logged-in user to access Lab Mode
        return is_user_logged_in();
    }

    /**
     * Check if Lab Mode is enabled via feature flag
     */
    private function is_lab_mode_enabled()
    {
        // Temporarily enable by default for testing
        return true;
        // Original code: return get_option(self::OPT_LAB_MODE_ENABLED, '0') === '1';
    }

    /**
     * Create database tables for Lab Mode
     */
    public function maybe_create_tables()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $experiments_table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;
        $feedback_table = $wpdb->prefix . self::TABLE_LAB_FEEDBACK;
        $preferences_table = $wpdb->prefix . self::TABLE_LAB_USER_PREFERENCES;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Experiments table
        $sql1 = "CREATE TABLE `$experiments_table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            experiment_data LONGTEXT NOT NULL,
            profile_data LONGTEXT NOT NULL,
            archetype VARCHAR(20) NOT NULL DEFAULT 'Discover',
            status VARCHAR(20) NOT NULL DEFAULT 'Draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY archetype (archetype)
        ) $charset;";

        // Feedback table
        $sql2 = "CREATE TABLE `$feedback_table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            experiment_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            difficulty TINYINT(1) NOT NULL,
            fit TINYINT(1) NOT NULL,
            learning TINYINT(1) NOT NULL,
            notes TEXT,
            next_action VARCHAR(20) NOT NULL,
            evolve_notes TEXT,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY experiment_id (experiment_id),
            KEY user_id (user_id)
        ) $charset;";

        // User preferences table for recalibration
        $sql3 = "CREATE TABLE `$preferences_table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            contexts LONGTEXT NOT NULL,
            risk_bias DECIMAL(3,2) DEFAULT 0.00,
            solo_group_bias DECIMAL(3,2) DEFAULT 0.00,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /**
     * Register Lab Mode settings in the admin
     */
    public function register_lab_mode_settings()
    {
        // Add feature flag setting
        register_setting(Micro_Coach_Core::OPT_GROUP, self::OPT_LAB_MODE_ENABLED, function ($v) {
            return $v ? '1' : '0';
        });

        add_settings_field(
            self::OPT_LAB_MODE_ENABLED,
            'Lab Mode (Experimental)',
            function () {
                $enabled = get_option(self::OPT_LAB_MODE_ENABLED, '0') === '1' ? 'checked' : '';
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_LAB_MODE_ENABLED) . '" value="1" ' . $enabled . '> Enable AI Coach Lab Mode - experimental workflow for data-rich challenges.</label>';
                echo '<p class="description">This adds a separate "Lab Mode" tab with profile-based experiment generation, feedback loops, and recalibration.</p>';
            },
            'quiz-platform-settings',
            'mc_quiz_ai_section'
        );
    }

    /**
     * Add Lab Mode and Career Explorer tabs to the dashboard (if all assessments are complete)
     */
    public function add_lab_mode_tab($tabs)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return $tabs;

        // Hide Lab Mode for employees
        if (!empty(get_user_meta($user_id, 'mc_linked_employer_id', true)))
            return $tabs;

        // Check if all assessments are complete
        $quizzes = Micro_Coach_Core::get_quizzes();
        $all_complete = true;
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['results_meta_key'])) {
                $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                if (empty($results)) {
                    $all_complete = false;
                    break;
                }
            }
        }

        if ($all_complete) {
            $tabs['tab-lab'] = '🧪 Lab Mode';
            // Career Explorer disabled for employees per requirements
            // $tabs['tab-career'] = '⚡ Career Explorer';
        }

        return $tabs;
    }

    /**
     * Render Lab Mode content in the dashboard
     */
    public function render_lab_mode_content($tab_id)
    {
        // Only render for the lab tab
        if ($tab_id !== 'tab-lab') {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id)
            return;

        // Check if all assessments are complete (same check as tab visibility)
        $quizzes = Micro_Coach_Core::get_quizzes();
        $all_complete = true;
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['results_meta_key'])) {
                $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                if (empty($results)) {
                    $all_complete = false;
                    break;
                }
            }
        }

        if (!$all_complete)
            return;

        // Render empty container - assets will load when tab becomes active
        echo '<div id="lab-mode-app"></div>';
        ?>
        <script>
            // Load Lab Mode assets and initialize only when the tab becomes active
            // Load Lab Mode assets and initialize        only when the tab becomes active
            document.addEventListener('DOMContentLoaded', function () {
                console.log("Lab Mode PHP inline script running...");

                // Flags to prevent multiple loads/initializations
                window.labModeAssetsLoaded = false;
                window.labModeInitialized = false;

                // Function to load Lab Mode assets dynamically
                function loadLabModeAssets() {
                    if (window.labModeAssetsLoaded) {
                        console.log("Lab Mode assets already loaded, skipping...");
                        return Promise.resolve();
                    }

                    console.log("Loading Lab Mode assets...");
                    window.labModeAssetsLoaded = true;

                    return new Promise(function (resolve, reject) {
                        // Function to load jQuery if needed
                        function loadJQuery() {
                            if (typeof jQuery !== 'undefined') {
                                return Promise.resolve();
                            }
                            console.log("Loading jQuery...");
                            return new Promise(function (resolve, reject) {
                                var script = document.createElement('script');
                                script.src = '<?php echo includes_url('js/jquery/jquery.min.js'); ?>';
                                script.onload = function () {
                                    console.log("jQuery loaded");
                                    resolve();
                                };
                                script.onerror = function () {
                                    console.error("Failed to load jQuery");
                                    reject(new Error('Failed to load jQuery'));
                                };
                                document.head.appendChild(script);
                            });
                        }

                        // Function to load D3.js if needed
                                function loadD3() {
                                    if (typeof d3 !== 'undefined') {
                                        return Promise.resolve();
                                    }
                                    console.log("Loading D3.js...");
                                    return new Promise(function(resolve, reject) {
                                        var script = document.createElement('script');
                                        script.src = 'https://d3js.org/d3.v7.min.js';
                                        script.onload = function() {
                                            console.log("D3.js loaded");
                                            resolve();
                                        };
                                        script.onerror = function() {
                                            console.error("Failed to load D3.js");
                                            // D3 is optional for some features, so maybe don't reject hard?
                                            // But if lab-mode.js depends on it, it might crash.
                                            // Let's reject for now to be safe.
                                            reject(new Error('Failed to load D3.js'));
                                        };
                                        document.head.appendChild(script);
                                    });
                                }

                                // Load CSS first
                                var css = document.createElement('link');
                                css.rel = 'stylesheet';
                                css.href = '<?php echo esc_url_raw(plugins_url('assets/lab-mode.css', __FILE__)); ?>?ver=' + Date.now();
                                document.head.appendChild(css);

                                // Load AI Loading Overlay CSS
                                var aiCss = document.createElement('link');
                                aiCss.rel = 'stylesheet';
                                aiCss.href = '<?php echo esc_url_raw(plugins_url('assets/ai-loading-overlay.css', __FILE__)); ?>?ver=' + Date.now();
                                document.head.appendChild(aiCss);

                                // Ensure dependencies are loaded before other scripts
                                Promise.all([loadJQuery(), loadD3()]).then(function() {
                                    // Load AI Loading Overlay JavaScript first
                                    var aiScript = document.createElement('script');
                                    aiScript.src = '<?php echo esc_url_raw(plugins_url('assets/ai-loading-overlay.js', __FILE__)); ?>?ver=' + Date.now();
                                    aiScript.onload = function () {
                                        console.log("AI Loading Overlay JS loaded");

                                        // Load main JavaScript after AI overlay is ready
                                        var script = document.createElement('script');
                                        script.src = '<?php echo esc_url_raw(plugins_url('assets/lab-mode.js', __FILE__)); ?>?ver=' + Date.now();
                                        script.onload = function () {
                                            console.log("Lab Mode main JS loaded");

                                            // Load iteration panel JavaScript
                                            var iterateScript = document.createElement('script');
                                            iterateScript.src = '<?php echo esc_url_raw(plugins_url('assets/lab-mode-iterate.js', __FILE__)); ?>?ver=' + Date.now();
                                            iterateScript.onload = function () {
                                                console.log("Lab Mode iterate JS loaded");

                                                // Load role model discovery JavaScript
                                                var roleModelScript = document.createElement('script');
                                                roleModelScript.src = '<?php echo esc_url_raw(plugins_url('assets/lab-mode-rolemodel-discovery.js', __FILE__)); ?>?ver=' + Date.now();
                                                roleModelScript.onload = function () {
                                                    console.log("Lab Mode role model discovery JS loaded");
                                                    console.log("Lab Mode assets loaded successfully");

                                                    // Set up localized data
                                                    window.labMode = {
                                                        ajaxUrl: '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>',
                                                        nonce: '<?php echo wp_create_nonce('mc_lab_nonce'); ?>',
                                                        userId: <?php echo get_current_user_id(); ?>,
                                                        restUrl: '<?php echo esc_url_raw(rest_url('wp/v2/')); ?>',
                                                        isAdmin: <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>,
                                                        defaultModel: '<?php echo class_exists('Micro_Coach_AI') ? Micro_Coach_AI::get_selected_model() : 'gpt-4o-mini'; ?>'
                                                    };
                                                    resolve();
                                                };
                                                roleModelScript.onerror = function () {
                                                    console.error("Failed to load Lab Mode Role Model Discovery JavaScript");
                                                    reject(new Error('Failed to load Lab Mode role model discovery assets'));
                                                };
                                                document.head.appendChild(roleModelScript);
                                            };
                                            iterateScript.onerror = function () {
                                                console.error("Failed to load Lab Mode Iterate JavaScript");
                                                reject(new Error('Failed to load Lab Mode iterate assets'));
                                            };
                                            document.head.appendChild(iterateScript);
                                        };
                                        script.onerror = function () {
                                            console.error("Failed to load Lab Mode JavaScript");
                                            reject(new Error('Failed to load Lab Mode assets'));
                                        };
                                        document.head.appendChild(script);
                                    };
                                    aiScript.onerror = function () {
                                        console.error("Failed to load AI Loading Overlay JavaScript");
                                        reject(new Error('Failed to load AI Loading Overlay assets'));
                                    };
                                    document.head.appendChild(aiScript);
                                }).catch(function(err) {
                                    reject(err);
                                });        });
                                            }

                                        // Function to initialize Lab Mode
                                        function initializeLabModeOnce() {
                                            if (window.labModeInitialized) {
                                                console.log("Lab Mode already initialized, skipping...");
                                                return;
                                            }

                                            console.log("Initializing Lab Mode for the first time...");
                                            window.labModeInitialized = true;

                                            // First load assets, then initialize
                                            loadLabModeAssets().then(function () {
                                                // Wait a bit for scripts to be fully loaded and parsed
                                                setTimeout(function () {
                                                    // Try the global initialization function first
                                                    if (typeof window.initializeLabMode === "function") {
                                                        console.log("Using global initializeLabMode function");
                                                        window.initializeLabMode();
                                                    } else if (typeof window.LabModeApp !== "undefined" && !window.LabModeAppInitialized) {
                                                        console.log("Using direct LabModeApp.init()");
                                                        window.LabModeApp.init();
                                                    } else {
                                                        console.log("Waiting for Lab Mode to load...");
                                                        // Wait for Lab Mode to load
                                                        var attempts = 0;
                                                        var initInterval = setInterval(function () {
                                                            attempts++;
                                                            if (typeof window.initializeLabMode === "function") {
                                                                console.log("Lab Mode loaded, initializing...");
                                                                window.initializeLabMode();
                                                                clearInterval(initInterval);
                                                            } else if (attempts > 30) {
                                                                console.error("Lab Mode failed to load");
                                                                var appContainer = document.getElementById("lab-mode-app");
                                                                if (appContainer) {
                                                                    appContainer.innerHTML = '<div class="lab-mode-error"><h3>Lab Mode Loading Error</h3><p>Lab Mode assets failed to load. Please refresh the page.</p><button class="lab-btn lab-btn-primary" onclick="location.reload()">Refresh Page</button></div>';
                                                                }
                                                                clearInterval(initInterval);
                                                            }
                                                        }, 100);
                                                    }
                                                }, 200);
                                            }).catch(function (error) {
                                                console.error("Failed to load Lab Mode assets:", error);
                                                var appContainer = document.getElementById("lab-mode-app");
                                                if (appContainer) {
                                                    appContainer.innerHTML = '<div class="lab-mode-error"><h3>Lab Mode Loading Error</h3><p>Failed to load Lab Mode assets. Please refresh the page.</p><button class="lab-btn lab-btn-primary" onclick="location.reload()">Refresh Page</button></div>';
                                                }
                                            });
                                        }

                                        // Check if Lab Mode tab is currently active
                                        var labTab = document.querySelector('[data-tab="tab-lab"]');
                                        if (labTab && labTab.classList.contains('active')) {
                                            console.log("Lab Mode tab is active, initializing immediately...");
                                            initializeLabModeOnce();
                                        }

                                        // Listen for tab clicks to initialize Lab Mode when its tab becomes active
                                        document.addEventListener('click', function(e) {
                                            if (e.target && e.target.matches('[data-tab="tab-lab"]')) {
                                                console.log("Lab Mode tab clicked, initializing...");
                                                setTimeout(initializeLabModeOnce, 100);
                                            }
                                        });

                                        // Listen for custom tab change event (more reliable)
                                        document.addEventListener('mc_tab_change', function (e) {
                                            if (e.detail && e.detail.tabId === 'tab-lab') {
                                                console.log("Lab Mode tab activated via event, initializing...");
                                                initializeLabModeOnce();
                                            }
                                        });

                                        // If URL requests Lab tab or Career Explorer directly
                                        try {
                                            var params = new URLSearchParams(window.location.search);
                                            var wantsLab = (params.get('tab') === 'lab') || (window.location.hash === '#tab-lab' || window.location.hash === '#lab');
                                            var wantsCareer = params.get('career') === '1' || window.location.hash === '#career-explorer';

                                            if (wantsCareer || wantsLab) {
                                                var labBtn = document.querySelector('[data-tab="tab-lab"]');
                                                if (labBtn) {
                                                    // Switch tab and initialize
                                                    labBtn.click();
                                                    setTimeout(function () {
                                                        initializeLabModeOnce();

                                                        // If career explorer requested, jump directly to it
                                                        if (wantsCareer) {
                                                            setTimeout(function () {
                                                                if (window.LabModeApp && typeof window.LabModeApp.showCareerExplorerDirectly === 'function') {
                                                                    console.log('Jumping to Career Explorer...');
                                                                    window.LabModeApp.showCareerExplorerDirectly();
                                                                }
                                                            }, 500);
                                                        }
                                                    }, 150);
                                                }
                                            }
                                        } catch (e) {
                                            console.warn('Lab Mode: unable to parse URL params for tab switch');
                                        }
                                    });
                                </script>
                                <?php
    }

    /**
     * Render Career Explorer content in the dashboard
     */
    public function render_career_explorer_content($tab_id)
    {
        // Only render for the career explorer tab
        if ($tab_id !== 'tab-career') {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id)
            return;

        // Check if all assessments are complete (same check as tab visibility)
        $quizzes = Micro_Coach_Core::get_quizzes();
        $all_complete = true;
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['results_meta_key'])) {
                $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                if (empty($results)) {
                    $all_complete = false;
                    break;
                }
            }
        }

        if (!$all_complete)
            return;

        // Render Career Explorer directly
        echo '<div id="career-explorer-standalone"></div>';
        ?>
                                <script>
                                    jQuery(document).ready(function ($) {
                                        console.log("Career Explorer tab script running...");

                                        // Flags to prevent multiple loads
                                        window.careerExplorerAssetsLoaded = window.careerExplorerAssetsLoaded || false;
                                        window.careerExplorerInitialized = window.careerExplorerInitialized || false;

                                        function loadCareerExplorerAssets() {
                                            if (window.careerExplorerAssetsLoaded) {
                                                console.log("Career Explorer assets already loaded");
                                                return Promise.resolve();
                                            }

                                            console.log("Loading Career Explorer assets...");
                                            window.careerExplorerAssetsLoaded = true;

                                            return new Promise(function (resolve, reject) {
                                                // Load Lab Mode CSS (includes career explorer styles)
                                                var css = document.createElement('link');
                                                css.rel = 'stylesheet';
                                                css.href = '<?php echo esc_url_raw(plugins_url('assets/lab-mode.css', __FILE__)); ?>?ver=' + Date.now();
                                                document.head.appendChild(css);

                                                // Load AI Loading Overlay CSS
                                                var aiCss = document.createElement('link');
                                                aiCss.rel = 'stylesheet';
                                                aiCss.href = '<?php echo esc_url_raw(plugins_url('assets/ai-loading-overlay.css', __FILE__)); ?>?ver=' + Date.now();
                                                document.head.appendChild(aiCss);

                                                // Load D3.js FIRST for Mind-Map visualization
                                                var d3Script = document.createElement('script');
                                                d3Script.src = 'https://d3js.org/d3.v7.min.js';
                                                d3Script.onload = function () {
                                                    console.log("D3.js loaded");

                                                    // Load AI Loading Overlay JavaScript
                                                    var aiScript = document.createElement('script');
                                                    aiScript.src = '<?php echo esc_url_raw(plugins_url('assets/ai-loading-overlay.js', __FILE__)); ?>?ver=' + Date.now();
                                                    aiScript.onload = function () {
                                                        console.log("AI Loading Overlay JS loaded");

                                                        // Load Lab Mode JavaScript (includes career explorer functions)
                                                        var script = document.createElement('script');
                                                        script.src = '<?php echo esc_url_raw(plugins_url('assets/lab-mode.js', __FILE__)); ?>?ver=' + Date.now();
                                                        script.onload = function () {
                                                            console.log("Career Explorer JS loaded");
                                                            resolve();
                                                        };
                                                        script.onerror = reject;
                                                        document.head.appendChild(script);
                                                    };
                                                    aiScript.onerror = reject;
                                                    document.head.appendChild(aiScript);
                                                };
                                                d3Script.onerror = function () {
                                                    console.error('Failed to load D3.js');
                                                    reject(new Error('D3.js failed to load'));
                                                };
                                                document.head.appendChild(d3Script);
                                            });
                                        }

                                        function initCareerExplorer() {
                                            if (window.careerExplorerInitialized) {
                                                console.log("Career Explorer already initialized");
                                                return;
                                            }

                                            if (typeof window.LabModeApp === 'undefined') {
                                                console.error("LabModeApp not available");
                                                return;
                                            }

                                            window.careerExplorerInitialized = true;
                                            console.log("Initializing Career Explorer standalone...");

                                            // Render career explorer directly in standalone container
                                            var html = `
                    <div class="career-explorer-standalone-wrapper">
                        ${window.LabModeApp.renderCareerExplorerTab()}
                    </div>
                `;

                                            $('#career-explorer-standalone').html(html);
                                        }

                                        // Initialize labMode data globally
                                        window.labMode = window.labMode || {
                                            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                                            nonce: '<?php echo wp_create_nonce('mc_lab_nonce'); ?>',
                                            userId: <?php echo get_current_user_id(); ?>
                                        };

                                        // Check if tab is already active when page loads
                                        if ($('#tab-career.active').length > 0 || $('#tab-career').is(':visible')) {
                                            console.log("Career Explorer tab is active on load, initializing...");
                                            loadCareerExplorerAssets().then(function () {
                                                setTimeout(initCareerExplorer, 150);
                                            }).catch(function (err) {
                                                console.error("Failed to load Career Explorer assets:", err);
                                            });
                                        }

                                        // Listen for tab clicks to load assets when Career Explorer becomes active
                                        // Support both data-tab attribute and direct clicks on tab links
                                        $(document).on('click', '[data-tab="tab-career"], .tab-link[href="#tab-career"]', function (e) {
                                            console.log("Career Explorer tab clicked");

                                            loadCareerExplorerAssets().then(function () {
                                                setTimeout(initCareerExplorer, 150);
                                            }).catch(function (err) {
                                                console.error("Failed to load Career Explorer assets:", err);
                                            });
                                        });

                                        // Also watch for tab content becoming visible (for tab switching)
                                        var observer = new MutationObserver(function (mutations) {
                                            mutations.forEach(function (mutation) {
                                                if (mutation.target.id === 'tab-career' && $(mutation.target).hasClass('active')) {
                                                    console.log("Career Explorer tab became active via mutation");
                                                    loadCareerExplorerAssets().then(function () {
                                                        setTimeout(initCareerExplorer, 150);
                                                    });
                                                }
                                            });
                                        });

                                        var careerTab = document.getElementById('tab-career');
                                        if (careerTab) {
                                            observer.observe(careerTab, {
                                                attributes: true,
                                                attributeFilter: ['class']
                                            });
                                        }
                                    });
                                </script>
                                <?php
    }

    /**
     * Enqueue Lab Mode JavaScript and CSS
     */
    private function enqueue_lab_mode_assets()
    {
        // Enqueue D3.js FIRST for Mind-Map visualization
        wp_enqueue_script(
            'd3js',
            'https://d3js.org/d3.v7.min.js',
            [],
            '7.8.5',
            false  // Load in header
        );

        // Enqueue Lab Mode JS with D3 as dependency
        wp_enqueue_script(
            'lab-mode-js',
            plugins_url('assets/lab-mode.js', __FILE__),
            ['jquery', 'd3js'],  // Add d3js as dependency
            time(),  // Use timestamp for cache busting during development
            false  // Load in header instead of footer
        );

        // Enqueue AI Loading Overlay assets
        wp_enqueue_script(
            'ai-loading-overlay-js',
            plugins_url('assets/ai-loading-overlay.js', __FILE__),
            ['jquery', 'd3js', 'lab-mode-js'],
            time(),
            false
        );

        wp_enqueue_style(
            'lab-mode-css',
            plugins_url('assets/lab-mode.css', __FILE__),
            [],
            time()  // Use timestamp for cache busting during development
        );

        wp_enqueue_style(
            'ai-loading-overlay-css',
            plugins_url('assets/ai-loading-overlay.css', __FILE__),
            [],
            time()
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('lab-mode-js', 'labMode', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_lab_nonce'),
            'userId' => get_current_user_id(),
            'restUrl' => rest_url('wp/v2/'),
            'isAdmin' => current_user_can('manage_options'),
            'defaultModel' => class_exists('Micro_Coach_AI') ? Micro_Coach_AI::get_selected_model() : 'gpt-4o-mini',
        ]);
    }

    /**
     * AJAX: Get user profile data (assessments)
     */
    public function ajax_get_profile_data()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Gather assessment data
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

        // Load question files for category mappings
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';

        $profile_data = [
            'mi_results' => $this->format_mi_results($mi_results, $mi_categories ?? []),
            'cdt_results' => $this->format_cdt_results($cdt_results, $cdt_categories ?? []),
            'bartle_results' => $this->format_bartle_results($bartle_results, $bartle_categories ?? []),
            'johari_results' => $this->format_johari_results($bartle_results), // Johari data is in Bartle results
        ];

        wp_send_json_success($profile_data);
    }

    /**
     * Format MI results for Lab Mode
     */
    private function format_mi_results($results, $categories)
    {
        if (empty($results) || empty($results['top3'])) {
            return null;
        }

        $formatted = [];
        foreach ($results['top3'] as $index => $mi_slug) {
            $score = $results['part1Scores'][$mi_slug] ?? 0;
            $formatted[] = [
                'key' => $mi_slug,
                'score' => (int) $score,
                'rank' => $index + 1,
                'label' => $categories[$mi_slug] ?? ucfirst(str_replace('-', ' ', $mi_slug))
            ];
        }

        return $formatted;
    }

    /**
     * Format CDT results for Lab Mode
     */
    private function format_cdt_results($results, $categories)
    {
        if (empty($results) || empty($results['sortedScores'])) {
            return null;
        }

        $formatted = [];
        foreach ($results['sortedScores'] as $index => $score_data) {
            $cdt_slug = $score_data[0];
            $score = $score_data[1];
            $formatted[] = [
                'key' => $cdt_slug,
                'score' => (int) $score,
                'rank' => $index + 1,
                'label' => $categories[$cdt_slug] ?? ucfirst(str_replace('-', ' ', $cdt_slug))
            ];
        }

        return $formatted;
    }

    /**
     * Format Bartle results for Lab Mode
     */
    private function format_bartle_results($results, $categories)
    {
        if (empty($results) || empty($results['sortedScores'])) {
            return null;
        }

        $primary_slug = $results['sortedScores'][0][0];
        $secondary_slug = isset($results['sortedScores'][1]) ? $results['sortedScores'][1][0] : null;

        return [
            'primary' => $primary_slug,
            'secondary' => $secondary_slug,
            'primary_label' => $categories[$primary_slug] ?? ucfirst($primary_slug),
            'secondary_label' => $secondary_slug ? ($categories[$secondary_slug] ?? ucfirst($secondary_slug)) : null
        ];
    }

    /**
     * Format Johari results (extracted from Bartle quiz)
     */
    private function format_johari_results($bartle_results)
    {
        // For now, return mock data - in real implementation this would come from actual Johari assessment
        return [
            'known' => ['reliable', 'curious'],
            'blind' => ['reserved'],
            'hidden' => ['ambitious'],
            'unknown' => []
        ];
    }

    /**
     * AJAX: Save user qualifiers
     */
    public function ajax_save_qualifiers()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            error_log('Lab Mode Debug - Save qualifiers: Insufficient permissions');
            wp_send_json_error('Insufficient permissions');
        }

        $raw_qualifiers = stripslashes($_POST['qualifiers'] ?? '');
        error_log('Lab Mode Debug - Raw qualifiers received: ' . $raw_qualifiers);

        $qualifiers_data = json_decode($raw_qualifiers, true);
        if (!$qualifiers_data) {
            error_log('Lab Mode Debug - Failed to decode qualifiers JSON');
            wp_send_json_error('Invalid qualifiers data - failed to parse JSON');
        }

        error_log('Lab Mode Debug - Decoded qualifiers: ' . print_r($qualifiers_data, true));

        // Save qualifiers to user meta
        $result = update_user_meta($user_id, 'mc_lab_qualifiers', $qualifiers_data);
        error_log('Lab Mode Debug - Update user meta result: ' . ($result ? 'success' : 'failed'));

        // Verify the save worked
        $saved_data = get_user_meta($user_id, 'mc_lab_qualifiers', true);
        error_log('Lab Mode Debug - Retrieved saved qualifiers: ' . print_r($saved_data, true));

        wp_send_json_success([
            'message' => 'Qualifiers saved',
            'saved_data_preview' => !empty($saved_data) ? array_keys($saved_data) : 'Empty'
        ]);
    }

    /**
     * AJAX: Generate experiments using AI
     */
    public function ajax_generate_experiments()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get user profile and qualifiers
        $profile_data = $this->get_user_profile_data($user_id);
        $qualifiers = get_user_meta($user_id, 'mc_lab_qualifiers', true);

        // Get model parameter from admin users (optional)
        $selected_model = null;
        if (current_user_can('manage_options') && !empty($_POST['model'])) {
            $selected_model = sanitize_text_field($_POST['model']);
            error_log('Lab Mode Debug - Admin selected model: ' . $selected_model);
        }

        // Debug logging
        error_log('Lab Mode Debug - Profile data: ' . (!empty($profile_data) ? 'found' : 'empty'));
        error_log('Lab Mode Debug - Qualifiers: ' . (!empty($qualifiers) ? 'found' : 'empty'));

        if (empty($profile_data) || empty($qualifiers)) {
            $error_msg = 'Missing: ';
            if (empty($profile_data))
                $error_msg .= 'profile data ';
            if (empty($qualifiers))
                $error_msg .= 'qualifiers ';

            wp_send_json_error($error_msg . '- ensure all assessments are complete and qualifiers are saved');
        }

        $using_mock = false;
        try {
            $experiments = $this->generate_experiments_with_ai($profile_data, $qualifiers, $selected_model);
            error_log('Lab Mode Debug - AI experiments generated successfully');
        } catch (Exception $ai_error) {
            error_log('Lab Mode Debug - AI failed, using mock experiments: ' . $ai_error->getMessage());

            // Use mock experiments for testing if AI fails
            $experiments = $this->get_mock_experiments($profile_data, $qualifiers);
            $using_mock = true;
            error_log('Lab Mode Debug - Using mock experiments instead');
        }

        try {

            // Save experiments to database
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;

            $experiment_ids = [];
            foreach ($experiments as $experiment) {
                $result = $wpdb->insert($table, [
                    'user_id' => $user_id,
                    'experiment_data' => wp_json_encode($experiment),
                    'profile_data' => wp_json_encode($profile_data),
                    'archetype' => $experiment['archetype'],
                    'status' => 'Draft'
                ]);

                if ($result) {
                    $experiment_ids[] = $wpdb->insert_id;
                }
            }

            wp_send_json_success([
                'experiments' => $experiments,
                'experiment_ids' => $experiment_ids,
                'using_mock' => $using_mock,
                'source' => $using_mock ? 'Mock Data (AI Failed)' : 'AI Generated'
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to generate experiments: ' . $e->getMessage());
        }
    }

    /**
     * Get complete user profile data for experiment generation
     */
    private function get_user_profile_data($user_id)
    {
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

        // Debug logging
        error_log('Lab Mode Debug - MI results: ' . (!empty($mi_results) ? 'found' : 'empty'));
        error_log('Lab Mode Debug - CDT results: ' . (!empty($cdt_results) ? 'found' : 'empty'));
        error_log('Lab Mode Debug - Bartle results: ' . (!empty($bartle_results) ? 'found' : 'empty'));

        if (empty($mi_results) || empty($cdt_results) || empty($bartle_results)) {
            error_log('Lab Mode Debug - Missing assessment data, returning null');
            return null;
        }

        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';

        return [
            'mi_top3' => $this->format_mi_results($mi_results, $mi_categories ?? []),
            'cdt' => $this->format_cdt_results($cdt_results, $cdt_categories ?? []),
            'johari' => $this->format_johari_results($bartle_results),
            'bartle' => $this->format_bartle_results($bartle_results, $bartle_categories ?? [])
        ];
    }

    /**
     * Get mock experiments for testing when AI fails
     */
    private function get_mock_experiments($profile_data, $qualifiers)
    {
        $mi_top = $profile_data['mi_top3'][0] ?? ['label' => 'Unknown'];
        $cdt_low = $profile_data['cdt'][0] ?? ['label' => 'Unknown'];
        $curiosity = $qualifiers['curiosity']['curiosities'][0] ?? 'learning';

        return [
            [
                'archetype' => 'Discover',
                'title' => "Scout {$curiosity} through {$mi_top['label']}",
                'rationale' => "This builds on your {$mi_top['label']} strength while gently exercising {$cdt_low['label']}.",
                'steps' => [
                    "Define a simple question: 'What don't I know about {$curiosity}?'",
                    "Collect 3 examples from online sources or role models",
                    "Take notes for 45 minutes using your {$mi_top['label']} approach",
                    "Identify one surprising insight or connection"
                ],
                'resources' => [
                    "Online articles about {$curiosity}",
                    "Note-taking tools that suit {$mi_top['label']}"
                ],
                'effort' => [
                    'timeHours' => 2,
                    'budgetUSD' => 0
                ],
                'riskLevel' => 'Low',
                'successCriteria' => [
                    "Spent at least 45 minutes actively exploring",
                    "Documented findings using preferred approach",
                    "Identified one surprising insight or connection"
                ],
                'linkedMI' => [$mi_top['key'] ?? 'unknown'],
                'linkedCDT' => [$cdt_low['key'] ?? 'unknown'],
                'tags' => ['discovery', $curiosity, 'MOCK_EXPERIMENT']
            ],
            [
                'archetype' => 'Build',
                'title' => "Create a small {$curiosity} project",
                'rationale' => "This leverages your {$mi_top['label']} for hands-on creation.",
                'steps' => [
                    "Set constraints: 2 hours, $0 budget, low risk",
                    "Create a first version using your {$mi_top['label']} strengths",
                    "Test with 1-2 people for feedback",
                    "Iterate based on what you learned"
                ],
                'resources' => [
                    "Basic tools for {$mi_top['label']}-based creation",
                    "1-2 people who can give feedback"
                ],
                'effort' => [
                    'timeHours' => 3,
                    'budgetUSD' => 10
                ],
                'riskLevel' => 'Medium',
                'successCriteria' => [
                    "Created a working prototype within time limit",
                    "Tested with at least one real person",
                    "Received and considered feedback"
                ],
                'linkedMI' => [$mi_top['key'] ?? 'unknown'],
                'linkedCDT' => [$cdt_low['key'] ?? 'unknown'],
                'tags' => ['building', $curiosity, 'MOCK_EXPERIMENT']
            ],
            [
                'archetype' => 'Share',
                'title' => "Share your {$curiosity} insights",
                'rationale' => "Sharing helps you practice communication while using your {$mi_top['label']} abilities.",
                'steps' => [
                    "Package your learning (1-page summary, demo, or 90-sec talk)",
                    "Share with friends/colleagues and ask 3 specific questions",
                    "Listen actively and take notes",
                    "Log 3 takeaways and decide next steps"
                ],
                'resources' => [
                    "Simple presentation tools",
                    "Friends or colleagues interested in {$curiosity}"
                ],
                'effort' => [
                    'timeHours' => 2,
                    'budgetUSD' => 0
                ],
                'riskLevel' => 'Low',
                'successCriteria' => [
                    "Shared with at least 2 people",
                    "Asked meaningful follow-up questions",
                    "Received specific feedback from audience"
                ],
                'linkedMI' => [$mi_top['key'] ?? 'unknown'],
                'linkedCDT' => [$cdt_low['key'] ?? 'unknown'],
                'tags' => ['sharing', $curiosity, 'MOCK_EXPERIMENT']
            ]
        ];
    }

    /**
     * Generate experiments using AI based on profile and qualifiers
     */
    private function generate_experiments_with_ai($profile_data, $qualifiers, $model = null)
    {
        // Load deterministic content libraries
        $archetype_templates = $this->get_archetype_templates();
        $cdt_dataset = $this->get_cdt_dataset();

        // Build AI prompt
        $prompt = $this->build_experiment_generation_prompt($profile_data, $qualifiers, $archetype_templates, $cdt_dataset);

        // Call AI service (reuse existing Micro_Coach_AI infrastructure)
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $response = $this->call_openai_api($api_key, $prompt, $model);

        // Debug logging
        error_log('Lab Mode Debug - AI API response: ' . print_r($response, true));

        if (!$response) {
            throw new Exception('Empty AI response');
        }

        if (!isset($response['experiments'])) {
            throw new Exception('AI response missing experiments field. Response keys: ' . implode(', ', array_keys($response ?: [])));
        }

        error_log('Lab Mode Debug - Experiments count: ' . count($response['experiments']));
        return $response['experiments'];
    }

    /**
     * Get archetype templates for experiment generation
     */
    private function get_archetype_templates()
    {
        $templates_file = MC_QUIZ_PLATFORM_PATH . 'assets/lab_libraries/archetype_templates.json';
        if (!file_exists($templates_file)) {
            return $this->get_fallback_archetype_templates();
        }

        $templates_json = file_get_contents($templates_file);
        $templates = json_decode($templates_json, true);

        if (!$templates) {
            return $this->get_fallback_archetype_templates();
        }

        return $templates;
    }

    /**
     * Fallback archetype templates if library file is missing
     */
    private function get_fallback_archetype_templates()
    {
        return [
            'Discover' => [
                'titlePatterns' => ['Scout {topic} through {method}'],
                'stepTemplates' => [
                    [
                        'Define a simple question: "What don\'t I know about {topic}?"',
                        'Collect 3 examples from {roleModelsOrSources}.',
                        'Take notes for 45 minutes using {MI_mode} (e.g., sketch, audio, outline).'
                    ]
                ],
                'rationales' => ['This builds on your {MI_strengths} while gently exercising your {CDT_growth_area}.'],
                'successCriteria' => [
                    [
                        'Spent at least {timeBlock} actively exploring',
                        'Documented findings using preferred {MI_mode}',
                        'Identified one surprising insight or connection'
                    ]
                ]
            ],
            'Build' => [
                'titlePatterns' => ['Prototype a {artifact} in {timeBlock}'],
                'stepTemplates' => [
                    [
                        'Set constraints: time {timeBlock}, budget ${budget}, risk {riskLevel}.',
                        'Create a first version using your {MI_combo} strengths.',
                        'Invite 1–2 people for feedback focused on {successCriterion}.'
                    ]
                ],
                'rationales' => ['This leverages your {MI_strengths} for hands-on creation while practicing {CDT_skill}.'],
                'successCriteria' => [
                    [
                        'Created a working prototype within {timeBlock}',
                        'Tested the creation with at least one real use case',
                        'Received and incorporated feedback from others'
                    ]
                ]
            ],
            'Share' => [
                'titlePatterns' => ['Share {result} with {audience}'],
                'stepTemplates' => [
                    [
                        'Package your work (1-pager, demo, or 90-sec talk).',
                        'Share with {audience} and ask 3 specific questions.',
                        'Log 3 takeaways and decide: repeat, evolve, or archive.'
                    ]
                ],
                'rationales' => ['Sharing helps you practice {CDT_skill} while using your {MI_strengths} to communicate.'],
                'successCriteria' => [
                    [
                        'Shared with at least {minimum_audience} people',
                        'Received specific feedback from at least 2 sharers',
                        'Asked follow-up questions to understand impact'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get MI combinations for experiment personalization
     */
    private function get_mi_combinations()
    {
        $combinations_file = MC_QUIZ_PLATFORM_PATH . 'assets/lab_libraries/mi_combinations.json';
        if (!file_exists($combinations_file)) {
            return [];
        }

        $combinations_json = file_get_contents($combinations_file);
        $combinations = json_decode($combinations_json, true);

        return $combinations ?: [];
    }

    /**
     * Get CDT dataset for coaching insights
     */
    private function get_cdt_dataset()
    {
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/details.php';
        return $cdt_dimension_details ?? [];
    }

    /**
     * Build AI prompt for experiment generation
     */
    private function build_experiment_generation_prompt($profile_data, $qualifiers, $archetype_templates, $cdt_dataset)
    {
        // Enhanced system prompt with role model focus and better structure
        $system_prompt = 'Role: You generate personalized "minimum viable experiments" (MVEs) for self-discovery.\n\nTask: Produce diverse, safe, low-stakes MVEs the user can try within 7 days.\n\nDesign Rules:\n1. **Ground in MI strengths** – leverage at least one top MI (explicitly note which)\n2. **Address CDT growth edges** – integrate small nudges for bottom 2 dimensions\n3. **Role models as inspiration** – draw on style/philosophy/methods of at least one role model. Make this influence explicit (e.g., "In the spirit of Marie Kondo, simplify...")\n4. **Calibrate to constraints** – align with time, budget, risk preferences. Do not exceed ±1 without explanation\n5. **Incorporate curiosities** – use at least one curiosity area in each experiment\n\nOutput JSON with fields:\n- archetype (Build, Explore, Express, Connect, Reflect)\n- title\n- rationale (why this fits MI/CDT/role model/constraints)\n- steps (3-5 concrete, runnable steps)\n- effort (timeHours, budgetUSD, riskLevel)\n- successCriteria (2-3)\n- influences (object with: miUsed, cdtEdge, roleModelUsed, curiosityUsed)\n- calibrationNotes (if adjustments made)\n\nConstraints: Language must be warm, concrete, non-judgmental. All experiments safe, legal, age-appropriate, low-risk. Return ONLY valid JSON.';

        // Get role models from qualifiers
        $role_models = [];
        if (isset($qualifiers['curiosity']['roleModels']) && is_array($qualifiers['curiosity']['roleModels'])) {
            $role_models = array_filter($qualifiers['curiosity']['roleModels']);
        }

        // Build comprehensive user message
        $user_message = sprintf(
            "Profile JSON: {\n  \"user\": {\n    \"mi_top3\": %s,\n    \"cdt_bottom2\": %s,\n    \"curiosities\": %s,\n    \"roleModels\": %s\n  },\n  \"constraints\": {\n    \"timePerWeek\": %d,\n    \"budget\": %d,\n    \"risk\": %d,\n    \"soloToGroup\": %d\n  }\n}\n\nGenerate 3 personalized MVEs as JSON.",
            json_encode(array_map(function ($mi) {
                return ['label' => $mi['label'], 'score' => $mi['score']];
            }, array_slice($profile_data['mi_top3'] ?: [], 0, 3))),
            json_encode(array_map(function ($cdt) {
                return ['label' => $cdt['label'], 'score' => $cdt['score']];
            }, array_slice($profile_data['cdt'] ?: [], -2))),
            json_encode($qualifiers['curiosity']['curiosities'] ?: []),
            json_encode($role_models),
            $qualifiers['curiosity']['constraints']['timePerWeekHours'] ?? 3,
            $qualifiers['curiosity']['constraints']['budget'] ?? 50,
            $qualifiers['curiosity']['constraints']['risk'] ?? 50,
            $qualifiers['curiosity']['constraints']['soloToGroup'] ?? 50
        );

        return [
            'system' => $system_prompt,
            'user' => $user_message
        ];
    }

    /**
     * Call OpenAI API with the generated prompt
     */
    private function call_openai_api($api_key, $prompt_data, $override_model = null)
    {
        // Use override model if provided (from admin), otherwise use default
        $model = $override_model ?: Micro_Coach_AI::get_selected_model();

        error_log('Lab Mode Debug - Using model: ' . $model . ($override_model ? ' (admin override)' : ' (default)'));

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt_data['system']],
                ['role' => 'user', 'content' => $prompt_data['user']]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            error_log('Lab Mode Debug - API request error: ' . $response->get_error_message());
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        error_log('Lab Mode Debug - API response status: ' . $status_code);

        $body = wp_remote_retrieve_body($response);
        error_log('Lab Mode Debug - API response body: ' . substr($body, 0, 1000) . '...');

        $data = json_decode($body, true);

        if (!$data) {
            throw new Exception('Failed to decode API response JSON');
        }

        if (isset($data['error'])) {
            throw new Exception('API error: ' . $data['error']['message']);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure. Keys: ' . implode(', ', array_keys($data)));
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Lab Mode Debug - AI content: ' . $content);

        $parsed_content = json_decode($content, true);
        if (!$parsed_content) {
            throw new Exception('Failed to decode AI response content as JSON: ' . $content);
        }

        return $parsed_content;
    }

    /**
     * AJAX: Start an experiment
     */
    public function ajax_start_experiment()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        $experiment_id = intval($_POST['experiment_id'] ?? 0);

        if (!$user_id || !$this->user_can_access_lab_mode() || !$experiment_id) {
            wp_send_json_error('Missing parameters or insufficient permissions');
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;

        $result = $wpdb->update($table, [
            'status' => 'Active',
            'updated_at' => current_time('mysql')
        ], [
            'id' => $experiment_id,
            'user_id' => $user_id
        ]);

        if ($result === false) {
            wp_send_json_error('Failed to update experiment status');
        }

        wp_send_json_success(['message' => 'Experiment started']);
    }

    /**
     * AJAX: Submit reflection feedback
     */
    public function ajax_submit_reflection()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            error_log('Lab Mode Reflection - Insufficient permissions for user ID: ' . $user_id);
            wp_send_json_error('Insufficient permissions');
        }

        $reflection_data = json_decode(stripslashes($_POST['reflection'] ?? ''), true);
        error_log('Lab Mode Reflection - Raw reflection data: ' . ($_POST['reflection'] ?? 'empty'));

        if (!$reflection_data) {
            error_log('Lab Mode Reflection - Failed to decode reflection data');
            wp_send_json_error('Invalid reflection data - could not parse JSON');
        }

        // Validate required fields
        $required_fields = ['experiment_id', 'difficulty', 'fit', 'learning', 'next_action'];
        $missing_fields = [];

        foreach ($required_fields as $field) {
            if (!isset($reflection_data[$field]) || $reflection_data[$field] === '') {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            error_log('Lab Mode Reflection - Missing required fields: ' . implode(', ', $missing_fields));
            wp_send_json_error('Missing required fields: ' . implode(', ', $missing_fields));
        }

        // Ensure tables exist
        $this->maybe_create_tables();

        // Save feedback to database
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LAB_FEEDBACK;

        $insert_data = [
            'experiment_id' => intval($reflection_data['experiment_id']),
            'user_id' => $user_id,
            'difficulty' => intval($reflection_data['difficulty']),
            'fit' => intval($reflection_data['fit']),
            'learning' => intval($reflection_data['learning']),
            'notes' => sanitize_textarea_field($reflection_data['notes'] ?? ''),
            'next_action' => sanitize_text_field($reflection_data['next_action']),
            'evolve_notes' => sanitize_textarea_field($reflection_data['evolve_notes'] ?? '')
        ];

        error_log('Lab Mode Reflection - Attempting to insert: ' . wp_json_encode($insert_data));

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            error_log('Lab Mode Reflection - Database insert failed. Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save reflection: ' . $wpdb->last_error);
        }

        error_log('Lab Mode Reflection - Successfully inserted feedback with ID: ' . $wpdb->insert_id);

        // Update experiment status
        $experiments_table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;
        $update_result = $wpdb->update($experiments_table, [
            'status' => 'Completed',
            'updated_at' => current_time('mysql')
        ], [
            'id' => intval($reflection_data['experiment_id']),
            'user_id' => $user_id
        ]);

        if ($update_result === false) {
            error_log('Lab Mode Reflection - Failed to update experiment status. Error: ' . $wpdb->last_error);
            // Don't fail the whole request for this
        }

        // Trigger recalibration
        try {
            $recalibration = $this->recalibrate_user_preferences($user_id, $reflection_data);
        } catch (Exception $e) {
            error_log('Lab Mode Reflection - Recalibration failed: ' . $e->getMessage());
            // Still return success since the reflection was saved
            $recalibration = [
                'summary' => 'Your feedback has been recorded. Recalibration will be applied to future experiments.',
                'risk_bias' => 0,
                'solo_group_bias' => 0,
                'contexts' => []
            ];
        }

        wp_send_json_success([
            'message' => 'Reflection submitted successfully',
            'recalibration' => $recalibration
        ]);
    }

    /**
     * Recalibrate user preferences based on feedback
     */
    private function recalibrate_user_preferences($user_id, $feedback)
    {
        // Ensure tables exist first
        $this->maybe_create_tables();

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LAB_USER_PREFERENCES;

        // Get current preferences
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE user_id = %d", $user_id), ARRAY_A);

        // Initialize with defaults if no preferences exist
        $contexts = [];
        if (!empty($current['contexts'])) {
            $decoded_contexts = json_decode($current['contexts'], true);
            $contexts = is_array($decoded_contexts) ? $decoded_contexts : [];
        }
        $risk_bias = floatval($current['risk_bias'] ?? 0);
        $solo_group_bias = floatval($current['solo_group_bias'] ?? 0);

        $changes = [];
        $old_risk_bias = $risk_bias;
        $old_solo_group_bias = $solo_group_bias;

        error_log("Lab Mode Recalibration - Starting values: risk_bias=$risk_bias, solo_group_bias=$solo_group_bias");

        // Apply comprehensive recalibration logic based on feedback

        // Difficulty-based adjustments
        if ($feedback['difficulty'] >= 5) {
            // Too difficult - reduce risk level
            $risk_bias = max(-1.0, $risk_bias - 0.3);
            $changes[] = 'reduced complexity due to high difficulty';
        } elseif ($feedback['difficulty'] <= 2) {
            // Too easy - increase risk level
            $risk_bias = min(1.0, $risk_bias + 0.2);
            $changes[] = 'increased complexity due to low difficulty';
        }

        // Fit-based adjustments
        if ($feedback['fit'] <= 2) {
            // Poor fit - adjust context preferences
            if ($solo_group_bias > 0) {
                $solo_group_bias = max(-1.0, $solo_group_bias - 0.2);
                $changes[] = 'adjusted towards group activities due to poor fit';
            } else {
                $solo_group_bias = min(1.0, $solo_group_bias + 0.2);
                $changes[] = 'adjusted towards solo activities due to poor fit';
            }
        } elseif ($feedback['fit'] >= 4) {
            // Great fit - reinforce current preferences
            if ($feedback['learning'] >= 4) {
                $changes[] = 'reinforced current preferences due to great fit and learning';
            }
        }

        // Learning-based adjustments
        if ($feedback['learning'] <= 2) {
            // Low learning - try different approach
            $solo_group_bias = -$solo_group_bias * 0.5; // Flip and reduce
            $changes[] = 'adjusted approach due to low learning';
        }

        // Sweet spot detection
        if ($feedback['learning'] >= 4 && $feedback['difficulty'] >= 3 && $feedback['difficulty'] <= 4 && $feedback['fit'] >= 4) {
            // Perfect zone - small reinforcement
            $risk_bias = $risk_bias * 1.1; // Slight amplification
            $risk_bias = max(-1.0, min(1.0, $risk_bias)); // Keep in bounds
            $changes[] = 'fine-tuned preferences (sweet spot detected)';
        }

        // Ensure values stay within bounds
        $risk_bias = max(-1.0, min(1.0, $risk_bias));
        $solo_group_bias = max(-1.0, min(1.0, $solo_group_bias));

        error_log("Lab Mode Recalibration - New values: risk_bias=$risk_bias, solo_group_bias=$solo_group_bias");

        // Save updated preferences
        $result = $wpdb->replace($table, [
            'user_id' => $user_id,
            'contexts' => wp_json_encode(empty($contexts) ? [] : $contexts),
            'risk_bias' => $risk_bias,
            'solo_group_bias' => $solo_group_bias
        ]);

        if ($result === false) {
            error_log('Lab Mode Recalibration - Failed to save preferences: ' . $wpdb->last_error);
        } else {
            error_log('Lab Mode Recalibration - Successfully saved preferences');
        }

        return [
            'contexts' => $contexts,
            'risk_bias' => round($risk_bias, 2),
            'solo_group_bias' => round($solo_group_bias, 2),
            'summary' => $this->generate_recalibration_summary($feedback, $changes, $old_risk_bias, $old_solo_group_bias, $risk_bias, $solo_group_bias)
        ];
    }

    /**
     * Generate human-readable recalibration summary
     */
    private function generate_recalibration_summary($feedback, $changes, $old_risk_bias, $old_solo_group_bias, $new_risk_bias, $new_solo_group_bias)
    {
        if (empty($changes)) {
            return "Your preferences are well-calibrated. We'll continue with similar experiments.";
        }

        $summary = "Based on your feedback, we " . implode(', ', $changes) . ".";

        // Add specific value changes if significant
        $risk_change = abs($new_risk_bias - $old_risk_bias);
        $group_change = abs($new_solo_group_bias - $old_solo_group_bias);

        if ($risk_change > 0.1 || $group_change > 0.1) {
            $summary .= " Your updated preference profile will guide future experiment selection.";
        }

        return $summary;
    }

    /**
     * AJAX: Get experiment history
     */
    public function ajax_get_history()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $experiments_table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;
        $feedback_table = $wpdb->prefix . self::TABLE_LAB_FEEDBACK;

        // Get experiments with feedback information
        $experiments = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, 
                    GROUP_CONCAT(
                        CONCAT('{\"difficulty\":', f.difficulty, ',\"fit\":', f.fit, ',\"learning\":', f.learning, ',\"notes\":\"', REPLACE(IFNULL(f.notes, ''), '\"', '\\\"'), '\"}') 
                        SEPARATOR '|'
                    ) as feedback_json
             FROM `$experiments_table` e 
             LEFT JOIN `$feedback_table` f ON e.id = f.experiment_id 
             WHERE e.user_id = %d 
             GROUP BY e.id 
             ORDER BY e.created_at DESC",
            $user_id
        ), ARRAY_A);

        $formatted_experiments = array_map(function ($exp) {
            $exp['experiment_data'] = json_decode($exp['experiment_data'], true);

            // Parse feedback data
            if (!empty($exp['feedback_json'])) {
                $feedback_strings = explode('|', $exp['feedback_json']);
                $exp['feedback'] = array_map(function ($feedback_str) {
                    return json_decode($feedback_str, true);
                }, $feedback_strings);
            } else {
                $exp['feedback'] = [];
            }
            unset($exp['feedback_json']);

            return $exp;
        }, $experiments);

        wp_send_json_success($formatted_experiments);
    }

    /**
     * AJAX: Get single experiment by ID
     */
    public function ajax_get_experiment()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $experiment_id = intval($_POST['experiment_id'] ?? 0);
        if (!$experiment_id) {
            wp_send_json_error('Invalid experiment ID');
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;

        $experiment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE id = %d AND user_id = %d",
            $experiment_id,
            $user_id
        ), ARRAY_A);

        if (!$experiment) {
            wp_send_json_error('Experiment not found');
        }

        $experiment['experiment_data'] = json_decode($experiment['experiment_data'], true);

        wp_send_json_success($experiment);
    }

    /**
     * AJAX: Save individual experiment to database
     */
    public function ajax_save_experiment()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            error_log('Lab Mode Save Experiment - Insufficient permissions for user ID: ' . $user_id);
            wp_send_json_error('Insufficient permissions');
        }

        $experiment_data = json_decode(stripslashes($_POST['experiment_data'] ?? ''), true);
        $archetype = sanitize_text_field($_POST['archetype'] ?? 'Discover');

        if (!$experiment_data) {
            error_log('Lab Mode Save Experiment - Invalid experiment data');
            wp_send_json_error('Invalid experiment data');
        }

        // Ensure tables exist
        $this->maybe_create_tables();

        // Get user profile data for context
        $profile_data = $this->get_user_profile_data($user_id);
        if (!$profile_data) {
            $profile_data = ['note' => 'Profile data not available at save time'];
        }

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LAB_EXPERIMENTS;

        $insert_data = [
            'user_id' => $user_id,
            'experiment_data' => wp_json_encode($experiment_data),
            'profile_data' => wp_json_encode($profile_data),
            'archetype' => $archetype,
            'status' => 'Active'
        ];

        error_log('Lab Mode Save Experiment - Attempting to insert: ' . wp_json_encode($insert_data));

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            error_log('Lab Mode Save Experiment - Database insert failed. Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save experiment: ' . $wpdb->last_error);
        }

        $experiment_id = $wpdb->insert_id;
        error_log('Lab Mode Save Experiment - Successfully saved with ID: ' . $experiment_id);

        wp_send_json_success([
            'experiment_id' => $experiment_id,
            'message' => 'Experiment saved successfully'
        ]);
    }

    /**
     * AJAX: Trigger recalibration
     */
    public function ajax_recalibrate()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get recent feedback to inform recalibration
        global $wpdb;
        $feedback_table = $wpdb->prefix . self::TABLE_LAB_FEEDBACK;

        $recent_feedback = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `$feedback_table` WHERE user_id = %d ORDER BY submitted_at DESC LIMIT 5",
            $user_id
        ), ARRAY_A);

        if (empty($recent_feedback)) {
            wp_send_json_error('No feedback available for recalibration');
        }

        // Generate evolved experiments based on feedback patterns
        try {
            $profile_data = $this->get_user_profile_data($user_id);
            $qualifiers = get_user_meta($user_id, 'mc_lab_qualifiers', true);

            // Adjust qualifiers based on feedback
            $adjusted_qualifiers = $this->adjust_qualifiers_based_on_feedback($qualifiers, $recent_feedback);

            $evolved_experiments = $this->generate_experiments_with_ai($profile_data, $adjusted_qualifiers);

            wp_send_json_success([
                'experiments' => $evolved_experiments,
                'message' => 'Generated evolved experiments based on your recent feedback'
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to generate evolved experiments: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Test save qualifiers with dummy data
     */
    public function ajax_test_save_qualifiers()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Create test qualifiers data
        $test_qualifiers = [
            'mi_qualifiers' => [
                [
                    'key' => 'linguistic',
                    'enjoy' => ['Writing', 'Speaking'],
                    'doing' => ['Journaling', 'Meetings']
                ]
            ],
            'cdt_qualifiers' => [
                [
                    'key' => 'risk-comfort',
                    'trippingPoints' => ['Uncertainty'],
                    'helps' => ['Planning']
                ]
            ],
            'curiosity' => [
                'curiosities' => ['Test topic'],
                'constraints' => ['risk' => 50]
            ]
        ];

        // Save test qualifiers
        $result = update_user_meta($user_id, 'mc_lab_qualifiers', $test_qualifiers);

        // Verify save
        $saved = get_user_meta($user_id, 'mc_lab_qualifiers', true);

        wp_send_json_success([
            'test_data' => $test_qualifiers,
            'save_result' => $result,
            'retrieved_data' => $saved,
            'message' => 'Test qualifiers save completed'
        ]);
    }

    /**
     * AJAX: Debug user data for troubleshooting
     */
    public function ajax_debug_user_data()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get all user assessment data
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);
        $qualifiers = get_user_meta($user_id, 'mc_lab_qualifiers', true);

        // Check quiz completion status
        $quizzes = Micro_Coach_Core::get_quizzes();
        $quiz_status = [];
        foreach ($quizzes as $quiz) {
            if (!empty($quiz['results_meta_key'])) {
                $results = get_user_meta($user_id, $quiz['results_meta_key'], true);
                $quiz_status[$quiz['name']] = [
                    'meta_key' => $quiz['results_meta_key'],
                    'completed' => !empty($results),
                    'data_preview' => !empty($results) ? array_keys((array) $results) : 'No data'
                ];
            }
        }

        wp_send_json_success([
            'user_id' => $user_id,
            'quiz_status' => $quiz_status,
            'mi_results' => !empty($mi_results) ? array_keys($mi_results) : 'Empty',
            'cdt_results' => !empty($cdt_results) ? array_keys($cdt_results) : 'Empty',
            'bartle_results' => !empty($bartle_results) ? array_keys($bartle_results) : 'Empty',
            'qualifiers' => !empty($qualifiers) ? array_keys($qualifiers) : 'Empty',
            'lab_mode_enabled' => $this->is_lab_mode_enabled(),
            'profile_data_result' => $this->get_user_profile_data($user_id) ? 'Valid' : 'Null'
        ]);
    }

    /**
     * Adjust user qualifiers based on feedback patterns
     */
    private function adjust_qualifiers_based_on_feedback($qualifiers, $feedback)
    {
        // Analyze feedback patterns
        $avg_difficulty = array_sum(array_column($feedback, 'difficulty')) / count($feedback);
        $avg_fit = array_sum(array_column($feedback, 'fit')) / count($feedback);

        // Adjust constraints based on patterns
        if (isset($qualifiers['constraints'])) {
            if ($avg_difficulty > 4) {
                // Reduce complexity
                $qualifiers['constraints']['risk'] = max(0, $qualifiers['constraints']['risk'] - 20);
                $qualifiers['constraints']['timePerWeekHours'] = max(1, $qualifiers['constraints']['timePerWeekHours'] - 1);
            }

            if ($avg_fit < 3) {
                // Adjust context preferences (would need more complex logic based on experiment contexts)
            }
        }

        return $qualifiers;
    }

    /**
     * AJAX: Generate AI-powered experiment variant
     */
    public function ajax_generate_ai_variant()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $original_experiment = json_decode(stripslashes($_POST['original_experiment'] ?? ''), true);
        $prompt_data = json_decode(stripslashes($_POST['prompt_data'] ?? ''), true);

        // Enhanced debugging
        error_log('Lab Mode AI Variant - Original experiment received: ' . (is_array($original_experiment) ? 'valid array' : 'invalid'));
        error_log('Lab Mode AI Variant - Prompt data received: ' . (is_array($prompt_data) ? 'valid array' : 'invalid'));

        if ($prompt_data) {
            error_log('Lab Mode AI Variant - Prompt system length: ' . strlen($prompt_data['system'] ?? ''));
            error_log('Lab Mode AI Variant - Prompt user length: ' . strlen($prompt_data['user'] ?? ''));
        }

        if (!$original_experiment || !$prompt_data) {
            $error_details = [];
            if (!$original_experiment)
                $error_details[] = 'missing original experiment';
            if (!$prompt_data)
                $error_details[] = 'missing prompt data';

            error_log('Lab Mode AI Variant - Validation failed: ' . implode(', ', $error_details));
            wp_send_json_error('Invalid data: ' . implode(', ', $error_details));
        }

        // Validate prompt data structure
        if (!isset($prompt_data['system']) || !isset($prompt_data['user'])) {
            error_log('Lab Mode AI Variant - Prompt data missing system or user fields');
            wp_send_json_error('Prompt data is missing required fields (system/user)');
        }

        // Check for empty prompts
        if (empty(trim($prompt_data['system'])) || empty(trim($prompt_data['user']))) {
            error_log('Lab Mode AI Variant - Empty prompt system or user content');
            wp_send_json_error('Prompt data contains empty system or user content');
        }

        try {
            // Use the AI to generate a variant
            $variant = $this->generate_ai_variant($prompt_data, $original_experiment);

            wp_send_json_success([
                'variant' => $variant,
                'source' => 'AI Generated Variant',
                'debug_info' => [
                    'prompt_system_length' => strlen($prompt_data['system']),
                    'prompt_user_length' => strlen($prompt_data['user']),
                    'original_title' => $original_experiment['title'] ?? 'No title'
                ]
            ]);

        } catch (Exception $e) {
            error_log('Lab Mode Debug - AI variant generation failed: ' . $e->getMessage());
            wp_send_json_error('AI variant generation failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Iterate on an experiment with a single modifier
     */
    public function ajax_iterate()
    {
        // Enhanced error logging
        error_log('Lab Mode Iterate - Request started by user: ' . get_current_user_id());

        try {
            check_ajax_referer('mc_lab_nonce', 'nonce');
        } catch (Exception $e) {
            error_log('Lab Mode Iterate - Nonce verification failed: ' . $e->getMessage());
            wp_send_json_error('Security verification failed. Please refresh the page and try again.');
        }

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            error_log('Lab Mode Iterate - Permission denied for user: ' . $user_id);
            wp_send_json_error('Insufficient permissions');
        }

        // Parse incoming data
        $current_experiment = json_decode(stripslashes($_POST['currentExperiment'] ?? ''), true);
        $modifier = json_decode(stripslashes($_POST['modifier'] ?? ''), true);
        $user_context = json_decode(stripslashes($_POST['userContext'] ?? ''), true);

        error_log('Lab Mode Iterate - Current experiment: ' . print_r($current_experiment, true));
        error_log('Lab Mode Iterate - Modifier: ' . print_r($modifier, true));
        error_log('Lab Mode Iterate - User context: ' . print_r($user_context, true));

        // Validate required data
        if (!$current_experiment || !$modifier || !$user_context) {
            $missing = [];
            if (!$current_experiment)
                $missing[] = 'current experiment';
            if (!$modifier)
                $missing[] = 'modifier';
            if (!$user_context)
                $missing[] = 'user context';

            wp_send_json_error('Missing required data: ' . implode(', ', $missing));
        }

        // Validate modifier structure
        if (!isset($modifier['kind']) || !isset($modifier['value'])) {
            error_log('Lab Mode Iterate - Invalid modifier structure: ' . print_r($modifier, true));
            wp_send_json_error('Invalid modifier structure - missing kind or value');
        }

        // Additional validation for custom modifiers
        if ($modifier['kind'] === 'Custom') {
            $custom_value = trim($modifier['value']);
            if (empty($custom_value)) {
                error_log('Lab Mode Iterate - Empty custom modifier value');
                wp_send_json_error('Custom modification request cannot be empty');
            }

            if (strlen($custom_value) < 10) {
                error_log('Lab Mode Iterate - Custom modifier too short: ' . strlen($custom_value) . ' characters');
                wp_send_json_error('Custom modification request is too short. Please provide more detail.');
            }

            if (strlen($custom_value) > 500) {
                error_log('Lab Mode Iterate - Custom modifier too long: ' . strlen($custom_value) . ' characters');
                wp_send_json_error('Custom modification request is too long. Please keep it under 500 characters.');
            }
        }

        // Check if debug info is requested
        $include_debug = !empty($_POST['includeDebug']);

        try {
            // Generate the revised experiment with optional debug info
            $result = $this->iterate_experiment($current_experiment, $modifier, $user_context, $include_debug);

            $revised_experiment = $result['experiment'];
            $debug_info = $result['debug'] ?? null;

            // Calculate changed fields for diff highlighting
            $changed_fields = $this->calculate_experiment_diff($current_experiment, $revised_experiment);

            // Extract calibration notes if available
            $calibration_notes = $revised_experiment['_calibrationNotes'] ?? null;
            unset($revised_experiment['_calibrationNotes']); // Remove internal field

            $response_data = [
                'experiment' => $revised_experiment,
                'calibrationNotes' => $calibration_notes,
                'changedFields' => $changed_fields
            ];

            // Include debug info if requested and available
            if ($include_debug && $debug_info) {
                $response_data['debug'] = $debug_info;
            }

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            error_log('Lab Mode Iterate - Failed: ' . $e->getMessage());
            wp_send_json_error('Failed to iterate experiment: ' . $e->getMessage());
        }
    }

    /**
     * Iterate an experiment with a single modifier using AI
     */
    private function iterate_experiment($current_experiment, $modifier, $user_context, $include_debug = false)
    {
        // Detect if experiment appears over-modified (heuristics)
        $complexity_indicators = [
            'title_length' => strlen($current_experiment['title'] ?? ''),
            'step_count' => count($current_experiment['steps'] ?? []),
            'avg_step_length' => 0,
            'description_repetition' => 0
        ];

        if (!empty($current_experiment['steps'])) {
            $total_step_length = array_sum(array_map('strlen', $current_experiment['steps']));
            $complexity_indicators['avg_step_length'] = $total_step_length / count($current_experiment['steps']);

            // Check for repetitive phrases (sign of additive modifications)
            $combined_text = implode(' ', $current_experiment['steps']) . ' ' . ($current_experiment['rationale'] ?? '');
            $complexity_indicators['description_repetition'] =
                substr_count(strtolower($combined_text), 'behavioral psychology') +
                substr_count(strtolower($combined_text), 'group setting') +
                substr_count(strtolower($combined_text), 'share') * 0.5;
        }

        $needs_cleanup = (
            $complexity_indicators['title_length'] > 60 ||
            $complexity_indicators['step_count'] > 5 ||
            $complexity_indicators['avg_step_length'] > 200 ||
            $complexity_indicators['description_repetition'] > 2
        );

        error_log('Lab Mode Iterate - Complexity analysis: ' . json_encode($complexity_indicators));
        error_log('Lab Mode Iterate - Needs cleanup: ' . ($needs_cleanup ? 'YES' : 'NO'));

        // Build system prompt for iteration with emphasis on integration
        $cleanup_emphasis = $needs_cleanup ?
            '\n\nIMPORTANT: This experiment appears to have been modified multiple times and needs SIGNIFICANT CLEANUP and SIMPLIFICATION. Focus on creating a clean, coherent experiment that incorporates the new modification while removing redundancy and complexity.' : '';

        $system_prompt = 'You are an AI coach specializing in elegant experiment refinement. Your goal is to INTEGRATE the requested change seamlessly into the existing experiment, not just add it on top.\n\nKey principles:\n- REFACTOR and STREAMLINE: If the experiment is getting complex, consolidate and simplify while incorporating the change\n- REPLACE rather than ADD: Instead of adding new elements, modify existing ones to incorporate the change\n- MAINTAIN COHERENCE: The final experiment should read as if designed from scratch, not like multiple modifications layered together\n- PRESERVE CORE INTENT: Keep the fundamental learning objective while elegantly weaving in the modification\n- CONCISE STEPS: Keep to 3-5 clear, actionable steps that flow naturally\n- TARGETED SUCCESS CRITERIA: 2-3 specific, measurable outcomes\n\nIf the experiment has become unwieldy from previous iterations, use this opportunity to clean it up while applying the modification.' . $cleanup_emphasis . '\n\nReturn clean, integrated JSON with: archetype, title, rationale, steps (array), effort (object with timeHours and budgetUSD), riskLevel, successCriteria (array), linkedMI (array), linkedCDT (array), and optionally _calibrationNotes explaining the integration approach.';

        // Build user prompt with current experiment and modifier
        $cleanup_instruction = $needs_cleanup ?
            "\n\nNOTE: Analysis suggests this experiment has become overly complex from previous modifications (title length: {$complexity_indicators['title_length']}, steps: {$complexity_indicators['step_count']}, repetition score: {$complexity_indicators['description_repetition']}). Please SIGNIFICANTLY SIMPLIFY and CONSOLIDATE while incorporating the new modification." : '';

        $user_prompt = sprintf(
            "Current Experiment to Refine: %s\n\nModification Request: %s\n\nUser Context: %s%s\n\nPlease INTEGRATE this modification into the experiment elegantly. If the experiment seems complex or repetitive from previous modifications, use this as an opportunity to streamline and refactor while incorporating the requested change. The result should feel cohesive and purposeful, not like a collection of add-ons.\n\nReturn the refined experiment as clean JSON.",
            json_encode($current_experiment, JSON_PRETTY_PRINT),
            json_encode($modifier, JSON_PRETTY_PRINT),
            json_encode($user_context, JSON_PRETTY_PRINT),
            $cleanup_instruction
        );

        error_log('Lab Mode Iterate - System prompt: ' . substr($system_prompt, 0, 200) . '...');
        error_log('Lab Mode Iterate - User prompt length: ' . strlen($user_prompt));

        // Call AI API
        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 800,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            throw new Exception('API returned status ' . $status_code . ': ' . ($error_data['error']['message'] ?? 'Unknown error'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Lab Mode Iterate - AI response: ' . $content);

        $revised_experiment = json_decode($content, true);
        if (!$revised_experiment) {
            throw new Exception('Failed to decode AI response as JSON: ' . $content);
        }

        // Validate required fields in response
        $required_fields = ['title', 'steps', 'successCriteria'];
        foreach ($required_fields as $field) {
            if (!isset($revised_experiment[$field])) {
                // Fill missing fields from original
                $revised_experiment[$field] = $current_experiment[$field] ?? null;
            }
        }

        // Ensure effort structure exists
        if (!isset($revised_experiment['effort']) || !is_array($revised_experiment['effort'])) {
            $revised_experiment['effort'] = $current_experiment['effort'] ?? ['timeHours' => 2, 'budgetUSD' => 0];
        }

        // Add calibration note if cleanup was performed
        if ($needs_cleanup && !isset($revised_experiment['_calibrationNotes'])) {
            $revised_experiment['_calibrationNotes'] = 'Streamlined and simplified this experiment to remove redundancy from previous modifications while integrating your requested change.';
        }

        // Prepare result with optional debug info
        $result = ['experiment' => $revised_experiment];

        if ($include_debug) {
            $result['debug'] = [
                'systemPrompt' => $system_prompt,
                'userPrompt' => $user_prompt,
                'model' => $model,
                'requestTime' => date('Y-m-d H:i:s'),
                'tokenUsage' => $data['usage'] ?? null
            ];
        }

        return $result;
    }

    /**
     * Calculate which fields changed between two experiments
     */
    private function calculate_experiment_diff($original, $revised)
    {
        $changed_fields = [];

        // Check top-level fields
        $fields_to_check = ['title', 'rationale', 'riskLevel', 'archetype'];
        foreach ($fields_to_check as $field) {
            if (($original[$field] ?? null) !== ($revised[$field] ?? null)) {
                $changed_fields[] = $field;
            }
        }

        // Check arrays (steps, successCriteria, etc.)
        $array_fields = ['steps', 'successCriteria', 'linkedMI', 'linkedCDT'];
        foreach ($array_fields as $field) {
            $orig_array = $original[$field] ?? [];
            $revised_array = $revised[$field] ?? [];
            if (json_encode($orig_array) !== json_encode($revised_array)) {
                $changed_fields[] = $field;
            }
        }

        // Check effort object
        $orig_effort = $original['effort'] ?? [];
        $revised_effort = $revised['effort'] ?? [];
        if (json_encode($orig_effort) !== json_encode($revised_effort)) {
            $changed_fields[] = 'effort';
        }

        return $changed_fields;
    }

    /**
     * Generate AI variant of an existing experiment
     */
    private function generate_ai_variant($prompt_data, $original_experiment)
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt_data['system']],
                ['role' => 'user', 'content' => $prompt_data['user']]
            ],
            'temperature' => 0.8, // Higher creativity for variants
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('API returned status: ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            throw new Exception('API error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        $variant = json_decode($content, true);

        if (!$variant) {
            throw new Exception('Failed to decode AI variant response as JSON');
        }

        // Ensure required fields are present
        if (!isset($variant['title']) || !isset($variant['steps']) || !isset($variant['successCriteria'])) {
            // If the AI response is not properly structured, return the original with modifications
            $variant = [
                ...$original_experiment,
                'title' => $variant['title'] ?? $original_experiment['title'] . ' (AI Variant)',
                'rationale' => $variant['rationale'] ?? 'An AI-generated variant of your original experiment.',
                'steps' => $variant['steps'] ?? $original_experiment['steps'],
                'successCriteria' => $variant['successCriteria'] ?? $original_experiment['successCriteria'],
                '_aiGenerated' => true
            ];
        }

        return $variant;
    }

    /**
     * AJAX: Analyze user's role models and generate adjacent suggestions
     */
    public function ajax_analyze_role_models()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Parse incoming data
        $user_role_models = json_decode(stripslashes($_POST['userRoleModels'] ?? '[]'), true);
        $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true);

        // Validate input
        if (empty($user_role_models) || !is_array($user_role_models)) {
            wp_send_json_error('Please provide at least one role model.');
        }

        if (empty($categories) || !is_array($categories)) {
            wp_send_json_error('Please select at least one category.');
        }

        // Filter out empty role models
        $user_role_models = array_filter($user_role_models, function ($model) {
            return !empty(trim($model));
        });

        if (count($user_role_models) < 2) {
            wp_send_json_error('Please provide at least 2 role models.');
        }

        if (count($user_role_models) > 3) {
            // Limit to first 3 for API efficiency
            $user_role_models = array_slice($user_role_models, 0, 3);
        }

        error_log('Lab Mode Role Models - User role models: ' . print_r($user_role_models, true));
        error_log('Lab Mode Role Models - Categories: ' . print_r($categories, true));

        try {
            $suggestions = $this->analyze_role_models_with_ai($user_role_models, $categories);

            wp_send_json_success([
                'suggestions' => $suggestions,
                'analyzed_models' => $user_role_models,
                'categories' => $categories
            ]);

        } catch (Exception $e) {
            error_log('Lab Mode Role Models - AI analysis failed: ' . $e->getMessage());
            wp_send_json_error('Failed to analyze role models: ' . $e->getMessage());
        }
    }

    /**
     * Analyze role models using AI to find adjacent suggestions
     */
    private function analyze_role_models_with_ai($user_role_models, $categories)
    {
        // Build system prompt for role model analysis
        $system_prompt = 'You are an assistant that expands on user-provided role models. Given a few role models and a broad admiration category, identify the themes and suggest adjacent role models with similar qualities. Provide output as JSON cards with name, category, and ≤20-word rationale.\n\nAnalyze the provided role models to identify common themes, values, and qualities. Then suggest 3-6 adjacent role models that share similar characteristics but offer different perspectives or approaches.\n\nOutput JSON with:\n{\n  "suggestions": [\n    {\n      "name": "Person Name",\n      "category": "Category from provided list",\n      "description": "Brief description (≤20 words) explaining why they might inspire the user"\n    }\n  ]\n}\n\nEnsure all suggestions are real people who are publicly known and have made positive contributions.';

        // Build user prompt with role models and categories
        $user_prompt = json_encode([
            'userRoleModels' => $user_role_models,
            'categories' => $categories
        ], JSON_PRETTY_PRINT);

        error_log('Lab Mode Role Models - System prompt length: ' . strlen($system_prompt));
        error_log('Lab Mode Role Models - User prompt: ' . $user_prompt);

        // Call AI API
        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.8, // Higher creativity for discovering new role models
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            throw new Exception('API returned status ' . $status_code . ': ' . ($error_data['error']['message'] ?? 'Unknown error'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Lab Mode Role Models - AI response: ' . $content);

        $analysis = json_decode($content, true);
        if (!$analysis) {
            throw new Exception('Failed to decode AI response as JSON: ' . $content);
        }

        if (!isset($analysis['suggestions']) || !is_array($analysis['suggestions'])) {
            throw new Exception('AI response missing suggestions field or invalid format');
        }

        // Validate and clean suggestions
        $cleaned_suggestions = [];
        foreach ($analysis['suggestions'] as $suggestion) {
            if (!isset($suggestion['name']) || !isset($suggestion['category']) || !isset($suggestion['description'])) {
                continue; // Skip invalid suggestions
            }

            // Ensure description is not too long
            $description = trim($suggestion['description']);
            $words = str_word_count($description);
            if ($words > 25) {
                // Truncate if too long
                $words_array = explode(' ', $description);
                $description = implode(' ', array_slice($words_array, 0, 20)) . '...';
            }

            $cleaned_suggestions[] = [
                'name' => sanitize_text_field(trim($suggestion['name'])),
                'category' => sanitize_text_field(trim($suggestion['category'])),
                'description' => sanitize_text_field($description)
            ];
        }

        if (empty($cleaned_suggestions)) {
            throw new Exception('No valid suggestions were generated');
        }

        // Limit to 6 suggestions max
        if (count($cleaned_suggestions) > 6) {
            $cleaned_suggestions = array_slice($cleaned_suggestions, 0, 6);
        }

        error_log('Lab Mode Role Models - Cleaned suggestions count: ' . count($cleaned_suggestions));

        return $cleaned_suggestions;
    }

    /**
     * AJAX: Generate Career Mind Map
     */
    public function ajax_generate_career_map()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $career_interest = sanitize_text_field($_POST['career_interest'] ?? '');
        $prompt_level = sanitize_text_field($_POST['prompt_level'] ?? 'minimal');
        $prompt_templates_json = $_POST['prompt_templates'] ?? '';
        $prompt_templates = json_decode(stripslashes($prompt_templates_json), true);

        if (empty(trim($career_interest))) {
            wp_send_json_error('Please enter a career or field to explore');
        }

        // Get user profile data
        $profile_data = $this->get_career_explorer_profile($user_id);

        if (!$profile_data) {
            wp_send_json_error('Unable to load your profile data. Please ensure all assessments are complete.');
        }

        error_log('Career Explorer - Career interest: ' . $career_interest);
        error_log('Career Explorer - Prompt level: ' . $prompt_level);
        error_log('Career Explorer - Profile data loaded: ' . print_r($profile_data, true));

        try {
            $career_map = $this->generate_career_map_with_ai($career_interest, $profile_data, $prompt_level, $prompt_templates);

            wp_send_json_success([
                'career_map' => $career_map,
                'career_interest' => $career_interest
            ]);

        } catch (Exception $e) {
            error_log('Career Explorer - AI generation failed: ' . $e->getMessage());
            wp_send_json_error('Failed to generate career map: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile data formatted for Career Explorer
     */
    private function get_career_explorer_profile($user_id)
    {
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

        if (empty($mi_results) || empty($cdt_results) || empty($bartle_results)) {
            return null;
        }

        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';

        // Format MI top 3
        $mi_top3 = [];
        if (!empty($mi_results['top3'])) {
            foreach ($mi_results['top3'] as $mi_slug) {
                $score = $mi_results['part1Scores'][$mi_slug] ?? 0;
                $mi_top3[] = [
                    'key' => $mi_slug,
                    'label' => $mi_categories[$mi_slug] ?? ucfirst(str_replace('-', ' ', $mi_slug)),
                    'score' => (int) $score
                ];
            }
        }

        // Format CDT scores
        $cdt_scores = [];
        $cdt_top = null;
        $cdt_edge = null;
        if (!empty($cdt_results['sortedScores'])) {
            foreach ($cdt_results['sortedScores'] as $index => $score_data) {
                $cdt_slug = $score_data[0];
                $score = $score_data[1];
                $cdt_scores[$cdt_slug] = (int) $score;

                if ($index === 0) {
                    $cdt_top = $cdt_categories[$cdt_slug] ?? ucfirst(str_replace('-', ' ', $cdt_slug));
                }
                if ($index === count($cdt_results['sortedScores']) - 1) {
                    $cdt_edge = $cdt_categories[$cdt_slug] ?? ucfirst(str_replace('-', ' ', $cdt_slug));
                }
            }
        }

        // Format Bartle player type
        $player_type = [];
        if (!empty($bartle_results['sortedScores'])) {
            $player_type['primary'] = $bartle_categories[$bartle_results['sortedScores'][0][0]] ?? 'Unknown';
            if (isset($bartle_results['sortedScores'][1])) {
                $player_type['secondary'] = $bartle_categories[$bartle_results['sortedScores'][1][0]] ?? null;
            }
        }

        // Get Johari adjectives (if available)
        $johari_adjectives = [];
        $jmi_results = get_user_meta($user_id, 'johari_mi_quiz_results', true);
        if (!empty($jmi_results['selected_adjectives'])) {
            $johari_adjectives = $jmi_results['selected_adjectives'];
        }

        // Get age group if available
        $age_group = 'Adult'; // Default
        $qualifiers = get_user_meta($user_id, 'mc_lab_qualifiers', true);
        if (!empty($qualifiers['curiosity']['ageGroup'])) {
            $age_group = $qualifiers['curiosity']['ageGroup'];
        }

        return [
            'mi_top3' => $mi_top3,
            'cdt_scores' => $cdt_scores,
            'cdt_top' => $cdt_top,
            'cdt_edge' => $cdt_edge,
            'player_type' => $player_type,
            'johari_adjectives' => $johari_adjectives,
            'age_group' => $age_group
        ];
    }

    /**
     * Generate Career Mind Map using AI
     */
    private function generate_career_map_with_ai($career_interest, $user_profile, $prompt_level = 'minimal', $prompt_templates = null)
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Build system prompt
        $system_prompt = 'You are a career advisor AI that helps users explore careers aligned with their psychological profile.\n\nYou will receive:\n- A career they\'re curious about\n- Their MI (Multiple Intelligences) top 3\n- Their CDT (Cognitive Dissonance Tolerance) scores and edges\n- Their Bartle Player Type (motivation style)\n- Johari window adjectives (self-perception traits)\n- Age group\n\nYou must return a SINGLE JSON object with this structure:\n{\n  "career_map": {\n    "central_career": "<the career they entered>",\n    "adjacent": [\n      {\n        "title": "Career Title",\n        "why_it_fits": "Brief explanation",\n        "profile_match": {\n          "mi": ["relevant MI"],\n          "cdt": "relevant CDT trait",\n          "bartle": "relevant player type",\n          "johari": ["relevant adjectives"]\n        }\n      }\n    ],\n    "parallel": [same structure as adjacent],\n    "wildcard": [same structure as adjacent]\n  }\n}\n\nRULES:\n- Adjacent careers: 3-5 careers very similar to input, easy transitions, low skill change\n- Parallel careers: 3-5 careers with similar core strengths but different industries\n- Wildcard careers: 2-3 unexpected careers based purely on user profile (MI/CDT/Bartle/Johari), not the input career\n- All careers must be real, legal, accessible\n- Tailor to age group (Teen/Student/Adult)\n- For wildcards, deeply leverage user\'s unique profile combination\n- NO prose, NO markdown, ONLY valid JSON';

        // Prepare profile data for template substitution
        $mi_summary = implode(', ', array_map(function ($mi) {
            return $mi['label'];
        }, $user_profile['mi_top3']));
        $mi_detailed = implode(', ', array_map(function ($mi) {
            return $mi['label'] . ' (' . $mi['score'] . '%)';
        }, $user_profile['mi_top3']));
        $johari_summary = !empty($user_profile['johari_adjectives']) ? implode(', ', $user_profile['johari_adjectives']) : 'Not available';
        $cdt_full = !empty($user_profile['cdt_scores']) ? implode(', ', array_map(function ($key, $val) {
            return ucfirst(str_replace('-', ' ', $key)) . ': ' . $val . '%';
        }, array_keys($user_profile['cdt_scores']), $user_profile['cdt_scores'])) : $user_profile['cdt_top'];
        $bartle_detailed = $user_profile['player_type']['primary'] . (!empty($user_profile['player_type']['secondary']) ? ' (Primary), ' . $user_profile['player_type']['secondary'] . ' (Secondary)' : '');

        // Check if custom template exists for this level
        $custom_template = '';
        if ($prompt_templates && isset($prompt_templates[$prompt_level]) && !empty($prompt_templates[$prompt_level])) {
            $custom_template = $prompt_templates[$prompt_level];

            // Validate template length (warn if extremely long)
            if (strlen($custom_template) > 5000) {
                error_log('Career Explorer - WARNING: Custom prompt template is very long (' . strlen($custom_template) . ' chars). This may cause timeouts.');
            }
        }

        // Build user prompt - use custom template if provided, otherwise use default
        if (!empty($custom_template)) {
            // Replace variables in custom template
            $user_prompt = str_replace(
                ['{career}', '{mi_top3}', '{mi_top3_detailed}', '{cdt_top}', '{cdt_edge}', '{cdt_full}', '{bartle}', '{bartle_detailed}', '{johari}', '{filters}'],
                [$career_interest, $mi_summary, $mi_detailed, $user_profile['cdt_top'], $user_profile['cdt_edge'], $cdt_full, $user_profile['player_type']['primary'], $bartle_detailed, $johari_summary, 'N/A'],
                $custom_template
            );
            error_log('Career Explorer - Using custom template for level: ' . $prompt_level);
        } else {
            // Default prompts based on verbosity level
            if ($prompt_level === 'maximum') {
                $user_prompt = sprintf(
                    "Career Interest: %s\n\nComprehensive User Profile:\n\nMultiple Intelligences (Top 3 with detailed scores):\n%s\n\nCognitive Dissonance Tolerance:\n- Highest Strength: %s (comfort zone)\n- Growth Edge: %s (development area)\n- Full CDT Profile: %s\n\nBartle Player Type:\n%s\n\nJohari Window Adjectives: %s\n\nAge Group: %s\n\nGenerate deeply personalized career suggestions that:\n1. Leverage their unique MI combination in specific ways\n2. Account for their cognitive comfort zones and stretch areas\n3. Match their intrinsic motivation patterns (Bartle type)\n4. Consider their self-perception traits (Johari)\n5. Provide detailed explanations for each career match\n\nFor each career, explain:\n- How it activates specific intelligences\n- How it relates to their CDT profile\n- Connection to their motivation style\n- Why it fits their self-perception",
                    $career_interest,
                    $mi_detailed,
                    $user_profile['cdt_top'],
                    $user_profile['cdt_edge'],
                    $cdt_full,
                    $bartle_detailed,
                    $johari_summary,
                    $user_profile['age_group']
                );
            } elseif ($prompt_level === 'medium') {
                $user_prompt = sprintf(
                    "Career Interest: %s\n\nUser Profile:\n- Top 3 Multiple Intelligences: %s\n- CDT Strength: %s (comfort with uncertainty)\n- CDT Growth Edge: %s (development area)\n- Bartle Player Type: %s\n- Johari Adjectives: %s\n- Age Group: %s\n\nGenerate personalized career suggestions that leverage their MI strengths, consider their CDT profile, and align with their motivation style (Bartle type).\n\nProvide adjacent, parallel, and wildcard career options with brief explanations of why each fits their profile.",
                    $career_interest,
                    $mi_summary,
                    $user_profile['cdt_top'],
                    $user_profile['cdt_edge'],
                    $bartle_detailed,
                    $johari_summary,
                    $user_profile['age_group']
                );
            } else {
                // Minimal
                $user_prompt = sprintf(
                    "Career Interest: %s\n\nUser: MI=%s, CDT=%s, Bartle=%s\n\nGenerate career suggestions (adjacent, parallel, wildcard) based on profile.",
                    $career_interest,
                    $mi_summary,
                    $user_profile['cdt_top'],
                    $user_profile['player_type']['primary']
                );
            }
        }

        error_log('Career Explorer - System prompt length: ' . strlen($system_prompt));
        error_log('Career Explorer - User prompt: ' . $user_prompt);

        // Call OpenAI API
        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 180,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('Career Explorer - API Error: ' . $error_msg);

            // If timeout, provide helpful message
            if (strpos($error_msg, 'timed out') !== false || strpos($error_msg, 'timeout') !== false) {
                throw new Exception('API request timed out. Try using "Minimal" prompt level in admin settings to reduce response time.');
            }

            throw new Exception('API request failed: ' . $error_msg);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            throw new Exception('API returned status ' . $status_code . ': ' . ($error_data['error']['message'] ?? 'Unknown error'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Career Explorer - AI response: ' . $content);

        $career_map = json_decode($content, true);

        if (!$career_map || !isset($career_map['career_map'])) {
            throw new Exception('Failed to decode career map JSON or missing career_map field');
        }

        // Validate structure
        $required_sections = ['adjacent', 'parallel', 'wildcard'];
        foreach ($required_sections as $section) {
            if (!isset($career_map['career_map'][$section])) {
                $career_map['career_map'][$section] = [];
            }
        }

        return $career_map['career_map'];
    }

    /**
     * AJAX: Handle career feedback (replacements, save, explain)
     */
    public function ajax_career_feedback()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $feedback_action = sanitize_text_field($_POST['feedback_action'] ?? '');
        $career_rejected = sanitize_text_field($_POST['career_rejected'] ?? '');
        $central_career = sanitize_text_field($_POST['central_career'] ?? '');
        $distance_group = sanitize_text_field($_POST['distance_group'] ?? 'adjacent');

        error_log('Career Feedback - Action: ' . $feedback_action);
        error_log('Career Feedback - Rejected: ' . $career_rejected);
        error_log('Career Feedback - Central: ' . $central_career);
        error_log('Career Feedback - Distance: ' . $distance_group);

        try {
            switch ($feedback_action) {
                case 'save':
                    $career_data = json_decode(stripslashes($_POST['career_data'] ?? ''), true);
                    $saved = $this->save_career_favorite($user_id, $career_data);
                    wp_send_json_success(['saved' => $saved]);
                    break;

                case 'explain_fit':
                    $explanation = $this->generate_career_explanation($user_id, $career_rejected, $central_career, $distance_group);
                    wp_send_json_success(['explanation' => $explanation]);
                    break;

                case 'too_similar':
                case 'too_different':
                case 'not_interested':
                    $profile_data = $this->get_career_explorer_profile($user_id);
                    $replacement = $this->generate_career_replacement($profile_data, $central_career, $career_rejected, $distance_group, $feedback_action);
                    wp_send_json_success(['replacement' => $replacement]);
                    break;

                default:
                    wp_send_json_error('Invalid feedback action');
            }
        } catch (Exception $e) {
            error_log('Career Feedback - Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save career to user favorites
     */
    private function save_career_favorite($user_id, $career_data)
    {
        // Get existing saved careers
        $saved_careers = get_user_meta($user_id, 'mc_saved_careers', true);
        if (!is_array($saved_careers)) {
            $saved_careers = [];
        }

        // Add timestamp and save
        $career_data['saved_at'] = current_time('mysql');
        $saved_careers[] = $career_data;

        // Keep only last 50 saved careers
        if (count($saved_careers) > 50) {
            $saved_careers = array_slice($saved_careers, -50);
        }

        update_user_meta($user_id, 'mc_saved_careers', $saved_careers);

        error_log('Career saved: ' . $career_data['title']);
        return true;
    }

    /**
     * AJAX: Get saved careers for current user
     */
    public function ajax_get_saved_careers()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $saved_careers = get_user_meta($user_id, 'mc_saved_careers', true);
        if (!is_array($saved_careers)) {
            $saved_careers = [];
        }

        // Sort by saved_at descending (newest first)
        usort($saved_careers, function ($a, $b) {
            return strcmp($b['saved_at'] ?? '', $a['saved_at'] ?? '');
        });

        wp_send_json_success([
            'careers' => $saved_careers,
            'count' => count($saved_careers)
        ]);
    }

    /**
     * AJAX: Delete a saved career by index
     */
    public function ajax_delete_saved_career()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        $index = intval($_POST['index'] ?? -1);
        if ($index < 0) {
            wp_send_json_error('Invalid index');
        }

        $saved_careers = get_user_meta($user_id, 'mc_saved_careers', true);
        if (!is_array($saved_careers)) {
            wp_send_json_error('No saved careers found');
        }

        // Sort by saved_at descending to match display order
        usort($saved_careers, function ($a, $b) {
            return strcmp($b['saved_at'] ?? '', $a['saved_at'] ?? '');
        });

        if (!isset($saved_careers[$index])) {
            wp_send_json_error('Career not found at index: ' . $index);
        }

        $deleted_title = $saved_careers[$index]['title'] ?? 'Unknown';

        // Remove the career at the specified index
        array_splice($saved_careers, $index, 1);

        // Update user meta
        update_user_meta($user_id, 'mc_saved_careers', $saved_careers);

        error_log('Career deleted: ' . $deleted_title . ' (index: ' . $index . ')');

        wp_send_json_success([
            'deleted' => true,
            'remaining_count' => count($saved_careers)
        ]);
    }

    /**
     * Generate explanation for why a career fits
     */
    private function generate_career_explanation($user_id, $career_title, $central_career, $distance_group)
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        $profile_data = $this->get_career_explorer_profile($user_id);
        if (!$profile_data) {
            throw new Exception('Unable to load profile data');
        }

        // Build explanation prompt
        $mi_summary = implode(', ', array_map(function ($mi) {
            return $mi['label'];
        }, $profile_data['mi_top3']));
        $johari_summary = !empty($profile_data['johari_adjectives']) ? implode(', ', $profile_data['johari_adjectives']) : 'Not available';

        // No need for random angles anymore - we're doing two sections

        $system_prompt = 'You are a career advisor. Generate TWO separate sections:\n\nSECTION 1 - Profile Fit (2-3 sentences):\nExplain WHY this career aligns with their specific MI strengths, CDT traits, and Bartle type. Be concrete about how their profile makes them naturally suited to this work.\n\nSECTION 2 - A Typical Day (2-3 sentences):\nDescribe WHAT a typical workday looks like - specific activities, interactions, and tasks they\'d actually do. Make it vivid and tangible.\n\nRULES:\n- NEVER use: "will allow you to", "will shine", "make you uniquely equipped", "suggests you will thrive", "means you can", "will empower you"\n- Use concrete, specific language\n- Write conversationally\n\nReturn as JSON: {"profile_fit": "...", "typical_day": "..."}';

        $user_prompt = sprintf(
            "Career: %s\nProfile: %s MI, %s CDT strength, %s growth edge, %s type\nContext: %s path from %s\n\nGenerate both sections.",
            $career_title,
            $mi_summary,
            $profile_data['cdt_top'],
            $profile_data['cdt_edge'],
            $profile_data['player_type']['primary'],
            $distance_group,
            $central_career
        );

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.8,
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('API returned status: ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Career Explanation - Raw AI response: ' . $content);

        $sections = json_decode($content, true);
        error_log('Career Explanation - Decoded sections: ' . print_r($sections, true));

        if (!$sections || !isset($sections['profile_fit']) || !isset($sections['typical_day'])) {
            error_log('Career Explanation - Missing required fields. Sections: ' . print_r($sections, true));
            throw new Exception('Invalid explanation format - missing profile_fit or typical_day');
        }

        return $sections;
    }

    /**
     * Generate career replacement based on feedback
     */
    private function generate_career_replacement($profile_data, $central_career, $rejected_career, $distance_group, $feedback_action)
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Build system prompt with feedback-specific instructions
        $distance_instruction = '';
        if ($feedback_action === 'too_similar') {
            $distance_instruction = 'The user found "%s" too similar to "%s". Suggest a career that is FARTHER away - more different, requiring more skill transition, in a different domain.';
        } elseif ($feedback_action === 'too_different') {
            $distance_instruction = 'The user found "%s" too different from "%s". Suggest a career that is CLOSER - more similar, easier transition, related skills.';
        } else { // not_interested
            $distance_instruction = 'The user is not interested in "%s". Suggest a different %s career (same distance from "%s") that might appeal to them based on their profile.';
        }

        $distance_instruction = sprintf($distance_instruction, $rejected_career, $central_career, $distance_group, $central_career);

        $system_prompt = 'You are a career advisor providing alternative career suggestions based on user feedback. ' . $distance_instruction . '\n\nReturn ONLY valid JSON in this format:\n{\n  "title": "Career Title",\n  "why_it_fits": "Brief explanation",\n  "profile_match": {\n    "mi": ["relevant MI"],\n    "cdt": "relevant CDT",\n    "bartle": "relevant Bartle",\n    "johari": ["relevant adjectives"]\n  }\n}\n\nThe career must be real, legal, accessible, and appropriately different/similar based on the feedback.';

        // Build user prompt
        $mi_summary = implode(', ', array_map(function ($mi) {
            return $mi['label'];
        }, $profile_data['mi_top3']));
        $johari_summary = !empty($profile_data['johari_adjectives']) ? implode(', ', $profile_data['johari_adjectives']) : 'Not available';

        $user_prompt = sprintf(
            "Central Career: %s\nRejected Career: %s\nFeedback: %s\n\nUser Profile:\n- MI Top 3: %s\n- CDT Highest: %s\n- CDT Edge: %s\n- Bartle Type: %s\n- Johari Adjectives: %s\n\nProvide one replacement career that addresses the feedback.",
            $central_career,
            $rejected_career,
            $feedback_action,
            $mi_summary,
            $profile_data['cdt_top'],
            $profile_data['cdt_edge'],
            $profile_data['player_type']['primary'],
            $johari_summary
        );

        error_log('Career Replacement - System prompt: ' . substr($system_prompt, 0, 200));
        error_log('Career Replacement - User prompt: ' . $user_prompt);

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.8,
            'max_tokens' => 400,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('API returned status: ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Career Replacement - AI response: ' . $content);

        $replacement = json_decode($content, true);

        if (!$replacement || !isset($replacement['title'])) {
            throw new Exception('Failed to decode replacement career JSON');
        }

        return $replacement;
    }

    /**
     * AJAX: Career Suggest with Filters (New Enhanced Endpoint)
     */
    public function ajax_career_suggest()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Parse request payload
        $seed_career = sanitize_text_field($_POST['seed_career'] ?? '');
        $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true);
        $novelty_bias = floatval($_POST['novelty_bias'] ?? 0.25);
        $limit_per_bucket = intval($_POST['limit_per_bucket'] ?? 6);

        // Get user profile data
        $profile_data = $this->get_enhanced_career_profile($user_id);

        if (!$profile_data) {
            wp_send_json_error('Unable to load your profile data. Please ensure all assessments are complete.');
        }

        error_log('Career Suggest - Seed: ' . $seed_career . ', Novelty: ' . $novelty_bias);
        error_log('Career Suggest - Filters: ' . print_r($filters, true));

        try {
            $suggestions = $this->generate_career_suggestions_with_filters(
                $seed_career,
                $profile_data,
                $filters,
                $novelty_bias,
                $limit_per_bucket
            );

            wp_send_json_success([
                'adjacent' => $suggestions['adjacent'] ?? [],
                'parallel' => $suggestions['parallel'] ?? [],
                'wildcard' => $suggestions['wildcard'] ?? []
            ]);

        } catch (Exception $e) {
            error_log('Career Suggest - AI generation failed: ' . $e->getMessage());
            wp_send_json_error('Failed to generate career suggestions: ' . $e->getMessage());
        }
    }

    /**
     * Get enhanced user profile data for Career Explorer with filters
     */
    private function get_enhanced_career_profile($user_id)
    {
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

        if (empty($mi_results) || empty($cdt_results) || empty($bartle_results)) {
            return null;
        }

        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/mi-quiz/mi-questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/cdt-quiz/questions.php';
        require_once MC_QUIZ_PLATFORM_PATH . 'quizzes/bartle-quiz/questions.php';

        // Format MI top 3 with slugs
        $mi_top3 = [];
        if (!empty($mi_results['top3'])) {
            foreach ($mi_results['top3'] as $mi_slug) {
                $score = $mi_results['part1Scores'][$mi_slug] ?? 0;
                $mi_top3[] = [
                    'slug' => $mi_slug,
                    'score' => (int) $score
                ];
            }
        }

        // Format CDT scores with snake_case slugs
        $cdt_scores = [];
        $cdt_top = null;
        $cdt_edge = null;

        // Map display names to snake_case
        $cdt_slug_map = [
            'discomfort-regulation' => 'discomfort_regulation',
            'conflict-resolution-tolerance' => 'conflict_resolution_tolerance',
            'value-conflict-navigation' => 'value_conflict_navigation',
            'ambiguity-tolerance' => 'ambiguity_tolerance',
            'self-confrontation-capacity' => 'self_confrontation_capacity'
        ];

        if (!empty($cdt_results['sortedScores'])) {
            foreach ($cdt_results['sortedScores'] as $index => $score_data) {
                $cdt_slug = $score_data[0];
                $score = $score_data[1];
                $snake_slug = $cdt_slug_map[$cdt_slug] ?? str_replace('-', '_', $cdt_slug);
                $cdt_scores[$snake_slug] = (int) $score;

                if ($index === 0) {
                    $cdt_top = $snake_slug;
                }
                if ($index === count($cdt_results['sortedScores']) - 1) {
                    $cdt_edge = $snake_slug;
                }
            }
        }

        // Format Bartle player type
        $bartle_primary = null;
        $bartle_secondary = null;
        if (!empty($bartle_results['sortedScores'])) {
            $bartle_primary = strtolower($bartle_results['sortedScores'][0][0]);
            if (isset($bartle_results['sortedScores'][1])) {
                $bartle_secondary = strtolower($bartle_results['sortedScores'][1][0]);
            }
        }

        // Get Johari adjectives (if available)
        $johari_adjectives = [];
        $jmi_results = get_user_meta($user_id, 'johari_mi_quiz_results', true);
        if (!empty($jmi_results['selected_adjectives'])) {
            $johari_adjectives = array_map('strtolower', $jmi_results['selected_adjectives']);
        }

        return [
            'mi_top3' => $mi_top3,
            'cdt_scores' => $cdt_scores,
            'cdt_top' => $cdt_top,
            'cdt_edge' => $cdt_edge,
            'bartle' => [
                'primary' => $bartle_primary,
                'secondary' => $bartle_secondary
            ],
            'johari' => $johari_adjectives
        ];
    }

    /**
     * Generate career suggestions with filters using AI
     */
    private function generate_career_suggestions_with_filters($seed_career, $profile, $filters, $novelty_bias, $limit)
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Build comprehensive system prompt per spec
        $system_prompt = $this->build_career_suggest_system_prompt();

        // Build user prompt with profile and filters
        $user_prompt = $this->build_career_suggest_user_prompt($seed_career, $profile, $filters, $novelty_bias, $limit);

        error_log('Career Suggest - System prompt length: ' . strlen($system_prompt));
        error_log('Career Suggest - User prompt: ' . substr($user_prompt, 0, 500));

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.7 + ($novelty_bias * 0.3), // Increase temp with novelty
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            throw new Exception('API returned status ' . $status_code . ': ' . ($error_data['error']['message'] ?? 'Unknown error'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        error_log('Career Suggest - AI response length: ' . strlen($content));

        $result = json_decode($content, true);

        if (!$result || (!isset($result['adjacent']) && !isset($result['parallel']) && !isset($result['wildcard']))) {
            throw new Exception('Failed to decode career suggestions JSON or missing required sections');
        }

        // Ensure all sections exist
        $result['adjacent'] = $result['adjacent'] ?? [];
        $result['parallel'] = $result['parallel'] ?? [];
        $result['wildcard'] = $result['wildcard'] ?? [];

        return $result;
    }

    /**
     * Build system prompt for career suggestions
     */
    private function build_career_suggest_system_prompt()
    {
        return 'You generate career suggestions tailored to a user\'s psychological profile:
- Multiple Intelligences (top 3 + scores)
- Cognitive Dissonance Tolerance (subscales + top + edge)
- Bartle player type (primary, optional secondary)
- Johari adjective descriptors

You must:
1) Return ONLY JSON with three arrays: {"adjacent":[...], "parallel":[...], "wildcard":[...]}.
2) Map each idea to the profile with a brief, concrete "why it fits."
3) Respect filters:
   - demand_horizon: trending_now | high_growth_5y | future_proof_10y | stable_low_vol | automation_resistant
   - education_levels: no_degree | certificate_bootcamp | bachelor | advanced
   - work_env: remote_friendly | hybrid | outdoor | hands_on | solo | collaborative | client_facing | structured | flexible
   - role_orientation: analytical | creative | leadership | technical | people_centered | helping | problem_solving | adventure_fieldwork
   - comp_band: lower | middle | upper | high_responsibility
   - social_impact: high_social | environmental | community | mission_driven
   - remote_only: boolean
   - stretch_opposites: boolean (include 20–40% "opposite" style fits in wildcard)
4) Calibrate novelty:
   - novelty_bias ∈ [0,1]; 0 = conventional, 1 = very unusual (affects wildcard most).
5) Distribution:
   - adjacent: easy pivots, similar skill cluster
   - parallel: same strengths, different domain/industry
   - wildcard: surprising but genuinely profile-consistent
6) Each item object MUST include:
   - title (string)
   - why_it_fits (<= 200 chars)
   - profile_match: { mi: string[], cdt_top: string, bartle: string, johari: string[] }
   - meta: { demand_horizon: string, education: string, work_env: string[], comp_band: string, social_impact: string[] }
7) Obey filters strictly. If a filter cannot be satisfied, reduce count but never hallucinate prerequisites.
8) Safety: exclude unsafe, illegal, or age-inappropriate roles. Prefer accessible first steps.
9) If seed_career is empty, infer from MI/CDT/Bartle to populate all three buckets.
10) No prose, markdown, or comments. JSON only.';
    }

    /**
     * Build user prompt for career suggestions
     */
    private function build_career_suggest_user_prompt($seed_career, $profile, $filters, $novelty_bias, $limit)
    {
        $prompt = "";

        // Seed career
        if (!empty($seed_career)) {
            $prompt .= "seed_career: " . $seed_career . "\n\n";
        } else {
            $prompt .= "seed_career: (empty - infer from profile)\n\n";
        }

        // Profile data
        $prompt .= "profile:\n";

        // MI top 3
        $mi_strings = [];
        foreach ($profile['mi_top3'] as $mi) {
            $mi_strings[] = '{"slug":"' . $mi['slug'] . '","score":' . $mi['score'] . '}';
        }
        $prompt .= "  mi_top3: [" . implode(',', $mi_strings) . "]\n";

        // CDT scores
        $cdt_strings = [];
        foreach ($profile['cdt_scores'] as $key => $val) {
            $cdt_strings[] = '"' . $key . '":' . $val;
        }
        $prompt .= "  cdt_scores: {" . implode(',', $cdt_strings) . "}\n";
        $prompt .= "  cdt_top: \"" . $profile['cdt_top'] . "\"\n";
        $prompt .= "  cdt_edge: \"" . $profile['cdt_edge'] . "\"\n";

        // Bartle
        $prompt .= "  bartle: {\"primary\":\"" . $profile['bartle']['primary'] . "\"";
        if ($profile['bartle']['secondary']) {
            $prompt .= ",\"secondary\":\"" . $profile['bartle']['secondary'] . "\"";
        }
        $prompt .= "}\n";

        // Johari
        $johari_json = json_encode($profile['johari']);
        $prompt .= "  johari: " . $johari_json . "\n\n";

        // Filters
        $prompt .= "filters:\n";

        $filter_keys = [
            'demand_horizon',
            'education_levels',
            'work_env',
            'role_orientation',
            'comp_band',
            'social_impact',
            'remote_only',
            'stretch_opposites'
        ];

        foreach ($filter_keys as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null) {
                $prompt .= "  $key: null\n";
            } elseif (is_array($value)) {
                $prompt .= "  $key: " . json_encode($value) . "\n";
            } elseif (is_bool($value)) {
                $prompt .= "  $key: " . ($value ? 'true' : 'false') . "\n";
            } else {
                $prompt .= "  $key: \"$value\"\n";
            }
        }

        $prompt .= "\nnovelty_bias: " . number_format($novelty_bias, 2) . "\n";
        $prompt .= "limit_per_bucket: " . $limit . "\n";

        return $prompt;
    }

    /**
     * AJAX: Get Related Careers for Mind-Map Expansion
     * Endpoint: mc_lab_get_related_careers
     * Params: career_title (string), lane (adjacent|parallel|wildcard), limit (int, default 8), novelty (float, default 0)
     */
    public function ajax_get_related_careers()
    {
        check_ajax_referer('mc_lab_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id || !$this->user_can_access_lab_mode()) {
            wp_send_json_error('Insufficient permissions');
        }

        // Parse request parameters
        $career_title = sanitize_text_field($_POST['career_title'] ?? '');
        $lane = sanitize_text_field($_POST['lane'] ?? 'parallel');
        $limit = intval($_POST['limit'] ?? 8);
        $novelty = floatval($_POST['novelty'] ?? 0);
        $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true) ?: [];
        $prompt_level = sanitize_text_field($_POST['prompt_level'] ?? 'minimal');
        $prompt_templates = json_decode(stripslashes($_POST['prompt_templates'] ?? '{}'), true) ?: [];

        // Validate lane parameter
        if (!in_array($lane, ['adjacent', 'parallel', 'wildcard'])) {
            wp_send_json_error('Invalid lane parameter');
        }

        if (empty($career_title)) {
            wp_send_json_error('career_title is required');
        }

        // Check cache first (transient with 1 hour expiration)
        // Include filters in cache key so different filters get different results
        $cache_key = 'mc_related_' . md5($career_title . $lane . $user_id . serialize($filters));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            wp_send_json_success($cached);
            return;
        }

        // Get user profile data
        $profile_data = $this->get_enhanced_career_profile($user_id);

        if (!$profile_data) {
            wp_send_json_error('Unable to load your profile data. Please ensure all assessments are complete.');
        }

        try {
            $related_careers = $this->generate_related_careers(
                $career_title,
                $lane,
                $profile_data,
                $limit,
                $novelty,
                $filters,
                $prompt_level,
                $prompt_templates
            );

            // Cache for 1 hour
            set_transient($cache_key, $related_careers, HOUR_IN_SECONDS);

            wp_send_json_success($related_careers);

        } catch (Exception $e) {
            error_log('Get Related Careers - AI generation failed: ' . $e->getMessage());
            wp_send_json_error('Failed to generate related careers: ' . $e->getMessage());
        }
    }

    /**
     * Generate related careers using AI based on lane type
     */
    private function generate_related_careers($career_title, $lane, $profile, $limit, $novelty, $filters = [], $prompt_level = 'minimal', $prompt_templates = [])
    {
        if (!class_exists('Micro_Coach_AI')) {
            throw new Exception('Micro_Coach_AI class not available');
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Build lane-specific system prompt
        $system_prompt = $this->build_related_careers_system_prompt($lane);

        // Build user prompt with profile, career title, and filters
        $user_prompt = $this->build_related_careers_user_prompt($career_title, $profile, $limit, $novelty, $filters);

        $model = Micro_Coach_AI::get_selected_model();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.7 + ($novelty * 0.3),
            'max_tokens' => 500,  // Reduced for faster generation
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            throw new Exception('API returned status ' . $status_code . ': ' . ($error_data['error']['message'] ?? 'Unknown error'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }

        $content = $data['choices'][0]['message']['content'];
        $careers = json_decode($content, true);

        if (!$careers || !isset($careers['careers'])) {
            throw new Exception('Failed to decode related careers JSON');
        }

        // Capture token usage for admin visibility
        $usage = $data['usage'] ?? null;
        $usage_info = null;

        if ($usage) {
            // Calculate estimated cost (GPT-4o-mini pricing as of 2024)
            // Input: $0.150 per 1M tokens, Output: $0.600 per 1M tokens
            $input_tokens = $usage['prompt_tokens'] ?? 0;
            $output_tokens = $usage['completion_tokens'] ?? 0;
            $total_tokens = $usage['total_tokens'] ?? 0;

            $input_cost = ($input_tokens / 1000000) * 0.150;
            $output_cost = ($output_tokens / 1000000) * 0.600;
            $total_cost = $input_cost + $output_cost;

            $usage_info = [
                'model' => $model,
                'input_tokens' => $input_tokens,
                'output_tokens' => $output_tokens,
                'total_tokens' => $total_tokens,
                'estimated_cost_usd' => round($total_cost, 6)
            ];
        }

        // Add IDs and ensure consistency
        $result = [
            'careers' => [],
            'usage' => $usage_info  // Include usage data for admin display
        ];

        foreach ($careers['careers'] as $index => $career) {
            $result['careers'][] = [
                'id' => 'career-' . md5($career['title'] . $lane . $index),
                'title' => $career['title'],
                'lane' => $lane,
                'fit' => $career['fit'] ?? 0.75,
                'similarity' => $career['similarity'] ?? 0.70,
                'mi' => $career['mi'] ?? [],
                'cdt_top' => $career['cdt_top'] ?? null,
                'bartle' => $career['bartle'] ?? null,
                'why_it_fits' => $career['why_it_fits'] ?? '',
                'profile_match' => [
                    'mi' => $career['mi'] ?? [],
                    'cdt_top' => $career['cdt_top'] ?? null,
                    'bartle' => $career['bartle'] ?? null,
                    'fit' => $career['fit'] ?? 0.75,
                    'similarity' => $career['similarity'] ?? 0.70
                ],
                'meta' => [
                    'demand_horizon' => $career['demand_horizon'] ?? 'stable_low_vol',
                    'education_level' => is_array($career['education'] ?? null) ? $career['education'] : [$career['education'] ?? 'bachelor'],
                    'work_env' => is_array($career['work_env'] ?? null) ? $career['work_env'] : [],
                    'comp_band' => $career['comp_band'] ?? 'middle',
                    'social_impact' => $career['social_impact'] ?? []
                ]
            ];
        }

        return $result;
    }

    /**
     * Build system prompt for related careers based on lane
     */
    private function build_related_careers_system_prompt($lane)
    {
        $lane_desc = [
            'adjacent' => 'similar careers, easy transitions',
            'parallel' => 'similar skills, different industries',
            'wildcard' => 'unexpected but fitting careers'
        ][$lane];

        return "Career discovery assistant. Generate {$lane_desc}.\n\nJSON format: {\"careers\":[{\"title\":\"\",\"fit\":0.0-1.0,\"similarity\":0.0-1.0,\"mi\":[\"str\"],\"cdt_top\":\"\",\"bartle\":\"\"}]}\nfit=profile match, similarity=source match. Concise only.";
    }

    /**
     * Build user prompt for related careers
     */
    private function build_related_careers_user_prompt($career_title, $profile, $limit, $novelty, $filters = [])
    {
        // Build MI string
        $mi_str = implode(',', array_column($profile['mi_top3'], 'slug'));

        // Build filter string (only if filters exist)
        $filter_str = '';
        if (!empty($filters) && is_array($filters)) {
            $filter_parts = [];
            foreach ($filters as $k => $v) {
                if ($v && $v !== []) {
                    $filter_parts[] = is_array($v) ? "$k:" . implode(',', $v) : "$k:$v";
                }
            }
            if (!empty($filter_parts)) {
                $filter_str = "\nfilters: " . implode('; ', $filter_parts);
            }
        }

        return "source: {$career_title}\nprofile: mi={$mi_str}, cdt={$profile['cdt_top']}, bartle={$profile['bartle']['primary']}{$filter_str}\nlimit: {$limit}";
    }
}

// Initialize Lab Mode if the class exists and Lab Mode is enabled
if (class_exists('Micro_Coach_Core')) {
    new Micro_Coach_AI_Lab();
}
