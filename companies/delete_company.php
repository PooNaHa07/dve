<?php
// companies/delete_company.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_role(['staff']); 
session_start(); // ต้องมี session เพื่อเก็บข้อความแจ้งเตือน

// 1. รับค่า ID จาก URL (GET)
$company_id = isset($_GET['id']) ? $_GET['id'] : null; 

if (!empty($company_id) && is_numeric($company_id)) {

    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    
    if ($stmt->execute()) {
        // หากลบสำเร็จ
        $_SESSION['success_message'] = "ลบข้อมูลบริษัท ID: " . $company_id . " สำเร็จแล้ว";
    } else {
        // หากลบไม่สำเร็จ
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
    }
    
    $stmt->close();
    
} else {
    // หากไม่พบ ID
    $_SESSION['error_message'] = "ไม่พบ ID บริษัทที่ต้องการลบ";
}

// 2. เปลี่ยนเส้นทางกลับไปยังหน้ารายการ
header("Location: list.php");
exit();
?>