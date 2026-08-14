<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';

require_login();
require_role(['director']);

if (!isset($_GET['id'])) {
    die('❌ ไม่พบ id ใน URL');
}

$id = (int)$_GET['id'];

/* สร้างคอลัมน์ลายเซ็นเจ้าหน้าที่ถ้ายังไม่มี */
$chk = $conn->query("SHOW COLUMNS FROM supervision_files LIKE 'staff_signed_file'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE supervision_files
        ADD COLUMN staff_signed_file VARCHAR(255) DEFAULT NULL AFTER file_path,
        ADD COLUMN staff_signed_at DATETIME DEFAULT NULL AFTER staff_signed_file,
        ADD COLUMN staff_signed_by INT(11) DEFAULT NULL AFTER staff_signed_at");
}

$stmt = $conn->prepare("SELECT file_path, staff_signed_file FROM supervision_files WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('❌ ไม่พบเอกสาร');
}

$row = $res->fetch_assoc();
$file_path = trim((string)($row['file_path'] ?? ''));
$staff_signed = isset($row['staff_signed_file']) ? trim((string)$row['staff_signed_file']) : '';
$signed_dir = __DIR__ . '/../uploads/supervision_docs/signed/';

if ($staff_signed !== '' && strpos($staff_signed, '..') === false && is_file($signed_dir . basename($staff_signed))) {
    $file = $signed_dir . basename($staff_signed);
    $file_path = $staff_signed;
} else {
    if ($file_path === '' || strpos($file_path, '..') !== false) {
        die('❌ path ไฟล์ไม่ถูกต้อง');
    }
    $base_dir = __DIR__ . '/../uploads/supervision_docs/original/';
    $file = $base_dir . basename($file_path);
}

if (!is_file($file)) {
    die('❌ ไม่พบไฟล์เอกสารในระบบ');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'pdf') {
    $mime = 'application/pdf';
} elseif (in_array($ext, ['jpg', 'jpeg'])) {
    $mime = 'image/jpeg';
} elseif ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'gif') {
    $mime = 'image/gif';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
