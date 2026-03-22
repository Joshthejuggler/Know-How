/**
 * AI Coach Lab Mode Frontend
 * Handles the progressive workflow: Import → Qualify → Generate → Execute → Reflect → Recalibrate
 * Version: 2.1.0 - Fixed career explanation quote handling
 */

(function ($) {
    'use strict';

    // Lab Mode main application
    window.LabModeApp = {
        currentStep: 'landing',
        profileData: null,
        qualifiers: null,
        experiments: [],
        compactMode: false,
        careerExplanationCache: {},
        careerLayout: localStorage.getItem('career_layout') || 'cards',
        mindMapState: {
            centerId: 'seed',
            nodes: {},
            edges: [],
            openRequests: new Set(),
            history: [],
            expandedNodes: new Set()
        },
        savedCareers: new Set(),

        // Initialize the application
        init: function () {
            this.bindEvents();
            this.loadLandingView();
            // Check admin status after DOM is ready
            setTimeout(() => {
                this.checkAdminStatus();
            }, 100);
            window.LabModeAppInitialized = true;
        },

        // Bind UI events
        bindEvents: function () {
            // Unbind first to prevent duplicate event handlers
            $(document).off('click', '.lab-start-btn');
            $(document).off('click', '.lab-view-history-btn');
            $(document).off('click', '.lab-generate-experiments-btn');
            $(document).off('click', '.lab-start-experiment-btn');
            $(document).off('click', '.lab-reflect-btn');
            $(document).off('click', '.lab-iterate-btn');
            $(document).off('click', '.lab-regenerate-ai-btn');
            $(document).off('submit', '.lab-qualifiers-form');
            $(document).off('submit', '.lab-reflection-form');
            $(document).off('click', '.quick-select-btn');
            $(document).off('click', '.career-action-btn');
            $(document).off('click', '.career-action-link');

            // Bind events
            $(document).on('click', '.lab-start-btn', this.startProfileInputs.bind(this));
            $(document).on('click', '.lab-view-history-btn', this.showHistory.bind(this));
            $(document).on('click', '.lab-generate-experiments-btn', this.generateExperiments.bind(this));
            $(document).on('click', '.lab-start-experiment-btn', this.startExperiment.bind(this));
            $(document).on('click', '.lab-reflect-btn', this.showReflectionForm.bind(this));
            $(document).on('click', '.lab-iterate-btn', this.openIterationPanel.bind(this));
            $(document).on('click', '.lab-regenerate-ai-btn', this.regenerateAiVariant.bind(this));
            $(document).on('submit', '.lab-qualifiers-form', this.saveQualifiers.bind(this));
            $(document).on('submit', '.lab-reflection-form', this.submitReflection.bind(this));
            $(document).on('click', '.quick-select-btn', this.handleQuickSelect.bind(this));
            $(document).on('click', '.career-action-btn', this.handleCareerFeedback.bind(this));
            $(document).on('click', '.career-action-link', this.handleCareerFeedback.bind(this));
        },

        // Show loading spinner
        showLoading: function (message) {
            // Remove typeform body class when showing loading
            $('body').removeClass('lab-typeform-active');

            $('#lab-mode-app').html(`
                <div class="lab-mode-loading">
                    <p>${message || 'Loading...'}</p>
                    <div class="loading-spinner"></div>
                </div>
            `);
        },

        // Show error message
        showError: function (message) {
            // Remove typeform body class when showing error
            $('body').removeClass('lab-typeform-active');

            $('#lab-mode-app').html(`
                <div class="lab-mode-error">
                    <h3>Error</h3>
                    <p>${message}</p>
                    <button class="lab-retry-btn" onclick="LabModeApp.init()">Try Again</button>
                </div>
            `);
        },

        // Check if current user is admin and show admin controls
        checkAdminStatus: function () {
            console.log('Checking admin status...', {
                bodyClasses: document.body.classList.toString(),
                url: window.location.href,
                labMode: typeof labMode !== 'undefined' ? labMode : 'undefined',
                isAdmin: labMode && labMode.isAdmin
            });

            // Check if admin controls should be shown (look for admin indicators)
            // WordPress admins often have body class 'logged-in' and other indicators
            const isAdmin = document.body.classList.contains('wp-admin') ||
                window.location.href.includes('wp-admin') ||
                (labMode && labMode.isAdmin === true) ||
                this.detectAdminCapabilities();

            console.log('Admin status result:', isAdmin);

            if (isAdmin) {
                console.log('Admin detected, showing model selector');
                this.showAdminControls();
            } else {
                console.log('Non-admin user, hiding admin controls');
            }
        },

        // Detect admin capabilities (fallback method)
        detectAdminCapabilities: function () {
            // Check for common WordPress admin indicators
            return document.querySelector('#wpadminbar') !== null ||
                document.body.classList.contains('admin-bar') ||
                document.body.classList.contains('wp-admin');
        },

        // Show admin controls
        showAdminControls: function () {
            console.log('Showing admin controls...');
            const adminControls = document.getElementById('lab-admin-controls');
            console.log('Admin controls element:', adminControls);

            if (adminControls) {
                adminControls.style.display = 'block';
                console.log('Admin controls made visible');

                // Set default model selection from backend
                const modelSelect = document.getElementById('lab-model-select');
                console.log('Model select element:', modelSelect);
                if (modelSelect && labMode && labMode.defaultModel) {
                    modelSelect.value = labMode.defaultModel;
                    console.log('Set default model to:', labMode.defaultModel);
                }
            } else {
                console.error('Admin controls element not found!');
            }
        },

        // Get selected GPT model (for admin users)
        getSelectedModel: function () {
            const modelSelect = document.getElementById('lab-model-select');
            return modelSelect ? modelSelect.value : 'gpt-4o-mini';
        },

        // Load the landing view
        loadLandingView: function () {
            const html = `
                <div class="lab-mode-landing">
                    <div class="lab-mode-hero">
                        <div class="lab-hero-icon">🧪</div>
                        <h2>Lab Mode</h2>
                    </div>
                    
                    <div class="lab-intro-content">
                        <div class="lab-mode-explanation" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px; text-align: left;">
                            <h3 style="margin: 0 0 8px 0; font-size: 1.1rem; color: #1e293b;">Your Personal Sandbox</h3>
                            <p style="margin: 0; color: #64748b; line-height: 1.5;">
                                While <strong>Assigned Experiments</strong> are guided by your organization, <strong>Lab Mode</strong> is for your own self-directed growth. Use this space to design your own experiments, explore your curiosity, and test new approaches safely.
                            </p>
                        </div>
                        <p class="lab-intro-main">Create personalized experiments based on your unique strengths, curiosities, and growth areas.</p>
                        
                        <div class="lab-features">
                            <div class="lab-feature">
                                <div class="lab-feature-icon">🎯</div>
                                <div class="lab-feature-text">
                                    <strong>Tailored to You</strong><br>
                                    <span>Based on your assessment results</span>
                                </div>
                            </div>
                            <div class="lab-feature">
                                <div class="lab-feature-icon">⏱️</div>
                                <div class="lab-feature-text">
                                    <strong>Quick & Focused</strong><br>
                                    <span>Designed for 1-week experiments</span>
                                </div>
                            </div>
                            <div class="lab-feature">
                                <div class="lab-feature-icon">🔄</div>
                                <div class="lab-feature-text">
                                    <strong>Iterative Learning</strong><br>
                                    <span>Refine based on your feedback</span>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Admin Model Selector (only shown to admins) -->
                    <div class="lab-admin-controls" id="lab-admin-controls" style="display: none;">
                        <div class="lab-admin-section">
                            <div class="lab-admin-header">
                                <span class="lab-admin-icon">⚙️</span>
                                <div class="lab-admin-title">Admin Controls</div>
                            </div>
                            <div class="lab-model-selector">
                                <label for="lab-model-select" class="lab-model-label">GPT Model:</label>
                                <select id="lab-model-select" class="lab-model-select">
                                    <option value="gpt-4o-mini">GPT-4o mini (faster, cheaper)</option>
                                    <option value="gpt-4o">GPT-4o (more capable, expensive)</option>
                                </select>
                                <div class="lab-model-note">Override default model for this session</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="lab-landing-actions">
                        <button class="lab-start-btn lab-btn lab-btn-primary">Start Lab Mode</button>
                        <button class="lab-view-history-btn lab-btn lab-btn-secondary">View Past Experiments</button>
                    </div>
                </div>
            `;
            $('#lab-mode-app').html(html);
            this.currentStep = 'landing';

            // Remove body classes when returning to landing
            $('body').removeClass('lab-mode-active lab-typeform-active');

            // Check admin status after rendering landing view
            this.checkAdminStatus();
        },

        // Start the profile inputs workflow
        startProfileInputs: function (e) {
            e.preventDefault();

            console.log('Start Lab Mode clicked');

            // Show simple loading while getting profile data
            this.showLoading('Loading your assessment data...');
            this.loadProfileData();
        },

        // Load user profile data from assessments
        loadProfileData: function () {
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_get_profile_data',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.profileData = response.data;
                        // Go directly to typeform
                        this.showProfileInputsForm();
                    } else {
                        this.showError(response.data || 'Failed to load profile data');
                    }
                },
                error: () => {
                    this.showError('Network error while loading profile data');
                }
            });
        },

        // Show the typeform-style profile inputs
        showProfileInputsForm: function () {
            const mi = this.profileData.mi_results || [];
            const cdt = this.profileData.cdt_results || [];

            // Get top 3 MI and lowest 2 CDT for qualifiers
            const topMI = mi.slice(0, 3);
            const bottomCDT = cdt.slice(-2);

            // Initialize typeform state
            this.initializeTypeform(topMI, bottomCDT);
        },

        // Initialize typeform-style interface
        initializeTypeform: function (topMI, bottomCDT, clearExisting = false) {
            // Try to restore previous state if available
            const savedState = this.loadTypeformState();

            if (!clearExisting && savedState && savedState.topMI && savedState.bottomCDT) {
                // Restore previous answers and saved progress, but ALWAYS start at welcome screen
                this.typeformState = {
                    currentQuestionIndex: 0, // Always start at welcome screen
                    answers: savedState.answers || {}, // Restore saved answers
                    topMI: topMI, // Update with current MI data in case it changed
                    bottomCDT: bottomCDT, // Update with current CDT data
                    questions: this.buildQuestionFlow(topMI, bottomCDT),
                    // Store the saved progress separately for the resume functionality
                    savedQuestionIndex: savedState.currentQuestionIndex || 0
                };
                console.log('Restored answers from previous session, starting at welcome screen');
                console.log('Saved progress was at question:', savedState.currentQuestionIndex);
            } else {
                // Create fresh state
                this.typeformState = {
                    currentQuestionIndex: 0,
                    answers: {},
                    topMI: topMI,
                    bottomCDT: bottomCDT,
                    questions: this.buildQuestionFlow(topMI, bottomCDT),
                    savedQuestionIndex: null
                };
                console.log('Initializing typeform with clean state:', this.typeformState);
            }

            this.renderTypeform();
        },

        // Save typeform state to localStorage
        saveTypeformState: function () {
            if (this.typeformState) {
                try {
                    const stateToSave = {
                        currentQuestionIndex: this.typeformState.currentQuestionIndex,
                        answers: this.typeformState.answers,
                        topMI: this.typeformState.topMI,
                        bottomCDT: this.typeformState.bottomCDT,
                        timestamp: Date.now()
                    };
                    localStorage.setItem('labModeTypeformState', JSON.stringify(stateToSave));
                    console.log('Saved typeform state to localStorage');
                } catch (error) {
                    console.error('Failed to save typeform state:', error);
                }
            }
        },

        // Load typeform state from localStorage
        loadTypeformState: function () {
            try {
                const savedState = localStorage.getItem('labModeTypeformState');
                if (savedState) {
                    const parsedState = JSON.parse(savedState);

                    // Check if state is not too old (7 days)
                    const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds
                    if (parsedState.timestamp && (Date.now() - parsedState.timestamp) < maxAge) {
                        console.log('Loaded typeform state from localStorage');
                        return parsedState;
                    } else {
                        console.log('Saved state is too old, ignoring');
                        this.clearTypeformState();
                    }
                }
            } catch (error) {
                console.error('Failed to load typeform state:', error);
            }
            return null;
        },

        // Clear typeform state from localStorage
        clearTypeformState: function () {
            try {
                localStorage.removeItem('labModeTypeformState');
                console.log('Cleared typeform state from localStorage');
            } catch (error) {
                console.error('Failed to clear typeform state:', error);
            }
        },

        // Clear saved progress and restart with fresh state
        clearAndRestart: function () {
            // Clear saved state
            this.clearTypeformState();

            // Reinitialize with clean state
            const mi = this.profileData.mi_results || [];
            const cdt = this.profileData.cdt_results || [];
            const topMI = mi.slice(0, 3);
            const bottomCDT = cdt.slice(-2);

            this.initializeTypeform(topMI, bottomCDT, true); // Force clear existing
        },

        // Resume from saved progress (go to the saved question index)
        resumeFromSavedProgress: function () {
            if (this.typeformState.savedQuestionIndex && this.typeformState.savedQuestionIndex > 0) {
                // Safety check - don't go past the last question
                const maxIndex = this.typeformState.questions.length - 1;
                const targetIndex = Math.min(this.typeformState.savedQuestionIndex, maxIndex);

                console.log('Resuming from saved progress at question:', targetIndex);
                this.typeformState.currentQuestionIndex = targetIndex;
                this.renderTypeform();
            } else {
                // No saved progress, just go to first real question
                this.typeformState.currentQuestionIndex = 1;
                this.renderTypeform();
            }
        },

        // Build the question flow based on user data
        buildQuestionFlow: function (topMI, bottomCDT) {
            const questions = [];

            // Welcome screen
            questions.push({
                id: 'welcome',
                type: 'welcome',
                title: '🧪 Lab Mode',
                subtitle: 'Let\'s create personalized experiments based on your strengths and curiosities. This will take about 3 minutes.',
                buttonText: 'Start Questions'
            });

            // MI questions - What you enjoy
            topMI.forEach((mi, index) => {
                questions.push({
                    id: `mi_enjoy_${mi.key}`,
                    type: 'textarea',
                    title: `What do you enjoy about ${mi.label}?`,
                    subtitle: `You scored high on ${mi.label}. Tell us what aspects you find most enjoyable.`,
                    placeholder: 'Type your thoughts here...',
                    examples: this.getMIExamples(mi.key, 'enjoy'),
                    required: false
                });
            });

            // MI questions - What you currently do
            topMI.forEach((mi, index) => {
                questions.push({
                    id: `mi_doing_${mi.key}`,
                    type: 'textarea',
                    title: `How do you currently use ${mi.label}?`,
                    subtitle: `What habits, activities, or projects involve your ${mi.label} strength?`,
                    placeholder: 'Describe your current activities...',
                    examples: this.getMIExamples(mi.key, 'doing'),
                    required: false
                });
            });

            // CDT growth challenges
            bottomCDT.forEach((cdt, index) => {
                questions.push({
                    id: `cdt_challenge_${cdt.key}`,
                    type: 'multiple_choice',
                    title: `${cdt.label}: Choose your growth focus`,
                    subtitle: `This was your lowest CDT score. Which challenge resonates most?`,
                    options: this.getCDTChallengeOptions(cdt.key),
                    required: true
                });
            });

            // Curiosity areas
            questions.push({
                id: 'curiosity_areas',
                type: 'multiple_input',
                title: 'What are you curious about right now?',
                subtitle: 'Share 2-3 things you\'d love to learn or explore. These help us craft relevant experiments.',
                inputs: [
                    { id: 'curiosity_1', placeholder: 'First curiosity area...', required: true },
                    { id: 'curiosity_2', placeholder: 'Second curiosity area...', required: true },
                    { id: 'curiosity_3', placeholder: 'Third curiosity area...', required: false }
                ],
                examples: this.getCuriosityExamples()
            });

            // Role models - enhanced discovery
            questions.push({
                id: 'role_models',
                type: 'role_model_discovery',
                title: 'Who are your role models?',
                subtitle: 'Let our AI help you discover inspiring people who share similar qualities with those you already admire.',
                required: false
            });

            // Constraints - Risk tolerance
            questions.push({
                id: 'risk_tolerance',
                type: 'slider',
                title: 'How do you approach new challenges?',
                subtitle: 'This helps us balance comfort zone stretching with achievable goals.',
                min: 0,
                max: 100,
                value: 50,
                leftLabel: 'Cautious',
                rightLabel: 'Bold',
                required: true
            });

            // Constraints - Budget
            questions.push({
                id: 'budget',
                type: 'slider',
                title: 'What\'s your experiment budget?',
                subtitle: 'Most experiments cost little to nothing, but some might involve books, courses, or tools.',
                min: 0,
                max: 200,
                value: 50,
                leftLabel: '$0',
                rightLabel: '$200+',
                valuePrefix: '$',
                required: true
            });

            // Constraints - Time
            questions.push({
                id: 'time_per_week',
                type: 'slider',
                title: 'How much time can you commit weekly?',
                subtitle: 'Be realistic - consistency matters more than duration.',
                min: 1,
                max: 10,
                value: 3,
                leftLabel: '1h',
                rightLabel: '10h+',
                valueSuffix: 'h',
                required: true
            });

            // Constraints - Solo vs Group
            questions.push({
                id: 'solo_group',
                type: 'slider',
                title: 'Do you prefer working solo or with others?',
                subtitle: 'This affects whether we suggest individual activities or group/community-based experiments.',
                min: 0,
                max: 100,
                value: 50,
                leftLabel: 'Solo',
                rightLabel: 'Group',
                required: true
            });

            return questions;
        },

        // Render the typeform interface
        renderTypeform: function () {
            const currentQuestion = this.typeformState.questions[this.typeformState.currentQuestionIndex];
            const isLastQuestion = this.typeformState.currentQuestionIndex === this.typeformState.questions.length - 1;

            // Calculate progress excluding the welcome screen
            const totalQuestions = this.typeformState.questions.length - 1; // Exclude welcome screen
            const currentStepNumber = Math.max(1, this.typeformState.currentQuestionIndex); // Start at 1 for non-welcome screens
            const progressPercent = this.typeformState.currentQuestionIndex === 0 ? 0 : ((this.typeformState.currentQuestionIndex - 1) / (totalQuestions - 1)) * 100;

            let questionHtml = '';

            // Render different question types
            switch (currentQuestion.type) {
                case 'welcome':
                    questionHtml = this.renderWelcomeScreen(currentQuestion);
                    break;
                case 'textarea':
                    questionHtml = this.renderTextareaQuestion(currentQuestion);
                    break;
                case 'multiple_choice':
                    questionHtml = this.renderMultipleChoiceQuestion(currentQuestion);
                    break;
                case 'multiple_input':
                    questionHtml = this.renderMultipleInputQuestion(currentQuestion);
                    break;
                case 'slider':
                    questionHtml = this.renderSliderQuestion(currentQuestion);
                    break;
                case 'role_model_discovery':
                    questionHtml = this.renderRoleModelDiscoveryQuestion(currentQuestion);
                    break;
            }

            // Hide footer on welcome screen for fresh starts
            const isWelcomeScreen = currentQuestion.type === 'welcome';
            const hasExistingAnswers = this.typeformState.answers && Object.keys(this.typeformState.answers).length > 0;
            const showFooter = !isWelcomeScreen || hasExistingAnswers;

            const footerHtml = showFooter ? `
                <div class="lab-typeform-footer">
                    <div class="lab-typeform-nav">
                        <div class="lab-nav-left">
                            <button class="lab-nav-back" ${this.typeformState.currentQuestionIndex === 0 ? 'disabled' : ''} data-action="back">
                                ← Back
                            </button>
                        </div>
                        
                        <div class="lab-progress-container">
                            <div class="lab-progress-bar">
                                <div class="lab-progress-fill" style="width: ${progressPercent}%"></div>
                            </div>
                            <div class="lab-progress-text">${currentStepNumber} of ${totalQuestions}</div>
                        </div>
                        
                        <button class="lab-nav-forward" id="next-btn" data-action="next" ${currentQuestion.type === 'welcome' ? '' : 'disabled'}>
                            ${isLastQuestion ? 'Generate Experiments' : (currentQuestion.type === 'welcome' ? 'Start' : 'Next →')}
                        </button>
                    </div>
                </div>
            ` : '';

            // Apply welcome-mode class only for welcome screens without existing answers
            const isWelcomeMode = isWelcomeScreen && !hasExistingAnswers;
            const containerClass = isWelcomeMode ? 'lab-typeform-container welcome-mode' : 'lab-typeform-container';

            const html = `
                <div class="${containerClass}">
                    <div class="lab-typeform-content">
                        <div class="lab-question-screen active">
                            ${questionHtml}
                        </div>
                    </div>
                    ${footerHtml}
                </div>
            `;

            console.log('Rendering typeform, question index:', this.typeformState.currentQuestionIndex);
            console.log('HTML includes footer:', html.includes('lab-typeform-footer'));
            $('#lab-mode-app').html(html);
            this.currentStep = 'typeform';

            // Add body class to hide dashboard elements during typeform
            $('body').addClass('lab-typeform-active');

            // Verify footer exists after render
            setTimeout(() => {
                const footer = document.querySelector('.lab-typeform-footer');
                console.log('Footer element after render:', footer);
                if (footer) {
                    console.log('Footer computed styles:', window.getComputedStyle(footer));
                }
            }, 100);

            // Initialize current question
            this.initializeCurrentQuestion(currentQuestion);

            // Setup keyboard navigation
            this.setupTypeformKeyboardNavigation();

            // Setup event delegation for interactions
            this.setupEventDelegation();

            // Make sure global functions are available for simple handlers
            window.updateSliderValue = (questionId, value, prefix, suffix) => this.updateSliderValue(questionId, value, prefix, suffix);
            window.updateAnswer = (questionId, value) => this.updateAnswer(questionId, value);
        },

        // Render welcome screen
        renderWelcomeScreen: function (question) {
            const hasExistingAnswers = this.typeformState.answers && Object.keys(this.typeformState.answers).length > 0;
            const savedQuestionIndex = this.typeformState.savedQuestionIndex;

            let progressMessage = '';
            let clearButton = '';
            let resumeButton = '';
            let startButton = '';

            if (hasExistingAnswers && savedQuestionIndex > 0) {
                const answeredCount = Object.keys(this.typeformState.answers).length;
                const totalQuestions = this.typeformState.questions.length - 1; // Exclude welcome screen

                progressMessage = `
                    <div class="saved-progress-indicator">
                        <div class="progress-icon">💾</div>
                        <div class="progress-info">
                            <p><strong>Previous answers restored!</strong></p>
                            <p class="progress-detail">${answeredCount} answers saved • You were on question ${savedQuestionIndex} of ${totalQuestions}</p>
                        </div>
                    </div>
                `;

                clearButton = `
                    <button class="lab-btn lab-btn-tertiary clear-progress-btn" onclick="LabModeApp.clearAndRestart()">
                        <span style="font-size: 1.1em; margin-right: 0.3em;">🔄</span>Start Fresh
                    </button>
                `;

                resumeButton = `
                    <button class="lab-btn lab-btn-secondary resume-progress-btn" onclick="LabModeApp.resumeFromSavedProgress()">
                        ▶️ Continue Where I Left Off
                    </button>
                `;
            } else {
                // Fresh start - show start button prominently below title
                startButton = `
                    <button class="lab-start-button" id="welcome-start-btn">
                        Start
                    </button>
                `;
            }

            return `
                <div class="lab-welcome-screen">
                    <h1>${question.title}</h1>
                    <p>${question.subtitle}</p>
                    ${startButton}
                    ${progressMessage}
                    <div class="welcome-actions">
                        ${resumeButton}
                        ${clearButton}
                    </div>
                </div>
            `;
        },

        // Render textarea question
        renderTextareaQuestion: function (question) {
            const currentValue = this.typeformState.answers[question.id] || '';
            console.log('Rendering textarea question:', question.id, 'Current value:', currentValue);

            const exampleButtons = question.examples ? question.examples.slice(0, 6).map((example, index) =>
                `<button type="button" class="lab-quick-select-btn" data-question-id="${question.id}" data-example="${example}">${example}</button>`
            ).join('') : '';

            return `
                <div>
                    <h2 class="lab-question-title">${question.title}</h2>
                    <p class="lab-question-subtitle">${question.subtitle}</p>
                    
                    ${exampleButtons ? `<div class="lab-quick-select-grid">${exampleButtons}</div>` : ''}
                    
                    <textarea class="lab-typeform-input lab-typeform-textarea" 
                              id="${question.id}" 
                              placeholder="${question.placeholder}"
                              oninput="updateAnswer(this.id, this.value)"></textarea>
                </div>
            `;
        },

        // Render multiple choice question
        renderMultipleChoiceQuestion: function (question) {
            const currentValue = this.typeformState.answers[question.id] || '';

            return `
                <div>
                    <h2 class="lab-question-title">${question.title}</h2>
                    <p class="lab-question-subtitle">${question.subtitle}</p>
                    
                    <div class="lab-choice-options">
                        ${question.options.map((option, index) => `
                            <div class="lab-choice-option ${currentValue === option ? 'selected' : ''}" 
                                 data-question-id="${question.id}" data-option="${option}">
                                ${option}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        },

        // Render multiple input question
        renderMultipleInputQuestion: function (question) {
            const exampleButtons = question.examples ? question.examples.slice(0, 9).map(example =>
                `<button type="button" class="lab-quick-select-btn" data-input-ids="${question.inputs.map(i => i.id).join(',')}" data-example="${example}">${example}</button>`
            ).join('') : '';

            return `
                <div>
                    <h2 class="lab-question-title">${question.title}</h2>
                    <p class="lab-question-subtitle">${question.subtitle}</p>
                    
                    ${exampleButtons ? `<div class="lab-quick-select-grid">${exampleButtons}</div>` : ''}
                    
                    <div class="lab-input-group">
                        ${question.inputs.map(input => {
                const currentValue = this.typeformState.answers[input.id] || '';
                return `
                                <input type="text" 
                                       class="lab-typeform-input" 
                                       id="${input.id}" 
                                       placeholder="${input.placeholder}"
                                       value="${currentValue}"
                                       oninput="updateAnswer(this.id, this.value)"
                                       ${input.required ? 'required' : ''}>
                            `;
            }).join('')}
                    </div>
                </div>
            `;
        },

        // Render slider question
        renderSliderQuestion: function (question) {
            const currentValue = this.typeformState.answers[question.id] || question.value;
            const displayValue = (question.valuePrefix || '') + currentValue + (question.valueSuffix || '');

            return `
                <div>
                    <h2 class="lab-question-title">${question.title}</h2>
                    <p class="lab-question-subtitle">${question.subtitle}</p>
                    
                    <div class="lab-typeform-slider-container">
                        <div class="lab-slider-value" id="slider-display-${question.id}">${displayValue}</div>
                        <input type="range" 
                               class="lab-typeform-slider" 
                               id="${question.id}"
                               min="${question.min}" 
                               max="${question.max}" 
                               value="${currentValue}"
                               oninput="updateSliderValue(this.id, this.value, '${question.valuePrefix || ''}', '${question.valueSuffix || ''}')">
                        <div class="lab-slider-labels">
                            <span>${question.leftLabel}</span>
                            <span>${question.rightLabel}</span>
                        </div>
                    </div>
                </div>
            `;
        },

        // Render role model discovery question
        renderRoleModelDiscoveryQuestion: function (question) {
            const currentValue = this.typeformState.answers[question.id];
            const hasSelection = currentValue && currentValue.finalSelection && currentValue.finalSelection.length > 0;

            let displayContent = '';

            if (hasSelection) {
                // Show selected role models
                const finalModels = currentValue.finalSelection;
                displayContent = `
                    <div class="selected-role-models">
                        <h4>Your Selected Role Models:</h4>
                        <div class="selected-models-list">
                            ${finalModels.map(model => `
                                <div class="selected-model-chip">
                                    <span class="model-name">${model.name}</span>
                                    <span class="model-category">${model.category}</span>
                                </div>
                            `).join('')}
                        </div>
                        <button type="button" class="rolemodel-change-btn" data-question-id="${question.id}">
                            Change Selection
                        </button>
                    </div>
                `;
            } else {
                // Show discovery button
                displayContent = `
                    <div class="role-model-discovery-prompt">
                        <div class="discovery-icon">🎯</div>
                        <p class="discovery-description">We'll help you identify people who inspire you and discover others with similar qualities.</p>
                        <button type="button" class="rolemodel-discover-btn" data-question-id="${question.id}">
                            Discover Role Models
                        </button>
                        <div class="or-divider">
                            <span>or</span>
                        </div>
                        <button type="button" class="rolemodel-skip-btn" data-question-id="${question.id}">
                            Skip This Step
                        </button>
                    </div>
                `;
            }

            return `
                <div>
                    <h2 class="lab-question-title">${question.title}</h2>
                    <p class="lab-question-subtitle">${question.subtitle}</p>
                    
                    ${displayContent}
                </div>
            `;
        },

        // Setup event delegation for all button interactions
        setupEventDelegation: function () {
            const self = this;

            // Remove any existing event listeners to avoid duplicates
            $(document).off('click.typeform');

            // Add event delegation for all typeform interactions
            $(document).on('click.typeform', '.lab-quick-select-btn', function (e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                const example = $(this).data('example');
                const inputIds = $(this).data('input-ids');

                if (questionId && example) {
                    // Textarea example button
                    self.addExampleToTextarea(questionId, example);
                } else if (inputIds && example) {
                    // Multiple input example button
                    self.addExampleToFirstEmpty(inputIds.split(','), example);
                }
            });

            // Navigation buttons
            $(document).on('click.typeform', '[data-action="next"]', function (e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    self.goToNextQuestion();
                }
            });

            $(document).on('click.typeform', '[data-action="back"]', function (e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    self.goToPreviousQuestion();
                }
            });

            // Multiple choice options
            $(document).on('click.typeform', '.lab-choice-option', function (e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                const option = $(this).data('option');
                if (questionId && option) {
                    self.selectChoice(questionId, option);
                }
            });

            // Role model discovery buttons
            $(document).on('click.typeform', '.rolemodel-discover-btn, .rolemodel-change-btn', function (e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                if (questionId) {
                    self.openRoleModelDiscovery(questionId);
                }
            });

            $(document).on('click.typeform', '.rolemodel-skip-btn', function (e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                if (questionId) {
                    self.skipRoleModelDiscovery(questionId);
                }
            });

            // Welcome start button
            $(document).on('click.typeform', '#welcome-start-btn, .lab-start-button', function (e) {
                e.preventDefault();
                self.goToNextQuestion();
            });
        },

        // Initialize current question (setup event listeners, etc.)
        initializeCurrentQuestion: function (question) {
            // Set values safely after DOM is ready
            setTimeout(() => {
                if (question.type === 'textarea') {
                    const textarea = document.getElementById(question.id);
                    if (textarea) {
                        const currentValue = this.typeformState.answers[question.id] || '';
                        console.log('Setting textarea value for', question.id, ':', currentValue);
                        textarea.value = currentValue;
                    }
                } else if (question.type === 'multiple_input') {
                    // Restore multiple input values
                    question.inputs.forEach(input => {
                        const inputElement = document.getElementById(input.id);
                        if (inputElement) {
                            const currentValue = this.typeformState.answers[input.id] || '';
                            console.log('Setting input value for', input.id, ':', currentValue);
                            inputElement.value = currentValue;
                        }
                    });
                } else if (question.type === 'slider') {
                    // Initialize slider with default value if no saved answer
                    const sliderElement = document.getElementById(question.id);
                    const sliderDisplay = document.getElementById(`slider-display-${question.id}`);
                    if (sliderElement && sliderDisplay) {
                        let currentValue = this.typeformState.answers[question.id];

                        // If no saved answer, use the default value from the question
                        if (currentValue === undefined) {
                            currentValue = question.value || 50; // Use question's default value
                            this.typeformState.answers[question.id] = currentValue; // Save default to answers
                            console.log('Initializing slider with default value for', question.id, ':', currentValue);
                        } else {
                            console.log('Setting slider value for', question.id, ':', currentValue);
                        }

                        sliderElement.value = currentValue;
                        const prefix = question.valuePrefix || '';
                        const suffix = question.valueSuffix || '';
                        sliderDisplay.textContent = prefix + currentValue + suffix;
                    }
                } else if (question.type === 'multiple_choice') {
                    // Restore multiple choice selection
                    const currentValue = this.typeformState.answers[question.id];
                    if (currentValue) {
                        const selectedOption = document.querySelector(`[data-question-id="${question.id}"][data-option="${currentValue}"]`);
                        if (selectedOption) {
                            console.log('Setting multiple choice selection for', question.id, ':', currentValue);
                            selectedOption.classList.add('selected');
                        }
                    }
                }

                // Auto-focus on first input if it exists
                const firstInput = document.querySelector('.lab-typeform-input');
                if (firstInput && question.type !== 'welcome') {
                    firstInput.focus();
                }
            }, 100);

            // Update next button state
            this.updateNextButton();
        },

        // Navigation methods
        goToNextQuestion: function () {
            const currentQuestion = this.typeformState.questions[this.typeformState.currentQuestionIndex];

            // Special handling for welcome screen
            if (currentQuestion.type === 'welcome') {
                this.typeformState.currentQuestionIndex++;
                this.renderTypeform();
                return;
            }

            // Validate required fields
            if (!this.validateCurrentQuestion()) {
                return;
            }

            // Check if this is the last question
            if (this.typeformState.currentQuestionIndex === this.typeformState.questions.length - 1) {
                // Validate all required questions before submitting
                const isFormComplete = this.validateAllQuestions();
                if (isFormComplete) {
                    this.submitTypeformData();
                } else {
                    console.warn('Form validation failed - some required questions not answered');
                    // Find first incomplete question and go there
                    const firstIncomplete = this.findFirstIncompleteQuestion();
                    if (firstIncomplete !== -1) {
                        this.typeformState.currentQuestionIndex = firstIncomplete;
                        this.renderTypeform();
                        alert('Please complete all required questions before generating experiments.');
                    }
                }
                return;
            }

            // Move to next question
            this.typeformState.currentQuestionIndex++;
            this.saveTypeformState();
            this.renderTypeform();
        },

        goToPreviousQuestion: function () {
            if (this.typeformState.currentQuestionIndex > 0) {
                this.typeformState.currentQuestionIndex--;
                this.saveTypeformState();
                this.renderTypeform();
            }
        },

        // Update answer in state
        updateAnswer: function (questionId, value) {
            this.typeformState.answers[questionId] = value;
            this.updateNextButton();

            // Auto-save state when answers change
            this.saveTypeformState();
        },

        // Select choice for multiple choice questions
        selectChoice: function (questionId, value) {
            this.typeformState.answers[questionId] = value;

            // Update UI
            document.querySelectorAll('.lab-choice-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.classList.add('selected');

            this.updateNextButton();

            // Save state after selection
            this.saveTypeformState();
        },

        // Update slider value
        updateSliderValue: function (questionId, value, prefix, suffix) {
            this.typeformState.answers[questionId] = parseInt(value);
            document.getElementById(`slider-display-${questionId}`).textContent = prefix + value + suffix;
            this.updateNextButton();

            // Save state after slider change
            this.saveTypeformState();
        },

        // Add example to textarea
        addExampleToTextarea: function (questionId, example) {
            console.log('Adding example to textarea:', questionId, example);
            const textarea = document.getElementById(questionId);
            if (!textarea) {
                console.error('Textarea not found:', questionId);
                return;
            }

            const currentValue = textarea.value;

            // Check if example already exists to avoid duplicates
            if (currentValue.includes(example)) {
                // Visual feedback that it's already added
                textarea.focus();
                return;
            }

            const newValue = currentValue ? currentValue + '\n' + example : example;
            textarea.value = newValue;
            this.updateAnswer(questionId, newValue);
            textarea.focus();

            // Visual feedback on the button
            const clickedButton = event.target;
            if (clickedButton) {
                clickedButton.classList.add('selected');
                setTimeout(() => {
                    clickedButton.classList.remove('selected');
                }, 500);
            }
        },

        // Add example to first empty input
        addExampleToFirstEmpty: function (inputIds, example) {
            console.log('Adding example to first empty input:', inputIds, example);
            // Handle both array and comma-separated string
            const ids = Array.isArray(inputIds) ? inputIds : inputIds.split(',');

            for (const inputId of ids) {
                const input = document.getElementById(inputId.trim());
                console.log('Checking input:', inputId.trim(), 'Element:', input, 'Current value:', input ? input.value : 'not found');
                if (input && !input.value.trim()) {
                    input.value = example;
                    this.updateAnswer(inputId.trim(), example);
                    input.focus();

                    // Visual feedback on the button
                    const clickedButton = event.target;
                    if (clickedButton) {
                        clickedButton.classList.add('selected');
                        setTimeout(() => {
                            clickedButton.classList.remove('selected');
                        }, 500);
                    }
                    break;
                }
            }
        },

        // Open role model discovery modal
        openRoleModelDiscovery: function (questionId) {
            if (!window.RoleModelDiscovery) {
                console.error('RoleModelDiscovery component not loaded');
                alert('Role model discovery is not available. Please refresh the page.');
                return;
            }

            window.RoleModelDiscovery.open((result) => {
                console.log('Role model discovery completed:', result);

                // Store the complete result
                this.updateAnswer(questionId, result);

                // Re-render the current question to show the selection
                this.renderTypeform();
            });
        },

        // Skip role model discovery
        skipRoleModelDiscovery: function (questionId) {
            // Store empty result to indicate skipped
            const skippedResult = {
                userInputModels: [],
                selectedCategories: [],
                aiSuggestions: [],
                finalSelection: [],
                skipped: true
            };

            this.updateAnswer(questionId, skippedResult);

            // Move to next question automatically
            this.goToNextQuestion();
        },

        // Validate current question
        validateCurrentQuestion: function () {
            const currentQuestion = this.typeformState.questions[this.typeformState.currentQuestionIndex];

            if (!currentQuestion.required && currentQuestion.type !== 'multiple_input') {
                return true;
            }

            switch (currentQuestion.type) {
                case 'textarea':
                    const textValue = this.typeformState.answers[currentQuestion.id];
                    return !currentQuestion.required || (textValue && textValue.trim().length > 0);

                case 'multiple_choice':
                    const choiceValue = this.typeformState.answers[currentQuestion.id];
                    return !currentQuestion.required || (choiceValue && choiceValue.length > 0);

                case 'multiple_input':
                    const requiredInputs = currentQuestion.inputs.filter(input => input.required);
                    return requiredInputs.every(input => {
                        const value = this.typeformState.answers[input.id];
                        return value && value.trim().length > 0;
                    });

                case 'slider':
                    // Sliders always have a value (either saved or default), so always valid
                    return true;

                case 'role_model_discovery':
                    // Role model discovery is optional, so always valid
                    return true;

                default:
                    return true;
            }
        },

        // Update next button state
        updateNextButton: function () {
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn) {
                const currentQuestion = this.typeformState.questions[this.typeformState.currentQuestionIndex];
                const isLastQuestion = this.typeformState.currentQuestionIndex === this.typeformState.questions.length - 1;

                if (isLastQuestion) {
                    // For last question, check if all required questions are complete
                    const isFormComplete = this.validateAllQuestions();
                    nextBtn.disabled = !isFormComplete;
                } else {
                    // For other questions, just check current question
                    const isValid = this.validateCurrentQuestion();
                    nextBtn.disabled = !isValid;
                }
            }
        },

        // Validate all questions in the form
        validateAllQuestions: function () {
            for (let i = 0; i < this.typeformState.questions.length; i++) {
                const question = this.typeformState.questions[i];
                if (question.type === 'welcome') continue; // Skip welcome screen

                if (question.required || question.type === 'multiple_input') {
                    const isValid = this.validateSpecificQuestion(question);
                    if (!isValid) {
                        console.log('Question validation failed:', question.id, question.title);
                        return false;
                    }
                }
            }
            return true;
        },

        // Find first incomplete required question
        findFirstIncompleteQuestion: function () {
            for (let i = 0; i < this.typeformState.questions.length; i++) {
                const question = this.typeformState.questions[i];
                if (question.type === 'welcome') continue;

                if (question.required || question.type === 'multiple_input') {
                    const isValid = this.validateSpecificQuestion(question);
                    if (!isValid) {
                        return i;
                    }
                }
            }
            return -1;
        },

        // Validate a specific question by its object
        validateSpecificQuestion: function (question) {
            switch (question.type) {
                case 'textarea':
                    const textValue = this.typeformState.answers[question.id];
                    return !question.required || (textValue && textValue.trim().length > 0);

                case 'multiple_choice':
                    const choiceValue = this.typeformState.answers[question.id];
                    return !question.required || (choiceValue && choiceValue.length > 0);

                case 'multiple_input':
                    const requiredInputs = question.inputs.filter(input => input.required);
                    return requiredInputs.every(input => {
                        const value = this.typeformState.answers[input.id];
                        return value && value.trim().length > 0;
                    });

                case 'slider':
                    // Sliders always have a value (either saved or default), so always valid
                    return true;

                default:
                    return true;
            }
        },

        // Submit typeform data
        submitTypeformData: function () {
            // Transform answers back to the expected format for the existing system
            const formData = this.transformAnswersToFormData();

            // Call existing save functionality
            this.saveQualifiers(formData);
        },

        // Transform typeform answers to form data format
        transformAnswersToFormData: function () {
            const answers = this.typeformState.answers;
            const formData = new FormData();

            // Add all answers to form data
            Object.keys(answers).forEach(key => {
                if (answers[key] !== null && answers[key] !== undefined) {
                    formData.append(key, answers[key]);
                }
            });

            return formData;
        },

        // Add keyboard navigation for typeform
        setupTypeformKeyboardNavigation: function () {
            const self = this;

            $(document).off('keydown.typeform').on('keydown.typeform', function (e) {
                if (self.currentStep !== 'typeform') return;

                // Enter key - go to next question (if valid)
                if (e.key === 'Enter' && !e.shiftKey) {
                    const activeElement = document.activeElement;
                    // Don't interfere with textarea line breaks
                    if (activeElement && activeElement.tagName !== 'TEXTAREA') {
                        e.preventDefault();
                        const nextBtn = document.getElementById('next-btn');
                        if (nextBtn && !nextBtn.disabled) {
                            self.goToNextQuestion();
                        }
                    }
                }

                // Escape key - go to previous question
                if (e.key === 'Escape') {
                    e.preventDefault();
                    self.goToPreviousQuestion();
                }

                // Number keys for multiple choice (1-9)
                if (e.key >= '1' && e.key <= '9') {
                    const currentQuestion = self.typeformState.questions[self.typeformState.currentQuestionIndex];
                    if (currentQuestion.type === 'multiple_choice') {
                        const optionIndex = parseInt(e.key) - 1;
                        if (optionIndex < currentQuestion.options.length) {
                            e.preventDefault();
                            const option = currentQuestion.options[optionIndex];
                            self.selectChoice(currentQuestion.id, option);
                        }
                    }
                }
            });
        },

        // Save qualifiers (updated for typeform data)
        saveQualifiers: function (formData) {
            // Transform typeform answers into the expected qualifiers format
            const answers = this.typeformState.answers;
            const qualifiers = {
                mi_qualifiers: [],
                cdt_qualifiers: [],
                curiosity: {
                    curiosities: [],
                    roleModels: [],
                    constraints: {},
                    contextTags: []
                }
            };

            // Extract MI qualifiers
            this.typeformState.topMI.forEach(mi => {
                const enjoy = answers[`mi_enjoy_${mi.key}`] ? answers[`mi_enjoy_${mi.key}`].split('\n').filter(s => s.trim()) : [];
                const doing = answers[`mi_doing_${mi.key}`] ? answers[`mi_doing_${mi.key}`].split('\n').filter(s => s.trim()) : [];

                qualifiers.mi_qualifiers.push({
                    key: mi.key,
                    enjoy: enjoy,
                    doing: doing
                });
            });

            // Extract CDT qualifiers
            this.typeformState.bottomCDT.forEach(cdt => {
                const challenge = answers[`cdt_challenge_${cdt.key}`] || '';
                qualifiers.cdt_qualifiers.push({
                    key: cdt.key,
                    challenge: challenge,
                    label: cdt.label
                });
            });

            // Extract curiosity data
            qualifiers.curiosity.curiosities = [
                answers['curiosity_1'],
                answers['curiosity_2'],
                answers['curiosity_3']
            ].filter(c => c && c.trim());

            // Extract enhanced role models data
            const roleModelData = answers['role_models'];
            if (roleModelData && !roleModelData.skipped && roleModelData.finalSelection) {
                // Use the enhanced role model discovery results
                qualifiers.curiosity.roleModels = roleModelData.finalSelection.map(model => model.name);
                qualifiers.curiosity.roleModelCategories = [...new Set(roleModelData.finalSelection.map(model => model.category))];
                qualifiers.curiosity.roleModelAnalysis = {
                    userInputModels: roleModelData.userInputModels || [],
                    selectedCategories: roleModelData.selectedCategories || [],
                    aiSuggestions: roleModelData.aiSuggestions || []
                };
            } else {
                // Fallback for skipped or empty role model discovery
                qualifiers.curiosity.roleModels = [];
                qualifiers.curiosity.roleModelCategories = [];
                qualifiers.curiosity.roleModelAnalysis = null;
            }

            qualifiers.curiosity.constraints = {
                risk: answers['risk_tolerance'] || 50,
                budget: answers['budget'] || 50,
                timePerWeekHours: answers['time_per_week'] || 3,
                soloToGroup: answers['solo_group'] || 50
            };

            qualifiers.curiosity.contextTags = []; // Not used in typeform version

            this.qualifiers = qualifiers;

            // Save qualifiers using AI loading overlay or fallback
            if (typeof AILoadingOverlay !== 'undefined' && AILoadingOverlay.show) {
                console.log('Using AI Loading Overlay for saving');
                const savingMessages = [
                    "Saving your personalized preferences…",
                    "Processing your strengths and growth areas…",
                    "Preparing your profile for AI analysis…",
                    "Setting up your experiment parameters…",
                    "Ready to generate your experiments…"
                ];

                try {
                    AILoadingOverlay.show({
                        messages: savingMessages,
                        subtitle: "Saving your profile data… 💾"
                    });
                } catch (error) {
                    console.error('Error with AI Loading Overlay during save, using fallback:', error);
                    this.showLoading('Saving your preferences...');
                }
            } else {
                console.log('AI Loading Overlay not available for saving, using fallback');
                this.showLoading('Saving your preferences...');
            }

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_save_qualifiers',
                    nonce: labMode.nonce,
                    qualifiers: JSON.stringify(qualifiers)
                },
                success: (response) => {
                    console.log('Save qualifiers response:', response);
                    if (response.success) {
                        console.log('Qualifiers saved successfully, generating experiments...');
                        // Progress to 50% and continue with experiment generation
                        if (typeof AILoadingOverlay !== 'undefined' && AILoadingOverlay.isVisible) {
                            AILoadingOverlay.setProgress(50);
                            AILoadingOverlay.updateSubtitle("Profile saved! Now generating experiments… 🧪");
                        }
                        this.generateExperiments(true); // Pass true to indicate this is from save process
                    } else {
                        console.error('Failed to save qualifiers:', response);
                        if (typeof AILoadingOverlay !== 'undefined' && AILoadingOverlay.isVisible) {
                            AILoadingOverlay.hide();
                        }
                        this.showError('Failed to save qualifiers: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Network error saving qualifiers:', xhr, status, error);
                    if (typeof AILoadingOverlay !== 'undefined' && AILoadingOverlay.isVisible) {
                        AILoadingOverlay.hide();
                    }
                    this.showError('Network error while saving qualifiers: ' + error);
                }
            });

            // Listen for cancel events during saving
            $(document).off('ai-loading-cancelled.saving').on('ai-loading-cancelled.saving', () => {
                // Cancel the save operation and return to form
                AILoadingOverlay.hide();
                // Stay on the current form since cancelling save doesn't make sense to go back
            });
        },

        // Clear validation errors
        clearValidationErrors: function () {
            $('.field-error').removeClass('field-error');
            $('.validation-message').remove();
            $('.validation-summary').remove();
            $('.cdt-challenge-options.field-error').removeClass('field-error');
        },

        // Highlight specific field with error
        highlightFieldError: function (fieldName) {
            const field = $(`[name="${fieldName}"]`);
            if (field.length) {
                field.addClass('field-error');
                // Scroll to first error if not visible
                if ($('.field-error').first().get(0) === field.get(0)) {
                    field.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        },

        // Show validation errors in a user-friendly way
        showValidationErrors: function (errors) {
            // Create error summary at the top of the form
            const errorSummaryHtml = `
                <div class="validation-summary lab-error-summary">
                    <h4>Please complete the following fields:</h4>
                    <ul>
                        ${errors.map(error => `<li>${error.message}</li>`).join('')}
                    </ul>
                    <p><em>Fields with errors are highlighted in red below.</em></p>
                </div>
            `;

            // Insert error summary at the top of the form
            $('.lab-qualifiers-form').prepend(errorSummaryHtml);

            // Scroll to the error summary
            $('.validation-summary').get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        // Handle quick-select button clicks
        handleQuickSelect: function (e) {
            e.preventDefault();
            const button = $(e.target);
            const example = button.text();
            const targetName = button.closest('.quick-select-buttons').data('target');
            const field = $(`textarea[name="${targetName}"], input[name="${targetName}"]`);

            // Get current content
            const currentContent = field.val().trim();

            // Handle different field types
            if (targetName === 'context_tags') {
                // For context tags, add as comma-separated values
                if (!currentContent.includes(example)) {
                    const newContent = currentContent ?
                        currentContent + ', ' + example :
                        example;
                    field.val(newContent);
                }
            } else if (field.is('textarea')) {
                // For textareas, add as new lines
                if (!currentContent.includes(example)) {
                    const newContent = currentContent ?
                        currentContent + '\n' + example :
                        example;
                    field.val(newContent);
                }
            } else if (field.is('input')) {
                // For single input fields, try to add to the first empty field or replace current
                if (targetName.startsWith('curiosity_') || targetName.startsWith('role_model_')) {
                    // Find the next empty field in the group
                    const baseName = targetName.replace(/_\d+$/, '');
                    let targetField = null;

                    for (let i = 1; i <= 3; i++) {
                        const candidateField = $(`input[name="${baseName}_${i}"]`);
                        if (candidateField.length && !candidateField.val().trim()) {
                            targetField = candidateField;
                            break;
                        }
                    }

                    if (targetField) {
                        targetField.val(example);
                    } else if (!currentContent) {
                        field.val(example);
                    }
                }
            }

            // Visual feedback
            button.addClass('selected');
            setTimeout(() => button.removeClass('selected'), 300);
        },

        // Get MI examples for quick-select buttons
        getMIExamples: function (miKey, type) {
            const examples = {
                'linguistic': {
                    enjoy: ['Writing stories', 'Public speaking', 'Wordplay and puns', 'Reading diverse topics', 'Facilitating discussions', 'Debates and arguments'],
                    doing: ['Daily journaling', 'Blog writing', 'Team meeting notes', 'Email newsletters', 'Presentation prep', 'Podcast listening']
                },
                'logical-mathematical': {
                    enjoy: ['Solving puzzles', 'Data analysis', 'System optimization', 'Budget planning', 'Strategic thinking', 'Number patterns'],
                    doing: ['Spreadsheet modeling', 'Project planning', 'Process improvement', 'Financial tracking', 'Research analysis', 'Code debugging']
                },
                'spatial': {
                    enjoy: ['Visual design', 'Photography', 'Map reading', 'Interior decorating', 'Drawing/sketching', 'Architecture'],
                    doing: ['Slide deck design', 'Room organizing', 'Visual note-taking', 'Infographic creation', 'Layout planning', 'Photo editing']
                },
                'bodily-kinesthetic': {
                    enjoy: ['Hands-on building', 'Sports/movement', 'Crafting', 'Cooking', 'Physical challenges', 'Dance/choreography'],
                    doing: ['Exercise routine', 'DIY projects', 'Gesture-rich presenting', 'Active meetings', 'Workshop facilitation', 'Walking meetings']
                },
                'musical': {
                    enjoy: ['Listening to music', 'Creating playlists', 'Singing', 'Playing instruments', 'Rhythm activities', 'Sound mixing'],
                    doing: ['Background music while working', 'Humming/whistling', 'Musical breaks', 'Audio learning', 'Sound design', 'Beat tapping']
                },
                'interpersonal': {
                    enjoy: ['Team collaboration', 'Mentoring others', 'Networking events', 'Group problem-solving', 'Community building', 'Public engagement'],
                    doing: ['One-on-one meetings', 'Team coordination', 'Peer feedback', 'Social organizing', 'Conflict resolution', 'Group facilitation']
                },
                'intrapersonal': {
                    enjoy: ['Self-reflection', 'Personal goal setting', 'Independent learning', 'Meditation', 'Life planning', 'Solitary thinking'],
                    doing: ['Morning reflection', 'Goal tracking', 'Solo work time', 'Personal projects', 'Self-assessment', 'Quiet contemplation']
                },
                'naturalistic': {
                    enjoy: ['Outdoor activities', 'Pattern recognition', 'Environmental awareness', 'Gardening', 'Nature observation', 'Weather tracking'],
                    doing: ['Daily walks', 'Plant care', 'Weather tracking', 'Recycling/sustainability', 'Outdoor meetings', 'Hiking/camping']
                }
            };

            return examples[miKey]?.[type] || ['Custom example'];
        },

        // Get CDT challenge options (3 focused choices per dimension)
        getCDTChallengeOptions: function (cdtKey) {
            const options = {
                // Current CDT dimensions from the quiz
                'ambiguity-tolerance': [
                    "I need all the details figured out before I can start working",
                    "I feel uncomfortable when requirements or expectations are unclear",
                    "I struggle with open-ended tasks that don't have obvious solutions"
                ],
                'value-conflict-navigation': [
                    "I find it hard to work with people whose values differ significantly from mine",
                    "I struggle to find common ground when facing opposing viewpoints",
                    "I tend to avoid or shut down conversations that involve conflicting beliefs"
                ],
                'self-confrontation-capacity': [
                    "I struggle to acknowledge when I'm wrong or have made a mistake",
                    "I find it difficult to receive constructive criticism without getting defensive",
                    "I avoid examining my own biases and assumptions about situations"
                ],
                'discomfort-regulation': [
                    "I struggle to stay calm when conversations get heated or tense",
                    "I tend to shut down or withdraw when facing difficult emotions",
                    "I find it hard to work through conflict without getting overwhelmed"
                ],
                'growth-orientation': [
                    "I prefer to stick with methods I already know rather than learn new ones",
                    "I focus on immediate results rather than building long-term capabilities",
                    "I resist trying new approaches that might slow me down initially"
                ],
                // Legacy variations for backward compatibility
                'self-confrontation': [
                    "I struggle to acknowledge when I'm wrong or have made a mistake",
                    "I find it difficult to receive constructive criticism without getting defensive",
                    "I avoid examining my own biases and assumptions about situations"
                ],
                'value-conflict': [
                    "I find it hard to work with people whose values differ significantly from mine",
                    "I struggle to find common ground when facing opposing viewpoints",
                    "I tend to avoid or shut down conversations that involve conflicting beliefs"
                ],
                'intellectual-humility': [
                    "I find it difficult to admit when I don't know something",
                    "I struggle to change my mind even when presented with compelling evidence",
                    "I have trouble asking for help or guidance from others"
                ],
                'perspective-integration': [
                    "I struggle to see situations from multiple viewpoints simultaneously",
                    "I find it hard to synthesize different approaches into a cohesive solution",
                    "I tend to stick to one perspective rather than considering alternatives"
                ],
                // Legacy keys for backward compatibility
                'risk-comfort': [
                    'I avoid making decisions when outcomes are uncertain',
                    'I stick to familiar approaches even when they might not be optimal',
                    'I get paralyzed analyzing risks instead of taking small steps forward'
                ],
                'ambiguity-tolerance': [
                    'I need all the details figured out before I can start working',
                    'I feel uncomfortable when requirements or expectations are unclear',
                    'I struggle with open-ended tasks that don\'t have obvious solutions'
                ],
                'complexity-handling': [
                    'I get overwhelmed when problems have multiple interconnected parts',
                    'I prefer simple, linear solutions over complex systems thinking',
                    'I avoid situations where I have to juggle many variables at once'
                ],
                'learning-orientation': [
                    'I prefer to stick with methods I already know rather than learn new ones',
                    'I focus on immediate results rather than building long-term capabilities',
                    'I resist trying new approaches that might slow me down initially'
                ],
                'future-focus': [
                    'I focus mainly on immediate tasks and struggle with long-term planning',
                    'I find it hard to invest time in things that won\'t pay off right away',
                    'I prefer concrete, tangible work over abstract or visionary thinking'
                ],
                'innovation-drive': [
                    'I prefer to optimize existing processes rather than create new solutions',
                    'I\'m cautious about trying unproven ideas or experimental approaches',
                    'I focus on incremental improvements rather than breakthrough innovations'
                ]
            };

            console.log('CDT Key requested:', cdtKey);
            console.log('Available options for key:', options[cdtKey]);

            return options[cdtKey] || ['Custom challenge option'];
        },

        // Get curiosity examples for quick-select buttons
        getCuriosityExamples: function () {
            return [
                'sustainable design', 'community building', 'digital storytelling',
                'urban planning', 'behavioral psychology', 'creative writing',
                'data visualization', 'renewable energy', 'mindfulness practices'
            ];
        },

        // Get role model examples for quick-select buttons  
        getRoleModelExamples: function () {
            return [
                'James Clear', 'Brené Brown', 'Tim Urban',
                'Seth Godin', 'Marie Kondo', 'Simon Sinek',
                'Cal Newport', 'Austin Kleon', 'Ryan Holiday'
            ];
        },

        // Get context tag examples for quick-select buttons
        getContextTagExamples: function () {
            return [
                'creative', 'collaborative', 'outdoors', 'learning', 'social', 'quiet',
                'morning', 'evening', 'weekend', 'home', 'coffee shop', 'nature',
                'music', 'writing', 'building', 'teaching', 'solo', 'group'
            ];
        },

        // Fill form with test data for easy testing
        fillTestData: function () {
            // Fill MI qualifiers (for top 3 MI)
            const topMI = this.profileData.mi_results.slice(0, 3);
            topMI.forEach(mi => {
                // Fill enjoy fields
                const enjoyField = $(`textarea[name="mi_enjoy_${mi.key}"]`);
                const doingField = $(`textarea[name="mi_doing_${mi.key}"]`);

                if (mi.key === 'linguistic') {
                    enjoyField.val('Writing blog posts\nFacilitating discussions\nStorytelling');
                    doingField.val('Daily journaling\nWeekly team meetings\nContent creation');
                } else if (mi.key === 'logical-mathematical') {
                    enjoyField.val('Solving puzzles\nAnalyzing data patterns\nSystem optimization');
                    doingField.val('Spreadsheet modeling\nProcess improvement\nBudget analysis');
                } else if (mi.key === 'spatial') {
                    enjoyField.val('Creating visual maps\nDesigning layouts\nPhotography');
                    doingField.val('Sketching ideas\nSlide deck design\nRoom organization');
                } else if (mi.key === 'interpersonal') {
                    enjoyField.val('Mentoring colleagues\nBuilding teams\nNetworking events');
                    doingField.val('One-on-one meetings\nTeam coordination\nCommunity involvement');
                } else {
                    enjoyField.val(`Exploring ${mi.label.toLowerCase()}\nPracticing related skills`);
                    doingField.val(`Daily activities with ${mi.label.toLowerCase()}\nWeekly practice sessions`);
                }
            });

            // Fill CDT qualifiers (for bottom 2 CDT)
            const bottomCDT = this.profileData.cdt_results.slice(-2);
            bottomCDT.forEach(cdt => {
                const tripsField = $(`textarea[name="cdt_trips_${cdt.key}"]`);
                const helpsField = $(`textarea[name="cdt_helps_${cdt.key}"]`);

                if (cdt.key === 'risk-comfort') {
                    tripsField.val('Avoiding decisions with uncertain outcomes\nSticking to proven approaches');
                    helpsField.val('Starting with small experiments\nHaving backup plans');
                } else if (cdt.key === 'ambiguity-tolerance') {
                    tripsField.val('Getting stuck when requirements are unclear\nNeeding all details before starting');
                    helpsField.val('Breaking complex problems into smaller parts\nAsking clarifying questions');
                } else {
                    tripsField.val(`Struggling with ${cdt.label.toLowerCase()}\nAvoiding complex situations`);
                    helpsField.val(`Using structured approaches\nSeeking guidance from others`);
                }
            });

            // Fill curiosity fields
            $('input[name="curiosity_1"]').val('sustainable design');
            $('input[name="curiosity_2"]').val('community building');
            $('input[name="curiosity_3"]').val('digital storytelling');

            // Fill role models
            $('input[name="role_model_1"]').val('James Clear');
            $('input[name="role_model_2"]').val('Brené Brown');
            $('input[name="role_model_3"]').val('Tim Urban');

            // Set constraint sliders
            $('input[name="risk_tolerance"]').val(60).trigger('input');
            $('input[name="budget"]').val(75).trigger('input');
            $('input[name="time_per_week"]').val(4).trigger('input');
            $('input[name="solo_group"]').val(40).trigger('input');

            // Fill context tags
            $('input[name="context_tags"]').val('creative, collaborative, outdoors, learning');

            alert('Test data filled! You can now generate experiments.');
        },

        // Test AI generation directly
        testAI: function () {
            // First save test qualifiers, then try AI
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_test_save_qualifiers',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    console.log('Test qualifiers saved, now testing AI...');

                    // Now try to generate experiments with AI
                    $.ajax({
                        url: labMode.ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'mc_lab_generate_experiments',
                            nonce: labMode.nonce
                        },
                        success: (aiResponse) => {
                            console.log('AI Test Response:', aiResponse);
                            alert('AI test completed. Check console and server logs for details.');
                        },
                        error: (xhr, status, error) => {
                            console.error('AI test failed:', xhr, status, error);
                            alert('AI test failed: ' + error);
                        }
                    });
                },
                error: () => {
                    alert('Failed to save test qualifiers first');
                }
            });
        },

        // Test save qualifiers with dummy data
        testSaveQualifiers: function () {
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_test_save_qualifiers',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    console.log('Test Save Response:', response.data);
                    alert('Test save completed. Check console for details.');
                },
                error: () => {
                    console.error('Failed to test save');
                    alert('Failed to test save');
                }
            });
        },

        // Debug user data for troubleshooting
        debugUserData: function () {
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_debug_user_data',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    console.log('Debug Data:', response.data);
                    alert('Debug data logged to console. Check browser developer tools.');
                },
                error: () => {
                    console.error('Failed to get debug data');
                    alert('Failed to get debug data');
                }
            });
        },

        // Generate experiments using AI
        generateExperiments: function (fromSave = false) {
            // Only show loading overlay if not already shown from save process
            if (!fromSave && !AILoadingOverlay.isVisible) {
                const experimentMessages = [
                    "Taking time to pull from your MI, CDT scores, and Motivational Scores…",
                    "Cross-checking your strengths and growth areas to make this experiment fit you…",
                    "Calibrating cost, time, and risk to your preferences…",
                    "Adding inspiration from your chosen role models and curiosities…",
                    "Almost ready: a safe, low-stakes experiment just for you…"
                ];

                AILoadingOverlay.show({
                    messages: experimentMessages,
                    subtitle: "AI is crafting your personalized experiments… 🧪"
                });
            } else if (fromSave) {
                // Update messages for the second phase of the process
                const newMessages = [
                    "Now analyzing your complete profile for experiments…",
                    "Cross-checking your strengths and growth areas…",
                    "Calibrating cost, time, and risk to your preferences…",
                    "Adding inspiration from your chosen role models…",
                    "Finalizing your personalized experiments…"
                ];
                AILoadingOverlay.messages = newMessages;
                AILoadingOverlay.messageIndex = 0;
            }

            const requestData = {
                action: 'mc_lab_generate_experiments',
                nonce: labMode.nonce,
                qualifiers: JSON.stringify(this.qualifiers)
            };

            // Add model selection for admin users
            const selectedModel = this.getSelectedModel();
            if (selectedModel && selectedModel !== 'gpt-4o-mini') {
                requestData.model = selectedModel;
                console.log('Using admin-selected model:', selectedModel);
            }

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: requestData,
                success: (response) => {
                    if (response.success) {
                        const raw = response.data.experiments || [];
                        console.log('Raw experiments from backend:', raw);
                        console.log('Current constraints:', this.qualifiers?.curiosity?.constraints);
                        // Apply client-side constraint calibration to ensure visible impact
                        this.experiments = this.applyConstraintsToExperiments(raw);
                        console.log('Calibrated experiments:', this.experiments);
                        this.experimentSource = response.data.source || 'Unknown';
                        this.usingMock = response.data.using_mock || false;

                        // Complete progress and show results
                        if (typeof AILoadingOverlay !== 'undefined' && AILoadingOverlay.isVisible) {
                            AILoadingOverlay.setProgress(100);
                            AILoadingOverlay.updateSubtitle("Complete! Showing your experiments… ✨");
                            setTimeout(() => {
                                AILoadingOverlay.hide();
                                this.showExperiments();
                            }, 1200); // Show completion message briefly before transitioning
                        } else {
                            // Fallback if overlay not available
                            this.showExperiments();
                        }
                    } else {
                        AILoadingOverlay.hide();
                        this.showError(response.data || 'Failed to generate experiments');
                    }
                },
                error: () => {
                    AILoadingOverlay.hide();
                    this.showError('Network error while generating experiments');
                }
            });

            // Listen for cancel events
            $(document).off('ai-loading-cancelled.experiments').on('ai-loading-cancelled.experiments', () => {
                // Cancel the AJAX request if possible and return to previous state
                AILoadingOverlay.hide();
                this.showProfileInputs(); // Return to the input screen
            });
        },

        // Show generated experiments with tabbed interface
        showExperiments: function () {
            // Remove typeform body class when showing experiments
            $('body').removeClass('lab-typeform-active');

            // Group experiments by tab (will filter out empty categories)
            const experimentsByTab = this.groupExperimentsByTab();

            // Get available tabs
            const availableTabs = Object.keys(experimentsByTab);
            console.log('Available tabs:', availableTabs);

            // Initialize current tab state or ensure it's valid
            if (!this.currentExperimentTab || !availableTabs.includes(this.currentExperimentTab)) {
                // Try to get from localStorage, but fallback to first available tab
                const savedTab = localStorage.getItem('labModeActiveTab');
                this.currentExperimentTab = (savedTab && availableTabs.includes(savedTab)) ? savedTab : availableTabs[0];
                console.log('Set current experiment tab to:', this.currentExperimentTab);
            }

            let html = `
                <div class="lab-experiments">
                    <h2>Your Experiments</h2>
                    <div class="lab-experiment-controls">
                        <p class="lab-subtitle">Each experiment is a safe, low-stakes way to practice self-discovery. Explore, Reflect, or Connect — start where you feel drawn.</p>
                        <div class="experiment-global-actions">
                            <div class="action-buttons-left">
                                <button class="lab-btn lab-btn-primary" onclick="LabModeApp.regenerateWithCurrentSettings()" title="Create a fresh set of experiments tailored to you">+ New Experiments</button>
                                <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.startProfileInputs(event)" title="Tweak your time, cost, or risk preferences">Adjust Settings</button>
                            </div>
                            <div class="action-buttons-right">
                                <button class="lab-btn lab-btn-reset" onclick="LabModeApp.loadLandingView()" title="Clear your current experiments and start fresh">🔄 Reset</button>
                            </div>
                        </div>
                    </div>
                    
                    ${availableTabs.length > 1 ? `
                    <!-- Tabbed Interface -->
                    <div class="experiments-tabs-container">
                        <!-- Desktop: Arrows on sides -->
                        <div class="tab-navigation desktop-nav">
                            <button class="tab-arrow tab-arrow-left" onclick="LabModeApp.previousTab()" aria-label="Previous tab">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            
                            <div class="experiments-tabs">
                                ${availableTabs.map(tab => `
                                    <button class="experiment-tab ${this.currentExperimentTab === tab ? 'active' : ''}" 
                                            data-tab="${tab}" 
                                            onclick="LabModeApp.switchTab('${tab}')"
                                            aria-selected="${this.currentExperimentTab === tab}"
                                            title="${this.getTabTooltip(tab)}">
                                        ${this.getTabLabel(tab)} <span class="tab-count">(${experimentsByTab[tab].length})</span>
                                    </button>
                                `).join('')}
                            </div>
                            
                            <button class="tab-arrow tab-arrow-right" onclick="LabModeApp.nextTab()" aria-label="Next tab">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Mobile: Arrows below tabs -->
                        <div class="tab-navigation mobile-nav">
                            <div class="experiments-tabs">
                                ${availableTabs.map(tab => `
                                    <button class="experiment-tab ${this.currentExperimentTab === tab ? 'active' : ''}" 
                                            data-tab="${tab}" 
                                            onclick="LabModeApp.switchTab('${tab}')"
                                            aria-selected="${this.currentExperimentTab === tab}"
                                            title="${this.getTabTooltip(tab)}">
                                        ${this.getTabLabel(tab)} <span class="tab-count">(${experimentsByTab[tab].length})</span>
                                    </button>
                                `).join('')}
                            </div>
                            
                            <div class="mobile-tab-arrows">
                                <button class="tab-arrow tab-arrow-left" onclick="LabModeApp.previousTab()" aria-label="Previous tab">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                </button>
                                <button class="tab-arrow tab-arrow-right" onclick="LabModeApp.nextTab()" aria-label="Next tab">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Experiment Cards for Active Tab -->
                    <div class="experiments-tab-content">
                        ${this.renderTabContent(experimentsByTab[this.currentExperimentTab] || [], this.currentExperimentTab)}
                    </div>
                    
                    <div class="lab-footer-actions">
                        <button class="lab-btn lab-btn-reset" onclick="LabModeApp.loadLandingView()">← Back to Start</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'experiments';

            // Setup keyboard navigation
            this.setupTabKeyboardNavigation();

            // Scroll to top after showing experiments
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Setup debug toggle event listeners
            this.setupDebugToggles();
        },

        // Group experiments by archetype for tabs
        groupExperimentsByTab: function () {
            const groups = {
                explore: [],
                reflect: [],
                connect: []
            };

            this.experiments.forEach((exp, index) => {
                const archetype = exp.archetype?.toLowerCase() || 'discover';
                // Map archetypes to tabs
                if (archetype === 'discover' || archetype === 'explore') {
                    groups.explore.push({ ...exp, originalIndex: index });
                } else if (archetype === 'build' || archetype === 'reflect') {
                    groups.reflect.push({ ...exp, originalIndex: index });
                } else if (archetype === 'share' || archetype === 'connect') {
                    groups.connect.push({ ...exp, originalIndex: index });
                } else {
                    // Default unknown archetypes to explore
                    groups.explore.push({ ...exp, originalIndex: index });
                }
            });

            // Only return groups that have experiments
            const filteredGroups = {};
            Object.keys(groups).forEach(key => {
                if (groups[key].length > 0) {
                    filteredGroups[key] = groups[key];
                }
            });

            console.log('Filtered experiment groups:', filteredGroups);
            return filteredGroups;
        },

        // Get display label for tab
        getTabLabel: function (tab) {
            const labels = {
                explore: 'Explore',
                reflect: 'Reflect',
                connect: 'Connect'
            };
            return labels[tab] || tab;
        },

        // Get tooltip for tab
        getTabTooltip: function (tab) {
            const tooltips = {
                explore: 'For trying new ideas and experiences',
                reflect: 'For journaling, analysis, and self-awareness',
                connect: 'For building relationships and learning with others'
            };
            return tooltips[tab] || '';
        },

        // Get icon for tab
        getTabIcon: function (tab) {
            const icons = {
                explore: '🔍',
                reflect: '💭',
                connect: '🤝'
            };
            return icons[tab] || '🎯';
        },

        // Render content for a specific tab
        renderTabContent: function (experiments, tabName) {
            if (experiments.length === 0) {
                return `
                    <div class="empty-tab-message">
                        <div class="empty-icon">${this.getTabIcon(tabName)}</div>
                        <h3>No ${this.getTabLabel(tabName)} experiments yet</h3>
                        <p>Generate new ones to try something in this area.</p>
                        <button class="lab-btn lab-btn-primary empty-cta" onclick="LabModeApp.regenerateWithCurrentSettings()" title="Create a fresh set of experiments tailored to you">+ New Experiments</button>
                    </div>
                `;
            }

            return `
                <div class="experiments-grid fade-in">
                    ${experiments.map(exp => `
                        <div class="experiment-card" data-archetype="${exp.archetype}">
                            <div class="experiment-header">
                                <h3 class="experiment-title">${exp.title}</h3>
                            </div>
                            
                            <div class="experiment-body">
                                <div class="experiment-description">
                                    <p class="experiment-summary">${this.generateEngagingDescription(exp)}</p>
                                </div>
                                
                                <div class="experiment-connection">
                                    <h4>Why This Fits You:</h4>
                                    <p class="connection-text">${this.generatePersonalizedConnection(exp, exp.originalIndex)}</p>
                                </div>
                                
                                <div class="experiment-steps">
                                    <h4>Steps:</h4>
                                    <ol>
                                        ${exp.steps.map(step => `<li>${step}</li>`).join('')}
                                    </ol>
                                </div>
                                
                                <div class="success-criteria">
                                    <h4>Success Criteria:</h4>
                                    <ul>
                                        ${(exp.successCriteria || []).map(criteria => `<li>${criteria}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="experiment-actions">
                                <button class="lab-btn lab-btn-primary lab-start-experiment-btn" data-experiment-id="${exp.originalIndex}">Try This</button>
                                <button class="lab-btn lab-btn-secondary lab-iterate-btn" data-experiment-id="${exp.originalIndex}" title="Iteratively refine this experiment">Tweak</button>
                                <button class="lab-btn lab-btn-secondary lab-regenerate-ai-btn" data-experiment-id="${exp.originalIndex}" title="Generate a new AI-powered variant">New Version</button>
                                <button class="lab-btn lab-btn-tertiary lab-debug-toggle-btn" data-experiment-id="${exp.originalIndex}">Debug Info</button>
                            </div>
                            
                            <div class="experiment-debug" id="debug-${exp.originalIndex}" style="display: none;">
                                <div class="debug-header">
                                    <h4>🛠️ Debug Information</h4>
                                    <small>Technical details for troubleshooting</small>
                                </div>
                                
                                <div class="debug-section">
                                    <h5>Raw Experiment Data:</h5>
                                    <div class="debug-code">
                                        <pre><code>${JSON.stringify(exp, null, 2)}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        // Switch to a specific tab
        switchTab: function (tabName) {
            if (this.currentExperimentTab === tabName) return;

            this.currentExperimentTab = tabName;
            localStorage.setItem('labModeActiveTab', tabName);

            // Update tab buttons
            $('.experiment-tab').removeClass('active').attr('aria-selected', 'false');
            $(`.experiment-tab[data-tab="${tabName}"]`).addClass('active').attr('aria-selected', 'true');

            // Fade out current content
            $('.experiments-tab-content').addClass('fade-out');

            setTimeout(() => {
                // Update content
                const experimentsByTab = this.groupExperimentsByTab();
                $('.experiments-tab-content').html(
                    this.renderTabContent(experimentsByTab[tabName] || [], tabName)
                );

                // Fade in new content
                $('.experiments-tab-content').removeClass('fade-out');

                // Re-setup debug toggles for new content
                this.setupDebugToggles();
            }, 150);
        },

        // Navigate to previous tab
        previousTab: function () {
            const experimentsByTab = this.groupExperimentsByTab();
            const tabs = Object.keys(experimentsByTab);
            if (tabs.length <= 1) return; // No navigation needed with 0 or 1 tab

            const currentIndex = tabs.indexOf(this.currentExperimentTab);
            const previousIndex = (currentIndex - 1 + tabs.length) % tabs.length;
            this.switchTab(tabs[previousIndex]);
        },

        // Navigate to next tab
        nextTab: function () {
            const experimentsByTab = this.groupExperimentsByTab();
            const tabs = Object.keys(experimentsByTab);
            if (tabs.length <= 1) return; // No navigation needed with 0 or 1 tab

            const currentIndex = tabs.indexOf(this.currentExperimentTab);
            const nextIndex = (currentIndex + 1) % tabs.length;
            this.switchTab(tabs[nextIndex]);
        },

        // Regenerate experiments using current settings without going through profile inputs
        regenerateWithCurrentSettings: function () {
            if (!this.qualifiers) {
                console.error('No qualifiers available for regeneration');
                this.showError('Unable to regenerate - no profile data found. Please start over.');
                return;
            }

            console.log('Regenerating experiments with current settings:', this.qualifiers);
            this.generateExperiments();
        },

        // Setup keyboard navigation for tabs
        setupTabKeyboardNavigation: function () {
            $(document).off('keydown.tabs').on('keydown.tabs', (e) => {
                if (this.currentStep !== 'experiments') return;

                // Arrow key navigation when tab has focus
                if (document.activeElement.classList.contains('experiment-tab')) {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.previousTab();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.nextTab();
                    }
                }
            });
        },

        // Setup debug toggle functionality
        setupDebugToggles: function () {
            $(document).off('click.debug').on('click.debug', '.lab-debug-toggle-btn', function (e) {
                e.preventDefault();
                const experimentId = $(this).data('experiment-id');
                const debugSection = $(`#debug-${experimentId}`);
                const button = $(this);

                if (debugSection.is(':visible')) {
                    debugSection.slideUp(200);
                    button.text('Debug Info');
                } else {
                    debugSection.slideDown(200);
                    button.text('Hide Info');
                }
            });
        },

        // Format the AI prompt for debugging
        formatDebugPrompt: function () {
            if (!this.qualifiers || !this.profileData) {
                return 'No prompt data available (using mock/fallback experiments)';
            }

            const mi = this.profileData.mi_results || [];
            const cdt = this.profileData.cdt_results || [];
            const topMI = mi.slice(0, 3);
            const bottomCDT = cdt.slice(-2);
            const constraints = this.qualifiers.curiosity?.constraints || {};
            const curiosities = this.qualifiers.curiosity?.curiosities || [];
            const roleModels = this.qualifiers.curiosity?.roleModels || [];

            return `SYSTEM PROMPT:
Role: You generate personalized "minimum viable experiments" (MVEs) for self-discovery.

Inputs you will receive: A JSON payload with: MI top 3 (with scores), CDT subscale scores (plus strongest & growth edge), optional interests/context; and user-selected filters (cost, time, energy, variety), the brainstorming lenses to use (Curiosity, Role Models, Opposites, Adjacency), and a quantity target.

Task: Produce a diverse set of safe, low-stakes MVEs that the user can try within 7 days. Respect filters; tie each idea back to MI/CDT.

Constraints:
– Specific, runnable steps (3–5), not generic advice.
– Calibrate cost/time/energy/variety to sliders; don't exceed ±1 unless you add a tradeoff note.
– All ideas must be safe, legal, age-appropriate, and low-risk.
– Keep language warm, concrete, and non-judgmental.

USER PROMPT:
Profile+filters JSON: {
  "user": {
    "mi_top3": ${JSON.stringify(topMI.map(mi => ({ label: mi.label, score: mi.score })))},
    "cdt_bottom2": ${JSON.stringify(bottomCDT.map(cdt => ({ label: cdt.label, score: cdt.score })))},
    "curiosities": ${JSON.stringify(curiosities)},
    "roleModels": ${JSON.stringify(roleModels)}
  },
  "constraints": {
    "timePerWeek": ${constraints.timePerWeekHours || 3},
    "budget": ${constraints.budget || 50},
    "risk": ${constraints.risk || 50},
    "soloToGroup": ${constraints.soloToGroup || 50}
  }
}

Generate 3-5 personalized experiments that combine the user's MI strengths, address their CDT growth areas, incorporate their curiosities, and respect their constraints.`;
        },

        // Generate Mi Approach based on user's profile
        generateMiApproach: function (experiment) {
            if (!this.profileData?.mi_results) {
                return 'Use your natural learning style to approach this experiment in a way that feels authentic to you.';
            }

            const topMI = this.profileData.mi_results.slice(0, 3);
            const primaryMI = topMI[0];
            const secondaryMI = topMI[1];

            const approaches = {
                'linguistic': 'Document your process through writing, talking through ideas, or teaching concepts to others. Use words and language as tools for processing and understanding.',
                'logical-mathematical': 'Break down the experiment into logical steps, analyze patterns and connections, and use systematic approaches to track progress and outcomes.',
                'spatial': 'Create visual representations, use diagrams or mind maps, and pay attention to spatial relationships and visual patterns in your learning.',
                'bodily-kinesthetic': 'Learn through hands-on action, physical movement, and direct experience. Trust your bodily sensations and kinesthetic feedback.',
                'musical': 'Use rhythm, patterns, and musical elements to structure your learning. Consider background music or rhythmic approaches to organization.',
                'interpersonal': 'Involve others in your learning process through discussion, collaboration, teaching, or getting feedback from people you trust.',
                'intrapersonal': 'Engage in deep self-reflection, connect the learning to your personal goals and values, and create quiet space for internal processing.',
                'naturalistic': 'Look for patterns and connections, organize information systematically, and connect learning to broader systems and natural processes.'
            };

            const primaryApproach = approaches[primaryMI?.key] || 'Approach this experiment in a way that feels natural to your learning style.';

            let miApproach = primaryApproach;

            // Add secondary MI if available
            if (secondaryMI && approaches[secondaryMI.key]) {
                const secondaryApproach = approaches[secondaryMI.key];
                miApproach += ` Additionally, since ${secondaryMI.label} is also a strength, consider ${secondaryApproach.toLowerCase()}`;
            }

            return miApproach;
        },

        // Generate engaging description for what the user will do
        generateEngagingDescription: function (experiment) {
            // If experiment has an explicit summary, use that
            if (experiment.summary) {
                return experiment.summary;
            }

            // If experiment has steps, create a concise summary from them
            if (experiment.steps && Array.isArray(experiment.steps) && experiment.steps.length > 0) {
                return this.createActionSummary(experiment.steps);
            }

            // Fallback to title or generic description
            if (experiment.title) {
                // Remove "Experiment" suffix and convert to action phrase
                const cleanTitle = experiment.title.replace(/\s*Experiment:?\s*/i, '').trim();
                return cleanTitle || 'An engaging experiment tailored to your interests.';
            }

            return experiment.description || experiment.rationale || 'An engaging experiment tailored to your interests.';
        },

        // Create a concise action summary from experiment steps
        createActionSummary: function (steps) {
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

        // Generate personalized connection explaining why this experiment was chosen
        generatePersonalizedConnection: function (experiment, index) {
            if (!this.qualifiers || !this.profileData) {
                return "This experiment aligns with your interests and learning preferences.";
            }

            let connection = "Perfect for you because ";

            // First check if the AI provided detailed influences
            if (experiment.influences) {
                const influences = experiment.influences;
                const personalConnections = [];

                // Role model connection with personal touch
                if (influences.roleModelUsed) {
                    personalConnections.push(`you're drawn to ${influences.roleModelUsed}'s methodology - this captures their essence`);
                }

                // MI connection with identity language
                if (influences.miUsed) {
                    personalConnections.push(`your ${influences.miUsed} nature shines in this type of work`);
                }

                // Curiosity connection with passion language
                if (influences.curiosityUsed) {
                    personalConnections.push(`your genuine fascination with ${influences.curiosityUsed} will keep you engaged`);
                }

                // CDT growth with supportive framing
                if (influences.cdtEdge) {
                    personalConnections.push(`it's a gentle way to strengthen your ${influences.cdtEdge} skills without pressure`);
                }

                if (personalConnections.length > 0) {
                    if (personalConnections.length === 1) {
                        connection += personalConnections[0] + ".";
                    } else if (personalConnections.length === 2) {
                        connection += personalConnections[0] + " and " + personalConnections[1] + ".";
                    } else {
                        const last = personalConnections.pop();
                        connection += personalConnections.join(", ") + ", and " + last + ".";
                    }
                    return connection;
                }
            }

            // Fallback to manual connection generation with personal tone
            const mi = this.profileData.mi_results || [];
            const topMI = mi.slice(0, 3);
            const curiosities = this.qualifiers.curiosity?.curiosities || [];
            const roleModels = this.qualifiers.curiosity?.roleModels || [];
            const constraints = this.qualifiers.curiosity?.constraints || {};

            const personalConnections = [];

            // Connect to top MI strength with identity language
            if (topMI.length > 0) {
                const primaryMI = topMI[0];
                const miConnections = {
                    'linguistic': 'your love of words and communication makes this a natural fit',
                    'logical-mathematical': 'your analytical mind will thrive in this structured approach',
                    'spatial': 'your visual intelligence and design sense will shine here',
                    'bodily-kinesthetic': 'your hands-on learning style is perfectly matched',
                    'musical': 'your ear for patterns and rhythm guides this approach',
                    'interpersonal': 'your gift for understanding people is exactly what\'s needed',
                    'intrapersonal': 'your self-reflective nature will find this deeply engaging',
                    'naturalistic': 'your systematic thinking will make this feel intuitive'
                };

                const miConnection = miConnections[primaryMI.key] || `your ${primaryMI.label} strength is perfectly suited for this`;
                personalConnections.push(miConnection);
            }

            // Enhanced role model connection with admiration language
            const roleModelMentions = this.findRoleModelInfluences(experiment, roleModels);
            if (roleModelMentions.length > 0) {
                personalConnections.push(`it echoes ${roleModelMentions[0]}'s approach, which clearly resonates with you`);
            } else if (roleModels.length > 0) {
                personalConnections.push(`it channels the spirit of creators like ${roleModels[0]} who inspire you`);
            }

            // Connect to curiosities with passion language
            if (curiosities.length > 0) {
                const primaryCuriosity = curiosities[0];
                if (experiment.title && experiment.title.toLowerCase().includes(primaryCuriosity.toLowerCase())) {
                    personalConnections.push(`it directly feeds your fascination with ${primaryCuriosity}`);
                } else {
                    personalConnections.push(`your curiosity about ${primaryCuriosity} will find new expression here`);
                }
            }

            // Connect to constraints with thoughtful accommodation
            if (constraints.timePerWeekHours <= 2) {
                personalConnections.push("it respects your time constraints with focused, efficient activities");
            } else if (constraints.timePerWeekHours >= 6) {
                personalConnections.push("it makes good use of your available time for deeper exploration");
            }

            if (constraints.risk <= 30) {
                personalConnections.push("it honors your preference for manageable, low-risk steps");
            } else if (constraints.risk >= 70) {
                personalConnections.push("it matches your appetite for bold, adventurous experiments");
            }

            if (constraints.soloToGroup <= 30) {
                personalConnections.push("it supports your independent, self-directed style");
            } else if (constraints.soloToGroup >= 70) {
                personalConnections.push("it includes the social connection you value");
            }

            // Fallback if no specific connections found
            if (personalConnections.length === 0) {
                connection += "it's thoughtfully designed around your unique combination of strengths and interests.";
                return connection;
            }

            // Format with personal touch
            if (personalConnections.length === 1) {
                connection += personalConnections[0] + ".";
            } else if (personalConnections.length === 2) {
                connection += personalConnections[0] + " and " + personalConnections[1] + ".";
            } else {
                const last = personalConnections.pop();
                connection += personalConnections.join(", ") + ", and " + last + ".";
            }

            return connection;
        },

        // Switch between career mini tabs (explore vs saved)
        switchCareerMiniTab: function (tabName) {
            // Update tab buttons
            $('.career-mini-tab').removeClass('active');
            $(`.career-mini-tab[data-mini-tab="${tabName}"]`).addClass('active');

            // Show/hide panels
            $('.career-mini-panel').hide();
            $(`#career-panel-${tabName}`).show();

            // If switching to saved, load the saved careers
            if (tabName === 'saved') {
                setTimeout(() => {
                    this.loadSavedCareers();
                }, 100);
            }
        },

        // Render Saved Careers tab
        renderSavedCareersTab: function () {
            const html = `
                <div class="saved-careers-container">
                    <div class="saved-careers-header">
                        <h2>💾 Saved Ideas</h2>
                        <p class="saved-careers-subtitle">Your saved career explorations</p>
                    </div>
                    
                    <div class="saved-careers-loading" style="text-align: center; padding: 3rem;">
                        <div class="loading-spinner"></div>
                        <p>Loading your saved careers...</p>
                    </div>
                    
                    <div class="saved-careers-list" style="display: none;">
                        <!-- Will be populated by AJAX -->
                    </div>
                </div>
            `;

            // Load saved careers after rendering
            setTimeout(() => {
                this.loadSavedCareers();
            }, 100);

            return html;
        },

        // Load saved careers from server
        loadSavedCareers: function () {
            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_get_saved_careers',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    if (response.success && response.data.careers) {
                        this.displaySavedCareers(response.data.careers);
                    } else {
                        $('.saved-careers-loading').html('<p>Error loading saved careers</p>');
                    }
                },
                error: () => {
                    $('.saved-careers-loading').html('<p>Network error while loading saved careers</p>');
                }
            });
        },

        // Delete a saved career
        deleteSavedCareer: function (index, careerTitle) {
            if (!confirm(`Delete "${careerTitle}" from your saved careers?`)) {
                return;
            }

            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_delete_saved_career',
                    nonce: labMode.nonce,
                    index: index
                },
                success: (response) => {
                    if (response.success) {
                        // Reload the saved careers list
                        this.loadSavedCareers();
                    } else {
                        alert('Error: ' + (response.data || 'Failed to delete career'));
                    }
                },
                error: () => {
                    alert('Network error while deleting career');
                }
            });
        },

        // Display saved careers
        displaySavedCareers: function (careers) {
            $('.saved-careers-loading').hide();

            if (careers.length === 0) {
                $('.saved-careers-list').html(`
                    <div class="empty-saved-careers">
                        <p style="text-align: center; color: #6b7280; padding: 3rem;">
                            🔍 No saved careers yet. Explore careers and click the "Save ♥" button to save ideas here.
                        </p>
                    </div>
                `).show();
                return;
            }

            const html = careers.map((career, index) => {
                const savedDate = new Date(career.saved_at);
                const formattedDate = savedDate.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                return `
                    <div class="saved-career-card" data-index="${index}" data-career-title="${career.title}">
                        <div class="saved-career-header">
                            <h4>${career.title}</h4>
                            <div class="saved-career-header-right">
                                <span class="saved-career-date">${formattedDate}</span>
                                <button class="saved-career-delete" data-index="${index}" title="Delete this saved career">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="saved-career-body">
                            <p>${career.why_it_fits}</p>
                            <div class="saved-career-meta">
                                <span class="saved-career-type">${career.distance_group || 'Career'}</span>
                                ${career.central_career ? `<span class="saved-career-from">from ${career.central_career}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            $('.saved-careers-list').html(html).show();

            // Bind delete handlers
            $('.saved-career-delete').on('click', (e) => {
                e.preventDefault();
                const index = $(e.currentTarget).data('index');
                const careerTitle = careers[index].title;
                this.deleteSavedCareer(index, careerTitle);
            });
        },

        // Render Career Explorer tab with filters and dice
        renderCareerExplorerTab: function () {
            // Initialize filters if not exist
            if (!this.careerFilters) {
                this.careerFilters = this.getDefaultFilters();
                this.loadFiltersFromStorage();
            }

            const html = `
                <div class="career-explorer-container">
                    <div class="career-explorer-header">
                        <h2>⚡ Career Explorer</h2>
                        <p class="career-explorer-subtitle">Discover roles aligned with your profile, with smart filters and labor-market insights.</p>
                    </div>
                    
                    <!-- View Layout Tabs -->
                    <div class="career-view-tabs">
                        <button class="career-view-tab ${this.careerLayout === 'cards' ? 'active' : ''}" data-view="cards" onclick="LabModeApp.switchCareerView('cards')">
                            📇 Cards
                        </button>
                        <button class="career-view-tab ${this.careerLayout === 'map' ? 'active' : ''}" data-view="map" onclick="LabModeApp.switchCareerView('map')">
                            🗺️ Mind-Map
                        </button>
                    </div>
                    
                    <!-- Mini tabs for Explorer / Saved Ideas -->
                    <div class="career-mini-tabs">
                        <button class="career-mini-tab active" data-mini-tab="explore" onclick="LabModeApp.switchCareerMiniTab('explore')">
                            🔍 Explore Careers
                        </button>
                        <button class="career-mini-tab" data-mini-tab="saved" onclick="LabModeApp.switchCareerMiniTab('saved')">
                            💾 Saved Ideas
                        </button>
                    </div>
                    
                    <!-- Explore Careers Panel -->
                    <div class="career-mini-panel" id="career-panel-explore">
                        <div class="career-explorer-input-panel">
                            <div class="career-input-row">
                                <input 
                                    type="text" 
                                    id="career-interest-input" 
                                    class="career-input" 
                                    placeholder="e.g., UX Designer, Electrician, Nurse, Data Analyst"
                                    maxlength="100"
                                />
                                <button 
                                    class="lab-btn lab-btn-primary" 
                                    id="generate-career-ideas-btn"
                                    onclick="LabModeApp.generateCareerIdeas()"
                                >
                                    Generate Career Ideas
                                </button>
                                <button 
                                    class="lab-btn lab-btn-dice" 
                                    id="career-dice-btn"
                                    onclick="LabModeApp.rollCareerDice()"
                                    title="Surprise me with wildcard suggestions!"
                                >
                                    🎲 Surprise me
                                </button>
                            </div>
                            
                            <!-- Filters Bar -->
                            ${this.renderFiltersBar()}
                            
                            <!-- Admin Prompt Controls (only shown to admins) -->
                            ${labMode && labMode.isAdmin ? this.renderAdminPromptControls() : ''}
                        </div>
                        
                        <div class="career-explorer-results" id="career-explorer-results" style="display: none;">
                            <!-- Results will be dynamically inserted here -->
                        </div>
                    </div>
                    
                    <!-- Saved Ideas Panel -->
                    <div class="career-mini-panel" id="career-panel-saved" style="display: none;">
                        ${this.renderSavedCareersTab()}
                    </div>
                </div>
            `;

            return html;
        },

        // Switch career view layout (cards/map)
        switchCareerView: function (layout) {
            const oldLayout = this.careerLayout;
            this.careerLayout = layout;
            localStorage.setItem('career_layout', layout);

            // Update URL
            try {
                const url = new URL(window.location);
                url.searchParams.set('layout', layout);
                window.history.pushState({}, '', url);
            } catch (e) {
                console.warn('Failed to update URL:', e);
            }

            // Update tab UI
            $('.career-view-tab').removeClass('active');
            $(`.career-view-tab[data-view="${layout}"]`).addClass('active');

            // Analytics
            console.log('career_layout_switched', { from: oldLayout, to: layout });

            // Re-render results if they exist
            if (this.currentCareerData) {
                this.displayCareerSuggestions(
                    this.currentCareerData,
                    this.currentCareerInterest,
                    this.isDiceRoll
                );
            }
        },

        // Get default filter values
        getDefaultFilters: function () {
            return {
                demand_horizon: null,
                education_levels: [],
                work_env: [],
                role_orientation: [],
                comp_band: null,
                social_impact: [],
                remote_only: false,
                stretch_opposites: false
            };
        },

        // Load filters from localStorage
        loadFiltersFromStorage: function () {
            try {
                const stored = localStorage.getItem('career_explorer_filters');
                if (stored) {
                    const filters = JSON.parse(stored);
                    this.careerFilters = { ...this.getDefaultFilters(), ...filters };
                }
            } catch (e) {
                console.warn('Failed to load filters from storage:', e);
            }
        },

        // Save filters to localStorage
        saveFiltersToStorage: function () {
            try {
                localStorage.setItem('career_explorer_filters', JSON.stringify(this.careerFilters));
            } catch (e) {
                console.warn('Failed to save filters to storage:', e);
            }
        },

        // Render admin prompt controls
        renderAdminPromptControls: function () {
            const currentLevel = localStorage.getItem('career_prompt_level') || 'minimal';
            const minimalPrompt = localStorage.getItem('career_prompt_minimal') || 'Generate career suggestions for: {career}\n\nUser: MI={mi_top3}, CDT={cdt_top}, Bartle={bartle}';
            const mediumPrompt = localStorage.getItem('career_prompt_medium') || 'Career interest: {career}\n\nProfile:\n- Top MI: {mi_top3}\n- CDT Strength: {cdt_top}\n- CDT Edge: {cdt_edge}\n- Bartle Type: {bartle}\n\nProvide adjacent, parallel, and wildcard career suggestions that leverage their strengths.';
            const maximumPrompt = localStorage.getItem('career_prompt_maximum') || 'Career exploration for: {career}\n\nComprehensive Profile:\n- Multiple Intelligences: {mi_top3_detailed}\n- CDT Profile: {cdt_full}\n- Bartle Type: {bartle_detailed}\n- Johari Traits: {johari}\n\nGenerate deeply personalized suggestions explaining how each career:\n1. Activates their intelligences\n2. Fits their CDT comfort zones\n3. Aligns with motivation patterns';

            return `
                <div class="admin-prompt-controls">
                    <div class="admin-prompt-header">
                        <span class="admin-icon">🔧</span>
                        <strong>Admin: AI Prompt Configuration</strong>
                        <span class="admin-hint">Configure prompts sent to OpenAI for career generation</span>
                    </div>
                    <div class="admin-prompt-body">
                        <div class="prompt-level-selector">
                            <label class="prompt-main-label">Active Verbosity Level:</label>
                            <div class="prompt-level-buttons">
                                <button class="prompt-level-btn ${currentLevel === 'minimal' ? 'active' : ''}" onclick="LabModeApp.setPromptLevel('minimal')">
                                    <span class="level-emoji">💰</span>
                                    <div class="level-info">
                                        <strong>Minimal</strong>
                                        <small>~50 tokens</small>
                                    </div>
                                </button>
                                <button class="prompt-level-btn ${currentLevel === 'medium' ? 'active' : ''}" onclick="LabModeApp.setPromptLevel('medium')">
                                    <span class="level-emoji">⚖️</span>
                                    <div class="level-info">
                                        <strong>Medium</strong>
                                        <small>~150 tokens</small>
                                    </div>
                                </button>
                                <button class="prompt-level-btn ${currentLevel === 'maximum' ? 'active' : ''}" onclick="LabModeApp.setPromptLevel('maximum')">
                                    <span class="level-emoji">🚀</span>
                                    <div class="level-info">
                                        <strong>Maximum</strong>
                                        <small>~300 tokens</small>
                                    </div>
                                </button>
                            </div>
                        </div>
                        
                        <div class="prompt-templates-section">
                            <div class="prompt-template-group">
                                <div class="template-header">
                                    <span class="template-badge template-badge-minimal">💰 Minimal</span>
                                    <span class="template-tokens">~50 tokens</span>
                                </div>
                                <textarea 
                                    id="prompt-minimal-input" 
                                    class="prompt-template-textarea"
                                    placeholder="Minimal prompt template..."
                                >${this.escapeHtml(minimalPrompt)}</textarea>
                            </div>
                            
                            <div class="prompt-template-group">
                                <div class="template-header">
                                    <span class="template-badge template-badge-medium">⚖️ Medium</span>
                                    <span class="template-tokens">~150 tokens</span>
                                </div>
                                <textarea 
                                    id="prompt-medium-input" 
                                    class="prompt-template-textarea"
                                    placeholder="Medium prompt template..."
                                >${this.escapeHtml(mediumPrompt)}</textarea>
                            </div>
                            
                            <div class="prompt-template-group">
                                <div class="template-header">
                                    <span class="template-badge template-badge-maximum">🚀 Maximum</span>
                                    <span class="template-tokens">~300 tokens</span>
                                </div>
                                <textarea 
                                    id="prompt-maximum-input" 
                                    class="prompt-template-textarea"
                                    placeholder="Maximum prompt template..."
                                >${this.escapeHtml(maximumPrompt)}</textarea>
                            </div>
                        </div>
                        
                        <div class="prompt-variables-help">
                            <strong>Available Variables:</strong>
                            <code>{career}</code>
                            <code>{mi_top3}</code>
                            <code>{cdt_top}</code>
                            <code>{cdt_edge}</code>
                            <code>{bartle}</code>
                            <code>{filters}</code>
                        </div>
                        
                        <div class="prompt-actions">
                            <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.saveAllPrompts()">Save All Prompts</button>
                            <button class="lab-btn lab-btn-primary" onclick="LabModeApp.previewCurrentPrompt()">Preview Active Prompt</button>
                        </div>
                        
                        <div class="prompt-preview" id="prompt-preview-section" style="display: none;">
                            <div class="preview-header">Current Prompt Preview:</div>
                            <pre class="prompt-preview-content" id="prompt-preview-content"></pre>
                        </div>
                    </div>
                </div>
            `;
        },

        // Set prompt verbosity level
        setPromptLevel: function (level) {
            localStorage.setItem('career_prompt_level', level);

            // Update button states
            $('.prompt-level-btn').removeClass('active');
            $('.prompt-level-btn').each(function () {
                const btnText = $(this).text().toLowerCase();
                if ((level === 'minimal' && btnText.includes('minimal')) ||
                    (level === 'medium' && btnText.includes('medium')) ||
                    (level === 'maximum' && btnText.includes('maximum'))) {
                    $(this).addClass('active');
                }
            });

            this.showFeedback(`Prompt level set to: ${level}`);
        },

        // Save all prompt templates
        saveAllPrompts: function () {
            const minimalPrompt = $('#prompt-minimal-input').val();
            const mediumPrompt = $('#prompt-medium-input').val();
            const maximumPrompt = $('#prompt-maximum-input').val();

            localStorage.setItem('career_prompt_minimal', minimalPrompt);
            localStorage.setItem('career_prompt_medium', mediumPrompt);
            localStorage.setItem('career_prompt_maximum', maximumPrompt);

            this.showFeedback('All prompt templates saved!');
        },

        // Preview current prompt
        previewCurrentPrompt: function () {
            const level = localStorage.getItem('career_prompt_level') || 'minimal';
            let promptTemplate = '';

            if (level === 'minimal') {
                promptTemplate = $('#prompt-minimal-input').val();
            } else if (level === 'medium') {
                promptTemplate = $('#prompt-medium-input').val();
            } else if (level === 'maximum') {
                promptTemplate = $('#prompt-maximum-input').val();
            }

            const preview = this.generatePromptPreview(promptTemplate);

            $('#prompt-preview-content').text(preview);
            $('#prompt-preview-section').slideDown(200).get(0).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        // Generate prompt preview with example data substituted
        generatePromptPreview: function (template) {
            const profile = this.userProfile || {};

            // Example data for preview
            const exampleData = {
                career: 'Data Scientist',
                mi_top3: profile.mi_top3 || 'Logical-Mathematical, Linguistic, Spatial',
                mi_top3_detailed: 'Logical-Mathematical (95%), Linguistic (88%), Spatial (82%)',
                cdt_top: profile.cdt_top || 'Exploration',
                cdt_edge: 'Stability',
                cdt_full: 'Exploration: 85%, Innovation: 72%, Stability: 45%',
                bartle: profile.bartle || 'Explorer',
                bartle_detailed: 'Explorer (Primary), Achiever (Secondary)',
                johari: 'Curious, Analytical, Independent',
                filters: 'Cost: Medium, Time: Flexible, Energy: High'
            };

            // Replace template variables
            let preview = template;
            Object.keys(exampleData).forEach(key => {
                const regex = new RegExp(`\\{${key}\\}`, 'g');
                preview = preview.replace(regex, exampleData[key]);
            });

            return preview;
        },

        // Render filters bar
        renderFiltersBar: function () {
            return `
                <div class="career-filters-bar">
                    <button class="filter-accordion-toggle" onclick="LabModeApp.toggleFiltersAccordion()">
                        <span class="filter-accordion-label">⚙️ Filters</span>
                        <span class="filter-accordion-icon">▼</span>
                    </button>
                    <div class="filter-chips" id="filter-chips-container">
                        <button class="filter-chip" data-filter="demand_horizon" onclick="LabModeApp.toggleFilterDrawer('demand_horizon')">
                            <span class="filter-chip-label">Demand Horizon</span>
                            <span class="filter-chip-count">${this.careerFilters.demand_horizon ? '1' : '0'}</span>
                        </button>
                        
                        <button class="filter-chip" data-filter="education_levels" onclick="LabModeApp.toggleFilterDrawer('education_levels')">
                            <span class="filter-chip-label">Education</span>
                            <span class="filter-chip-count">${this.careerFilters.education_levels.length}</span>
                        </button>
                        
                        <button class="filter-chip" data-filter="work_env" onclick="LabModeApp.toggleFilterDrawer('work_env')">
                            <span class="filter-chip-label">Work Environment</span>
                            <span class="filter-chip-count">${this.careerFilters.work_env.length}</span>
                        </button>
                        
                        <button class="filter-chip" data-filter="role_orientation" onclick="LabModeApp.toggleFilterDrawer('role_orientation')">
                            <span class="filter-chip-label">Role Type</span>
                            <span class="filter-chip-count">${this.careerFilters.role_orientation.length}</span>
                        </button>
                        
                        <button class="filter-chip" data-filter="comp_band" onclick="LabModeApp.toggleFilterDrawer('comp_band')">
                            <span class="filter-chip-label">Compensation</span>
                            <span class="filter-chip-count">${this.careerFilters.comp_band ? '1' : '0'}</span>
                        </button>
                        
                        <button class="filter-chip" data-filter="social_impact" onclick="LabModeApp.toggleFilterDrawer('social_impact')">
                            <span class="filter-chip-label">Social Impact</span>
                            <span class="filter-chip-count">${this.careerFilters.social_impact.length}</span>
                        </button>
                        
                        <label class="filter-toggle">
                            <input type="checkbox" ${this.careerFilters.remote_only ? 'checked' : ''} onchange="LabModeApp.toggleRemoteOnly(this.checked)">
                            <span>Remote-only</span>
                        </label>
                        
                        <label class="filter-toggle">
                            <input type="checkbox" ${this.careerFilters.stretch_opposites ? 'checked' : ''} onchange="LabModeApp.toggleStretchOpposites(this.checked)">
                            <span>Stretch/Opposites</span>
                        </label>
                        
                        <button class="filter-reset-btn" onclick="LabModeApp.resetFilters()">Reset filters</button>
                    </div>
                    
                    <!-- Filter Drawers (hidden by default) -->
                    <div id="filter-drawers"></div>
                </div>
            `;
        },

        // Toggle filters accordion (mobile)
        toggleFiltersAccordion: function () {
            const container = $('#filter-chips-container');
            const icon = $('.filter-accordion-icon');
            const isOpen = container.hasClass('open');

            if (isOpen) {
                container.removeClass('open').slideUp(200);
                icon.text('▼');
            } else {
                container.addClass('open').slideDown(200);
                icon.text('▲');
            }
        },

        // Toggle filter drawer
        toggleFilterDrawer: function (filterType) {
            const drawersContainer = document.getElementById('filter-drawers');
            const existing = drawersContainer.querySelector(`[data-drawer="${filterType}"]`);

            if (existing) {
                existing.remove();
                return;
            }

            // Close other drawers
            drawersContainer.innerHTML = '';

            // Render drawer based on type
            const drawer = this.renderFilterDrawer(filterType);
            drawersContainer.innerHTML = drawer;
        },

        // Render filter drawer content
        renderFilterDrawer: function (filterType) {
            const options = {
                demand_horizon: [
                    { value: 'trending_now', label: 'Trending now' },
                    { value: 'high_growth_5y', label: 'High growth (next 5 years)' },
                    { value: 'future_proof_10y', label: 'Future-proof (10+ years)' },
                    { value: 'stable_low_vol', label: 'Stable / low volatility' },
                    { value: 'automation_resistant', label: 'Automation-resistant' }
                ],
                education_levels: [
                    { value: 'no_degree', label: 'No degree' },
                    { value: 'certificate_bootcamp', label: 'Certificate/Bootcamp' },
                    { value: 'bachelor', label: 'Bachelor' },
                    { value: 'advanced', label: 'Advanced' }
                ],
                work_env: [
                    { value: 'remote_friendly', label: 'Remote-friendly' },
                    { value: 'hybrid', label: 'Hybrid' },
                    { value: 'outdoor', label: 'Outdoor/Field' },
                    { value: 'hands_on', label: 'Hands-on' },
                    { value: 'solo', label: 'Solo' },
                    { value: 'collaborative', label: 'Highly collaborative' },
                    { value: 'client_facing', label: 'Client-facing' },
                    { value: 'structured', label: 'Highly structured' },
                    { value: 'flexible', label: 'Highly flexible' }
                ],
                role_orientation: [
                    { value: 'analytical', label: 'Analytical' },
                    { value: 'creative', label: 'Creative' },
                    { value: 'leadership', label: 'Leadership' },
                    { value: 'technical', label: 'Technical' },
                    { value: 'people_centered', label: 'People-centered' },
                    { value: 'helping', label: 'Helping professions' },
                    { value: 'problem_solving', label: 'Problem-solving' },
                    { value: 'adventure_fieldwork', label: 'Adventure/Fieldwork' }
                ],
                comp_band: [
                    { value: 'lower', label: 'Lower' },
                    { value: 'middle', label: 'Middle' },
                    { value: 'upper', label: 'Upper' },
                    { value: 'high_responsibility', label: 'High-responsibility' }
                ],
                social_impact: [
                    { value: 'high_social', label: 'High social impact' },
                    { value: 'environmental', label: 'Environmental' },
                    { value: 'community', label: 'Community-oriented' },
                    { value: 'mission_driven', label: 'Mission-driven' }
                ]
            };

            const filterOptions = options[filterType] || [];
            const isMulti = ['education_levels', 'work_env', 'role_orientation', 'social_impact'].includes(filterType);
            const currentValue = this.careerFilters[filterType];

            let optionsHtml = '';
            if (isMulti) {
                optionsHtml = filterOptions.map(opt => {
                    const checked = currentValue.includes(opt.value) ? 'checked' : '';
                    return `
                        <label class="filter-option">
                            <input type="checkbox" value="${opt.value}" ${checked} onchange="LabModeApp.updateFilter('${filterType}', '${opt.value}', this.checked, true)">
                            <span>${opt.label}</span>
                        </label>
                    `;
                }).join('');
            } else {
                optionsHtml = filterOptions.map(opt => {
                    const checked = currentValue === opt.value ? 'checked' : '';
                    return `
                        <label class="filter-option">
                            <input type="radio" name="${filterType}" value="${opt.value}" ${checked} onchange="LabModeApp.updateFilter('${filterType}', '${opt.value}', true, false)">
                            <span>${opt.label}</span>
                        </label>
                    `;
                }).join('');

                // Add "Any" option for single-select
                optionsHtml = `
                    <label class="filter-option">
                        <input type="radio" name="${filterType}" value="" ${!currentValue ? 'checked' : ''} onchange="LabModeApp.updateFilter('${filterType}', null, false, false)">
                        <span>Any</span>
                    </label>
                ` + optionsHtml;
            }

            return `
                <div class="filter-drawer" data-drawer="${filterType}">
                    <div class="filter-drawer-header">
                        <h4>${this.getFilterLabel(filterType)}</h4>
                        <button class="filter-drawer-close" onclick="LabModeApp.toggleFilterDrawer('${filterType}')">✕</button>
                    </div>
                    <div class="filter-drawer-options">
                        ${optionsHtml}
                    </div>
                </div>
            `;
        },

        // Get filter label
        getFilterLabel: function (filterType) {
            const labels = {
                demand_horizon: 'Demand Horizon',
                education_levels: 'Education/Training',
                work_env: 'Work Environment',
                role_orientation: 'Role Orientation',
                comp_band: 'Compensation Band',
                social_impact: 'Social Impact'
            };
            return labels[filterType] || filterType;
        },

        // Update filter value
        updateFilter: function (filterType, value, checked, isMulti) {
            if (isMulti) {
                if (checked) {
                    if (!this.careerFilters[filterType].includes(value)) {
                        this.careerFilters[filterType].push(value);
                    }
                } else {
                    this.careerFilters[filterType] = this.careerFilters[filterType].filter(v => v !== value);
                }
            } else {
                this.careerFilters[filterType] = checked ? value : null;
            }

            this.saveFiltersToStorage();
            this.updateFilterChipCounts();
        },

        // Update filter chip counts
        updateFilterChipCounts: function () {
            document.querySelectorAll('.filter-chip').forEach(chip => {
                const filterType = chip.dataset.filter;
                const countSpan = chip.querySelector('.filter-chip-count');
                if (countSpan) {
                    const value = this.careerFilters[filterType];
                    if (Array.isArray(value)) {
                        countSpan.textContent = value.length;
                    } else {
                        countSpan.textContent = value ? '1' : '0';
                    }
                }
            });
        },

        // Toggle remote only
        toggleRemoteOnly: function (checked) {
            this.careerFilters.remote_only = checked;
            this.saveFiltersToStorage();
        },

        // Toggle stretch opposites
        toggleStretchOpposites: function (checked) {
            this.careerFilters.stretch_opposites = checked;
            this.saveFiltersToStorage();
        },

        // Reset all filters
        resetFilters: function () {
            this.careerFilters = this.getDefaultFilters();
            this.saveFiltersToStorage();

            // Re-render the career explorer panel
            const explorePanel = document.getElementById('career-panel-explore');
            if (explorePanel) {
                const inputValue = document.getElementById('career-interest-input')?.value || '';
                explorePanel.innerHTML = `
                    <div class="career-explorer-input-panel">
                        <div class="career-input-row">
                            <input 
                                type="text" 
                                id="career-interest-input" 
                                class="career-input" 
                                placeholder="e.g., UX Designer, Electrician, Nurse, Data Analyst"
                                maxlength="100"
                                value="${inputValue}"
                            />
                            <button 
                                class="lab-btn lab-btn-primary" 
                                id="generate-career-ideas-btn"
                                onclick="LabModeApp.generateCareerIdeas()"
                            >
                                Generate Career Ideas
                            </button>
                            <button 
                                class="lab-btn lab-btn-dice" 
                                id="career-dice-btn"
                                onclick="LabModeApp.rollCareerDice()"
                                title="Surprise me with wildcard suggestions!"
                            >
                                🎲 Surprise me
                            </button>
                        </div>
                        
                        <!-- Filters Bar -->
                        ${this.renderFiltersBar()}
                    </div>
                    
                    <div class="career-explorer-results" id="career-explorer-results" style="display: none;">
                        <!-- Results will be dynamically inserted here -->
                    </div>
                `;
            }
        },

        // Generate Career Ideas (new endpoint with filters)
        generateCareerIdeas: function () {
            const careerInput = document.getElementById('career-interest-input');
            const seedCareer = careerInput ? careerInput.value.trim() : '';

            // Analytics
            console.log('career_generate_clicked', {
                seed_career: seedCareer,
                filters: this.careerFilters,
                novelty_bias: 0.25
            });

            this.callCareerSuggestAPI(seedCareer, 0.25, false);
        },

        // Roll Career Dice (high novelty)
        rollCareerDice: function () {
            const careerInput = document.getElementById('career-interest-input');
            const seedCareer = careerInput ? careerInput.value.trim() : '';

            // Generate random novelty between 0.8 and 1.0
            const novelty = 0.8 + (Math.random() * 0.2);

            // Analytics
            console.log('career_dice_clicked', {
                seed_career: seedCareer,
                novelty_bias: novelty
            });

            this.callCareerSuggestAPI(seedCareer, novelty, true);
        },

        // Call the career suggest API
        callCareerSuggestAPI: function (seedCareer, noveltyBias, isDice) {
            // Show loading overlay
            if (window.AILoadingOverlay) {
                const messages = isDice ? [
                    'Rolling the dice for wildcard suggestions…',
                    'Exploring unexpected career paths…',
                    'Finding profile-true surprises…',
                    'Matching novelty with your unique strengths…'
                ] : [
                    seedCareer ? `Analyzing careers related to ${seedCareer}…` : 'Analyzing your profile…',
                    'Matching with your MI strengths and CDT profile…',
                    'Finding adjacent career paths with easy transitions…',
                    'Discovering parallel careers in different industries…',
                    'Identifying wildcard options based on your unique profile…'
                ];

                window.AILoadingOverlay.show({
                    subtitle: isDice ? 'AI is rolling the dice for you…' : 'AI is exploring career pathways for you…',
                    messages: messages
                });
            }

            // Prepare request data
            const requestData = {
                action: 'mc_lab_career_suggest',
                nonce: labMode.nonce,
                seed_career: seedCareer,
                filters: JSON.stringify(this.careerFilters),
                novelty_bias: noveltyBias,
                limit_per_bucket: 6
            };

            // Call AJAX endpoint
            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: requestData,
                success: (response) => {
                    // Hide loading overlay
                    if (window.AILoadingOverlay) {
                        window.AILoadingOverlay.hide();
                    }

                    if (response.success) {
                        this.displayCareerSuggestions(response.data, seedCareer, isDice);
                    } else {
                        const errorMsg = response.data || 'Failed to generate career suggestions';
                        console.error('Career suggestions error:', errorMsg);

                        // Show user-friendly error with suggestions
                        let displayMsg = errorMsg;
                        if (errorMsg.includes('timed out') || errorMsg.includes('timeout')) {
                            displayMsg += '\n\nTip: If you\'re an admin, try switching to "Minimal" prompt level in the admin settings at the top of this page.';
                        }

                        alert('Error: ' + displayMsg);
                    }
                },
                error: (xhr, status, error) => {
                    // Hide loading overlay
                    if (window.AILoadingOverlay) {
                        window.AILoadingOverlay.hide();
                    }

                    console.error('Career suggestions failed:', { xhr, status, error, response: xhr.responseText });

                    let errorMsg = 'Network error. Please check your connection and try again.';

                    // Try to parse error response
                    try {
                        const responseData = JSON.parse(xhr.responseText);
                        if (responseData && responseData.data) {
                            errorMsg = responseData.data;
                            if (errorMsg.includes('timed out') || errorMsg.includes('timeout')) {
                                errorMsg += '\n\nTip: Try switching to "Minimal" prompt level to reduce API response time.';
                            }
                        }
                    } catch (e) {
                        // Use default error message
                    }

                    alert(errorMsg);
                }
            });
        },

        // Display career suggestions (new format)
        displayCareerSuggestions: function (data, seedCareer, isDice) {
            // Store for feedback actions and re-rendering
            this.currentCareerInterest = seedCareer;
            this.isDiceRoll = isDice;
            this.currentCareerData = data;

            const resultsContainer = document.getElementById('career-explorer-results');

            // Choose rendering based on layout
            let contentHtml;
            if (this.careerLayout === 'map') {
                contentHtml = this.renderMindMapView(data, seedCareer, isDice);
            } else {
                contentHtml = this.renderCardsView(data, seedCareer, isDice);
            }

            const html = `
                <div class="career-map-results ${isDice ? 'dice-roll' : ''}">
                    ${contentHtml}
                    <div class="career-map-actions">
                        <button class="lab-btn lab-btn-secondary" onclick="document.getElementById('career-interest-input').value=''; document.getElementById('career-interest-input').focus(); document.getElementById('career-explorer-results').style.display='none';">
                            Explore Another Career
                        </button>
                    </div>
                </div>
            `;

            resultsContainer.innerHTML = html;
            resultsContainer.style.display = 'block';

            // Initialize mind-map if that's the active layout
            if (this.careerLayout === 'map') {
                setTimeout(() => {
                    this.initializeMindMap(data, seedCareer);
                }, 100);
            }

            // Scroll to results
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        // Render cards view (existing layout)
        renderCardsView: function (data, seedCareer, isDice) {
            return `
                <div class="career-map-header">
                    <div class="career-header-row">
                        <div>
                            <h3>${isDice ? '🎲 Dice Roll Results' : (seedCareer ? 'Career Ideas for: ' + seedCareer : 'Career Ideas Based on Your Profile')}</h3>
                            <p>Careers aligned with your MI, CDT, Bartle Type, and Johari profile</p>
                        </div>
                        <label class="compact-mode-toggle">
                            <input 
                                type="checkbox" 
                                ${this.compactMode ? 'checked' : ''} 
                                onchange="LabModeApp.toggleCompactMode(this.checked)"
                            >
                            <span>Compact mode</span>
                        </label>
                    </div>
                </div>
                
                <div class="career-clusters">
                    ${this.renderCareerClusterEnhanced('Adjacent Careers', 'Very similar; easy transitions', data.adjacent, 'adjacent')}
                    ${this.renderCareerClusterEnhanced('Parallel Careers', 'Similar strengths, different industries', data.parallel, 'parallel')}
                    ${this.renderCareerClusterEnhanced('Wildcard Careers', 'Unexpected options based on your unique profile', data.wildcard, 'wildcard')}
                </div>
            `;
        },

        // Render mind-map view (D3 visualization)
        renderMindMapView: function (data, seedCareer, isDice) {
            // Ensure filters are initialized
            if (!this.careerFilters) {
                this.careerFilters = this.getDefaultFilters();
                this.loadFiltersFromStorage();
            }

            // Get saved lane preference or default to 'adjacent'
            const savedLane = localStorage.getItem('mindmap_expansion_lane') || 'adjacent';

            return `
                <div class="career-mindmap-container">
                    <div class="mindmap-header">
                        <div class="mindmap-header-top">
                            <h3>${isDice ? '🎲 Dice Roll' : 'Mind-Map'}: ${seedCareer || 'Your Profile'}</h3>
                            <span class="mindmap-hint">👆 Click nodes to expand • Hover for quick actions</span>
                        </div>
                        
                        <div class="mindmap-breadcrumbs"></div>
                    </div>
                    
                    <!-- Full-width expansion type selector -->
                    <div class="mindmap-lane-selector-bar">
                        <span class="lane-selector-label">Expansion type:</span>
                        <div class="lane-selector-buttons">
                            <button class="lane-selector-btn ${savedLane === 'goodfit' ? 'active' : ''}" data-lane="goodfit" onclick="LabModeApp.setExpansionLane('goodfit')">
                                <span class="lane-icon">⭐</span>
                                <span class="lane-text">
                                    <strong>Good Fit</strong>
                                    <small>Best matches</small>
                                </span>
                            </button>
                            <button class="lane-selector-btn ${savedLane === 'adjacent' ? 'active' : ''}" data-lane="adjacent" onclick="LabModeApp.setExpansionLane('adjacent')">
                                <span class="lane-dot lane-adjacent"></span>
                                <span class="lane-text">
                                    <strong>Adjacent</strong>
                                    <small>Easy transitions</small>
                                </span>
                            </button>
                            <button class="lane-selector-btn ${savedLane === 'parallel' ? 'active' : ''}" data-lane="parallel" onclick="LabModeApp.setExpansionLane('parallel')">
                                <span class="lane-dot lane-parallel"></span>
                                <span class="lane-text">
                                    <strong>Parallel</strong>
                                    <small>Different industries</small>
                                </span>
                            </button>
                            <button class="lane-selector-btn ${savedLane === 'wildcard' ? 'active' : ''}" data-lane="wildcard" onclick="LabModeApp.setExpansionLane('wildcard')">
                                <span class="lane-dot lane-wildcard"></span>
                                <span class="lane-text">
                                    <strong>Wildcard</strong>
                                    <small>Unexpected options</small>
                                </span>
                            </button>
                            <button class="lane-selector-btn ${savedLane === 'mixed' ? 'active' : ''}" data-lane="mixed" onclick="LabModeApp.setExpansionLane('mixed')">
                                <span class="lane-icon">✨</span>
                                <span class="lane-text">
                                    <strong>Mixed</strong>
                                    <small>All types (3× cost)</small>
                                </span>
                            </button>
                        </div>
                    </div>
                    
                    <div id="career-mindmap-canvas"></div>
                    <div class="mindmap-node-drawer" id="mindmap-drawer" style="display: none;"></div>
                </div>
            `;
        },

        // Initialize D3 mind-map visualization
        initializeMindMap: function (data, seedCareer) {
            console.log('initializeMindMap called', { d3Available: typeof d3 !== 'undefined', data, seedCareer });

            if (typeof d3 === 'undefined') {
                console.error('D3.js not loaded - check that d3js script is enqueued');
                $('#career-mindmap-canvas').html('<div style="padding: 40px; text-align: center; color: #dc2626;">D3.js library not loaded. Please refresh the page.</div>');
                return;
            }

            // Setup sticky expansion filters
            this.setupStickyExpansionFilters();

            const canvas = $('#career-mindmap-canvas');
            if (!canvas.length) {
                console.error('Mind-map canvas not found');
                return;
            }

            const width = canvas.width();
            if (width === 0) {
                console.error('Canvas width is 0, waiting for layout...');
                setTimeout(() => this.initializeMindMap(data, seedCareer), 200);
                return;
            }

            // Reset state for new map
            this.mindMapState = {
                centerId: 'seed',
                nodes: {},
                edges: [],
                openRequests: new Set(),
                history: [],
                expandedNodes: new Set(),
                totalUsage: {
                    total_tokens: 0,
                    total_cost_usd: 0,
                    api_calls: 0
                },
                hasShownLaneAlert: false
            };

            // Populate initial state from data
            // Seed node
            this.mindMapState.nodes['seed'] = {
                id: 'seed',
                title: seedCareer || 'Your Profile',
                type: 'seed',
                depth: 0
            };

            // Adjacent careers
            if (data.adjacent && data.adjacent.length > 0) {
                data.adjacent.forEach((career, i) => {
                    const nodeId = `adj-${i}`;
                    this.mindMapState.nodes[nodeId] = {
                        id: nodeId,
                        title: career.title,
                        type: 'career',
                        lane: 'adjacent',
                        depth: 1,
                        parentId: 'seed',
                        fit: career.profile_match?.fit || 0.7,
                        similarity: career.profile_match?.similarity || 0.8,
                        data: career,
                        mi: career.profile_match?.mi || [],
                        cdt_top: career.profile_match?.cdt_top,
                        bartle: career.profile_match?.bartle
                    };

                    this.mindMapState.edges.push({
                        source: 'seed',
                        target: nodeId,
                        similarity: career.profile_match?.similarity || 0.8
                    });
                });
            }

            // Parallel careers
            if (data.parallel && data.parallel.length > 0) {
                data.parallel.forEach((career, i) => {
                    const nodeId = `par-${i}`;
                    this.mindMapState.nodes[nodeId] = {
                        id: nodeId,
                        title: career.title,
                        type: 'career',
                        lane: 'parallel',
                        depth: 1,
                        parentId: 'seed',
                        fit: career.profile_match?.fit || 0.6,
                        similarity: career.profile_match?.similarity || 0.5,
                        data: career,
                        mi: career.profile_match?.mi || [],
                        cdt_top: career.profile_match?.cdt_top,
                        bartle: career.profile_match?.bartle
                    };

                    this.mindMapState.edges.push({
                        source: 'seed',
                        target: nodeId,
                        similarity: career.profile_match?.similarity || 0.5
                    });
                });
            }

            // Wildcard careers
            if (data.wildcard && data.wildcard.length > 0) {
                data.wildcard.forEach((career, i) => {
                    const nodeId = `wild-${i}`;
                    this.mindMapState.nodes[nodeId] = {
                        id: nodeId,
                        title: career.title,
                        type: 'career',
                        lane: 'wildcard',
                        depth: 1,
                        parentId: 'seed',
                        fit: career.profile_match?.fit || 0.5,
                        similarity: career.profile_match?.similarity || 0.3,
                        data: career,
                        mi: career.profile_match?.mi || [],
                        cdt_top: career.profile_match?.cdt_top,
                        bartle: career.profile_match?.bartle
                    };

                    this.mindMapState.edges.push({
                        source: 'seed',
                        target: nodeId,
                        similarity: career.profile_match?.similarity || 0.3
                    });
                });
            }

            console.log('State initialized:', {
                nodeCount: Object.keys(this.mindMapState.nodes).length,
                edgeCount: this.mindMapState.edges.length
            });

            // Initialize breadcrumbs
            this.updateBreadcrumbs();

            // Render from state
            this.updateMindMapVisualization();
        },

        // Prepare mind-map nodes from career data
        prepareMindMapNodes: function (data, seedCareer) {
            const nodes = [];

            // Center node
            nodes.push({
                id: 'seed',
                title: seedCareer || 'Your Profile',
                type: 'seed'
            });

            // Adjacent careers
            if (data.adjacent && data.adjacent.length > 0) {
                data.adjacent.forEach((career, i) => {
                    nodes.push({
                        id: `adj-${i}`,
                        title: career.title,
                        type: 'career',
                        lane: 'adjacent',
                        fit: career.profile_match?.fit || 0.7,
                        similarity: career.profile_match?.similarity || 0.8,
                        data: career
                    });
                });
            }

            // Parallel careers
            if (data.parallel && data.parallel.length > 0) {
                data.parallel.forEach((career, i) => {
                    nodes.push({
                        id: `par-${i}`,
                        title: career.title,
                        type: 'career',
                        lane: 'parallel',
                        fit: career.profile_match?.fit || 0.6,
                        similarity: career.profile_match?.similarity || 0.5,
                        data: career
                    });
                });
            }

            // Wildcard careers
            if (data.wildcard && data.wildcard.length > 0) {
                data.wildcard.forEach((career, i) => {
                    nodes.push({
                        id: `wild-${i}`,
                        title: career.title,
                        type: 'career',
                        lane: 'wildcard',
                        fit: career.profile_match?.fit || 0.5,
                        similarity: career.profile_match?.similarity || 0.3,
                        data: career
                    });
                });
            }

            return nodes;
        },

        // Prepare mind-map links
        prepareMindMapLinks: function (nodes) {
            const links = [];
            const seed = nodes.find(n => n.type === 'seed');

            nodes.forEach(node => {
                if (node.type === 'career') {
                    links.push({
                        source: seed.id,
                        target: node.id,
                        similarity: node.similarity || 0.5
                    });
                }
            });

            return links;
        },

        // Show mind-map node drawer
        showMindMapNodeDrawer: function (nodeData) {
            if (!nodeData.data) return; // Skip seed node

            const career = nodeData.data;
            const drawer = $('#mindmap-drawer');

            const html = `
                <div class="drawer-header">
                    <h4>${this.escapeHtml(career.title)}</h4>
                    <button class="drawer-close" onclick="LabModeApp.closeMindMapDrawer()">✕</button>
                </div>
                <div class="drawer-body">
                    <p>${this.escapeHtml(career.why_it_fits)}</p>
                    ${career.profile_match ? this.renderProfileMatchSection(career.profile_match, false) : ''}
                    ${career.meta ? this.renderRequirementsSection(career.meta, false) : ''}
                    ${career.meta ? this.renderWorkStyleSection(career.meta, false) : ''}
                </div>
                <div class="drawer-actions">
                    <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.mindMapNodeAction('not_interested', '${nodeData.id}')">
                        Not interested
                    </button>
                    <button class="lab-btn lab-btn-primary" onclick="LabModeApp.mindMapNodeAction('save', '${nodeData.id}')">
                        Save ♥
                    </button>
                </div>
            `;

            drawer.html(html).slideDown(200);

            // Analytics
            console.log('career_map_node_opened', { nodeId: nodeData.id, lane: nodeData.lane });
        },

        // Close mind-map drawer
        closeMindMapDrawer: function () {
            $('#mindmap-drawer').slideUp(200);
        },

        // Mind-map node action (save/dismiss)
        mindMapNodeAction: function (action, nodeId) {
            // Find the node data
            const nodes = this.currentCareerData ?
                [...(this.currentCareerData.adjacent || []),
                ...(this.currentCareerData.parallel || []),
                ...(this.currentCareerData.wildcard || [])] : [];

            // Parse node ID to find career
            const match = nodeId.match(/^(adj|par|wild)-(\d+)$/);
            if (!match) return;

            const [, lane, index] = match;
            const laneMap = { adj: 'adjacent', par: 'parallel', wild: 'wildcard' };
            const laneName = laneMap[lane];
            const careerIndex = parseInt(index);

            const careerData = this.currentCareerData[laneName]?.[careerIndex];
            if (!careerData) return;

            // Handle action
            if (action === 'save') {
                console.log('career_map_node_action', { action: 'save', nodeId, lane: laneName });
                // Call save API (reuse existing saveCareer logic)
                this.saveCareerFromMindMap(careerData, laneName);
            } else if (action === 'not_interested') {
                console.log('career_map_node_action', { action: 'dismiss', nodeId, lane: laneName });
                // Close drawer and maybe remove from view
                this.closeMindMapDrawer();
                alert('Career dismissed. This feature will be enhanced to replace the node.');
            }
        },

        // Save career from mind-map
        saveCareerFromMindMap: function (career, clusterType) {
            const centralCareer = this.currentCareerInterest || '';

            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_career_feedback',
                    nonce: labMode.nonce,
                    feedback_action: 'save',
                    career_rejected: career.title,
                    central_career: centralCareer,
                    distance_group: clusterType,
                    career_data: JSON.stringify({
                        title: career.title,
                        why_it_fits: career.why_it_fits,
                        distance_group: clusterType,
                        central_career: centralCareer
                    })
                },
                success: (response) => {
                    if (response.success) {
                        this.closeMindMapDrawer();
                        alert('Career saved! View it in the Saved Ideas tab.');
                    } else {
                        alert('Failed to save career');
                    }
                },
                error: () => {
                    alert('Network error. Please try again.');
                }
            });
        },

        // D3 drag behavior for mind-map
        mindMapDrag: function (simulation) {
            function dragstarted(event) {
                if (!event.active) simulation.alphaTarget(0.3).restart();
                event.subject.fx = event.subject.x;
                event.subject.fy = event.subject.y;
            }

            function dragged(event) {
                event.subject.fx = event.x;
                event.subject.fy = event.y;
            }

            function dragended(event) {
                if (!event.active) simulation.alphaTarget(0);
                event.subject.fx = null;
                event.subject.fy = null;
            }

            return d3.drag()
                .on('start', dragstarted)
                .on('drag', dragged)
                .on('end', dragended);
        },

        // Setup sticky expansion filters
        setupStickyExpansionFilters: function () {
            const laneSelector = $('.mindmap-lane-selector-bar');
            if (!laneSelector.length) return;

            // Remove any existing scroll handler
            $(window).off('scroll.stickyFilters');

            // Get initial position
            const initialOffset = laneSelector.offset()?.top || 0;

            // Add scroll handler
            $(window).on('scroll.stickyFilters', function () {
                const scrollTop = $(window).scrollTop();

                // Add/remove sticky-active class based on scroll position
                if (scrollTop > initialOffset - 10) {
                    laneSelector.addClass('sticky-active');
                } else {
                    laneSelector.removeClass('sticky-active');
                }
            });
        },

        // Set expansion lane preference
        setExpansionLane: function (lane) {
            localStorage.setItem('mindmap_expansion_lane', lane);

            // Update button states
            $('.lane-selector-btn').removeClass('active');
            $(`.lane-selector-btn[data-lane="${lane}"]`).addClass('active');

            console.log('Expansion lane set to:', lane);
        },

        // Get current expansion lane
        getExpansionLane: function () {
            return localStorage.getItem('mindmap_expansion_lane') || 'adjacent';
        },

        // Expand node with selected lane(s)
        expandNodeWithLane: async function (nodeId, lane) {
            const node = this.mindMapState.nodes[nodeId];
            if (!node) {
                console.error('Node not found:', nodeId);
                return;
            }

            // Check if already expanded
            if (this.mindMapState.expandedNodes.has(nodeId)) {
                console.log('Node already expanded:', nodeId);
                this.showNodeFeedback(nodeId, 'Already expanded', 'info');
                return;
            }

            // Check if already loading
            const requestKey = nodeId;
            if (this.mindMapState.openRequests.has(requestKey)) {
                console.log('Already loading:', requestKey);
                return;
            }

            this.mindMapState.openRequests.add(requestKey);

            // Determine which lanes to fetch
            let lanesToFetch;
            if (lane === 'mixed') {
                lanesToFetch = ['adjacent', 'parallel', 'wildcard'];
            } else if (lane === 'goodfit') {
                // Good fit uses adjacent lane with high fit threshold
                lanesToFetch = ['adjacent'];
            } else {
                lanesToFetch = [lane];
            }

            const laneConfig = {
                goodfit: { limit: 5, novelty: 0.05, label: 'goodfit' },
                adjacent: { limit: 5, novelty: 0.1, label: 'adjacent' },
                parallel: { limit: 5, novelty: 0.25, label: 'parallel' },
                wildcard: { limit: 4, novelty: 0.5, label: 'wildcard' }
            };

            // Show loading feedback
            const loadingMsg = lane === 'mixed'
                ? 'Loading variety of careers...'
                : `Loading ${lane} careers...`;
            this.showNodeFeedback(nodeId, loadingMsg, 'loading');
            console.log(`Expanding node with lane: ${lane}`, nodeId);

            try {
                // Fetch selected lane(s)
                // Get prompt settings from localStorage (same as initial generation)
                const promptLevel = localStorage.getItem('career_prompt_level') || 'minimal';
                const promptTemplates = {
                    minimal: localStorage.getItem('career_prompt_minimal') || '',
                    medium: localStorage.getItem('career_prompt_medium') || '',
                    maximum: localStorage.getItem('career_prompt_maximum') || ''
                };

                const requests = lanesToFetch.map(laneType => {
                    const actualLane = lane === 'goodfit' ? 'adjacent' : laneType;
                    const config = laneConfig[lane === 'goodfit' ? 'goodfit' : laneType];
                    return $.ajax({
                        url: labMode.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'mc_lab_get_related_careers',
                            nonce: labMode.nonce,
                            career_title: node.title,
                            lane: actualLane,
                            limit: lane === 'mixed' ? 3 : config.limit,
                            novelty: config.novelty,
                            filters: JSON.stringify(this.careerFilters || {}),
                            min_fit: lane === 'goodfit' ? 0.7 : 0,  // Only for goodfit lane
                            prompt_level: promptLevel,
                            prompt_templates: JSON.stringify(promptTemplates)
                        }
                    });
                });

                const responses = await Promise.all(requests);

                // Collect all successful results and track usage
                let totalAdded = 0;

                responses.forEach((response, index) => {
                    if (response.success && response.data) {
                        const careers = response.data.careers || response.data;
                        const laneType = lanesToFetch[index];
                        const addedCount = this.addChildrenToMap(nodeId, laneType, careers);
                        totalAdded += addedCount;
                        if (response.data.usage) this.trackUsage(response.data.usage);
                    }
                });

                if (totalAdded > 0) {
                    this.mindMapState.expandedNodes.add(nodeId);
                    this.updateMindMapVisualization();

                    // Show success feedback
                    const successMsg = lane === 'mixed'
                        ? `Added ${totalAdded} careers (mixed types)`
                        : `Added ${totalAdded} ${lane} careers`;
                    this.showNodeFeedback(nodeId, successMsg, 'success');

                    // Update usage display for admins
                    this.updateUsageDisplay();

                    // Analytics
                    console.log('career_map_expand', { nodeId, lane, totalAdded });
                } else {
                    this.showNodeFeedback(nodeId, 'No careers found', 'error');
                }
            } catch (error) {
                console.error('Error expanding node:', error);
                this.showNodeFeedback(nodeId, 'Network error', 'error');
            } finally {
                this.mindMapState.openRequests.delete(requestKey);
            }
        },

        // Add children to map with deduplication
        addChildrenToMap: function (parentId, lane, children) {
            const parentNode = this.mindMapState.nodes[parentId];
            if (!parentNode) return 0;

            const parentDepth = parentNode.depth || 0;
            const childDepth = parentDepth + 1;

            // Depth limit: 3 rings from current center
            if (childDepth > 3) {
                console.warn('Depth limit reached, not adding children');
                return 0;
            }

            let addedCount = 0;

            children.forEach(career => {
                // Check if career already exists in the map
                const existingNode = Object.values(this.mindMapState.nodes).find(n =>
                    n.title.toLowerCase() === career.title.toLowerCase()
                );

                if (existingNode) {
                    // Deduplicate: skip adding duplicate edge to reduce clutter
                    console.log('Career already exists, skipping duplicate:', career.title);
                } else {
                    // Add new node
                    const nodeId = career.id || `career-${Math.random().toString(36).substr(2, 9)}`;

                    this.mindMapState.nodes[nodeId] = {
                        id: nodeId,
                        title: career.title,
                        type: 'career',
                        lane: career.lane || lane,
                        depth: childDepth,
                        parentId: parentId,
                        fit: career.fit || 0.7,
                        similarity: career.similarity || 0.5,
                        data: career,
                        mi: career.mi || [],
                        cdt_top: career.cdt_top,
                        bartle: career.bartle
                    };

                    // Add edge from parent to new child
                    this.mindMapState.edges.push({
                        source: parentId,
                        target: nodeId,
                        similarity: career.similarity || 0.5
                    });

                    addedCount++;
                }
            });

            return addedCount;
        },

        // Update Mind-Map visualization from state
        updateMindMapVisualization: function () {
            if (typeof d3 === 'undefined') {
                console.error('D3.js not loaded');
                return;
            }

            const canvas = $('#career-mindmap-canvas');
            if (!canvas.length) {
                console.error('Mind-map canvas not found');
                return;
            }

            // Clear existing SVG
            canvas.empty();

            // Get viewport width and use 90% of it
            const viewportWidth = $(window).width();
            const targetWidth = Math.floor(viewportWidth * 0.9);

            // Force the canvas to the target width
            canvas.css({
                'width': targetWidth + 'px',
                'margin': '0 auto'
            });

            // Walk up ALL parent containers and force them to full width
            canvas.parents().each(function () {
                $(this).css({
                    'width': '100%',
                    'max-width': 'none',
                    'margin-left': 'auto',
                    'margin-right': 'auto'
                });
            });

            const width = targetWidth;
            const height = 800;

            console.log('Mind map at 90% width:', width, 'viewport:', viewportWidth);

            // Create SVG with zoom support
            const svg = d3.select('#career-mindmap-canvas')
                .append('svg')
                .attr('width', width)
                .attr('height', height)
                .attr('viewBox', [0, 0, width, height]);

            // Add zoom group
            const zoomGroup = svg.append('g');

            // Setup zoom behavior
            const zoom = d3.zoom()
                .scaleExtent([0.3, 3])
                .on('zoom', (event) => {
                    zoomGroup.attr('transform', event.transform);
                });

            svg.call(zoom);

            // Convert state to D3 format
            const nodes = Object.values(this.mindMapState.nodes);

            // Only show parent-child edges (tree structure) to reduce clutter
            const links = this.mindMapState.edges
                .filter(e => {
                    const targetNode = this.mindMapState.nodes[e.target.id || e.target];
                    return targetNode && targetNode.parentId === (e.source.id || e.source);
                })
                .map(e => ({ ...e })); // Clone edges

            console.log('Updating visualization:', { nodeCount: nodes.length, linkCount: links.length });

            // Force simulation with improved spacing
            const simulation = d3.forceSimulation(nodes)
                .force('link', d3.forceLink(links).id(d => d.id).distance(180))
                .force('charge', d3.forceManyBody().strength(-500))
                .force('center', d3.forceCenter(width / 2, height / 2))
                .force('collision', d3.forceCollide().radius(60));

            // Render links
            const link = zoomGroup.append('g')
                .selectAll('line')
                .data(links)
                .join('line')
                .attr('class', 'mindmap-link')
                .attr('stroke', '#94a3b8')
                .attr('stroke-width', d => (d.similarity || 0.5) * 3);

            // Render nodes
            const node = zoomGroup.append('g')
                .selectAll('g')
                .data(nodes)
                .join('g')
                .attr('class', d => `mindmap-node ${d.type}`)
                .call(this.mindMapDrag(simulation));

            // Node circles with good-fit highlighting (70%+ fit)
            node.append('circle')
                .attr('r', d => d.type === 'seed' ? 30 : 20)
                .attr('class', d => {
                    let classes = d.lane ? `node-${d.lane}` : 'node-seed';
                    if (d.type === 'career' && (d.fit || 0) >= 0.7) {
                        classes += ' node-matches-filter';
                    }
                    return classes;
                })
                .attr('stroke-width', d => (d.fit || 0.5) * 5);

            // Node labels with improved positioning and wrapping
            node.each(function (d) {
                const nodeGroup = d3.select(this);
                const words = d.title.split(' ');
                const lineHeight = 12;
                const maxWidth = 100;

                // Simple word wrapping
                if (d.title.length > 15) {
                    const lines = [];
                    let currentLine = '';

                    words.forEach(word => {
                        const testLine = currentLine + (currentLine ? ' ' : '') + word;
                        if (testLine.length > 15 && currentLine) {
                            lines.push(currentLine);
                            currentLine = word;
                        } else {
                            currentLine = testLine;
                        }
                    });
                    if (currentLine) lines.push(currentLine);

                    // Render multi-line text
                    const startY = 35 + (lines.length - 1) * lineHeight / 2;
                    lines.forEach((line, i) => {
                        nodeGroup.append('text')
                            .text(line)
                            .attr('dy', startY + (i * lineHeight))
                            .attr('text-anchor', 'middle')
                            .attr('class', 'node-label');
                    });
                } else {
                    // Single line
                    nodeGroup.append('text')
                        .text(d.title)
                        .attr('dy', 35)
                        .attr('text-anchor', 'middle')
                        .attr('class', 'node-label');
                }
            });

            // Single-click: expand with selected lane
            const self = this;
            node.on('click', function (event, d) {
                event.stopPropagation();
                if (d.type === 'career') {
                    // Add visual feedback: pulsing ring
                    const clickedNode = d3.select(this);

                    // Add a temporary pulsing ring
                    const ring = clickedNode.append('circle')
                        .attr('r', 20)
                        .attr('fill', 'none')
                        .attr('stroke', '#3B82F6')
                        .attr('stroke-width', 3)
                        .attr('opacity', 1);

                    // Animate the ring
                    ring.transition()
                        .duration(600)
                        .attr('r', 40)
                        .attr('opacity', 0)
                        .remove();

                    // Also pulse the node itself
                    clickedNode.select('circle')
                        .transition()
                        .duration(150)
                        .attr('r', 25)
                        .transition()
                        .duration(150)
                        .attr('r', 20);

                    const lane = self.getExpansionLane();
                    self.expandNodeWithLane(d.id, lane);
                }
            });

            // Hover: show tooltip
            node.on('mouseenter', (event, d) => {
                if (d.type === 'career') {
                    this.showNodeTooltip(event, d);
                }
            });

            node.on('mouseleave', () => {
                // Don't hide tooltip immediately - give time to click buttons
                setTimeout(() => {
                    const tooltip = $('#mindmap-tooltip');
                    if (!tooltip.is(':hover')) {
                        this.hideNodeTooltip();
                    }
                }, 100);
            });

            // Update positions on tick
            simulation.on('tick', () => {
                link
                    .attr('x1', d => d.source.x)
                    .attr('y1', d => d.source.y)
                    .attr('x2', d => d.target.x)
                    .attr('y2', d => d.target.y);

                node.attr('transform', d => `translate(${d.x},${d.y})`);
            });
        },

        // Set new map center (re-root)
        setMapCenter: function (nodeId) {
            const node = this.mindMapState.nodes[nodeId];
            if (!node || node.type === 'seed') return;

            // Add current center to history
            this.mindMapState.history.push(this.mindMapState.centerId);

            // Set new center
            this.mindMapState.centerId = nodeId;

            // Recalculate depths from new center
            this.recalculateDepths(nodeId);

            // Update breadcrumbs
            this.updateBreadcrumbs();

            // Re-render
            this.updateMindMapVisualization();

            // Analytics
            console.log('career_map_reroot', { nodeId });
        },

        // Recalculate depths from new center using BFS
        recalculateDepths: function (centerId) {
            // Reset all depths
            Object.values(this.mindMapState.nodes).forEach(node => {
                node.depth = Infinity;
            });

            // BFS from center
            const queue = [centerId];
            this.mindMapState.nodes[centerId].depth = 0;

            while (queue.length > 0) {
                const currentId = queue.shift();
                const currentNode = this.mindMapState.nodes[currentId];
                const currentDepth = currentNode.depth;

                // Find all connected nodes
                this.mindMapState.edges.forEach(edge => {
                    let neighborId = null;

                    if (edge.source === currentId || edge.source.id === currentId) {
                        neighborId = edge.target.id || edge.target;
                    } else if (edge.target === currentId || edge.target.id === currentId) {
                        neighborId = edge.source.id || edge.source;
                    }

                    if (neighborId) {
                        const neighbor = this.mindMapState.nodes[neighborId];
                        if (neighbor && neighbor.depth > currentDepth + 1) {
                            neighbor.depth = currentDepth + 1;
                            queue.push(neighborId);
                        }
                    }
                });
            }
        },

        // Update breadcrumbs navigation
        updateBreadcrumbs: function () {
            const path = this.getPathToNode(this.mindMapState.centerId);
            const breadcrumbsHtml = path.map((nodeId, index) => {
                const node = this.mindMapState.nodes[nodeId];
                const isLast = index === path.length - 1;
                return `
                    <span class="breadcrumb-item ${isLast ? 'active' : ''}" 
                          ${!isLast ? `onclick="LabModeApp.setMapCenter('${nodeId}')"` : ''}>
                        ${this.escapeHtml(node.title)}
                    </span>
                    ${!isLast ? '<span class="breadcrumb-separator">›</span>' : ''}
                `;
            }).join('');

            $('.mindmap-breadcrumbs').html(breadcrumbsHtml);
        },

        // Get path from seed to specified node
        getPathToNode: function (nodeId) {
            const path = [];
            let currentId = nodeId;

            while (currentId) {
                path.unshift(currentId);
                const node = this.mindMapState.nodes[currentId];
                if (!node || node.type === 'seed') break;
                currentId = node.parentId;
            }

            return path;
        },

        // Show lightweight tooltip on hover
        showNodeTooltip: function (event, nodeData) {
            let tooltip = $('#mindmap-tooltip');
            if (!tooltip.length) {
                // Create tooltip if it doesn't exist
                $('body').append('<div id="mindmap-tooltip" class="mindmap-tooltip" style="display: none;"></div>');
                tooltip = $('#mindmap-tooltip');
            }

            // Ensure MI is an array
            let miArray = [];
            if (Array.isArray(nodeData.mi)) {
                miArray = nodeData.mi;
            } else if (nodeData.mi) {
                miArray = [nodeData.mi];
            }

            // Render MI badges
            const miBadges = miArray.slice(0, 2).map(mi => `<span class="badge-mi-sm">${this.escapeHtml(mi)}</span>`).join('');

            // Get metadata from node data if available
            const meta = nodeData.data?.meta || {};
            let metaHtml = '';

            if (Object.keys(meta).length > 0) {
                const metaItems = [];

                if (meta.demand_horizon) {
                    metaItems.push(`<span class="tooltip-meta-item" title="Demand Horizon">📈 ${this.formatMetaLabel('demand_horizon', meta.demand_horizon)}</span>`);
                }

                if (meta.education_level && meta.education_level.length > 0) {
                    metaItems.push(`<span class="tooltip-meta-item" title="Education Level">🎓 ${this.formatMetaLabel('education', meta.education_level[0])}</span>`);
                }

                if (meta.comp_band) {
                    metaItems.push(`<span class="tooltip-meta-item" title="Compensation Band">💰 ${this.formatMetaLabel('comp_band', meta.comp_band)}</span>`);
                }

                if (meta.work_env && meta.work_env.length > 0) {
                    const envLabel = this.formatMetaLabel('work_env', meta.work_env[0]);
                    metaItems.push(`<span class="tooltip-meta-item" title="Work Environment">🏢 ${envLabel}</span>`);
                }

                if (meta.social_impact && meta.social_impact.length > 0) {
                    metaItems.push(`<span class="tooltip-meta-item" title="Social Impact">🌍 ${this.formatMetaLabel('social_impact', meta.social_impact[0])}</span>`);
                }

                if (metaItems.length > 0) {
                    metaHtml = `<div class="tooltip-meta">${metaItems.slice(0, 3).join('')}</div>`;
                }
            }

            const html = `
                <div class="tooltip-header">
                    <strong>${this.escapeHtml(nodeData.title)}</strong>
                    <span class="tooltip-lane badge-${nodeData.lane}">${nodeData.lane}</span>
                </div>
                <div class="tooltip-stats">
                    <span class="tooltip-stat">Fit: ${Math.round((nodeData.fit || 0) * 100)}%</span>
                    <span class="tooltip-stat">Similarity: ${Math.round((nodeData.similarity || 0) * 100)}%</span>
                </div>
                <div class="tooltip-badges">
                    ${miBadges}
                    ${nodeData.bartle ? `<span class="badge-bartle-sm">${this.escapeHtml(nodeData.bartle)}</span>` : ''}
                </div>
                ${metaHtml}
                <div class="tooltip-actions">
                    <button class="tooltip-btn tooltip-btn-info" data-node-id="${nodeData.id}" title="Why this fits">?</button>
                    <button class="tooltip-btn tooltip-btn-save" data-node-id="${nodeData.id}" title="Save">♥</button>
                    <button class="tooltip-btn tooltip-btn-dismiss" data-node-id="${nodeData.id}" title="Not interested">✕</button>
                </div>
            `;

            tooltip
                .html(html)
                .css({
                    left: event.pageX + 10,
                    top: event.pageY + 10,
                    display: 'block'
                });

            // Store reference to this for event handlers
            const self = this;

            // Bind click events to tooltip buttons (use event delegation)
            tooltip.off('click').on('click', '.tooltip-btn-save', function (e) {
                e.stopPropagation();
                const nodeId = $(e.currentTarget).data('node-id');
                const node = self.mindMapState.nodes[nodeId];
                if (node && node.data) {
                    self.saveCareerFromMindMap(node.data, node.lane);
                }
            });

            tooltip.on('click', '.tooltip-btn-info', function (e) {
                e.stopPropagation();
                const nodeId = $(e.currentTarget).data('node-id');
                const node = self.mindMapState.nodes[nodeId];
                if (node && node.data) {
                    self.showMindMapCareerExplanation(node);
                }
            });

            tooltip.on('click', '.tooltip-btn-dismiss', function (e) {
                e.stopPropagation();
                const nodeId = $(e.currentTarget).data('node-id');
                self.dismissCareerFromMap(nodeId);
            });

            // Keep tooltip visible when hovering over it
            tooltip.off('mouseleave').on('mouseleave', function () {
                self.hideNodeTooltip();
            });
        },

        // Hide tooltip
        hideNodeTooltip: function () {
            $('#mindmap-tooltip').css('display', 'none');
        },

        // Show career explanation modal for mind map nodes
        showMindMapCareerExplanation: function (node) {
            const careerTitle = node.title;
            const centralCareer = this.currentCareerInterest || 'Your Profile';
            const clusterType = node.lane || 'adjacent';

            // Hide tooltip
            this.hideNodeTooltip();

            // Check cache first
            if (!this.careerExplanationCache) {
                this.careerExplanationCache = {};
            }

            if (this.careerExplanationCache[careerTitle]) {
                this.displayExplanationModal(careerTitle, this.careerExplanationCache[careerTitle], node);
                return;
            }

            // Show loading modal
            this.displayExplanationModal(careerTitle, { loading: true }, node);

            // Fetch explanation from server
            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_career_feedback',
                    nonce: labMode.nonce,
                    feedback_action: 'explain_fit',
                    career_rejected: careerTitle,
                    central_career: centralCareer,
                    distance_group: clusterType
                },
                success: (response) => {
                    if (response.success && response.data.explanation) {
                        this.careerExplanationCache[careerTitle] = response.data.explanation;
                        this.displayExplanationModal(careerTitle, response.data.explanation, node);
                    } else {
                        this.displayExplanationModal(careerTitle, { error: 'Failed to load explanation' }, node);
                    }
                },
                error: () => {
                    this.displayExplanationModal(careerTitle, { error: 'Network error' }, node);
                }
            });
        },

        // Display explanation in a modal
        displayExplanationModal: function (careerTitle, sections, node) {
            // Remove any existing modal
            $('#mindmap-explanation-modal').remove();

            let content;
            if (sections.loading) {
                content = '<div class="explanation-loading">Loading explanation...</div>';
            } else if (sections.error) {
                content = `<div class="explanation-error">${sections.error}</div>`;
            } else {
                content = `
                    <div class="explanation-columns">
                        <div class="explanation-column">
                            <div class="explanation-section-header">Why this fits your profile</div>
                            <div class="explanation-section-content">${this.escapeHtml(sections.profile_fit)}</div>
                        </div>
                        <div class="explanation-column">
                            <div class="explanation-section-header">A typical day</div>
                            <div class="explanation-section-content">${this.escapeHtml(sections.typical_day)}</div>
                        </div>
                    </div>
                `;
            }

            const modal = $(`
                <div id="mindmap-explanation-modal" class="mindmap-explanation-modal">
                    <div class="explanation-modal-overlay"></div>
                    <div class="explanation-modal-content">
                        <div class="explanation-modal-header">
                            <h3>${this.escapeHtml(careerTitle)}</h3>
                            <button class="explanation-modal-close">✕</button>
                        </div>
                        <div class="explanation-modal-body">
                            ${content}
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Bind close events
            modal.find('.explanation-modal-close, .explanation-modal-overlay').on('click', function () {
                modal.fadeOut(200, function () { $(this).remove(); });
            });

            // Show with animation
            modal.hide().fadeIn(200);
        },

        // Dismiss career from map (soft hide)
        dismissCareerFromMap: function (nodeId) {
            console.log('Dismissing career:', nodeId);
            // For now, just hide the node (can be enhanced to replace it)
            delete this.mindMapState.nodes[nodeId];
            this.mindMapState.edges = this.mindMapState.edges.filter(e =>
                e.source !== nodeId && e.target !== nodeId &&
                e.source.id !== nodeId && e.target.id !== nodeId
            );
            this.hideNodeTooltip();
            this.updateMindMapVisualization();
        },

        // Render filter summary for Mind-Map
        renderFilterSummary: function () {
            const filters = this.careerFilters || this.getDefaultFilters();
            const activeFilters = [];

            // Check each filter type
            if (filters.demand_horizon) {
                activeFilters.push({ label: 'Demand', value: filters.demand_horizon.replace(/_/g, ' ') });
            }
            if (filters.education_levels && filters.education_levels.length > 0) {
                activeFilters.push({ label: 'Education', value: filters.education_levels.length + ' selected' });
            }
            if (filters.work_env && filters.work_env.length > 0) {
                activeFilters.push({ label: 'Work Env', value: filters.work_env.length + ' selected' });
            }
            if (filters.role_orientation && filters.role_orientation.length > 0) {
                activeFilters.push({ label: 'Role Type', value: filters.role_orientation.length + ' selected' });
            }
            if (filters.comp_band) {
                activeFilters.push({ label: 'Comp', value: filters.comp_band });
            }
            if (filters.social_impact && filters.social_impact.length > 0) {
                activeFilters.push({ label: 'Impact', value: filters.social_impact.length + ' selected' });
            }
            if (filters.remote_only) {
                activeFilters.push({ label: 'Remote', value: 'only' });
            }
            if (filters.stretch_opposites) {
                activeFilters.push({ label: 'Stretch', value: 'on' });
            }

            if (activeFilters.length === 0) {
                return `
                    <div class="filter-summary-content">
                        <span class="filter-summary-icon">🎯</span>
                        <span class="filter-summary-text">No filters active</span>
                        <button class="filter-summary-edit" onclick="document.querySelector('.career-filters-bar').scrollIntoView({ behavior: 'smooth', block: 'start' })">
                            Add filters ↑
                        </button>
                    </div>
                `;
            }

            return `
                <div class="filter-summary-content">
                    <span class="filter-summary-icon">🔍</span>
                    <span class="filter-summary-text">
                        ${activeFilters.map(f => `<span class="filter-tag">${f.label}: ${f.value}</span>`).join('')}
                    </span>
                    <button class="filter-summary-edit" onclick="document.querySelector('.career-filters-bar').scrollIntoView({ behavior: 'smooth', block: 'start' })">
                        Edit ↑
                    </button>
                </div>
            `;
        },

        // Check if a node matches current filters
        nodeMatchesFilters: function (nodeData) {
            const filters = this.careerFilters || this.getDefaultFilters();
            const careerData = nodeData.data || {};
            const meta = careerData.meta || {};

            // No filters active = all match
            const hasActiveFilters =
                filters.demand_horizon ||
                (filters.education_levels && filters.education_levels.length > 0) ||
                (filters.work_env && filters.work_env.length > 0) ||
                (filters.role_orientation && filters.role_orientation.length > 0) ||
                filters.comp_band ||
                (filters.social_impact && filters.social_impact.length > 0) ||
                filters.remote_only ||
                filters.stretch_opposites;

            if (!hasActiveFilters) return false; // Don't highlight if no filters

            let matches = 0;
            let checks = 0;

            // Check demand horizon
            if (filters.demand_horizon) {
                checks++;
                if (meta.demand_horizon === filters.demand_horizon) matches++;
            }

            // Check education
            if (filters.education_levels && filters.education_levels.length > 0) {
                checks++;
                if (meta.education && filters.education_levels.includes(meta.education)) matches++;
            }

            // Check work environment
            if (filters.work_env && filters.work_env.length > 0) {
                checks++;
                const careerEnv = meta.work_env || [];
                if (filters.work_env.some(f => careerEnv.includes(f))) matches++;
            }

            // Check remote only
            if (filters.remote_only) {
                checks++;
                const careerEnv = meta.work_env || [];
                if (careerEnv.includes('remote_friendly')) matches++;
            }

            // Check compensation band
            if (filters.comp_band) {
                checks++;
                if (meta.comp_band === filters.comp_band) matches++;
            }

            // Check social impact
            if (filters.social_impact && filters.social_impact.length > 0) {
                checks++;
                const careerImpact = meta.social_impact || [];
                if (filters.social_impact.some(f => careerImpact.includes(f))) matches++;
            }

            // Consider it a match if it satisfies at least 70% of active filters
            return checks > 0 && (matches / checks) >= 0.7;
        },

        // Show visual feedback after node interaction
        showNodeFeedback: function (nodeId, message, type = 'info') {
            console.log('showNodeFeedback called:', { nodeId, message, type });

            // Remove any existing feedback
            $('#mindmap-feedback').remove();

            // Create feedback element
            const feedbackClass = `mindmap-feedback mindmap-feedback-${type}`;
            const icon = type === 'loading' ? '◐' : type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';

            const feedback = $(`
                <div id="mindmap-feedback" class="${feedbackClass}">
                    <span class="feedback-icon">${icon}</span>
                    <span class="feedback-message">${message}</span>
                </div>
            `);

            // Find container - try canvas parent, then mindmap container, then body
            let container = $('.career-mindmap-container');
            if (!container.length) {
                container = $('body');
            }

            container.append(feedback);
            console.log('Feedback appended to:', container[0]);

            // Auto-hide after 3 seconds (except for loading)
            if (type !== 'loading') {
                setTimeout(() => {
                    feedback.fadeOut(300, function () { $(this).remove(); });
                }, 3000);
            }
        },

        // Track OpenAI usage (admin only)
        trackUsage: function (usage) {
            if (!usage || !this.mindMapState) return;

            this.mindMapState.totalUsage.total_tokens += usage.total_tokens || 0;
            this.mindMapState.totalUsage.total_cost_usd += usage.estimated_cost_usd || 0;
            this.mindMapState.totalUsage.api_calls += 1;

            console.log('OpenAI Usage:', usage);
        },

        // Update usage display for admins
        updateUsageDisplay: function () {
            // Only show for admins
            if (!labMode.isAdmin) return;

            const usage = this.mindMapState.totalUsage;
            if (!usage || usage.api_calls === 0) return;

            // Check if display already exists
            let display = $('#mindmap-usage-display');

            if (!display.length) {
                // Create display element
                display = $(`
                    <div id="mindmap-usage-display" class="mindmap-usage-display">
                        <div class="usage-label">🔧 Admin: OpenAI Usage</div>
                        <div class="usage-stats"></div>
                    </div>
                `);
                $('.career-mindmap-container').append(display);
            }

            // Update stats
            const costFormatted = usage.total_cost_usd < 0.01
                ? `$${(usage.total_cost_usd * 100).toFixed(3)}¢`
                : `$${usage.total_cost_usd.toFixed(4)}`;

            display.find('.usage-stats').html(`
                <span class="usage-stat">${usage.api_calls} API calls</span>
                <span class="usage-stat">${usage.total_tokens.toLocaleString()} tokens</span>
                <span class="usage-stat"><strong>${costFormatted}</strong></span>
            `);
        },

        // Render a career cluster with enhanced meta fields
        renderCareerClusterEnhanced: function (title, description, careers, clusterType) {
            if (!careers || careers.length === 0) {
                return `
                    <div class="career-cluster career-cluster-${clusterType}">
                        <div class="career-cluster-header">
                            <h4>${title}</h4>
                            <p class="career-cluster-desc">${description}</p>
                        </div>
                        <div class="career-cluster-empty">
                            <p>No ${clusterType} careers generated.</p>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="career-cluster career-cluster-${clusterType}">
                    <div class="career-cluster-header">
                        <h4>${title}</h4>
                        <p class="career-cluster-desc">${description}</p>
                    </div>
                    <div class="career-cards">
                        ${careers.map((career, index) => this.renderCareerCardEnhanced(career, index, clusterType)).join('')}
                    </div>
                </div>
            `;
        },

        // Render a single career card with enhanced meta fields (V2 Design System)
        renderCareerCardEnhanced: function (career, index, clusterType) {
            const cardId = `career-card-${clusterType}-${index}`;
            const titleId = `${cardId}-title`;
            const isCompact = this.compactMode || false;

            // Build data attributes for analytics
            const miMatch = career.profile_match?.mi?.join(',') || '';
            const bartle = career.profile_match?.bartle || '';
            const growthHorizon = career.meta?.demand_horizon || '';
            const education = career.meta?.education || '';
            const isRemote = career.meta?.work_env?.includes('remote_friendly') ? 'true' : 'false';

            return `
                <article 
                    class="career-card career-card-v2 career-card-${clusterType} ${isCompact ? 'career-card-compact' : ''}" 
                    id="${cardId}" 
                    data-career-title="${this.escapeHtml(career.title)}" 
                    data-cluster-type="${clusterType}"
                    data-card-id="${cardId}"
                    data-mi-match="${miMatch}"
                    data-bartle="${bartle}"
                    data-growth-horizon="${growthHorizon}"
                    data-education="${education}"
                    data-remote="${isRemote}"
                    role="group"
                    aria-labelledby="${titleId}"
                >
                    <!-- Title -->
                    <h3 class="career-card-title" id="${titleId}">${this.escapeHtml(career.title)}</h3>
                    
                    <!-- Summary (hidden in compact mode) -->
                    ${!isCompact ? `<p class="career-card-summary">${this.escapeHtml(career.why_it_fits)}</p>` : ''}
                    
                    <!-- Divider -->
                    <div class="career-card-divider"></div>
                    
                    <!-- Profile Match Section -->
                    ${this.renderProfileMatchSection(career.profile_match, isCompact)}
                    
                    <!-- Requirements Section -->
                    ${this.renderRequirementsSection(career.meta, isCompact)}
                    
                    <!-- Work Style Tags Section -->
                    ${this.renderWorkStyleSection(career.meta, isCompact)}
                    
                    <!-- Actions -->
                    <div class="career-card-actions">
                        <div class="career-actions-row">
                            <button 
                                class="career-action-btn career-action-dismiss" 
                                data-action="not_interested"
                                aria-label="Dismiss ${this.escapeHtml(career.title)}"
                                title="Not interested"
                            >
                                ${isCompact ? '✕' : 'Not interested'}
                            </button>
                            <button 
                                class="career-action-btn career-action-save" 
                                data-action="save"
                                aria-label="Save ${this.escapeHtml(career.title)}"
                                title="Save this career"
                            >
                                ${isCompact ? '♥' : 'Save ♥'}
                            </button>
                        </div>
                        <a 
                            href="#" 
                            class="career-action-link" 
                            data-action="explain_fit"
                            aria-label="Learn why ${this.escapeHtml(career.title)} fits your profile"
                        >
                            Why this fits me
                        </a>
                    </div>
                </article>
            `;
        },

        // Helper method for HTML escaping
        escapeHtml: function (text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Profile Match Section
        renderProfileMatchSection: function (profileMatch, isCompact) {
            if (!profileMatch) return '';

            const badgeSize = isCompact ? 'badge-sm' : '';
            let html = '<div class="career-section career-profile-section">';
            html += '<h4 class="career-section-title">Profile Match</h4>';
            html += '<div class="career-badges">';

            // MI badges
            if (profileMatch.mi && profileMatch.mi.length > 0) {
                html += profileMatch.mi.map(mi =>
                    `<span class="career-badge badge-mi ${badgeSize}">${this.escapeHtml(mi)}</span>`
                ).join('');
            }

            // Bartle badge
            if (profileMatch.bartle) {
                html += `<span class="career-badge badge-bartle ${badgeSize}">${this.escapeHtml(profileMatch.bartle)}</span>`;
            }

            // CDT top (if available)
            if (profileMatch.cdt_top) {
                html += `<span class="career-badge badge-cdt ${badgeSize}">${this.escapeHtml(profileMatch.cdt_top.replace(/_/g, ' '))}</span>`;
            }

            html += '</div></div>';
            return html;
        },

        // Requirements Section
        renderRequirementsSection: function (meta, isCompact) {
            if (!meta || !meta.education) return '';

            const badgeSize = isCompact ? 'badge-sm' : '';
            let html = '<div class="career-section career-requirements-section">';
            html += '<h4 class="career-section-title">Requirements</h4>';
            html += '<div class="career-badges">';

            // Education with icon
            html += `<span class="career-badge badge-education ${badgeSize}">
                <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                ${this.formatMetaLabel('education', meta.education)}
            </span>`;

            html += '</div></div>';
            return html;
        },

        // Work Style Tags Section
        renderWorkStyleSection: function (meta, isCompact) {
            if (!meta) return '';

            const badgeSize = isCompact ? 'badge-sm' : '';
            let html = '<div class="career-section career-workstyle-section">';
            html += '<h4 class="career-section-title">Work Style</h4>';
            html += '<div class="career-badges">';

            // Demand horizon with icon
            if (meta.demand_horizon) {
                html += `<span class="career-badge badge-demand ${badgeSize}">
                    <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    ${this.formatMetaLabel('demand_horizon', meta.demand_horizon)}
                </span>`;
            }

            // Pay band with icon
            if (meta.comp_band) {
                html += `<span class="career-badge badge-pay ${badgeSize}">
                    <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    ${this.formatMetaLabel('comp_band', meta.comp_band)}
                </span>`;
            }

            // Work environment (show first, + more)
            if (meta.work_env && meta.work_env.length > 0) {
                const envIcon = meta.work_env.includes('remote_friendly') ?
                    '<svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                    '';

                html += `<span class="career-badge badge-remote ${badgeSize}">
                    ${envIcon}
                    ${this.formatMetaLabel('work_env', meta.work_env[0])}
                </span>`;

                if (meta.work_env.length > 1) {
                    html += `<span class="career-badge badge-more ${badgeSize}">+${meta.work_env.length - 1}</span>`;
                }
            }

            // Social impact
            if (meta.social_impact && meta.social_impact.length > 0) {
                html += `<span class="career-badge badge-impact ${badgeSize}">${this.formatMetaLabel('social_impact', meta.social_impact[0])}</span>`;
                if (meta.social_impact.length > 1) {
                    html += `<span class="career-badge badge-more ${badgeSize}">+${meta.social_impact.length - 1}</span>`;
                }
            }

            html += '</div></div>';
            return html;
        },

        // Toggle compact mode
        toggleCompactMode: function (enabled) {
            this.compactMode = enabled;
            localStorage.setItem('career_compact_mode', enabled);
            // Re-render current results
            const resultsContainer = document.getElementById('career-explorer-results');
            if (resultsContainer && resultsContainer.style.display !== 'none') {
                // Store current data and re-render
                if (this.currentCareerData) {
                    this.displayCareerSuggestions(
                        this.currentCareerData,
                        this.currentCareerInterest,
                        this.isDiceRoll
                    );
                }
            }
        },

        // Render skeleton loader
        renderSkeletonCards: function (count = 6) {
            return Array(count).fill(0).map((_, i) => `
                <div class="career-card career-card-v2 career-card-skeleton" aria-hidden="true">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-summary"></div>
                    <div class="skeleton-summary" style="width: 85%;"></div>
                    <div class="skeleton-divider"></div>
                    <div class="skeleton-badges">
                        <div class="skeleton-badge"></div>
                        <div class="skeleton-badge"></div>
                        <div class="skeleton-badge"></div>
                    </div>
                    <div class="skeleton-badges">
                        <div class="skeleton-badge"></div>
                    </div>
                    <div class="skeleton-badges">
                        <div class="skeleton-badge"></div>
                        <div class="skeleton-badge"></div>
                    </div>
                    <div class="skeleton-actions">
                        <div class="skeleton-button"></div>
                        <div class="skeleton-button"></div>
                    </div>
                </div>
            `).join('');
        },

        // Render career meta chips (new)
        renderCareerMeta: function (meta) {
            if (!meta) return '';

            let html = '<div class="career-meta-chips">';

            if (meta.demand_horizon) {
                html += `<span class="meta-chip meta-chip-demand">${this.formatMetaLabel('demand_horizon', meta.demand_horizon)}</span>`;
            }

            if (meta.education) {
                html += `<span class="meta-chip meta-chip-education">${this.formatMetaLabel('education', meta.education)}</span>`;
            }

            if (meta.comp_band) {
                html += `<span class="meta-chip meta-chip-comp">${this.formatMetaLabel('comp_band', meta.comp_band)}</span>`;
            }

            if (meta.work_env && meta.work_env.length > 0) {
                const envLabels = meta.work_env.slice(0, 2).map(env => this.formatMetaLabel('work_env', env));
                html += envLabels.map(label => `<span class="meta-chip meta-chip-env">${label}</span>`).join('');
                if (meta.work_env.length > 2) {
                    html += `<span class="meta-chip meta-chip-more">+${meta.work_env.length - 2}</span>`;
                }
            }

            if (meta.social_impact && meta.social_impact.length > 0) {
                html += `<span class="meta-chip meta-chip-impact">${this.formatMetaLabel('social_impact', meta.social_impact[0])}</span>`;
                if (meta.social_impact.length > 1) {
                    html += `<span class="meta-chip meta-chip-more">+${meta.social_impact.length - 1}</span>`;
                }
            }

            html += '</div>';
            return html;
        },

        // Format meta label for display
        formatMetaLabel: function (category, value) {
            const labels = {
                demand_horizon: {
                    'trending_now': 'Trending now',
                    'high_growth_5y': 'High growth 5y',
                    'future_proof_10y': 'Future-proof',
                    'stable_low_vol': 'Stable',
                    'automation_resistant': 'Automation-resistant'
                },
                education: {
                    'no_degree': 'No degree',
                    'certificate_bootcamp': 'Certificate',
                    'bachelor': 'Bachelor',
                    'advanced': 'Advanced'
                },
                comp_band: {
                    'lower': 'Lower pay',
                    'middle': 'Middle pay',
                    'upper': 'Upper pay',
                    'high_responsibility': 'High responsibility'
                },
                work_env: {
                    'remote_friendly': 'Remote',
                    'hybrid': 'Hybrid',
                    'outdoor': 'Outdoor',
                    'hands_on': 'Hands-on',
                    'solo': 'Solo',
                    'collaborative': 'Collaborative',
                    'client_facing': 'Client-facing',
                    'structured': 'Structured',
                    'flexible': 'Flexible'
                },
                social_impact: {
                    'high_social': 'High social impact',
                    'environmental': 'Environmental',
                    'community': 'Community',
                    'mission_driven': 'Mission-driven'
                }
            };

            return (labels[category] && labels[category][value]) || value.replace(/_/g, ' ');
        },

        // Generate Career Map (legacy endpoint - kept for backward compatibility)
        generateCareerMap: function () {
            const careerInput = document.getElementById('career-interest-input');
            const careerInterest = careerInput.value.trim();

            if (!careerInterest) {
                alert('Please enter a career or field to explore');
                careerInput.focus();
                return;
            }

            // Show loading overlay
            if (window.AILoadingOverlay) {
                window.AILoadingOverlay.show({
                    subtitle: 'AI is exploring career pathways for you…',
                    messages: [
                        `Analyzing careers related to ${careerInterest}…`,
                        'Matching with your MI strengths and CDT profile…',
                        'Finding adjacent career paths with easy transitions…',
                        'Discovering parallel careers in different industries…',
                        'Identifying wildcard options based on your unique profile…'
                    ]
                });
            }

            // Get prompt level and templates from admin settings
            const promptLevel = localStorage.getItem('career_prompt_level') || 'minimal';
            const promptTemplates = {
                minimal: localStorage.getItem('career_prompt_minimal') || '',
                medium: localStorage.getItem('career_prompt_medium') || '',
                maximum: localStorage.getItem('career_prompt_maximum') || ''
            };

            // Call AJAX endpoint
            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_generate_career_map',
                    nonce: labMode.nonce,
                    career_interest: careerInterest,
                    prompt_level: promptLevel,
                    prompt_templates: JSON.stringify(promptTemplates)
                },
                success: (response) => {
                    // Hide loading overlay
                    if (window.AILoadingOverlay) {
                        window.AILoadingOverlay.hide();
                    }

                    if (response.success && response.data.career_map) {
                        this.displayCareerMap(response.data.career_map, careerInterest);
                    } else {
                        alert('Error: ' + (response.data || 'Failed to generate career map'));
                    }
                },
                error: (xhr, status, error) => {
                    // Hide loading overlay
                    if (window.AILoadingOverlay) {
                        window.AILoadingOverlay.hide();
                    }

                    console.error('Career map generation failed:', { xhr, status, error });
                    alert('Network error. Please check your connection and try again.');
                }
            });
        },

        // Show Career Explorer directly (for quick testing)
        showCareerExplorerDirectly: function () {
            console.log('showCareerExplorerDirectly called');

            // Create mock experiments array to satisfy tab rendering
            this.experiments = [];

            // Render experiments view which will show Career Explorer tab
            const html = `
                <div class="lab-experiments">
                    <h2>Career Explorer (Test Mode)</h2>
                    <p class="lab-subtitle">Quick access for testing</p>
                    
                    <div class="experiments-tab-content">
                        ${this.renderCareerExplorerTab()}
                    </div>
                    
                    <div class="lab-footer-actions" style="margin-top: 2rem;">
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.loadLandingView()">← Back to Start</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'career-explorer';
            console.log('Career Explorer rendered in test mode');
        },

        // Display Career Map results
        displayCareerMap: function (careerMap, careerInterest) {
            // Store for feedback actions
            this.currentCareerInterest = careerInterest;

            const resultsContainer = document.getElementById('career-explorer-results');

            const html = `
                <div class="career-map-results">
                    <div class="career-map-header">
                        <h3>Career Mind Map for: ${careerMap.central_career || careerInterest}</h3>
                        <p>Careers aligned with your MI, CDT, Bartle Type, and Johari profile</p>
                    </div>
                    
                    <div class="career-clusters">
                        ${this.renderCareerCluster('Adjacent Careers', 'Very similar to your chosen career; easy transitions', careerMap.adjacent, 'adjacent')}
                        ${this.renderCareerCluster('Parallel Careers', 'Similar strengths, different industries', careerMap.parallel, 'parallel')}
                        ${this.renderCareerCluster('Wildcard Careers', 'Unexpected options based on your unique profile', careerMap.wildcard, 'wildcard')}
                    </div>
                    
                    <div class="career-map-actions">
                        <button class="lab-btn lab-btn-secondary" onclick="document.getElementById('career-interest-input').value=''; document.getElementById('career-interest-input').focus(); document.getElementById('career-explorer-results').style.display='none';">
                            Explore Another Career
                        </button>
                    </div>
                </div>
            `;

            resultsContainer.innerHTML = html;
            resultsContainer.style.display = 'block';

            // Scroll to results
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        // Render a career cluster (Adjacent, Parallel, or Wildcard)
        renderCareerCluster: function (title, description, careers, clusterType) {
            if (!careers || careers.length === 0) {
                return `
                    <div class="career-cluster career-cluster-${clusterType}">
                        <div class="career-cluster-header">
                            <h4>${title}</h4>
                            <p class="career-cluster-desc">${description}</p>
                        </div>
                        <div class="career-cluster-empty">
                            <p>No ${clusterType} careers generated.</p>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="career-cluster career-cluster-${clusterType}">
                    <div class="career-cluster-header">
                        <h4>${title}</h4>
                        <p class="career-cluster-desc">${description}</p>
                    </div>
                    <div class="career-cards">
                        ${careers.map((career, index) => this.renderCareerCard(career, index, clusterType)).join('')}
                    </div>
                </div>
            `;
        },

        // Render a single career card
        renderCareerCard: function (career, index, clusterType) {
            const cardId = `career-card-${clusterType}-${index}`;
            return `
                <div class="career-card career-card-${clusterType}" id="${cardId}" data-career-title="${career.title}" data-cluster-type="${clusterType}">
                    <div class="career-card-header">
                        <h5 class="career-title">${career.title}</h5>
                    </div>
                    <div class="career-card-body">
                        <p class="career-why-fits">${career.why_it_fits}</p>
                        ${career.profile_match ? this.renderProfileMatch(career.profile_match) : ''}
                    </div>
                    <div class="career-action-bar">
                        <button class="career-action-btn" data-action="too_similar" title="Show me something less similar">
                            Too similar
                        </button>
                        <button class="career-action-btn" data-action="too_different" title="Show me something closer">
                            Too different
                        </button>
                        <button class="career-action-btn" data-action="not_interested" title="Show me something else">
                            Not interested
                        </button>
                        <button class="career-action-btn career-action-save" data-action="save" title="Save this career">
                            Save ♥
                        </button>
                        <button class="career-action-btn career-action-explain" data-action="explain_fit" title="Why does this fit me?">
                            Why this fits me
                        </button>
                    </div>
                </div>
            `;
        },

        // Render profile match chips
        renderProfileMatch: function (profileMatch) {
            let html = '<div class="career-profile-match">';

            if (profileMatch.mi && profileMatch.mi.length > 0) {
                html += `<div class="profile-match-section">
                    <span class="profile-match-label">MI:</span>
                    ${profileMatch.mi.map(mi => `<span class="profile-chip profile-chip-mi">${mi}</span>`).join('')}
                </div>`;
            }

            if (profileMatch.cdt) {
                html += `<div class="profile-match-section">
                    <span class="profile-match-label">CDT:</span>
                    <span class="profile-chip profile-chip-cdt">${profileMatch.cdt}</span>
                </div>`;
            }

            if (profileMatch.bartle) {
                html += `<div class="profile-match-section">
                    <span class="profile-match-label">Bartle:</span>
                    <span class="profile-chip profile-chip-bartle">${profileMatch.bartle}</span>
                </div>`;
            }

            if (profileMatch.johari && profileMatch.johari.length > 0) {
                html += `<div class="profile-match-section">
                    <span class="profile-match-label">Johari:</span>
                    ${profileMatch.johari.map(adj => `<span class="profile-chip profile-chip-johari">${adj}</span>`).join('')}
                </div>`;
            }

            html += '</div>';
            return html;
        },

        // Handle career feedback button clicks
        handleCareerFeedback: function (e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            const action = button.data('action');
            const card = button.closest('.career-card');
            const careerTitle = card.data('career-title');
            const clusterType = card.data('cluster-type');
            const cardId = card.data('card-id');
            const miMatch = card.data('mi-match');
            const bartle = card.data('bartle');
            const growthHorizon = card.data('growth-horizon');

            // Get central career from input or stored value
            const centralCareer = this.currentCareerInterest || document.getElementById('career-interest-input')?.value?.trim() || '';

            console.log('Career feedback:', { action, careerTitle, clusterType, centralCareer });

            // Handle different actions
            if (action === 'explain_fit') {
                // Analytics: career_explain_open
                console.log('career_explain_open', { card_id: cardId, career_title: careerTitle });
                this.showCareerExplanation(card, careerTitle);
            } else if (action === 'save') {
                // Analytics: career_save
                console.log('career_save', { card_id: cardId, mi_match: miMatch, bartle: bartle, growth_horizon: growthHorizon });
                this.saveCareer(card, careerTitle, clusterType);
            } else if (action === 'not_interested') {
                // Analytics: career_dismiss
                console.log('career_dismiss', { card_id: cardId, cluster_type: clusterType });
                this.requestCareerReplacement(card, action, careerTitle, clusterType, centralCareer);
            } else {
                // too_similar, too_different - get replacement
                this.requestCareerReplacement(card, action, careerTitle, clusterType, centralCareer);
            }
        },

        // Request a career replacement from AI
        requestCareerReplacement: function (card, action, careerRejected, clusterType, centralCareer) {
            // Disable all buttons in this card during request
            card.find('.career-action-btn').prop('disabled', true);

            // Get current career data from card
            const careerData = {
                title: careerRejected,
                why_it_fits: card.find('.career-why-fits').text(),
                profile_match: {} // Would need to parse from card if needed
            };

            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_career_feedback',
                    nonce: labMode.nonce,
                    feedback_action: action,
                    career_rejected: careerRejected,
                    central_career: centralCareer,
                    distance_group: clusterType,
                    rejected_career_data: JSON.stringify(careerData)
                },
                success: (response) => {
                    if (response.success && response.data.replacement) {
                        this.replaceCareerCard(card, response.data.replacement, clusterType);
                    } else {
                        alert('Error: ' + (response.data || 'Failed to get replacement'));
                        card.find('.career-action-btn').prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Career feedback failed:', { xhr, status, error });
                    alert('Network error. Please try again.');
                    card.find('.career-action-btn').prop('disabled', false);
                }
            });
        },

        // Replace a career card with animation
        replaceCareerCard: function (oldCard, newCareer, clusterType) {
            // Fade out old card
            oldCard.css('opacity', '1').animate({ opacity: 0 }, 300, function () {
                // Get the index from the card ID
                const cardId = oldCard.attr('id');
                const index = cardId ? cardId.split('-').pop() : '0';

                // Create new card HTML
                const newCardHtml = LabModeApp.renderCareerCard(newCareer, index, clusterType);

                // Replace content
                oldCard.replaceWith(newCardHtml);

                // Fade in new card
                const newCard = $(`#career-card-${clusterType}-${index}`);
                newCard.css('opacity', '0').animate({ opacity: 1 }, 300);
            });
        },

        // Show explanation popover
        showCareerExplanation: function (card, careerTitle) {
            console.log('showCareerExplanation called for career');

            // Check if explanation already exists and toggle it
            const existingExplanation = card.find('.career-explanation');
            if (existingExplanation.length > 0) {
                console.log('Found existing explanation, toggling visibility');
                existingExplanation.slideToggle(200);
                const explainBtn = card.find('[data-action="explain_fit"]');
                if (existingExplanation.is(':visible')) {
                    explainBtn.text('Hide explanation');
                } else {
                    explainBtn.text('Why this fits me');
                }
                return;
            }

            // Check if we're already loading this explanation
            if (card.data('loading-explanation')) {
                console.log('Already loading explanation for career');
                return;
            }
            card.data('loading-explanation', true);

            // Initialize cache if not exists
            if (!this.careerExplanationCache) {
                this.careerExplanationCache = {};
            }

            // Check if we already have a cached explanation for this career
            if (this.careerExplanationCache[careerTitle]) {
                console.log('Using cached explanation for career');
                const sections = this.careerExplanationCache[careerTitle];

                // Validate that we have the new format (object with profile_fit and typical_day)
                if (typeof sections === 'object' && sections.profile_fit && sections.typical_day) {
                    // Create explanation elements using jQuery to avoid quote issues
                    const $explanation = $('<div>').addClass('career-explanation');

                    // Profile fit section
                    const $profileSection = $('<div>').addClass('career-explanation-section');
                    $profileSection.append(
                        $('<div>').addClass('career-explanation-header').text('Why this fits your profile')
                    );
                    $profileSection.append(
                        $('<div>').addClass('career-explanation-content').text(sections.profile_fit)
                    );
                    $explanation.append($profileSection);

                    // Typical day section
                    const $daySection = $('<div>').addClass('career-explanation-section');
                    $daySection.append(
                        $('<div>').addClass('career-explanation-header').text('A typical day')
                    );
                    $daySection.append(
                        $('<div>').addClass('career-explanation-content').text(sections.typical_day)
                    );
                    $explanation.append($daySection);

                    // V2 cards use article structure, append before actions
                    const cardActions = card.find('.career-card-actions');
                    if (cardActions.length > 0) {
                        cardActions.before($explanation);
                    } else {
                        // Fallback for legacy cards
                        card.find('.career-card-body').append($explanation);
                    }
                    $explanation.hide().slideDown(200);

                    const explainBtn = card.find('[data-action="explain_fit"]');
                    explainBtn.text('Hide explanation');
                    card.data('loading-explanation', false);
                    return;
                } else {
                    // Old format cached - clear it and fetch fresh
                    console.log('Old format detected, clearing cache for career');
                    delete this.careerExplanationCache[careerTitle];
                }
            }

            // Disable button during request
            const explainBtn = card.find('[data-action="explain_fit"]');
            explainBtn.prop('disabled', true).text('Loading...');

            const centralCareer = this.currentCareerInterest || document.getElementById('career-interest-input')?.value?.trim() || '';
            const clusterType = card.data('cluster-type');

            console.log('Making AJAX request for career explanation:', careerTitle || 'unknown');

            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_career_feedback',
                    nonce: labMode.nonce,
                    feedback_action: 'explain_fit',
                    career_rejected: careerTitle,
                    central_career: centralCareer,
                    distance_group: clusterType
                },
                success: (response) => {
                    console.log('Career explanation response received');

                    // Clear loading flag
                    card.data('loading-explanation', false);

                    if (response.success && response.data.explanation) {
                        console.log('Explanation data validated');

                        const sections = response.data.explanation;

                        // Validate we have the correct format
                        if (typeof sections !== 'object' || !sections.profile_fit || !sections.typical_day) {
                            console.error('Invalid explanation format received:', sections);
                            alert('Error: Received invalid explanation format');
                            explainBtn.text('Why this fits me');
                            return;
                        }

                        // Cache the explanation (now it's an object with profile_fit and typical_day)
                        if (!LabModeApp.careerExplanationCache) {
                            LabModeApp.careerExplanationCache = {};
                        }
                        LabModeApp.careerExplanationCache[careerTitle] = response.data.explanation;
                        console.log('Cached explanation for career');

                        // Create explanation elements using jQuery to avoid quote issues
                        const $explanation = $('<div>').addClass('career-explanation');

                        // Profile fit section
                        const $profileSection = $('<div>').addClass('career-explanation-section');
                        $profileSection.append(
                            $('<div>').addClass('career-explanation-header').text('Why this fits your profile')
                        );
                        $profileSection.append(
                            $('<div>').addClass('career-explanation-content').text(sections.profile_fit)
                        );
                        $explanation.append($profileSection);

                        // Typical day section
                        const $daySection = $('<div>').addClass('career-explanation-section');
                        $daySection.append(
                            $('<div>').addClass('career-explanation-header').text('A typical day')
                        );
                        $daySection.append(
                            $('<div>').addClass('career-explanation-content').text(sections.typical_day)
                        );
                        $explanation.append($daySection);

                        console.log('Appending explanation to card');
                        // V2 cards use article structure, append before actions
                        const cardActions = card.find('.career-card-actions');
                        if (cardActions.length > 0) {
                            console.log('Found V2 card structure, appending before actions');
                            cardActions.before($explanation);
                        } else {
                            // Fallback for legacy cards
                            console.log('Using legacy card structure');
                            const cardBody = card.find('.career-card-body');
                            console.log('Card body found:', cardBody.length);
                            cardBody.append($explanation);
                        }

                        console.log('Explanation element found after append:', card.find('.career-explanation').length);
                        $explanation.hide().slideDown(200);
                        console.log('Explanation should now be visible');

                        // Update button text
                        explainBtn.prop('disabled', false).text('Hide explanation');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to get explanation'));
                        explainBtn.text('Why this fits me');
                    }
                },
                error: (xhr, status, error) => {
                    // Clear loading flag on error
                    card.data('loading-explanation', false);
                    explainBtn.prop('disabled', false).text('Why this fits me');
                    console.error('Career explanation failed:', { xhr, status, error });
                    alert('Network error. Please try again.');
                }
            });
        },

        // Save a career to favorites
        saveCareer: function (card, careerTitle, clusterType) {
            const saveBtn = card.find('[data-action="save"]');
            const centralCareer = this.currentCareerInterest || document.getElementById('career-interest-input')?.value?.trim() || '';

            // Get career data
            const careerData = {
                title: careerTitle,
                why_it_fits: card.find('.career-why-fits').text(),
                distance_group: clusterType,
                central_career: centralCareer
            };

            saveBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: labMode.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mc_lab_career_feedback',
                    nonce: labMode.nonce,
                    feedback_action: 'save',
                    career_rejected: careerTitle,
                    central_career: centralCareer,
                    distance_group: clusterType,
                    career_data: JSON.stringify(careerData)
                },
                success: (response) => {
                    if (response.success && response.data.saved) {
                        saveBtn.removeClass('career-action-save').addClass('career-action-saved')
                            .text('Saved ✓').prop('disabled', true);
                        card.addClass('career-card-saved');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to save'));
                        saveBtn.prop('disabled', false).text('Save ♥');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Career save failed:', { xhr, status, error });
                    alert('Network error. Please try again.');
                    saveBtn.prop('disabled', false).text('Save ♥');
                }
            });
        },

        // Helper function to format connection strings
        formatConnections: function (connections) {
            if (connections.length === 1) {
                return connections[0] + ".";
            } else if (connections.length === 2) {
                return connections[0] + " and " + connections[1] + ".";
            } else {
                const lastConnection = connections.pop();
                return connections.join(", ") + ", and " + lastConnection + ".";
            }
        },

        // Helper function to find role model influences in experiment content
        findRoleModelInfluences: function (experiment, roleModels) {
            const searchText = `${experiment.title || ''} ${experiment.rationale || ''} ${(experiment.steps || []).join(' ')}`.toLowerCase();

            const foundModels = [];
            roleModels.forEach(model => {
                if (searchText.includes(model.toLowerCase())) {
                    foundModels.push(model);
                }
            });

            return foundModels;
        },

        // Map raw slider constraints to normalized targets used for calibration (0..4)
        mapConstraintsToTargets: function () {
            const c = this.qualifiers?.curiosity?.constraints || {};
            // Budget slider may be 0..200; map to 0..4
            const cost = Math.max(0, Math.min(4, Math.round((Number(c.budget ?? 50) / 200) * 4)));
            // Time per week 1..10; map to 0..4
            const tpw = Number(c.timePerWeekHours ?? 3);
            const time = tpw <= 2 ? 0 : tpw <= 3 ? 1 : tpw <= 5 ? 2 : tpw <= 7 ? 3 : 4;
            // Risk 0..100 — use as proxy for energy tolerance 0..4
            const energy = Math.max(0, Math.min(4, Math.round((Number(c.risk ?? 50) / 100) * 4)));
            // Variety unavailable directly; approximate from energy for now
            const variety = energy;
            return { cost, time, energy, variety };
        },

        // Adjust generated experiments client-side to visibly reflect constraints
        applyConstraintsToExperiments: function (experiments) {
            const targets = this.mapConstraintsToTargets();
            const c = this.qualifiers?.curiosity?.constraints || {};
            const tpw = Number(c.timePerWeekHours ?? 3);
            const maxBudget = Number(c.budget ?? 50);

            console.log('Applying constraints - targets:', targets, 'constraints:', c);

            const calibrated = (experiments || []).map(exp => {
                const e = { ...exp };

                // Initialize effort object if missing (common for mock/fallback experiments)
                if (!e.effort) {
                    e.effort = {
                        timeHours: Math.floor(Math.random() * 3) + 1, // Random 1-3 hours
                        budgetUSD: Math.floor(Math.random() * 50)     // Random 0-49 dollars
                    };
                }

                let timeH = Number(e.effort.timeHours ?? 1);
                let budget = Number(e.effort.budgetUSD ?? 0);
                const originalTime = timeH;
                const originalBudget = budget;

                // If user time is low, cap time; if high, allow more time
                if (targets.time <= 1) {
                    timeH = Math.min(timeH, Math.max(1, Math.ceil(tpw / 2))); // keep short
                } else if (targets.time >= 3) {
                    timeH = Math.max(timeH, Math.min(10, Math.ceil(tpw * 0.8))); // deepen more aggressively
                }

                // Budget calibration: if user allows high spend, bump minimal budgets; else clamp
                if (targets.cost <= 1) {
                    budget = Math.min(budget, Math.max(0, Math.floor(maxBudget * 0.2)));
                } else if (targets.cost >= 3) {
                    const floor = Math.max(50, Math.floor(maxBudget * 0.4));
                    budget = Math.max(budget, floor);
                }

                // Always annotate rationale to show calibration happened
                const notes = [];
                if (timeH !== originalTime) {
                    notes.push(`time adjusted from ${originalTime}h to ${timeH}h based on your ${targets.time <= 1 ? 'limited' : 'generous'} availability`);
                }
                if (budget !== originalBudget) {
                    notes.push(`budget adjusted from $${originalBudget} to $${budget} for ${targets.cost >= 3 ? 'higher investment' : 'budget-conscious'} approach`);
                }

                // Add a calibration note even if no changes (to show system is working)
                if (notes.length === 0) {
                    notes.push(`calibrated for ${timeH}h/${budget} budget based on your constraints`);
                }

                if (notes.length) {
                    e._calibrationNotes = `Calibrated: ${notes.join('; ')}`;
                }

                e.effort.timeHours = timeH;
                e.effort.budgetUSD = budget;
                e._calibrated = true;
                return e;
            });

            // Sort by closeness to targets (time/budget proxy)
            const dist = (e) => {
                const t = Number(e.effort?.timeHours ?? 0);
                const b = Number(e.effort?.budgetUSD ?? 0);
                // Map to 0..4 to compare
                const tScaled = t <= 2 ? 0 : t <= 3 ? 1 : t <= 5 ? 2 : t <= 7 ? 3 : 4;
                const bScaled = Math.max(0, Math.min(4, Math.round((b / Math.max(1, maxBudget)) * 4)));
                return Math.abs(tScaled - targets.time) + Math.abs(bScaled - targets.cost);
            };
            calibrated.sort((a, b) => dist(a) - dist(b));

            console.log('Calibration complete - first experiment:', calibrated[0]);
            return calibrated;
        },

        // Regenerate a variant using AI
        regenerateAiVariant: function (e) {
            e.preventDefault();
            const experimentIndex = parseInt($(e.target).data('experiment-id'));
            const experiment = this.experiments[experimentIndex];

            if (!experiment) return;

            this.showLoading('Generating AI-powered variant...');

            // Create a custom prompt for variant generation
            const variantPrompt = this.buildVariantPrompt(experiment);

            // Debug logging
            console.log('AI Variant Generation Debug:');
            console.log('- Experiment:', experiment.title || 'No title');
            console.log('- Profile data available:', !!this.profileData);
            console.log('- Qualifiers available:', !!this.qualifiers);
            console.log('- Variant prompt system length:', variantPrompt.system?.length || 0);
            console.log('- Variant prompt user length:', variantPrompt.user?.length || 0);
            console.log('- Full variant prompt:', variantPrompt);

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_generate_ai_variant',
                    nonce: labMode.nonce,
                    original_experiment: JSON.stringify(experiment),
                    prompt_data: JSON.stringify(variantPrompt)
                },
                success: (response) => {
                    console.log('AI Variant Response:', response);

                    if (response.success && response.data.variant) {
                        // Replace the experiment with the AI-generated variant
                        this.experiments[experimentIndex] = {
                            ...response.data.variant,
                            _aiGenerated: true,
                            _variantOf: experiment.title
                        };

                        // Re-render experiments
                        this.showExperiments();

                        // Scroll to the updated experiment
                        setTimeout(() => {
                            $(`.experiment-card[data-archetype="${response.data.variant.archetype}"]`).eq(experimentIndex)
                                .get(0)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 100);
                    } else {
                        console.error('AI variant generation failed:', response);
                        this.showError(response.data || 'AI variant generation failed. Please try again.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AI variant AJAX error:', { xhr, status, error });
                    this.showError('Network error while generating AI variant. Please check your connection and try again.');
                }
            });
        },

        // Build specialized prompt for evolution based on user's specific feedback
        buildEvolutionPrompt: function (originalExperiment, reflection) {
            // Get profile data
            let topMI = [];
            let curiosities = [];
            let roleModels = [];
            let constraints = {};

            if (this.profileData?.mi_results) {
                topMI = this.profileData.mi_results.slice(0, 3);
            }

            if (this.qualifiers?.curiosity) {
                curiosities = this.qualifiers.curiosity.curiosities || [];
                roleModels = this.qualifiers.curiosity.roleModels || [];
                constraints = this.qualifiers.curiosity.constraints || {};
            }

            // Create meaningful fallbacks if no profile data
            if (topMI.length === 0) {
                topMI = [{ label: 'Creative Intelligence' }, { label: 'Problem-Solving' }, { label: 'Learning' }];
            }
            if (curiosities.length === 0) {
                curiosities = ['personal growth', 'skill development', 'creative expression'];
            }
            if (roleModels.length === 0) {
                roleModels = ['innovative thinkers', 'skilled practitioners', 'growth-minded individuals'];
            }

            const timePerWeek = constraints.timePerWeekHours || 3;
            const budget = constraints.budget || 50;
            const risk = constraints.risk || 50;

            // Build detailed evolution context
            const evolutionContext = [];

            if (reflection.evolve_notes) {
                evolutionContext.push(`SPECIFIC USER REQUEST: "${reflection.evolve_notes}"`);
            }

            if (reflection.difficulty) {
                if (reflection.difficulty >= 4) {
                    evolutionContext.push('User found the original experiment too challenging - make it more accessible');
                } else if (reflection.difficulty <= 2) {
                    evolutionContext.push('User found the original experiment too easy - increase the challenge level');
                }
            }

            if (reflection.fit <= 2) {
                evolutionContext.push('User felt poor fit with original - improve personalization and relevance');
            }

            if (reflection.learning <= 2) {
                evolutionContext.push('User felt low learning value - enhance practical learning outcomes');
            }

            const profileSummary = [
                `Top strengths: ${topMI.map(mi => mi.label).join(', ')}`,
                `Interests: ${curiosities.join(', ')}`,
                `Inspiration: ${roleModels.join(', ')}`,
                `Constraints: ${timePerWeek}h/week, $${budget} budget, ${risk}/100 risk tolerance`
            ].join('\n- ');

            const evolutionPrompt = `You are evolving an experiment based on specific user feedback. The user tried the original experiment and wants it evolved with their specific changes.

IMPORTANT: Focus heavily on the user's specific evolution request. This is not a general variant - it's a targeted improvement based on their experience.

Return only valid JSON with the same structure as the original experiment.`;

            return {
                system: evolutionPrompt,
                user: `Original experiment: ${JSON.stringify(originalExperiment)}\n\nUser profile:\n- ${profileSummary}\n\nEVOLUTION FEEDBACK:\n${evolutionContext.join('\n')}\n\nCreate an evolved version that specifically addresses the user's feedback, especially their specific request: "${reflection.evolve_notes || 'General improvements requested'}". Keep the same archetype but modify the approach, steps, and details to match their request.`
            };
        },

        // Build AI prompt for variant generation with reflection feedback
        buildVariantPrompt: function (originalExperiment) {
            // Get profile data - use cached data if available, otherwise create fallback
            let topMI = [];
            let curiosities = [];
            let roleModels = [];
            let constraints = {};

            // Try to use existing profile data
            if (this.profileData?.mi_results) {
                topMI = this.profileData.mi_results.slice(0, 3);
            }

            if (this.qualifiers?.curiosity) {
                curiosities = this.qualifiers.curiosity.curiosities || [];
                roleModels = this.qualifiers.curiosity.roleModels || [];
                constraints = this.qualifiers.curiosity.constraints || {};
            }

            // Create meaningful fallbacks if no profile data is available
            if (topMI.length === 0) {
                topMI = [
                    { label: 'Creative Intelligence' },
                    { label: 'Problem-Solving' },
                    { label: 'Learning' }
                ];
            }

            if (curiosities.length === 0) {
                curiosities = ['personal growth', 'skill development', 'creative expression'];
            }

            if (roleModels.length === 0) {
                roleModels = ['innovative thinkers', 'skilled practitioners', 'growth-minded individuals'];
            }

            // Set reasonable constraint defaults
            const timePerWeek = constraints.timePerWeekHours || 3;
            const budget = constraints.budget || 50;
            const risk = constraints.risk || 50;

            // Try to get recent reflection feedback for context
            let reflectionContext = '';
            if (this.recentFeedback && this.recentFeedback.length > 0) {
                const feedback = this.recentFeedback[0]; // Most recent feedback
                const feedbackSummary = [];

                if (feedback.difficulty) {
                    if (feedback.difficulty >= 4) {
                        feedbackSummary.push('prefers less challenging experiments');
                    } else if (feedback.difficulty <= 2) {
                        feedbackSummary.push('wants more challenging experiments');
                    }
                }

                if (feedback.fit) {
                    if (feedback.fit <= 2) {
                        feedbackSummary.push('needs better personalization');
                    } else if (feedback.fit >= 4) {
                        feedbackSummary.push('good fit with current approach');
                    }
                }

                if (feedback.learning) {
                    if (feedback.learning >= 4) {
                        feedbackSummary.push('values high learning content');
                    } else if (feedback.learning <= 2) {
                        feedbackSummary.push('needs more practical learning outcomes');
                    }
                }

                if (feedback.evolve_notes) {
                    feedbackSummary.push(`specific request: "${feedback.evolve_notes}"`);
                }

                if (feedbackSummary.length > 0) {
                    reflectionContext = `\n\nRecent feedback: ${feedbackSummary.join(', ')}.`;
                }
            }

            // Build a rich prompt even with fallback data
            const profileSummary = [
                `Top strengths: ${topMI.map(mi => mi.label).join(', ')}`,
                `Interests: ${curiosities.join(', ')}`,
                `Inspiration: ${roleModels.join(', ')}`,
                `Constraints: ${timePerWeek}h/week, $${budget} budget, ${risk}/100 risk tolerance`
            ].join('\n- ');

            const enhancedSystemPrompt = 'Generate a creative variant of the given experiment. Keep the same archetype but change the approach, steps, and success criteria. Make it fresh and engaging while staying true to the user\'s profile. If recent feedback is provided, incorporate those insights to improve the variant. Return only valid JSON with the same structure as the original experiment.';

            return {
                system: enhancedSystemPrompt,
                user: `Original experiment: ${JSON.stringify(originalExperiment)}\n\nUser profile:\n- ${profileSummary}${reflectionContext}\n\nCreate a variant that feels different but maintains the same archetype and core intent. Focus on making it more engaging and personally relevant based on their strengths, interests, and any feedback provided.`
            };
        },

        // Set recent feedback data for AI variant generation
        setRecentFeedback: function (feedbackData) {
            this.recentFeedback = feedbackData;
        },

        // Start an experiment
        startExperiment: function (e) {
            e.preventDefault();
            const experimentIndex = parseInt($(e.target).data('experiment-id'));
            const experiment = this.experiments[experimentIndex];

            if (!experiment) return;

            // Store as current experiment for potential evolution
            this.currentExperiment = experiment;

            this.showLoading('Starting experiment...');

            // First, save the experiment to database if it doesn't have a database ID
            if (!experiment.database_id) {
                this.saveExperimentToDatabase(experiment, experimentIndex);
            } else {
                this.showRunningExperiment(experiment, experiment.database_id);
            }
        },

        // Save experiment to database and get database ID
        saveExperimentToDatabase: function (experiment, experimentIndex) {
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_save_experiment',
                    nonce: labMode.nonce,
                    experiment_data: JSON.stringify(experiment),
                    archetype: experiment.archetype || 'Discover'
                },
                success: (response) => {
                    if (response.success && response.data.experiment_id) {
                        // Store the database ID in the experiment
                        this.experiments[experimentIndex].database_id = response.data.experiment_id;

                        // Now show the running experiment with the database ID
                        this.showRunningExperiment(experiment, response.data.experiment_id);
                    } else {
                        this.showError('Failed to save experiment to database');
                    }
                },
                error: () => {
                    this.showError('Network error while saving experiment');
                }
            });
        },

        // Show running experiment interface
        showRunningExperiment: function (experiment, experimentId) {
            // Generate personalized Mi Approach based on user's profile
            const miApproach = this.generateMiApproach(experiment);

            const html = `
                <div class="lab-running-experiment">
                    <div class="experiment-header">
                        <h2>${experiment.title}</h2>
                        <span class="experiment-status status-active">Active</span>
                    </div>
                    
                    <div class="experiment-details">
                        <div class="experiment-overview">
                            <h3>What You'll Do:</h3>
                            <p class="experiment-description">${this.generateEngagingDescription(experiment)}</p>
                        </div>
                        
                        <div class="experiment-mi-approach">
                            <h3>Mi Approach:</h3>
                            <p class="mi-approach-text">${miApproach}</p>
                        </div>
                        
                        <div class="experiment-steps">
                            <h3>Steps to Complete:</h3>
                            <ul class="steps-checklist">
                                ${experiment.steps.map((step, index) => `
                                    <li>
                                        <input type="checkbox" id="step-${index}">
                                        <label for="step-${index}">${step}</label>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                        
                        <div class="experiment-effort">
                            <h3>Time & Resources:</h3>
                            <div class="effort-summary">
                                <span class="effort-item">⏱️ ${experiment.effort?.timeHours || 0} hours</span>
                                <span class="effort-item">💰 $${experiment.effort?.budgetUSD || 0}</span>
                                <span class="effort-item risk-${(experiment.riskLevel || 'medium').toLowerCase()}">🎯 ${experiment.riskLevel || 'Medium'} Risk</span>
                            </div>
                        </div>
                        
                        <div class="success-criteria">
                            <h3>Success Criteria:</h3>
                            <ul class="criteria-checklist">
                                ${(experiment.successCriteria || []).map((criteria, index) => `
                                    <li>
                                        <input type="checkbox" id="criteria-${index}">
                                        <label for="criteria-${index}">${criteria}</label>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                    
                    <div class="experiment-actions">
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.showExperiments()">Back to Experiments</button>
                        <button class="lab-btn lab-btn-primary lab-reflect-btn" data-experiment-id="${experimentId}">Complete & Reflect</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'running';
        },

        // Show reflection form
        showReflectionForm: function (e) {
            e.preventDefault();
            const experimentId = $(e.target).data('experiment-id');

            const html = `
                <div class="lab-reflection">
                    <h2>Reflection & Feedback</h2>
                    <p>Help us improve future suggestions by sharing how this experiment went.</p>
                    
                    <form class="lab-reflection-form" data-experiment-id="${experimentId}">
                        <div class="reflection-ratings">
                            <div class="rating-group">
                                <label>Difficulty (1 = Too Easy, 5 = Too Hard)</label>
                                <div class="rating-buttons">
                                    ${[1, 2, 3, 4, 5].map(n => `
                                        <input type="radio" name="difficulty" value="${n}" id="diff-${n}" required>
                                        <label for="diff-${n}">${n}</label>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <div class="rating-group">
                                <label>Fit (1 = Poor Match, 5 = Perfect Fit)</label>
                                <div class="rating-buttons">
                                    ${[1, 2, 3, 4, 5].map(n => `
                                        <input type="radio" name="fit" value="${n}" id="fit-${n}" required>
                                        <label for="fit-${n}">${n}</label>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <div class="rating-group">
                                <label>Learning Value (1 = No Growth, 5 = Major Growth)</label>
                                <div class="rating-buttons">
                                    ${[1, 2, 3, 4, 5].map(n => `
                                        <input type="radio" name="learning" value="${n}" id="learn-${n}" required>
                                        <label for="learn-${n}">${n}</label>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        
                        <div class="reflection-notes">
                            <label>What surprised you? (optional)</label>
                            <textarea name="notes" rows="3" placeholder="Any insights, unexpected outcomes, or thoughts about the process..."></textarea>
                        </div>
                        
                        <div class="next-action">
                            <label>Next Action:</label>
                            <div class="action-buttons">
                                <input type="radio" name="next_action" value="Repeat" id="action-repeat" required>
                                <label for="action-repeat">Repeat</label>
                                
                                <input type="radio" name="next_action" value="Evolve" id="action-evolve" required>
                                <label for="action-evolve">Evolve</label>
                                
                                <input type="radio" name="next_action" value="Archive" id="action-archive" required>
                                <label for="action-archive">Archive</label>
                            </div>
                        </div>
                        
                        <div class="evolve-notes" style="display:none;">
                            <label>What to change next time?</label>
                            <textarea name="evolve_notes" rows="2" placeholder="Specific adjustments for the next iteration..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="lab-btn lab-btn-secondary" onclick="LabModeApp.showExperiments()">Cancel</button>
                            <button type="submit" class="lab-btn lab-btn-primary">Submit Reflection</button>
                        </div>
                    </form>
                </div>
            `;

            $('#lab-mode-app').html(html);

            // Show/hide evolve notes based on selection
            $(document).on('change', 'input[name="next_action"]', function () {
                const evolveNotesDiv = $('.evolve-notes');
                if (this.value === 'Evolve') {
                    evolveNotesDiv.show();
                    evolveNotesDiv.find('textarea').prop('required', true);
                } else {
                    evolveNotesDiv.hide();
                    evolveNotesDiv.find('textarea').prop('required', false);
                }
            });

            this.currentStep = 'reflection';
        },

        // Submit reflection feedback
        submitReflection: function (e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const experimentId = $(e.target).data('experiment-id');

            const reflection = {
                experiment_id: experimentId,
                difficulty: parseInt(formData.get('difficulty')),
                fit: parseInt(formData.get('fit')),
                learning: parseInt(formData.get('learning')),
                notes: formData.get('notes') || '',
                next_action: formData.get('next_action'),
                evolve_notes: formData.get('evolve_notes') || ''
            };

            // Store the reflection data for potential evolution use
            this.lastReflection = reflection;

            this.showLoading('Submitting reflection and recalibrating...');

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_submit_reflection',
                    nonce: labMode.nonce,
                    reflection: JSON.stringify(reflection)
                },
                success: (response) => {
                    if (response.success) {
                        // If user chose 'Evolve' and provided specific notes, customize the recalibration actions
                        if (reflection.next_action === 'Evolve') {
                            this.showEvolutionOptions(response.data.recalibration, reflection);
                        } else {
                            this.showRecalibrationSummary(response.data.recalibration);
                        }
                    } else {
                        this.showError(response.data || 'Failed to submit reflection');
                    }
                },
                error: () => {
                    this.showError('Network error while submitting reflection');
                }
            });
        },

        // Show evolution options when user chose to evolve
        showEvolutionOptions: function (recalibration, reflection) {
            const evolveNotesText = reflection.evolve_notes ?
                `<div class="evolution-feedback">
                    <h4>Your Evolution Request:</h4>
                    <p class="user-feedback">"${reflection.evolve_notes}"</p>
                </div>` : '';

            const html = `
                <div class="lab-recalibration evolution-mode">
                    <h2>Evolution Ready</h2>
                    <div class="recalibration-summary">
                        <h3>Preferences Updated</h3>
                        <p>${recalibration.summary}</p>
                        
                        ${evolveNotesText}
                        
                        <div class="recalibration-details">
                            <h4>Updated Preferences:</h4>
                            <ul>
                                <li>Risk Bias: ${recalibration.risk_bias}</li>
                                <li>Solo/Group Bias: ${recalibration.solo_group_bias}</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="recalibration-actions">
                        <button class="lab-btn lab-btn-primary" onclick="LabModeApp.generateEvolvedExperiment()">Generate Evolved Experiment</button>
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.generateNextIteration()">Generate Fresh Experiments</button>
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.showHistory()">View History</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'evolution';
        },

        // Show recalibration summary
        showRecalibrationSummary: function (recalibration) {
            const html = `
                <div class="lab-recalibration">
                    <h2>Recalibration Complete</h2>
                    <div class="recalibration-summary">
                        <h3>What Changed:</h3>
                        <p>${recalibration.summary}</p>
                        
                        <div class="recalibration-details">
                            <h4>Updated Preferences:</h4>
                            <ul>
                                <li>Risk Bias: ${recalibration.risk_bias}</li>
                                <li>Solo/Group Bias: ${recalibration.solo_group_bias}</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="recalibration-actions">
                        <button class="lab-btn lab-btn-primary" onclick="LabModeApp.generateNextIteration()">Generate Next Iteration</button>
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.showHistory()">View History</button>
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.loadLandingView()">Start Fresh</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'recalibration';
        },

        // Generate evolved experiment based on user's specific feedback
        generateEvolvedExperiment: function () {
            if (!this.lastReflection || !this.currentExperiment) {
                this.showError('Missing reflection data for evolution. Please try again.');
                return;
            }

            this.showLoading('Generating evolved experiment based on your specific feedback...');

            // Use AI to evolve the current experiment with specific feedback
            const evolutionPrompt = this.buildEvolutionPrompt(this.currentExperiment, this.lastReflection);

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_generate_ai_variant',
                    nonce: labMode.nonce,
                    original_experiment: JSON.stringify(this.currentExperiment),
                    prompt_data: JSON.stringify(evolutionPrompt)
                },
                success: (response) => {
                    if (response.success && response.data.variant) {
                        // Replace current experiments with the evolved version
                        this.experiments = [{
                            ...response.data.variant,
                            _aiGenerated: true,
                            _evolvedFrom: this.currentExperiment.title,
                            _evolutionNotes: this.lastReflection.evolve_notes
                        }];

                        this.showExperiments();
                    } else {
                        console.error('Evolution failed:', response);
                        this.showError(response.data || 'Evolution failed. Please try again.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Evolution AJAX error:', { xhr, status, error });
                    this.showError('Network error while evolving experiment. Please try again.');
                }
            });
        },

        // Generate next iteration of experiments
        generateNextIteration: function () {
            this.showLoading('Generating evolved experiments based on your feedback...');

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_recalibrate',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.experiments = response.data.experiments;
                        this.showExperiments();
                    } else {
                        this.showError(response.data || 'Failed to generate evolved experiments');
                    }
                },
                error: () => {
                    this.showError('Network error while generating evolved experiments');
                }
            });
        },

        // Show experiment history
        showHistory: function () {
            this.showLoading('Loading your experiment history...');

            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_get_history',
                    nonce: labMode.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayHistory(response.data);
                    } else {
                        this.showError(response.data || 'Failed to load history');
                    }
                },
                error: () => {
                    this.showError('Network error while loading history');
                }
            });
        },

        // Display experiment history
        displayHistory: function (experiments) {
            // Extract recent feedback for AI variant generation
            const recentFeedbackData = [];
            experiments.forEach(exp => {
                if (exp.feedback && exp.feedback.length > 0) {
                    recentFeedbackData.push(...exp.feedback);
                }
            });

            // Sort by most recent and store for AI variant generation
            recentFeedbackData.sort((a, b) => {
                const dateA = new Date(a.submitted_at || 0);
                const dateB = new Date(b.submitted_at || 0);
                return dateB - dateA;
            });

            // Set recent feedback for AI variant generation
            this.setRecentFeedback(recentFeedbackData.slice(0, 3)); // Keep last 3 feedback entries

            const html = `
                <div class="lab-history">
                    <h2>Experiment History</h2>
                    <p>Timeline of your experiments with status and key metrics.</p>
                    
                    <div class="history-timeline">
                        ${experiments.length === 0 ? `
                            <div class="no-experiments">
                                <p>No experiments yet. <a href="#" onclick="LabModeApp.startProfileInputs(event)">Create your first experiment</a>.</p>
                            </div>
                        ` : experiments.map((exp, index) => {
                const hasReflection = exp.feedback && exp.feedback.length > 0;
                const canReflect = exp.status === 'Active' || exp.status === 'Completed';

                return `
                            <div class="history-item status-${exp.status.toLowerCase()}" data-experiment-id="${exp.id}">
                                <div class="history-header">
                                    <h3 class="clickable-title" onclick="LabModeApp.viewExperimentFromHistory(${exp.id})">${exp.experiment_data.title}</h3>
                                    <div class="history-meta">
                                        <span class="status-badge status-${exp.status.toLowerCase()}">${exp.status}</span>
                                        <span class="archetype-badge">${exp.archetype}</span>
                                        <span class="date">${new Date(exp.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                                <p class="history-rationale">${exp.experiment_data.rationale || exp.experiment_data.description || 'No description available.'}</p>
                                
                                <div class="history-item-actions">
                                    <button class="lab-btn lab-btn-small lab-btn-secondary" onclick="LabModeApp.viewExperimentFromHistory(${exp.id})">View Details</button>
                                    ${canReflect && !hasReflection ? `
                                        <button class="lab-btn lab-btn-small lab-btn-primary" onclick="LabModeApp.showReflectionFormFromHistory(${exp.id})">Complete & Reflect</button>
                                    ` : ''}
                                    ${hasReflection ? `
                                        <span class="reflection-status">✅ Reflected</span>
                                    ` : ''}
                                </div>
                                
                                ${hasReflection ? `
                                    <div class="reflection-summary">
                                        <strong>Your reflection:</strong> ${exp.feedback[0].notes || 'No notes provided'}
                                        ${exp.feedback[0].evolve_notes ? `<br><strong>Evolution notes:</strong> ${exp.feedback[0].evolve_notes}` : ''}
                                    </div>
                                ` : ''}
                            </div>
                            `;
            }).join('')}
                    </div>
                    
                    <div class="history-actions">
                        <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.loadLandingView()">Back to Start</button>
                    </div>
                </div>
            `;

            $('#lab-mode-app').html(html);
            this.currentStep = 'history';
        },

        // View experiment from history
        viewExperimentFromHistory: function (experimentId) {
            this.showLoading('Loading experiment details...');

            // Get experiment data from server
            $.ajax({
                url: labMode.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mc_lab_get_experiment',
                    nonce: labMode.nonce,
                    experiment_id: experimentId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        const experiment = response.data.experiment_data;
                        this.showRunningExperiment(experiment, experimentId);
                    } else {
                        this.showError('Failed to load experiment details');
                    }
                },
                error: () => {
                    this.showError('Network error while loading experiment');
                }
            });
        },

        // Show reflection form for experiment from history
        showReflectionFormFromHistory: function (experimentId) {
            this.showReflectionForm({ preventDefault: () => { }, target: { dataset: { experimentId: experimentId } } });
        },

        // Open iteration panel for experiment refinement
        openIterationPanel: function (event) {
            event.preventDefault();

            const experimentIndex = $(event.target).data('experiment-id');
            console.log('Looking for experiment with index:', experimentIndex, 'in experiments array of length:', this.experiments.length);

            // Find experiment by originalIndex (which may be stored in the data attribute)
            const experiment = this.experiments[experimentIndex];

            if (!experiment) {
                console.error('Experiment not found at index:', experimentIndex);
                console.log('Available experiments:', this.experiments.map((exp, idx) => ({ index: idx, title: exp.title })));
                this.showError('Experiment not found. Please try refreshing the page.');
                return;
            }

            console.log('Found experiment:', experiment);

            // Ensure IterationPanel is loaded
            if (typeof window.IterationPanel === 'undefined') {
                console.error('IterationPanel not loaded');
                this.showError('Iteration panel not available. Please refresh the page.');
                return;
            }

            console.log('Opening iteration panel for experiment at index:', experimentIndex, experiment);

            // Build user context
            const userContext = {
                mi_top3: this.profileData?.mi_results?.slice(0, 3) || [],
                cdt_bottom2: this.profileData?.cdt_results?.slice(-2) || [],
                curiosities: this.qualifiers?.curiosity?.curiosities || [],
                roleModels: this.qualifiers?.curiosity?.roleModels || [],
                constraints: this.qualifiers?.curiosity?.constraints || {}
            };

            window.IterationPanel.open(experiment, experimentIndex, userContext);
        },

        // Update experiment in the experiments array and re-render
        updateExperiment: function (index, updatedExperiment) {
            if (index >= 0 && index < this.experiments.length) {
                this.experiments[index] = $.extend(true, {}, updatedExperiment);
                console.log('Updated experiment at index:', index, updatedExperiment);

                // Re-render the experiments view to show changes
                this.showExperiments();
            } else {
                console.error('Invalid experiment index:', index);
            }
        }
    };

    // Initialize Lab Mode when called
    function initializeLabMode() {
        if ($('#lab-mode-app').length > 0 && !window.LabModeAppInitialized) {
            console.log('Initializing Lab Mode...');
            LabModeApp.init();
            return true;
        }
        return false;
    }

    // Make initialization function globally available
    window.initializeLabMode = initializeLabMode;

    // Auto-initialize when document is ready
    $(document).ready(function () {
        initializeLabMode();

        // Also try when Lab Mode tab is clicked
        $(document).on('click', '[data-tab="tab-lab"]', function () {
            setTimeout(initializeLabMode, 100);
        });
    });

    // Fallback initialization on window load
    $(window).on('load', function () {
        initializeLabMode();
    });

})(jQuery);
