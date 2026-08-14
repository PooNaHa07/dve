<?php
// mentors/delete_mentor.php - หน้าลบข้อมูลครูนิเทศก์
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']); // กำหนดสิทธิ์ให้เฉพาะ staff เข้าถึงได้

// 1. รับค่า ID จาก URL (GET)
$mentor_id = isset($_GET['id']) ? $_GET['id'] : null; 

// ตรวจสอบว่ามี ID ถูกส่งมาและเป็นตัวเลขที่ถูกต้อง
if (!empty($mentor_id) && is_numeric($mentor_id)) {

    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $stmt = $conn->prepare("DELETE FROM mentors WHERE id = ?");
    
    // ผูกตัวแปร (i คือ integer)
    $stmt->bind_param("i", $mentor_id);
    
    // ดำเนินการลบ
    if ($stmt->execute()) {
        // หากลบสำเร็จ
        $_SESSION['success_message'] = "ลบข้อมูลครูนิเทศก์ ID: " . $mentor_id . " สำเร็จแล้ว";
    } else {
        // หากลบไม่สำเร็จ
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
    }
    
    $stmt->close();
    
} else {
    // หากไม่พบ ID
    $_SESSION['error_message'] = "ไม่พบ ID ครูนิเทศก์ที่ต้องการลบ";
}

// 2. เปลี่ยนเส้นทางกลับไปยังหน้ารายการครูนิเทศก์
header("Location: list.php");
exit();
?>