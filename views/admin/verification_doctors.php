<?php
require_once '../../config/db.php';
require_once '../../middleware/auth_check.php';
require_once '../../utils/mailer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.html');
    exit();
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);

        if ($doctorId && $documentId) {
            try {
                $pdo->beginTransaction();

                $status = ($_POST['action'] === 'approve') ? 1 : 0;
                $message = ($status) ? "Doctor verification approved successfully." : "Doctor verification rejected.";

                $stmt = $pdo->prepare("UPDATE doctor_verification_documents SET is_verified = :status, verified_at = NOW(), verified_by = :adminId WHERE document_id = :documentId AND doctor_id = :doctorId");
                $stmt->execute([
                    ':status' => $status,
                    ':adminId' => $_SESSION['user_id'],
                    ':documentId' => $documentId,
                    ':doctorId' => $doctorId
                ]);

                $stmt = $pdo->prepare("UPDATE users SET is_verified = :status WHERE user_id = :doctorId");
                $stmt->execute([
                    ':status' => $status,
                    ':doctorId' => $doctorId
                ]);
                $stmt = $pdo->prepare("SELECT name, email, specialization FROM users WHERE user_id = :doctorId");
                $stmt->execute([':doctorId' => $doctorId]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($status == 1) {
                    $subject = "Welcome to Smart HealthCare";
                    $message = "Dear {$doctor['name']},<br><br>";
                    $message .= "You were verified as a " . $doctor['specialization'] . " Doctor<br><br>";
                    $message .= "Best regards,<br>Smart Health Team";

                    $emailSent = sendEmail($doctor['email'], $subject, $message);
                } else if ($status == 0) {
                    $subject = "Doctor Verification Rejected";
                    $message = "Dear {$doctor['name']},<br><br>";
                    $message .= "Your verification request has been rejected.<br><br>";
                    $message .= "Please reupload the required documents or contact support for more information.<br><br>";
                    $message .= "Best regards,<br>Smart Health Team";

                    $emailSent = sendEmail($doctor['email'], $subject, $message);

                    $stmt = $pdo->prepare("UPDATE doctor_verification_documents SET is_verified = -1, verified_at = NOW(), verified_by = :adminId WHERE document_id = :documentId AND doctor_id = :doctorId");
                    $stmt->execute([
                        ':adminId' => $_SESSION['user_id'],
                        ':documentId' => $documentId,
                        ':doctorId' => $doctorId
                    ]);
                }

                $action = ($status) ? 'doctor_verification_approved' : 'doctor_verification_rejected';
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:userId, :action, :desc, :ip)");
                $stmt->execute([
                    ':userId' => $_SESSION['user_id'],
                    ':action' => $action,
                    ':desc' => "Doctor ID: $doctorId",
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);

                $pdo->commit();
                $_SESSION['success_message'] = $message;
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
            }
            header("Location: verification_doctors.php");
            exit();
        }
    }
}

