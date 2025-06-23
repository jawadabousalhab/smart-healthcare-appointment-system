<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';

// Verify user is IT Admin
if ($_SESSION['role'] != 'it_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$pdo = getPDO();

// Get the requested action
$action = isset($_GET['action']) ? $_GET['action'] : 'get_ai_logs';

// Handle different actions
switch ($action) {
    case 'get_ai_logs':
        handleGetAiLogs($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleGetAiLogs($pdo)
{
    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
    $offset = ($page - 1) * $perPage;

    // Get filters
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $logLevels = isset($_GET['levels']) ? explode(',', $_GET['levels']) : ['INFO', 'WARNING', 'ERROR'];
    $timeRange = isset($_GET['time_range']) ? (int)$_GET['time_range'] : 0;

    // Get clinic ID for this IT admin
    $clinic_id = null;
    $stmt = $pdo->prepare("SELECT clinic_id FROM clinic_it_admins WHERE it_admin_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $clinic_id = $result['clinic_id'];
    }

    // Build base query
    $query = "SELECT * FROM ai_logs WHERE 1=1";
    $params = [];

    // Filter by clinic (through appointments if needed)
    if ($clinic_id) {
        $query .= " AND (related_appointment_id IS NULL OR related_appointment_id IN (
            SELECT appointment_id FROM appointments WHERE clinic_id = ?
        ))";
        $params[] = $clinic_id;
    }

    // Apply time range filter
    if ($timeRange > 0) {
        $query .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $timeRange;
    }

    // Apply search filter
    if (!empty($searchTerm)) {
        $query .= " AND (action_taken LIKE ? OR ai_reason LIKE ? OR related_appointment_id LIKE ?)";
        $searchTermLike = "%$searchTerm%";
        $params[] = $searchTermLike;
        $params[] = $searchTermLike;
        $params[] = $searchTermLike;
    }

    // Apply log level filters
    $levelConditions = [];
    foreach ($logLevels as $level) {
        $levelConditions[] = "action_taken LIKE ?";
        $params[] = "%$level%";
    }

    if (!empty($levelConditions)) {
        $query .= " AND (" . implode(" OR ", $levelConditions) . ")";
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as counted";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalLogs = $totalResult['total'];

    // Get paginated logs
    $query .= " ORDER BY timestamp DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($query);

    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }


    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'success' => true,
        'logs' => $logs,
        'total' => $totalLogs,
        'per_page' => $perPage,
        'current_page' => $page
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
}
