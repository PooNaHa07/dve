<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $sup_id = (int)$_GET['id'];
    $class_id = (int)$_GET['class_id'];
    $now = date('Y-m-d H:i:s');

    // อัปเดต status เป็น 2 (ลงนามแล้ว) และบันทึกเวลา
    $sql = "UPDATE supervision_files SET status = 2, director_signed_at = ? WHERE id = ? AND status = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $now, $sup_id);

    if ($stmt->execute()) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>window.addEventListener('load',function(){ Swal.fire({ icon:'success', title:'ลงนามแล้ว', text:'ลงนามรับทราบเรียบร้อยแล้ว', confirmButtonText:'ตกลง', confirmButtonColor:'#0d6efd' }).then(function(){ window.location.href='view_students.php?class_id=$class_id'; }); });</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>