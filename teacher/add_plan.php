<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id']; // ดึง ID ของครูที่ Login อยู่

// --- ส่วนบันทึกข้อมูล ---
if (isset($_POST['submit_plan'])) {
    // กำหนดค่าเริ่มต้นเนื่องจากถูกตัดออกจากหน้าจอ
    $title = "แผนการนิเทศอัปโหลดเมื่อ " . date('d/m/Y H:i');
    $plan_date = date('Y-m-d');
    $company_id = 'all'; // กระจายไปยังทุกสถานประกอบการในความดูแล
    $note = "";

    // 1. จัดการอัปโหลดไฟล์
    $file_new_name = "";
    if (!empty($_FILES['plan_file']['name'])) {
        $target_dir = "../uploads/plans/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // สร้างโฟลเดอร์ถ้ายังไม่มี
        }
        
        $file_ext = pathinfo($_FILES['plan_file']['name'], PATHINFO_EXTENSION);
        $file_new_name = time() . "_" . uniqid() . "." . $file_ext;
        $target_file = $target_dir . $file_new_name;

        if (!move_uploaded_file($_FILES['plan_file']['tmp_name'], $target_file)) {
            header("Location: plans.php?msg=error");
            exit;
        }
    } else {
        // ถ้าไม่เลือกไฟล์เลย ให้กลับไป
        header("Location: plans.php?msg=error");
        exit;
    }

    // 2. เตรียมคำสั่ง SQL
    $sql_insert = "INSERT INTO plans (teacher_id, company_id, title, plan_date, note, filename) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql_insert);

    if ($stmt === false) {
        die("SQL Error: " . $conn->error);
    }

    if ($company_id === 'all') {
        // กรณีเลือก "ทั้งหมดทุกที่" ที่นักเรียนในปกครองอยู่
        $stmt_comps = $conn->prepare("
            SELECT DISTINCT c.id 
            FROM companies c 
            JOIN users u ON u.company_id = c.id 
            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id 
            WHERE ta.teacher_id = ? AND u.role = 'student'
        ");
        $stmt_comps->bind_param("i", $teacher_id);
        $stmt_comps->execute();
        $all_comps = $stmt_comps->get_result();
        
        $c_ids = [];
        while ($row = $all_comps->fetch_assoc()) {
            $c_ids[] = $row['id'];
        }

        // เพิ่มสถานประกอบการที่นักเรียนพิมพ์เอง (ถ้ามี)
        $stmt_cust = $conn->prepare("
            SELECT DISTINCT u.company_name 
            FROM users u
            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
            WHERE ta.teacher_id = ? AND u.role = 'student' 
              AND (u.company_id IS NULL OR u.company_id = 0)
              AND u.company_name IS NOT NULL AND u.company_name != ''
        ");
        $stmt_cust->bind_param("i", $teacher_id);
        $stmt_cust->execute();
        $cust_comps = $stmt_cust->get_result();
        while ($row = $cust_comps->fetch_assoc()) {
            $c_name = trim($row['company_name']);
            $chk = $conn->prepare("SELECT id FROM companies WHERE name = ?");
            $chk->bind_param("s", $c_name);
            $chk->execute();
            $res_chk = $chk->get_result();
            if ($res_chk->num_rows > 0) {
                $cid = $res_chk->fetch_assoc()['id'];
            } else {
                $ins = $conn->prepare("INSERT INTO companies (name) VALUES (?)");
                $ins->bind_param("s", $c_name);
                $ins->execute();
                $cid = $ins->insert_id;
            }
            if (!in_array($cid, $c_ids)) {
                $c_ids[] = $cid;
            }
        }
        
        $count = 0;
        foreach ($c_ids as $c_id) {
            $stmt->bind_param("iissss", $teacher_id, $c_id, $title, $plan_date, $note, $file_new_name);
            if ($stmt->execute()) $count++;
        }
        $msg = "บันทึกและกระจายแผนนิเทศสำเร็จสำหรับ $count สถานประกอบการ";
    } else {
        $c_id = (int)$company_id;
        $stmt->bind_param("iissss", $teacher_id, $c_id, $title, $plan_date, $note, $file_new_name);
        if ($stmt->execute()) {
            $msg = "บันทึกแผนการนิเทศเรียบร้อยแล้ว";
        }
    }
    
    if (isset($count) && $count > 0) {
        header("Location: plans.php?msg=success&count=" . $count);
    } else {
        header("Location: plans.php?msg=success");
    }
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../includes/teacher_style.css">

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="plans.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-file-earmark-plus icon-gradient"></i></div>
            อัปโหลดแผนการนิเทศใหม่
        </h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="premium-card">
                <div class="premium-card-header bg-white">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-upload text-primary me-2"></i> เลือกไฟล์แผนการนิเทศ</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="form-group mb-4">
                            <label class="form-label fw-bold text-dark">ไฟล์เอกสารแผนงาน (.pdf เท่านั้น) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-filetype-pdf text-danger fs-5"></i></span>
                                <input type="file" name="plan_file" class="form-control" accept=".pdf" required>
                            </div>
                            <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle me-1"></i> ระบบจะนำแผนนี้ไปใช้กับสถานประกอบการในความดูแลทั้งหมดโดยอัตโนมัติ</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_plan" class="btn btn-tch-gradient btn-lg fw-bold rounded-pill py-3 shadow-sm">
                                <i class="bi bi-cloud-arrow-up-fill me-2"></i> บันทึกและอัปโหลดข้อมูล
                            </button>
                            <a href="plans.php" class="btn btn-link text-muted btn-sm mt-1">ยกเลิกและย้อนกลับ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>