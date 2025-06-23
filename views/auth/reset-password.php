<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

$response = [
    'success' => false,
    'message' => 'Invalid request'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['newPassword'] ?? '';

    // Check session for verified user ID
    if (empty($_SESSION['reset_user_id'])) {
        $response['message'] = 'No valid reset session found. Please verify your code again.';
        echo json_encode($response);
        exit();
    }

    if (empty($newPassword)) {
        $response['message'] = 'New password is required';
        echo json_encode($response);
        exit();
    }

    if (strlen($newPassword) < 8) {
        $response['message'] = 'Password must be at least 8 characters';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDO();

        $userId = $_SESSION['reset_user_id'];

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, needs_password_setup = 0 WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        // Optionally mark all codes as used for this user (cleanup)
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Clear reset session
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);

        $response['success'] = true;
        $response['message'] = 'Password reset successfully! Redirecting to login...';
    } catch (PDOException $e) {
        error_log("Reset password error: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
    }
}

echo json_encode($response);
