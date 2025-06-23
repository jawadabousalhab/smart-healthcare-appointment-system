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

// Get doctor's assigned clinics
$clinics = [];
$stmt = $pdo->prepare("SELECT c.clinic_id, c.name FROM clinic_doctors cd 
                      JOIN clinics c ON cd.clinic_id = c.clinic_id
                      WHERE cd.doctor_id = :doctor_id");
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctor's availability and appointments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+6 days', strtotime($start_date)));
    $clinic_id = isset($_GET['clinic_id']) ? $_GET['clinic_id'] : null;
    $view_type = isset($_GET['view']) ? $_GET['view'] : 'week';

    try {
        if ($view_type === 'month') {
            $availabilityByClinic = [];
            $appointmentsByClinic = [];

            // Simplified query for month view - just check which dates have availability
            $query = "SELECT date, clinic_id FROM doctor_availability 
                     WHERE doctor_id = :doctor_id 
                     AND date BETWEEN :start_date AND :end_date";

            if ($clinic_id && $clinic_id !== 'all') {
                $query .= " AND clinic_id = :clinic_id";
            }

            $query .= " GROUP BY date, clinic_id";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':doctor_id', $doctor_id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);

            if ($clinic_id && $clinic_id !== 'all') {
                $stmt->bindParam(':clinic_id', $clinic_id);
            }

            $stmt->execute();
            $availabilityDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dayStats = [];

            foreach ($availabilityDates as $avail) {
                $key = $avail['date'];
                if (!isset($dayStats[$key])) {
                    $dayStats[$key] = ['available_minutes' => 0, 'booked_minutes' => 0];
                }

                // Get all slots on that day
                $stmt2 = $pdo->prepare("SELECT start_time, end_time FROM doctor_availability 
        WHERE doctor_id = :doctor_id AND date = :date" .
                    ($clinic_id && $clinic_id !== 'all' ? " AND clinic_id = :clinic_id" : ""));
                $stmt2->bindParam(':doctor_id', $doctor_id);
                $stmt2->bindParam(':date', $key);
                if ($clinic_id && $clinic_id !== 'all') {
                    $stmt2->bindParam(':clinic_id', $clinic_id);
                }
                $stmt2->execute();
                $slots = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                foreach ($slots as $slot) {
                    $start = strtotime($slot['start_time']);
                    $end = strtotime($slot['end_time']);
                    $dayStats[$key]['available_minutes'] += ($end - $start) / 60;
                }
            }


            // Simplified appointments check
            $query = "SELECT appointment_date, clinic_id FROM appointments
                     WHERE doctor_id = :doctor_id
                     AND appointment_date BETWEEN :start_date AND :end_date
                     AND status IN ('approved', 'pending')";

            if ($clinic_id && $clinic_id !== 'all') {
                $query .= " AND clinic_id = :clinic_id";
            }

            $query .= " GROUP BY appointment_date, clinic_id";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':doctor_id', $doctor_id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);

            if ($clinic_id && $clinic_id !== 'all') {
                $stmt->bindParam(':clinic_id', $clinic_id);
            }

            $stmt->execute();
            $appointmentDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($appointmentDates as $appt) {
                $key = $appt['appointment_date'];
                if (!isset($dayStats[$key])) {
                    $dayStats[$key] = ['available_minutes' => 0, 'booked_minutes' => 0];
                }

                // Assume appointments are fixed length (e.g., 30 minutes)
                $dayStats[$key]['booked_minutes'] += 30;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'view_type' => 'month',
                'clinics' => $clinics,
                'availability' => $availabilityByClinic,
                'appointments' => $appointmentsByClinic,
                'day_stats' => $dayStats
            ]);
            exit;
        }

        $availabilityByClinic = [];
        $appointmentsByClinic = [];

        // Get availability for each clinic or specific clinic if filtered
        $query = "SELECT * FROM doctor_availability 
                 WHERE doctor_id = :doctor_id 
                 AND date BETWEEN :start_date AND :end_date";

        if ($clinic_id && $clinic_id !== 'all') {
            $query .= " AND clinic_id = :clinic_id";
        }

        $query .= " ORDER BY date, start_time";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);

        if ($clinic_id && $clinic_id !== 'all') {
            $stmt->bindParam(':clinic_id', $clinic_id);
        }

        $stmt->execute();
        $allAvailability = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group availability by clinic
        foreach ($allAvailability as $availability) {
            $clinicKey = $availability['clinic_id'] ?? 'none';
            if (!isset($availabilityByClinic[$clinicKey])) {
                $availabilityByClinic[$clinicKey] = [];
            }
            $availabilityByClinic[$clinicKey][] = $availability;
        }

        // Get appointments for each clinic or specific clinic if filtered
        $query = "SELECT a.*, u.name as patient_name 
                 FROM appointments a
                 JOIN users u ON a.patient_id = u.user_id
                 WHERE a.doctor_id = :doctor_id
                 AND a.appointment_date BETWEEN :start_date AND :end_date
                 AND a.status IN ('approved', 'pending')";

        if ($clinic_id && $clinic_id !== 'all') {
            $query .= " AND a.clinic_id = :clinic_id";
        }

        $query .= " ORDER BY a.appointment_date, a.appointment_time";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);

        if ($clinic_id && $clinic_id !== 'all') {
            $stmt->bindParam(':clinic_id', $clinic_id);
        }

        $stmt->execute();
        $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group appointments by clinic
        foreach ($allAppointments as $appointment) {
            $clinicKey = $appointment['clinic_id'] ?? 'none';
            if (!isset($appointmentsByClinic[$clinicKey])) {
                $appointmentsByClinic[$clinicKey] = [];
            }
            $appointmentsByClinic[$clinicKey][] = $appointment;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'clinics' => $clinics,
            'availability' => $availabilityByClinic,
            'appointments' => $appointmentsByClinic
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
// Handle AI predicted slots acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'accept_predictions') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['slots'])) {
        echo json_encode(['status' => 'error', 'message' => 'No slots provided']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $savedCount = 0;
        $errors = [];

        // Get assigned clinics and map clinic_id to name
        $stmt = $pdo->prepare("SELECT c.clinic_id, c.name FROM clinic_doctors cd 
                               JOIN clinics c ON cd.clinic_id = c.clinic_id
                               WHERE cd.doctor_id = :doctor_id");
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->execute();
        $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $clinicNames = [];
        foreach ($clinics as $clinic) {
            $clinicNames[$clinic['clinic_id']] = $clinic['name'];
        }

        $insertStmt = $pdo->prepare("INSERT INTO doctor_availability 
            (doctor_id, clinic_id, date, start_time, end_time, status)
            VALUES (:doctor_id, :clinic_id, :date, :start_time, :end_time, :status)");

        $conflictCheckStmt = $pdo->prepare("SELECT 1 FROM doctor_availability
            WHERE doctor_id = :doctor_id
            AND date = :date
            AND (
                (start_time < :end_time AND end_time > :start_time)
            )");

        foreach ($input['slots'] as $slot) {
            if (empty($slot['clinic_id']) || empty($slot['date']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                $errors[] = 'Incomplete slot data';
                continue;
            }

            // Check if doctor is assigned to this clinic
            if (!isset($clinicNames[$slot['clinic_id']])) {
                $errors[] = "Invalid clinic ID {$slot['clinic_id']} for date {$slot['date']}";
                continue;
            }

            // Check for overlapping time in any clinic
            $conflictCheckStmt->execute([
                ':doctor_id' => $doctor_id,
                ':date' => $slot['date'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time']
            ]);

            if ($conflictCheckStmt->fetch()) {
                $clinicName = $clinicNames[$slot['clinic_id']] ?? "Unknown Clinic";
                $errors[] = "[CONFLICT] {$slot['date']} {$slot['start_time']}–{$slot['end_time']} overlaps with another slot (Clinic: {$clinicName})";
                continue;
            }

            // Insert new availability
            $insertStmt->execute([
                ':doctor_id' => $doctor_id,
                ':clinic_id' => $slot['clinic_id'],
                ':date' => $slot['date'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time'],
                ':status' => $slot['status'] ?? 'available'
            ]);
            $savedCount++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => $savedCount > 0 ? 'success' : 'error',
            'message' => $savedCount > 0 ? "Saved $savedCount slot(s) successfully" : "No new slots were saved",
            'saved_count' => $savedCount,
            'errors' => $errors
        ]);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}



// Add or update availability (single or recurring)
// Add or update availability (single or recurring)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        exit;
    }

    // Check if this is a recurring availability request
    $isRecurring = isset($_GET['recurring']) && $_GET['recurring'] === 'true';

    if ($isRecurring) {
        handleRecurringAvailability($pdo, $doctor_id, $data, $clinics);
        exit;
    }

    // Validate clinic_id - doctor must be assigned to this clinic
    if (empty($data['clinic_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Clinic is required']);
        exit;
    }

    $validClinic = false;
    foreach ($clinics as $clinic) {
        if ($clinic['clinic_id'] == $data['clinic_id']) {
            $validClinic = true;
            break;
        }
    }

    if (!$validClinic) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid clinic selection']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Overlap check (only for inserts or time changes)
        $checkOverlapStmt = $pdo->prepare(
            "SELECT 1 FROM doctor_availability
            WHERE doctor_id = :doctor_id
            AND date = :date
            AND (
                (start_time < :end_time AND end_time > :start_time)
            )" .
                (!empty($data['availability_id']) ? " AND availability_id != :availability_id" : "")
        );

        $checkParams = [
            ':doctor_id' => $doctor_id,
            ':date' => $data['date'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time']
        ];

        if (!empty($data['availability_id'])) {
            $checkParams[':availability_id'] = $data['availability_id'];
        }

        $checkOverlapStmt->execute($checkParams);

        if ($checkOverlapStmt->fetch()) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => "The selected time overlaps with another availability slot"
            ]);
            exit;
        }

        if (empty($data['availability_id'])) {
            // Add new availability
            $stmt = $pdo->prepare("INSERT INTO doctor_availability 
                (doctor_id, clinic_id, date, start_time, end_time, status)
                VALUES (:doctor_id, :clinic_id, :date, :start_time, :end_time, :status)");
        } else {
            // Update existing availability
            $stmt = $pdo->prepare("UPDATE doctor_availability 
                SET clinic_id = :clinic_id,
                    date = :date, 
                    start_time = :start_time, 
                    end_time = :end_time, 
                    status = :status
                WHERE availability_id = :availability_id AND doctor_id = :doctor_id");
            $stmt->bindParam(':availability_id', $data['availability_id']);
        }

        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':clinic_id', $data['clinic_id']);
        $stmt->bindParam(':date', $data['date']);
        $stmt->bindParam(':start_time', $data['start_time']);
        $stmt->bindParam(':end_time', $data['end_time']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->execute();

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Availability saved successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}


// Delete availability
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $availability_id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$availability_id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Availability ID is required']);
        exit;
    }

    try {
        // First verify this availability belongs to the current doctor
        $stmt = $pdo->prepare("SELECT 1 FROM doctor_availability 
                              WHERE availability_id = :availability_id 
                              AND doctor_id = :doctor_id");
        $stmt->bindParam(':availability_id', $availability_id);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->execute();

        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Availability not found or unauthorized']);
            exit;
        }

        // Now delete it
        $stmt = $pdo->prepare("DELETE FROM doctor_availability 
                              WHERE availability_id = :availability_id");
        $stmt->bindParam(':availability_id', $availability_id);
        $stmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Availability deleted successfully']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle recurring availability
function handleRecurringAvailability($pdo, $doctor_id, $data, $clinics)
{
    if (
        empty($data['start_date']) || empty($data['end_date']) || empty($data['start_time']) ||
        empty($data['end_time']) || empty($data['repeat_pattern']) || empty($data['clinic_id'])
    ) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'All fields are required for recurring availability']);
        exit;
    }

    // Validate clinic
    $validClinic = false;
    foreach ($clinics as $clinic) {
        if ($clinic['clinic_id'] == $data['clinic_id']) {
            $validClinic = true;
            break;
        }
    }

    if (!$validClinic) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid clinic selection']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $start_date = new DateTime($data['start_date']);
        $end_date = new DateTime($data['end_date']);
        $start_time = $data['start_time'];
        $end_time = $data['end_time'];
        $status = $data['status'] ?? 'available';
        $clinic_id = $data['clinic_id'];

        $stmt = $pdo->prepare("INSERT INTO doctor_availability 
                              (doctor_id, clinic_id, date, start_time, end_time, status)
                              VALUES (:doctor_id, :clinic_id, :date, :start_time, :end_time, :status)");

        $current_date = clone $start_date;

        while ($current_date <= $end_date) {
            // Check if this day should be included based on repeat pattern
            $should_add = false;
            $day_of_week = $current_date->format('w'); // 0 (Sunday) to 6 (Saturday)

            switch ($data['repeat_pattern']) {
                case 'daily':
                    $should_add = true;
                    break;
                case 'weekly':
                    if ($current_date->format('w') == $start_date->format('w')) {
                        $should_add = true;
                    }
                    break;
                case 'weekdays':
                    if ($day_of_week >= 1 && $day_of_week <= 5) { // Monday to Friday
                        $should_add = true;
                    }
                    break;
            }

            if ($should_add) {
                $date_str = $current_date->format('Y-m-d');

                // Check if availability already exists for this date and time
                $check_stmt = $pdo->prepare("SELECT 1 FROM doctor_availability 
                                            WHERE doctor_id = :doctor_id 
                                            AND clinic_id = :clinic_id
                                            AND date = :date 
                                            AND start_time = :start_time");
                $check_stmt->bindParam(':doctor_id', $doctor_id);
                $check_stmt->bindParam(':clinic_id', $clinic_id);
                $check_stmt->bindParam(':date', $date_str);
                $check_stmt->bindParam(':start_time', $start_time);
                $check_stmt->execute();

                if (!$check_stmt->fetch()) {
                    $stmt->bindParam(':doctor_id', $doctor_id);
                    $stmt->bindParam(':clinic_id', $clinic_id);
                    $stmt->bindParam(':date', $date_str);
                    $stmt->bindParam(':start_time', $start_time);
                    $stmt->bindParam(':end_time', $end_time);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                }
            }

            $current_date->modify('+1 day');
        }

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Recurring availability added successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit;
