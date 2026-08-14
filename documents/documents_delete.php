<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../documents_list.php"); exit;
}

require_once __DIR__ . '/../includes/configdb.php';

$id = (int)$_GET['id'];
$res = $conn->query("SELECT filename FROM documents WHERE id = $id");
$doc = $res->fetch_assoc();

if ($doc) {
    @unlink("../uploads/documents/" . $doc['filename']); // ถอยออกไปลบไฟล์
    $conn->query("DELETE FROM documents WHERE id = $id");
}

header("Location: ../documents_list.php");
exit;