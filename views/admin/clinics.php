<?php

require_once '../../config/db.php';
$pdo = getPDO();
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/auth_check.php';
header('Content-Type: application/json');
$auth = new AuthMiddleware();
$auth->checkAdmin();


// Handle different actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_stats':
            getStats();
            break;
        case 'get_clinics':
            getClinics();
            break;
        case 'get_assignments':
            getAssignments();
            break;
        case 'get_unassigned_clinics':
            getUnassignedClinics();
            break;
        case 'get_it_admins':
            GetItAdmins();
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_clinic':
            addClinic();
            break;
        case 'assign_it_admin':
            assignItAdmin();
            break;
        case 'unassign_it_admin':
            unassignItAdmin();
            break;
        case 'get_unassigned_clinics':
            getUnassignedClinics();
            break;
    }
}

function getStats()
{
    global $pdo;

    $stats = [
        'total_clinics' => $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn(),
        'assigned_clinics' => $pdo->query("SELECT COUNT(DISTINCT clinic_id) FROM clinic_it_admins")->fetchColumn(),
        'unassigned_clinics' => $pdo->query("SELECT COUNT(*) FROM clinics WHERE clinic_id NOT IN (SELECT DISTINCT clinic_id FROM clinic_it_admins)")->fetchColumn(),
        'active_it_admins' => $pdo->query("SELECT COUNT(DISTINCT it_admin_id) FROM clinic_it_admins")->fetchColumn()
    ];

    echo json_encode($stats);
}

function getClinics()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM clinics ORDER BY name");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}

function getAssignments()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT c.clinic_id, c.name as clinic_name, u.user_id, u.name as admin_name, u.email, a.assigned_at 
        FROM clinic_it_admins a
        JOIN clinics c ON a.clinic_id = c.clinic_id
        JOIN users u ON a.it_admin_id = u.user_id
        ORDER BY c.name, u.name
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}

function addClinic()
{
    global $pdo;

    $fullphonenumber = $_POST['phone_number_code'] . $_POST['phone_number'];
    $stmt = $pdo->prepare("INSERT INTO clinics (name, location, phone_number, map_coordinates, created_by) VALUES (?, ?, ?, ?, ?)");

    if ($stmt->execute([
        $_POST['name'],
        $_POST['location'],
        $fullphonenumber,
        $_POST['map_coordinates'],
        $_SESSION['user_id']
    ])) {
        echo json_encode(['success' => true]);
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'Clinic Updated ',
            'Clinic was added Succesfully',
            'success'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed']);
    }
}

function assignItAdmin()
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO clinic_it_admins (clinic_id, it_admin_id, assigned_at) VALUES (?, ?, NOW())");

    if ($stmt->execute([
        $_POST['clinic_id'],
        $_POST['it_admin_id']
    ])) {
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'IT assigned ',
            'IT assigned Succesfully',
            'warning'
        ]);
        echo json_encode(['success' => true]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'IT not assigned ',
            'IT failed to assign',
            'warning'
        ]);
        echo json_encode(['success' => false, 'message' => 'Assignment failed']);
    }
}
function getItAdmins()
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT user_id, name, email
        FROM users 
        WHERE role = 'it_admin' 
        ORDER BY name
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}

function unassignItAdmin()
{
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM clinic_it_admins WHERE clinic_id = ? AND it_admin_id = ?");

    if ($stmt->execute([
        $_POST['clinic_id'],
        $_POST['it_admin_id']
    ])) {
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'IT Unassigned ',
            'IT unassigned Succesfully',
            'success'
        ]);
        echo json_encode(['success' => true]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO notifications 
                      (user_id, title, message, type, is_read) 
                      VALUES (?, ?, ?, ?,0)");
        $stmt->execute([
            $_SESSION['user_id'],
            'IT still assigned ',
            'Unassigning failed',
            'warning'
        ]);
        echo json_encode(['success' => false, 'message' => 'Unassignment failed']);
    }
}
function getUnassignedClinics()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM clinics WHERE clinic_id NOT IN (SELECT clinic_id FROM clinic_it_admins) ORDER BY name");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}
