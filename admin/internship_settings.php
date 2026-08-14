<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST['dates'] as $level_id => $dates) {
        $start = $dates['start'];
        $end = $dates['end'];
        $stmt = $conn->prepare("UPDATE internship_settings SET start_date = ?, end_date = ? WHERE id = ?");
        $stmt->bind_param("ssi", $start, $end, $level_id);
        $stmt->execute();
    }
    log_audit('UPDATE_INTERNSHIP_DATES', 'ปรับปรุงการตั้งค่าช่วงเวลาการฝึกงานระบบ');
    $success = true;
}

$settings = $conn->query("SELECT * FROM internship_settings ORDER BY id ASC");
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.setting-card {
    background: #fff;
    border-radius: 1rem;
    border: 1px solid #f1f5f9;
    padding: 1.75rem;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
}
.setting-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--admin-card-shadow);
}
.setting-icon-ring {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    background: var(--admin-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
}
</style>

<div class="container admin-content-wrapper pb-5">
    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ',
            text: 'อัปเดตกำหนดการและช่วงเวลาฝึกประสบการณ์เรียบร้อยแล้ว',
            confirmButtonText: 'รับทราบ',
            confirmButtonColor: '#4f46e5'
        });
    </script>
    <?php endif; ?>

    <div class="admin-header-section mb-4">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-calendar-alt"></i>
                ตั้งค่าช่วงเวลาฝึกงานและฝึกอาชีพ
            </h2>
            <p class="text-muted small mb-0 mt-1">จัดการกรอบเวลาเริ่มต้นและสิ้นสุดการฝึกประสบการณ์ตามหลักสูตร</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> กลับหน้าแรก
            </a>
        </div>
    </div>

    <form method="POST" class="pb-5">
        <div class="row g-4">
            <?php 
            $icons = ['fa-school', 'fa-graduation-cap', 'fa-briefcase'];
            $idx = 0;
            while($row = $settings->fetch_assoc()): 
                $currIcon = isset($icons[$idx]) ? $icons[$idx] : 'fa-calendar-check';
                $idx++;
            ?>
            <div class="col-md-6">
                <div class="setting-card shadow-sm">
                    <div class="setting-icon-ring bg-gradient">
                        <i class="fas <?php echo $currIcon; ?>"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($row['level_name']); ?></h4>
                    <p class="text-muted small border-bottom pb-3">กำหนดวันและเวลาเริ่มต้น - สิ้นสุด</p>

                    <div class="row g-3 mt-2">
                        <div class="col-sm-6">
                            <label class="admin-form-label fw-bold mb-2"><i class="fas fa-play-circle text-success me-1 small"></i> วันเริ่มต้น</label>
                            <input type="date" name="dates[<?php echo $row['id']; ?>][start]" 
                                   class="form-control admin-form-control shadow-none" value="<?php echo $row['start_date']; ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="admin-form-label fw-bold mb-2"><i class="fas fa-stop-circle text-danger me-1 small"></i> วันสิ้นสุด</label>
                            <input type="date" name="dates[<?php echo $row['id']; ?>][end]" 
                                   class="form-control admin-form-control shadow-none" value="<?php echo $row['end_date']; ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="admin-table-container mt-4 p-4 bg-white text-end border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small d-none d-md-inline"><i class="fas fa-info-circle me-2"></i>ตรวจสอบวันที่ให้ถูกต้องก่อนกดยืนยันบันทึกข้อมูล</span>
                <div class="d-flex gap-2 w-100 w-md-auto">
                    <a href="../roles/admin.php" class="btn-admin-outline w-50 w-md-auto text-center text-decoration-none">ยกเลิก</a>
                    <button type="submit" class="btn-admin-primary w-50 w-md-auto px-5">
                        <i class="fas fa-save me-2"></i>บันทึกข้อมูลทั้งหมด
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
