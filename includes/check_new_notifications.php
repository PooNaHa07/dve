<?php
header('Content-Type: application/json');
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$u = current_user();
$user_id = (int)$u['id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

global $conn;

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    type VARCHAR(50) DEFAULT 'info',
    action_url VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
// Ensure columns exist for safety
$check_type = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
if ($check_type && $check_type->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) DEFAULT 'info'");
}
$check_url = $conn->query("SHOW COLUMNS FROM notifications LIKE 'action_url'");
if ($check_url && $check_url->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL");
}

$new_notifications = [];
$latest_id = $last_id;

$stmt = $conn->prepare("SELECT id, title, message, type, action_url, created_at FROM notifications WHERE user_id = ? AND is_read = 0 AND id > ? ORDER BY id ASC");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $last_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $new_notifications[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'action_url' => $row['action_url'],
            'time' => date('H:i น.', strtotime($row['created_at']))
        ];
        if ((int)$row['id'] > $latest_id) {
            $latest_id = (int)$row['id'];
        }
    }
    $stmt->close();
}

// Also get the current unread count
$unread_count = 0;
$count_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
if ($count_res) {
    $count_row = $count_res->fetch_assoc();
    $unread_count = (int)$count_row['cnt'];
}

echo json_encode([
    'success' => true,
    'new_notifications' => $new_notifications,
    'latest_id' => $latest_id,
    'unread_count' => $unread_count
]);
