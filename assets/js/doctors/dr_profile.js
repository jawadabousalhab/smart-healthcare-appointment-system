document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const profileContainer = document.getElementById('profile-container');
    const editProfileBtn = document.getElementById('edit-profile-btn');
    const editProfileModal = document.getElementById('edit-profile-modal');
    const changePasswordBtn = document.getElementById('change-password-btn');
    const changePasswordModal = document.getElementById('change-password-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const profileForm = document.getElementById('profile-form');
    const passwordForm = document.getElementById('password-form');
    const changePhotoBtn = document.getElementById('change-photo-btn');
    const photoUpload = document.getElementById('photo-upload');
    const profilePhotoLarge = document.getElementById('profile-photo-large');
    const profilePic = document.getElementById('profile-pic');

    // Load profile data
    loadProfileData();

    // Event Listeners
    editProfileBtn.addEventListener('click', openEditProfileModal);
    changePasswordBtn.addEventListener('click', openChangePasswordModal);

    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });

    profileForm.addEventListener('submit', saveProfile);
    passwordForm.addEventListener('submit', changePassword);

    changePhotoBtn.addEventListener('click', function () {
        photoUpload.click();
    });

    photoUpload.addEventListener('change', uploadProfilePhoto);

    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === editProfileModal) {
            closeAllModals();
        }
        if (event.target === changePasswordModal) {
            closeAllModals();
        }
    });

    // Functions
    function loadProfileData() {
        fetch('dr_profile.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const profile = data.data.profile;
                    const stats = data.data.stats;

                    // Update profile photo
                    if (profile.profile_picture) {
                        const photoPath = `../../uploads/profiles/doctors/${profile.profile_picture}`;
                        profilePhotoLarge.src = photoPath;
                        profilePic.src = photoPath;
                    }

                    // Update personal info
                    document.getElementById('full-name').textContent = profile.name;
                    document.getElementById('email').textContent = profile.email;
                    document.getElementById('phone').textContent = profile.phone_number || 'Not provided';
                    document.getElementById('address').textContent = profile.address || 'Not provided';
                    document.getElementById('specialization').textContent = profile.specialization || 'Not specified';
                    document.getElementById('doctor-name').textContent = profile.name;

                    // Update stats
                    document.getElementById('total-patients').textContent = stats.total_patients;
                    document.getElementById('total-appointments').textContent = stats.total_appointments;

                    // Populate edit form
                    if (profile.name) {
                        const names = profile.name.split(' ');
                        document.getElementById('edit-first-name').value = names[0] || '';
                        document.getElementById('edit-last-name').value = names.slice(1).join(' ') || '';
                    }
                    document.getElementById('edit-email').value = profile.email;
                    document.getElementById('edit-phone').value = profile.phone_number;
                    document.getElementById('edit-address').value = profile.address;
                    document.getElementById('edit-specialization').value = profile.specialization;
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Failed to load profile data: ' + error.message);
            });
    }

    function openEditProfileModal() {
        editProfileModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function openChangePasswordModal() {
        changePasswordModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeAllModals() {
        editProfileModal.style.display = 'none';
        changePasswordModal.style.display = 'none';
        document.body.style.overflow = '';
    }

    function saveProfile(e) {
        e.preventDefault();
        const phone = document.getElementById('edit-phone').value;
        const countrycode = document.getElementById('country-code').value;
        const formData = {
            first_name: document.getElementById('edit-first-name').value,
            last_name: document.getElementById('edit-last-name').value,
            email: document.getElementById('edit-email').value,
            phone: phone,
            countrycode: countrycode,
            phone_number: countrycode + phone,
            address: document.getElementById('edit-address').value,
            specialization: document.getElementById('edit-specialization').value
        };

        fetch('dr_profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeAllModals();
                    loadProfileData();
                    showSuccess('Profile updated successfully');
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Error updating profile: ' + error.message);
            });
    }

    function changePassword(e) {
        e.preventDefault();

        const formData = {
            current_password: document.getElementById('current-password').value,
            new_password: document.getElementById('new-password').value,
            confirm_password: document.getElementById('confirm-password').value
        };

        fetch('dr_profile.php?action=change_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeAllModals();
                    passwordForm.reset();
                    showSuccess('Password changed successfully');
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Error changing password: ' + error.message);
            });
    }

    function uploadProfilePhoto() {
        const file = photoUpload.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('profile_photo', file);

        fetch('dr_profile.php?action=update_photo', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const photoPath = `../../uploads/profiles/doctors/${data.photo_path}`;
                    profilePhotoLarge.src = photoPath;
                    profilePic.src = photoPath;
                    showSuccess('Profile photo updated');
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Error uploading photo: ' + error.message);
            });
    }

    function showError(message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;

        profileContainer.appendChild(errorElement);

        setTimeout(() => {
            errorElement.remove();
        }, 5000);
    }

    function showSuccess(message) {
        const successElement = document.createElement('div');
        successElement.className = 'success-message';
        successElement.textContent = message;

        profileContainer.appendChild(successElement);

        setTimeout(() => {
            successElement.remove();
        }, 5000);
    }
});