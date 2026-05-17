<?php
// API: get completed appointments for a patient (used in billing form).

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');


$action = $_GET['action'] ?? '';

// Get completed appointments for a patient (for billing dropdown)
if ($action === 'get_appointments') {
    $patient_id = intval($_GET['patient_id'] ?? 0);
    if (!$patient_id) {
        echo json_encode(['appointments' => []]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_code, a.appointment_date, a.status, s.service_name, s.price
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.patient_id = ?
        AND a.status IN ('pending','confirmed','completed')
        ORDER BY a.appointment_date DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['appointments' => $rows]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
