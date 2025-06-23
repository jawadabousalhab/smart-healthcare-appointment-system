<?php
require_once '../../config/db.php';
require_once '../../middleware/auth_check.php';
require_once '../../middleware/Doctor_verified.php';
if (!isset($_SESSION['user_id']) || !isDoctorVerified($_SESSION['user_id'])) {
    header('Location: ../auth/doctor_verification.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Smart Healthcare</title>
    <link rel="stylesheet" href="../../assets/css/doctor_css/doctor_dashboard.css">
    <link rel="stylesheet" href="../../assets/css/doctor_css/appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ai-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo">
                <h1>Smart Healthcare</h1>
                <p>Doctor Dashboard</p>
            </div>
            <nav>
                <ul>
                    <li class="active"><a href="#" id="dashboard-link"><i
                                class="fas fa-tachometer-alt"></i>
                            Dashboard</a></li>
                    <li><a href="appointment.html" id="appointment-link"><i class="fas fa-calendar-check"></i>
                            Appointments</a></li>
                    <li><a href="patients.html" id="patients-link"><i class="fas fa-user-friends"></i> Patients</a>
                    </li>
                    <li><a href="medical_reports.html" id="reports-link"><i class="fas fa-file-medical"></i> Medical
                            Reports</a></li>
                    <li><a href="schedule.html" id="schedule-link"><i class="fas fa-calendar-plus"></i> My Schedule</a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <header>
                <input type="hidden" id="doctor-id" value="<?php echo $_SESSION['user_id']; ?>">
                <div class="search-bar">
                    <input type="text" id="global-search" placeholder="Search patients, appointments...">
                    <button id="search-btn"><i class="fas fa-search"></i></button>
                </div>
                <style>
                    #ai-modal-btn {
                        position: relative;
                        cursor: pointer;
                        color: #3b82f6;
                        font-size: 24px;
                        right: 30px;
                        transition: color 0.3s ease;

                    }
                </style>

                <div class="user-info">
                    <span id="ai-modal-btn" title="Predict Availability"><i class="fas fa-robot"></i></span>
                    <img id="profile-pic" src="../../assets/images/default-profile.png" alt="Profile"
                        class="profile-pic">
                    <span class="username" id="doctor-name">Loading...</span>
                    <div class="dropdown">
                        <i class="fas fa-caret-down"></i>
                        <div class="dropdown-content">
                            <a href="dr_profile.html">Profile</a>
                            <a href="../auth/logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div id="ai-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <div class="ai-section">
                        <h2>AI-Powered Insights</h2>
                        <div class="ai-widget">
                            <h3>Doctor Availability Prediction</h3>
                            <label for="prediction-range">Prediction Range:</label>
                            <select id="prediction-range" class="ai-select">
                                <option value="7">Next 7 Days</option>
                                <option value="30">Next 30 Days</option>
                                <option value="60">Next 60 Days</option>
                            </select>
                            <button id="predict-availability" class="ai-button">
                                <i class="fas fa-robot"></i> Predict Availability
                            </button>
                            <div id="availability-prediction" class="ai-response"></div>
                            <br>
                            <button id="accept-predictions-btn" class="ai-button">Accept Predicted Slots</button>

                        </div>
                        <div class="ai-widget">
                            <h3>Sensitive Appointment Detection</h3>
                            <button id="detect-sensitive" class="ai-button">
                                <i class="fas fa-robot"></i> Detect Sensitive Cases
                            </button>
                            <ul id="sensitive-appointments" class="ai-response"></ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div id="dashboard-content">
                <!-- Content will be loaded dynamically via JavaScript -->
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading dashboard...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../assets/js/doctors/doctors_dashboard.js"></script>
    <script src="../../assets/js/doctors/calendar.js"></script>
</body>

</html>