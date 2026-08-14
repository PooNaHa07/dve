<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher', 'admin']);

header('Content-Type: application/json');

if (!isset($_GET['std_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing student ID']);
    exit;
}

$std_id = (int)$_GET['std_id'];
$u = current_user();
$teacher_id = (int)$u['id'];

// Get student info (with IDOR check)
$stmt_std = $conn->prepare("SELECT u.id, u.fullname, u.student_code, COALESCE(c.name, u.company_name) as company_name 
                            FROM users u 
                            LEFT JOIN companies c ON u.company_id = c.id 
                            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id 
                            WHERE u.id = ? AND ta.teacher_id = ?");
$stmt_std->bind_param("ii", $std_id, $teacher_id);
$stmt_std->execute();
$student = $stmt_std->get_result()->fetch_assoc();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found or access denied']);
    exit;
}

// Get teacher evaluation
$eval_res = $conn->query("SELECT * FROM evaluations WHERE student_id = $std_id");
$evaluation = $eval_res->fetch_assoc();

echo json_encode([
    'success' => true,
    'student' => $student,
    'evaluation' => $evaluation
]);
