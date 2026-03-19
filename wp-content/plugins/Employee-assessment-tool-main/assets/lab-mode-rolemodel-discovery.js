/**
 * Role Model Discovery Modal - Two-step process for enhanced role model selection
 * Step 1: Input collection (user role models + categories)
 * Step 2: AI suggestions display with selection interface
 */

(function($) {
    'use strict';

    window.RoleModelDiscovery = {
        isOpen: false,
        currentStep: 1, // 1 = input, 2 = suggestions
        userRoleModels: [],
        selectedCategories: [],
        aiSuggestions: [],
        selectedSuggestions: [],
        onComplete: null, // Callback when user completes selection
        
        // Category options for role model analysis
        availableCategories: [
            'Literary Figures',
            'Media Influencers', 
            'Musicians',
            'Artists',
            'Scientists',
            'Activists',
            'Entrepreneurs',
            'Authors',
            'Philosophers',
            'Innovators',
            'Coaches',
            'Educators'
        ],

        /**
         * Open the role model discovery modal
         * @param {Function} callback - Function to call when selection is complete
         */
        open: function(callback) {
            if (this.isOpen) return;
            
            this.isOpen = true;
            this.currentStep = 1;
            this.userRoleModels = [];
            this.selectedCategories = [];
            this.aiSuggestions = [];
            this.selectedSuggestions = [];
            this.onComplete = callback;
            
            this.createModal();
            this.showStep1();
        },

        /**
         * Close the modal and reset state
         */
        close: function() {
            if (!this.isOpen) return;
            
            $('#rolemodel-discovery-modal').removeClass('active');
            $('body').removeClass('modal-open');
            
            setTimeout(() => {
                $('#rolemodel-discovery-modal').remove();
                this.isOpen = false;
                this.currentStep = 1;
                this.userRoleModels = [];
                this.selectedCategories = [];
                this.aiSuggestions = [];
                this.selectedSuggestions = [];
                this.onComplete = null;
            }, 300);
        },

        /**
         * Create the modal DOM structure
         */
        createModal: function() {
            const modalHtml = `
                <div id="rolemodel-discovery-modal" class="rolemodel-discovery-modal" role="dialog" aria-modal="true" aria-labelledby="rolemodel-modal-title">
                    <div class="rolemodel-backdrop"></div>
                    <div class="rolemodel-dialog">
                        <div class="rolemodel-header">
                            <h2 id="rolemodel-modal-title">Discover Role Models</h2>
                            <button type="button" class="rolemodel-close-btn" aria-label="Close role model discovery">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="rolemodel-content">
                            <div class="rolemodel-progress">
                                <div class="progress-step" data-step="1">
                                    <div class="step-circle">1</div>
                                    <span class="step-label">Your Models</span>
                                </div>
                                <div class="progress-line"></div>
                                <div class="progress-step" data-step="2">
                                    <div class="step-circle">2</div>
                                    <span class="step-label">AI Suggestions</span>
                                </div>
                            </div>
                            <div id="rolemodel-step-content">
                                <!-- Step content will be rendered here -->
                            </div>
                        </div>
                        <div class="rolemodel-footer">
                            <button type="button" class="rolemodel-btn rolemodel-btn-secondary" id="rolemodel-back-btn" style="display: none;">
                                ← Back
                            </button>
                            <div class="rolemodel-footer-spacer"></div>
                            <button type="button" class="rolemodel-btn rolemodel-btn-secondary" id="rolemodel-cancel-btn">
                                Cancel
                            </button>
                            <button type="button" class="rolemodel-btn rolemodel-btn-primary" id="rolemodel-next-btn" disabled>
                                Next →
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('body').addClass('modal-open');
            
            this.bindEvents();
            
            // Show modal with animation
            setTimeout(() => {
                $('#rolemodel-discovery-modal').addClass('active');
            }, 10);
        },

        /**
         * Bind modal events
         */
        bindEvents: function() {
            // Close button
            $(document).on('click', '.rolemodel-close-btn, .rolemodel-backdrop, #rolemodel-cancel-btn', (e) => {
                e.preventDefault();
                this.close();
            });

            // Navigation buttons
            $(document).on('click', '#rolemodel-back-btn', (e) => {
                e.preventDefault();
                this.goToStep1();
            });

            $(document).on('click', '#rolemodel-next-btn', (e) => {
                e.preventDefault();
                if (this.currentStep === 1) {
                    this.goToStep2();
                } else {
                    this.completeSelection();
                }
            });

            // ESC key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    e.preventDefault();
                    this.close();
                }
            });
        },

        /**
         * Show step 1 - input collection
         */
        showStep1: function() {
            this.currentStep = 1;
            this.updateProgressIndicator();

            const stepHtml = `
                <div class="rolemodel-step-1">
                    <div class="step-intro">
                        <h3>Who do you admire?</h3>
                        <p>Name 2–3 people who inspire you. These could be authors, creators, leaders, or anyone whose work or approach you find compelling.</p>
                    </div>

                    <div class="rolemodel-input-section">
                        <label class="rolemodel-label">Your Role Models</label>
                        <div class="rolemodel-inputs">
                            <input type="text" class="rolemodel-input" id="rolemodel-1" placeholder="e.g., Maya Angelou" maxlength="100">
                            <input type="text" class="rolemodel-input" id="rolemodel-2" placeholder="e.g., Steve Jobs" maxlength="100">
                            <input type="text" class="rolemodel-input" id="rolemodel-3" placeholder="e.g., Marie Kondo (optional)" maxlength="100">
                        </div>
                        <small class="rolemodel-help">Enter names or handles (at least 2 required)</small>
                    </div>

                    <div class="rolemodel-categories-section">
                        <label class="rolemodel-label">Categories of Admiration</label>
                        <p class="category-description">What areas do your role models represent? Select 1-3 categories to guide our AI analysis.</p>
                        <div class="rolemodel-category-grid">
                            ${this.availableCategories.map(category => `
                                <label class="rolemodel-category-option">
                                    <input type="checkbox" value="${category}" class="category-checkbox">
                                    <span class="category-label">${category}</span>
                                </label>
                            `).join('')}
                            <div class="rolemodel-category-option rolemodel-other-option">
                                <input type="checkbox" value="Other" class="category-checkbox" id="category-other" style="display: none;">
                                <input type="text" class="other-category-input" placeholder="Other category..." maxlength="50">
                            </div>
                        </div>
                        <small class="rolemodel-help">Choose 1-3 categories that best describe your areas of interest</small>
                    </div>
                </div>
            `;

            $('#rolemodel-step-content').html(stepHtml);
            $('#rolemodel-back-btn').hide();
            $('#rolemodel-next-btn').text('Analyze & Get Suggestions').prop('disabled', true);

            // Bind step 1 specific events
            this.bindStep1Events();
        },

        /**
         * Bind events specific to step 1
         */
        bindStep1Events: function() {
            // Role model input validation
            $(document).on('input', '.rolemodel-input', () => {
                this.validateStep1();
            });

            // Category checkbox handling
            $(document).on('change', '.category-checkbox', (e) => {
                const checkbox = $(e.target);
                const isOther = checkbox.attr('id') === 'category-other';
                
                // Auto-check the Other checkbox when user types in the text field
                if (isOther && !checkbox.is(':checked')) {
                    const otherInput = $('.other-category-input');
                    if (otherInput.val().trim()) {
                        checkbox.prop('checked', true);
                    }
                }

                // Limit to 3 categories
                const checkedBoxes = $('.category-checkbox:checked');
                if (checkedBoxes.length > 3) {
                    checkbox.prop('checked', false);
                    this.showValidationMessage('Please select a maximum of 3 categories.');
                    return;
                }

                this.validateStep1();
            });

            // Other category input - auto-check checkbox when user types
            $(document).on('input', '.other-category-input', (e) => {
                const otherInput = $(e.target);
                const otherCheckbox = $('#category-other');
                const hasValue = otherInput.val().trim().length > 0;
                
                // Auto-check/uncheck the Other checkbox based on input content
                if (hasValue && !otherCheckbox.is(':checked')) {
                    // Check if we're already at the 3 category limit
                    const checkedBoxes = $('.category-checkbox:checked');
                    if (checkedBoxes.length < 3) {
                        otherCheckbox.prop('checked', true);
                    }
                } else if (!hasValue && otherCheckbox.is(':checked')) {
                    otherCheckbox.prop('checked', false);
                }
                
                this.validateStep1();
            });
        },

        /**
         * Validate step 1 inputs
         */
        validateStep1: function() {
            // Check role models (at least 2 required)
            const roleModels = [];
            $('.rolemodel-input').each(function() {
                const value = $(this).val().trim();
                if (value) {
                    roleModels.push(value);
                }
            });

            // Check categories (at least 1 required)
            const categories = [];
            $('.category-checkbox:checked').each(function() {
                const value = $(this).val();
                if (value === 'Other') {
                    const otherValue = $('.other-category-input').val().trim();
                    if (otherValue) {
                        categories.push(otherValue);
                    }
                } else {
                    categories.push(value);
                }
            });

            const isValid = roleModels.length >= 2 && categories.length >= 1;
            $('#rolemodel-next-btn').prop('disabled', !isValid);

            // Clear any previous validation messages if now valid
            if (isValid) {
                $('.rolemodel-validation-message').remove();
            }

            this.userRoleModels = roleModels;
            this.selectedCategories = categories;

            return isValid;
        },

        /**
         * Show validation message
         */
        showValidationMessage: function(message) {
            $('.rolemodel-validation-message').remove();
            const messageHtml = `<div class="rolemodel-validation-message">${message}</div>`;
            $('#rolemodel-step-content').prepend(messageHtml);
            
            setTimeout(() => {
                $('.rolemodel-validation-message').fadeOut(() => {
                    $('.rolemodel-validation-message').remove();
                });
            }, 3000);
        },

        /**
         * Go to step 2 - analyze role models and show suggestions
         */
        goToStep2: function() {
            if (!this.validateStep1()) {
                this.showValidationMessage('Please complete all required fields before continuing.');
                return;
            }

            this.showLoading();
            
            // Call AI analysis
            this.analyzeRoleModels().then(() => {
                this.showStep2();
            }).catch((error) => {
                console.error('Role model analysis failed:', error);
                this.showError('Failed to analyze role models. Please try again.');
            });
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            const loadingHtml = `
                <div class="rolemodel-loading">
                    <div class="loading-animation">
                        <div class="loading-circle"></div>
                        <div class="loading-circle"></div>
                        <div class="loading-circle"></div>
                    </div>
                    <h3>Analyzing Your Role Models</h3>
                    <p>Our AI is finding people with similar qualities and approaches...</p>
                </div>
            `;

            $('#rolemodel-step-content').html(loadingHtml);
            $('#rolemodel-next-btn').prop('disabled', true).text('Analyzing...');
        },

        /**
         * Show error state
         */
        showError: function(message) {
            const errorHtml = `
                <div class="rolemodel-error">
                    <div class="error-icon">⚠️</div>
                    <h3>Analysis Error</h3>
                    <p>${message}</p>
                    <button type="button" class="rolemodel-btn rolemodel-btn-primary" onclick="RoleModelDiscovery.goToStep1()">
                        Try Again
                    </button>
                </div>
            `;

            $('#rolemodel-step-content').html(errorHtml);
            $('#rolemodel-next-btn').prop('disabled', false).text('Next →');
        },

        /**
         * Analyze role models using AI
         */
        analyzeRoleModels: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: window.labMode?.ajaxUrl || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'mc_lab_analyze_role_models',
                        nonce: window.labMode?.nonce || '',
                        userRoleModels: JSON.stringify(this.userRoleModels),
                        categories: JSON.stringify(this.selectedCategories)
                    },
                    timeout: 60000, // 60 second timeout for AI analysis
                    success: (response) => {
                        if (response.success && response.data.suggestions) {
                            this.aiSuggestions = response.data.suggestions;
                            console.log('Role model suggestions received:', this.aiSuggestions);
                            resolve(response.data.suggestions);
                        } else {
                            console.error('Role model analysis failed:', response);
                            reject(new Error(response.data || 'Analysis failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Role model analysis error:', { xhr, status, error });
                        reject(new Error('Network error during analysis'));
                    }
                });
            });
        },

        /**
         * Show step 2 - AI suggestions
         */
        showStep2: function() {
            this.currentStep = 2;
            this.updateProgressIndicator();

            const stepHtml = `
                <div class="rolemodel-step-2">
                    <div class="step-intro">
                        <h3>People You Might Also Admire</h3>
                        <p>Based on your role models (${this.userRoleModels.join(', ')}), here are some similar people who might inspire you:</p>
                    </div>

                    <div class="rolemodel-suggestions-grid">
                        ${this.aiSuggestions.map((suggestion, index) => `
                            <div class="rolemodel-suggestion-card" data-index="${index}" role="button" tabindex="0" aria-pressed="false">
                                <div class="suggestion-header">
                                    <h4 class="suggestion-name">${suggestion.name}</h4>
                                    <span class="suggestion-category">${suggestion.category}</span>
                                </div>
                                <p class="suggestion-description">${suggestion.description}</p>
                            </div>
                        `).join('')}
                    </div>

                    <div class="rolemodel-custom-section">
                        <div class="custom-section-header">
                            <span class="custom-icon">➕</span>
                            <span>None of these fit? Add your own</span>
                        </div>
                        <div class="custom-inputs">
                            <input type="text" class="rolemodel-custom-input" placeholder="Name another role model..." maxlength="100">
                            <button type="button" class="rolemodel-btn rolemodel-btn-secondary add-custom-btn">Add</button>
                        </div>
                        <div class="custom-role-models" id="custom-role-models-list">
                            <!-- Custom added role models will appear here -->
                        </div>
                    </div>
                </div>
            `;

            $('#rolemodel-step-content').html(stepHtml);
            $('#rolemodel-back-btn').show();
            $('#rolemodel-next-btn').text('Complete Selection').prop('disabled', true);

            // Bind step 2 specific events
            this.bindStep2Events();
        },

        /**
         * Bind events specific to step 2
         */
        bindStep2Events: function() {
            // Suggestion card selection
            $(document).on('click', '.rolemodel-suggestion-card', (e) => {
                const card = $(e.currentTarget);
                const index = parseInt(card.data('index'));
                
                card.toggleClass('selected');
                
                const isSelected = card.hasClass('selected');
                card.attr('aria-pressed', isSelected);

                if (isSelected) {
                    this.selectedSuggestions.push(this.aiSuggestions[index]);
                } else {
                    this.selectedSuggestions = this.selectedSuggestions.filter(s => s.name !== this.aiSuggestions[index].name);
                }

                this.validateStep2();
            });

            // Keyboard support for cards
            $(document).on('keydown', '.rolemodel-suggestion-card', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(e.currentTarget).click();
                }
            });

            // Custom role model addition
            $(document).on('click', '.add-custom-btn', () => {
                this.addCustomRoleModel();
            });

            $(document).on('keydown', '.rolemodel-custom-input', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.addCustomRoleModel();
                }
            });

            // Remove custom role model
            $(document).on('click', '.remove-custom-btn', (e) => {
                const name = $(e.target).data('name');
                this.removeCustomRoleModel(name);
            });
        },

        /**
         * Add custom role model
         */
        addCustomRoleModel: function() {
            const input = $('.rolemodel-custom-input');
            const name = input.val().trim();
            
            if (!name) return;
            
            // Check if already exists
            const existingCustom = this.selectedSuggestions.find(s => s.name === name && s.category === 'Custom');
            if (existingCustom) {
                this.showValidationMessage('This role model has already been added.');
                return;
            }
            
            // Add to selections
            const customModel = {
                name: name,
                category: 'Custom',
                description: 'Added by you'
            };
            
            this.selectedSuggestions.push(customModel);
            
            // Add to UI
            const customHtml = `
                <div class="custom-role-model-item" data-name="${name}">
                    <span class="custom-name">${name}</span>
                    <button type="button" class="remove-custom-btn" data-name="${name}">×</button>
                </div>
            `;
            $('#custom-role-models-list').append(customHtml);
            
            // Clear input
            input.val('');
            
            this.validateStep2();
        },

        /**
         * Remove custom role model
         */
        removeCustomRoleModel: function(name) {
            this.selectedSuggestions = this.selectedSuggestions.filter(s => !(s.name === name && s.category === 'Custom'));
            $(`.custom-role-model-item[data-name="${name}"]`).remove();
            this.validateStep2();
        },

        /**
         * Validate step 2 selections
         */
        validateStep2: function() {
            // At least 1 selection required, max 3 recommended
            const totalSelected = this.selectedSuggestions.length;
            const isValid = totalSelected >= 1;
            
            $('#rolemodel-next-btn').prop('disabled', !isValid);
            
            if (totalSelected > 3) {
                this.showValidationMessage('We recommend selecting 1-3 role models for the best experience.');
            }

            return isValid;
        },

        /**
         * Update progress indicator
         */
        updateProgressIndicator: function() {
            $('.progress-step').removeClass('active completed');
            
            if (this.currentStep === 1) {
                $('.progress-step[data-step="1"]').addClass('active');
            } else {
                $('.progress-step[data-step="1"]').addClass('completed');
                $('.progress-step[data-step="2"]').addClass('active');
            }
        },

        /**
         * Go back to step 1
         */
        goToStep1: function() {
            this.showStep1();
        },

        /**
         * Complete the selection and call the callback
         */
        completeSelection: function() {
            if (!this.validateStep2()) {
                this.showValidationMessage('Please select at least one role model to continue.');
                return;
            }

            const finalSelection = {
                userInputModels: this.userRoleModels,
                selectedCategories: this.selectedCategories,
                aiSuggestions: this.aiSuggestions,
                finalSelection: this.selectedSuggestions
            };

            console.log('Role model discovery completed:', finalSelection);

            // Call the completion callback
            if (typeof this.onComplete === 'function') {
                this.onComplete(finalSelection);
            }

            // Close the modal
            this.close();
        }
    };

})(jQuery);