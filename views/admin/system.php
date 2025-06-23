<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');
$auth = new AuthMiddleware();
$auth->checkAdmin();

// Main request handler
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        handleGetRequest();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        handlePostRequest();
    } else {
        throw new InvalidRequestException("Invalid request method or missing action");
    }
} catch (InvalidRequestException $e) {
    http_response_code($e->getCode());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
    exit;
}

function handleGetRequest()
{
    switch ($_GET['action']) {
        case 'get_system_status':
            echo json_encode(getSystemStatus());
            break;
        case 'get_system_logs':
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 20;
            echo json_encode(getSystemLogs($page, $perPage));
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}


function handlePostRequest()
{
    switch ($_POST['action']) {

        case 'clear_logs':
            $result = clearLogs();
            insertSystemLog('Logs Cleared', 'All system logs were cleared.');
            echo json_encode($result);
            break;
        case 'update_system_settings':
            validateSystemSettings($_POST);
            $result = updateSystemSettings($_POST);
            insertSystemLog('System Settings Updated', 'System settings were updated.');
            echo json_encode($result);
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

class InvalidRequestException extends Exception
{
    public function __construct($message = "Invalid request", $code = 400)
    {
        parent::__construct($message, $code);
    }
}

function validateSystemSettings($data)
{
    if (empty($data['maintenance_mode']) || !in_array($data['maintenance_mode'], ['enabled', 'disabled'])) {
        throw new InvalidRequestException("Invalid maintenance mode value", 400);
    }

    if (!empty($data['backup_schedule']) && !in_array($data['backup_schedule'], ['daily', 'weekly', 'monthly'])) {
        throw new InvalidRequestException("Invalid backup schedule", 400);
    }
}

function getSystemStatus()
{
    $pdo = getPDO();

    // Check database connection
    $dbStatus = 'healthy';
    try {
        $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        $dbStatus = 'critical';
    }

    // Check disk space
    $freeSpace = disk_free_space(__DIR__);
    $totalSpace = disk_total_space(__DIR__);
    $diskUsage = ($totalSpace - $freeSpace) / $totalSpace * 100;
    $diskStatus = $diskUsage > 90 ? 'critical' : ($diskUsage > 75 ? 'warning' : 'healthy');

    // Get system settings
    $settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => [
            'database' => $dbStatus,
            'disk_space' => $diskStatus,
            'disk_usage' => round($diskUsage, 2),
            'settings' => $settings ?: []
        ]
    ];
}



function getSystemLogs($page, $perPage)
{
    $pdo = getPDO();
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT log_id, action, description, created_at 
        FROM system_logs 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    $total = $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ]
    ];
}



function insertSystemLog($action, $description)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO system_logs (action, description) VALUES (:action, :description)");
    $stmt->execute([
        ':action' => $action,
        ':description' => $description
    ]);
}
function clearLogs()
{
    $pdo = getPDO();

    // Delete all records from the system_logs table
    $stmt = $pdo->prepare("DELETE FROM system_logs");
    $stmt->execute();

    return [
        'success' => true,
        'message' => 'System logs have been cleared.'
    ];
}

function updateSystemSettings($data)
{
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        UPDATE system_settings
        SET maintenance_mode = :maintenance_mode, backup_schedule = :backup_schedule
        WHERE id = 1
    ");
    $stmt->execute([
        ':maintenance_mode' => $data['maintenance_mode'],
        ':backup_schedule' => $data['backup_schedule']
    ]);
    $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
    $stmt->execute([
        $_SESSION['user_id'],
        'System Updated ',
        'System Settings updated Succesfully',
        'system'
    ]);

    return [
        'success' => true,
        'message' => 'System settings have been updated.'
    ];
}
