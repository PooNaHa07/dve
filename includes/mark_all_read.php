<?php
require_once __DIR__ . '/functions.php';
require_login();

$u = current_user();
$user_id = (int)$u['id'];

global $conn;
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");

// Redirect back to previous page or default to index
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : BASE_URL . '/index.php';
header("Location: $referer");
exit;
