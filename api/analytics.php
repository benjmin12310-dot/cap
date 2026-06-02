<?php
// API: return chart data for the analytics dashboard.

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── Shared helper: run a query and die cleanly on failure ─────────────────────
function run_query(mysqli $conn, string $sql): mysqli_result {
    $result = $conn->query($sql);
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
        exit();
    }
    return $result;
}

// PATIENTS PER MONTH (last 12 months)
if ($action === 'patients_per_month') {
    $rows = run_query($conn, "
        SELECT DATE_FORMAT(created_at, '%b %Y') as label,
               DATE_FORMAT(created_at, '%Y-%m') as sort_key,
               COUNT(*) as total
        FROM patients
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// APPOINTMENTS PER MONTH (last 12 months)
if ($action === 'appointments_per_month') {
    $rows = run_query($conn, "
        SELECT DATE_FORMAT(appointment_date, '%b %Y') as label,
               DATE_FORMAT(appointment_date, '%Y-%m') as sort_key,
               COUNT(*) as total
        FROM appointments
        WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// TOP SERVICES (completed appointments)
if ($action === 'top_services') {
    $rows = run_query($conn, "
        SELECT s.service_name as label, COUNT(a.id) as total
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.status = 'completed'
        GROUP BY s.id, s.service_name
        ORDER BY total DESC
        LIMIT 8
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// APPOINTMENT STATUS BREAKDOWN (current month)
if ($action === 'status_breakdown') {
    $rows = run_query($conn, "
        SELECT status as label, COUNT(*) as total
        FROM appointments
        WHERE appointment_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
          AND appointment_date <  DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')
        GROUP BY status
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// PEAK BOOKING DAYS (all time)
if ($action === 'peak_days') {
    $rows = run_query($conn, "
        SELECT DAYNAME(appointment_date) as label,
               DAYOFWEEK(appointment_date) as sort_key,
               COUNT(*) as total
        FROM appointments
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// PEAK BOOKING HOURS (all time)
if ($action === 'peak_hours') {
    $rows = run_query($conn, "
        SELECT DATE_FORMAT(appointment_time, '%l:00 %p') as label,
               HOUR(appointment_time) as sort_key,
               COUNT(*) as total
        FROM appointments
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'total')),
    ]);
    exit();
}

// NEW VS RETURNING PATIENTS (current month)
if ($action === 'new_vs_returning') {
    $month_start = date('Y-m-01');

    $new = (int) run_query($conn, "
        SELECT COUNT(*) as c FROM patients
        WHERE created_at >= '$month_start'
    ")->fetch_assoc()['c'];

    // Returning = had an appointment this month but were registered BEFORE this month
    $returning = (int) run_query($conn, "
        SELECT COUNT(DISTINCT a.patient_id) as c
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        WHERE a.appointment_date >= '$month_start'
          AND p.created_at        <  '$month_start'
    ")->fetch_assoc()['c'];

    echo json_encode([
        'status' => 'ok',
        'labels' => ['New Patients', 'Returning Patients'],
        'data'   => [$new, $returning],
    ]);
    exit();
}

// REVENUE PER MONTH (last 6 months)
if ($action === 'revenue_per_month') {
    $rows = run_query($conn, "
        SELECT DATE_FORMAT(created_at, '%b %Y') as label,
               DATE_FORMAT(created_at, '%Y-%m') as sort_key,
               SUM(amount_paid) as total
        FROM bills
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('floatval', array_column($rows, 'total')),
    ]);
    exit();
}

// SUMMARY KPI NUMBERS (for dashboard cards)
if ($action === 'kpi_summary') {
    $month_start = date('Y-m-01');
    $today       = date('Y-m-d');

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $today_appts = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'");
    $stmt->execute();
    $pending = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) as c FROM appointments
        WHERE status = 'completed' AND appointment_date >= ?
    ");
    $stmt->bind_param('s', $month_start);
    $stmt->execute();
    $completed = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as c FROM bills WHERE DATE(created_at) >= ?
    ");
    $stmt->bind_param('s', $month_start);
    $stmt->execute();
    $revenue = (float) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) as c FROM appointments WHERE appointment_date >= ?
    ");
    $stmt->bind_param('s', $month_start);
    $stmt->execute();
    $total_this_month = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $total_patients = (int) run_query($conn, "SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];

    $rate = $total_this_month > 0 ? round(($completed / $total_this_month) * 100, 1) : 0;

    echo json_encode([
        'status'          => 'ok',
        'total_patients'  => $total_patients,
        'today_appts'     => $today_appts,
        'pending'         => $pending,
        'completed_month' => $completed,
        'revenue_month'   => number_format($revenue, 2),
        'completion_rate' => $rate,
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
