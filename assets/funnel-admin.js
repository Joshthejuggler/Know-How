/**
 * Quiz Funnel Admin JavaScript
 * Handles drag-and-drop reordering, form submission, and preview updates
 */
(function($) {
    'use strict';
    
    let adminData = null;
    let isLoading = false;
    
    $(document).ready(function() {
        // Check if we have admin data
        if (typeof mc_funnel_admin_data === 'undefined') {
            console.warn('MC Funnel Admin: No data found');
            return;
        }
        
        adminData = mc_funnel_admin_data;
        
        initializeSortable();
        bindEvents();
        updatePlaceholderFieldVisibility();
    });
    
    function initializeSortable() {
        $('#mc-funnel-steps').sortable({
            handle: '.mc-funnel-step-drag',
            placeholder: 'mc-funnel-step-placeholder',
            update: function() {
                updateStepNumbers();
                updatePreview();
            }
        });
    }
    
    function bindEvents() {
        // Form submission
        $('#mc-funnel-form').on('submit', function(e) {
            e.preventDefault();
            saveFunnelConfig();
        });
        
        // Reset button
        $('#mc-funnel-reset').on('click', function(e) {
            e.preventDefault();
            if (confirm('Reset to default configuration? This will undo all your changes.')) {
                resetToDefaults();
            }
        });
        
        // Title input changes
        $('.mc-funnel-step-title-input').on('input', debounce(function() {
            updatePreview();
        }, 500));
        
        // Placeholder enable/disable
        $('input[name="placeholder_enabled"]').on('change', function() {
            updatePlaceholderFieldVisibility();
            updatePreview();
        });
        
        // Placeholder field changes
        $('#placeholder_title, #placeholder_description, #placeholder_target').on('input', debounce(function() {
            updatePreview();
        }, 500));
    }
    
    function updateStepNumbers() {
        $('#mc-funnel-steps .mc-funnel-step-admin').each(function(index) {
            $(this).find('.mc-funnel-step-number').text(index + 1);
        });
    }
    
    function updatePlaceholderFieldVisibility() {
        const isEnabled = $('input[name="placeholder_enabled"]').is(':checked');
        $('.mc-funnel-placeholder-fields').toggle(isEnabled);
    }
    
    function updatePreview() {
        // This is a simplified preview update
        // In a real implementation, you might want to make an AJAX call to get the updated HTML
        const $preview = $('#mc-funnel-preview');
        $preview.addClass('updating');
        
        setTimeout(() => {
            $preview.removeClass('updating');
            // For now, just indicate that the preview would be updated
            if (!$preview.find('.preview-note').length) {
                $preview.append('<p class="preview-note"><em>Preview will update when you save changes.</em></p>');
            }
        }, 300);
    }
    
    function saveFunnelConfig() {
        if (isLoading) return;
        
        isLoading = true;
        showNotice('Saving configuration...', 'info', false);
        
        const $saveButton = $('#mc-funnel-save');
        const originalText = $saveButton.text();
        $saveButton.text('Saving...').prop('disabled', true);
        
        // Collect form data
        const formData = {
            action: 'mc_save_funnel',
            _ajax_nonce: adminData.nonce,
            steps: [],
            titles: {},
            placeholder_enabled: $('input[name="placeholder_enabled"]').is(':checked'),
            placeholder_title: $('#placeholder_title').val(),
            placeholder_description: $('#placeholder_description').val(),
            placeholder_target: $('#placeholder_target').val()
        };
        
        // Collect steps in order
        $('#mc-funnel-steps .mc-funnel-step-admin').each(function() {
            const slug = $(this).data('slug');
            const title = $(this).find('.mc-funnel-step-title-input').val();
            
            formData.steps.push(slug);
            formData.titles[slug] = title;
        });
        
        // Always add placeholder at the end
        formData.steps.push('placeholder');
        
        $.post(adminData.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('Configuration saved successfully!', 'success');
                    // Refresh the preview by reloading the shortcode
                    refreshPreview();
                } else {
                    showNotice('Error: ' + (response.data || 'Failed to save configuration'), 'error');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showNotice('Network error occurred. Please try again.', 'error');
            })
            .always(function() {
                isLoading = false;
                $saveButton.text(originalText).prop('disabled', false);
            });
    }
    
    function resetToDefaults() {
        // Reset form to default values
        const defaults = {
            steps: ['mi-quiz', 'cdt-quiz', 'bartle-quiz'],
            titles: {
                'mi-quiz': 'Multiple Intelligences Assessment',
                'cdt-quiz': 'Growth Strengths Assessment',
                'bartle-quiz': 'Core Motivation Assessment'
            },
            placeholder: {
                enabled: false,
                title: 'Advanced Self-Discovery Module',
                description: 'Coming soon - unlock deeper insights into your personal growth journey',
                target: ''
            }
        };
        
        // Reset title inputs
        $('.mc-funnel-step-title-input').each(function() {
            const slug = $(this).closest('.mc-funnel-step-admin').data('slug');
            if (defaults.titles[slug]) {
                $(this).val(defaults.titles[slug]);
            }
        });
        
        // Reset placeholder fields
        $('input[name="placeholder_enabled"]').prop('checked', defaults.placeholder.enabled);
        $('#placeholder_title').val(defaults.placeholder.title);
        $('#placeholder_description').val(defaults.placeholder.description);
        $('#placeholder_target').val(defaults.placeholder.target);
        
        updatePlaceholderFieldVisibility();
        updatePreview();
        
        showNotice('Form reset to defaults. Click "Save Configuration" to apply changes.', 'info');
    }
    
    function refreshPreview() {
        const $preview = $('#mc-funnel-preview');
        $preview.addClass('loading');
        
        // In a real implementation, you'd make an AJAX call to get the updated shortcode output
        // For now, we'll just simulate a refresh
        setTimeout(() => {
            $preview.removeClass('loading');
            $preview.find('.preview-note').remove();
        }, 1000);
    }
    
    function showNotice(message, type = 'info', autoDismiss = true) {
        const $notices = $('#mc-funnel-admin-notices');
        
        // Remove existing notices
        $notices.empty();
        
        const noticeClass = type === 'success' ? 'notice-success' : 
                           type === 'error' ? 'notice-error' : 
                           type === 'warning' ? 'notice-warning' : 'notice-info';
        
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $notices.append($notice);
        
        // Handle dismiss button
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(() => $notice.remove());
        });
        
        // Auto-dismiss after 5 seconds for success/info
        if (autoDismiss && (type === 'success' || type === 'info')) {
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }
        
        // Scroll to notices
        $('html, body').animate({
            scrollTop: $notices.offset().top - 100
        }, 300);
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
})(jQuery);

// Add some additional CSS for admin interface
$(document).ready(function() {
    if (!$('#mc-funnel-admin-dynamic-styles').length) {
        $('<style id="mc-funnel-admin-dynamic-styles">').html(`
            .mc-funnel-step-placeholder {
                height: 60px;
                background: #f0f0f1;
                border: 2px dashed #c3c4c7;
                border-radius: 4px;
                margin: 8px 0;
            }
            
            #mc-funnel-preview.updating {
                opacity: 0.6;
                pointer-events: none;
            }
            
            #mc-funnel-preview.loading {
                position: relative;
                opacity: 0.6;
            }
            
            #mc-funnel-preview.loading:after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 20px;
                height: 20px;
                border: 2px solid #0073aa;
                border-top: 2px solid transparent;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
            
            .preview-note {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 8px 12px;
                margin-top: 12px;
                font-size: 13px;
                color: #856404;
            }
        `).appendTo('head');
    }
});
</function_results>

I notice there are some escaped newlines in the JavaScript. Let me fix those:

<function_calls>
<invoke name="edit_files">
<parameter name="title">Fix escaped newlines in funnel-admin.js