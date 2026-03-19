/**
 * ExperimentIterationPanel - Interactive modal for refining experiments
 * Handles single-modifier iteration with AI to iteratively improve experiments
 */

(function($) {
    'use strict';

    window.IterationPanel = {
        isOpen: false,
        currentExperiment: null,
        originalExperiment: null,
        currentIndex: null,
        iterations: [],
        currentIteration: 0,
        userContext: null,
        isLoading: false,
        focusedElementBeforeOpen: null,
        focusableElements: null,
        firstFocusableElement: null,
        lastFocusableElement: null,

        /**
         * Open the iteration panel with an experiment
         * @param {Object} experiment - The experiment object to iterate on
         * @param {number} index - Index in the experiments array
         * @param {Object} context - User context (MI, CDT, role models, etc.)
         */
        open: function(experiment, index, context = null) {
            console.log('IterationPanel.open called', { experiment, index, context });
            
            if (this.isOpen) {
                console.warn('IterationPanel already open');
                return;
            }

            this.currentExperiment = $.extend(true, {}, experiment);
            this.originalExperiment = $.extend(true, {}, experiment);
            this.currentIndex = index;
            this.iterations = [$.extend(true, {}, experiment)];
            this.currentIteration = 0;
            this.userContext = context || this.getUserContext();
            this.debugHistory = []; // Initialize debug history
            this.isOpen = true;

            // Store current focus
            this.focusedElementBeforeOpen = document.activeElement;

            // Create modal if it doesn't exist
            if (!document.getElementById('iteration-modal')) {
                this.createModal();
            }

            // Populate and show modal
            this.populateModal();
            $('#iteration-modal').addClass('active').attr('aria-hidden', 'false');
            
            // Set up focus management
            this.setupFocusTrap();
            this.firstFocusableElement?.focus();

            // Prevent background scrolling
            $('body').addClass('modal-open');

            console.log('IterationPanel opened successfully');
        },

        /**
         * Close the iteration panel
         */
        close: function() {
            if (!this.isOpen) return;

            $('#iteration-modal').removeClass('active').attr('aria-hidden', 'true');
            $('body').removeClass('modal-open');
            
            // Restore focus
            if (this.focusedElementBeforeOpen) {
                this.focusedElementBeforeOpen.focus();
            }

            // Reset state
            this.isOpen = false;
            this.currentExperiment = null;
            this.originalExperiment = null;
            this.currentIndex = null;
            this.iterations = [];
            this.currentIteration = 0;
            this.userContext = null;
            this.debugHistory = [];
            this.isLoading = false;
            
            // Clear any debug panels or notifications
            $('.iteration-debug-panel').remove();
            $('.version-notification').remove();
            
            console.log('IterationPanel closed');
        },

        /**
         * Create the modal DOM structure
         */
        createModal: function() {
            const modalHTML = `
                <div id="iteration-modal" class="iteration-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="iteration-modal-title" tabindex="-1">
                    <div class="iteration-backdrop" role="presentation"></div>
                    <div class="iteration-dialog">
                        <div class="iteration-header">
                            <h2 id="iteration-modal-title">Refine Experiment</h2>
                            <div class="iteration-header-controls">
                                <button type="button" class="iteration-admin-debug-btn lab-btn lab-btn-tertiary" style="display: none;" title="Show AI prompts and debug info">
                                    🐛 Debug
                                </button>
                                <button type="button" class="iteration-close-btn" aria-label="Close iteration panel">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>
                        <div class="iteration-content">
                            <div class="iteration-columns">
                                <div class="iteration-left">
                                    <div class="iteration-current-experiment">
                                        <div class="iteration-breadcrumb">
                                            <span class="iteration-version">Version 1</span>
                                        </div>
                                        <div class="experiment-display">
                                            <!-- Current experiment content will be populated here -->
                                        </div>
                                        <div class="iteration-diff-notice" style="display: none;">
                                            <small class="iteration-calibration-notes"></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="iteration-right">
                                    <div class="iteration-controls">
                                        <div class="iteration-section-header">
                                            <span class="iteration-section-label">Refine with One Change</span>
                                            <small class="iteration-section-subtitle">Each click sends exactly ONE modifier</small>
                                        </div>
                                        
                                        <!-- Custom Input Section -->
                                        <div class="iteration-custom-input">
                                            <div class="custom-input-header">
                                                <span class="modifier-emoji">✏️</span>
                                                <span class="modifier-label">Custom Request</span>
                                            </div>
                                            <div class="custom-input-body">
                                                <textarea 
                                                    id="iteration-custom-text" 
                                                    placeholder="Describe the specific change you want... (e.g., 'Add a team building element', 'Make it work for remote teams', 'Include measurable outcomes')"
                                                    rows="3"
                                                    maxlength="500"
                                                    aria-label="Custom modification request"
                                                ></textarea>
                                                <div class="custom-input-actions">
                                                    <div class="char-counter">
                                                        <span class="char-count">0</span>/500
                                                    </div>
                                                    <button type="button" class="iteration-modifier-btn iteration-custom-btn" data-mod-type="Custom" title="Apply your custom modification">
                                                        Apply Custom Change
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="iteration-divider">
                                            <span>Or choose a quick modifier</span>
                                        </div>
                                        
                                        <div class="iteration-modifier-groups">
                                            <!-- Modifier controls will be populated here -->
                                        </div>
                                        <div class="iteration-loading" style="display: none;">
                                            <div class="loading-spinner"></div>
                                            <span>AI is refining your experiment...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="iteration-footer">
                            <div class="iteration-footer-left">
                                <button type="button" class="lab-btn lab-btn-tertiary iteration-reset-btn">Reset to Original</button>
                            </div>
                            <div class="iteration-footer-right">
                                <button type="button" class="lab-btn lab-btn-secondary iteration-close-btn-footer">Close</button>
                                <button type="button" class="lab-btn lab-btn-primary iteration-save-btn">Apply Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            this.bindModalEvents();
        },

        /**
         * Bind modal-specific events
         */
        bindModalEvents: function() {
            // Close button events
            $(document).on('click', '.iteration-close-btn, .iteration-close-btn-footer', (e) => {
                e.preventDefault();
                this.close();
            });

            // Backdrop click to close
            $(document).on('click', '.iteration-backdrop', (e) => {
                e.preventDefault();
                this.close();
            });

            // ESC key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    e.preventDefault();
                    this.close();
                }
            });

            // Tab key focus management
            $(document).on('keydown', (e) => {
                if (e.key === 'Tab' && this.isOpen) {
                    this.handleTabKey(e);
                }
            });

            // Reset button
            $(document).on('click', '.iteration-reset-btn', (e) => {
                e.preventDefault();
                this.resetToOriginal();
            });

            // Save/Apply button
            $(document).on('click', '.iteration-save-btn', (e) => {
                e.preventDefault();
                this.saveAndApply();
            });

            // Modifier buttons (delegated)
            $(document).on('click', '.iteration-modifier-btn', (e) => {
                e.preventDefault();
                if (this.isLoading) return;
                
                const $btn = $(e.currentTarget);
                const modType = $btn.data('mod-type');
                const modValue = $btn.data('mod-value');
                
                // Handle custom input differently
                if (modType === 'Custom') {
                    this.handleCustomModifier();
                } else {
                    this.sendModifier({
                        kind: modType,
                        value: modValue
                    });
                }
            });
            
            // Character counter for custom input
            $(document).on('input keyup paste', '#iteration-custom-text', (e) => {
                // Add small delay to ensure value is updated after paste
                setTimeout(() => {
                    const text = $(e.target).val();
                    const charCount = text.length;
                    $('.char-count').text(charCount);
                    
                    console.log('Custom text updated:', charCount, 'characters');
                    
                    // Enable/disable custom button based on input
                    const $customBtn = $('.iteration-custom-btn');
                    if (charCount > 0 && charCount <= 500) {
                        $customBtn.prop('disabled', false).removeClass('disabled');
                        console.log('Custom button enabled');
                    } else {
                        $customBtn.prop('disabled', true).addClass('disabled');
                        console.log('Custom button disabled, char count:', charCount);
                    }
                }, 0);
            });
            
            // Enter key in custom textarea to trigger submission
            $(document).on('keydown', '#iteration-custom-text', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    this.handleCustomModifier();
                }
            });
            
            // Admin debug button
            $(document).on('click', '.iteration-admin-debug-btn', (e) => {
                e.preventDefault();
                this.toggleAdminDebugPanel();
            });
        },

        /**
         * Set up focus trap for accessibility
         */
        setupFocusTrap: function() {
            this.focusableElements = $('#iteration-modal').find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            this.firstFocusableElement = this.focusableElements.first()[0];
            this.lastFocusableElement = this.focusableElements.last()[0];
        },

        /**
         * Handle tab key for focus trapping
         */
        handleTabKey: function(e) {
            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === this.firstFocusableElement) {
                    e.preventDefault();
                    this.lastFocusableElement?.focus();
                }
            } else {
                // Tab
                if (document.activeElement === this.lastFocusableElement) {
                    e.preventDefault();
                    this.firstFocusableElement?.focus();
                }
            }
        },

        /**
         * Populate the modal with experiment data and controls
         */
        populateModal: function() {
            this.renderCurrentExperiment();
            this.renderModifierControls();
            this.updateBreadcrumb();
            this.initializeCustomInput();
            this.checkAdminStatus();
        },
        
        /**
         * Check if user is admin and show debug controls
         */
        checkAdminStatus: function() {
            const isAdmin = (window.labMode && window.labMode.isAdmin) ||
                           document.body.classList.contains('wp-admin') ||
                           document.querySelector('#wpadminbar') !== null ||
                           document.body.classList.contains('admin-bar');
            
            console.log('IterationPanel - Admin status:', isAdmin);
            
            if (isAdmin) {
                $('.iteration-admin-debug-btn').show();
            } else {
                $('.iteration-admin-debug-btn').hide();
            }
        },
        
        /**
         * Toggle admin debug panel
         */
        toggleAdminDebugPanel: function() {
            const existingPanel = $('.iteration-admin-debug-panel');
            
            if (existingPanel.length > 0) {
                existingPanel.slideToggle(300);
                const isVisible = existingPanel.is(':visible');
                $('.iteration-admin-debug-btn').text(isVisible ? '🐛 Hide Debug' : '🐛 Debug');
            } else {
                this.createAdminDebugPanel();
            }
        },
        
        /**
         * Create admin debug panel showing AI prompts
         */
        createAdminDebugPanel: function() {
            const debugHistory = this.debugHistory || [];
            
            let debugContent = '<p style="color: #666; font-style: italic;">No AI requests made yet. Try making a modification to see debug info.</p>';
            
            if (debugHistory.length > 0) {
                debugContent = debugHistory.map((entry, index) => `
                    <div class="admin-debug-entry">
                        <h5>Request ${index + 1} (${entry.timestamp})</h5>
                        <div class="debug-section">
                            <h6>Modifier:</h6>
                            <div class="debug-code"><pre>${JSON.stringify(entry.modifier, null, 2)}</pre></div>
                        </div>
                        <div class="debug-section">
                            <h6>System Prompt:</h6>
                            <div class="debug-prompt-container">
                                <textarea class="admin-debug-prompt" readonly rows="8">${entry.systemPrompt || 'Not available'}</textarea>
                                <button class="debug-copy-btn" data-copy-text="${this.escapeHtml(entry.systemPrompt || '')}" title="Copy to clipboard">📋</button>
                            </div>
                        </div>
                        <div class="debug-section">
                            <h6>User Prompt:</h6>
                            <div class="debug-prompt-container">
                                <textarea class="admin-debug-prompt" readonly rows="6">${entry.userPrompt || 'Not available'}</textarea>
                                <button class="debug-copy-btn" data-copy-text="${this.escapeHtml(entry.userPrompt || '')}" title="Copy to clipboard">📋</button>
                            </div>
                        </div>
                        <div class="debug-section">
                            <h6>Response Summary:</h6>
                            <p><strong>Changed Fields:</strong> ${entry.changedFields ? entry.changedFields.join(', ') : 'None'}</p>
                            ${entry.calibrationNotes ? `<p><strong>Calibration:</strong> ${entry.calibrationNotes}</p>` : ''}
                        </div>
                    </div>
                `).join('<hr style="margin: 1.5rem 0; border: 1px solid #eee;">');
            }
            
            const debugPanelHTML = `
                <div class="iteration-admin-debug-panel" style="margin-top: 1rem; display: none;">
                    <div class="admin-debug-header">
                        <h4>🛠️ Admin Debug Panel</h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0.5rem 0 1rem 0;">AI prompts and responses for this iteration session</p>
                    </div>
                    <div class="admin-debug-content">
                        ${debugContent}
                    </div>
                </div>
            `;
            
            $('.iteration-breadcrumb').after(debugPanelHTML);
            $('.iteration-admin-debug-panel').slideDown(300);
            $('.iteration-admin-debug-btn').text('🐛 Hide Debug');
            
            // Bind copy button events
            $('.debug-copy-btn').on('click', (e) => {
                const textToCopy = $(e.target).data('copy-text');
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const $btn = $(e.target);
                    const originalText = $btn.text();
                    $btn.text('✓ Copied!');
                    setTimeout(() => $btn.text(originalText), 2000);
                }).catch(err => {
                    console.error('Failed to copy text:', err);
                    alert('Failed to copy to clipboard');
                });
            });
        },
        
        /**
         * Update admin debug panel with latest data
         */
        updateAdminDebugPanel: function() {
            const existingPanel = $('.iteration-admin-debug-panel');
            if (existingPanel.length > 0 && existingPanel.is(':visible')) {
                // Remove existing panel and recreate with updated data
                existingPanel.remove();
                this.createAdminDebugPanel();
            }
        },
        
        /**
         * Escape HTML for safe insertion
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/"/g, '&quot;');
        },

        /**
         * Render the current experiment in the left column
         */
        renderCurrentExperiment: function() {
            const exp = this.currentExperiment;
            const originalExp = this.originalExperiment;
            
            const experimentHTML = `
                <div class="experiment-card-display">
                    <div class="experiment-header">
                        <h3 class="experiment-title">${this.renderWithDiff('title', exp.title, originalExp.title)}</h3>
                        <span class="archetype-badge archetype-${String(exp.archetype || 'discover').toLowerCase()}">${exp.archetype || 'Discover'}</span>
                    </div>
                    
                    <div class="experiment-body">
                        <div class="experiment-description">
                            <h4>What You'll Do:</h4>
                            <p>${this.renderWithDiff('description', this.getExperimentDescription(exp), this.getExperimentDescription(originalExp))}</p>
                        </div>
                        
                        <div class="experiment-rationale">
                            <h4>Why This Fits You:</h4>
                            <p>${this.renderWithDiff('rationale', exp.rationale || '', originalExp.rationale || '')}</p>
                        </div>
                        
                        <div class="experiment-steps">
                            <h4>Steps:</h4>
                            <ol>
                                ${(exp.steps || []).map((step, idx) => 
                                    `<li>${this.renderWithDiff(`steps[${idx}]`, step, (originalExp.steps || [])[idx] || '')}</li>`
                                ).join('')}
                            </ol>
                        </div>
                        
                        <div class="experiment-effort">
                            <div class="effort-display">
                                <span class="effort-time">${this.renderWithDiff('timeHours', exp.effort?.timeHours || 0, originalExp.effort?.timeHours || 0)}h</span>
                                <span class="effort-budget">$${this.renderWithDiff('budgetUSD', exp.effort?.budgetUSD || 0, originalExp.effort?.budgetUSD || 0)}</span>
                                <span class="risk-level risk-${String(exp.riskLevel || 'medium').toLowerCase()}">${this.renderWithDiff('riskLevel', exp.riskLevel || 'Medium', originalExp.riskLevel || 'Medium')}</span>
                            </div>
                        </div>
                        
                        <div class="success-criteria">
                            <h4>Success Criteria:</h4>
                            <ul>
                                ${(exp.successCriteria || []).map((criteria, idx) => 
                                    `<li>${this.renderWithDiff(`successCriteria[${idx}]`, criteria, (originalExp.successCriteria || [])[idx] || '')}</li>`
                                ).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            `;

            $('.experiment-display').html(experimentHTML);
        },

        /**
         * Render text with diff highlighting and HTML sanitization
         */
        renderWithDiff: function(field, current, original) {
            // Basic HTML sanitization - escape HTML entities
            const sanitizeCurrent = $('<div>').text(current).html();
            const sanitizeOriginal = $('<div>').text(original).html();
            
            if (sanitizeCurrent !== sanitizeOriginal) {
                return `<span class="diff-changed" data-field="${this.sanitizeAttr(field)}">${sanitizeCurrent}</span>`;
            }
            return sanitizeCurrent;
        },
        
        /**
         * Sanitize HTML attribute values
         */
        sanitizeAttr: function(value) {
            return $('<div>').attr('data-temp', value).attr('data-temp');
        },
        
        /**
         * Extract the main description from an experiment object
         * Uses the same logic as generateEngagingDescription from LabModeApp
         */
        getExperimentDescription: function(exp) {
            if (!exp) return 'No description available';
            
            // If experiment has an explicit summary, use that
            if (exp.summary) {
                return exp.summary;
            }
            
            // If experiment has steps, create a concise summary from them
            if (exp.steps && Array.isArray(exp.steps) && exp.steps.length > 0) {
                return this.createActionSummary(exp.steps);
            }
            
            // Fallback to title or other fields
            if (exp.title) {
                // Remove "Experiment" suffix and convert to action phrase
                const cleanTitle = exp.title.replace(/\s*Experiment:?\s*/i, '').trim();
                return cleanTitle || 'An engaging experiment tailored to your interests.';
            }
            
            return exp.description || exp.rationale || 'An engaging experiment tailored to your interests.';
        },
        
        /**
         * Create a concise action summary from experiment steps
         * Copied from LabModeApp.createActionSummary
         */
        createActionSummary: function(steps) {
            if (!steps || steps.length === 0) {
                return 'Complete a series of personalized activities.';
            }
            
            // Take first 1-2 steps to create summary
            const relevantSteps = steps.slice(0, 2);
            
            // Clean and simplify each step
            const cleanedSteps = relevantSteps.map(step => {
                return step
                    .replace(/^(Choose|Select|Pick|Identify)\s+/, 'Choose ') // Normalize selection verbs
                    .replace(/^(Create|Make|Build|Design)\s+/, 'Create ') // Normalize creation verbs
                    .replace(/^(Write|Document|Journal)\s+/, 'Write ') // Normalize writing verbs
                    .replace(/^(Research|Study|Explore|Investigate)\s+/, 'Research ') // Normalize research verbs
                    .replace(/^(Practice|Try|Attempt|Test)\s+/, 'Practice ') // Normalize practice verbs
                    .replace(/^(Reflect|Think|Consider)\s+/, 'Reflect ') // Normalize reflection verbs
                    .replace(/\s+about\s+your\s+curiosit(y|ies)/, ' about your interests') // Simplify curiosity references
                    .replace(/\s+related\s+to\s+your\s+curiosit(y|ies)/, ' related to your interests') // Simplify curiosity references
                    .replace(/\s+from\s+your\s+role\s+models?/, ' from people you admire') // Simplify role model references
                    .trim();
            });
            
            if (cleanedSteps.length === 1) {
                return cleanedSteps[0].endsWith('.') ? cleanedSteps[0] : cleanedSteps[0] + '.';
            } else if (cleanedSteps.length === 2) {
                // Join with "then" for natural flow
                const firstStep = cleanedSteps[0].replace(/\.$/, ''); // Remove trailing period if present
                const secondStep = cleanedSteps[1].replace(/\.$/, ''); // Remove trailing period if present
                return firstStep + ', then ' + secondStep.toLowerCase() + '.';
            } else {
                const firstStep = cleanedSteps[0].replace(/\.$/, ''); // Remove trailing period if present
                return firstStep + ', and more.';
            }
        },

        /**
         * Render modifier controls in the right column
         */
        renderModifierControls: function() {
            const context = this.userContext || {};
            
            const modifierGroupsHTML = `
                <!-- Cost Controls -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">💸</span>
                        <span class="modifier-label">Cost</span>
                    </div>
                    <div class="modifier-buttons">
                        <button class="iteration-modifier-btn" data-mod-type="Cost" data-mod-value="Cheaper" title="Reduce budgetUSD and/or use free resources">Cheaper</button>
                        <button class="iteration-modifier-btn" data-mod-type="Cost" data-mod-value="More Expensive" title="Allow paid tools or materials">More Expensive</button>
                        <button class="iteration-modifier-btn" data-mod-type="Cost" data-mod-value="Max Value" title="Optimize impact per dollar">Max Value</button>
                    </div>
                </div>

                <!-- Time Controls -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">⏱️</span>
                        <span class="modifier-label">Time</span>
                    </div>
                    <div class="modifier-buttons">
                        <button class="iteration-modifier-btn" data-mod-type="Time" data-mod-value="Faster" title="Complete in less time; simplify steps">Faster</button>
                        <button class="iteration-modifier-btn" data-mod-type="Time" data-mod-value="Slower" title="Allow more depth; extend time block">Slower</button>
                        <button class="iteration-modifier-btn" data-mod-type="Time" data-mod-value="Long-term" title="Convert to a repeatable multi-week plan">Long-term</button>
                    </div>
                </div>

                <!-- Role Models -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">⭐</span>
                        <span class="modifier-label">Role Models</span>
                    </div>
                    <div class="modifier-buttons">
                        ${this.renderRoleModelButtons()}
                    </div>
                </div>

                <!-- Style Controls -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">🎛️</span>
                        <span class="modifier-label">Style</span>
                    </div>
                    <div class="modifier-buttons">
                        <button class="iteration-modifier-btn" data-mod-type="Style" data-mod-value="More Creative">More Creative</button>
                        <button class="iteration-modifier-btn" data-mod-type="Style" data-mod-value="More Structured">More Structured</button>
                        <button class="iteration-modifier-btn" data-mod-type="Style" data-mod-value="More Social">More Social</button>
                    </div>
                </div>

                <!-- Constraints Controls -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">🧭</span>
                        <span class="modifier-label">Constraints</span>
                    </div>
                    <div class="modifier-buttons">
                        <button class="iteration-modifier-btn" data-mod-type="Constraint" data-mod-value="Safer">Safer</button>
                        <button class="iteration-modifier-btn" data-mod-type="Constraint" data-mod-value="Riskier">Riskier</button>
                        <button class="iteration-modifier-btn" data-mod-type="Constraint" data-mod-value="Solo">Solo</button>
                        <button class="iteration-modifier-btn" data-mod-type="Constraint" data-mod-value="Group">Group</button>
                    </div>
                </div>

                <!-- Curiosity Controls -->
                <div class="modifier-group">
                    <div class="modifier-group-header">
                        <span class="modifier-emoji">🎯</span>
                        <span class="modifier-label">Curiosity / Context</span>
                    </div>
                    <div class="modifier-buttons">
                        ${this.renderCuriosityButtons()}
                    </div>
                </div>
            `;

            $('.iteration-modifier-groups').html(modifierGroupsHTML);
        },

        /**
         * Render role model buttons based on user context
         */
        renderRoleModelButtons: function() {
            const roleModels = this.userContext?.roleModels || [];
            
            if (roleModels.length === 0) {
                return '<p class="modifier-empty">No role models configured</p>';
            }

            return roleModels.slice(0, 5).map(model => 
                `<button class="iteration-modifier-btn" data-mod-type="RoleModel" data-mod-value="${model}">${model}</button>`
            ).join('');
        },

        /**
         * Render curiosity buttons based on user context
         */
        renderCuriosityButtons: function() {
            const curiosities = this.userContext?.curiosities || [];
            
            if (curiosities.length === 0) {
                return '<p class="modifier-empty">No curiosities configured</p>';
            }

            return curiosities.slice(0, 4).map(curiosity => 
                `<button class="iteration-modifier-btn modifier-btn-chip" data-mod-type="Curiosity" data-mod-value="${curiosity}">${curiosity}</button>`
            ).join('');
        },

        /**
         * Update the version breadcrumb with clickable navigation
         */
        updateBreadcrumb: function() {
            const totalVersions = this.iterations.length;
            const currentVersion = this.currentIteration + 1;
            
            let breadcrumbHTML = '<div class="breadcrumb-versions">';
            for (let i = 0; i < totalVersions; i++) {
                const isActive = i === this.currentIteration;
                breadcrumbHTML += `<button type="button" class="version-link ${isActive ? 'active' : ''}" data-version="${i}" title="View version ${i + 1}">v${i + 1}</button>`;
                if (i < totalVersions - 1) {
                    breadcrumbHTML += '<span class="version-separator"> / </span>';
                }
            }
            breadcrumbHTML += '</div>';
            
            // Add debug toggle if multiple versions exist
            if (totalVersions > 1) {
                breadcrumbHTML += '<button type="button" class="debug-toggle-btn" title="Show AI request debug info">🐛 Debug</button>';
            }
            
            $('.iteration-breadcrumb').html(breadcrumbHTML);
            
            // Bind version navigation events
            $('.version-link').off('click').on('click', (e) => {
                const versionIndex = parseInt($(e.target).data('version'));
                this.navigateToVersion(versionIndex);
            });
            
            // Bind debug toggle event
            $('.debug-toggle-btn').off('click').on('click', () => {
                this.toggleDebugPanel();
            });
        },

        /**
         * Navigate to a specific version in the iteration history
         */
        navigateToVersion: function(versionIndex) {
            if (versionIndex < 0 || versionIndex >= this.iterations.length) {
                console.error('Invalid version index:', versionIndex);
                return;
            }
            
            console.log('Navigating to version:', versionIndex + 1);
            
            this.currentIteration = versionIndex;
            this.currentExperiment = $.extend(true, {}, this.iterations[versionIndex]);
            
            // Update UI
            this.populateModal();
            
            // Show a brief notification
            this.showVersionNotification(versionIndex + 1);
        },
        
        /**
         * Show notification when switching versions
         */
        showVersionNotification: function(versionNumber) {
            // Remove existing notification
            $('.version-notification').remove();
            
            const notification = `
                <div class="version-notification">
                    <span class="version-icon">↻</span>
                    Now viewing version ${versionNumber}
                </div>
            `;
            
            $('.iteration-breadcrumb').after(notification);
            
            // Auto-hide after 2 seconds
            setTimeout(() => {
                $('.version-notification').fadeOut(() => {
                    $('.version-notification').remove();
                });
            }, 2000);
        },
        
        /**
         * Toggle debug panel visibility
         */
        toggleDebugPanel: function() {
            const debugPanel = $('.iteration-debug-panel');
            
            if (debugPanel.length === 0) {
                // Create and show debug panel
                this.createDebugPanel();
            } else {
                // Toggle existing panel
                debugPanel.toggle();
                const isVisible = debugPanel.is(':visible');
                $('.debug-toggle-btn').text(isVisible ? '🐛 Hide Debug' : '🐛 Debug');
            }
        },
        
        /**
         * Create the debug panel showing AI request history
         */
        createDebugPanel: function() {
            const debugHistory = this.getDebugHistory();
            
            const debugHTML = `
                <div class="iteration-debug-panel">
                    <div class="debug-panel-header">
                        <h4>🐛 AI Request Debug History</h4>
                        <button type="button" class="debug-close-btn" title="Close debug panel">&times;</button>
                    </div>
                    <div class="debug-panel-content">
                        ${debugHistory.map((entry, index) => `
                            <div class="debug-entry" data-version="${index + 1}">
                                <div class="debug-entry-header">
                                    <h5>Version ${index + 1} → Version ${index + 2}</h5>
                                    <span class="debug-timestamp">${entry.timestamp}</span>
                                </div>
                                <div class="debug-entry-body">
                                    <div class="debug-section">
                                        <h6>Modifier Sent:</h6>
                                        <pre class="debug-code">${JSON.stringify(entry.modifier, null, 2)}</pre>
                                    </div>
                                    <div class="debug-section">
                                        <h6>System Prompt:</h6>
                                        <div class="debug-prompt">${this.truncateText(entry.systemPrompt, 300)}</div>
                                        ${entry.systemPrompt.length > 300 ? '<button class="debug-expand-btn" data-type="system" data-index="' + index + '">Show Full Prompt</button>' : ''}
                                    </div>
                                    <div class="debug-section">
                                        <h6>User Prompt Preview:</h6>
                                        <div class="debug-prompt">${this.truncateText(entry.userPrompt, 200)}</div>
                                        ${entry.userPrompt.length > 200 ? '<button class="debug-expand-btn" data-type="user" data-index="' + index + '">Show Full Prompt</button>' : ''}
                                    </div>
                                    <div class="debug-section">
                                        <h6>AI Response:</h6>
                                        <div class="debug-response">
                                            <strong>Changed Fields:</strong> ${entry.changedFields.join(', ')}<br>
                                            <strong>Calibration:</strong> ${entry.calibrationNotes || 'None'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            $('.iteration-breadcrumb').after(debugHTML);
            
            // Bind debug panel events
            $('.debug-close-btn').on('click', () => {
                $('.iteration-debug-panel').hide();
                $('.debug-toggle-btn').text('🐛 Debug');
            });
            
            // Bind expand button events
            $('.debug-expand-btn').on('click', (e) => {
                const $btn = $(e.target);
                const type = $btn.data('type');
                const index = $btn.data('index');
                const entry = debugHistory[index];
                const fullText = type === 'system' ? entry.systemPrompt : entry.userPrompt;
                
                $btn.prev('.debug-prompt').html(fullText);
                $btn.remove();
            });
            
            $('.debug-toggle-btn').text('🐛 Hide Debug');
        },
        
        /**
         * Get debug history for AI requests
         */
        getDebugHistory: function() {
            // Return stored debug entries from iterations
            return this.debugHistory || [];
        },
        
        /**
         * Truncate text for debug display
         */
        truncateText: function(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        },
        
        /**
         * Store debug information for an AI request
         */
        storeDebugInfo: function(modifier, systemPrompt, userPrompt, response) {
            if (!this.debugHistory) {
                this.debugHistory = [];
            }
            
            this.debugHistory.push({
                timestamp: new Date().toLocaleTimeString(),
                modifier: modifier,
                systemPrompt: systemPrompt,
                userPrompt: userPrompt,
                changedFields: response.changedFields || [],
                calibrationNotes: response.calibrationNotes
            });
            
            console.log('Debug info stored:', this.debugHistory.length, 'entries');
        },
        
        /**
         * Initialize the custom input section
         */
        initializeCustomInput: function() {
            console.log('Initializing custom input section');
            
            // Clear any previous input
            const $textarea = $('#iteration-custom-text');
            const $charCount = $('.char-count');
            const $customBtn = $('.iteration-custom-btn');
            
            $textarea.val('');
            $charCount.text('0');
            $customBtn.prop('disabled', true).addClass('disabled');
            
            console.log('Custom input initialized:', {
                textarea: $textarea.length,
                charCount: $charCount.length,
                button: $customBtn.length
            });
            
            // Trigger initial state check
            $textarea.trigger('input');
        },
        
        /**
         * Handle custom text input modifier
         */
        handleCustomModifier: function() {
            console.log('Handling custom modifier...');
            
            const customText = $('#iteration-custom-text').val().trim();
            console.log('Custom text:', customText, 'length:', customText.length);
            
            // Enhanced validation
            if (!customText) {
                console.warn('No custom text provided');
                this.showError('Please enter a custom modification request.');
                $('#iteration-custom-text').focus();
                return;
            }
            
            if (customText.length < 10) {
                console.warn('Custom text too short:', customText.length, 'characters');
                this.showError('Please provide more detail in your custom request (at least 10 characters).');
                $('#iteration-custom-text').focus();
                return;
            }
            
            if (customText.length > 500) {
                console.warn('Custom text too long:', customText.length, 'characters');
                this.showError('Custom request is too long. Please keep it under 500 characters.');
                $('#iteration-custom-text').focus();
                return;
            }
            
            // Check for potentially problematic content
            const lowercaseText = customText.toLowerCase();
            if (lowercaseText.includes('delete') || lowercaseText.includes('remove all')) {
                if (!confirm('Your request mentions deletion. Are you sure you want to proceed? This might significantly change your experiment.')) {
                    return;
                }
            }
            
            // Create modifier object
            const modifier = {
                kind: 'Custom',
                value: customText
            };
            
            console.log('Sending custom modifier:', modifier);
            
            // Disable input during processing
            $('#iteration-custom-text').prop('disabled', true);
            $('.iteration-custom-btn').prop('disabled', true).addClass('disabled');
            
            this.sendModifier(modifier);
            
            // Clear the textarea after successful sending
            $('#iteration-custom-text').val('').prop('disabled', false);
            $('.char-count').text('0');
            
            console.log('Custom input cleared after submission');
        },
        
        /**
         * Send a modifier to the backend for processing
         */
        sendModifier: function(modifier) {
            if (this.isLoading) {
                console.warn('Already processing a modifier');
                return;
            }

            console.log('Sending modifier:', modifier);
            
            // Validate required data before sending
            if (!this.currentExperiment) {
                console.error('No current experiment available');
                this.showError('No experiment data available. Please try refreshing the page.');
                return;
            }
            
            if (!window.labMode?.nonce) {
                console.error('No security nonce available');
                this.showError('Security token missing. Please refresh the page and try again.');
                return;
            }
            
            this.isLoading = true;
            this.showLoading(true);
            $('.iteration-modifier-btn').prop('disabled', true);

            const requestData = {
                action: 'mc_lab_iterate',
                nonce: window.labMode.nonce,
                currentExperiment: JSON.stringify(this.currentExperiment),
                modifier: JSON.stringify(modifier),
                userContext: JSON.stringify(this.userContext),
                includeDebug: true // Request debug info from backend
            };
            
            // Store modifier for debug purposes
            this.currentModifier = modifier;
            this.requestStartTime = Date.now();
            
            console.log('Request data:', {
                action: requestData.action,
                nonce: requestData.nonce ? 'Present' : 'Missing',
                experimentTitle: this.currentExperiment?.title || 'Unknown',
                modifierKind: modifier?.kind || 'Unknown',
                url: window.labMode?.ajaxUrl || '/wp-admin/admin-ajax.php'
            });

            $.ajax({
                url: window.labMode?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
                data: requestData,
                timeout: 45000, // Increased timeout for AI processing
                beforeSend: (xhr) => {
                    console.log('AJAX request starting...');
                },
                success: (response) => {
                    console.log('AJAX success:', response);
                    this.handleIterateSuccess(response);
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', { xhr, status, error, responseText: xhr.responseText });
                    this.handleIterateError(xhr, status, error);
                },
                complete: () => {
                    const duration = Date.now() - this.requestStartTime;
                    console.log('Request completed in', duration, 'ms');
                    this.isLoading = false;
                    this.showLoading(false);
                    $('.iteration-modifier-btn').prop('disabled', false);
                    
                    // Re-enable custom input if it was disabled
                    $('#iteration-custom-text').prop('disabled', false);
                    
                    // Re-validate custom input state
                    const customText = $('#iteration-custom-text').val().trim();
                    const $customBtn = $('.iteration-custom-btn');
                    if (customText.length > 0 && customText.length <= 500) {
                        $customBtn.prop('disabled', false).removeClass('disabled');
                    }
                }
            });
        },

        /**
         * Handle successful iteration response
         */
        handleIterateSuccess: function(response) {
            if (response.success && response.data) {
                const { experiment: revisedExperiment, calibrationNotes, changedFields, debug } = response.data;
                
                // Store debug information if available
                if (debug && this.currentModifier) {
                    this.storeDebugInfo(
                        this.currentModifier,
                        debug.systemPrompt || 'System prompt not available',
                        debug.userPrompt || 'User prompt not available',
                        { changedFields, calibrationNotes }
                    );
                    
                    // Update admin debug panel if it's visible
                    this.updateAdminDebugPanel();
                }
                
                // Add to iterations history
                this.iterations.push($.extend(true, {}, revisedExperiment));
                this.currentIteration = this.iterations.length - 1;
                this.currentExperiment = $.extend(true, {}, revisedExperiment);
                
                // Update UI
                this.populateModal();
                
                // Show calibration notes if available
                if (calibrationNotes) {
                    $('.iteration-calibration-notes').text(calibrationNotes);
                    $('.iteration-diff-notice').show();
                    
                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        $('.iteration-diff-notice').fadeOut();
                    }, 5000);
                }
                
                console.log('Iteration successful', { revisedExperiment, calibrationNotes, changedFields, debugStored: !!debug });
            } else {
                this.handleIterateError(null, 'error', response.data || 'Unknown error occurred');
            }
        },

        /**
         * Handle iteration error
         */
        handleIterateError: function(xhr, status, error) {
            console.error('Iteration failed:', { xhr, status, error, responseText: xhr?.responseText });
            
            let errorMessage = 'Failed to iterate experiment. ';
            let debugInfo = {
                status: status,
                error: error,
                statusCode: xhr?.status,
                responseText: xhr?.responseText
            };
            
            if (status === 'timeout') {
                errorMessage += 'The AI processing took too long. This can happen with complex requests. Please try again with a simpler modification.';
            } else if (status === 'abort') {
                errorMessage += 'Request was cancelled. Please try again.';
            } else if (xhr?.status === 403) {
                errorMessage += 'Permission denied. Please refresh the page and try again.';
            } else if (xhr?.status === 404) {
                errorMessage += 'Service not found. Please contact support if this persists.';
            } else if (xhr?.status >= 500) {
                errorMessage += 'Server error occurred. Please try again in a moment.';
            } else if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage += xhr.responseJSON.data;
            } else if (xhr?.responseText) {
                try {
                    const parsedResponse = JSON.parse(xhr.responseText);
                    if (parsedResponse.data) {
                        errorMessage += parsedResponse.data;
                    } else {
                        errorMessage += 'Unexpected server response.';
                    }
                } catch (e) {
                    errorMessage += 'Server returned an invalid response.';
                }
            } else {
                errorMessage += 'Please check your internet connection and try again.';
            }
            
            // Add modifier type to error message for context
            if (this.currentModifier) {
                errorMessage += ` (Modifier: ${this.currentModifier.kind})`;
            }
            
            console.error('Error details for debugging:', debugInfo);
            this.showError(errorMessage);
        },

        /**
         * Show loading state
         */
        showLoading: function(show) {
            if (show) {
                $('.iteration-loading').show();
                $('.iteration-modifier-groups').hide();
            } else {
                $('.iteration-loading').hide();
                $('.iteration-modifier-groups').show();
            }
        },

        /**
         * Show error message in the modal
         */
        showError: function(message) {
            // Remove any existing error
            $('.iteration-error').remove();
            
            const errorHTML = `
                <div class="iteration-error" role="alert">
                    <strong>Error:</strong> ${message}
                    <button type="button" class="error-dismiss" aria-label="Dismiss error">&times;</button>
                </div>
            `;
            
            $('.iteration-content').prepend(errorHTML);
            
            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                $('.iteration-error').fadeOut(() => {
                    $('.iteration-error').remove();
                });
            }, 10000);
            
            // Manual dismiss
            $(document).on('click', '.error-dismiss', () => {
                $('.iteration-error').fadeOut(() => {
                    $('.iteration-error').remove();
                });
            });
        },

        /**
         * Reset experiment to original state
         */
        resetToOriginal: function() {
            if (confirm('Reset to the original experiment? This will lose all iterations and debug history.')) {
                this.currentExperiment = $.extend(true, {}, this.originalExperiment);
                this.iterations = [$.extend(true, {}, this.originalExperiment)];
                this.currentIteration = 0;
                this.debugHistory = []; // Clear debug history
                
                this.populateModal();
                $('.iteration-diff-notice').hide();
                $('.iteration-debug-panel').remove(); // Remove debug panel
                
                console.log('Reset to original experiment');
            }
        },

        /**
         * Save changes and apply to the main experiment list
         */
        saveAndApply: function() {
            console.log('Saving and applying changes...');
            
            // Clear all diff highlighting before saving
            this.clearDiffHighlighting();
            
            // Call back to LabModeApp to update the experiment
            if (window.LabModeApp && typeof window.LabModeApp.updateExperiment === 'function') {
                window.LabModeApp.updateExperiment(this.currentIndex, this.currentExperiment);
            } else {
                console.warn('LabModeApp.updateExperiment not available');
            }
            
            this.close();
            console.log('Applied changes and closed panel');
        },
        
        /**
         * Clear all diff highlighting from the experiment display
         */
        clearDiffHighlighting: function() {
            console.log('Clearing diff highlighting...');
            
            // Remove all diff-changed classes and unwrap content
            $('.diff-changed').each(function() {
                const $this = $(this);
                const originalText = $this.text().replace('✏️', '').trim(); // Remove icon
                $this.replaceWith(originalText);
            });
            
            // Also update the current experiment to remove any diff markers
            this.originalExperiment = $.extend(true, {}, this.currentExperiment);
            
            console.log('Diff highlighting cleared');
        },

        /**
         * Get user context from LabModeApp or build default
         */
        getUserContext: function() {
            if (window.LabModeApp) {
                const profileData = window.LabModeApp.profileData;
                const qualifiers = window.LabModeApp.qualifiers;
                
                return {
                    mi_top3: profileData?.mi_results?.slice(0, 3) || [],
                    cdt_bottom2: profileData?.cdt_results?.slice(-2) || [],
                    curiosities: qualifiers?.curiosity?.curiosities || [],
                    roleModels: qualifiers?.curiosity?.roleModels || [],
                    constraints: qualifiers?.curiosity?.constraints || {}
                };
            }
            
            return {
                mi_top3: [],
                cdt_bottom2: [],
                curiosities: [],
                roleModels: [],
                constraints: {}
            };
        }
    };

})(jQuery);