<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../documents_list.php"); exit;
}

// ถอยออก 1 ชั้นเพื่อไปหา includes
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
include __DIR__ . '/../includes/header.php';

$err = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $file = $_FILES['doc_file'];

    $target_dir = "../uploads/documents/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = time() . "_" . uniqid() . "." . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $stmt = $conn->prepare("INSERT INTO documents (title, filename, uploaded_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $title, $new_filename, $user_id);
        if ($stmt->execute()) {
            header("Location: ../documents_list.php");
            exit;
        }
        $errno = $conn->errno;
        $errmsg = $conn->error;
        if ($errno == 1062 || (is_string($errmsg) && stripos($errmsg, 'Duplicate') !== false)) {
            $err = "ข้อมูลเอกสารซ้ำในระบบ กรุณาตรวจสอบ";
        } else {
            $err = "เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่อีกครั้ง";
        }
        @unlink($target_dir . $new_filename);
    } else {
        $err = "อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
    }
}
?>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white"><h4>➕ อัปโหลดเอกสารใหม่</h4></div>
        <div class="card-body">
            <?php if (!empty($err)): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>ชื่อเอกสาร</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>เลือกไฟล์</label>
                    <input type="file" name="doc_file" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                <a href="list.php" class="btn btn-secondary">ยกเลิก</a>
            </form>
        </div>
    </div>
</div>