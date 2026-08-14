<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

/**
 * Cron: แจ้งเตือนผู้บริหาร เมื่อครูนิเทศก์ยังไม่ตรวจรายงานของนักเรียนเกิน 7 วัน
 * ควรรันทุกวัน เช่น: 0 8 * * * php /path/to/cron/check_teacher_review.php
 */

// หา report ที่ยังไม่ถูกครูตรวจภายใน 7 วัน
$sql = "
SELECT 
    dr.id AS report_id,
    dr.student_id,
    dr.date_work,
    dr.created_at,
    u.fullname AS student_name,
    u.mentor_id
FROM daily_reports dr
JOIN users u ON dr.student_id = u.id
WHERE dr.status = 'pending'
AND dr.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {

    // ดึงผู้บริหารทั้งหมด
    $admins_res = $conn->query("SELECT id FROM users WHERE role IN ('director','admin')");
    $admin_ids = [];
    if ($admins_res) {
        while ($admin = $admins_res->fetch_assoc()) {
            $admin_ids[] = (int)$admin['id'];
        }
    }

    while ($row = $res->fetch_assoc()) {
        $student_name = $row['student_name'];
        $report_id    = (int)$row['report_id'];
        $date_work    = !empty($row['date_work']) ? $row['date_work'] : substr($row['created_at'], 0, 10);

        $title   = '⏰ ครูนิเทศก์ยังไม่ตรวจรายงาน';
        $msg     = "นักเรียน {$student_name} มีรายงานวันที่ {$date_work} ที่รอการตรวจเกิน 7 วันแล้ว";
        $url     = BASE_URL . '/teacher/approve_reports.php';

        // แจ้งเตือนผู้บริหารทุกคน
        foreach ($admin_ids as $admin_id) {
            add_notification($admin_id, $title, $msg, 'review_late', $url);
        }

        // แจ้งเตือนครูนิเทศก์โดยตรง (ถ้ามี mentor_id)
        if (!empty($row['mentor_id'])) {
            $mentor_title = '⏰ มีรายงานรอตรวจเกิน 7 วัน';
            $mentor_msg   = "รายงานของ {$student_name} วันที่ {$date_work} ยังรอการตรวจสอบอยู่";
            add_notification((int)$row['mentor_id'], $mentor_title, $mentor_msg, 'review_late', $url);
        }
    }
}

// Auto-cleanup expired notifications to keep DB clean
$conn->query("DELETE FROM notifications WHERE (expires_at IS NOT NULL AND expires_at < NOW()) OR (created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))");

echo date('Y-m-d H:i:s') . " — check_teacher_review cron completed.\n";
