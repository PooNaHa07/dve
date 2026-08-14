<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../documents_list.php"); exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
include __DIR__ . '/../includes/header.php';

$id = (int)$_GET['id'];
$res = $conn->query("SELECT * FROM documents WHERE id = $id");
$doc = $res->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $filename = $doc['filename'];

    if (!empty($_FILES['doc_file']['name'])) {
        @unlink("../uploads/documents/" . $doc['filename']); // ลบไฟล์เก่า
        $ext = pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['doc_file']['tmp_name'], "../uploads/documents/" . $filename);
    }

    $stmt = $conn->prepare("UPDATE documents SET title = ?, filename = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $filename, $id);
    $stmt->execute();
    header("Location: ../documents_list.php");
    exit;
}
?>
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-warning"><h4>✏️ แก้ไขเอกสาร</h4></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>ชื่อเอกสาร</label>
                    <input type="text" name="title" class="form-control" value="<?= e($doc['title']) ?>" required>
                </div>
                <div class="mb-3">
                    <label>ไฟล์ปัจจุบัน: <?= e($doc['filename']) ?></label>
                    <input type="file" name="doc_file" class="form-control">
                    <small class="text-muted text-danger">* ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยนไฟล์</small>
                </div>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                <a href="../documents_list.php" class="btn btn-secondary">ยกเลิก</a>
            </form>
        </div>
    </div>
</div>