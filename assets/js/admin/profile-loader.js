document.addEventListener('DOMContentLoaded', function () {
    // Function to load profile data
    function loadProfileData() {
        fetch('loadprofilepicture.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateProfileElements(data.data);
                } else {
                    console.error('Failed to load profile:', data.message);
                    setDefaultProfile();
                }
            })
            .catch(error => {
                console.error('Error loading profile:', error);
                setDefaultProfile();
            });
    }

    // Update all profile elements on the page
    function updateProfileElements(profileData) {

        document.querySelectorAll('.profile-picture, .user-avatar').forEach(img => {
            img.src = profileData.profile_picture;
            img.alt = profileData.name + "'s profile picture";
        });

        // Update user names
        document.querySelectorAll('.user-name, .profile-name').forEach(el => {
            el.textContent = profileData.name;
        });

        // Update sidebar profile
        const sidebarProfile = document.querySelector('.sidebar-profile');
        if (sidebarProfile) {
            const img = sidebarProfile.querySelector('img');
            const name = sidebarProfile.querySelector('.profile-name');
            if (img) img.src = profileData.profile_picture;
            if (name) name.textContent = profileData.name;
        }
    }

    // Set default profile if loading fails
    function setDefaultProfile() {
        const defaultPic = '../../images/default-profile.png';
        document.querySelectorAll('.profile-picture, .user-avatar').forEach(img => {
            if (!img.src.includes(defaultPic)) {
                img.src = defaultPic;
            }
        });
    }

    // Load profile data initially
    loadProfileData();

    // Refresh profile data every 5 minutes
    setInterval(loadProfileData, 300000);
});