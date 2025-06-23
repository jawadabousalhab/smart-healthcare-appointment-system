<?php

require_once '../../config/db.php';
require_once '../../middleware/check_auth.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
}

if (!isset($_GET['patient_id'])) {
    die(json_encode(["status" => "error", "message" => "Patient ID is required."]));
}


$pdo = getPDO();
$patient_id = intval($_GET['patient_id']);
$doctor_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT appointment_id, appointment_date, reason 
        FROM appointments 
        WHERE patient_id = ? AND doctor_id = ?
        ORDER BY appointment_date DESC
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $appointments]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
