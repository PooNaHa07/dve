<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$std_id = (int)($_POST['std_id'] ?? 0);
$u = current_user();
$teacher_id = (int)$u['id'];

if ($std_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
    exit;
}

// Check access
$stmt_check = $conn->prepare("SELECT u.id FROM users u 
                            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id 
                            WHERE u.id = ? AND ta.teacher_id = ?");
$stmt_check->bind_param("ii", $std_id, $teacher_id);
$stmt_check->execute();
if (!$stmt_check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$s_work = (int)($_POST['score_work'] ?? 0);
$s_report = (int)($_POST['score_report'] ?? 0);
$s_behavior = (int)($_POST['score_behavior'] ?? 0);

// Validation
if ($s_work < 0 || $s_work > 70 || $s_report < 0 || $s_report > 20 || $s_behavior < 0 || $s_behavior > 10) {
    echo json_encode(['success' => false, 'error' => 'คะแนนไม่อยู่ในช่วงที่กำหนด (70/20/10)']);
    exit;
}

$total = $s_work + $s_report + $s_behavior;
$term = trim($_POST['term'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($total >= 80) $grade = '4';
elseif ($total >= 75) $grade = '3.5';
elseif ($total >= 70) $grade = '3';
elseif ($total >= 65) $grade = '2.5';
elseif ($total >= 60) $grade = '2';
elseif ($total >= 55) $grade = '1.5';
elseif ($total >= 50) $grade = '1';
else $grade = '0';

// Check existing
$check = $conn->query("SELECT id FROM evaluations WHERE student_id = $std_id");
$as = $check->fetch_assoc();

if ($as) {
    $stmt = $conn->prepare("UPDATE evaluations SET term=?, score_work=?, score_report=?, score_behavior=?, total_score=?, grade=?, remarks=?, teacher_id=? WHERE student_id=?");
    $stmt->bind_param("siiiisssi", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $teacher_id, $std_id);
} else {
    $stmt = $conn->prepare("INSERT INTO evaluations (term, score_work, score_report, score_behavior, total_score, grade, remarks, teacher_id, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiiisssi", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $teacher_id, $std_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
