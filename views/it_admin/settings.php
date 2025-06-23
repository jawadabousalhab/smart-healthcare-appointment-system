<?php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';

require_once '../../middleware/auth_check.php';
// Verify user is IT Admin
if ($_SESSION['role'] != 'it_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$pdo = getPDO();

// Get the requested action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Handle different actions
switch ($action) {
    case 'get_user_profile':
        handleGetUserProfile($pdo);
        break;
    case 'save_user_profile':
        handleSaveUserProfile($pdo);
        break;
    case 'get_settings':
        handleGetSettings($pdo);
        break;
    case 'save_settings':
        handleSaveSettings($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleGetSettings($pdo)
{
    // In a real application, you would fetch these from your database
    $response = [
        'success' => true,
        'data' => [
            'general' => [
                'email' => true,
                'phone' => true,
                'UTC' => "utc"
            ],
            'notifications' => [
                'email_system' => true,
                'email_security' => true,
                'email_updates' => false,
                'app_system' => true,
                'app_activity' => true
            ],
            'security' => [
                'session_timeout' => 30,
                'two_factor' => false
            ],
            'backup' => [
                'backup_schedule' => 'weekly',
                'backup_retention' => 30,
                'backup_auto' => true,
                'backup_location' => '/var/backups/smarthealth'
            ]
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
}
function handleGetUserProfile($pdo)
{
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("SELECT email, phone_number, profile_picture FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $response = [
                'success' => true,
                'data' => [
                    'profile_picture' =>  $user['profile_picture']
                        ? '../../uploads/profiles/' . $user['profile_picture']
                        : '../../assets/images/default-profile.png',
                    'email' => $user['email'],
                    'phone' => $user['phone_number'],
                    'timezone' => 'UTC' // You would get this from database in real implementation
                ]
            ];
        } else {
            $response = ['success' => false, 'message' => 'User not found'];
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}

function handleSaveUserProfile($pdo)
{
    $userId = $_SESSION['user_id'];
    $email = $_POST['email'] ?? '';
    $country_code = $_POST['country_code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $full_phone = $country_code . $phone;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }

    // Handle file upload
    $profilePicture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileExt = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $fileName = 'profile_' . $userId . '_' . time() . '.' . $fileExt;
        $targetPath = $uploadDir . $fileName;
        $profilePicture = $fileName;

        // Validate image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_picture']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                $profilePicture = $fileName;

                // Delete old profile picture if exists
                $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $oldPicture = $stmt->fetchColumn();

                if ($oldPicture && file_exists($uploadDir . $oldPicture)) {
                    unlink($uploadDir . $oldPicture);
                }
            }
        }
    }

    try {
        // Update user profile
        if ($profilePicture) {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone_number = ?, profile_picture = ? WHERE user_id = ?");
            $stmt->execute([$email, $full_phone, $profilePicture, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
            $stmt->execute([$email, $full_phone, $userId]);
        }

        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'profile_picture' => $profilePicture ? '../../uploads/profiles/' . $profilePicture : null
            ]
        ];
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}

function handleSaveSettings($pdo)
{
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $userId = $_SESSION['user_id'];

    // Debugging: Check if the form data is received correctly
    error_log('Received type: ' . $type);
    error_log('Received POST data: ' . print_r($_POST, true));  // Log the POST data for debugging
    error_log('User ID: ' . $userId);

    try {
        $type = isset($_POST['type']) ? $_POST['type'] : '';

        // Ensure the type is valid and recognized
        if (!in_array($type, ['general', 'notifications', 'security', 'backup'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid settings type']);
            exit();
        }
        switch ($type) {
            case 'general':
                // Process general settings
                $response = ['success' => true, 'message' => 'General settings saved successfully'];
                break;

            case 'notifications':
                // Process notification settings
                $response = ['success' => true, 'message' => 'Notification settings saved successfully'];
                break;

            case 'security':
                // Process password change and two-factor settings
                $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
                $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

                if ($newPassword && $newPassword === $confirmPassword) {
                    // Hash password and save to database
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    // SQL Query to update password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $userId]);

                    // Check if the update was successful
                    if ($stmt->rowCount() > 0) {
                        $response = ['success' => true, 'message' => 'Password updated successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Password update failed (no changes detected)'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Passwords do not match or are empty'];
                }
                break;

            case 'backup':
                // Process backup settings
                $response = ['success' => true, 'message' => 'Backup settings saved successfully'];
                break;

            default:
                $response = ['success' => false, 'message' => 'Invalid settings type'];
                break;
        }
    } catch (Exception $e) {
        // Log the error message for debugging
        error_log('Error: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'An error occurred while saving settings'];
    }

    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
}
