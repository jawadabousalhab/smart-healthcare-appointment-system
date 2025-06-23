<?php
session_start();
header('Content-Type: application/json');
require_once  '../../config/db.php';


$response = [
    'success' => false,
    'message' => 'Invalid verification request'
];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $postData = file_get_contents('php://input');
    parse_str($postData, $data);

    $code = $data['code'] ?? '';
    $email = $data['email'] ?? '';

    if (empty($code) || empty($email)) {
        $response['message'] = 'Verification code and email are required';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDO();

        // Check if user exists with this email and verification code
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND verification_code = ? AND is_verified = 0");
        $stmt->execute([$email, $code]);

        if ($stmt->rowCount() > 0) {
            // Mark user as verified
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?");
            $stmt->execute([$email]);

            // Clear verification email from session
            unset($_SESSION['verify_email']);

            $response['success'] = true;
            $response['message'] = 'Your email has been verified successfully! Redirecting to login...';
        } else {
            // Check if user is already verified
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_verified = 1");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $response['message'] = 'This email address has already been verified. Please login.';
            } else {
                // Check if code is wrong
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_verified = 0");
                $stmt->execute([$email]);

                if ($stmt->rowCount() > 0) {
                    $response['message'] = 'Invalid verification code. Please check your email and try again.';
                } else {
                    $response['message'] = 'No pending verification found for this email. Please register first.';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $response['message'] = 'An error occurred during verification. Please try again later.';
    }
}

echo json_encode($response);
