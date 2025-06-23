$(document).ready(function () {
    // Tab switching functionality
    $('.tab-btn').click(function () {
        const tabId = $(this).data('tab');

        // Update active tab button
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');

        // Show corresponding tab content
        $('.tab-content').removeClass('active');
        $(`#${tabId}-tab`).addClass('active');
    });

    // Load current settings
    loadSettings();

    // Form submission handlers
    $('#general-settings-form').submit(function (e) {
        e.preventDefault();
        saveGeneralSettings();
    });

    $('#notification-settings-form').submit(function (e) {
        e.preventDefault();
        saveSettings('notifications', $(this).serialize());
    });

    $('#security-settings-form').submit(function (e) {
        e.preventDefault();

        // Debug: Log the serialized form data before sending
        const formData = $(this).serialize();
        console.log('Serialized form data:', formData);

        saveSettings('security', formData);
    });


    $('#backup-settings-form').submit(function (e) {
        e.preventDefault();
        saveSettings('backup', $(this).serialize());
    });




    // Function to load current settings
    function loadSettings() {
        $.ajax({
            url: 'settings.php',
            type: 'GET',
            data: { action: 'get_user_profile' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Set profile picture
                    if (response.data.profile_picture) {
                        $('#profile-preview').attr('src', response.data.profile_picture);
                    }

                    // Set email and phone
                    $('#email').val(response.data.email || '');
                    $('#phone').val(response.data.phone || '');

                    // Set timezone
                    $('#timezone').val(response.data.timezone || 'UTC');
                } else {
                    showStatusMessage(response.message, 'error');
                }
            },
            error: function () {
                showStatusMessage('Failed to load profile settings. Please try again.', 'error');
            }
        });
    }

    // Profile picture preview
    $('#profile-picture').change(function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
                $('#profile-preview').attr('src', event.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    // Function to save settings
    function saveGeneralSettings(type, formData) {
        // For profile picture upload, we need FormData
        const form = $('#general-settings-form')[0];
        const data = new FormData(form);
        data.append('action', 'save_user_profile');

        $.ajax({
            url: 'settings.php',
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showStatusMessage('Profile updated successfully!', 'success');
                    // Update profile picture in header if changed
                    if (response.data.profile_picture) {
                        $('.user-info .profile-pic').attr('src', response.data.profile_picture);
                    }
                } else {
                    showStatusMessage(response.message, 'error');
                }
            },
            error: function () {
                showStatusMessage('Failed to update profile. Please try again.', 'error');
            }
        });
    }

    // Function to save settings
    // Function to save settings
    function saveSettings(type, formData) {
        $.ajax({
            url: 'settings.php',
            type: 'POST',
            data: formData + '&action=save_settings&type=' + type, // Ensure 'type' is appended here
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if (type === 'security') {
                        document.getElementById('password-change').value = "";
                        document.getElementById('password-confirm').value = "";
                    }
                    showStatusMessage('Settings saved successfully!', 'success');

                } else {
                    showStatusMessage(response.message, 'error');
                }
            },
            error: function (xhr, status, error) {
                showStatusMessage(`Error saving settings: ${error}`, 'error');
            }
        });
    }


    // Function to show status messages
    function showStatusMessage(message, type) {
        const $status = $('#settings-status');
        $status.removeClass('success error').addClass(type).text(message).fadeIn();

        setTimeout(function () {
            $status.fadeOut();
        }, 5000);
    }
});
