<?php
header('Content-Type: application/json');
require_once '../../config/db.php'; // Database configuration
require_once '../../middleware/check_auth.php'; // Authentication middleware
$pdo = getPDO();

try {

    // Get counts for dashboard
    $counts = [
        'clinics' => $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn(),
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'backups' => $pdo->query("SELECT COUNT(*) FROM backups")->fetchColumn(),
        'activity_logs' => $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
        'ai_logs' => $pdo->query("SELECT COUNT(*) FROM ai_logs")->fetchColumn()
    ];

    // Get recent activity logs (last 5)
    $recentActivity = [];
    $stmt = $pdo->query("
        SELECT a.*, u.name as user_name 
        FROM activity_logs a
        JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $recentActivity[] = [
            'log_id' => $row['log_id'],
            'user_name' => $row['user_name'],
            'action' => $row['action'],
            'description' => $row['description'],
            'timestamp' => $row['created_at']
        ];
    }

    // Get system status (simulated - in a real app this would check actual services)
    $systemStatus = [
        'database' => 'good',
        'api_services' => 'good',
        'backup_service' => 'warning', // Simulating a warning for demo
        'ai_services' => 'good'
    ];

    // Prepare response
    $response = [
        'success' => true,
        'counts' => $counts,
        'recent_activity' => $recentActivity,
        'system_status' => $systemStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
