<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
require_once '../../middleware/Doctor_verified.php';
header('Content-Type: application/json');

// Verify user is a Doctor
if ($_SESSION['role'] != 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
if (!isDoctorVerified($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Doctor not verified']);
    exit();
}


$doctor_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getPDO();

    switch ($action) {
        case 'get_patients':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = 10;
            $offset = ($page - 1) * $perPage;
            $search = $_GET['search'] ?? '';

            // Base query to get patients with completed/confirmed appointments
            $query = "SELECT DISTINCT p.user_id, p.name, p.email, p.phone_number as phone, p.profile_picture,
                     COUNT(a.appointment_id) as appointment_count,
                     MAX(a.appointment_date) as last_visit
                     FROM users p
                     JOIN appointments a ON p.user_id = a.patient_id
                     WHERE a.doctor_id = ? 
                     AND a.status IN ('completed', 'approved')";

            $params = [$doctor_id];

            // Add search filter if provided
            if (!empty($search)) {
                $query .= " AND (p.name LIKE ? OR p.email LIKE ? OR p.phone_number LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Group and count
            $query .= " GROUP BY p.user_id";

            // Get total count
            $countQuery = "SELECT COUNT(*) FROM ($query) as total";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            // Add pagination and sorting
            $query .= " ORDER BY last_visit DESC LIMIT $perPage OFFSET $offset";

            // Get patients
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate last page
            $lastPage = ceil($total / $perPage);
            if ($page > $lastPage) {
                $page = $lastPage; // If the requested page exceeds last page, show the last page
            }

            echo json_encode([
                'success' => true,
                'data' => $patients,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $lastPage
                ]
            ]);
            break;

        case 'get_patient_details':
            $patient_id = $_GET['patient_id'] ?? null;

            if (!$patient_id) {
                echo json_encode(['success' => false, 'message' => 'Patient ID required']);
                exit();
            }

            // Get patient details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                echo json_encode(['success' => false, 'message' => 'Patient not found']);
                exit();
            }

            // Get patient's appointments with this doctor
            $stmt = $pdo->prepare("SELECT a.*, 
                                  CONCAT(d.name) as doctor_name
                                  FROM appointments a
                                  JOIN users d ON a.doctor_id = d.user_id
                                  WHERE a.doctor_id = ? AND a.patient_id = ?
                                  AND a.status IN ('completed', 'approved')
                                  ORDER BY a.appointment_date DESC");
            $stmt->execute([$doctor_id, $patient_id]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get medical reports with doctor name
            $stmt = $pdo->prepare("SELECT r.*, 
                                  CONCAT(d.name) as doctor_name
                                  FROM medical_reports r
                                  JOIN users d ON r.doctor_id = d.user_id
                                  WHERE r.doctor_id = ? AND r.patient_id = ?
                                  ORDER BY r.report_date DESC");
            $stmt->execute([$doctor_id, $patient_id]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'patient' => $patient,
                    'appointments' => $appointments,
                    'reports' => $reports
                ]
            ]);
            break;

        case 'get_report_details':
            $report_id = $_GET['report_id'] ?? null;

            if (!$report_id) {
                echo json_encode(['success' => false, 'message' => 'Report ID required']);
                exit();
            }

            // Get report details with doctor and patient info
            $stmt = $pdo->prepare("SELECT r.*, 
                                  CONCAT(d.name) as doctor_name,
                                  CONCAT(p.name) as patient_name
                                  FROM medical_reports r
                                  JOIN users d ON r.doctor_id = d.user_id
                                  JOIN users p ON r.patient_id = p.user_id
                                  WHERE r.report_id = ? AND r.doctor_id = ?");
            $stmt->execute([$report_id, $doctor_id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                echo json_encode(['success' => false, 'message' => 'Report not found or unauthorized']);
                exit();
            }

            echo json_encode([
                'success' => true,
                'data' => $report
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
