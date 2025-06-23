<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../middleware/check_auth.php';

$pdo = getPDO();

// Check if user is logged in and is an IT admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {

    $itAdminId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM clinics c
        JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
        WHERE cia.it_admin_id = ?
        ORDER BY c.name ASC
    ");
    $stmt->execute([$itAdminId]);
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($clinics);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
