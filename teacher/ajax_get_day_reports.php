<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$u = current_user();
$teacher_id = (int)$u['id'];

$action = $_GET['action'] ?? 'day';

// ─────────────────────────────────────────────────────────────────
// ACTION: Get calendar summary for a month (dots/counts per day)
// ─────────────────────────────────────────────────────────────────
if ($action === 'month') {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    if ($month < 1 || $month > 12) { echo json_encode(['success' => false]); exit; }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-t', strtotime($start));

    // Get room IDs for this teacher
    $room_ids = [];
    $res_rooms = $conn->query("SELECT classroom_id FROM teacher_assignments WHERE teacher_id = $teacher_id");
    while ($r = $res_rooms->fetch_assoc()) { $room_ids[] = (int)$r['classroom_id']; }

    if (empty($room_ids)) {
        echo json_encode(['success' => true, 'days' => []]);
        exit;
    }
    $in_rooms = implode(',', $room_ids);

    $stmt = $conn->prepare("
        SELECT 
            dr.date_work,
            COUNT(*) as total,
            SUM(CASE WHEN dr.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN dr.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN dr.status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM daily_reports dr
        JOIN users u ON dr.student_id = u.id
        WHERE dr.date_work BETWEEN ? AND ?
          AND u.classroom_id IN ($in_rooms)
          AND u.role = 'student'
        GROUP BY dr.date_work
        ORDER BY dr.date_work ASC
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $days = [];
    while ($row = $result->fetch_assoc()) {
        $days[$row['date_work']] = [
            'total'    => (int)$row['total'],
            'pending'  => (int)$row['pending'],
            'approved' => (int)$row['approved'],
            'rejected' => (int)$row['rejected'],
        ];
    }

    echo json_encode(['success' => true, 'days' => $days]);
    exit;
}

// ─────────────────────────────────────────────────────────────────
// ACTION: Get all reports for a specific date
// ─────────────────────────────────────────────────────────────────
if ($action === 'day') {
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date']);
        exit;
    }

    $room_ids = [];
    $res_rooms = $conn->query("SELECT classroom_id FROM teacher_assignments WHERE teacher_id = $teacher_id");
    while ($r = $res_rooms->fetch_assoc()) { $room_ids[] = (int)$r['classroom_id']; }

    if (empty($room_ids)) {
        echo json_encode(['success' => true, 'reports' => []]);
        exit;
    }
    $in_rooms = implode(',', $room_ids);

    $stmt = $conn->prepare("
        SELECT dr.*, 
               u.fullname, u.student_code, u.profile_image,
               COALESCE(comp.name, u.company_name) as company_name,
               cl.class_name
        FROM daily_reports dr
        JOIN users u ON dr.student_id = u.id
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id
        LEFT JOIN companies comp ON u.company_id = comp.id
        WHERE dr.date_work = ?
          AND u.classroom_id IN ($in_rooms)
          AND u.role = 'student'
        ORDER BY dr.status ASC, u.student_code ASC
    ");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        // Sanitize output
        $reports[] = [
            'id'              => (int)$row['id'],
            'student_id'      => (int)$row['student_id'],
            'fullname'        => htmlspecialchars($row['fullname']),
            'student_code'    => htmlspecialchars($row['student_code'] ?? ''),
            'class_name'      => htmlspecialchars($row['class_name'] ?? ''),
            'company_name'    => htmlspecialchars($row['company_name'] ?? ''),
            'profile_image'   => $row['profile_image'] ? htmlspecialchars($row['profile_image']) : null,
            'date_work'       => $row['date_work'],
            'details'         => htmlspecialchars($row['details'] ?? ''),
            'problems'        => htmlspecialchars($row['problems'] ?? ''),
            'solutions'       => htmlspecialchars($row['solutions'] ?? ''),
            'teacher_comment' => htmlspecialchars($row['teacher_comment'] ?? ''),
            'image1'          => $row['image1'] ? htmlspecialchars($row['image1']) : null,
            'image2'          => $row['image2'] ? htmlspecialchars($row['image2']) : null,
            'status'          => $row['status'],
            'approved_at'     => $row['approved_at'],
            'created_at'      => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'reports' => $reports, 'date' => $date]);
    exit;
}

// ─────────────────────────────────────────────────────────────────
// ACTION: Approve a report
// ─────────────────────────────────────────────────────────────────
if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    if ($report_id <= 0) { echo json_encode(['success' => false]); exit; }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE daily_reports SET status = 'approved', approved_by = ?, approved_at = ?
        WHERE id = ?
        AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id WHERE ta.teacher_id = ?)");
    $stmt->bind_param('isii', $teacher_id, $now, $report_id, $teacher_id);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}

// ─────────────────────────────────────────────────────────────────
// ACTION: Reject a report
// ─────────────────────────────────────────────────────────────────
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    $comment   = trim($_POST['comment'] ?? '');
    if ($report_id <= 0) { echo json_encode(['success' => false]); exit; }

    $stmt = $conn->prepare("UPDATE daily_reports SET status = 'rejected', teacher_comment = ?, approved_by = ?, approved_at = NOW()
        WHERE id = ?
        AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id WHERE ta.teacher_id = ?)");
    $stmt->bind_param('siii', $comment, $teacher_id, $report_id, $teacher_id);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}

// ─────────────────────────────────────────────────────────────────
// ACTION: Edit Comment
// ─────────────────────────────────────────────────────────────────
if ($action === 'edit_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    $comment   = trim($_POST['comment'] ?? '');
    if ($report_id <= 0) { echo json_encode(['success' => false]); exit; }

    $stmt = $conn->prepare("UPDATE daily_reports SET teacher_comment = ?
        WHERE id = ? AND status = 'rejected'
        AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id WHERE ta.teacher_id = ?)");
    $stmt->bind_param('sii', $comment, $report_id, $teacher_id);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
