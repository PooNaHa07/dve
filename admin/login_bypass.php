<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 4;
$_SESSION['fullname'] = 'System Admin';
$_SESSION['role'] = 'admin';
header('Location: merge_duplicates.php');
exit;
