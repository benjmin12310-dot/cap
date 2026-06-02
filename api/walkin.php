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
$bl_stmt = $conn->prepare("SELECT id FROM blocked_dates WHERE blocked_date = ? LIMIT 1");
$bl_stmt->bind_param('s', $today);
$bl_stmt->execute();
$blocked = $bl_stmt->get_result()->num_rows;
$bl_stmt->close();
if ($blocked > 0) {
    echo json_encode(['is_closed' => true, 'reason' => 'Today is a blocked date (holiday or clinic closed).', 'slot' => null, 'label' => null]);
    exit();
}

// Get schedule
$sc_stmt = $conn->prepare("SELECT * FROM schedules WHERE day_of_week = ? AND is_open = 1 LIMIT 1");
$sc_stmt->bind_param('s', $day);
$sc_stmt->execute();
$sched = $sc_stmt->get_result()->fetch_assoc();
$sc_stmt->close();
if (!$sched) {
    echo json_encode(['is_closed' => true, 'reason' => 'No schedule configured for ' . ucfirst($day) . '.', 'slot' => null, 'label' => null]);
    exit();
}

$open_ts  = strtotime($today . ' ' . $sched['open_time']);
$close_ts = strtotime($today . ' ' . $sched['close_time']);
$slot_dur = intval($sched['slot_duration_minutes']);
$step     = $slot_dur * 60;

// Guard: a zero-duration slot would cause an infinite loop below
if ($step <= 0) {
    echo json_encode(['is_closed' => true, 'reason' => 'Invalid clinic schedule: slot duration must be greater than zero.', 'slot' => null, 'label' => null]);
    exit();
}

$br_stmt = $conn->prepare("
    SELECT a.appointment_time,
           COALESCE(s.duration_minutes, ?) AS duration_minutes
    FROM appointments a
    LEFT JOIN services s ON s.id = a.service_id
    WHERE a.appointment_date = ?
    AND a.status NOT IN ('cancelled','no-show')
");
$br_stmt->bind_param('is', $slot_dur, $today);
$br_stmt->execute();
$booked_res = $br_stmt->get_result();
$booked_windows = [];
while ($row = $booked_res->fetch_assoc()) {
    $start = strtotime($today . ' ' . $row['appointment_time']);
    $booked_windows[] = ['start' => $start, 'end' => $start + intval($row['duration_minutes']) * 60];
}
$br_stmt->close();

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
