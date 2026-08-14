<?php
// calendar/add_event.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_role(['staff']);
include __DIR__ . '/../includes/header.php';

// กำหนดเส้นทางสำหรับอัปโหลดรูปภาพ
$upload_dir = __DIR__ . '/../uploads/event_images/';
$web_upload_path = '/uploads/event_images/'; // Path ที่ใช้ใน HTML/URL

$error = '';
$success = '';

// ค่าเริ่มต้นสำหรับฟอร์ม
$title = '';
$description = '';
$event_date = date('Y-m-d'); // วันที่วันนี้

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ต้องเพิ่ม enctype="multipart/form-data" ในแท็ก <form>
    // ตรวจสอบและจัดการไฟล์อัปโหลดก่อน
    $image_filename = NULL; // ค่าเริ่มต้นเป็น NULL หากไม่มีการอัปโหลด
    $upload_ok = true; // สถานะการอัปโหลด

    // รับและทำความสะอาดข้อมูล
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $event_date_input = trim(isset($_POST['event_date']) ? $_POST['event_date'] : '');
    $created_at = date('Y-m-d H:i:s');
    
    // ตรวจสอบข้อมูลพื้นฐาน
    if (empty($title) || empty($event_date_input)) {
        $error = "กรุณากรอกชื่อกิจกรรมและวันที่จัดกิจกรรมให้ครบถ้วน";
        $upload_ok = false; // ไม่ต้องดำเนินการอัปโหลดต่อถ้าข้อมูลหลักไม่ครบ
    }
    
    // --- ส่วนจัดการไฟล์อัปโหลด ---
    if ($upload_ok && isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['event_image']['name'];
        $file_tmp = $_FILES['event_image']['tmp_name'];
        $file_size = $_FILES['event_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // 1. ตรวจสอบนามสกุลไฟล์
        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "ไม่อนุญาตไฟล์ประเภท {$file_ext} กรุณาใช้ไฟล์ JPG, JPEG, PNG, หรือ GIF เท่านั้น";
            $upload_ok = false;
        }
        
        // 2. ตรวจสอบขนาดไฟล์ (เช่น ไม่เกิน 5MB)
        if ($file_size > 5 * 1024 * 1024) {
            $error = "ขนาดไฟล์เกิน 5MB";
            $upload_ok = false;
        }

        // 3. สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
        if ($upload_ok) {
            $image_filename = uniqid('event_', true) . '.' . $file_ext;
            $destination = $upload_dir . $image_filename;
            
            // 4. ย้ายไฟล์
            if (!move_uploaded_file($file_tmp, $destination)) {
                $error = "เกิดข้อผิดพลาดในการบันทึกไฟล์อัปโหลด";
                $image_filename = NULL; // ตั้งค่าเป็น NULL ถ้าการย้ายไฟล์ล้มเหลว
                $upload_ok = false;
            }
        }
    }
    
    // --- ส่วนบันทึกข้อมูลลงฐานข้อมูล ---
    if ($upload_ok && empty($error)) {
        $event_date = date('Y-m-d H:i:s', strtotime($event_date_input . ' 00:00:00'));
        
        // ใช้ Prepared Statement สำหรับ INSERT (เพิ่ม image_filename)
        $stmt = $conn->prepare(
            "INSERT INTO calendar_events (title, description, image_filename, event_date, created_at) VALUES (?, ?, ?, ?, ?)"
        );
        // "sssss" คือ string สำหรับ 5 พารามิเตอร์ (title, description, image_filename, event_date, created_at)
        $stmt->bind_param("sssss", $title, $description, $image_filename, $event_date, $created_at);
        
        if ($stmt->execute()) {
            $success = "เพิ่มกิจกรรม **" . e($title) . "** สำเร็จแล้ว!";
            $title = $description = '';
            $event_date = date('Y-m-d');
        } else {
            if ($image_filename && file_exists($upload_dir . $image_filename)) {
                @unlink($upload_dir . $image_filename);
            }
            $errno = $conn->errno;
            $errmsg = $conn->error;
            if ($errno == 1062 || (is_string($errmsg) && stripos($errmsg, 'Duplicate') !== false)) {
                $error = "ข้อมูลกิจกรรมซ้ำในระบบ กรุณาตรวจสอบ";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล กรุณาลองใหม่อีกครั้ง";
            }
        }
        $stmt->close();
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>เพิ่มกิจกรรมในปฏิทินใหม่</h3>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> กลับไปหน้ารายการกิจกรรม
        </a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="pastel-card p-4">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" value="<?= e($title) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="event_date" class="form-label">วันที่จัดกิจกรรม <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="event_date" name="event_date" value="<?= e(date('Y-m-d', strtotime($event_date))) ?>" required>
                <small class="form-text text-muted">เลือกวันที่กิจกรรมจะเกิดขึ้น</small>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e($description) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="event_image" class="form-label">รูปภาพประชาสัมพันธ์ (ไม่จำเป็น)</label>
                <input type="file" class="form-control" id="event_image" name="event_image" accept="image/jpeg, image/png, image/gif">
                <small class="form-text text-muted">อนุญาตเฉพาะไฟล์ JPG, PNG, GIF (ไม่เกิน 5MB)</small>
            </div>
            
            <button type="submit" class="btn btn-pastel">
                <i class="bi bi-calendar-check"></i> บันทึกกิจกรรม
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>