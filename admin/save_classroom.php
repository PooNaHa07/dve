<?php
// admin/save_classroom.php - Handle classroom create/update via AJAX
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$class_name = trim($_POST['class_name']);
$total_students = isset($_POST['total_students']) ? (int)$_POST['total_students'] : 0;

if (empty($class_name)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อห้องเรียน']);
    exit;
}

if ($id === 0) {
    $stmt = $conn->prepare("INSERT INTO classrooms (class_name, total_students) VALUES (?, ?)");
    $stmt->bind_param("si", $class_name, $total_students);
} else {
    $stmt = $conn->prepare("UPDATE classrooms SET class_name=?, total_students=? WHERE id=?");
    $stmt->bind_param("sii", $class_name, $total_students, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
}

$stmt->close();
$conn->close();
