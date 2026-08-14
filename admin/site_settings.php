<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $keys = [
        'footer_fb_url', 'footer_line_url', 'footer_web_url', 
        'footer_college_name_1', 'footer_college_name_2', 'footer_slogan',
        'cert_director_name', 'cert_college_name', 'academic_deputy_name'
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            update_setting($key, $_POST[$key]);
        }
    }
    log_audit('UPDATE_FOOTER_SETTINGS', 'ปรับปรุงการตั้งค่าข้อมูลลิงก์ ข้อความใน Footer และข้อมูลผู้ลงนาม');
    $success = true;
}

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
            text: 'อัปเดตข้อมูลการตั้งค่าเรียบร้อยแล้ว',
            confirmButtonText: 'รับทราบ',
            confirmButtonColor: '#4f46e5'
        });
    </script>
    <?php endif; ?>

    <div class="admin-header-section mb-4">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-cog"></i>
                ตั้งค่าการแสดงผล (Footer & Links)
            </h2>
            <p class="text-muted small mb-0 mt-1">จัดการลิงก์โซเชียลมีเดีย ชื่อสถานศึกษา และข้อความส่วนท้ายของเว็บไซต์</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> กลับหน้าแรก
            </a>
        </div>
    </div>

    <form method="POST" class="pb-5">
        <div class="row g-4">
            <!-- Social Links Group -->
            <div class="col-lg-6">
                <div class="setting-card shadow-sm">
                    <div class="setting-icon-ring bg-gradient bg-info">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">ช่องทางการติดต่อ (Social Links)</h4>
                    <p class="text-muted small border-bottom pb-3">กำหนด URL สำหรับปุ่มโซเชียลใน Footer</p>

                    <div class="mt-3">
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2"><i class="fab fa-facebook text-primary me-1"></i> Facebook URL</label>
                            <input type="text" name="footer_fb_url" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('footer_fb_url', '#')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2"><i class="fab fa-line text-success me-1"></i> Line Account URL</label>
                            <input type="text" name="footer_line_url" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('footer_line_url', '#')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2"><i class="fas fa-globe text-info me-1"></i> เว็บไซต์หลัก/ห้องสมุด URL</label>
                            <input type="text" name="footer_web_url" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('footer_web_url', '#')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Info Group -->
            <div class="col-lg-6">
                <div class="setting-card shadow-sm">
                    <div class="setting-icon-ring bg-gradient bg-primary">
                        <i class="fas fa-font"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">ข้อมูลการแสดงผล (Footer Content)</h4>
                    <p class="text-muted small border-bottom pb-3">ตั้งค่าข้อความชื่อสถาบันและสโลแกน</p>

                    <div class="mt-3">
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2">ชื่อหน่วยงาน / ชื่อส่วนงาน (บรรทัดที่ 1)</label>
                            <input type="text" name="footer_college_name_1" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('footer_college_name_1', 'งานอาชีวศึกษาระบบทวิภาคี')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2">ชื่อสถานศึกษา (บรรทัดที่ 2)</label>
                            <input type="text" name="footer_college_name_2" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('footer_college_name_2', 'วิทยาลัยอาชีวศึกษาเพชรบุรี')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label fw-bold mb-2">สโลแกน / ข้อความกำกับ</label>
                            <textarea name="footer_slogan" rows="3" class="form-control admin-form-control shadow-none"><?php echo htmlspecialchars(get_setting('footer_slogan', "มุ่งเน้นความเป็นเลิศทางการศึกษาและทักษะวิชาชีพ <br>\nเพื่อพัฒนากำลังคนอาชีวศึกษาสู่ตลาดแรงงาน")); ?></textarea>
                            <small class="text-muted">รองรับ HTML tag เบื้องต้น เช่น &lt;br&gt; สำหรับขึ้นบรรทัดใหม่</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificate Signatory Group -->
            <div class="col-lg-12">
                <div class="setting-card shadow-sm">
                    <div class="setting-icon-ring bg-gradient bg-warning">
                        <i class="fas fa-award"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">ตั้งค่าผู้ลงนามเกียรติบัตร (Certificate Settings)</h4>
                    <p class="text-muted small border-bottom pb-3">กำหนดชื่อผู้ลงนามและหน่วยงานที่จะแสดงในเกียรติบัตร</p>

                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label class="admin-form-label fw-bold mb-2">ชื่อผู้อำนวยการวิทยาลัย (ในเกียรติบัตร)</label>
                            <input type="text" name="cert_director_name" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('cert_director_name', 'นางวรากร หิรัญมณีมาศ')); ?>">
                            <small class="text-muted">ชื่อที่จะแสดงภายใต้ลายเซ็นผู้อำนวยการ</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="admin-form-label fw-bold mb-2">ชื่อวิทยาลัย (ในเกียรติบัตร)</label>
                            <input type="text" name="cert_college_name" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('cert_college_name', 'วิทยาลัยอาชีวศึกษาเพชรบุรี')); ?>">
                            <small class="text-muted">ชื่อหน่วยงานที่จะแสดงใต้ตำแหน่งผู้อำนวยการ</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Report Signatory Group -->
            <div class="col-lg-12">
                <div class="setting-card shadow-sm">
                    <div class="setting-icon-ring bg-gradient bg-danger">
                        <i class="fas fa-print"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">ตั้งค่าผู้ลงนามใบรายงานผล (Print Report Settings)</h4>
                    <p class="text-muted small border-bottom pb-3">กำหนดชื่อผู้ลงนามสำหรับเอกสารรายงานผลการฝึกงานของนักศึกษา</p>

                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label class="admin-form-label fw-bold mb-2">ชื่อรองผู้อำนวยการฝ่ายวิชาการ</label>
                            <input type="text" name="academic_deputy_name" 
                                   class="form-control admin-form-control shadow-none" 
                                   value="<?php echo e(get_setting('academic_deputy_name', '...................................................')); ?>">
                            <small class="text-muted">ชื่อที่จะแสดงในช่องผู้ลงนามรองผู้อำนวยการฝ่ายวิชาการบนหน้าปรินต์</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-table-container mt-4 p-4 bg-white text-end border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small d-none d-md-inline"><i class="fas fa-info-circle me-2"></i>ข้อมูลจะแสดงผลที่แถบด้านล่างสุดของทุกหน้าจอ</span>
                <div class="d-flex gap-2 w-100 w-md-auto">
                    <a href="../roles/admin.php" class="btn-admin-outline w-50 w-md-auto text-center text-decoration-none">ยกเลิก</a>
                    <button type="submit" class="btn-admin-primary w-50 w-md-auto px-5">
                        <i class="fas fa-save me-2"></i>บันทึกการตั้งค่า
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
