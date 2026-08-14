<?php
// documents/get_document.php - Fetch document data for editing
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['admin', 'staff']);

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, title, filename FROM documents WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $doc = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $doc]);
} else {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
}

$stmt->close();
