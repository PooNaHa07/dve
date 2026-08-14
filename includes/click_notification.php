<?php
require_once __DIR__ . '/functions.php';
require_login();

$u = current_user();
$user_id = (int)$u['id'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

global $conn;
if ($id > 0) {
    // Fetch notification to get action_url
    $stmt = $conn->prepare("SELECT action_url FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $n = $res->fetch_assoc();
        $stmt->close();
        
        // Mark as read
        $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ii", $id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        if ($n && !empty($n['action_url'])) {
            header("Location: " . $n['action_url']);
            exit;
        }
    }
}

// Default fallback
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : BASE_URL . '/index.php';
header("Location: $referer");
exit;
