<?php
require_once '../../config/db.php';
require_once '../../utils/mailer.php';
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
        case 'get_doctors':
            getDoctors($pdo);
            break;
        case 'get_assigned_clinics':
            getAssignedClinics($pdo);
            break;
        case 'get_doctor':
            getDoctor($pdo);
            break;
        case 'save_doctor':
            saveDoctor($pdo);
            break;
        case 'find_existing_doctor':
            findExistingDoctor($pdo);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getDoctors($pdo)
{
    $itAdminId = $_SESSION['user_id'];
    $page = $_GET['page'] ?? 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $search = $_GET['search'] ?? '';
    $clinicId = $_GET['clinic_id'] ?? '';

    // Base query to get doctors assigned to clinics this IT admin manages
    $query = "SELECT u.user_id, u.name, u.email, u.phone_number, u.specialization,
              GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as assigned_clinics
              FROM users u
              JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
              JOIN clinics c ON cd.clinic_id = c.clinic_id
              JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
              WHERE u.role = 'doctor' AND cia.it_admin_id = ?";

    $params = [$itAdminId];

    if (!empty($search)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.specialization LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($clinicId)) {
        $query .= " AND c.clinic_id = ?";
        $params[] = $clinicId;
    }

    $query .= " GROUP BY u.user_id
                ORDER BY u.name ASC
                LIMIT $offset, $perPage";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT u.user_id)
                   FROM users u
                   JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
                   JOIN clinics c ON cd.clinic_id = c.clinic_id
                   JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
                   WHERE u.role = 'doctor' AND cia.it_admin_id = ?";

    $countParams = [$itAdminId];

    if (!empty($search)) {
        $countQuery .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.specialization LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }

    if (!empty($clinicId)) {
        $countQuery .= " AND c.clinic_id = ?";
        $countParams[] = $clinicId;
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $doctors,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ]);
}

function getAssignedClinics($pdo)
{
    $itAdminId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT c.clinic_id, c.name 
                          FROM clinics c
                          JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
                          WHERE cia.it_admin_id = ?
                          ORDER BY c.name ASC");
    $stmt->execute([$itAdminId]);
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($clinics);
}

function getDoctor($pdo)
{
    $doctorId = $_GET['id'] ?? 0;


    // Get doctor basic info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'doctor'");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Doctor not found']);
        return;
    }

    // Get assigned clinics
    $stmt = $pdo->prepare("SELECT clinic_id FROM clinic_doctors WHERE doctor_id = ?");
    $stmt->execute([$doctorId]);
    $assignedClinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $doctor['assigned_clinics'] = $assignedClinics;

    header('Content-Type: application/json');
    echo json_encode($doctor);
}
function findExistingDoctor($pdo)
{
    $query = $_GET['query'] ?? '';
    if (empty($query)) {
        echo json_encode(null);
        return;
    }

    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE role = 'doctor' AND (email = ? OR name LIKE ?)");
    $stmt->execute([$query, "%$query%"]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($doctor ?: null);
}


function saveDoctor($pdo)
{

    $data = json_decode(file_get_contents('php://input'), true);
    $itAdminId = $_SESSION['user_id'];

    $doctorId = $data['doctor_id'] ?? 0;
    $name = $data['name'];
    $email = $data['email'];
    $countryCode = filter_var($data['country_code'], FILTER_SANITIZE_STRING);
    $phone = filter_var($data['phone'], FILTER_SANITIZE_STRING);
    $full_phone = $countryCode . $phone;
    $specialization = $data['specialization'];
    $assignedClinics = $data['assigned_clinics'] ?? [];
    $password = $data['password'] ?? null;

    // Validate required fields
    if (empty($name) || empty($email) || empty($specialization)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Name, email and specialization are required']);
        return;
    }

    // Verify assigned clinics belong to this IT admin
    if (!empty($assignedClinics)) {
        $placeholders = implode(',', array_fill(0, count($assignedClinics), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clinic_it_admins 
                                  WHERE clinic_id IN ($placeholders) AND it_admin_id = ?");
        $params = array_merge($assignedClinics, [$itAdminId]);
        $stmt->execute($params);

        if ($stmt->fetchColumn() != count($assignedClinics)) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'You can only assign to clinics you manage']);
            return;
        }
    }

    $pdo->beginTransaction();

    try {
        if ($doctorId) {
            // Update existing doctor
            $stmt = $pdo->prepare("UPDATE users SET 
                                      name = ?, email = ?, phone_number = ?, specialization = ?
                                      WHERE user_id = ? AND role = 'doctor'");
            $stmt->execute([$name, $email, $full_phone, $specialization, $doctorId]);
        } else {
            // Create new doctor
            $tempPassword = bin2hex(random_bytes(8)); // Generate a random password
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users 
                                      (name, email, phone_number, specialization, password, role, is_verified)
                                      VALUES (?, ?, ?, ?, ?, 'doctor',0)");
            $stmt->execute([$name, $email, $full_phone, $specialization, $passwordHash]);
            $doctorId = $pdo->lastInsertId();

            // Send email with credentials
            $subject = "Your Doctor Account Credentials";
            $message = "
                    <html>
                    <head>
                        <title>Your Doctor Account Credentials</title>
                    </head>
                    <body>
                        <h2>Welcome to Smart Healthcare System</h2>
                        <p>Your doctor account has been created by the IT administrator.</p>
                        <p>Here are your login credentials:</p>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Temporary Password:</strong> $tempPassword</p>
                        <p>We recommend changing your password after first login.</p>
                        <br>
                        <p>Best regards,<br>Smart Healthcare Team</p>
                    </body>
                    </html>
                ";

            $mailSent = sendEmail($email, $subject, $message);

            if (!$mailSent) {
                throw new Exception("Failed to send email to doctor");
            }
        }

        // Update assigned clinics
        $pdo->prepare("DELETE FROM clinic_doctors WHERE doctor_id = ?")->execute([$doctorId]);

        if (!empty($assignedClinics)) {
            $stmt = $pdo->prepare("INSERT INTO clinic_doctors (clinic_id, doctor_id) VALUES (?, ?)");
            foreach ($assignedClinics as $clinicId) {
                $stmt->execute([$clinicId, $doctorId]);
            }
        }

        $pdo->commit();

        // Log activity
        $action = $doctorId ? 'doctor_updated' : 'doctor_created';
        $description = $doctorId ? "Updated doctor: $name" : "Created new doctor: $name";

        $logStmt = $pdo->prepare("INSERT INTO activity_logs 
                                     (user_id, action, description, ip_address)
                                     VALUES (?, ?, ?, ?)");
        $logStmt->execute([
            $itAdminId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR']
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'doctor_id' => $doctorId,
            'temp_password' => $doctorId ? null : $tempPassword // Only return password for new doctors
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to save doctor: ' . $e->getMessage()]);
    }
}
