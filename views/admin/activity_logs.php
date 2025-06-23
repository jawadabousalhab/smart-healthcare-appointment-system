<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');

$auth = new AuthMiddleware();
$auth->checkAdmin();

if (isset($_GET['action']) && $_GET['action'] === 'get_recent_activity') {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("
           SELECT al.action, al.description, u.name AS performed_by, al.created_at
           FROM activity_logs al
           JOIN users u ON al.user_id = u.user_id
           ORDER BY al.created_at DESC
           LIMIT 10
        ");

        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'activities' => $activities,
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 1
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch activity logs']);
    }
}
