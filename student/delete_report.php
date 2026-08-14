<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $student_id = (int)$_SESSION['user_id'];

    if ($id > 0) {
        // ลบไฟล์รูปภาพที่แนบมากับรายงานออกจากระบบดิสก์ก่อนลบ Record
        delete_report_images($id);

        // ป้องกันการลบข้อมูลของคนอื่น
        $stmt = $conn->prepare("DELETE FROM daily_reports WHERE id = ? AND student_id = ?");
        $stmt->bind_param("ii", $id, $student_id);

        if ($stmt->execute() && $conn->affected_rows > 0) {
            header("Location: view_report.php?msg=deleted");
        } else {
            // ไม่พบรายการ หรือไม่ใช่รายการของผู้ใช้นี้
            header("Location: view_report.php?err=delete_failed");
        }
        $stmt->close();
    } else {
        header("Location: view_report.php?err=invalid_id");
    }
} else {
    header("Location: view_report.php");
}
exit;