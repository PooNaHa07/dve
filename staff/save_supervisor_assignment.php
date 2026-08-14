<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff', 'admin', 'director']);

header('Content-Type: application/json; charset=utf-8');

$u      = current_user();
$caller_id   = (int)$u['id'];
$caller_role = $u['role'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
    $student_id    = (int)($_POST['student_id']    ?? 0);

    if ($supervisor_id <= 0 || $student_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    // ตรวจสอบว่า supervisor มี role ถูกต้อง
    $chk = $conn->prepare("SELECT id FROM users WHERE id=? AND role='supervisor' LIMIT 1");
    $chk->bind_param("i", $supervisor_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'ไม่พบ Supervisor ที่ระบุ']);
        exit;
    }
    $chk->close();

    // ตรวจสอบว่า student มี role ถูกต้อง
    $chk2 = $conn->prepare("SELECT id FROM users WHERE id=? AND role='student' LIMIT 1");
    $chk2->bind_param("i", $student_id);
    $chk2->execute();
    if ($chk2->get_result()->num_rows === 0) {
        $chk2->close();
        echo json_encode(['success' => false, 'message' => 'ไม่พบนักเรียนที่ระบุ']);
        exit;
    }
    $chk2->close();

    $stmt = $conn->prepare("
        INSERT IGNORE INTO supervisor_assignments
        (supervisor_id, student_id, assigned_by, assigned_by_role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $supervisor_id, $student_id, $caller_id, $caller_role);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected > 0) {
        log_audit('supervisor_assign_add', "Staff {$u['fullname']} (ID:{$caller_id}) assigned supervisor_id:{$supervisor_id} to student_id:{$student_id}");
        echo json_encode(['success' => true]);
    } elseif ($ok && $affected === 0) {
        echo json_encode(['success' => false, 'message' => 'การมอบหมายนี้มีอยู่แล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
    }

} elseif ($action === 'remove') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);

    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM supervisor_assignments WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        log_audit('supervisor_assign_remove', "Staff {$u['fullname']} (ID:{$caller_id}) removed assignment_id:{$assignment_id}");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ลบไม่สำเร็จ']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}
