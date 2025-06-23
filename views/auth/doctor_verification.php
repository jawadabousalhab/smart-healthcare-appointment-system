<?php
require_once '../../middleware/auth_check.php';
require_once '../../config/db.php';
require_once '../../middleware/Doctor_verified.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$doctor_name = $_SESSION['name'];

if (!isset($user_id) || $user_role !== 'doctor') {
    header('Location: login.html');
    exit();
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Status check
$stmt = $pdo->prepare("SELECT * FROM doctor_verification_documents WHERE doctor_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$latestSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

$canReupload = false;
$pendingSubmission = null;
$rejected = false;
$success = '';
$error = '';

// Decide what to show
if ($latestSubmission) {
    if ($latestSubmission['is_verified'] == 1) {
        header('Location: ../doctor/doctors_dashboard_html.php');
        exit();
    } elseif ($latestSubmission['is_verified'] == 0) {
        $pendingSubmission = $latestSubmission;
    } elseif ($latestSubmission['is_verified'] == -1) {
        $canReupload = true;
        $rejected = true;
    }
} else {
    $canReupload = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReupload) {
    try {
        $doctorNameSlug = urlencode(str_replace(' ', '_', $doctor_name));
        $requiredFiles = ['id_passport', 'certificate', 'gov_letter'];
        $uploadedFiles = [];
        $uploadDir = '../../uploads/doctor_documents/' . $doctorNameSlug . '/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($requiredFiles as $fileType) {
            if (!isset($_FILES[$fileType])) {
                throw new Exception("Please upload all required documents");
            }

            if ($_FILES[$fileType]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading $fileType: " . $_FILES[$fileType]['error']);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024;

            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $_FILES[$fileType]['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception("Invalid file type for $fileType. Only JPG, PNG, or PDF allowed.");
            }

            if ($_FILES[$fileType]['size'] > $maxSize) {
                throw new Exception("File too large for $fileType. Maximum 5MB allowed.");
            }

            $extension = pathinfo($_FILES[$fileType]['name'], PATHINFO_EXTENSION);
            $filename = 'doc_' . $user_id . '_' . $fileType . '_' . time() . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES[$fileType]['tmp_name'], $destination)) {
                throw new Exception("Failed to upload $fileType.");
            }

            $uploadedFiles[$fileType . '_file'] = $filename;
        }

        $stmt = $pdo->prepare("INSERT INTO doctor_verification_documents 
            (doctor_id, certificate_file, id_passport_file, gov_letter_file, is_verified, submitted_at) 
            VALUES (?, ?, ?, ?, 0, NOW())");

        $stmt->execute([
            $user_id,
            $uploadedFiles['certificate_file'],
            $uploadedFiles['id_passport_file'],
            $uploadedFiles['gov_letter_file']
        ]);

        $_SESSION['success_message'] = "Documents submitted successfully!";
        header('Location: doctor_verification.php');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
        foreach ($uploadedFiles as $file) {
            if (file_exists($uploadDir . $file)) {
                unlink($uploadDir . $file);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Doctor Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #dee2e6;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .file-info {
            margin-top: 10px;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Doctor Verification</h4>
                    </div>
                    <div class="card-body">

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php elseif ($pendingSubmission): ?>
                            <div class="alert alert-info">
                                <h5>Your documents are under review</h5>
                                <p>Submitted on <?= date('M j, Y', strtotime($pendingSubmission['submitted_at'])) ?>.</p>
                            </div>
                        <?php elseif ($rejected): ?>
                            <div class="alert alert-danger">
                                Your previous submission was rejected. Please upload the correct documents.
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <?php if ($canReupload): ?>
                            <div class="document-requirements">
                                <h5>Verification Required</h5>
                                <p>Before you can access the doctor dashboard, we need to verify your identity and medical credentials.</p>
                                <p>Please upload the following documents:</p>
                                <ul>
                                    <li><strong>Government-issued ID or Passport</strong> - To verify your identity</li>
                                    <li><strong>Medical License/Certificate</strong> - To verify your medical credentials</li>
                                    <li><strong>Official Government Letter</strong> - Confirming your status as a licensed doctor</li>
                                </ul>
                                <p class="text-muted">All documents should be clear, legible and in JPG, PNG, or PDF format (max 5MB each).</p>
                            </div>


                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <h5>ID or Passport</h5>
                                    <div class="upload-area">
                                        <input type="file" name="id_passport" id="id_passport" class="d-none" required>
                                        <label for="id_passport" class="btn btn-outline-primary">Choose File</label>
                                        <div class="file-info" id="id_passport_info">No file selected</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h5>Medical Certificate</h5>
                                    <div class="upload-area">
                                        <input type="file" name="certificate" id="certificate" class="d-none" required>
                                        <label for="certificate" class="btn btn-outline-primary">Choose File</label>
                                        <div class="file-info" id="certificate_info">No file selected</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h5>Government Letter</h5>
                                    <div class="upload-area">
                                        <input type="file" name="gov_letter" id="gov_letter" class="d-none" required>
                                        <label for="gov_letter" class="btn btn-outline-primary">Choose File</label>
                                        <div class="file-info" id="gov_letter_info">No file selected</div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Submit Documents</button>
                                </div>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        ['id_passport', 'certificate', 'gov_letter'].forEach(id => {
            const input = document.getElementById(id);
            const info = document.getElementById(id + '_info');
            input.addEventListener('change', function() {
                info.textContent = this.files[0] ? this.files[0].name : 'No file selected';
            });
        });
    </script>
</body>

</html>