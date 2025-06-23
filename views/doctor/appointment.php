<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
require_once '../../middleware/Doctor_verified.php';

include 'email_function.php';
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
$action = $_REQUEST['action'] ?? '';

try {
    $pdo = getPDO();

    switch ($action) {
        case 'get_appointments':
            $status = $_GET['status'] ?? 'all';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            // Base query
            $query = "SELECT a.*, p.name as patient_name, p.profile_picture as patient_photo, 
                 c.name as clinic_name
          FROM appointments a
          JOIN users p ON a.patient_id = p.user_id
          JOIN clinics c ON a.clinic_id = c.clinic_id
          WHERE a.doctor_id = ?";

            $params = [$doctor_id];

            // Filter by status
            if ($status !== 'all') {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }
            if (!empty($search)) {
                $query .= " AND (p.name LIKE ? OR c.name LIKE ? OR a.reason LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM ($query) as total";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            // Add pagination and sorting
            $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC 
                       LIMIT $perPage OFFSET $offset";


            // Get appointments
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $appointments,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'update_status':
            $appointmentId = $_POST['appointment_id'] ?? null;
            $status = $_POST['status'] ?? null;

            if (!$appointmentId || !$status) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }

            // First get the appointment details to check for conflicts
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit();
            }

            // Check for conflicting appointments only when approving
            if ($status === 'approved') {
                $conflictCheck = $pdo->prepare("
            SELECT a.*, u.email, u.name 
            FROM appointments a
            JOIN users u ON a.patient_id = u.user_id
            WHERE a.appointment_id != ?
            AND a.doctor_id = ?
            AND a.appointment_date = ?
            AND a.appointment_time = ?
            AND a.status IN ('approved', 'pending')
        ");
                $conflictCheck->execute([
                    $appointmentId,
                    $doctor_id,
                    $appointment['appointment_date'],
                    $appointment['appointment_time']
                ]);
                $conflictingAppointments = $conflictCheck->fetchAll(PDO::FETCH_ASSOC);

                // If conflicts found, send reschedule requests
                if (!empty($conflictingAppointments)) {
                    // Update the current appointment to approved
                    $stmt = $pdo->prepare("UPDATE appointments SET status = ? 
                                  WHERE appointment_id = ? AND doctor_id = ?");
                    $stmt->execute([$status, $appointmentId, $doctor_id]);

                    // Update conflicting appointments to "asked to reschedule"
                    foreach ($conflictingAppointments as $conflict) {
                        // Update status
                        $updateStmt = $pdo->prepare("UPDATE appointments SET status = 'asked to reschedule' 
                                           WHERE appointment_id = ?");
                        $updateStmt->execute([$conflict['appointment_id']]);

                        // Send reschedule email (implement this function)
                        sendRescheduleEmail(
                            $conflict['email'],
                            $conflict['name'],
                            $appointment['appointment_date'],
                            $appointment['appointment_time'],
                            $doctor_id
                        );
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Appointment approved. Conflicting appointments have been asked to reschedule.'
                    ]);
                    exit();
                }
            }

            // No conflicts or not approving - proceed normally
            $stmt = $pdo->prepare("UPDATE appointments SET status = ? 
                          WHERE appointment_id = ? AND doctor_id = ?");
            $stmt->execute([$status, $appointmentId, $doctor_id]);

            echo json_encode(['success' => true, 'message' => 'Status updated']);
            break;
        case 'reschedule':
            $appointmentId = $_POST['appointment_id'] ?? null;
            $newDate = $_POST['new_date'] ?? null;
            $newTime = $_POST['new_time'] ?? null;

            if (!$appointmentId || !$newDate || !$newTime) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }

            try {
                // First get the appointment details to send email
                $stmt = $pdo->prepare("SELECT a.*, u.email, u.name 
                              FROM appointments a
                              JOIN users u ON a.patient_id = u.user_id
                              WHERE a.appointment_id = ?");
                $stmt->execute([$appointmentId]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$appointment) {
                    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                    exit();
                }

                // Update the appointment
                $updateStmt = $pdo->prepare("UPDATE appointments 
                                    SET appointment_date = ?, 
                                        appointment_time = ?,
                                        status = 'rescheduled'
                                    WHERE appointment_id = ?");
                $updateStmt->execute([$newDate, $newTime, $appointmentId]);

                // Send confirmation email to patient
                sendRescheduleEmail(
                    $appointment['email'],
                    $appointment['name'],
                    $newDate,
                    $newTime,
                    $doctor_id
                );

                echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
        case 'request_reschedule':
            $appointmentId = $_POST['appointment_id'] ?? null;

            if (!$appointmentId) {
                echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
                exit();
            }

            try {
                // Get appointment details including patient info
                $stmt = $pdo->prepare("SELECT a.*, u.email, u.name 
                              FROM appointments a
                              JOIN users u ON a.patient_id = u.user_id
                              WHERE a.appointment_id = ? AND a.doctor_id = ?");
                $stmt->execute([$appointmentId, $doctor_id]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$appointment) {
                    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                    exit();
                }

                // Update status to indicate reschedule requested
                $updateStmt = $pdo->prepare("UPDATE appointments 
                                    SET status = 'reschedule_requested'
                                    WHERE appointment_id = ?");
                $updateStmt->execute([$appointmentId]);

                // Send reschedule request email
                sendRescheduleRequestEmail(
                    $appointment['email'],
                    $appointment['name'],
                    $appointment['appointment_date'],
                    $appointment['appointment_time'],
                    $doctor_id,
                    $appointmentId
                );

                echo json_encode(['success' => true, 'message' => 'Reschedule request sent']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
        case 'get_appointment_details':
            $appointmentId = $_POST['appointment_id'] ?? null;

            if (!$appointmentId) {
                echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
                exit();
            }

            try {
                $stmt = $pdo->prepare("SELECT a.*, p.name as patient_name, p.email as patient_email, 
                               p.profile_picture as patient_photo, c.name as clinic_name
                        FROM appointments a
                        JOIN users p ON a.patient_id = p.user_id
                        JOIN clinics c ON a.clinic_id = c.clinic_id
                        WHERE a.appointment_id = ? AND a.doctor_id = ?");
                $stmt->execute([$appointmentId, $doctor_id]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$appointment) {
                    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                    exit();
                }

                echo json_encode([
                    'success' => true,
                    'data' => $appointment
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;


        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
