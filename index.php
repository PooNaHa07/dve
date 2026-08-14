<?php
require_once __DIR__ . '/includes/functions.php';

// ถ้ายังไม่ล็อกอิน ให้ไปหน้าหลัก (Landing) แทนหน้า login
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/Landing-Page.php');
    exit;
}

$u = current_user();

switch ($u['role']) {
    case 'admin':
        header("Location: " . BASE_URL . "/roles/admin.php");
        exit;

    case 'director': // เพิ่มเคสของผู้บริหารตรงนี้
        header("Location: " . BASE_URL . "/roles/director.php");
        exit;

    case 'teacher':
        header("Location: " . BASE_URL . "/roles/teacher.php");
        exit;

    case 'staff':
        header("Location: " . BASE_URL . "/roles/staff.php");
        exit;

    default: // student
        header("Location: " . BASE_URL . "/roles/student.php");
        exit;
}
?>