<?php
// admin/delete_classroom.php - Handle classroom deletion via AJAX
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// Check if there are users assigned to this classroom
$check_user = $conn->query("SELECT id FROM users WHERE classroom_id = $id LIMIT 1");
if ($check_user && $check_user->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากยังมีนักเรียนลงทะเบียนอยู่ในห้องนี้!']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM classrooms WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
}

$stmt->close();
$conn->close();
