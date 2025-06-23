
document.addEventListener('DOMContentLoaded', function () {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.querySelector('.hidden.md\\:flex');

    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('mobile-menu-visible');
        });
    }
    const form = document.getElementById('forgotPasswordForm');
    const statusMessage = document.getElementById('statusMessage');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();

        if (!email) {
            showStatus('Please enter your email address', 'error');
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        // Send request to server
        fetch('../../views/auth/forgot-password.php', {  // Changed from '../../views/auth/forgot-password.php'
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(data.message, 'success');
                    form.reset();

                    // Check if we have a redirect URL
                    if (data.redirect) {
                        // Redirect after 2 seconds to show success message
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
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
                submitBtn.textContent = 'Send Reset Code';
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