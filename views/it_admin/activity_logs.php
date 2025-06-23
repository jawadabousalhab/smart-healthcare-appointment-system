<?php
require_once '../../config/db.php';
require_once '../../middleware/auth_check.php';

// Check if user is logged in and is an IT admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_admin') {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied');
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getPDO();

    switch ($action) {
        case 'get_logs':
            getActivityLogs($pdo);
            break;
        case 'get_users':
            getUsersForFilter($pdo);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getActivityLogs($pdo)
{
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    $search = $_GET['search'] ?? '';
    $actionType = $_GET['type'] ?? '';
    $userId = $_GET['user_id'] ?? '';

    // Base query with joins
    $query = "SELECT l.log_id, l.action, l.description, l.ip_address, l.created_at,
                     u.user_id, u.name as user_name, u.role as user_role
              FROM activity_logs l
              JOIN users u ON l.user_id = u.user_id
              WHERE 1=1";

    $params = [];

    // Add search filter if provided
    if (!empty($search)) {
        $query .= " AND (l.action LIKE ? OR l.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Add action type filter if provided
    if (!empty($actionType)) {
        $query .= " AND l.action LIKE ?";
        $params[] = "$actionType%";
    }

    // Add user filter if provided
    if (!empty($userId)) {
        $query .= " AND l.user_id = ?";
        $params[] = $userId;
    }

    // Complete query with sorting and pagination
    $query .= " ORDER BY l.created_at DESC LIMIT $offset, $perPage";


    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format timestamps
    foreach ($logs as &$log) {
        $log['created_at'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
    }

    // Get total count with same filters
    $countQuery = "SELECT COUNT(*) FROM activity_logs l WHERE 1=1";
    $countParams = [];

    if (!empty($search)) {
        $countQuery .= " AND (l.action LIKE ? OR l.description LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }

    if (!empty($actionType)) {
        $countQuery .= " AND l.action LIKE ?";
        $countParams[] = "$actionType%";
    }

    if (!empty($userId)) {
        $countQuery .= " AND l.user_id = ?";
        $countParams[] = $userId;
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $logs,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ]);
}

function getUsersForFilter($pdo)
{
    $currentAdminId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.role
        FROM users u
        WHERE u.user_id = ? AND u.role = 'it_admin'

        UNION

        SELECT u.user_id, u.name, u.role
        FROM users u
        INNER JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
        WHERE u.role = 'doctor'

        ORDER BY name
    ");
    $stmt->execute([$currentAdminId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($users);
}
