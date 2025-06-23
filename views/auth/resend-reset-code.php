<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once  '../../utils/mailer.php';

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

        // Check if user exists
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Invalidate any existing codes
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
            $stmt->execute([$user['user_id']]);

            // Generate new 6-digit verification code
            $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

            // Store new verification code
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, reset_code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $new_code, $expires_at]);

            // Send verification code email
            $subject = "Your New Password Reset Verification Code";
            $message = "Dear {$user['name']},<br><br>";
            $message .= "Your new verification code is:<br><br>";
            $message .= "<strong style='font-size: 1.5rem; letter-spacing: 0.5rem;'>$new_code</strong><br><br>";
            $message .= "This code will expire in 1 hour. If you didn't request this, please ignore this email.<br><br>";
            $message .= "Best regards,<br>Smart Health Team";

            if (sendEmail($email, $subject, $message)) {
                $response['success'] = true;
                $response['message'] = 'New verification code sent to your email.';
            } else {
                $response['message'] = 'Failed to send new verification code. Please try again later.';
            }
        } else {
            $response['message'] = 'If this email exists in our system, you will receive a verification code.';
        }
    } catch (PDOException $e) {
        error_log("Resend reset code error: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
    }
}

echo json_encode($response);
