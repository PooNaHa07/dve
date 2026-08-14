<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

// 1. ดึงข้อมูลเดิมออกมาแสดงในฟอร์ม
if (!isset($_GET['id'])) {
    header("Location: subject_plans.php");
    exit;
}

$id = (int)$_GET['id'];
$u = current_user();
$teacher_id = (int)$u['id'];

// [SECURITY]: ตรวจสอบสิทธิ์ (IDOR check) เพื่อป้องกันการแก้ไขข้อมูลของครูคนอื่น
$stmt = $conn->prepare("SELECT * FROM subject_plans WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $id, $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();

if (!$data) {
    echo "ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้";
    exit;
}

// 2. ส่วนการอัปเดตข้อมูลเมื่อกดปุ่มบันทึก
if (isset($_POST['update_subject'])) {
    $s_code = $_POST['subject_code'];
    $s_name = $_POST['subject_name'];
    $company_id = null;
    $new_filename = $data['filename']; // ใช้ชื่อไฟล์เดิมเป็นค่าเริ่มต้น

    // ตรวจสอบว่ามีการอัปโหลดไฟล์ใหม่หรือไม่
    if (!empty($_FILES['pdf_file']['name'])) {
        $target_dir = "../uploads/subject_plans/";
        
        // ลบไฟล์เก่าทิ้งก่อน (ถ้ามี)
        if ($data['filename']) {
            @unlink($target_dir . $data['filename']);
        }

        // อัปโหลดไฟล์ใหม่
        $ext = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
        $new_filename = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_dir . $new_filename);
    }

    // อัปเดตข้อมูลลงฐานข้อมูล
    $stmt = $conn->prepare("UPDATE subject_plans SET subject_code=?, subject_name=?, company_id=?, filename=? WHERE id=?");
    $stmt->bind_param("ssisi", $s_code, $s_name, $company_id, $new_filename, $id);
    
    if ($stmt->execute()) {
        header("Location: subject_plans.php?msg=updated");
        exit;
    } else {
        echo "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../includes/teacher_style.css">

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="subject_plans.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-pencil-square icon-gradient"></i></div>
            แก้ไขแผนฝึกอาชีพรายวิชา
        </h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="premium-card">
                <div class="premium-card-header bg-white">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i> แก้ไขข้อมูลแผนงาน</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">รหัสวิชา <span class="text-danger">*</span></label>
                                <input type="text" name="subject_code" class="form-control border-primary-light" value="<?php echo htmlspecialchars($data['subject_code']); ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">ชื่อรายวิชา <span class="text-danger">*</span></label>
                                <input type="text" name="subject_name" class="form-control border-primary-light" value="<?php echo htmlspecialchars($data['subject_name']); ?>" required>
                            </div>
                        </div>

                        

                        <div class="form-group mb-4">
                            <label class="form-label fw-bold">เอกสารปัจจุบัน</label>
                            <div class="d-flex align-items-center p-2 bg-light border rounded mb-2">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                <div class="flex-grow-1 text-truncate small"><?php echo htmlspecialchars($data['filename']); ?></div>
                                <a href="../uploads/subject_plans/<?php echo $data['filename']; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye"></i> ดูไฟล์เดิม</a>
                            </div>
                            
                            <label class="form-label fw-bold small mt-2">อัปโหลดไฟล์ใหม่ (เฉพาะ .pdf, ปล่อยว่างถ้าไม่ต้องการแก้)</label>
                            <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                        </div>

                        <hr class="my-4 border-light">

                        <div class="d-grid gap-2">
                            <button type="submit" name="update_subject" class="btn btn-tch-gradient py-3 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-save-fill me-2"></i> บันทึกการแก้ไขข้อมูล
                            </button>
                            <a href="subject_plans.php" class="btn btn-link text-muted btn-sm mt-1">ยกเลิกย้อนกลับ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>