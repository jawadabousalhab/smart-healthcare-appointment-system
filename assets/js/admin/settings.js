document.addEventListener('DOMContentLoaded', function () {
    // Tab switching functionality
    const tabs = document.querySelectorAll('.settings-tab');
    const contents = document.querySelectorAll('.settings-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('border-indigo-500', 'text-indigo-600'));
            tabs.forEach(t => t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'));

            // Add active class to clicked tab
            this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            this.classList.add('border-indigo-500', 'text-indigo-600');

            // Hide all content
            contents.forEach(content => content.classList.add('hidden'));

            // Show selected content
            const targetId = this.id.replace('-tab', '-settings');
            document.getElementById(targetId).classList.remove('hidden');
        });
    });

    // Load admin profile data
    fetch('settings.php?action=get_admin_profile')
        .then(response => response.json())
        .then(data => {
            if (data) {
                document.getElementById('adminName').textContent = data.name;
                document.getElementById('currentUserName').textContent = data.name;
                document.getElementById('name').value = data.name;
                document.getElementById('email').value = data.email;
                document.getElementById('phone').value = data.phone_number || '';

                if (data.profile_picture) {
                    const imgSrc = data.profile_picture + '?t=' + new Date().getTime(); // Cache-busting
                    document.getElementById('avatarPreview').src = data.profile_picture + '?t=' + Date.now();
                    document.getElementById('UserAvatar').src = imgSrc;
                    document.getElementById('currentUserAvatar').src = imgSrc;
                }
            }
        })
        .catch(error => console.error('Error loading admin profile:', error));

    // Profile picture preview
    document.getElementById('avatar').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
                document.getElementById('avatarPreview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Form submissions
    document.getElementById('profileForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('settings.php?action=update_profile', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Profile updated successfully', 'success');
                    // Update displayed name if changed
                    document.getElementById('currentUserName').textContent = formData.get('name');
                    if (data.profile_picture) {
                        document.getElementById('avatarPreview').src = data.profile_picture + '?t=' + new Date().getTime();
                        document.getElementById('currentUserAvatar').src = data.profile_picture + '?t=' + new Date().getTime();
                    }
                } else {
                    showMessage(data.message || 'Failed to update profile', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred', 'error');
            });
    });

    document.getElementById('passwordForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('settings.php?action=update_password', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Password updated successfully', 'success');
                    this.reset();
                } else {
                    showMessage(data.message || 'Failed to update password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred', 'error');
            });
    });

    // System settings form
    document.getElementById('systemForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('settings.php?action=update_system_settings', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('System settings updated successfully', 'success');
                } else {
                    showMessage(data.message || 'Failed to update settings', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred', 'error');
            });
    });

    // Notification settings form
    document.getElementById('notificationsForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('settings.php?action=update_notification_settings', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Notification preferences updated successfully', 'success');
                } else {
                    showMessage(data.message || 'Failed to update preferences', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred', 'error');
            });
    });

    // Show message function
    function showMessage(message, type) {
        const container = document.getElementById('messageContainer');
        container.innerHTML = `
            <div class="${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'} px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">${type === 'success' ? 'Success!' : 'Error!'}</strong>
                <span class="block sm:inline">${message}</span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                    <svg class="fill-current h-6 w-6 ${type === 'success' ? 'text-green-500' : 'text-red-500'}" 
                        role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>
        `;
        container.classList.remove('hidden');

        // Hide message after 5 seconds
        setTimeout(() => {
            container.classList.add('hidden');
        }, 5000);
    }
});