<?php
// settings.php
require_once '../../config/db.php';
require_once '../../middleware/AuthMiddleware.php';
header('Content-Type: application/json');
require_once '../../middleware/auth_check.php';
$auth = new AuthMiddleware();
$auth->checkAdmin();


// Get database connection
$pdo = getPDO();

// Get the current admin ID from session
$adminId = $_SESSION['user_id'];

// Handle different actions
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_admin_profile':
            getAdminProfile($pdo, $adminId);
            break;

        case 'update_profile':
            updateAdminProfile($pdo, $adminId);
            break;

        case 'update_password':
            updateAdminPassword($pdo, $adminId);
            break;

        case 'update_system_settings':
            updateSystemSettings($pdo, $adminId);
            break;

        case 'update_notification_settings':
            updateNotificationSettings($pdo, $adminId);
            break;

        case 'get_system_settings':
            getSystemSettings($pdo);
            break;



        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get admin profile data
 */
function getAdminProfile($pdo, $adminId)
{

    $stmt = $pdo->prepare("SELECT user_id, name, email, phone_number, profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new Exception("Admin not found");
    }

    // Set default profile picture if empty
    if (empty($admin['profile_picture'])) {
        $admin['profile_picture'] = '/assets/images/default-profile.png';
    } else {
        // Ensure the path is web-accessible
        $admin['profile_picture'] =  ltrim($admin['profile_picture'], '/');
    }

    echo json_encode($admin);
}

/**
 * Update admin profile
 */
function updateAdminProfile($pdo, $adminId)
{
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $country_code = $_POST['country_code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $full_phone = $country_code . $phone;
    // Basic validation
    if (empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        return;
    }

    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $adminId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        return;
    }

    // Handle file upload
    $profilePicture = null;
    if (!empty($_FILES['avatar']['name'])) {
        $uploadDir = '../../uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExt;
        $targetPath = $uploadDir . $fileName;
        $profilePicture = '../../uploads/profiles/' . $fileName;
        // Check if image file is actual image
        $check = getimagesize($_FILES['avatar']['tmp_name']);
        if ($check === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File is not an image']);
            return;
        }

        // Check file size (max 2MB)
        if ($_FILES['avatar']['size'] > 2000000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File is too large (max 2MB)']);
            return;
        }

        // Allow certain file formats
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($fileExt), $allowedExts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed']);
            return;
        }

        // Try to upload file
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
            $profilePicture = $targetPath;

            // Delete old profile picture if it exists and isn't the default
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $stmt->execute([$adminId]);
            $oldPicture = $stmt->fetchColumn();

            if ($oldPicture && $oldPicture !== '../../assets/images/default-profile.png' && file_exists($oldPicture)) {
                unlink($oldPicture);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            return;
        }
        // Handle file upload
        $profilePicture = null;
        if (!empty($_FILES['avatar']['name'])) {
            $uploadDir = '../../uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $fileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;

            // Web accessible path (relative to your public directory)
            $webPath = '../../uploads/profiles/' . $fileName;

            // ... [file validation code remains the same]

            // Try to upload file
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $profilePicture = $webPath; // Store the web-accessible path

                // Delete old profile picture if it exists and isn't the default
                $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
                $stmt->execute([$adminId]);
                $oldPicture = $stmt->fetchColumn();

                if ($oldPicture && $oldPicture !== '/assets/images/default-profile.png' && file_exists('../../' . ltrim($oldPicture, '/'))) {
                    unlink('../../' . ltrim($oldPicture, '/'));
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                return;
            }
        }
    }

    // Update database
    if ($profilePicture) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, profile_picture = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $full_phone, $profilePicture, $adminId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone_number = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $full_phone, $adminId]);
    }

    // Update session data
    $_SESSION['name'] = $name;
    if ($profilePicture) {
        $_SESSION['profile_picture'] = $profilePicture;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'profile_picture' => $profilePicture ? str_replace('../../', '/', '../../uploads/profiles/...') : ($_SESSION['profile_picture'] ?? null)

    ]);
}

/**
 * Update admin password
 */
function updateAdminPassword($pdo, $adminId)
{
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        return;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        return;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$adminId]);
    $hashedPassword = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $hashedPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }

    // Update password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$newHashedPassword, $adminId]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
}

/**
 * Update system settings
 */
function updateSystemSettings($pdo, $adminId)
{
    // Only allow super admins to update system settings
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only super admins can update system settings']);
        return;
    }

    $maintenanceMode = isset($_POST['maintenance_mode']) ? 'enabled' : 'disabled';
    $backupSchedule = $_POST['backup_schedule'] ?? 'monthly';

    // Validate backup schedule
    if (!in_array($backupSchedule, ['daily', 'weekly', 'monthly'])) {
        $backupSchedule = 'monthly';
    }

    // Update settings in database
    $stmt = $pdo->prepare("UPDATE system_settings SET maintenance_mode = ?, backup_schedule = ?, updated_by = ? WHERE id = 1");
    $stmt->execute([$maintenanceMode, $backupSchedule, $adminId]);

    echo json_encode([
        'success' => true,
        'message' => 'System settings updated successfully',
        'settings' => [
            'maintenance_mode' => $maintenanceMode,
            'backup_schedule' => $backupSchedule
        ]
    ]);
}

/**
 * Get current system settings
 */
function getSystemSettings($pdo)
{
    $stmt = $pdo->prepare("SELECT maintenance_mode, backup_schedule FROM system_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Initialize default settings if they don't exist
        $stmt = $pdo->prepare("INSERT INTO system_settings (id, maintenance_mode, backup_schedule) VALUES (1, 'disabled', 'monthly')");
        $stmt->execute();

        $settings = [
            'maintenance_mode' => 'disabled',
            'backup_schedule' => 'monthly'
        ];
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
}



/**
 * Update notification settings
 */
function updateNotificationSettings($pdo, $adminId)
{
    $settings = [
        'email_system' => isset($_POST['email_system']) ? 1 : 0,
        'email_security' => isset($_POST['email_security']) ? 1 : 0,
        'email_updates' => isset($_POST['email_updates']) ? 1 : 0,
        'app_system' => isset($_POST['app_system']) ? 1 : 0,
        'app_security' => isset($_POST['app_security']) ? 1 : 0,
        'app_activity' => isset($_POST['app_activity']) ? 1 : 0
    ];

    // Convert settings to JSON
    $settingsJson = json_encode($settings);

    // Update notification settings in database
    $stmt = $pdo->prepare("UPDATE users SET notification_settings = ? WHERE user_id = ?");
    $stmt->execute([$settingsJson, $adminId]);

    // Update session with new settings
    $_SESSION['notification_settings'] = $settings;

    echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully']);
}
