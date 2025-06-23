<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../utils/mailer.php';

$response = [
    'success' => false,
    'message' => 'Invalid request'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $response['message'] = 'Email is required';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDO();
        $pdo->exec("SET time_zone = '+00:00'");

        // Check if user exists with this email
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            date_default_timezone_set('UTC');  // or your preferred time zone

            // Generate 6-digit verification code
            $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

            $stmt = $pdo->prepare("INSERT INTO password_resets 
                      (user_id, reset_code, expires_at) 
                      VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $verification_code, $expires_at]);


            // Store user email in session for verification step
            $_SESSION['reset_email'] = $email;

            // Send verification code email
            $subject = "Your Password Reset Verification Code";
            $message = "Dear {$user['name']},<br><br>";
            $message .= "We received a request to reset your password. Your verification code is:<br><br>";
            $message .= "<strong style='font-size: 1.5rem; letter-spacing: 0.5rem;'>$verification_code</strong><br><br>";
            $message .= "This code will expire in 1 hour. If you didn't request this, please ignore this email.<br><br>";
            $message .= "Best regards,<br>Smart Health Team";

            if (sendEmail($email, $subject, $message)) {
                $response['success'] = true;
                $response['message'] = 'Verification code sent to your email';
                $response['redirect'] = 'verify-reset-code.html?email=' . urlencode($email);
            } else {
                $response['message'] = 'Failed to send verification code. Please try again later.';
            }
        } else {
            // Show generic message for security
            $response['message'] = 'If this email exists in our system, you will receive a verification code.';
        }
    } catch (PDOException $e) {
        error_log("Forgot password error: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
    }
}

echo json_encode($response);
