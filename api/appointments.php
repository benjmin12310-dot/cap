<?php
// API: get available time slots for a date, update appointment status, delete appointment.

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');


$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Read JSON body for POST requests
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!empty($body)) $action = $body['action'] ?? $action;

// CSRF protection for all state-mutating actions.
// The JS caller must include _csrf (from csrf_field()) in the JSON body or POST data.
// Read-only actions (get_slots) are exempt.
$mutating_actions = ['update_status', 'delete_appointment'];
if (in_array($action, $mutating_actions)) {
    $submitted = $body['_csrf'] ?? ($_POST['_csrf'] ?? '');
    $expected  = $_SESSION['csrf_token'] ?? '';
    if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
        error_log('[CSRF] Token mismatch on api/appointments.php action=' . $action . ' from IP: ' . get_client_ip());
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request token. Please refresh and try again.']);
        exit();
    }
}

// GET AVAILABLE TIME SLOTS FOR A DATE (optionally filtered by doctor)
if ($action === 'get_slots') {
    $date      = $_GET['date']      ?? '';
    $doctor_id = intval($_GET['doctor_id'] ?? 0);  // 0 = any doctor / clinic-wide

    if (empty($date)) {
        echo json_encode(['status' => 'error', 'message' => 'Date required']);
        exit();
    }

    // Reject anything that isn't a valid Y-m-d date — strtotime('garbage') silently
    // returns false and date('l', false) falls back to the Unix epoch (Thursday).
    $parsed_date = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed_date || $parsed_date->format('Y-m-d') !== $date) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format. Expected YYYY-MM-DD.']);
        exit();
    }

    $day = strtolower(date('l', strtotime($date)));
    $day_code = strtolower(substr($day, 0, 3)); // mon, tue, etc.

    // Check if date is blocked
    $bl_stmt = $conn->prepare("SELECT id FROM blocked_dates WHERE blocked_date = ? LIMIT 1");
    $bl_stmt->bind_param('s', $date);
    $bl_stmt->execute();
    $blocked = $bl_stmt->get_result()->num_rows;
    $bl_stmt->close();
    if ($blocked > 0) {
        echo json_encode(['status' => 'ok', 'slots' => [], 'message' => 'Clinic is closed on this date.']);
        exit();
    }

    $open_time  = null;  // will be set from doctor schedule or clinic schedule below
    $close_time = null;

    // If a specific doctor is selected, verify they work on this day
    if ($doctor_id > 0) {
        $doc_stmt = $conn->prepare("SELECT schedule_days, start_time, end_time FROM doctors WHERE id = ? AND is_active = 1 LIMIT 1");
        $doc_stmt->bind_param('i', $doctor_id);
        $doc_stmt->execute();
        $doctor = $doc_stmt->get_result()->fetch_assoc();
        $doc_stmt->close();

        if (!$doctor) {
            echo json_encode(['status' => 'ok', 'slots' => [], 'message' => 'Doctor not found or inactive.']);
            exit();
        }

        $working_days = array_map('trim', explode(',', $doctor['schedule_days'] ?? ''));
        if (!in_array($day_code, $working_days)) {
            echo json_encode(['status' => 'ok', 'slots' => [], 'message' => 'This doctor does not work on ' . ucfirst($day) . 's.']);
            exit();
        }

        // Use doctor-specific hours instead of clinic hours
        $open_time  = $doctor['start_time'];
        $close_time = $doctor['end_time'];
    }

    // Get clinic schedule for that day
    $sc_stmt = $conn->prepare("SELECT * FROM schedules WHERE day_of_week = ? AND is_open = 1 LIMIT 1");
    $sc_stmt->bind_param('s', $day);
    $sc_stmt->execute();
    $sched = $sc_stmt->get_result()->fetch_assoc();
    $sc_stmt->close();
    if (!$sched) {
        echo json_encode(['status' => 'ok', 'slots' => [], 'message' => 'Clinic is closed on this day.']);
        exit();
    }

    // Use doctor hours if a doctor was selected; otherwise fall back to clinic hours
    $open_time  = $open_time  ?? $sched['open_time'];
    $close_time = $close_time ?? $sched['close_time'];

    $slots    = [];
    $start    = strtotime($open_time);
    $end      = strtotime($close_time);
    $slot_dur = intval($sched['slot_duration_minutes']);

    // Guard: a zero-duration slot would cause an infinite loop in the for() below.
    if ($slot_dur <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid clinic schedule: slot duration must be greater than zero.']);
        exit();
    }

    $step = $slot_dur * 60;

    // Fetch booked windows.
    // If doctor_id is given → only that doctor's bookings block slots.
    // If no doctor selected → any booking blocks the slot (old clinic-wide behaviour).
    // $slot_dur is bound as a parameter instead of interpolated into the SQL string.
    if ($doctor_id > 0) {
        $br_stmt = $conn->prepare("
            SELECT a.appointment_time,
                   COALESCE(s.duration_minutes, ?) AS duration_minutes
            FROM   appointments a
            LEFT JOIN services s ON s.id = a.service_id
            WHERE  a.appointment_date = ?
            AND    a.doctor_id = ?
            AND    a.status NOT IN ('cancelled', 'no-show')
        ");
        $br_stmt->bind_param('isi', $slot_dur, $date, $doctor_id);
    } else {
        $br_stmt = $conn->prepare("
            SELECT a.appointment_time,
                   COALESCE(s.duration_minutes, ?) AS duration_minutes
            FROM   appointments a
            LEFT JOIN services s ON s.id = a.service_id
            WHERE  a.appointment_date = ?
            AND    a.status NOT IN ('cancelled', 'no-show')
        ");
        $br_stmt->bind_param('is', $slot_dur, $date);
    }
    $br_stmt->execute();
    $booked_result = $br_stmt->get_result();
    $br_stmt->close();

    $booked_windows = [];
    while ($row = $booked_result->fetch_assoc()) {
        $appt_start = strtotime($row['appointment_time']);
        $booked_windows[] = [
            'start' => $appt_start,
            'end'   => $appt_start + (intval($row['duration_minutes']) * 60),
        ];
    }

    for ($t = $start; $t < $end; $t += $step) {
        $time_24   = date('H:i', $t);
        $time_12   = date('h:i A', $t);
        $is_blocked = false;
        foreach ($booked_windows as $win) {
            if ($t >= $win['start'] && $t < $win['end']) {
                $is_blocked = true;
                break;
            }
        }
        $slots[] = [
            'time_24'   => $time_24,
            'time_12'   => $time_12,
            'available' => !$is_blocked,
        ];
    }

    echo json_encode(['status' => 'ok', 'slots' => $slots]);
    exit();
}

// UPDATE APPOINTMENT STATUS
if ($action === 'update_status') {
    $id     = intval($body['id'] ?? 0);
    $status = $body['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];

    if (!$id || !in_array($status, $allowed)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit();
    }

    // ── Past-appointment guard ────────────────────────────────────
    // Confirming an already-passed appointment makes no sense.
    // completed / cancelled / no-show on past dates are still allowed
    // (staff may need to clean up records after the fact).
    if ($status === 'confirmed') {
        $past_chk = $conn->prepare("SELECT appointment_date FROM appointments WHERE id = ? LIMIT 1");
        $past_chk->bind_param('i', $id);
        $past_chk->execute();
        $past_row = $past_chk->get_result()->fetch_assoc();
        $past_chk->close();
        if ($past_row && strtotime($past_row['appointment_date']) < strtotime('today')) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Cannot confirm an appointment that has already passed.']);
            exit();
        }
    }
    // ─────────────────────────────────────────────────────────────

    // ── Dental-record check ───────────────────────────────────────
    // If marking as completed, check whether a dental record exists.
    // Return a warning (not an error) so the frontend can ask the user.
    // Pass force=true in the request body to skip the warning and complete anyway.
    if ($status === 'completed' && empty($body['force'])) {
        $chk = $conn->prepare(
            "SELECT COUNT(*) as c FROM dental_records WHERE appointment_id = ?"
        );
        $chk->bind_param('i', $id);
        $chk->execute();
        $chk_row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (($chk_row['c'] ?? 0) == 0) {
            // No treatment record — tell the frontend to show the warning modal
            echo json_encode([
                'status'  => 'no_record_warning',
                'appt_id' => $id,
            ]);
            exit();
        }
    }
    // ─────────────────────────────────────────────────────────────

    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    if ($stmt->execute()) {
        log_action($conn, $current_user_id, $current_user_name, 'Updated Appointment Status', 'appointments', $id, "Status changed to: $status");

        // ── Notification trigger ──────────────────────────────────
        $appt_stmt = $conn->prepare("
            SELECT a.appointment_code, a.appointment_date,
                   CONCAT(p.first_name,' ',p.last_name) as patient_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE a.id = ? LIMIT 1
        ");
        $appt_stmt->bind_param('i', $id);
        $appt_stmt->execute();
        $appt = $appt_stmt->get_result()->fetch_assoc();
        $appt_stmt->close();
        if ($appt) {
            $pname = $appt['patient_name'];
            $code  = $appt['appointment_code'];
            $date  = date('M d, Y', strtotime($appt['appointment_date']));
            $notif_map = [
                'confirmed'  => ['Appointment Confirmed',  "$pname's appointment ($code) on $date has been confirmed."],
                'completed'  => ['Appointment Completed',  "$pname's appointment ($code) on $date marked as completed."],
                'cancelled'  => ['Appointment Cancelled',  "$pname's appointment ($code) on $date has been cancelled."],
                'no-show'    => ['Patient No-Show',        "$pname did not show up for appointment $code on $date."],
            ];
            if (isset($notif_map[$status])) {
                notify($conn, 'appointment', $notif_map[$status][0], $notif_map[$status][1], 'modules/appointments/list.php');
            }
        }
        // ─────────────────────────────────────────────────────────

        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Update failed.']);
    }
    $stmt->close();
    exit();
}

// DELETE APPOINTMENT (hard delete with related payments)
if ($action === 'delete_appointment') {
    $id = intval($body['id'] ?? 0);

    if (!$id) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Invalid appointment ID.']);
        exit();
    }

    // DATABASE SECURITY: $id is intval() above — safe positive integer
    $appt = $conn->query("SELECT appointment_code FROM appointments WHERE id = $id LIMIT 1")->fetch_assoc();
    if (!$appt) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Appointment not found.']);
        exit();
    }

    // Delete related payments first, then the appointment.
    // Both deletes use intval()-sanitised $id — safe positive integer.
    $bills_del = $conn->query("DELETE FROM bills WHERE appointment_id = $id");
    if (!$bills_del) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete related bills.']);
        exit();
    }
    $del = $conn->query("DELETE FROM appointments WHERE id = $id");

    if ($del) {
        log_action($conn, $current_user_id, $current_user_name, 'Deleted Appointment', 'appointments', $id, "Permanently deleted: " . $appt['appointment_code']);
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Delete failed.']);
    }
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
