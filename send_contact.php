<?php
// 1. เรียกใช้ไฟล์ตั้งค่าฐานข้อมูลและฟังก์ชันต่างๆ
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';

// ตั้งค่า Timezone ให้เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// 2. ตรวจสอบว่ามีการส่งข้อมูลผ่าน POST มาจริงหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. รับค่าและทำความสะอาดข้อมูล (ป้องกัน SQL Injection)
    $name    = mysqli_real_escape_string($conn, $_POST['name']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // 4. ตรวจสอบค่าว่าง (Validation เบื้องต้น)
    if (empty($name) || empty($contact) || empty($message)) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>window.addEventListener('load',function(){ Swal.fire({ icon:'warning', title:'กรุณาตรวจสอบ', text:'กรุณากรอกข้อมูลให้ครบถ้วน', confirmButtonText:'ตกลง', confirmButtonColor:'#0d6efd' }).then(function(){ window.history.back(); }); });</script>";
        exit();
    }

    // 5. คำสั่ง SQL สำหรับบันทึกข้อมูลเข้าตาราง contact_messages
    // กำหนด status เป็น 'unread' เพื่อให้ไปแจ้งเตือนที่ Dashboard
    $user_id_val = is_logged_in() ? (int)$_SESSION['user_id'] : "NULL";
    $sql = "INSERT INTO contact_messages (user_id, sender_name, contact_info, subject, message, status, created_at) 
            VALUES ($user_id_val, '$name', '$contact', '$subject', '$message', 'unread', NOW())";

    // 6. ประมวลผลและส่งผลลัพธ์กลับไปยังหน้าเว็บ
    if ($conn->query($sql)) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>window.addEventListener('load',function(){ Swal.fire({ icon:'success', title:'ส่งสำเร็จ', text:'ขอบคุณที่ติดต่อเรา เจ้าหน้าที่จะรีบดำเนินการตรวจสอบโดยเร็ว', confirmButtonText:'ตกลง', confirmButtonColor:'#0d6efd' }).then(function(){ window.location.href='contact.php'; }); });</script>";
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>window.addEventListener('load',function(){ Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:'ไม่สามารถบันทึกข้อมูลได้ในขณะนี้', confirmButtonText:'ตกลง', confirmButtonColor:'#dc3545' }).then(function(){ window.history.back(); }); });</script>";
    }

} else {
    // หากพยายามเข้าถึงไฟล์นี้โดยตรงโดยไม่ผ่านการกดปุ่มส่งฟอร์ม
    header("Location: contact.php");
    exit();
}
?>