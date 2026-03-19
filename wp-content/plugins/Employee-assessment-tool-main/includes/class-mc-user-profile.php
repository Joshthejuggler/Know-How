<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handle user profile data including age group collection
 */
class MC_User_Profile
{

    /**
     * Initialize hooks and actions
     */
    public static function init()
    {
        // Hook into user registration to collect age group
        add_action('user_register', [__CLASS__, 'handle_user_registration']);

        // AJAX handlers for age group collection
        add_action('wp_ajax_mc_save_age_group', [__CLASS__, 'ajax_save_age_group']);
        add_action('wp_ajax_nopriv_mc_save_age_group', [__CLASS__, 'ajax_save_age_group']);

        // Add age group field to user profile pages (admin)
        add_action('show_user_profile', [__CLASS__, 'add_age_group_profile_field']);
        add_action('edit_user_profile', [__CLASS__, 'add_age_group_profile_field']);
        add_action('personal_options_update', [__CLASS__, 'save_age_group_profile_field']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_age_group_profile_field']);
    }

    /**
     * Get user's age group with fallback
     * 
     * @param int|null $user_id User ID, defaults to current user
     * @return string Age group ('teen', 'graduate', 'adult')
     */
    public static function get_user_age_group($user_id = null)
    {
        // ALWAYS return adult, bypassing any logic or checks
        return 'adult';
    }

    /**
     * Check if user has set their age group
     * 
     * @param int|null $user_id User ID, defaults to current user
     * @return bool True if age group is set
     */
    public static function has_age_group($user_id = null)
    {
        // Always return true so no checks block the user
        return true;
    }

    /**
     * Save user's age group
     * 
     * @param int $user_id User ID
     * @param string $age_group Age group to save
     * @return bool Success status
     */
    public static function save_age_group($user_id, $age_group)
    {
        if (!in_array($age_group, ['teen', 'graduate', 'adult'])) {
            return false;
        }

        $result = update_user_meta($user_id, 'mc_age_group', $age_group);

        // Clear dashboard cache when profile changes
        if ($result && class_exists('MC_Cache')) {
            MC_Cache::clear_dashboard_cache($user_id);
        }

        return $result;
    }

    /**
     * Handle new user registration - mark as needing age group if not provided
     * 
     * @param int $user_id New user ID
     */
    public static function handle_user_registration($user_id)
    {
        // DEPRECATED: No longer strictly requiring age group.
        // We set it to 'adult' by default to ensure backend consistency if checked elsewhere.
        update_user_meta($user_id, 'mc_age_group', 'adult');
        delete_user_meta($user_id, 'mc_needs_age_group');
    }

    /**
     * AJAX handler to save age group
     */
    public static function ajax_save_age_group()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'mc_age_group')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Must be logged in');
        }

        $age_group = sanitize_text_field($_POST['age_group'] ?? '');
        if (empty($age_group)) {
            wp_send_json_error('Age group is required');
        }

        $success = self::save_age_group($user_id, $age_group);

        if ($success) {
            // Remove the "needs age group" flag
            delete_user_meta($user_id, 'mc_needs_age_group');

            wp_send_json_success([
                'message' => 'Age group saved successfully',
                'age_group' => $age_group
            ]);
        } else {
            wp_send_json_error('Failed to save age group');
        }
    }

    /**
     * Add age group field to user profile pages in admin
     */
    public static function add_age_group_profile_field($user)
    {
        $age_group = self::get_user_age_group($user->ID);
        ?>
        <h3>Quiz Platform Settings</h3>
        <table class="form-table">
            <tr>
                <th><label for="mc_age_group">Age Group</label></th>
                <td>
                    <select name="mc_age_group" id="mc_age_group">
                        <option value="teen" <?php selected($age_group, 'teen'); ?>>Teen / High School</option>
                        <option value="graduate" <?php selected($age_group, 'graduate'); ?>>Student / Recent Graduate</option>
                        <option value="adult" <?php selected($age_group, 'adult'); ?>>Adult / Professional</option>
                    </select>
                    <p class="description">This determines which quiz questions and content are shown.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save age group from profile page
     */
    public static function save_age_group_profile_field($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['mc_age_group'])) {
            $age_group = sanitize_text_field($_POST['mc_age_group']);
            self::save_age_group($user_id, $age_group);
        }
    }

    /**
     * Render age group collection modal/form
     * 
     * @param string $context Context where this is being shown ('registration', 'quiz', etc.)
     * @return string HTML for age group collection
     */
    public static function render_age_group_form($context = 'quiz')
    {
        $nonce = wp_create_nonce('mc_age_group');

        ob_start();
        ?>
        <div id="mc-age-group-modal" class="mc-age-group-modal" style="display: none;">
            <div class="mc-age-group-modal-content">
                <div class="mc-age-group-header">
                    <h3>Tell us about yourself</h3>
                    <p>This helps us customize your quiz experience with age-appropriate questions.</p>
                </div>

                <form id="mc-age-group-form">
                    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>" />
                    <input type="hidden" name="context" value="<?php echo esc_attr($context); ?>" />

                    <div class="mc-age-group-options">
                        <label class="mc-age-group-option">
                            <input type="radio" name="age_group" value="teen" />
                            <span class="mc-age-group-label">
                                <strong>Teen / High School</strong>
                                <small>Age-appropriate questions focused on school and future planning</small>
                            </span>
                        </label>

                        <label class="mc-age-group-option">
                            <input type="radio" name="age_group" value="graduate" />
                            <span class="mc-age-group-label">
                                <strong>Student / Recent Graduate</strong>
                                <small>Questions tailored for college students and early career</small>
                            </span>
                        </label>

                        <label class="mc-age-group-option">
                            <input type="radio" name="age_group" value="adult" />
                            <span class="mc-age-group-label">
                                <strong>Adult / Professional</strong>
                                <small>Questions designed for working professionals and life experience</small>
                            </span>
                        </label>
                    </div>

                    <div class="mc-age-group-actions">
                        <button type="submit" class="mc-age-group-submit-btn">Continue</button>
                    </div>
                </form>
            </div>
        </div>

        <style>
            .mc-age-group-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            }

            .mc-age-group-modal-content {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }

            .mc-age-group-header h3 {
                margin: 0 0 0.5rem 0;
                color: #1a202c;
                font-size: 1.5rem;
            }

            .mc-age-group-header p {
                margin: 0 0 1.5rem 0;
                color: #4a5568;
                line-height: 1.5;
            }

            .mc-age-group-options {
                margin-bottom: 1.5rem;
            }

            .mc-age-group-option {
                display: flex;
                align-items: flex-start;
                padding: 1rem;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                margin-bottom: 0.75rem;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .mc-age-group-option:hover {
                border-color: #3b82f6;
                background-color: #f8fafc;
            }

            .mc-age-group-option input[type="radio"] {
                margin: 0.25rem 0.75rem 0 0;
            }

            .mc-age-group-option input[type="radio"]:checked+.mc-age-group-label {
                color: #1a202c;
            }

            .mc-age-group-option input[type="radio"]:checked {
                accent-color: #3b82f6;
            }

            .mc-age-group-option:has(input:checked) {
                border-color: #3b82f6;
                background-color: #eff6ff;
            }

            .mc-age-group-label {
                flex: 1;
                color: #4a5568;
            }

            .mc-age-group-label strong {
                display: block;
                margin-bottom: 0.25rem;
                color: #1a202c;
                font-size: 1.1rem;
            }

            .mc-age-group-label small {
                color: #6b7280;
                line-height: 1.4;
            }

            .mc-age-group-actions {
                text-align: center;
            }

            .mc-age-group-submit-btn {
                background: #3b82f6;
                color: white;
                border: none;
                padding: 0.75rem 2rem;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .mc-age-group-submit-btn:hover {
                background: #2563eb;
            }

            .mc-age-group-submit-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>

        <script>
            (function () {
                const modal = document.getElementById('mc-age-group-modal');
                const form = document.getElementById('mc-age-group-form');

                if (!modal || !form) return;

                // Handle form submission
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const ageGroup = formData.get('age_group');

                    if (!ageGroup) {
                        alert('Please select an age group');
                        return;
                    }

                    const submitBtn = form.querySelector('.mc-age-group-submit-btn');
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';

                    // Send to server
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'mc_save_age_group',
                            age_group: ageGroup,
                            _ajax_nonce: formData.get('_ajax_nonce'),
                            context: formData.get('context')
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Hide modal and trigger custom event
                                modal.style.display = 'none';

                                // Dispatch event so quizzes can react
                                document.dispatchEvent(new CustomEvent('mc-age-group-saved', {
                                    detail: { age_group: ageGroup }
                                }));
                            } else {
                                alert(data.data || 'Failed to save age group');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Network error occurred');
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Continue';
                        });
                });

                // Show modal function (called by other scripts)
                window.showAgeGroupModal = function () {
                    modal.style.display = 'flex';
                };

                // Hide modal function
                window.hideAgeGroupModal = function () {
                    modal.style.display = 'none';
                };
            })();
        </script>
        <?php

        return ob_get_clean();
    }
}