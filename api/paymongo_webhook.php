<?php
// paymongo_webhook.php — Receives payment events from PayMongo and marks bills as paid.
//
// HOW TO SET THIS UP IN PAYMONGO DASHBOARD:
//   URL: https://yourdomain.com/cap/api/paymongo_webhook.php
//   Events to subscribe: payment.paid, link.payment.paid
//
// After creating the webhook, copy the Webhook Secret Key into your .env:
//   PAYMONGO_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxx

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paymongo.php';

// Only accept POST from PayMongo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$raw_body  = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? '';
$secret    = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? '';

// Log every incoming webhook for debugging
$log_dir  = __DIR__ . '/../logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . 'paymongo_webhooks.log';
$log_line = '[' . date('Y-m-d H:i:s') . '] SIG=' . substr($signature, 0, 40) . '... BODY=' . substr($raw_body, 0, 300) . "\n";
file_put_contents($log_file, $log_line, FILE_APPEND);

// Verify signature (skip in test mode if no secret set yet)
if ($secret) {
    if (!paymongo_verify_webhook($raw_body, $signature, $secret)) {
        http_response_code(401);
        exit('Invalid signature');
    }
}

$event = json_decode($raw_body, true);
if (!$event) {
    http_response_code(400);
    exit('Bad JSON');
}

$event_type = $event['data']['attributes']['type'] ?? '';
$resource   = $event['data']['attributes']['data'] ?? [];
$attributes = $resource['attributes'] ?? [];

// We care about: payment.paid  OR  link.payment.paid
if (!in_array($event_type, ['payment.paid', 'link.payment.paid'])) {
    http_response_code(200);
    exit('Event ignored');
}

// Extract metadata — we stored bill_id here when creating the link
$metadata = $attributes['metadata'] ?? [];
$bill_id  = intval($metadata['bill_id'] ?? 0);

// Also try to find by paymongo_link_id in case metadata is missing
if (!$bill_id) {
    $link_id = $resource['id'] ?? ($attributes['links'][0]['id'] ?? '');
    if ($link_id) {
        $escaped = $conn->real_escape_string($link_id);
        $row = $conn->query("SELECT id FROM bills WHERE paymongo_link_id='$escaped' LIMIT 1")->fetch_assoc();
        $bill_id = intval($row['id'] ?? 0);
    }
}

if (!$bill_id) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] WARN: Could not resolve bill_id from event.\n", FILE_APPEND);
    http_response_code(200);
    exit('No bill_id found');
}

// Fetch current bill
$bill = $conn->query("SELECT * FROM bills WHERE id=$bill_id LIMIT 1")->fetch_assoc();
if (!$bill) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] WARN: Bill #$bill_id not found.\n", FILE_APPEND);
    http_response_code(200);
    exit('Bill not found');
}

if ($bill['status'] === 'paid') {
    // Already marked paid — idempotent, just acknowledge
    http_response_code(200);
    exit('Already paid');
}

// Amount paid (PayMongo sends centavos)
$paid_centavos = intval($attributes['amount'] ?? 0);
$paid_pesos    = round($paid_centavos / 100, 2);
$new_paid      = round($bill['amount_paid'] + $paid_pesos, 2);
$new_status    = $new_paid >= $bill['amount_due'] ? 'paid' : 'partial';

$payment_method_raw = $attributes['source']['type'] ?? 'online';
// Map PayMongo source type to our payment_method enum
$method_map = [
    'gcash'       => 'gcash',
    'paymaya'     => 'gcash',  // Maya
    'card'        => 'other',
    'dob'         => 'bank',
    'dob_ubp'     => 'bank',
    'billease'    => 'other',
    'qrph'        => 'gcash',
];
$payment_method = $method_map[$payment_method_raw] ?? 'other';
$payment_ref    = $resource['id'] ?? '';

$stmt = $conn->prepare("
    UPDATE bills
    SET amount_paid = ?, payment_method = ?, payment_ref = ?, status = ?,
        paymongo_paid_at = NOW()
    WHERE id = ?
");
$stmt->bind_param('dsssi', $new_paid, $payment_method, $payment_ref, $new_status, $bill_id);
$stmt->execute();
$stmt->close();

// Log action (system user)
$patient_name = $metadata['patient_name'] ?? "Patient";
$bill_code    = $bill['bill_code'];
$note = "Auto-updated via PayMongo webhook | Event: $event_type | Amount: ₱$paid_pesos | Method: $payment_method_raw | New status: $new_status";
$conn->query("
    INSERT INTO activity_logs (user_id, user_name, action, module, record_id, notes, created_at)
    VALUES (0, 'PayMongo Webhook', 'Auto Payment Update', 'billing', $bill_id, '" . $conn->real_escape_string($note) . "', NOW())
");

// In-app notification
if ($new_status === 'paid') {
    notify($conn, 'payment', '✅ Bill Fully Paid (Online)',
        "$patient_name's bill $bill_code is now fully paid via PayMongo. Total: ₱" . number_format($bill['amount_due'], 2) . ".",
        'modules/billing/view.php?id=' . $bill_id
    );
} else {
    $remaining = $bill['amount_due'] - $new_paid;
    notify($conn, 'payment', '💳 Online Payment Received',
        "₱" . number_format($paid_pesos, 2) . " received online from $patient_name. Remaining: ₱" . number_format($remaining, 2) . ". Bill: $bill_code.",
        'modules/billing/view.php?id=' . $bill_id
    );
}

file_put_contents($log_file,
    "[" . date('Y-m-d H:i:s') . "] SUCCESS: Bill #$bill_id updated to '$new_status'. Paid ₱$paid_pesos.\n",
    FILE_APPEND
);

http_response_code(200);
echo json_encode(['status' => 'ok']);
