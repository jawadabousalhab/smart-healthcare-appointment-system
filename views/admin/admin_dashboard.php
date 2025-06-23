<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';


$auth = new AuthMiddleware();
$auth->checkAdmin();

// Verify super admin access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.html');
    exit();
}

// Get database connection
$pdo = getPDO();

// Fetch all dashboard statistics
try {
    // Clinic stats
    $totalClinics = $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn();

    // Admin stats
    $activeAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    // IT Admin stats
    $itAdminsCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'it_admin'")->fetchColumn();

    // System health
    $systemHealth = [
        'database' => 'healthy', // You can add actual checks here
        'server' => 'healthy'
    ];

    // Recent admins (last 5)
    $admins = $pdo->query("
        SELECT user_id, name, email, created_at 
        FROM users 
        WHERE role = 'admin'
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent IT admins (last 5)
    $itAdmins = $pdo->query("
        SELECT user_id, name, email, created_at 
        FROM users 
        WHERE role = 'it_admin'
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent clinics (last 5)
    $clinics = $pdo->query("
        SELECT c.clinic_id, c.name, c.location,  
               COUNT(cd.doctor_id) as doctor_count,
               u.name as created_by
        FROM clinics c
        LEFT JOIN clinic_doctors cd ON c.clinic_id = cd.clinic_id
        LEFT JOIN users u ON c.created_by = u.user_id
        GROUP BY c.clinic_id
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent activity logs (last 10)
    $activityLogs = $pdo->query("
        SELECT log_id, action, description, created_at 
        FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Current admin data for display
    $admin = [
        'name' => $_SESSION['name'],
        'profile_picture' => $_SESSION['profile_picture'] ?? '../../assets/images/default-profile.png'
    ];

    // Prepare stats array for the view
    $stats = [
        'total_clinics' => $totalClinics,
        'active_admins' => $activeAdmins,
        'it_admins' => $itAdminsCount,
        'system_health' => $systemHealth
    ];
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Error loading dashboard data. " . $e->getMessage()); // TEMPORARY: show detailed error
}

echo json_encode([
    'stats' => $stats,
    'recent_admins' => $admins,
    'recent_it_admins' => $itAdmins,
    'recent_clinics' => $clinics,
    'recent_logs' => $activityLogs,
    'admin' => $admin,
    'unread_notifications' => 0 // Placeholder if you plan to support this
]);
exit;
