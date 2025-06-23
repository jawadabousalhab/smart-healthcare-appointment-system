<?php

require_once '../../config/db.php';
require '../../vendor/autoload.php';
require_once '../../middleware/auth_check.php';
require_once '../../middleware/Doctor_verified.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


use Mpdf\Mpdf;

if (!file_exists('../../vendor/autoload.php')) {
    die("Composer autoloader not found. Please run 'composer install' in the project root.");
}
if (!isset($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
}
if ($_SESSION['role'] !== 'doctor') {
    die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
}
if (!isDoctorVerified($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Doctor not verified."]));
}

$pdo = getPDO();
$doctor_id = $_SESSION['user_id'];

// Get list of patients for the current doctor
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_patients') {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.email 
            FROM users u
            INNER JOIN appointments a ON u.user_id = a.patient_id
            WHERE a.doctor_id = ?
            GROUP BY u.user_id
            ORDER BY u.name
        ");
        $stmt->execute([$doctor_id]);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "data" => $patients]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

// Get medical reports for selected patient
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['patient_id'])) {
    $patient_id = intval($_GET['patient_id']);

    try {
        $stmt = $pdo->prepare("
            SELECT mr.*, u.name AS doctor_name, 
                   a.appointment_date, a.appointment_time
            FROM medical_reports mr
            LEFT JOIN users u ON mr.doctor_id = u.user_id
            LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
            WHERE mr.patient_id = ?
            ORDER BY mr.report_date DESC, a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "data" => $reports]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

// Create new medical report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../vendor/autoload.php'; // For PDF generation

    $patient_id = intval($_POST['patient_id']);
    $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $diagnosis = $_POST['diagnosis'] ?? null;
    $prescription = $_POST['prescription'] ?? null;
    $report_type = $_POST['report_type'] ?? null;
    $notes = $_POST['notes'] ?? null;

    $file_path = null;

    // Handle file upload
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../../controllers/doctors/medical_reports/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION);
        $file_name = "report_" . time() . "_" . bin2hex(random_bytes(4)) . ".pdf"; // Force PDF extension
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['report_file']['tmp_name'], $file_path)) {
            echo json_encode(["status" => "error", "message" => "File upload failed."]);
            exit;
        }
    } else {
        // Generate PDF if no file was uploaded
        $upload_dir = "../../controllers/doctors/medical_reports/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = "report_" . time() . "_" . bin2hex(random_bytes(4)) . ".pdf";
        $file_path = $upload_dir . $file_name;

        // Generate PDF content
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);

        $html = '<h1>Medical Report</h1>';

        // Get patient details
        $patientStmt = $pdo->prepare("SELECT name, email, phone_number FROM users WHERE user_id = ?");
        $patientStmt->execute([$patient_id]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

        // Get doctor details
        $doctorStmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $doctorStmt->execute([$doctor_id]);
        $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);

        $html .= '<table style="width: 100%; margin-bottom: 20px;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%;"><strong>Patient:</strong> ' . htmlspecialchars($patient['name']) . '</td>';
        $html .= '<td><strong>Doctor:</strong> ' . htmlspecialchars($doctor['name']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td><strong>Contact:</strong> ' . htmlspecialchars($patient['email']);
        if (!empty($patient['phone'])) {
            $html .= ' | ' . htmlspecialchars($patient['phone']);
        }
        $html .= '</td>';
        $html .= '<td><strong>Date:</strong> ' . date('Y-m-d') . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        if ($appointment_id) {
            $html .= '<p><strong>Related Appointment ID:</strong> ' . $appointment_id . '</p>';
        }

        // Add medical history section
        $html .= '<h3>Medical History</h3>';
        $historyStmt = $pdo->prepare("SELECT diagnosis, prescription, report_date FROM medical_reports WHERE patient_id = ? ORDER BY report_date DESC LIMIT 5");
        $historyStmt->execute([$patient_id]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($history)) {
            $html .= '<ul>';
            foreach ($history as $record) {
                $html .= '<li>';
                $html .= '<strong>' . $record['report_date'] . ':</strong> ' . htmlspecialchars($record['diagnosis']);
                if ($record['prescription']) {
                    $html .= '<br><em>Prescription:</em> ' . htmlspecialchars($record['prescription']);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>No previous medical history found.</p>';
        }

        if ($appointment_id) {
            $html .= '<p><strong>Related Appointment ID:</strong> ' . $appointment_id . '</p>';
        }

        if ($diagnosis) {
            $html .= '<h3>Diagnosis</h3>';
            $html .= '<p>' . nl2br(htmlspecialchars($diagnosis)) . '</p>';
        }

        if ($prescription) {
            $html .= '<h3>Prescription</h3>';
            $html .= '<p>' . nl2br(htmlspecialchars($prescription)) . '</p>';
        }

        if ($notes) {
            $html .= '<h3>Notes</h3>';
            $html .= '<p>' . nl2br(htmlspecialchars($notes)) . '</p>';
        }

        $mpdf->WriteHTML($html);
        $mpdf->Output($file_path, \Mpdf\Output\Destination::FILE);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO medical_reports 
                (doctor_id, patient_id, appointment_id, diagnosis, prescription, report_type, notes, file_path, report_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([
            $doctor_id,
            $patient_id,
            $appointment_id,
            $diagnosis,
            $prescription,
            $report_type,
            $notes,
            $file_path
        ]);

        echo json_encode([
            "status" => "success",
            "message" => "Report created successfully!",
            "report_id" => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        // Delete uploaded file if database insert failed
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        echo json_encode(["status" => "error", "message" => "Failed to create report: " . $e->getMessage()]);
    }
    exit;
}
echo json_encode(["status" => "error", "message" => "Invalid request."]);
