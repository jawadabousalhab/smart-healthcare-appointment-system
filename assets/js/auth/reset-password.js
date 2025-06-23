// Mobile menu toggle

document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.querySelector('.hidden.md\\:flex');

    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('mobile-menu-visible');
        });
    }

    // Get token from URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        showStatus('Invalid password reset link. Please request a new one.', 'error');
        document.getElementById('resetPasswordForm').style.display = 'none';
        return;
    }

    // Set token in hidden field
    document.getElementById('resetToken').value = token;

    const form = document.getElementById('resetPasswordForm');
    const statusMessage = document.getElementById('statusMessage');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword.length < 8) {
            showStatus('Password must be at least 8 characters', 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            showStatus('Passwords do not match', 'error');
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';

        // Send request to server
        fetch('reset-password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `token=${encodeURIComponent(token)}&newPassword=${encodeURIComponent(newPassword)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(data.message, 'success');
                    // Redirect to login after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 3000);
                } else {
                    showStatus(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatus('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            });
    });

    function showStatus(message, type) {
        statusMessage.innerHTML = `
            <div class="alert alert-${type}">
                ${message}
            </div>
        `;

        // Scroll to status message
        statusMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});