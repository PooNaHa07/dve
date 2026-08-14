<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff', 'admin']);

header('Content-Type: application/json');

if (!isset($_GET['std_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing student ID']);
    exit;
}

$std_id = (int)$_GET['std_id'];

// Get student info
$std_res = $conn->query("SELECT u.id, u.fullname, u.student_code, c.name as company_name 
                        FROM users u 
                        LEFT JOIN companies c ON u.company_id = c.id 
                        WHERE u.id = $std_id");
$student = $std_res->fetch_assoc();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit;
}

// Get staff evaluation
$eval_res = $conn->query("SELECT * FROM staff_evaluations WHERE student_id = $std_id");
$evaluation = $eval_res->fetch_assoc();

echo json_encode([
    'success' => true,
    'student' => $student,
    'evaluation' => $evaluation
]);
