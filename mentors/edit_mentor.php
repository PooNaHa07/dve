<?php
// mentors/edit_mentor.php - หน้าแก้ไขข้อมูลครูนิเทศก์
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']); 
include __DIR__ . '/../includes/header.php';

$error = '';
$success = '';
$mentor = null; 

// 1. รับค่า $mentor_id จาก URL (GET)
$mentor_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($mentor_id) {
    // Load Mentor Data INCLUDING user_id
    $stmt = $conn->prepare("SELECT id, user_id, fullname, department, email, phone FROM mentors WHERE id = ?");
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $mentor = $result->fetch_assoc();
    } else {
        $error = "ไม่พบข้อมูลครูนิเทศก์ที่ระบุ";
    }
    $stmt->close();
} else {
    $error = "ไม่พบ ID ครูนิเทศก์";
}

// Query potential users including currently assigned user
$potentialTeachers = [];
if ($mentor) {
    // Select users who either have NO mentor profile OR is current user assigned to this mentor
    $current_assigned = (int)$mentor['user_id'];
    
    $sqlU = "SELECT u.id, u.username, u.fullname FROM users u 
             LEFT JOIN mentors m ON u.id = m.user_id 
             WHERE u.role = 'teacher' AND (m.id IS NULL OR u.id = $current_assigned) 
             ORDER BY u.fullname ASC";
             
    $qUsers = $conn->query($sqlU);
    if ($qUsers) {
        while($r = $qUsers->fetch_assoc()) {
            $potentialTeachers[] = $r;
        }
    }
}

// 2. Process POST Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mentor) {
    $fullname = trim($_POST['fullname'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if (empty($fullname) || empty($department)) {
        $error = "กรุณากรอกชื่อ-สกุล และสังกัดให้ครบถ้วน";
    } else {
        // UPDATE WITH user_id
        $stmt_update = $conn->prepare(
            "UPDATE mentors SET fullname = ?, department = ?, email = ?, phone = ?, user_id = ? WHERE id = ?"
        );
        $stmt_update->bind_param("ssssii", $fullname, $department, $email, $phone, $new_user_id, $mentor_id);
        
        if ($stmt_update->execute()) {
            $success = "แก้ไขข้อมูลครูนิเทศก์สำเร็จแล้ว!";
            // Refetch/Update local array for the view
            $mentor['fullname'] = $fullname;
            $mentor['department'] = $department;
            $mentor['email'] = $email;
            $mentor['phone'] = $phone;
            $mentor['user_id'] = $new_user_id;
        } else {
            if ($conn->errno == 1062) {
                $error = "ข้อมูลซ้ำ (มีผู้ใช้ท่านอื่นถูกผูกกับบัญชีนี้แล้ว)";
            } else {
                $error = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
        $stmt_update->close();
    }
}
?>
<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page pt-4">
    <div class="container">
        <!-- Page Header Container -->
        <div class="staff-page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขครูนิเทศก์</h4>
                <p class="text-muted mb-0 small"><?= e($mentor['fullname'] ?? 'โหลดข้อมูลไม่สำเร็จ') ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="list.php" class="btn btn-outline-secondary border-opacity-25 bg-white rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> กลับ
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success shadow-sm border-0 rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?></div>
        <?php endif; ?>

    <?php if ($mentor): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card border-0 shadow p-4 p-md-5" style="border-radius: 20px;">
                <h5 class="mb-4 fw-bold text-indigo-700 border-bottom pb-3">
                    <i class="bi bi-pencil-square me-2"></i>แก้ไขรายละเอียดโปรไฟล์
                </h5>
                <form method="POST">
                    <!-- Link to User Account Dropdown -->
                    <div class="mb-4">
                        <label for="user_id" class="form-label fw-bold text-secondary"><i class="bi bi-person-badge me-1"></i>ผูกกับบัญชีผู้ใช้งาน (ถ้ามี)</label>
                        <select class="form-select form-select-lg border-0 shadow-sm rounded-3 bg-light" id="user_id" name="user_id" style="font-size: 0.95rem;">
                            <option value="0">-- ไม่ผูกบัญชี --</option>
                            <?php foreach($potentialTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (int)$mentor['user_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['fullname']) ?> (Username: <?= e($t['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-4 opacity-50">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fullname" class="form-label fw-bold text-secondary">ชื่อ-สกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="fullname" name="fullname" value="<?= e($mentor['fullname']) ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label fw-bold text-secondary">สังกัด/สาขางาน <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="department" name="department" value="<?= e($mentor['department']) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-bold text-secondary">อีเมล</label>
                            <input type="email" class="form-control border-0 bg-light shadow-sm rounded-3" id="email" name="email" value="<?= e($mentor['email']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="phone" class="form-label fw-bold text-secondary">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control border-0 bg-light shadow-sm rounded-3" id="phone" name="phone" value="<?= e($mentor['phone']) ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-staff btn-lg py-3 rounded-3 shadow d-flex align-items-center justify-content-center gap-2 fw-bold">
                            <i class="bi bi-save-fill"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div> <!-- Close Container -->
</div> <!-- Close staff-dashboard-page -->

<?php include __DIR__ . '/../includes/footer.php'; ?>