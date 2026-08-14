<?php
// calendar/edit_event.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['staff']);
include __DIR__ . '/../includes/header.php';

$event_id = isset($_GET['id']) ? $_GET['id'] : null;
$error = '';
$success = '';
$event = null; // ข้อมูลกิจกรรมที่ดึงมา

// 1. ดึงข้อมูลกิจกรรมปัจจุบันมาแสดงในฟอร์ม
if ($event_id) {
    $stmt = $conn->prepare("SELECT id, title, description, event_date FROM calendar_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $event = $result->fetch_assoc();
    } else {
        $error = "ไม่พบข้อมูลกิจกรรมที่ระบุ";
    }
    $stmt->close();
} else {
    $error = "ไม่พบ ID กิจกรรม";
}

// 2. ประมวลผลเมื่อมีการส่งฟอร์ม (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event_id) {
    // รับค่าจากฟอร์มและทำความสะอาดข้อมูล
    $title_input = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $description_input = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $event_date_input = trim(isset($_POST['event_date']) ? $_POST['event_date'] : '');

    // การตรวจสอบข้อมูลพื้นฐาน
    if (empty($title_input) || empty($event_date_input)) {
        $error = "กรุณากรอกชื่อกิจกรรมและวันที่จัดกิจกรรมให้ครบถ้วน";
    } else {
        // แปลงรูปแบบวันที่
        $event_date_update = date('Y-m-d H:i:s', strtotime($event_date_input . ' 00:00:00'));
        
        // ใช้ Prepared Statement สำหรับ UPDATE
        $stmt_update = $conn->prepare(
            "UPDATE calendar_events SET title = ?, description = ?, event_date = ? WHERE id = ?"
        );
        $stmt_update->bind_param("sssi", $title_input, $description_input, $event_date_update, $event_id);
        
        if ($stmt_update->execute()) {
            $success = "แก้ไขกิจกรรม **" . e($title_input) . "** สำเร็จแล้ว!";
            
            // อัปเดตตัวแปร $event ให้แสดงข้อมูลใหม่ในฟอร์มทันที
            $event['title'] = $title_input;
            $event['description'] = $description_input;
            $event['event_date'] = $event_date_update;
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error;
        }
        $stmt_update->close();
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>แก้ไขกิจกรรม: <?= e(isset($event['title']) ? $event['title'] : 'ID ' . $event_id) ?></h3>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> กลับไปหน้ารายการกิจกรรม
        </a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <?php if ($event): ?>
    <div class="pastel-card p-4">
        <form method="POST">
            <div class="mb-3">
                <label for="title" class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" value="<?= e($event['title']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="event_date" class="form-label">วันที่จัดกิจกรรม <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="event_date" name="event_date" value="<?= e(date('Y-m-d', strtotime($event['event_date']))) ?>" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e($event['description']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-pastel">
                <i class="bi bi-save"></i> บันทึกการแก้ไข
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>