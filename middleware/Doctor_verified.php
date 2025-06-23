<?php
// Let auth_check.php handle session_start() and session restoration
require_once __DIR__ . '/../middleware/auth_check.php'; // ensures session is active and restored from cookie if needed
require_once __DIR__ . '/../config/db.php'; // getPDO()

function isDoctorVerified($doctorId)
{
    // Ensure the doctor ID is numeric for safety
    if (!is_numeric($doctorId)) {
        return false;
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ? AND role = 'doctor'");
    $stmt->execute([$doctorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && $row['is_verified'] == 1;
}
