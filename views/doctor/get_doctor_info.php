<?php

require_once '../../config/db.php';

require_once '../../middleware/auth_check.php';


if (!isset($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
}


$doctor_id = $_SESSION['user_id'];

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT name FROM users WHERE user_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $doctor]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
