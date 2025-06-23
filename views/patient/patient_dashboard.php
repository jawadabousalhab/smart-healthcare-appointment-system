<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../middleware/auth_check.php';


// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$patient_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$pdo = getPDO();
try {
    switch ($action) {
        case 'get_appointments':
            $stmt = $pdo->prepare("
                SELECT a.*, u.name as doctor_name, c.name as clinic_name 
                FROM appointments a
                JOIN users u ON a.doctor_id = u.user_id
                JOIN clinics c ON a.clinic_id = c.clinic_id
                WHERE a.patient_id = ? 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            $stmt->execute([$patient_id]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($appointments);
            break;

        case 'get_doctors':
            $search = $_GET['search'] ?? '';
            $location = $_GET['location'] ?? '';

            $query = "
                SELECT u.user_id as doctor_id, u.name, u.specialization, u.profile_picture, 
                       c.clinic_id,c.name as clinic_name, c.location
                FROM users u
                JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
                JOIN clinics c ON cd.clinic_id = c.clinic_id
                WHERE u.role = 'doctor'
            ";

            $params = [];

            if (!empty($search)) {
                $query .= " AND (u.name LIKE ? OR u.specialization LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if (!empty($location)) {
                $query .= " AND c.location LIKE ?";
                $params[] = "%$location%";
            }



            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($doctors);
            break;
        case 'get_clinics_for_doctor':
            $doctor_id = $_GET['doctor_id'] ?? 0;
            $stmt = $pdo->prepare("
                    SELECT c.clinic_id, c.name 
                    FROM clinic_doctors cd
                    JOIN clinics c ON cd.clinic_id = c.clinic_id
                    WHERE cd.doctor_id = ?
                ");
            $stmt->execute([$doctor_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'get_clinic_location':
            $clinic_id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT name, location, map_coordinates FROM clinics WHERE clinic_id = ?");
            $stmt->execute([$clinic_id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            break;
        case 'find_nearest_doctors':
            $specialization = $_GET['specialization'] ?? 'general';

            $stmt = $pdo->prepare("
        SELECT u.user_id as doctor_id, u.name, u.specialization, 
               c.clinic_id, c.name as clinic_name, c.location, c.map_coordinates,
               d.is_verified
        FROM users u
        JOIN clinic_doctors cd ON u.user_id = cd.doctor_id
        JOIN clinics c ON cd.clinic_id = c.clinic_id
        JOIN doctor_verification_documents d ON u.user_id = d.doctor_id
        WHERE u.role = 'doctor' 
        AND u.specialization LIKE ?
        AND d.is_verified = 1
        ORDER BY RAND()
        LIMIT 5
    ");
            $stmt->execute(["%$specialization%"]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'get_clinics':
            $stmt = $pdo->prepare("SELECT clinic_id, name FROM clinics");
            $stmt->execute();
            $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($clinics);
            break;
        case 'find_nearest_doctors':
            $specialization = $_GET['specialization'] ?? 'general';

            $stmt = $pdo->prepare("
        SELECT doctors.user_id, doctors.name, doctors.specialization,
               clinics.clinic_id, clinics.name as clinic_name
        FROM doctors
        JOIN clinics ON doctors.doctor_id = clinics.doctor_id
        WHERE doctors.specialization LIKE ?
        LIMIT 5
    ");
            $stmt->execute(["%$specialization%"]);
            $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($doctors);
            break;

        case 'get_doctor_availability':
            $doctor_id = $_GET['doctor_id'] ?? 0;
            $clinic_id = $_GET['clinic_id'] ?? 0;
            $date = $_GET['date'] ?? '';

            if (!$doctor_id || !$clinic_id || !$date) {
                echo json_encode(['error' => 'Missing parameters']);
                break;
            }

            // Get doctor's available slots
            $stmt = $pdo->prepare("
                SELECT start_time, end_time 
                FROM doctor_availability 
                WHERE doctor_id = ? AND clinic_id = ? AND date = ? AND status = 'available'
            ");
            $stmt->execute([$doctor_id, $clinic_id, $date]);
            $availability = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$availability) {
                echo json_encode(['error' => 'Doctor not available on this date']);
                break;
            }

            // Get already booked appointments
            $stmt = $pdo->prepare("
                SELECT appointment_time 
                FROM appointments 
                WHERE doctor_id = ? AND clinic_id = ? AND appointment_date = ? AND status = 'confirmed'
            ");
            $stmt->execute([$doctor_id, $clinic_id, $date]);
            $booked_times = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Generate available time slots (every 30 minutes)
            $start = strtotime($availability['start_time']);
            $end = strtotime($availability['end_time']);
            $current = $start;
            $slots = [];

            while ($current < $end) {
                $time = date('H:i:s', $current);
                if (!in_array($time, $booked_times)) {
                    $slots[] = $time;
                }
                $current = strtotime('+30 minutes', $current);
            }

            echo json_encode(['available_slots' => $slots]);
            break;

        case 'book_appointment':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                INSERT INTO appointments 
                (patient_id, doctor_id, clinic_id, appointment_date, appointment_time, status, reason, notes) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $patient_id,
                $data['doctor_id'],
                $data['clinic_id'],
                $data['date'],
                $data['time'],
                $data['reason'],
                $data['notes'] ?? ''
            ]);

            $appointment_id = $pdo->lastInsertId();

            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, ip_address) 
                VALUES (?, 'appointment_booked', ?, ?)
            ");
            $stmt->execute([
                $patient_id,
                "Booked appointment #$appointment_id with doctor #{$data['doctor_id']}",
                $_SERVER['REMOTE_ADDR']
            ]);

            echo json_encode(['success' => true, 'appointment_id' => $appointment_id]);
            break;

        case 'cancel_appointment':
            $appointment_id = $_GET['id'] ?? 0;

            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled' 
                WHERE appointment_id = ? AND patient_id = ?
            ");
            $stmt->execute([$appointment_id, $patient_id]);

            if ($stmt->rowCount() > 0) {
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address) 
                    VALUES (?, 'appointment_cancelled', ?, ?)
                ");
                $stmt->execute([
                    $patient_id,
                    "Cancelled appointment #$appointment_id",
                    $_SERVER['REMOTE_ADDR']
                ]);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Appointment not found or not authorized']);
            }
            break;

        case 'get_medical_records':
            $stmt = $pdo->prepare("
                 SELECT mr.*, u.name as doctor_name
FROM medical_reports mr
JOIN users u ON mr.doctor_id = u.user_id
WHERE mr.patient_id = ?
ORDER BY mr.report_date DESC
            ");
            $stmt->execute([$patient_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($records);
            break;

        case 'get_patient_info':
            $stmt = $pdo->prepare(
                "SELECT name, email, phone_number, address, profile_picture 
        FROM users 
        WHERE user_id = ?"
            );
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            // Remove sensitive data
            unset($patient['password'], $patient['password_reset_code'], $patient['verification_code']);

            echo json_encode($patient);
            break;

        case 'update_patient_info':
            $data = json_decode(file_get_contents('php://input'), true);
            $country_code = $data['country_code'] ?? null;
            $phone_number = $data['phone'] ?? null;
            $full_phone_number = $country_code . $phone_number;
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, phone_number = ?, address = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['name'],
                $full_phone_number,
                $data['address'],
                $patient_id
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'change_password':
            $data = json_decode(file_get_contents('php://input'), true);

            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$patient_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($data['current_password'], $user['password'])) {
                echo json_encode(['error' => 'Current password is incorrect']);
                break;
            }

            if ($data['new_password'] !== $data['confirm_password']) {
                echo json_encode(['error' => 'New passwords do not match']);
                break;
            }

            // Update password
            $new_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$new_password, $patient_id]);

            echo json_encode(['success' => true]);
            break;

        case 'ai_assistant':
            $message = $_GET['message'] ?? '';
            $apiKey = 'Eq5byJfjzJVTCDo51DBY0F1I2vCHfe1z'; // Replace with your real key
            $data = json_encode([
                'model' => 'mistral-medium', // Or mistral-small, etc.
                'messages' => [['role' => 'user', 'content' => $message]]
            ]);

            $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);
            $reply = $responseData['choices'][0]['message']['content'] ?? 'Sorry, I could not understand your request.';

            echo json_encode(['response' => $reply]);
            break;


            break;
        case 'get_stats':
            try {
                // Get total appointments
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                $appointments = $stmt->fetchColumn();

                // Get total medical records
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_reports WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                $records = $stmt->fetchColumn();

                // Get unique doctors visited (completed appointments)
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT doctor_id) FROM appointments WHERE patient_id = ? AND status = 'completed'");
                $stmt->execute([$patient_id]);
                $doctors = $stmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'appointments' => $appointments,
                    'records' => $records,
                    'doctors' => $doctors
                ]);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'update_profile_picture':
            try {
                $target_dir = "../../uploads/profiles/patients/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
                $new_filename = "patient_" . $patient_id . "_" . time() . "." . $file_extension;
                $target_file = $target_dir . $new_filename;

                // Check if image file is a actual image
                $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
                if ($check === false) {
                    echo json_encode(['error' => 'File is not an image.']);
                    break;
                }

                // Check file size (max 2MB)
                if ($_FILES["profile_picture"]["size"] > 2000000) {
                    echo json_encode(['error' => 'Sorry, your file is too large. Max 2MB allowed.']);
                    break;
                }

                // Allow certain file formats
                $allowed_extensions = ["jpg", "jpeg", "png", "gif"];
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    echo json_encode(['error' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.']);
                    break;
                }

                // Upload file
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    // Update database
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt->execute([$new_filename, $patient_id]);

                    echo json_encode([
                        'success' => true,
                        'new_picture' => "../../uploads/profiles/patients/" . $new_filename
                    ]);
                } else {
                    echo json_encode(['error' => 'Sorry, there was an error uploading your file.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
