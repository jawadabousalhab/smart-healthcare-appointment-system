<?php
require_once '../../config/db.php';

require_once '../../middleware/auth_check.php';


$pdo = getPDO();
// Check if user is logged in and is an IT admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_admin') {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied');
}

$action = $_GET['action'] ?? '';

try {

    switch ($action) {
        case 'get_assigned_clinics':
            getAssignedClinics($pdo);
            break;
        case 'get_clinic':
            getClinic($pdo);
            break;
        case 'update_clinic':
            updateClinic($pdo);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getAssignedClinics($pdo)
{
    $itAdminId = $_SESSION['user_id'];
    $page = $_GET['page'] ?? 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $search = $_GET['search'] ?? '';

    $query = "SELECT c.* FROM clinics c
              JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
              WHERE cia.it_admin_id = ?";

    $params = [$itAdminId];

    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.location LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY c.name ASC LIMIT $offset, $perPage";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM clinics c
                   JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
                   WHERE cia.it_admin_id = ?";

    if (!empty($search)) {
        $countQuery .= " AND (c.name LIKE ? OR c.location LIKE ?)";
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $clinics,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ]);
}

function getClinic($pdo)
{
    $clinicId = $_GET['id'] ?? 0;
    $itAdminId = $_SESSION['user_id'];

    // Verify the clinic is assigned to this IT admin
    $stmt = $pdo->prepare("SELECT 1 FROM clinic_it_admins 
                          WHERE clinic_id = ? AND it_admin_id = ?");
    $stmt->execute([$clinicId, $itAdminId]);

    if (!$stmt->fetchColumn()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'You are not assigned to this clinic']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM clinics WHERE clinic_id = ?");
    $stmt->execute([$clinicId]);
    $clinic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clinic) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Clinic not found']);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode($clinic);
}

function updateClinic($pdo)
{
    // Verify content type
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid content type']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Received data for updateClinic: " . print_r($data, true));
    if (json_last_error() !== JSON_ERROR_NONE) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $itAdminId = $_SESSION['user_id'];
    $clinicId = filter_var($data['clinic_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$clinicId) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid clinic ID']);
        return;
    }

    // Verify assignment
    $stmt = $pdo->prepare("SELECT 1 FROM clinic_it_admins 
                          WHERE clinic_id = ? AND it_admin_id = ?");
    $stmt->execute([$clinicId, $itAdminId]);

    if (!$stmt->fetchColumn()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'You are not assigned to this clinic']);
        return;
    }

    // Validate inputs
    $name = trim(filter_var($data['name'] ?? '', FILTER_SANITIZE_STRING));
    $location = trim(filter_var($data['location'] ?? '', FILTER_SANITIZE_STRING));
    $phone = trim(filter_var($data['phone'] ?? '', FILTER_SANITIZE_STRING));
    $country_code = filter_var($data['phone_cc'] ?? '', FILTER_SANITIZE_STRING);
    $full_phone = $country_code . $phone;
    $coordinates = trim(filter_var($data['coordinates'] ?? '', FILTER_SANITIZE_STRING));
    error_log("Coordinates after filter_var: " . $coordinates);

    if (empty($name) || empty($location)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Name and location are required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE clinics SET 
                              name = ?, location = ?, phone_number = ?, map_coordinates = ?
                              WHERE clinic_id = ?");
        $stmt->execute([$name, $location, $full_phone, $coordinates, $clinicId]);

        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_logs 
                                 (user_id, action, description, ip_address)
                                 VALUES (?, ?, ?, ?)");
        $logStmt->execute([
            $itAdminId,
            'clinic_updated',
            "Updated clinic: " . substr($name, 0, 100),
            $_SERVER['REMOTE_ADDR']
        ]);

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to update clinic: ' . $e->getMessage()]);
    }
}
