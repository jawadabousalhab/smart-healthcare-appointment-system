<?php
session_start();
require_once  '../../config/db.php';
require_once '../../utils/mailer.php';



$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_code = filter_input(INPUT_POST, 'phone_code', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $full_phone = $phone_code . $phone; // Combine phone code and number
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $role = 'patient'; // Default role
    $terms = isset($_POST['terms']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || empty($address)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!$terms) {
        $error = 'You must agree to the terms and conditions';
    } else {
        try {
            $pdo = getPDO();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = 'Email address is already registered';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Generate 6-digit verification code
                $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone_number, address, password, role, verification_code) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $full_phone, $address, $hashed_password, $role, $verification_code]);

                // Store email in session for verification
                $_SESSION['verify_email'] = $email;

                // Send verification email
                $subject = "Your Verification Code";
                $message = "Dear $name,<br><br>";
                $message .= "Thank you for registering with Smart Healthcare Appointment System.<br>";
                $message .= "Your verification code is: <strong>$verification_code</strong><br><br>";
                $message .= "Please enter this code on the verification page to complete your registration.<br><br>";
                $message .= "If you did not create an account, please ignore this email.<br><br>";
                $message .= "Best regards,<br>Smart Health Team";

                if (sendEmail($email, $subject, $message)) {
                    // Redirect to verification page
                    header('Location: verify.html?email=' . urlencode($email));
                    exit();
                } else {
                    $error = 'Registration completed but we couldn\'t send the verification email. Please contact support.';
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'An error occurred during registration. Please try again later.';
        }
    }
}
