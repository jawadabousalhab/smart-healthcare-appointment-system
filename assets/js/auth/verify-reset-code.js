


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
        window.location.href = 'forgot-password.html';
        return;
    }

    // Set email in hidden field and display
    document.getElementById('email').value = email;
    document.getElementById('verificationEmail').textContent = `Enter the verification code sent to ${email}`;

    // Form submission
    const form = document.getElementById('verifyCodeForm');
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const code = document.getElementById('verificationCode').value.trim();
        const email = document.getElementById('email').value;

        if (code.length !== 6) {
            showStatus('Please enter a valid 6-digit code', 'error');
            return;
        }

        verifyCode(email, code);
    });

    // Resend code link
    document.getElementById('resendLink').addEventListener('click', function (e) {
        e.preventDefault();
        resendVerificationCode(email);
    });
});

function verifyCode(email, code) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerHTML = '<div class="verification-loading"><i class="fas fa-spinner fa-spin"></i><p>Verifying code...</p></div>';

    fetch('verify-reset-code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `email=${encodeURIComponent(email)}&code=${encodeURIComponent(code)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatus(data.message, 'success');
                // Redirect to reset password page after 2 seconds
                setTimeout(() => {
                    window.location.href = `reset-password.html?token=${encodeURIComponent(data.token)}`;
                }, 2000);
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatus('An error occurred. Please try again.', 'error');
        });
}

function resendVerificationCode(email) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerHTML = '<div class="verification-loading"><i class="fas fa-spinner fa-spin"></i><p>Sending new code...</p></div>';

    fetch('resend-reset-code.php', {
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
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatus('An error occurred. Please try again.', 'error');
        });
}

function showStatus(message, type) {
    const statusDiv = document.getElementById('statusMessage');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const colorClass = type === 'success' ? 'verification-success' : 'verification-error';

    statusDiv.innerHTML = `
        <div class="${colorClass}">
            <i class="fas ${icon}"></i>
            <p>${message}</p>
        </div>
    `;
}