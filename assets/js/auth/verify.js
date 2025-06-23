


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
    // Get email from URL
    const urlParams = new URLSearchParams(window.location.search);
    const email = urlParams.get('email');

    if (!email) {
        // No email provided, redirect to register
        window.location.href = 'register.php';
        return;
    }

    // Display email to user
    document.getElementById('verificationEmail').textContent = `Enter the verification code sent to ${email}`;

    // Setup form submission
    const verificationForm = document.getElementById('verificationForm');
    verificationForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const code = document.getElementById('verificationCode').value.trim();

        if (code.length !== 6) {
            showVerificationStatus('Please enter a valid 6-digit code', 'error');
            return;
        }

        verifyCode(email, code);
    });

    // Setup resend link
    document.getElementById('resendLink').addEventListener('click', function (e) {
        e.preventDefault();
        resendVerificationCode(email);
    });
});

function verifyCode(email, code) {
    const statusDiv = document.getElementById('verificationStatus');
    statusDiv.innerHTML = '<div class="verification-loading"><i class="fas fa-spinner fa-spin"></i><p>Verifying code...</p></div>';

    fetch('../../views/auth/verify.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `code=${encodeURIComponent(code)}&email=${encodeURIComponent(email)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showVerificationStatus(data.message, 'success');
                // Redirect to login after 3 seconds
                setTimeout(() => {
                    window.location.href = '../../views/auth/login.html';
                }, 3000);
            } else {
                showVerificationStatus(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showVerificationStatus('An error occurred during verification. Please try again.', 'error');
        });
}

function resendVerificationCode(email) {
    const statusDiv = document.getElementById('verificationStatus');
    statusDiv.innerHTML = '<div class="verification-loading"><i class="fas fa-spinner fa-spin"></i><p>Sending new code...</p></div>';

    fetch('../../views/auth/resend_verification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `email=${encodeURIComponent(email)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showVerificationStatus(data.message, 'success');
            } else {
                showVerificationStatus(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showVerificationStatus('An error occurred while resending the code. Please try again.', 'error');
        });
}

function showVerificationStatus(message, type) {
    const statusDiv = document.getElementById('verificationStatus');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const colorClass = type === 'success' ? 'verification-success' : 'verification-error';

    statusDiv.innerHTML = `
        <div class="${colorClass}">
            <i class="fas ${icon}"></i>
            <p>${message}</p>
        </div>
    `;
}