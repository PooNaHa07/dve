<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['supervisor']);

header('Content-Type: application/json; charset=utf-8');

$u             = current_user();
$supervisor_id = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Security: CSRF Token Validation ──
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf($csrf)) {
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้องหรือเซสชันหมดอายุ (Invalid CSRF Token)']);
    exit;
}

$daily_report_id = (int)($_POST['daily_report_id'] ?? 0);
$student_id      = (int)($_POST['student_id']      ?? 0);
$comment         = trim($_POST['comment']           ?? '');

if (mb_strlen($comment) > 3000) {
    $comment = mb_substr($comment, 0, 3000);
}

if ($daily_report_id <= 0 || $student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ── Security: ตรวจสอบว่า supervisor คนนี้ดูแลนักเรียนคนนั้นจริง ──
$supervised_ids = get_supervised_student_ids($supervisor_id);
if (!in_array($student_id, $supervised_ids, true)) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึงบันทึกของนักเรียนคนนี้']);
    exit;
}

// ── Security: ตรวจสอบว่า report นั้นเป็นของ student_id ที่ระบุ ──
$check = $conn->prepare("SELECT id FROM daily_reports WHERE id = ? AND student_id = ? LIMIT 1");
$check->bind_param("ii", $daily_report_id, $student_id);
$check->execute();
$check_res = $check->get_result();
if ($check_res->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'ไม่พบบันทึกงานที่ระบุ']);
    exit;
}
$check->close();

// ── UPDATE supervisor_comment ──
$now  = date('Y-m-d H:i:s');
$stmt = $conn->prepare("
    UPDATE daily_reports
    SET supervisor_comment       = ?,
        supervisor_commented_by  = ?,
        supervisor_commented_at  = ?
    WHERE id = ? AND student_id = ?
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ']);
    exit;
}

// Security Fix: Correct bind_param types "sisii" (s=comment, i=supervisor_id, s=now, i=daily_report_id, i=student_id)
$stmt->bind_param("sisii", $comment, $supervisor_id, $now, $daily_report_id, $student_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // Log audit action
    log_audit_action('supervisor_comment', "Supervisor #{$supervisor_id} commented on daily report #{$daily_report_id} of student #{$student_id}");

    // แจ้งเตือนนักเรียน
    if (!empty($comment)) {
        $supervisor_name = $u['fullname'] ?? 'ผู้ดูแลการฝึกงาน';
        add_notification(
            $student_id,
            '💬 ผู้ดูแลการฝึกงานให้ feedback แล้ว',
            "{$supervisor_name} ได้เขียน feedback สำหรับบันทึกงานของคุณ",
            'info',
            BASE_URL . '/student/view_report.php'
        );
    }
    echo json_encode(['success' => true, 'comment' => $comment, 'commented_at' => $now]);
} else {
    echo json_encode(['success' => false, 'message' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง']);
}

