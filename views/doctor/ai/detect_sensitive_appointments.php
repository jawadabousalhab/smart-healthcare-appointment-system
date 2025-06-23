<?php
header('Content-Type: application/json');
require_once '../../../config/db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['user_id'];
$prompt = $_POST['prompt'] ?? '';

if (!$prompt) {
    echo json_encode(['error' => 'Missing prompt']);
    exit;
}

try {
    $pdo = getPDO();

    // Fetch upcoming appointments for the doctor
    $stmt = $pdo->prepare("SELECT 
    a.appointment_date, 
    a.appointment_time, 
    u.name AS patient_name, 
    a.reason 
FROM appointments a
JOIN users u ON a.patient_id = u.user_id
WHERE a.doctor_id = ? 
  AND a.appointment_date >= CURDATE()
ORDER BY a.appointment_date, a.appointment_time;");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$appointments) {
        echo json_encode(['error' => 'No upcoming appointments found']);
        exit;
    }

    // Build context for AI
    $context = "You are an AI assistant helping organize appointments. Classify each appointment based on its urgency, using patterns in the reason text. Do not give medical advice. Only return JSON.\n\n";

    foreach ($appointments as $a) {
        $context .= "- {$a['appointment_date']} at {$a['appointment_time']}: Patient {$a['patient_name']} - Reason: {$a['reason']}\n";
    }

    $context .= "\nClassify these into 'routine', 'important', or 'sensitive'. Output JSON only in this format:\n";
    $context .= '[{"date": "2025-05-01", "time": "10:00", "sensitivity": "sensitive", "reason": "Severe chest pain reported."}]';
    $apiKey = 'Eq5byJfjzJVTCDo51DBY0F1I2vCHfe1z';
    // Send to LLaMA via Ollama
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

    $parsed = json_decode($aiResponse, true);
    $raw = trim($parsed['response'] ?? '');

    // Extract valid JSON array using regex
    preg_match('/\[\s*{.*}\s*\]/s', $raw, $matches);
    $jsonData = isset($matches[0]) ? json_decode($matches[0], true) : null;

    if (is_array($jsonData)) {
        echo json_encode([
            'success' => true,
            'appointments' => $jsonData,
            'response' => $parsed['response'] // also return raw response for fallback
        ]);
    } else {
        echo json_encode([
            'error' => 'Invalid JSON returned by AI',
            'response' => $parsed['response'] ?? ''
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
