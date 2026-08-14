<?php
// mentors/add_mentor.php - หน้าฟอร์มเพิ่มครูนิเทศก์
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']); 
include __DIR__ . '/../includes/header.php';

// BUG FIX: Get list of potential teacher users to link to
$potentialTeachers = [];
$qUsers = $conn->query("SELECT u.id, u.username, u.fullname FROM users u 
                        LEFT JOIN mentors m ON u.id = m.user_id 
                        WHERE u.role = 'teacher' AND m.id IS NULL 
                        ORDER BY u.fullname ASC");
if ($qUsers) {
    while($r = $qUsers->fetch_assoc()) {
        $potentialTeachers[] = $r;
    }
}

// กำหนดค่าเริ่มต้นสำหรับช่องฟอร์ม
$fullname = ''; 
$department = 'วิทยาลัยอาชีวศึกษาเพชรบุรี'; 
$email = ''; 
$phone = ''; 
$selected_user_id = 0;

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับและทำความสะอาดข้อมูลจาก Form
    $fullname = trim($_POST['fullname']); 
    $department = trim($_POST['department']); 
    $email = trim($_POST['email']); 
    $phone = trim($_POST['phone']); 
    $selected_user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    $status = 1; 
    $created_at = date('Y-m-d H:i:s');

    // 3. Validation
    if (empty($fullname) || empty($department)) {
        $error_message = "กรุณากรอกข้อมูล ชื่อ-สกุล และสังกัดให้ครบถ้วน";
    } else {
        // Secure insertion
        $sql = "INSERT INTO mentors (user_id, fullname, phone, email, department, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             die('🚨 SQL Error: ' . $conn->error);
        }

        $stmt->bind_param("issssss", $selected_user_id, $fullname, $phone, $email, $department, $status, $created_at);
        
        if ($stmt->execute()) {
            $success_message = "เพิ่มข้อมูลครูนิเทศก์สำเร็จ!";
            // If successful, redirect after small delay or leave message. Let's just clear fields.
            $fullname = $department = $email = $phone = ''; 
            $selected_user_id = 0;
        } else {
            if ($conn->errno == 1062) {
                $error_message = "ข้อมูลซ้ำ (มีบัญชีผู้ใช้นี้ในฐานข้อมูลครูนิเทศก์แล้ว)";
            } else {
                $error_message = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>
<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page pt-4">
    <div class="container">
        <!-- Page Header Container -->
        <div class="staff-page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>เพิ่มครูนิเทศก์ใหม่</h4>
                <p class="text-muted mb-0 small">กรอกข้อมูลโปรไฟล์สำหรับผู้ดูแลตรวจสอบการฝึกงาน</p>
            </div>
            <div class="d-flex gap-2">
                <a href="list.php" class="btn btn-outline-secondary border-opacity-25 bg-white rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> กลับ
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success shadow-sm border-0 rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i><?= $error_message ?></div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="glass-card border-0 shadow p-4 p-md-5" style="border-radius: 20px;">
                    <h5 class="mb-4 fw-bold text-indigo-700 border-bottom pb-3">
                        <i class="bi bi-pencil-square me-2"></i>รายละเอียดโปรไฟล์
                    </h5>
                    <form method="POST" action="">
            
                        <!-- Link to User Account Dropdown -->
                        <div class="mb-4">
                            <label for="user_id" class="form-label fw-bold text-secondary"><i class="bi bi-person-badge me-1"></i>ผูกกับบัญชีผู้ใช้งาน (ถ้ามี)</label>
                            <select class="form-select form-select-lg border-0 shadow-sm rounded-3 bg-light" id="user_id" name="user_id" style="font-size: 0.95rem;">
                                <option value="0">-- ไม่ผูกบัญชี (สร้างข้อมูลอย่างเดียว) --</option>
                                <?php foreach($potentialTeachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $selected_user_id == $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['fullname']) ?> (Username: <?= e($t['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small mt-2"><i class="bi bi-info-circle me-1"></i> แสดงเฉพาะบัญชีที่มีสิทธิ์เป็น Teacher ที่ยังไม่ได้มีรายชื่อครูนิเทศก์</div>
                        </div>

                        <hr class="my-4 opacity-50">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fullname" class="form-label fw-bold text-secondary">ชื่อ-สกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="fullname" name="fullname" value="<?= e($fullname) ?>" required placeholder="เช่น นายสมศักดิ์ รักดี">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label fw-bold text-secondary">สังกัด/สาขางาน <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="department" name="department" value="<?= e($department) ?>" required placeholder="เช่น สาขาเทคโนโลยีสารสนเทศ">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label fw-bold text-secondary">อีเมล</label>
                                <input type="email" class="form-control border-0 bg-light shadow-sm rounded-3" id="email" name="email" value="<?= e($email) ?>" placeholder="name@example.com">
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="phone" class="form-label fw-bold text-secondary">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="08x-xxx-xxxx">
                            </div>
                        </div>
                        
                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-staff btn-lg py-3 rounded-3 shadow d-flex align-items-center justify-content-center gap-2 fw-bold">
                                <i class="bi bi-check-circle-fill"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> <!-- container end -->
</div> <!-- staff-dashboard-page end -->
<?php include __DIR__ . '/../includes/footer.php'; ?>