<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Security Check
$user = current_user();
if (!$user || !in_array($user['role'], ['staff'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, user_id, fullname, department, email, phone FROM mentors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($mentor = $result->fetch_assoc()) {
        echo json_encode($mentor);
    } else {
        echo json_encode(['error' => 'Mentor not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid ID']);
}
