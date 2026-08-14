<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

if (isset($_POST['submit_subject'])) {
    $s_code = $_POST['subject_code'];
    $s_name = $_POST['subject_name'];
    $u = current_user();
    $teacher_id = $u['id'];

    // จัดการไฟล์
    $filename = $_FILES['pdf_file']['name'];
    $target_dir = "../uploads/subject_plans/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $new_filename = time() . "_" . uniqid() . ".pdf";
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_dir . $new_filename)) {
        
        $stmt = $conn->prepare("INSERT INTO subject_plans (teacher_id, subject_code, subject_name, company_id, filename) VALUES (?, ?, ?, ?, ?)");
        $company_id = null;
        $stmt->bind_param("isiss", $teacher_id, $s_code, $s_name, $company_id, $new_filename);
        $stmt->execute();
        $stmt->close();

        header("Location: subject_plans.php?msg=success");
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../includes/teacher_style.css">

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="subject_plans.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-journal-plus icon-gradient"></i></div>
            เพิ่มแผนฝึกอาชีพรายวิชา
        </h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="premium-card">
                <div class="premium-card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-card-list text-primary me-2"></i> รายละเอียดรายวิชา</h6>
                </div>
                <div class="card-body p-4 pt-0">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">รหัสวิชา <span class="text-danger">*</span></label>
                                <input type="text" name="subject_code" class="form-control border-primary-light" placeholder="เช่น 30000-2001" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold text-dark">ชื่อรายวิชา <span class="text-danger">*</span></label>
                                <input type="text" name="subject_name" class="form-control border-primary-light" placeholder="ระบุชื่อวิชาตามหลักสูตร" required>
                            </div>
                        </div>
                        
                        

                        <div class="form-group mb-4">
                            <label class="form-label fw-bold text-dark">อัปโหลดไฟล์เอกสารแผนการฝึก (PDF) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-file-earmark-pdf text-danger fs-5"></i></span>
                                <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_subject" class="btn btn-tch-gradient btn-lg fw-bold rounded-pill py-3">
                                <i class="bi bi-save2-fill me-2"></i> บันทึกข้อมูล
                            </button>
                            <a href="subject_plans.php" class="btn btn-link text-muted btn-sm">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>