<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // User is already logged in, redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: views/admin/admin_dashboard.html');;
            break;
        case 'doctor':
            header('Location: views/doctor/doctors_dashboard_html.php');
            break;
        case 'it_admin':
            header('Location: views/it/it_admin_dashboard.html');
            break;
        case 'patient':
            header('Location: views/patient/patient_dashboard.html');
            break;
        default:
            header('Location: views/dashboard.php');
    }
    exit();
}

// If not logged in, check remember_token
if (isset($_COOKIE['remember_token'])) {
    require_once 'config/db.php'; // Or correct path
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
                header('Location: views/admin/admin_dashboard.html');
                break;
            case 'doctor':
                header('Location: views/doctor/doctors_dashboard_html.php');
                break;
            case 'it_admin':
                header('Location: views/it/it_admin_dashboard.html');
                break;
            case 'patient':
                header('Location: views/patient/patient_dashboard.html');
                break;
            default:
                header('Location: index.php');
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Healthcare Appointment System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>SmartHealth</span>
                </div>
                <ul class="nav-links">
                    <li><a href="views/auth/login.html" class="btn btn-outline">Login</a></li>
                    <li><a href="views/auth/register.html" class="btn btn-primary">Register</a></li>
                </ul>
                <div class="hamburger">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Smart Healthcare Appointment System</h1>
                    <p class="lead">Revolutionizing healthcare access with AI-powered appointment scheduling and
                        management for clinics and patients.</p>
                </div>
                <div class="hero-image">
                    <img src="assets/images/hero-doctor.png" alt="Doctor using digital healthcare system">
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features">
            <div class="container">
                <h2>Key Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h3>AI-Powered Scheduling</h3>
                        <p>Intelligent appointment management with automatic prioritization and smart scheduling.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Role-Based Access</h3>
                        <p>Secure dashboards for patients, doctors, and administrators with appropriate permissions.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Clinic Locator</h3>
                        <p>Find healthcare providers near you with our integrated map system.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Real-Time Notifications</h3>
                        <p>Get instant updates about your appointments and important health information.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <h3>Digital Records</h3>
                        <p>Access your medical reports and prescriptions anytime, anywhere.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-comment-medical"></i>
                        </div>
                        <h3>AI Assistant</h3>
                        <p>Our chatbot helps you find the right doctor and answers your questions.</p>
                    </div>
                </div>
            </div>
        </section>



        <script src="assets/js/main.js"></script>
</body>

</html>