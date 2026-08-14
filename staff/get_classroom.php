<?php
// staff/get_classroom.php - Fetch classroom details for AJAX modal (Staff access)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff']);

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, class_name, total_students FROM classrooms WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if ($room) {
    echo json_encode($room);
} else {
    echo json_encode(['error' => 'Classroom not found']);
}

$stmt->close();
$conn->close();
