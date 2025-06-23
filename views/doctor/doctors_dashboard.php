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
        case 'get_dashboard_data':
            // Get doctor details
            $stmt = $pdo->prepare("SELECT name, email, phone_number, profile_picture FROM users WHERE user_id = ?");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if profile picture exists, else use a default one
            $profile_picture = $doctor['profile_picture'] ? '../../uploads/profiles/doctors/' . $doctor['profile_picture'] : '../../assets/images/default-profile.png';

            // Include the profile picture path in the response data
            $doctor['profile_picture_path'] = $profile_picture;

            // Get today's appointments count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments 
                                 WHERE doctor_id = ? AND appointment_date = CURDATE() 
                                 AND status = 'approved'");
            $stmt->execute([$doctor_id]);
            $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get total patients
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM appointments 
                                 WHERE doctor_id = ?");
            $stmt->execute([$doctor_id]);
            $total_patients = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get next appointment
            $stmt = $pdo->prepare("SELECT a.appointment_time, p.name as patient_name
                                 FROM appointments a
                                 JOIN users p ON a.patient_id = p.user_id
                                 WHERE a.doctor_id = ? AND a.status = 'approved'
                                 AND (a.appointment_date > CURDATE() OR 
                                     (a.appointment_date = CURDATE() AND a.appointment_time > CURTIME()))
                                 ORDER BY a.appointment_date, a.appointment_time
                                 LIMIT 1");
            $stmt->execute([$doctor_id]);
            $next_appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get upcoming appointments (next 5)
            $stmt = $pdo->prepare("SELECT a.*, p.name as patient_name 
                                 FROM appointments a
                                 JOIN users p ON a.patient_id = p.user_id
                                 WHERE a.doctor_id = ? AND a.status = 'approved'
                                 AND (a.appointment_date > CURDATE() OR 
                                     (a.appointment_date = CURDATE() AND a.appointment_time > CURTIME()))
                                 ORDER BY a.appointment_date, a.appointment_time
                                 LIMIT 5");
            $stmt->execute([$doctor_id]);
            $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'doctor' => $doctor,
                    'stats' => [
                        'today_appointments' => $today_appointments['count'],
                        'total_patients' => $total_patients['count'],
                        'next_appointment' => $next_appointment ? date('h:i A', strtotime($next_appointment['appointment_time'])) : 'None',
                        'next_patient' => $next_appointment['patient_name'] ?? ''
                    ],
                    'upcoming_appointments' => $upcoming_appointments
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
