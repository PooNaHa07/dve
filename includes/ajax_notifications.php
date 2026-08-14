<?php
header('Content-Type: application/json');
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$u = current_user();
$user_id = (int)$u['id'];

// Get action parameter
$action = $_POST['action'] ?? $_GET['action'] ?? '';

global $conn;
if (!isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($action === 'mark_read') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
} elseif ($action === 'mark_all_read') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
} elseif ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Fetch current unread count
$unread_count = 0;
$count_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
if ($count_res) {
    $count_row = $count_res->fetch_assoc();
    $unread_count = (int)$count_row['cnt'];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count
]);
