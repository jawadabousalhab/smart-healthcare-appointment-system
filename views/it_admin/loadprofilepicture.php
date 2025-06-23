<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';

require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');


// Get database connection
$pdo = getPDO();

// Get the current admin ID from session
$ITadminId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT user_id, name, profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$ITadminId]);
    $ITadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    $profilePicture = $ITadmin['profile_picture']
        ? '../../uploads/profiles/' . $ITadmin['profile_picture']
        : '../../assets/images/default-profile.png';

    if (!$ITadmin) {
        throw new Exception("Admin not found");
    }

    // Set default profile picture if empty
    if (empty($ITadmin['profile_picture'])) {
        $ITadmin['profile_picture'] = 'assets/images/default-profile.png';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'name' => $ITadmin['name'],
            'profile_picture' => $profilePicture
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
