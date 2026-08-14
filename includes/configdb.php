<?php
// ตั้งค่าเขตเวลาเป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ค่าเริ่มต้นมาตรฐาน
$host = "localhost";
$user = "root";
$pass = "";
$db   = "tvet_system";

// โหลดค่าปรับแต่งเฉพาะเครื่อง (Local Override) หากมีไฟล์ configdb.local.php
if (file_exists(__DIR__ . '/configdb.local.php')) {
    require_once __DIR__ . '/configdb.local.php';
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
