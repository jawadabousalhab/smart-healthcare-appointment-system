<?php

require_once '../../config/db.php';
require_once '../../middleware/auth_check.php';
require_once '../../middleware/Doctor_verified.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}
if (!isDoctorVerified($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Doctor not verified']);
    exit;
}

$pdo = getPDO();
$doctor_id = $_SESSION['user_id'];

// Get doctor profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get basic profile info
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
            exit;
        }

        // Get stats
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total_patients FROM appointments WHERE doctor_id = :id");
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();
        $total_patients = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE doctor_id = :id");
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();
        $total_appointments = $stmt->fetchColumn();

        // Combine all data
        $response = [
            'status' => 'success',
            'data' => [
                'profile' => $profile,
                'stats' => [
                    'total_patients' => $total_patients,
                    'total_appointments' => $total_appointments
                ]
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Update doctor profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $full_name = $data['first_name'] . ' ' . $data['last_name'];
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $full_phone = $data['countrycode'] . $data['phone'];
        $address = filter_var($data['address'], FILTER_SANITIZE_STRING);
        $specialization = filter_var($data['specialization'], FILTER_SANITIZE_STRING);
        // Update users table (basic info)
        $stmt = $pdo->prepare("UPDATE users SET 
                              name = :name,
                              email = :email,
                              phone_number = :phone_number,
                              address = :address,
                              specialization = :specialization
                              WHERE user_id = :id");
        $stmt->bindParam(':name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number',  $full_phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':specialization', $specialization);
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'change_password') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['current_password']) || !isset($data['new_password']) || !isset($data['confirm_password'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        exit;
    }

    if ($data['new_password'] !== $data['confirm_password']) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
        exit;
    }

    if (strlen($data['new_password']) < 8) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = :id");
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($data['current_password'], $user['password'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $new_password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE user_id = :id");
        $stmt->bindParam(':password', $new_password_hash);
        $stmt->bindParam(':id', $doctor_id);
        $stmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Password changed successfully']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Update profile photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_photo') {
    if (!isset($_FILES['profile_photo'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['profile_photo'];

    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, and GIF files are allowed']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB max
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'File size must be less than 2MB']);
        exit;
    }

    try {
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $doctor_id . '_' . time() . '.' . $extension;
        $upload_path = '../../uploads/profiles/doctors/' . $filename;



        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Update database with new photo path
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = :photo WHERE user_id = :id");
            $stmt->bindParam(':photo', $filename);
            $stmt->bindParam(':id', $doctor_id);
            $stmt->execute();

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Profile photo updated', 'photo_path' => $filename]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
