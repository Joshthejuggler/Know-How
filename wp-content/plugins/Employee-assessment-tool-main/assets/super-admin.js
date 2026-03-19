jQuery(document).ready(function ($) {
    'use strict';

    // Modal handling
    function openModal(modalId) {
        $(modalId).fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeModal(modalId) {
        $(modalId).fadeOut(200);
        $('body').css('overflow', 'auto');
    }

    // Open create employer modal
    $('.mc-btn-create-employer').on('click', function () {
        $('#mc-create-employer-form')[0].reset();
        openModal('#mc-create-employer-modal');
    });

    // Close modal on overlay click or close button
    $('.mc-modal-overlay, .mc-modal-close').on('click', function () {
        closeModal('#mc-create-employer-modal');
    });

    // Prevent modal close when clicking inside modal content
    $('.mc-modal-content').on('click', function (e) {
        e.stopPropagation();
    });

    // Close modal on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('#mc-create-employer-modal');
            closeModal('#mc-reassign-modal');
        }
    });

    // Create employer
    $('#mc-submit-employer').on('click', function () {
        const $btn = $(this);
        const $form = $('#mc-create-employer-form');

        // Basic validation
        const email = $form.find('#employer_email').val().trim();
        if (!email) {
            alert('Email address is required');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).html('<span class="mc-loading"></span> Creating...');

        const formData = {
            action: 'mc_create_employer',
            nonce: mcSuperAdmin.nonce,
            employer_email: email,
            employer_first_name: $form.find('#employer_first_name').val().trim(),
            employer_last_name: $form.find('#employer_last_name').val().trim(),
            company_name: $form.find('#company_name').val().trim(),
            send_invite: $form.find('#send_invite').is(':checked') ? 'true' : 'false'
        };

        $.ajax({
            url: mcSuperAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    closeModal('#mc-create-employer-modal');
                    // Reload page to show new employer
                    window.location.reload();
                } else {
                    alert(response.data.message || 'An error occurred');
                    $btn.prop('disabled', false).html('Create Employer');
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                $btn.prop('disabled', false).html('Create Employer');
            }
        });
    });

    // Send invite
    $('.mc-btn-send-invite').on('click', function () {
        const $btn = $(this);
        const employerId = $btn.data('employer-id');
        const employerEmail = $btn.data('employer-email');

        if (!confirm(`Send invitation email to ${employerEmail}?`)) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: mcSuperAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mc_send_employer_invite',
                nonce: mcSuperAdmin.nonce,
                employer_id: employerId
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || 'Failed to send invitation');
                }
                $btn.prop('disabled', false);
            },
            error: function () {
                alert('Network error. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    // View employer details
    $('.mc-btn-view').on('click', function () {
        const employerId = $(this).data('employer-id');
        // Navigate to user edit page
        window.location.href = `user-edit.php?user_id=${employerId}`;
    });

    // Edit employer
    $('.mc-btn-edit').on('click', function () {
        const employerId = $(this).data('employer-id');
        // Navigate to user edit page
        window.location.href = `user-edit.php?user_id=${employerId}`;
    });

    // Delete employer
    $('.mc-btn-delete').on('click', function () {
        const $btn = $(this);
        const employerId = $btn.data('employer-id');
        const $row = $btn.closest('tr');
        const companyName = $row.find('td:first strong').text();

        if (!confirm(`Are you sure you want to delete ${companyName}? This action cannot be undone.`)) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: mcSuperAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mc_delete_employer',
                nonce: mcSuperAdmin.nonce,
                employer_id: employerId
            },
            success: function (response) {
                if (response.success) {
                    // Fade out and remove row
                    $row.fadeOut(300, function () {
                        $(this).remove();

                        // Check if table is now empty
                        if ($('.mc-employers-table tbody tr').length === 0) {
                            window.location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || 'Failed to delete employer');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    // Reassign company
    $('.mc-btn-reassign').on('click', function () {
        const employerId = $(this).data('employer-id');
        const companyName = $(this).data('company-name');
        $('#mc-reassign-employer-id').val(employerId);
        $('#mc-reassign-company-name').text(companyName);
        $('#mc-reassign-email').val('');
        openModal('#mc-reassign-modal');
    });

    // Close reassign modal
    $('#mc-reassign-modal .mc-modal-overlay, #mc-reassign-modal .mc-modal-close').on('click', function () {
        closeModal('#mc-reassign-modal');
    });

    $('#mc-submit-reassign').on('click', function () {
        const $btn = $(this);
        const employerId = $('#mc-reassign-employer-id').val();
        const newEmail = $('#mc-reassign-email').val().trim();

        if (!newEmail) {
            alert('Email address is required.');
            return;
        }

        if (!confirm('Are you sure you want to reassign this company to ' + newEmail + '?\n\nIf the target user already owns a company, the two companies (and their employees) will be swapped.\n\nThis will transfer all company data and employee links.')) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="mc-loading"></span> Reassigning...');

        $.ajax({
            url: mcSuperAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mc_reassign_company',
                nonce: mcSuperAdmin.nonce,
                employer_id: employerId,
                new_email: newEmail
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    closeModal('#mc-reassign-modal');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'An error occurred.');
                    $btn.prop('disabled', false).html('Reassign Company');
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                $btn.prop('disabled', false).html('Reassign Company');
            }
        });
    });

    // Search employers
    $('#mc-employer-search').on('keyup', function () {
        const searchTerm = $(this).val().toLowerCase().trim();

        $('.mc-employers-table tbody tr:not(.mc-details-row)').each(function () {
            const $row = $(this);
            const rowText = $row.text().toLowerCase();
            const $detailsRow = $row.next('.mc-details-row');

            if (rowText.includes(searchTerm)) {
                $row.show();
                // Keep details row hidden unless it was already open, but we need to ensure it's not orphaned
                if ($row.find('.mc-accordion-toggle').hasClass('active')) {
                    $detailsRow.show();
                }
            } else {
                $row.hide();
                $detailsRow.hide();
            }
        });
    });

    // Delete employee
    $(document).on('click', '.mc-btn-delete-employee', function () {
        const $btn = $(this);
        const employeeId = $btn.data('employee-id');
        const employeeName = $btn.data('employee-name');

        if (!confirm('Are you sure you want to permanently delete ' + employeeName + '? This will remove the user and all their data.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: mcSuperAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mc_delete_employee',
                nonce: mcSuperAdmin.nonce,
                employee_id: employeeId
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Failed to delete employee.');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    // Accordion Toggle
    $(document).on('click', '.mc-accordion-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const $row = $btn.closest('tr');
        const $detailsRow = $row.next('.mc-details-row');

        $btn.toggleClass('active');
        $detailsRow.toggle();
    });
});
