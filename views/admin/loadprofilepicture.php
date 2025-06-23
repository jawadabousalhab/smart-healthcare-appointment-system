<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');
$auth = new AuthMiddleware();
$auth->checkAdmin();

// Get database connection
$pdo = getPDO();

// Get the current admin ID from session
$adminId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT user_id, name, profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new Exception("Admin not found");
    }

    // Set default profile picture if empty
    if (empty($admin['profile_picture'])) {
        $admin['profile_picture'] = '/assets/images/default-profile.png';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'name' => $admin['name'],
            'profile_picture' => ltrim($admin['profile_picture'], '/')
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
