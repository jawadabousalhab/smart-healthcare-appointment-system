<?php
require_once '../../middleware/auth_check.php';
require_once '../../config/db.php';

$remember = isset($_POST['remember']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';


    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $pdo = getPDO();

            // Prepare SQL to get user by email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Check if user is verified
                if (!$user['is_verified']) {
                    if ($user['role'] === 'doctor') {
                        session_start();
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['name'];
                        header('Location: doctor_verification.php');
                        exit();
                    } else {
                        $error = 'Please verify your email before logging in. <a href="resend-verification.php?email=' . urlencode($email) . '">Resend verification</a>';
                    }
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    // Set cookie if "Remember me" was checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + 60 * 60 * 24 * 30; // 30 days

                        setcookie('remember_token', $token, $expiry, '/');

                        // Store token in database
                        $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE user_id = ?");
                        $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user['user_id']]);
                    }

                    // Redirect based on role
                    redirectBasedOnRole($user['role']);
                }
            } else {
                $error = urlencode("Invalid Email or Password");
                header("Location: login.html?error=$error");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

// Function to redirect based on user role
function redirectBasedOnRole($role)
{
    switch ($role) {
        case 'admin':
            header('Location: ../admin/admin_dashboard.html');
            break;
        case 'it_admin':
            header('Location: ../it_admin/it_admin_dashboard.html');
            break;
        case 'doctor':
            header('Location: ../doctor/doctors_dashboard_html.php');
            break;
        case 'patient':
            header('Location: ../patient/patient_dashboard.html');
            break;
        default:
            header('Location: login.html');
    }
    exit();
}
if ($remember) {
    $token = bin2hex(random_bytes(32)); // 64-character token
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Store in database
    $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE user_id = ?");
    $stmt->execute([$token, $expiry, $user_id]);

    // Set as cookie
    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
}
if (isset($_SESSION['user_id'])) {
    // User is already logged in, redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /admin/admin_dashboard.html');
            break;
        case 'doctor':
            header('Location: /doctor/doctors_dashboard_html.php');
            break;
        case 'it_admin':
            header('Location: /it/it_admin_dashboard.html');
            break;
        case 'patient':
            header('Location: /patient/patient_dashboard.html');
            break;
        default:
            header('Location: login.html');
    }
    exit();
}

// If not logged in, check remember_token
if (isset($_COOKIE['remember_token'])) {
    require_once '../../config/db.php'; // Or correct path
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND token_expiry > NOW()");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        // Redirect again after restoring session
        switch ($user['role']) {
            case 'admin':
                header('Location: /admin/admin_dashboard.html');
                break;
            case 'doctor':
                header('Location: /doctor/doctors_dashboard_html.php');
                break;
            case 'it_admin':
                header('Location: /it/it_admin_dashboard.html');
                break;
            case 'patient':
                header('Location: /patient/patient_dashboard.html');
                break;
            default:
                header('Location: login.html');
        }
        exit();
    }
}
