<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require '../../utils/mailer.php';
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
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    exit;
}

function handleGetRequest()
{
    switch ($_GET['action']) {
        case 'get_it_admins':
            echo json_encode(getItAdmins());
            break;
        case 'search_it_admins':
            $search = $_GET['search'] ?? '';
            $filter = $_GET['filter'] ?? 'all';
            echo json_encode(searchItAdmins($search, $filter));
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

function handlePostRequest()
{
    switch ($_POST['action']) {
        case 'add_it_admin':
            validateAdminData($_POST);
            echo json_encode(addItAdmin($_POST));
            break;
        case 'update_it_admin':
            validateAdminData($_POST, true);
            echo json_encode(updateItAdmin($_POST));
            break;
        case 'delete_it_admin':
            if (empty($_POST['user_id'])) {
                throw new InvalidRequestException("User ID is required", 400);
            }
            echo json_encode(deleteItAdmin($_POST['user_id']));
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

// Custom exception class
class InvalidRequestException extends Exception
{
    public function __construct($message = "Invalid request", $code = 400)
    {
        parent::__construct($message, $code);
    }
}

function validateAdminData($data, $requireId = false)
{
    error_log("DEBUG phone_number: " . $data['phone_number']);

    if ($requireId && empty($data['user_id'])) {
        throw new InvalidRequestException("User ID is required", 400);
    }

    if (empty($data['name']) || strlen($data['name']) > 100) {
        throw new InvalidRequestException("Valid name is required (max 100 chars)", 400);
    }

    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidRequestException("Valid email is required", 400);
    }
    $fullphonenb = $data['phone_number_code'] . $data['phone_number'];
    if (isset($fullphonenb) &&  $fullphonenb !== '') {
        error_log("PHONE RECEIVED: " . $data['phone_number']);
    }
}
function getItAdmins()
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, phone_number, 
               DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at 
        FROM users 
        WHERE role = 'it_admin' 
        ORDER BY name
    ");
    $stmt->execute();
    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

function searchItAdmins($search, $filter)
{
    $pdo = getPDO();
    $query = "
        SELECT user_id, name, email, phone_number, created_at 
        FROM users 
        WHERE role = 'it_admin'
    ";

    $params = [];

    if (!empty($search)) {
        $query .= " AND (name LIKE :search OR email LIKE :search OR phone_number LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($filter === 'recent') {
        $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    $query .= " ORDER BY name";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}


function addItAdmin($data)
{
    $pdo = getPDO();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetchColumn() > 0) {
        throw new InvalidRequestException("Email already exists", 409);
    }

    // Generate a random password
    $password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $fullphonenb = $data['phone_number_code'] . $data['phone_number'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (name, email, phone_number, password, role, needs_password_setup, is_verified) 
            VALUES (?, ?, ?, ?, 'it_admin', 1, 1)
        ");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $fullphonenb,
            $hashed_password
        ]);

        $user_id = $pdo->lastInsertId();

        // Send welcome email with password
        $subject = "Welcome to Smart HealthCare";
        $message = "Dear {$data['name']},<br><br>";
        $message .= "You were registered to our system as an IT admin, and the system generated a password for you:<br><br>";
        $message .= "<strong style='font-size: 1.5rem; letter-spacing: 0.5rem;'>$password</strong><br><br>";
        $message .= "Please change your password after first login.<br><br>";
        $message .= "Best regards,<br>Smart Health Team";

        $emailSent = sendEmail($data['email'], $subject, $message);
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'IT Addition',
            'IT added Succesfully',
            'success'
        ]);

        $pdo->commit();
        return [
            'success' => true,
            'message' => 'IT Admin added successfully' . ($emailSent ? '' : ' but email could not be sent'),
            'user_id' => $user_id
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Failed to add IT Admin: " . $e->getMessage(), 500);
    }
}

function updateItAdmin($data)
{
    $pdo = getPDO();

    // Check if email exists for another user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE email = ? AND user_id != ?
    ");
    $stmt->execute([$data['email'], $data['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new InvalidRequestException("Email already in use by another user", 409);
    }

    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, email = ?, phone_number = ? 
        WHERE user_id = ? AND role = 'it_admin'
    ");
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone_number'] ?? null,
        $data['user_id']
    ]);
    $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
    $stmt->execute([
        $_SESSION['user_id'],
        'IT Updated ',
        'IT updated Succesfully',
        'success'
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("No IT Admin found with that ID or no changes made", 404);
    }

    return [
        'success' => true,
        'message' => 'IT Admin updated successfully'
    ];
}

function deleteItAdmin($userId)
{
    $pdo = getPDO();

    // Check if admin is assigned to any clinic
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clinic_it_admins WHERE it_admin_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() > 0) {
        throw new InvalidRequestException("Cannot delete IT Admin assigned to clinics", 403);
    }

    $pdo->beginTransaction();

    try {
        // Handle all foreign key dependencies
        $dependencyTables = [
            'activity_logs' => 'user_id',
            'backups' => 'created_by',
            'notifications' => 'user_id',
            // Add any other tables that reference users.user_id
        ];

        foreach ($dependencyTables as $table => $column) {
            // Option 1: Delete dependent records
            $pdo->prepare("DELETE FROM $table WHERE $column = ?")->execute([$userId]);

            // OR Option 2: Set to NULL if column allows NULLs
            //$pdo->prepare("UPDATE $table SET $column = NULL WHERE $column = ?")->execute([$userId]);
        }

        // Now delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'it_admin'");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("No IT Admin found with that ID", 404);
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'IT Admin deleted successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Failed to delete IT Admin: " . $e->getMessage(), 500);
    }
}
