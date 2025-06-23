<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

$response = [
    'success' => false,
    'message' => 'Invalid request'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $code = trim(filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING));

    if (empty($email) || empty($code)) {
        $response['message'] = 'Email and verification code are required';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDO();

        // Set the same timezone as your database
        $pdo->exec("SET time_zone = '+00:00'");

        // Debug output
        error_log("Verifying code for email: $email");
        error_log("Code received: $code");

        // Get the most recent unexpired, unused code
        $stmt = $pdo->prepare("SELECT pr.reset_id, pr.user_id, pr.reset_code, pr.expires_at
                              FROM password_resets pr
                              JOIN users u ON pr.user_id = u.user_id
                              WHERE u.email = ?
                              AND pr.used = 0
                              ORDER BY pr.created_at DESC
                              LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug output
            error_log("DB Record Found:");
            error_log("Reset Code: " . $reset['reset_code']);
            error_log("Expires At: " . $reset['expires_at']);
            error_log("Current DB Time: " . $pdo->query("SELECT NOW()")->fetchColumn());

            // Trim and compare codes (case-sensitive)
            if (trim($reset['reset_code']) === trim($code)) {
                // Verify expiration using database time
                $stmt = $pdo->prepare("SELECT 1 FROM password_resets 
                                      WHERE reset_id = ? 
                                      AND expires_at > NOW()");
                $stmt->execute([$reset['reset_id']]);

                if ($stmt->rowCount() > 0) {
                    // Mark this code as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 
                                          WHERE reset_id = ?");
                    $stmt->execute([$reset['reset_id']]);

                    // Set session for password reset
                    $_SESSION['reset_user_id'] = $reset['user_id'];
                    $_SESSION['reset_verified'] = true;

                    $response['success'] = true;
                    $response['message'] = 'Code verified successfully!';
                } else {
                    $response['message'] = 'Verification code has expired.';
                    error_log("Code expired - DB time: " . $reset['expires_at']);
                }
            } else {
                $response['message'] = 'Invalid verification code.';
                error_log("Code mismatch - DB: '{$reset['reset_code']}', Input: '$code'");
            }
        } else {
            $response['message'] = 'No active verification code found for this email.';
            error_log("No unexpired, unused codes found for email: $email");
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
    }
}

echo json_encode($response);