$query = "SELECT u.user_id, u.name, u.email, u.specialization, dvd.document_id, dvd.certificate_file, dvd.id_passport_file, dvd.gov_letter_file, dvd.submitted_at FROM users u JOIN doctor_verification_documents dvd ON u.user_id = dvd.doctor_id WHERE u.role = 'doctor' AND dvd.is_verified = 0 ORDER BY dvd.submitted_at ASC";
$pendingVerifications = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$verifiedDoctors = $pdo->query("SELECT u.user_id, u.name, u.email, u.specialization, dvd.verified_at, dvd.verified_by, admin.name as verified_by_name FROM users u JOIN doctor_verification_documents dvd ON u.user_id = dvd.doctor_id LEFT JOIN users admin ON dvd.verified_by = admin.user_id WHERE u.role = 'doctor' AND dvd.is_verified = 1 ORDER BY dvd.verified_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Verification | Admin Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/admin_css/admin.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        .document-preview {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }

        .card-title {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>

<body class="bg-gray-100">

    <div class="flex h-screen overflow-hidden">
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>
        <div class="hidden md:flex md:flex-shrink-0">
            <!-- Sidebar -->
            <div id="sidebar" class="hidden md:flex md:flex-shrink-0">
                <div class="flex flex-col w-64 bg-gray-800">
                    <div class="flex items-center justify-center h-16 bg-gray-900">
                        <span class="text-white font-bold text-lg">Super Admin Panel</span>
                    </div>
                    <div class="flex flex-col flex-grow overflow-y-auto">
                        <nav class="flex-1 px-2 py-4 space-y-1">
                            <a href="admin_dashboard.html"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                            </a>
                            <a href="clinics.html"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-clinic-medical mr-3"></i> Clinics
                            </a>
                            <a href="it_admins.html"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-users-cog mr-3"></i> IT Admins
                            </a>
                            <a href="admins.html"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-shield mr-3"></i> Admins
                            </a>
                            <a href="system.html"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-server mr-3"></i> System
                            </a>
                            <a href="verification_doctors.php"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-check mr-3"></i> Doctors Verification
                            </a>
                            <a href="../auth/logout.php"
                                class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-sign-out-alt mr-3"></i> Logout
                            </a>
                        </nav>
                    </div>
                    <div class="flex-shrink-0 flex border-t border-gray-700 p-4">
                        <div class="flex items-center">
                            <div>
                                <img class="profile-picture h-8 w-8 rounded-full"
                                    src="../../assets/images/default-profile.png" alt="Admin profile">
                            </div>
                            <div class="ml-3">
                                <p class="user-name text-sm font-medium text-white"></p>
                                <p class="text-xs font-medium text-gray-300">Super Admin</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Navigation -->
            <header class="flex justify-between items-center p-4 bg-white border-b border-gray-200">
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 focus:outline-none md:hidden">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="ml-4 text-lg font-semibold text-gray-700">Doctor Verification</h2>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button id="notifications-button" class="text-gray-500 focus:outline-none relative">
                            <i class="fas fa-bell"></i>
                            <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500"></span>
                        </button>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center text-gray-500 focus:outline-none">
                            <span class="user-name mr-2">Super Admin</span>
                            <img class="profile-picture h-8 w-8 rounded-full"
                                src="../../assets/images/default-profile.png" alt="Admin profile">
                        </button>
                        <div id="user-menu-dropdown"
                            class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                            <div class="py-1">
                                <a href="settings.html" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto p-6">

                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <h2><i class="bi bi-person-check-fill me-2"></i>Doctor Verification</h2>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <ul class="nav nav-tabs" id="verificationTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            Pending Verifications <span class="badge bg-danger ms-1"><?= count($pendingVerifications) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="verified-tab" data-bs-toggle="tab" data-bs-target="#verified" type="button" role="tab">
                            Recently Verified
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <?php if (empty($pendingVerifications)): ?>
                            <div class="alert alert-info text-center"><i class="bi bi-info-circle me-2"></i>No pending verification requests.</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($pendingVerifications as $request): ?>
                                    <?php $doctorNameSlug = urlencode(str_replace(' ', '_', $request['name'])); ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow-sm">
                                            <div class="card-header bg-white">
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($request['name']) ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>
                                                <p><strong>Specialization:</strong> <?= htmlspecialchars($request['specialization']) ?></p>
                                                <p><strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($request['submitted_at'])) ?></p>

                                                <h6 class="mt-3">Documents:</h6>
                                                <div class="row">
                                                    <div class="col-4">
                                                        <small>Certificate</small>
                                                        <a href="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['certificate_file']) ?>" target="_blank">
                                                            <img src="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['certificate_file']) ?>" class="document-preview img-thumbnail">
                                                        </a>
                                                    </div>
                                                    <div class="col-4">
                                                        <small>ID/Passport</small>
                                                        <a href="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['id_passport_file']) ?>" target="_blank">
                                                            <img src="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['id_passport_file']) ?>" class="document-preview img-thumbnail">
                                                        </a>
                                                    </div>
                                                    <div class="col-4">
                                                        <small>Gov. Letter</small>
                                                        <a href="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['gov_letter_file']) ?>" target="_blank">
                                                            <img src="../../uploads/doctor_documents/<?= $doctorNameSlug ?>/<?= htmlspecialchars($request['gov_letter_file']) ?>" class="document-preview img-thumbnail">
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-footer d-flex justify-content-between">
                                                <form method="POST">
                                                    <input type="hidden" name="doctor_id" value="<?= $request['user_id'] ?>">
                                                    <input type="hidden" name="document_id" value="<?= $request['document_id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"><i class="bi bi-x-circle"></i> Reject</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="verified" role="tabpanel">
                        <?php if (empty($verifiedDoctors)): ?>
                            <div class="alert alert-info text-center">No recently verified doctors.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Specialization</th>
                                            <th>Email</th>
                                            <th>Verified On</th>
                                            <th>Verified By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verifiedDoctors as $doctor): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($doctor['name']) ?></td>
                                                <td><?= htmlspecialchars($doctor['specialization']) ?></td>
                                                <td><?= htmlspecialchars($doctor['email']) ?></td>
                                                <td><?= date('M j, Y g:i A', strtotime($doctor['verified_at'])) ?></td>
                                                <td><?= htmlspecialchars($doctor['verified_by_name'] ?? 'System') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin/profile-loader.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const button = document.getElementById('user-menu-button');
            const menu = document.getElementById('user-menu-dropdown');

            button.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });

            document.addEventListener('click', function(e) {
                if (!menu.contains(e.target) && !button.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            });
        });
    </script>
    <script src="../../assets/js/admin/notifications.js"></script>
</body>

</html>