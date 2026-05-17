<?php
// API: returns next available walk-in slot for today.
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');


$today = date('Y-m-d');
$day   = strtolower(date('l'));
$now   = time();

// Check blocked date
$blocked = $conn->query("SELECT id FROM blocked_dates WHERE blocked_date = '$today'")->num_rows;
if ($blocked > 0) {
    echo json_encode(['is_closed' => true, 'reason' => 'Today is a blocked date (holiday or clinic closed).', 'slot' => null, 'label' => null]);
    exit();
}

// Get schedule
$sched = $conn->query("SELECT * FROM schedules WHERE day_of_week = '$day' AND is_open = 1 LIMIT 1")->fetch_assoc();
if (!$sched) {
    echo json_encode(['is_closed' => true, 'reason' => 'No schedule configured for ' . ucfirst($day) . '.', 'slot' => null, 'label' => null]);
    exit();
}

$open_ts  = strtotime($today . ' ' . $sched['open_time']);
$close_ts = strtotime($today . ' ' . $sched['close_time']);
$step     = intval($sched['slot_duration_minutes'] ?? 30) * 60;

$booked_res = $conn->query("
    SELECT a.appointment_time,
           COALESCE(s.duration_minutes, " . intval($sched['slot_duration_minutes']) . ") AS duration_minutes
    FROM appointments a
    LEFT JOIN services s ON s.id = a.service_id
    WHERE a.appointment_date = '$today'
    AND a.status NOT IN ('cancelled','no-show')
");
$booked_windows = [];
while ($row = $booked_res->fetch_assoc()) {
    $start = strtotime($today . ' ' . $row['appointment_time']);
    $booked_windows[] = ['start' => $start, 'end' => $start + intval($row['duration_minutes']) * 60];
}

$next_slot = null; $next_label = null;
for ($t = $open_ts; $t < $close_ts; $t += $step) {
    if ($t < $now) continue;
    $taken = false;
    foreach ($booked_windows as $w) {
        if ($t >= $w['start'] && $t < $w['end']) { $taken = true; break; }
    }
    if (!$taken) { $next_slot = date('H:i', $t); $next_label = date('h:i A', $t); break; }
}

echo json_encode([
    'is_closed'   => false,
    'is_full'     => $next_slot === null,
    'slot'        => $next_slot,
    'label'       => $next_label,
    'open_label'  => date('h:i A', $open_ts),
    'close_label' => date('h:i A', $close_ts),
    'reason'      => $next_slot === null ? 'Schedule is full for today.' : null,
]);
