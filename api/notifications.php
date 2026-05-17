<?php
// API: mark notifications as read, get unread count.

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');


$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

// Mark a notification as read
if ($action === 'mark_read') {
    $id = intval($body['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(422);
echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    }
    exit();
}

// Mark all as read for current user
if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit();
}

// Get unread count (for badge in sidebar)
if ($action === 'get_count') {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    echo json_encode(['status' => 'success', 'count' => $count]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
