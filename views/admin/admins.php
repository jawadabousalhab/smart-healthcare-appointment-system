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
        case 'get_admins':
            echo json_encode(getAdmins());
            break;
        case 'search_admins':
            $search = $_GET['search'] ?? '';
            $filter = $_GET['filter'] ?? 'all';
            echo json_encode(searchAdmins($search, $filter));
            break;
        default:
            throw new InvalidRequestException("Invalid action specified", 400);
    }
}

function handlePostRequest()
{
    switch ($_POST['action']) {
        case 'add_admin':
            validateAdminData($_POST);
            echo json_encode(addAdmin($_POST));
            break;
        case 'update_admin':
            validateAdminData($_POST, true);
            echo json_encode(updateAdmin($_POST));
            break;
        case 'delete_admin':
            if (empty($_POST['user_id'])) {
                throw new InvalidRequestException("User ID is required", 400);
            }
            echo json_encode(deleteAdmin($_POST['user_id']));
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
function validateAdminData($data, $requireId = false)
{
    if ($requireId && empty($data['user_id'])) {
        throw new InvalidRequestException("User ID is required", 400);
    }

    if (empty($data['name']) || strlen($data['name']) > 100) {
        throw new InvalidRequestException("Valid name is required (max 100 chars)", 400);
    }

    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidRequestException("Valid email is required", 400);
    }

    $fullphonenb = $data['phone_number_code'] . '' . $data['phone_number'];
    if (isset($fullphonenb) &&  $fullphonenb !== '') {
        error_log("PHONE RECEIVED: " . $fullphonenb);
    }
}

function getAdmins()
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, phone_number, created_at 
        FROM users 
        WHERE role = 'admin' 
        ORDER BY name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchAdmins($search, $filter)
{
    $pdo = getPDO();
    $query = "
        SELECT user_id, name, email, phone_number, created_at 
        FROM users 
        WHERE role = 'admin'
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addAdmin($data)
{
    $email = trim($data['email']);

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        throw new InvalidRequestException("Email already exists", 409);
    }

    $password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    try {
        $fullphonenb = $data['phone_number_code'] . $data['phone_number'];

        $stmt = $pdo->prepare("
            INSERT INTO users 
            (name, email, phone_number, password, role, needs_password_setup, is_verified) 
            VALUES (?, ?, ?, ?, 'admin', 1, 1)
        ");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $fullphonenb,
            $hashed_password
        ]);

        $user_id = $pdo->lastInsertId();

        $subject = "Welcome to Smart HealthCare";
        $message = "Dear {$data['name']},<br><br>";
        $message .= "You were registered as an Admin. Your temporary password is:<br><br>";
        $message .= "<strong style='font-size: 1.5rem;'>$password</strong><br><br>";
        $message .= "Please change your password after first login.<br><br>";
        $message .= "Best regards,<br>Smart Health Team";

        $emailSent = sendEmail($data['email'], $subject, $message);

        $pdo->commit();
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'Admin Added ',
            'Admin added successfully',
            'success'
        ]);
        return [
            'success' => true,
            'message' => 'Admin added successfully' . ($emailSent ? '' : ' but email could not be sent'),
            'user_id' => $user_id
        ];
    } catch (Exception $e) {
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'Admin not Added ',
            'There is a server Error',
            'warning'
        ]);
        $pdo->rollBack();
        throw new Exception("Failed to add Admin: " . $e->getMessage(), 500);
    }
}

function updateAdmin($data)
{
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE email = ? AND user_id != ?
    ");
    $stmt->execute([$data['email'], $data['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new InvalidRequestException("Email already in use by another user", 409);
    }
    $fullphonenb = $data['phone_number_code'] . $data['phone_number'];


    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, email = ?, phone_number = ? 
        WHERE user_id = ? AND role = 'admin'
    ");
    $stmt->execute([
        $data['name'],
        $data['email'],
        $fullphonenb,
        $data['user_id']
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("No Admin found with that ID or no changes made", 404);
    }

    return [
        'success' => true,
        'message' => 'Admin updated successfully'
    ];
}

function deleteAdmin($userId)
{
    $pdo = getPDO();

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            DELETE FROM users 
            WHERE user_id = ? AND role = 'admin'
        ");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("No Admin found with that ID", 404);
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Admin deleted successfully'
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Failed to delete Admin: " . $e->getMessage(), 500);
    }
}
