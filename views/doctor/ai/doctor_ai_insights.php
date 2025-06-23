<?php
header('Content-Type: application/json');
require_once '../../../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['user_id'];
$pdo = getPDO();
set_time_limit(300);

// Get doctor's assigned clinics with names
$stmt = $pdo->prepare("SELECT c.clinic_id, c.name FROM clinic_doctors cd 
                      JOIN clinics c ON cd.clinic_id = c.clinic_id
                      WHERE cd.doctor_id = ?");
$stmt->execute([$doctor_id]);
$doctorClinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($doctorClinics)) {
    echo json_encode(['error' => 'Doctor is not assigned to any clinics']);
    exit;
}

// Get availability history with clinic names
$stmt = $pdo->prepare("SELECT da.date, da.start_time, da.end_time, da.status, 
                       c.clinic_id, c.name as clinic_name
                       FROM doctor_availability da
                       JOIN clinics c ON da.clinic_id = c.clinic_id
                       WHERE da.doctor_id = ? 
                       ORDER BY da.date DESC 
                       LIMIT 30");
$stmt->execute([$doctor_id]);
$availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build context for AI with clinic information
$context = "You are a medical AI assistant. Predict availability for a doctor based on:\n\n";
$context .= "Assigned Clinics:\n";
foreach ($doctorClinics as $clinic) {
    $context .= "- Clinic ID {$clinic['clinic_id']} ({$clinic['name']})\n";
}

$range = isset($_GET['range']) ? intval($_GET['range']) : 7;
$today = date('Y-m-d');
$apiKey = 'Eq5byJfjzJVTCDo51DBY0F1I2vCHfe1z';

function callMistralAPI($prompt, $apiKey)
{
    $data = json_encode([
        "model" => "mistral-small",
        "messages" => [
            ["role" => "system", "content" => "You are a medical AI assistant. Predict availability for a doctor."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0,
        "stop" => ["\n\n", "\nBased on", "\nPrediction:", "Summary:", "Explanation:", "Analysis:"],
        "max_tokens" => 4000
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
    return $response;
}

if ($range == 7) {
    $prompt = $context . "\n\nToday is $today. Predict the doctor's availability for the next $range days only using the assigned clinics." .
        "\nStrictly return ONLY a valid JSON array (no introduction, no explanation, no extra text)." .
        "\nOutput format must be:" .
        "\n[ { \"date\": \"2025-06-01\", \"start_time\": \"09:00\", \"end_time\": \"17:00\", \"status\": \"available\", \"clinic_id\": 1, \"clinic_name\": \"3enaya\" }, ... ]" .
        "\nIf you return anything other than the array, the system will break. DO NOT include any text or summary outside the array.";

    $response = callMistralAPI($prompt, $apiKey);
    $responseData = json_decode($response, true);
    $raw = trim($responseData['choices'][0]['message']['content'] ?? '');
} elseif ($range == 30) {
    // First 15 days
    $prompt1 = $context . "\n\nToday is $today. Predict availability for the next 15 days (days 1-15)..." .
        "\nStrictly return ONLY a valid JSON array (no introduction, no explanation, no extra text)." .
        "\nOutput format must be:" .
        "\n[ { \"date\": \"2025-06-01\", \"start_time\": \"09:00\", \"end_time\": \"17:00\", \"status\": \"available\", \"clinic_id\": 1, \"clinic_name\": \"3enaya\" }, ... ]";

    // Next 15 days
    $startDate = date('Y-m-d', strtotime('+15 days'));
    $prompt2 = $context . "\n\nStarting from $startDate predict availability for the following 15 days (days 16-30)..." .
        "\nStrictly return ONLY a valid JSON array (no introduction, no explanation, no extra text)." .
        "\nOutput format must be:" .
        "\n[ { \"date\": \"2025-06-16\", \"start_time\": \"09:00\", \"end_time\": \"17:00\", \"status\": \"available\", \"clinic_id\": 1, \"clinic_name\": \"3enaya\" }, ... ]";

    $response1 = callMistralAPI($prompt1, $apiKey);
    $response2 = callMistralAPI($prompt2, $apiKey);

    $responseData1 = json_decode($response1, true);
    $raw1 = trim($responseData1['choices'][0]['message']['content'] ?? '');
    $responseData2 = json_decode($response2, true);
    $raw2 = trim($responseData2['choices'][0]['message']['content'] ?? '');

    // Combine both responses
    preg_match('/\[\s*{.*}\s*\]/s', $raw1, $matches1);
    preg_match('/\[\s*{.*}\s*\]/s', $raw2, $matches2);

    $predicted1 = isset($matches1[0]) ? json_decode($matches1[0], true) : json_decode($raw1, true);
    $predicted2 = isset($matches2[0]) ? json_decode($matches2[0], true) : json_decode($raw2, true);

    $raw = json_encode(array_merge(
        is_array($predicted1) ? $predicted1 : [],
        is_array($predicted2) ? $predicted2 : []
    ));
    $responseData = ['choices' => [['message' => ['content' => $raw]]]];
} else {
    echo json_encode(['error' => 'Invalid range specified']);
    exit;
}

// Process the response (common for both 7 and 30 day ranges)
$raw = trim($responseData['choices'][0]['message']['content'] ?? '');

// Extract JSON array
preg_match('/\[\s*{.*}\s*\]/s', $raw, $matches);
$predicted = isset($matches[0]) ? json_decode($matches[0], true) : json_decode($raw, true);

if (is_array($predicted)) {
    // Validate clinic IDs before returning
    $validClinicIds = array_column($doctorClinics, 'clinic_id');
    // Fetch existing available dates from DB
    $stmt = $pdo->prepare("SELECT date FROM doctor_availability WHERE doctor_id = ? AND status = 'available'");
    $stmt->execute([$doctor_id]);
    $existingDates = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'date');

    $filteredPredictions = [];
    foreach ($predicted as $slot) {
        if (
            is_array($slot) &&
            isset($slot['date'], $slot['start_time'], $slot['end_time'], $slot['clinic_id'])
        ) {
            $filteredPredictions[] = $slot;
        }
    }

    echo json_encode([
        'predicted_availability' => array_values($filteredPredictions),
        'clinics' => $doctorClinics
    ]);
} else {
    echo json_encode([
        'error' => 'Invalid prediction format',
        'raw_output' => $raw
    ]);
}
