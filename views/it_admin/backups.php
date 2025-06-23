<?php
require_once '../../config/db.php';
require_once 'backuputil.php';

session_start();

// Check if user is logged in and is an IT admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_admin') {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied');
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getPDO();

    switch ($action) {
        case 'get_backups':
            getBackups($pdo);
            break;
        case 'create_backup':
            createBackup($pdo);
            break;
        case 'download_backup':
            downloadBackup($pdo);
            break;
        case 'delete_backup':
            deleteBackup($pdo);
            break;
        case 'restore_backup':
            restoreBackup($pdo);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getBackups($pdo)
{
    $itAdminId = $_SESSION['user_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';

    // Base query with joins
    $query = "SELECT b.backup_id, b.filename, b.size, b.created_at, 
                     u.name as created_by_name, b.type, b.description
              FROM backups b
              JOIN users u ON b.created_by = u.user_id
              WHERE b.created_by = ?";

    $params = [$itAdminId];

    // Add search filter if provided
    if (!empty($search)) {
        $query .= " AND (b.filename LIKE ? OR b.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Add type filter if provided
    if (!empty($type) && in_array($type, ['full', 'clinics', 'doctors', 'assignments'])) {
        $query .= " AND b.type = ?";
        $params[] = $type;
    }

    // Inject LIMIT and OFFSET directly
    $query .= " ORDER BY b.created_at DESC LIMIT $offset, $perPage";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format file sizes and dates
    foreach ($backups as &$backup) {
        $backup['size'] = formatSizeUnits($backup['size']);
        $backup['created_at'] = date('Y-m-d H:i:s', strtotime($backup['created_at']));
    }

    // Get total count with same filters
    $countQuery = "SELECT COUNT(*) FROM backups b WHERE b.created_by = ?";
    $countParams = [$itAdminId];

    if (!empty($search)) {
        $countQuery .= " AND (b.filename LIKE ? OR b.description LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }

    if (!empty($type) && in_array($type, ['full', 'clinics', 'doctors', 'assignments'])) {
        $countQuery .= " AND b.type = ?";
        $countParams[] = $type;
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $backups,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ]);
}


function createBackup($pdo)
{
    $itAdminId = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $backupName = $data['name'] ?? 'Manual Backup ' . date('Y-m-d H:i:s');
    $description = $data['description'] ?? '';
    $backupType = $data['type'] ?? 'full';

    // Validate backup type
    $validTypes = ['full', 'clinics', 'doctors', 'appointments', 'assignments']; // ADD 'appointments' here
    if (!in_array($backupType, $validTypes)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid backup type.']);
        return;
    }

    $backupFileName = date('Ymd_His') . '_' . $backupType . '.json';
    $backupDir = dirname(dirname(__DIR__)) . '/backups/';


    try {
        // Create backup data
        $backupData = BackupUtil::generateBackupData($pdo, $itAdminId, $backupType);

        // Create backup file
        $fileInfo = BackupUtil::createBackupFile($backupData, $backupType);
        $filename = 'backup_' . date('Ymd_His') . '_' . uniqid() . "_{$backupType}.json";
        $filepath = $backupDir . $filename;
        if (!file_put_contents($filepath, json_encode($fileInfo))) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Failed to create backup file.']);
            return;
        }
        $filesize = $fileInfo['size'];

        // Save to database
        $stmt = $pdo->prepare("INSERT INTO backups 
                              (filename, size, type, description, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$filename, $filesize, $backupType, $description, $itAdminId]);
        $backupId = $pdo->lastInsertId();

        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_logs 
                                 (user_id, action, description, ip_address, created_at)
                                 VALUES (?, ?, ?, ?, NOW())");
        $logStmt->execute([
            $itAdminId,
            'backup_created',
            "Created backup: $filename ($backupType)",
            $_SERVER['REMOTE_ADDR']
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'backup_id' => $backupId,
            'filename' => $filename,
            'size' => BackupUtil::formatSize($filesize)
        ]);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to create backup: ' . $e->getMessage()]);
    }
}

function downloadBackup($pdo)
{
    $backupId = $_GET['id'] ?? 0;
    $itAdminId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT filename FROM backups WHERE backup_id = ? AND created_by = ?");
    $stmt->execute([$backupId, $itAdminId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup) {
        header('HTTP/1.1 404 Not Found');
        die('Backup not found or access denied');
    }
    $backupDir = dirname(dirname(__DIR__)) . '/backups/';

    $filepath = $backupDir . $backup['filename'];

    if (!file_exists($filepath)) {
        header('HTTP/1.1 404 Not Found');
        die('Backup file not found');
    }

    // Log download activity
    $logStmt = $pdo->prepare("INSERT INTO activity_logs 
                             (user_id, action, description, ip_address, created_at)
                             VALUES (?, ?, ?, ?, NOW())");
    $logStmt->execute([
        $itAdminId,
        'backup_downloaded',
        "Downloaded backup: " . $backup['filename'],
        $_SERVER['REMOTE_ADDR']
    ]);

    // Send file for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/json'); // Assuming the backup files are JSON
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

function deleteBackup($pdo)
{
    $backupId = $_GET['id'] ?? 0;
    $itAdminId = $_SESSION['user_id'];

    // Get backup info first
    $stmt = $pdo->prepare("SELECT filename FROM backups WHERE backup_id = ? AND created_by = ?");
    $stmt->execute([$backupId, $itAdminId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Backup not found or access denied']);
        return;
    }

    $pdo->beginTransaction();

    try {
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM backups WHERE backup_id = ?");
        $stmt->execute([$backupId]);

        // Delete file
        $filepath = '../../backups/' . $backup['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $pdo->commit();

        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_logs 
                                 (user_id, action, description, ip_address, created_at)
                                 VALUES (?, ?, ?, ?, NOW())");
        $logStmt->execute([
            $itAdminId,
            'backup_deleted',
            "Deleted backup: " . $backup['filename'],
            $_SERVER['REMOTE_ADDR']
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to delete backup: ' . $e->getMessage()]);
    }
}
function restoreBackup($pdo)
{
    $backupId = $_POST['id'] ?? 0; // Use POST for restore actions
    $itAdminId = $_SESSION['user_id'];

    // Get backup info
    $stmt = $pdo->prepare("SELECT filename, type FROM backups WHERE backup_id = ? AND created_by = ?");
    $stmt->execute([$backupId, $itAdminId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Backup not found or access denied.']);
        return;
    }

    // Define the base backup directory
    $backupDir = dirname(dirname(__DIR__)) . '/backups/';
    $filepath = $backupDir . $backup['filename'];

    if (!file_exists($filepath)) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Backup file not found on server.']);
        return;
    }

    $pdo->beginTransaction(); // Start a transaction for safety

    try {
        // Call the restoration utility function
        // This function in BackupUtil will handle reading the JSON and inserting data
        $success = BackupUtil::restoreDataFromJson($pdo, $filepath, $backup['type']);

        if (!$success) {
            throw new Exception("Data restoration failed for unknown reason.");
        }

        $pdo->commit(); // Commit transaction if successful

        // Log restoration activity
        $logStmt = $pdo->prepare("INSERT INTO activity_logs
                                 (user_id, action, description, ip_address, created_at)
                                 VALUES (?, ?, ?, ?, NOW())");
        $logStmt->execute([
            $itAdminId,
            'backup_restored',
            "Restored backup: " . $backup['filename'] . " (Type: " . $backup['type'] . ")",
            $_SERVER['REMOTE_ADDR']
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Backup restored successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback transaction on error
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to restore backup: ' . $e->getMessage()]);
    }
}

function formatSizeUnits($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
