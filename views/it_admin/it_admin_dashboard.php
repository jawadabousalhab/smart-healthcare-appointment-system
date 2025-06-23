<?php

require_once '../../config/db.php';

require_once '../../middleware/auth_check.php';
// Database configuration

// Check if user is logged in and is an IT admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_admin') {
    header('Location: ../login.html');
    exit();
}

$pdo = getPDO();

// Get IT admin details
$itAdminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$itAdminId]);
$itAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get counts for dashboard
$counts = [];
$queries = [
    'clinic_it_admins' => "SELECT COUNT(*) FROM clinic_it_admins where it_admin_id = $itAdminId",
    'clinics' => "SELECT COUNT(DISTINCT c.clinic_id)
                  FROM clinics c
                  JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id
                  WHERE cia.it_admin_id = $itAdminId",

    'activity_logs' => "SELECT COUNT(*) FROM activity_logs",
    'ai_logs' => "SELECT COUNT(*) FROM ai_logs"
];
foreach ($queries as $key => $query) {
    $counts[$key] = $pdo->query($query)->fetchColumn();
}
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM users u
    JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
    JOIN clinic_it_admins cia ON cd.clinic_id = cia.clinic_id
    WHERE cia.it_admin_id = ? and u.role='doctor'");
$stmt->execute([$itAdminId]);
$counts['doctors'] = $stmt->fetchColumn();


// Get recent activity logs
$recentActivity = [];
$stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userStmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
    $userStmt->execute([$row['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $recentActivity[] = [
        'log_id' => $row['log_id'],
        'user_name' => $user['name'],
        'action' => $row['action'],
        'description' => $row['description'],
        'timestamp' => $row['created_at']
    ];
}
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_stats') {
        header('Content-Type: application/json');
        echo json_encode([
            'counts' => $counts,
            'recent_activity' => $recentActivity
        ]);
        exit();
    }
}

// Get assigned clinics for this IT admin
$assignedClinics = [];
$stmt = $pdo->prepare("SELECT c.* FROM clinics c 
                      JOIN clinic_it_admins cia ON c.clinic_id = cia.clinic_id 
                      WHERE cia.it_admin_id = ?");
$stmt->execute([$itAdminId]);
$assignedClinics = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Prepare data for the view
$dashboardData = [
    'itAdmin' => $itAdmin,
    'counts' => $counts,
    'recentActivity' => $recentActivity,
    'assignedClinics' => $assignedClinics,
    'error' => $error ?? null,
    'success_message' => $_SESSION['success_message'] ?? null
];

// Clear the success message after displaying it
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}
