<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MC_Report_Template
 * 
 * Handles rendering of the Deep Assessment Analysis Report modal.
 * Acts as the single source of truth for the report structure.
 */
class MC_Report_Template
{
    /**
     * Render the Analysis Modal HTML.
     * 
     * @param bool $include_debug Whether to include the admin debug metadata container.
     */
    public static function render_analysis_modal($include_debug = false)
    {
        ?>
        <div id="mc-analysis-modal" class="mc-modal">
            <div class="mc-modal-content mc-deep-report-modal">

                <div id="mc-report-loading" style="display: none; text-align: center; padding: 40px; position: relative;">
                    <span class="mc-close-modal" onclick="closeAnalysisModal()"
                        style="position: absolute; top: 10px; right: 10px;">&times;</span>
                    <div class="mc-spinner"></div>
                    <p>Generating deep analysis... this may take a minute.</p>
                </div>

                <div id="mc-report-content" class="mc-deep-report">
                    <!-- Hero Section -->
                    <div class="mc-report-hero">
                        <div class="mc-report-header-row"
                            style="display: flex; justify-content: space-between; align-items: flex-start; padding-top: 0.5rem;">
                            <div class="mc-header-left">
                                <h2
                                    style="margin: 0; font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.2; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                    <span id="mc-analysis-company"
                                        style="font-size: 1.5rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"></span>
                                    <span style="font-size: 1.5rem; color: #cbd5e1; font-weight: 400;">—</span>
                                    <span id="mc-analysis-name" style="color: #0f172a;">Employee Name</span>
                                </h2>
                                <div style="margin-top: 0.5rem;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span id="mc-role-badge" style="font-size: 1rem; color: #475569; font-weight: 600;">Role
                                            Analysis</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mc-header-right" style="display: flex; align-items: flex-start; gap: 12px;">
                                <button onclick="downloadReportPDF(this)" class="mc-btn mc-btn-secondary mc-btn-sm"
                                    style="display: flex; align-items: center; gap: 6px; margin-top: 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    PDF
                                </button>
                                <button id="mc-regenerate-report-btn" class="mc-btn mc-btn-secondary mc-btn-sm"
                                    style="display: flex; align-items: center; gap: 6px; margin-top: 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    Regenerate
                                </button>
                                <span class="mc-close-hero" onclick="closeAnalysisModal()"
                                    style="position: static; font-size: 1.5rem; cursor: pointer; color: #94a3b8; line-height: 1; padding: 4px; margin-left: 8px; margin-top: -4px;">&times;</span>
                            </div>
                        </div>
                        <div
                            style="padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; margin-top: 0.5rem;">
                            <p style="margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.5;">
                                This report analyzes the employee's fit for the specific role based on their
                                assessment results. It synthesizes data from Motivational, Personality, and
                                Cognitive assessments to predict performance, cultural fit, and leadership
                                potential.
                            </p>
                        </div>

                        <?php if ($include_debug): ?>
                            <!-- Test Data Metadata (Admin Debug) -->
                            <div id="mc-test-metadata-container"
                                style="display:none; background:#fffbeb; border:1px solid #fcd34d; padding:12px 20px; font-size:13px; color:#92400e; margin-bottom: 20px; border-radius: 6px;">
                                <h4 style="margin:0 0 8px 0; color:#b45309; display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-admin-network"></span> Test Data Metadata (Admin Only)
                                </h4>
                                <div class="mc-metadata-content"></div>
                            </div>
                        <?php endif; ?>

                        <!-- Company Culture Fit (Moved to Top) -->
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
                                        <span id="mc-hero-fit-score"
                                            style="font-size:3em; font-weight:800; color:var(--mc-primary); line-height:1;">--</span>
                                        <span style="font-size:1.2em; color:#94a3b8; font-weight:500;">/ 100</span>
                                    </div>
                                </div>
                                <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                                    <p id="mc-hero-fit-rationale"
                                        style="margin: 0; font-size: 1.05rem; line-height: 1.6; color: #334155;">--</p>
                                </div>
                                <!-- Added Summary Section -->
                                <div
                                    style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-left: 4px solid #2563eb; border-radius: 0 4px 4px 0;">
                                    <p id="mc-hero-context-summary" style="margin: 0; color: #334155; line-height: 1.6;">--</p>
                                </div>
                            </div>
                        </div>

                        <!-- Leadership Strip -->
                        <div class="mc-hero-leadership-strip">
                            <div class="mc-leadership-header">
                                <h4>Leadership Potential</h4>
                                <div class="mc-leadership-spectrum">
                                    <div class="mc-spectrum-track">
                                        <div class="mc-spectrum-segment" data-level="individual">Individual</div>
                                        <div class="mc-spectrum-segment" data-level="emerging">Emerging</div>
                                        <div class="mc-spectrum-segment" data-level="developing">Developing</div>
                                        <div class="mc-spectrum-segment" data-level="strong">Strong</div>
                                        <div class="mc-spectrum-segment" data-level="rockstar">Rockstar Fit</div>
                                    </div>
                                    <div id="mc-spectrum-marker" class="mc-spectrum-marker"></div>
                                </div>
                            </div>
                            <p id="mc-hero-leadership-summary">--</p>
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
                                    <ul id="mc-hero-strengths" class="mc-pill-list"></ul>
                                </div>
                                <div class="mc-insight-box"
                                    style="background:#fff1f2; border-radius:12px; padding:1.5rem; border: 1px solid #ffe4e6;">
                                    <h4
                                        style="margin-top:0; color:#9f1239; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; font-weight: 700;">
                                        Potential Blindspots</h4>
                                    <ul id="mc-hero-weaknesses" class="mc-pill-list"></ul>
                                </div>
                                <div class="mc-insight-box"
                                    style="background:#f0f9ff; border-radius:12px; padding:1.5rem; border: 1px solid #e0f2fe;">
                                    <h4
                                        style="margin-top:0; color:#0369a1; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; font-weight: 700;">
                                        Key Motivators</h4>
                                    <ul id="mc-hero-motivators" class="mc-pill-list"></ul>
                                </div>
                            </div>
                        </div>
                    </div><!-- Main Content Grid -->
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
                                        <ul id="mc-comm-do"></ul>
                                    </div>
                                    <div class="mc-playbook-col mc-avoid">
                                        <h4>Avoid This</h4>
                                        <ul id="mc-comm-avoid"></ul>
                                    </div>
                                </div>
                                <div class="mc-playbook-footer">
                                    <strong>Preferred Format:</strong> <span id="mc-comm-format">--</span>
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
                                        <ul id="mc-motiv-energizers" class="mc-check-list"></ul>
                                    </div>
                                    <div>
                                        <h4>Drainers</h4>
                                        <ul id="mc-motiv-drainers" class="mc-cross-list"></ul>
                                    </div>
                                </div>
                                <div class="mc-divider"></div>
                                <div class="mc-work-style-box">
                                    <p><strong>Work Style:</strong> <span id="mc-work-approach">--</span></p>
                                    <div class="mc-grid-2">
                                        <div>
                                            <small>Best When:</small>
                                            <ul id="mc-work-best" class="mc-sm-list"></ul>
                                        </div>
                                        <div>
                                            <small>Struggles When:</small>
                                            <ul id="mc-work-struggle" class="mc-sm-list"></ul>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Strain Index Analysis -->
                            <div class="mc-section-card" id="mc-strain-section" style="display:none;">
                                <div class="mc-section-header">
                                    <h3>Strain Index Analysis</h3>
                                    <span class="mc-section-icon">🧠</span>
                                </div>
                                <div class="mc-strain-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <!-- Overall Score -->
                                    <div class="mc-strain-overall"
                                        style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px;">
                                        <h4
                                            style="margin: 0 0 15px 0; color: #64748b; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.05em;">
                                            Overall Strain</h4>
                                        <div style="position: relative; width: 140px; height: 70px; margin: 0 auto;">
                                            <div class="mc-strain-gauge"
                                                style="position: absolute; top: 0; left: 0; width: 140px; height: 70px; overflow: hidden;">
                                                <div class="mc-gauge-bg"
                                                    style="width: 100%; height: 100%; background: #e2e8f0; border-top-left-radius: 70px; border-top-right-radius: 70px;">
                                                </div>
                                                <div class="mc-gauge-fill" id="mc-strain-gauge-fill"
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #ef4444; border-top-left-radius: 70px; border-top-right-radius: 70px; transform-origin: bottom center; transform: rotate(-180deg); transition: transform 1s;">
                                                </div>
                                            </div>
                                            <div id="mc-strain-overall-score"
                                                style="position: absolute; bottom: 0; left: 0; width: 100%; text-align: center; font-size: 2em; font-weight: 800; color: #0f172a; line-height: 1; z-index: 5;">
                                                --</div>
                                        </div>
                                        <button id="mc-strain-deep-dive-btn" class="mc-btn mc-btn-secondary mc-btn-sm"
                                            style="margin-top: 15px; width: 100%; justify-content: center; font-size: 13px; font-weight: 500;">
                                            View More
                                        </button>
                                    </div>
                                    <!-- Sub-Indices -->
                                    <div class="mc-strain-breakdown">
                                        <div class="mc-strain-row" style="margin-bottom: 15px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span style="font-weight: 600; color: #475569;">Rumination</span>
                                                <span id="mc-strain-rumination-val" style="font-weight: 700;">--</span>
                                            </div>
                                            <div
                                                style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                <div id="mc-strain-rumination-bar"
                                                    style="height: 100%; background: #3b82f6; width: 0%;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mc-strain-row" style="margin-bottom: 15px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span style="font-weight: 600; color: #475569;">Avoidance</span>
                                                <span id="mc-strain-avoidance-val" style="font-weight: 700;">--</span>
                                            </div>
                                            <div
                                                style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                <div id="mc-strain-avoidance-bar"
                                                    style="height: 100%; background: #f59e0b; width: 0%;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mc-strain-row">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span style="font-weight: 600; color: #475569;">Emotional Flood</span>
                                                <span id="mc-strain-flood-val" style="font-weight: 700;">--</span>
                                            </div>
                                            <div
                                                style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                <div id="mc-strain-flood-bar"
                                                    style="height: 100%; background: #ec4899; width: 0%;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; font-size: 0.9em; color: #64748b; font-style: italic;">
                                    * Strain Index metrics are internal-only and not visible to the employee.
                                </div>

                                <!-- On-demand data container -->
                                <div id="mc-strain-accordions" class="mc-strain-accordions"
                                    style="margin-top: 20px; display: none;"></div>
                            </div>

                            <!-- Coaching Recommendations -->
                            <div class="mc-section-card">
                                <div class="mc-section-header">
                                    <h3>Coaching Recommendations</h3>
                                    <span class="mc-section-icon">🎯</span>
                                </div>
                                <div id="mc-coaching-container" class="mc-cards-container">
                                    <!-- Cards injected via JS -->
                                </div>
                            </div>

                            <!-- Growth Edges -->
                            <div class="mc-section-card">
                                <div class="mc-section-header">
                                    <h3>Stretch Assignments</h3>
                                    <span class="mc-section-icon">📈</span>
                                </div>
                                <div id="mc-growth-container" class="mc-cards-container">
                                    <!-- Cards injected via JS -->
                                </div>
                            </div>

                            <!-- Team & Leadership -->
                            <div class="mc-section-card">
                                <div class="mc-section-header">
                                    <h3>Team & Leadership</h3>
                                    <span class="mc-section-icon">👥</span>
                                </div>
                                <div class="mc-grid-2">
                                    <div>
                                        <h4>Collaboration</h4>
                                        <p><strong>Thrives with:</strong> <span id="mc-team-thrives">--</span></p>
                                        <p><strong>Friction with:</strong> <span id="mc-team-friction">--</span></p>
                                    </div>
                                    <div>
                                        <h4>Ideal Conditions</h4>
                                        <p id="mc-hero-conditions">--</p>
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
                                        <ul id="mc-guide-strengths"></ul>
                                    </div>
                                    <div class="mc-guide-item">
                                        <strong>Key Motivators</strong>
                                        <ul id="mc-guide-motivators"></ul>
                                    </div>
                                    <div class="mc-guide-item">
                                        <strong>Communication</strong>
                                        <ul id="mc-guide-comm"></ul>
                                    </div>
                                    <div class="mc-guide-item">
                                        <strong>Coaching Moves</strong>
                                        <ul id="mc-guide-coaching"></ul>
                                    </div>
                                    <div class="mc-guide-item">
                                        <strong>This Year's Growth</strong>
                                        <p id="mc-guide-growth">--</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mc-meta-box">
                                <h4>Conflict & Stress</h4>
                                <p><strong>Handling:</strong> <span id="mc-stress-handling">--</span></p>
                                <p><strong>Signs:</strong> <span id="mc-stress-signs">--</span></p>
                                <p><strong>Support:</strong> <span id="mc-stress-support">--</span></p>
                            </div>
                        </div>
                    </div>

                    <div id="mc-analysis-meta-warning"
                        style="display:none; margin-top: 20px; font-size: 0.8em; color: #999; text-align: center;">
                        Based on <span id="mc-meta-quiz-count">0</span> quizzes and <span id="mc-meta-peer-count">0</span>
                        peer reviews.
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
