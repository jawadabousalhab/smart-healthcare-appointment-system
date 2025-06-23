<?php
session_start();
header('Content-Type: application/json');
require_once  '../../config/db.php';
require_once  '../../utils/mailer.php';

$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $postData = file_get_contents('php://input');
    parse_str($postData, $data);

    $email = $data['email'] ?? '';

    if (empty($email)) {
        $response['message'] = 'Email is required';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDO();

        // Check if user exists and is unverified
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND is_verified = 0");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate new 6-digit verification code
            $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Update verification code in database
            $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
            $stmt->execute([$new_code, $email]);

            // Send verification email
            $subject = "Your New Verification Code";
            $message = "Dear {$user['name']},<br><br>";
            $message .= "Here is your new verification code: <strong>$new_code</strong><br><br>";
            $message .= "Please enter this code on the verification page to complete your registration.<br><br>";
            $message .= "If you did not request this code, please ignore this email.<br><br>";
            $message .= "Best regards,<br>Smart Health Team";

            if (sendEmail($email, $subject, $message)) {
                $response['success'] = true;
                $response['message'] = 'A new verification code has been sent to your email.';
            } else {
                $response['message'] = 'Failed to send verification email. Please try again later.';
            }
        } else {
            $response['message'] = 'No pending verification found for this email. Please register first.';
        }
    } catch (PDOException $e) {
        error_log("Resend verification error: " . $e->getMessage());
        $response['message'] = 'An error occurred while resending the verification code. Please try again later.';
    }
}

echo json_encode($response);
