<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$u = current_user();
$teacher_id = (int)$u['id'];

// [SECURITY]: ดึงข้อมูลพร้อมตรวจความเจ้าของ (IDOR) ป้องกันครูเข้าไปแก้ไขแผนของครูคนอื่น
$stmt = $conn->prepare("SELECT * FROM plans WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $id, $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();

if (!$data) {
    die("Error: ไม่พบแผนการนิเทศ หรือคุณไม่มีสิทธิ์เข้าถึงเอกสารนี้");
}

if (isset($_POST['update_plan'])) {
    $title = $_POST['title'];
    $plan_date = $_POST['plan_date'];
    $note = $_POST['note'];
    $filename = $data['filename']; // ใช้ไฟล์เดิมเป็นค่าเริ่มต้น

    // ถ้ามีการอัปโหลดไฟล์ใหม่
    if (!empty($_FILES['plan_file']['name'])) {
        @unlink("../uploads/plans/" . $data['filename']); // ลบไฟล์เก่า
        $ext = pathinfo($_FILES['plan_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['plan_file']['tmp_name'], "../uploads/plans/" . $filename);
    }

    $stmt = $conn->prepare("UPDATE plans SET title=?, plan_date=?, note=?, filename=? WHERE id=?");
    $stmt->bind_param("ssssi", $title, $plan_date, $note, $filename, $id);
    
    if ($stmt->execute()) {
        header("Location: plans.php?msg=updated");
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../includes/teacher_style.css">

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="plans.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-pencil-square icon-gradient"></i></div>
            แก้ไขแผนการนิเทศ
        </h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="premium-card">
                <div class="premium-card-header bg-white">
                    <span class="fw-bold text-dark"><i class="bi bi-info-circle me-1 text-primary"></i> แก้ไขรายละเอียดเอกสาร</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-4">
                            <label class="form-label fw-bold">หัวข้อการนิเทศ <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control border-primary-light" value="<?php echo htmlspecialchars($data['title']); ?>" required>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label fw-bold">วันที่ลงพื้นที่ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-calendar-event text-primary"></i></span>
                                <input type="date" name="plan_date" class="form-control" value="<?php echo $data['plan_date']; ?>" required>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label fw-bold">ไฟล์เอกสารเดิม</label>
                            <div class="d-flex align-items-center p-2 bg-light border rounded mb-2">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                <div class="flex-grow-1 text-truncate small"><?php echo htmlspecialchars($data['filename']); ?></div>
                                <a href="../uploads/plans/<?php echo $data['filename']; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye"></i> ดูไฟล์</a>
                            </div>
                            <label class="form-label fw-bold small mt-2">อัปโหลดไฟล์ใหม่ทดแทน (เฉพาะ PDF, ปล่อยว่างเพื่อใช้ไฟล์เดิม)</label>
                            <input type="file" name="plan_file" class="form-control" accept=".pdf">
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label fw-bold">หมายเหตุ / รายละเอียดเพิ่มเติม</label>
                            <textarea name="note" class="form-control" rows="3"><?php echo htmlspecialchars($data['note']); ?></textarea>
                        </div>

                        <hr class="my-4 border-light">

                        <div class="d-grid gap-2">
                            <button type="submit" name="update_plan" class="btn btn-tch-gradient py-3 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-save-fill me-2"></i> บันทึกการแก้ไข
                            </button>
                            <a href="plans.php" class="btn btn-link text-muted btn-sm">ยกเลิกและย้อนกลับ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>