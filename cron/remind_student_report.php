<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

/**
 * Cron: แจ้งเตือนนักเรียนที่ยังไม่ได้ส่งบันทึกรายงานประจำวันของวันนี้
 * ควรรันทุกวันทำการเวลา 16:00 น.
 * ตัวอย่างการตั้ง Cron: 0 16 * * 1-5 php /path/to/cron/remind_student_report.php
 */

// 1. ตรวจสอบวันหยุดเสาร์-อาทิตย์ (1 = วันจันทร์, 7 = วันอาทิตย์)
$day_of_week = (int)date('N');
if ($day_of_week > 5) {
    echo date('Y-m-d H:i:s') . " — Weekend (Saturday/Sunday), skipping student report reminders.\n";
    exit;
}

// 2. หานักเรียนที่ยังไม่ได้ส่งรายงานของวันนี้ และอยู่ในช่วงเวลาฝึกงาน (หรือยังไม่ได้ระบุวัน)
$sql = "
SELECT u.id, u.fullname, u.student_level
FROM users u
LEFT JOIN internship_settings i ON u.student_level = i.level_name
WHERE u.role = 'student'
  AND u.mentor_id IS NOT NULL
  AND (
      i.start_date IS NULL 
      OR i.end_date IS NULL 
      OR (CURDATE() BETWEEN i.start_date AND i.end_date)
  )
  AND u.id NOT IN (
      SELECT student_id 
      FROM daily_reports 
      WHERE date_work = CURDATE()
  )
";

$res = $conn->query($sql);
$reminded_count = 0;

if ($res && $res->num_rows > 0) {
    $title = '⏰ อย่าลืมบันทึกงานประจำวันวันนี้!';
    $message = 'คุณยังไม่ได้ส่งบันทึกการฝึกงานของวันนี้ กรุณากรอกและส่งรายงานก่อนสิ้นวันทำงานนะครับ';
    $action_url = BASE_URL . '/student/submit_report.php';

    while ($row = $res->fetch_assoc()) {
        $student_id = (int)$row['id'];
        // ส่งแจ้งเตือนผ่านฟังก์ชันระบบ
        add_notification($student_id, $title, $message, 'report_late_reminder', $action_url);
        $reminded_count++;
    }
}

// 3. Auto-cleanup expired notifications to keep DB clean
$conn->query("DELETE FROM notifications WHERE (expires_at IS NOT NULL AND expires_at < NOW()) OR (created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))");

echo date('Y-m-d H:i:s') . " — remind_student_report cron completed. Reminded $reminded_count students.\n";
