<?php
// API: return chart data for the analytics dashboard.

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');


$action = $_GET['action'] ?? '';

// PATIENTS PER MONTH (last 12 months)
if ($action === 'patients_per_month') {
    $rows = $conn->query("
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
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// APPOINTMENTS PER MONTH (last 12 months)
if ($action === 'appointments_per_month') {
    $rows = $conn->query("
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
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// TOP SERVICES (completed appointments)
if ($action === 'top_services') {
    $rows = $conn->query("
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
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// APPOINTMENT STATUS BREAKDOWN (current month)
if ($action === 'status_breakdown') {
    $rows = $conn->query("
        SELECT status as label, COUNT(*) as total
        FROM appointments
        WHERE appointment_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
        GROUP BY status
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// PEAK BOOKING DAYS (all time)
if ($action === 'peak_days') {
    $rows = $conn->query("
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
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// PEAK BOOKING HOURS (all time)
if ($action === 'peak_hours') {
    $rows = $conn->query("
        SELECT DATE_FORMAT(appointment_time, '%h:00 %p') as label,
               HOUR(appointment_time) as sort_key,
               COUNT(*) as total
        FROM appointments
        GROUP BY sort_key, label
        ORDER BY sort_key ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'labels' => array_column($rows, 'label'),
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// NEW VS RETURNING PATIENTS (current month)
if ($action === 'new_vs_returning') {
    $new = $conn->query("
        SELECT COUNT(*) as c FROM patients
        WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
    ")->fetch_assoc()['c'];

    $returning = $conn->query("
        SELECT COUNT(DISTINCT patient_id) as c
        FROM appointments
        WHERE appointment_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
        AND patient_id NOT IN (
            SELECT id FROM patients
            WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
        )
    ")->fetch_assoc()['c'];

    echo json_encode([
        'status' => 'ok',
        'labels' => ['New Patients', 'Returning Patients'],
        'data'   => [(int)$new, (int)$returning]
    ]);
    exit();
}

// REVENUE PER MONTH (last 6 months)
if ($action === 'revenue_per_month') {
    $rows = $conn->query("
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
        'data'   => array_column($rows, 'total')
    ]);
    exit();
}

// SUMMARY KPI NUMBERS (for dashboard cards)
if ($action === 'kpi_summary') {
    $total_patients = $conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
    $today          = date('Y-m-d');
    $today_appts    = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = '$today'")->fetch_assoc()['c'];
    $pending        = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'")->fetch_assoc()['c'];
    $month_start    = date('Y-m-01');
    $completed      = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'completed' AND appointment_date >= '$month_start'")->fetch_assoc()['c'];
    $revenue        = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as c FROM bills WHERE DATE(created_at) >= '$month_start'")->fetch_assoc()['c'];

    $total_this_month = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date >= '$month_start'")->fetch_assoc()['c'];
    $rate = $total_this_month > 0 ? round(($completed / $total_this_month) * 100, 1) : 0;

    echo json_encode([
        'status'          => 'ok',
        'total_patients'  => (int)$total_patients,
        'today_appts'     => (int)$today_appts,
        'pending'         => (int)$pending,
        'completed_month' => (int)$completed,
        'revenue_month'   => number_format((float)$revenue, 2),
        'completion_rate' => $rate
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
