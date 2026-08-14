<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Security Check
if (!is_logged_in() || !in_array(get_current_role(), ['staff', 'admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($company = $result->fetch_assoc()) {
        echo json_encode($company);
    } else {
        echo json_encode(['error' => 'Company not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid ID']);
}
