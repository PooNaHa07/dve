<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']);
include __DIR__ . '/../includes/header.php';

$err = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $image_name = null;

    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $err = "อนุญาตเฉพาะไฟล์ JPG, PNG, GIF เท่านั้น";
        } else {
            $target_dir = __DIR__ . "/../uploads/news/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $image_name = time() . '_' . rand(1000,9999) . "." . $ext;
            $target_file = $target_dir . $image_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $err = "อัปโหลดรูปไม่สำเร็จ";
            }
        }
    }

    if (!$err && $title && $content) {
        $stmt = $conn->prepare("INSERT INTO news (title, content, image, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $title, $content, $image_name);

        if ($stmt->execute()) {
            $success = "เพิ่มข่าวสารเรียบร้อยแล้ว";
        } else {
            $err = "เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่อีกครั้ง";
        }
    }
}
?>
<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page pt-4 pb-5">
    <div class="container">
        
        <!-- Header area -->
        <div class="staff-page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1"><i class="bi bi-plus-circle text-primary me-2"></i>เขียนข่าวสารใหม่</h4>
                <p class="text-muted small mb-0">เพิ่มข้อมูลข่าวสารหรือประกาศเพื่อแสดงหน้าเว็บ</p>
            </div>
            <a href="news.php" class="btn btn-outline-secondary bg-white shadow-sm border-opacity-25 rounded-pill px-4">← ยกเลิก</a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h6 class="mb-0 fw-bold text-secondary d-flex align-items-center gap-2"><i class="bi bi-pencil text-primary"></i> กรอกข้อมูลเนื้อหา</h6>
                        <hr class="border-light mt-3 mb-0">
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($err): ?>
                            <div class="alert alert-danger border-0 rounded-3 shadow-xs d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                                <div><?= e($err) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 rounded-3 shadow-xs d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= e($success) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">หัวข้อข่าว / ชื่อกิจกรรม</label>
                                <input type="text" name="title" class="form-control form-control-lg bg-light border-0 rounded-3" 
                                       placeholder="พิมพ์หัวข้อที่ต้องการให้แสดงผล..." required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">รายละเอียด</label>
                                <textarea name="content" class="form-control bg-light border-0 rounded-3" rows="8" 
                                          placeholder="พิมพ์เนื้อหาที่ต้องการประกาศรายละเอียดเพิ่มเติมที่นี่..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">ภาพประกอบปก (ไม่บังคับ)</label>
                                <div class="custom-file-input-wrapper p-3 bg-light rounded-3 text-center border-dashed">
                                    <input type="file" name="image" id="fileInput" class="form-control border-0 bg-white shadow-sm" accept="image/*">
                                    <small class="text-muted mt-2 d-block">รูปแบบไฟล์ที่รองรับ: .jpg, .png, .gif (ขนาดไม่ควรเกิน 2MB)</small>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-staff btn-lg rounded-pill py-3 shadow-sm fw-bold">
                                    <i class="bi bi-save2-fill me-2"></i> ยืนยันการบันทึกข่าวสาร
                                </button>
                            </div>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .border-dashed {
        border: 2px dashed rgba(0,0,0,0.08) !important;
    }
    .form-control:focus {
        background-color: #fff !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    .shadow-xs { box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

