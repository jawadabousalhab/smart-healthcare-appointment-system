<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');
$auth = new AuthMiddleware();
$auth->checkAdmin();

// Get database connection
$pdo = getPDO();
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'mark_read':
            $id = $input['id'] ?? 0;
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true]);
            exit;

        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
            exit;
    }
}

try {
    switch ($action) {
        case 'get_unread_count':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }

            $userId = (int)$_SESSION['user_id'];
            $pdo->exec("COMMIT"); // Ensure no open transactions

            $stmt = $pdo->prepare("SELECT COUNT(*) as unread 
                                  FROM notifications 
                                  WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'count' => $count,
                'last_checked' => time(),
                'user_verified' => $userId === $_SESSION['user_id'],
            ]);
            break;

        case 'get_notification':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($notification) {
                echo json_encode([
                    'success' => true,
                    'notification' => $notification
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Notification not found']);
            }
            break;

        case 'get_notifications':
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(50, intval($_GET['per_page'] ?? 10));
            $offset = ($page - 1) * $perPage;

            $filter = $_GET['filter'] ?? 'all';
            $timeRange = $_GET['time_range'] ?? 'all';

            $query = "SELECT * FROM notifications WHERE user_id = :user_id";
            $params = ['user_id' => $userId];

            // Apply filters
            if ($filter === 'unread') {
                $query .= " AND is_read = 0";
            } elseif (in_array($filter, ['system', 'alert', 'appointment'])) {
                $query .= " AND type = :type_filter";
                $params['type_filter'] = $filter;
            }

            // Apply time range
            if ($timeRange === 'today') {
                $query .= " AND DATE(created_at) = CURDATE()";
            } elseif ($timeRange === 'week') {
                $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            } elseif ($timeRange === 'month') {
                $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            }

            // Count total
            $countQuery = "SELECT COUNT(*) FROM ($query) AS count_table";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $totalCount = $stmt->fetchColumn();

            // Paginated query
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue(":$key", $val);
            }
            $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total_count' => (int)$totalCount
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
