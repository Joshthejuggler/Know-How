<?php if (!defined('ABSPATH')) exit; ?>

<div class="mc-bench-page">
    <div class="mc-bench-header">
        <div class="mc-bench-title-group">
            <h1>Scorecard Management & Evaluation</h1>
            <p class="mc-bench-subtitle">Manage your cultural baselines and evaluate candidate alignment with premium analytics.</p>
        </div>
        <div class="mc-bench-tabs">
            <button class="mc-bench-tab-btn active" data-tab="manage-scorecards">
                <span class="dashicons dashicons-groups"></span> Manage Scorecards
            </button>
            <button class="mc-bench-tab-btn" data-tab="candidate-evaluation">
                <span class="dashicons dashicons-chart-area"></span> Candidate Evaluation
            </button>
        </div>
    </div>

    <!-- Manage Scorecards Tab -->
    <div id="tab-manage-scorecards" class="mc-tab-content active">
        <div class="mc-layout-grid">
            <!-- Left: Sidebar -->
            <div class="mc-sidebar-panel">
                <div class="mc-side-card">
                    <div class="mc-card-header">
                        <span class="step-num">1</span>
                        <h3>Selected Scorecard</h3>
                    </div>
                    
                    <div class="mc-select-wrapper" style="display:flex; gap:8px; margin-bottom:12px;">
                        <select id="mc-manage-scorecard-select" style="flex:1;">
                            <?php foreach ($scorecards as $sc): ?>
                                <option value="<?php echo esc_attr($sc['id']); ?>"><?php echo esc_html($sc['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button id="mc-btn-new-scorecard" class="mc-action-btn" title="Create New Scorecard" style="padding:0 10px; border-radius:4px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
                            <span class="dashicons dashicons-plus" style="line-height:28px;"></span>
                        </button>
                    </div>
                    <div style="font-size:12px; margin-bottom:24px;">
                        <a href="#" id="mc-rename-sc" style="text-decoration:none;">Rename</a> &nbsp;|&nbsp; <a href="#" id="mc-delete-sc" style="color:#dc2626; text-decoration:none;">Delete</a>
                    </div>
                    
                    <div class="mc-card-header">
                        <span class="step-num">2</span>
                        <h3>Define Baseline</h3>
                    </div>
                    <p class="description">Select the members to include in this scorecard.</p>
                    
                    <div class="mc-culture-selector">
                        <input type="hidden" id="mc-active-scorecard-id" value="<?php echo !empty($scorecards) ? esc_attr($scorecards[0]['id']) : 'scorecard_1'; ?>">
                        
                        <div class="mc-user-search-premium">
                            <input type="text" id="mc-scorecard-search" placeholder="Search team members..." style="width:100%; box-sizing:border-box; margin-bottom:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom: 8px;">
                                <a href="#" id="mc-btn-select-all" style="font-size:12px; font-weight:600; text-decoration:none; color:#2563eb;">✓ Select All</a>
                                <a href="#" id="mc-btn-clear-all" style="font-size:12px; font-weight:600; text-decoration:none; color:#dc2626;">✕ Clear All</a>
                            </div>
                        </div>

                        <div class="mc-selection-box">
                            <div class="mc-user-list-scroller">
                                <?php if (empty($current_employees)): ?>
                                    <div class="mc-empty-small">No employees found.</div>
                                <?php else: ?>
                                    <ul class="mc-user-list-premium" id="mc-manage-employee-list">
                                        <!-- Checkboxes will be rendered accurately by JS based on selection, but initially load scorecard[0] -->
                                        <?php 
                                        $initial_selected = !empty($scorecards) ? $scorecards[0]['last_selected_ids'] : [];
                                        foreach ($current_employees as $user): ?>
                                            <li>
                                                <label class="mc-user-item">
                                                    <input type="checkbox" class="mc-culture-checkbox" value="<?php echo $user->ID; ?>" <?php checked(in_array($user->ID, $initial_selected)); ?>>
                                                    <div class="mc-user-info">
                                                        <span class="mc-user-name"><?php echo esc_html($user->display_name); ?></span>
                                                        <span class="mc-user-email"><?php echo esc_html($user->user_email); ?></span>
                                                    </div>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mc-auto-save-notice" style="margin-top:16px;">
                        <span class="dashicons dashicons-saved"></span> Auto-saving selection
                    </div>
                </div>
            </div>

            <!-- Right: Scorecard Results -->
            <div class="mc-main-panel">
                <div id="mc-live-scorecard" class="mc-scorecard-card" style="display:none;">
                    <div class="mc-scorecard-header-flex">
                        <h2 id="mc-scorecard-preview-name">Team Culture Profile</h2>
                        <span class="mc-badge-outline" id="mc-scorecard-member-count">0 Members</span>
                    </div>
                    
                    <div class="mc-scorecard-data-grid">
                        <div class="mc-data-box">
                            <h3>Average Cultural Dynamics (CDT)</h3>
                            <div class="mc-radar-wrap">
                                <canvas id="mc-culture-radar"></canvas>
                            </div>
                        </div>
                        <div class="mc-data-box">
                            <h3>Average Player Type Scores</h3>
                            <div class="mc-bartle-wrap">
                                <canvas id="mc-culture-bartle"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="mc-blind-spots-area">
                        <h3>Primary Persona Distribution & Blind Spots</h3>
                        <div id="mc-blind-spots-list" class="mc-blind-grid-premium">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
                
                <div id="mc-culture-empty" class="mc-panel-empty">
                    <div class="mc-empty-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <h2>Scorecard Configuration</h2>
                    <p>Select members on the left to see the aggregate cultural profile for the selected scorecard.</p>
                </div>
                
                <div id="mc-culture-loading" style="display:none;" class="mc-panel-loading">
                    <div class="mc-spinner"></div>
                    <p>Loading Baseline...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidate Evaluation Tab -->
    <div id="tab-candidate-evaluation" class="mc-tab-content">
        <div class="mc-layout-grid">
            <!-- Left: Sidebar -->
            <div class="mc-sidebar-panel">
                <div class="mc-side-card">
                    <div class="mc-card-header">
                        <span class="step-num">1</span>
                        <h3>Target Scorecard</h3>
                    </div>
                    <p class="description">Select the benchmark to evaluate against.</p>
                    <div class="mc-select-wrapper">
                        <select id="mc-eval-scorecard-select" class="mc-premium-select">
                            <?php foreach ($scorecards as $sc): ?>
                                <option value="<?php echo esc_attr($sc['id']); ?>"><?php echo esc_html($sc['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mc-card-header" style="margin-top:24px;">
                        <span class="step-num">2</span>
                        <h3>Select Candidate</h3>
                    </div>
                    <p class="description">Choose the candidate you wish to evaluate.</p>
                    <div class="mc-select-wrapper">
                        <select id="mc-eval-candidate-select" class="mc-premium-select">
                            <option value="">Select a Candidate...</option>
                            <?php foreach ($candidates as $user): ?>
                                <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button id="mc-generate-evaluation" class="mc-btn-primary full-width" style="margin-top: 24px;">
                        Generate Analysis
                    </button>
                </div>
            </div>

            <!-- Right: Results -->
            <div class="mc-main-panel">
                <div id="mc-eval-results" style="display:none;">
                    
                    <!-- Candidate Fit Summary Hero -->
                    <div class="mc-fit-report-header" style="margin-bottom: 24px;">
                        <div class="mc-fit-hero-score">
                            <div class="score-circle-mini">
                                <span id="mc-fit-pct">0%</span>
                            </div>
                            <div class="mc-fit-header-info">
                                <h2 id="mc-eval-candidate-name" style="color: #ffffff; margin: 0 0 6px; font-size: 24px;">Candidate Name</h2>
                                <p style="margin:4px 0 8px; font-size:13px; color:rgba(255,255,255,0.8);">Evaluated against: <strong id="mc-eval-target-name" style="color:#ffffff;">Scorecard</strong></p>
                                <div class="mc-fit-badge-premium" id="mc-fit-label">Culture Addition</div>
                            </div>
                        </div>
                        <div class="mc-fit-actions">
                             <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" target="_blank">
                                <input type="hidden" name="action" value="mc_generate_candidate_pdf">
                                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('mc_culture_scorecard_nonce'); ?>">
                                <input type="hidden" id="mc-pdf-candidate-id" name="candidate_id" value="">
                                <input type="hidden" id="mc-pdf-scorecard-id" name="scorecard_id" value="">
                                <button type="submit" class="mc-btn-integrated">
                                    <span class="dashicons dashicons-pdf"></span> Integrated Report
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Radar & Trait Alignment (Formerly Talent Comparison) -->
                    <div class="mc-charts-grid" style="margin-bottom:24px;">
                        <div class="mc-chart-card">
                            <h3>Archetype Overlay</h3>
                            <p style="font-size: 13px; color: #64748b; margin: 8px 0 24px 0; line-height: 1.4;">
                                Visualizes the candidate's holistic footprint (solid blue) plotted directly against the target scorecard's average baseline (dashed grey).
                            </p>
                            <div class="mc-chart-canvas-wrap">
                                <canvas id="mc-bench-radar"></canvas>
                            </div>
                            <div class="mc-custom-legend">
                                <span class="legend-dot benchmark"></span> Benchmark
                                <span class="legend-dot candidate"></span> Candidate
                            </div>
                        </div>

                        <div class="mc-breakdown-card">
                            <h3>Trait Alignment Index</h3>
                            <p style="font-size: 13px; color: #64748b; margin: 8px 0 24px 0; line-height: 1.4;">
                                Compares the candidate's absolute trait scores against the benchmark, determining the percentage of mutual alignment.
                            </p>
                            <div id="mc-traits-list" class="mc-trait-alignment-list">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Culture Fit Narrative details -->
                    <div id="mc-fit-report-body" class="mc-fit-body-premium">
                        <!-- Populated by JS -->
                    </div>

                </div>

                <!-- Empty State -->
                <div id="mc-bench-empty" class="mc-panel-empty">
                    <div class="mc-empty-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <h2>Start Evaluation</h2>
                    <p>Select a Candidate and a Target Scorecard to generate a comprehensive cultural and trait alignment report.</p>
                </div>

                <!-- Loading State -->
                <div id="mc-bench-loading" style="display:none;" class="mc-panel-loading">
                    <div class="mc-spinner"></div>
                    <p>Generating Unified Analysis...</p>
                </div>
            </div>
        </div>
    </div>
</div>
