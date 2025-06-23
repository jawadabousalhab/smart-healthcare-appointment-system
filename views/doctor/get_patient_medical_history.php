<?php

require_once '../../config/db.php';
require_once '../../middleware/check_auth.php';


if (!isset($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
}


$patient_id = intval($_GET['patient_id']);

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT diagnosis, prescription, report_date as date 
        FROM medical_reports 
        WHERE patient_id = ? 
        ORDER BY report_date DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $history]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
