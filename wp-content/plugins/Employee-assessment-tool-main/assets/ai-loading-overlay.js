/**
 * AI Loading Overlay Component
 * Displays animated loading state while AI generates personalized suggestions
 */

(function($) {
    'use strict';

    window.AILoadingOverlay = {
        isVisible: false,
        messageIndex: 0,
        messageInterval: null,
        animationTimeout: null,
        
        // Content variations that rotate every 5-7 seconds
        messages: [
            "Taking time to pull from your MI, CDT scores, and Motivational Scores…",
            "Cross-checking your strengths and growth areas to make this experiment fit you…",
            "Calibrating cost, time, and risk to your preferences…",
            "Adding inspiration from your chosen role models and curiosities…",
            "Almost ready: a safe, low-stakes experiment just for you…"
        ],
        
        /**
         * Show the loading overlay
         * @param {Object} options - Configuration options
         * @param {string[]} options.messages - Custom messages to rotate through
         * @param {string} options.subtitle - Custom subtitle text
         */
        show: function(options = {}) {
            if (this.isVisible) return;
            
            this.isVisible = true;
            this.messageIndex = 0;
            
            // Use custom messages if provided
            if (options.messages && options.messages.length > 0) {
                this.messages = options.messages;
            }
            
            const subtitle = options.subtitle || "AI is crafting your personalized experiment…";
            
            // Create overlay HTML
            const overlayHtml = `
                <div id="ai-loading-overlay" class="ai-loading-overlay" role="status" aria-live="polite">
                    <div class="ai-loading-backdrop"></div>
                    <div class="ai-loading-content">
                        <!-- Cancel button for safety -->
                        <button type="button" class="ai-loading-cancel" aria-label="Cancel AI generation">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        
                        <!-- Spinner -->
                        <div class="ai-loading-spinner">
                            <svg class="ai-spinner-icon" viewBox="0 0 50 50">
                                <circle class="ai-spinner-path" cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10"></circle>
                            </svg>
                        </div>
                        
                        <!-- Subtitle -->
                        <h3 class="ai-loading-subtitle">${subtitle}</h3>
                        
                        <!-- Scrolling marquee text container -->
                        <div class="ai-loading-marquee-container">
                            <div class="ai-loading-marquee">
                                <span class="ai-loading-text" id="ai-loading-text">${this.messages[0]}</span>
                            </div>
                        </div>
                        
                        <!-- Progress indicator -->
                        <div class="ai-loading-progress">
                            <div class="ai-progress-bar">
                                <div class="ai-progress-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add to DOM
            $('body').append(overlayHtml);
            $('body').addClass('ai-loading-active');
            
            // Start animations
            this.startMessageRotation();
            this.startProgressAnimation();
            
            // Fade in overlay
            setTimeout(() => {
                $('#ai-loading-overlay').addClass('ai-loading-visible');
            }, 10);
            
            // Bind events
            this.bindEvents();
        },
        
        /**
         * Hide the loading overlay
         */
        hide: function() {
            if (!this.isVisible) return;
            
            this.isVisible = false;
            
            // Clear intervals
            if (this.messageInterval) {
                clearInterval(this.messageInterval);
                this.messageInterval = null;
            }
            
            if (this.animationTimeout) {
                clearTimeout(this.animationTimeout);
                this.animationTimeout = null;
            }
            
            // Fade out overlay
            $('#ai-loading-overlay').removeClass('ai-loading-visible');
            
            // Remove from DOM after animation
            setTimeout(() => {
                $('#ai-loading-overlay').remove();
                $('body').removeClass('ai-loading-active');
            }, 400);
        },
        
        /**
         * Start rotating through messages
         */
        startMessageRotation: function() {
            // Longer interval for better readability: 4-5 seconds
            const getRandomInterval = () => Math.floor(Math.random() * 1000) + 4000;
            
            const rotateMessage = () => {
                this.messageIndex = (this.messageIndex + 1) % this.messages.length;
                const textElement = $('#ai-loading-text');
                
                // Fade out current text
                textElement.addClass('ai-text-fade-out');
                
                setTimeout(() => {
                    // Change text and fade back in
                    textElement.text(this.messages[this.messageIndex]);
                    textElement.removeClass('ai-text-fade-out').addClass('ai-text-fade-in');
                    
                    setTimeout(() => {
                        textElement.removeClass('ai-text-fade-in');
                    }, 600); // Match CSS transition duration
                }, 600); // Match CSS transition duration
                
                // Schedule next rotation
                this.animationTimeout = setTimeout(rotateMessage, getRandomInterval());
            };
            
            // Start first rotation
            this.animationTimeout = setTimeout(rotateMessage, getRandomInterval());
        },
        
        /**
         * Start progress bar animation
         */
        startProgressAnimation: function() {
            // Simulate gradual progress
            const progressBar = $('.ai-progress-fill');
            let progress = 0;
            
            const updateProgress = () => {
                if (!this.isVisible) return;
                
                // Gradually increase progress with some randomness
                progress += Math.random() * 3 + 1;
                progress = Math.min(progress, 85); // Never reach 100% until actually complete
                
                progressBar.css('width', progress + '%');
                
                if (progress < 85) {
                    setTimeout(updateProgress, 500 + Math.random() * 1000);
                }
            };
            
            updateProgress();
        },
        
        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Cancel button
            $(document).off('click.ailoading').on('click.ailoading', '.ai-loading-cancel', () => {
                // Emit custom event for parent components to handle
                $(document).trigger('ai-loading-cancelled');
            });
            
            // ESC key to cancel
            $(document).off('keydown.ailoading').on('keydown.ailoading', (e) => {
                if (e.key === 'Escape' && this.isVisible) {
                    $(document).trigger('ai-loading-cancelled');
                }
            });
            
            // Prevent scrolling on body when overlay is active
            $(document).off('wheel.ailoading touchmove.ailoading').on('wheel.ailoading touchmove.ailoading', (e) => {
                if (this.isVisible) {
                    e.preventDefault();
                }
            });
        },
        
        /**
         * Update the subtitle text
         * @param {string} subtitle - New subtitle text
         */
        updateSubtitle: function(subtitle) {
            $('.ai-loading-subtitle').text(subtitle);
        },
        
        /**
         * Add a new message to the rotation
         * @param {string} message - Message to add
         */
        addMessage: function(message) {
            this.messages.push(message);
        },
        
        /**
         * Set progress manually (0-100)
         * @param {number} percent - Progress percentage
         */
        setProgress: function(percent) {
            $('.ai-progress-fill').css('width', Math.min(Math.max(percent, 0), 100) + '%');
        },
        
        /**
         * Complete progress animation
         * @param {boolean} autoHide - Whether to automatically hide after completion (default: false)
         * @param {number} delay - Delay before auto-hide in milliseconds (default: 800)
         */
        completeProgress: function(autoHide = false, delay = 800) {
            $('.ai-progress-fill').css('width', '100%');
            if (autoHide) {
                setTimeout(() => this.hide(), delay);
            }
        }
    };
    
})(jQuery);