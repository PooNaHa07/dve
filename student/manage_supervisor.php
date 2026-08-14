<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['student']);

header('Content-Type: application/json; charset=utf-8');

$u          = current_user();
$student_id = (int)$u['id'];
$action     = $_POST['action'] ?? '';

if ($action === 'add') {
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
    if ($supervisor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    // ตรวจสอบว่า supervisor มีอยู่จริง
    $chk = $conn->prepare("SELECT id FROM users WHERE id=? AND role='supervisor' LIMIT 1");
    $chk->bind_param("i", $supervisor_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'ไม่พบ Supervisor ที่ระบุ']);
        exit;
    }
    $chk->close();

    $role_student = 'student';
    $stmt = $conn->prepare("
        INSERT IGNORE INTO supervisor_assignments
        (supervisor_id, student_id, assigned_by, assigned_by_role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $supervisor_id, $student_id, $student_id, $role_student);
    $ok       = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected > 0) {
        // ดึงชื่อ supervisor เพื่อตอบกลับ
        $row = $conn->query("SELECT fullname, email FROM users WHERE id=$supervisor_id")->fetch_assoc();
        echo json_encode([
            'success' => true,
            'supervisor_name'  => $row['fullname'],
            'supervisor_email' => $row['email'] ?? '',
        ]);
    } elseif ($ok && $affected === 0) {
        echo json_encode(['success' => false, 'message' => 'คุณเลือก Supervisor คนนี้ไว้แล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
    }

} elseif ($action === 'remove') {
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
    if ($supervisor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    // นักเรียนลบได้เฉพาะที่ตนเองเลือก (assigned_by_role = 'student')
    $stmt = $conn->prepare("
        DELETE FROM supervisor_assignments
        WHERE supervisor_id = ? AND student_id = ? AND assigned_by_role = 'student'
    ");
    $stmt->bind_param("ii", $supervisor_id, $student_id);
    $ok       = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected > 0) {
        echo json_encode(['success' => true]);
    } elseif ($ok && $affected === 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการที่เลือกหรือไม่มีสิทธิ์ลบ (ลบได้เฉพาะที่เลือกเอง)']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}
