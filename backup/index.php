<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

header('Content-Type: application/json');
$auth = new AuthMiddleware();
$auth->checkAdmin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        handleGetRequest();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        handlePostRequest();
    } else {
        throw new InvalidRequestException("Invalid request method or missing action");
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?? 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

function handleGetRequest()
{
    switch ($_GET['action']) {
        case 'get_backups':
            echo json_encode(getBackups());
            break;
        case 'download_backup':
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                throw new InvalidRequestException('No filename specified for download.', 400);
            }
            handleBackupDownload($filename);
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

function handlePostRequest()
{
    switch ($_POST['action']) {
        case 'create_backup':
            $result = createBackup();
            insertSystemLog('Backup Created', 'A new backup "' . $result['filename'] . '" was created.');
            echo json_encode($result);
            break;
        case 'restore_backup':
            if (empty($_POST['backup_id'])) {
                throw new InvalidRequestException("Backup ID is required", 400);
            }
            $result = restoreBackup($_POST['backup_id']);
            insertSystemLog('Backup Restored', 'Backup with ID ' . $_POST['backup_id'] . ' was restored.');
            echo json_encode($result);
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

// Include all the backup-related functions from the original system.php
// (getBackups, handleBackupDownload, createBackup, restoreBackup, insertSystemLog)

class InvalidRequestException extends Exception
{
    public function __construct($message = "Invalid request", $code = 400)
    {
        parent::__construct($message, $code);
    }
}

function handleBackupDownload($filename)
{
    $filepath = __DIR__ . '/../../storage/backups/' . $filename;

    if (!file_exists($filepath)) {
        throw new InvalidRequestException('Backup file not found', 404);
    }

    // Log the download action
    insertSystemLog('Backup Downloaded', 'Backup "' . $filename . '" was downloaded.');

    // Set headers for downloading the file
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    flush(); // Ensure no previous content is sent
    readfile($filepath);
    exit;
}
function getBackups()
{
    $pdo = getPDO();
    $stmt = $pdo->query("
        SELECT backup_id, filename, size, created_at, created_by 
        FROM backups 
        ORDER BY created_at DESC
    ");
    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}
function createBackup()
{
    $pdo = getPDO();
    $backupFilename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backupPath = __DIR__ . '/../storage/backups/' . $backupFilename;

    $sqlDump = "";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $sqlDump .= "-- Table structure for `$table`\n";
        $sqlDump .= $row['Create Table'] . ";\n\n";

        // Get table data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $sqlDump .= "-- Dumping data for table `$table`\n";
            foreach ($rows as $row) {
                $escapedValues = array_map(function ($value) use ($pdo) {
                    return $value !== null ? $pdo->quote($value) : 'NULL';
                }, $row);

                $sqlDump .= "INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $escapedValues) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    // Save to file
    file_put_contents($backupPath, $sqlDump);

    // Store in DB
    $stmt = $pdo->prepare("
        INSERT INTO backups (filename, size, created_by) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $backupFilename,
        filesize($backupPath),
        $_SESSION['user_id']
    ]);
    $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
    $stmt->execute([
        $_SESSION['user_id'],
        'Backup Created ',
        'Backup created Succesfully',
        'success'
    ]);

    return [
        'success' => true,
        'message' => 'Backup created successfully (no mysqldump needed)',
        'filename' => $backupFilename
    ];
}

function restoreBackup($backupId)
{
    $pdo = getPDO();

    // Fetch backup
    $backup = $pdo->query("SELECT filename FROM backups WHERE backup_id = " . (int)$backupId)
        ->fetch(PDO::FETCH_ASSOC);

    if (!$backup) {
        throw new InvalidRequestException("Backup not found", 404);
    }

    $backupPath = __DIR__ . '/../../storage/backups/' . $backup['filename'];

    if (!file_exists($backupPath)) {
        throw new Exception("Backup file not found", 404);
    }

    // Restore using mysql
    $command = "mysql --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " < " . escapeshellarg($backupPath);
    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        throw new Exception("Restore failed", 500);
    }

    // Log restore to system_logs
    insertSystemLog('Backup Restored', 'Backup "' . $backup['filename'] . '" was restored.');
    $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
    $stmt->execute([
        $_SESSION['user_id'],
        'Backup Restored ',
        'Backup restored Succesfully',
        'success'
    ]);
    return [
        'success' => true,
        'message' => 'Backup restored successfully'
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
