<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['staff']);

$u = current_user();
$hide_welcome = true; // Hide original welcome banner from header
include __DIR__ . '/../includes/header.php';

$totalNews = safe_count('news');
$totalMentors = safe_count('mentors');
$totalCompanies = safe_count('companies');
$totalCalendar = safe_count('calendar_events');
$totalDocuments = safe_count('documents');
$totalPending = safe_count('daily_reports', "status = 'pending'");
$totalCertificates = safe_count('evaluations', "grade IS NOT NULL AND grade != ''");
$totalSupervisionPending = safe_count('supervision_files', "status = '0'");
$totalSubmittedToDirector = safe_count('supervision_files', "status = '1'");
$totalStaffGradePending = 0;
if (isset($conn) && $conn) {
    try {
        $chk = $conn->query("SHOW TABLES LIKE 'staff_evaluations'");
        if ($chk && $chk->num_rows > 0) {
            $r = $conn->query("SELECT COUNT(*) AS c FROM users u LEFT JOIN staff_evaluations s ON u.id = s.student_id WHERE u.role = 'student' AND (s.id IS NULL OR s.grade IS NULL OR s.grade = '')");
            if ($r && $row = $r->fetch_assoc()) {
                $totalStaffGradePending = (int) $row['c'];
            }
        }
    } catch (Exception $e) {
        error_log("Staff dashboard warning: " . $e->getMessage());
    }
}

?>
<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page">
    <div class="container py-4">
        <!-- Hero Area -->
        <div class="staff-hero-card d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="fw-extrabold mb-2 text-white" style="letter-spacing: -1px;">สวัสดีครับคุณ <?= htmlspecialchars($u['fullname'] ?? 'เจ้าหน้าที่') ?></h1>
                <p class="mb-0" style="color: rgba(255,255,255,0.9);">ระบบจัดการฝึกงานส่วนกลาง | ยินดีต้อนรับเข้าสู่แผงควบคุมหลัก</p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-white text-indigo-600 px-4 py-2 rounded-pill fw-bold shadow-sm" style="color: #4f46e5 !important;">
                    <i class="bi bi-shield-lock-fill me-2" style="color: #4f46e5 !important;"></i>เจ้าหน้าที่ระบบ
                </span>
            </div>
        </div>

        <!-- Stats Section -->
        <p class="staff-section-title"><i class="bi bi-bar-chart-fill" style="color: #6366f1;"></i> สรุปข้อมูล</p>
        <div class="row g-3 mb-4">
            <!-- Card 1: ข่าวประชาสัมพันธ์ -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;"><i class="bi bi-newspaper"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalNews ?></div>
                        <div class="staff-stat-lbl">ข่าวประชาสัมพันธ์</div>
                    </div>
                </div>
            </div>
            <!-- Card 2: ครูนิเทศก์ -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalMentors ?></div>
                        <div class="staff-stat-lbl">ครูนิเทศก์</div>
                    </div>
                </div>
            </div>
            <!-- Card 3: สถานประกอบการ -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalCompanies ?></div>
                        <div class="staff-stat-lbl">สถานประกอบการ</div>
                    </div>
                </div>
            </div>
            <!-- Card 4: ปฏิทินกิจกรรม -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalCalendar ?></div>
                        <div class="staff-stat-lbl">ปฏิทินกิจกรรม</div>
                    </div>
                </div>
            </div>
            
            <!-- Card 5: เอกสาร -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;"><i class="bi bi-file-earmark-text"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalDocuments ?></div>
                        <div class="staff-stat-lbl">เอกสาร</div>
                    </div>
                </div>
            </div>
            <!-- Card 6: รายงานรอตรวจสอบ -->
            <div class="col-md-6 col-xl-3">
                <a href="../reports/staff_report_view.php" class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e;"><i class="bi bi-check2-square"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalPending ?></div>
                        <div class="staff-stat-lbl">รายงานรอตรวจสอบ</div>
                    </div>
                </a>
            </div>
            <!-- Card 7: เกียรติบัตรที่ออกแล้ว -->
            <div class="col-md-6 col-xl-3">
                <div class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(75, 85, 99, 0.1); color: #4b5563;"><i class="bi bi-award"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalCertificates ?></div>
                        <div class="staff-stat-lbl">เกียรติบัตรที่ออกแล้ว</div>
                    </div>
                </div>
            </div>
            <!-- Card 8: ใบนิเทศรอตรวจ -->
            <div class="col-md-6 col-xl-3">
                <a href="../staff/manage_supervision.php" class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(249, 115, 22, 0.1); color: #f97316;"><i class="bi bi-clipboard-check"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalSupervisionPending ?></div>
                        <div class="staff-stat-lbl">ใบนิเทศรอตรวจ</div>
                    </div>
                </a>
            </div>
            
            <!-- Card 9: นักเรียนรอการประเมินเกรด (เจ้าหน้าที่) -->
            <div class="col-md-6 col-xl-3">
                <a href="../staff/assessment_list.php" class="staff-stat-box">
                    <div class="staff-stat-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;"><i class="bi bi-bar-chart"></i></div>
                    <div>
                        <div class="staff-stat-num"><?= $totalStaffGradePending ?></div>
                        <div class="staff-stat-lbl">นักเรียนรอการประเมินเกรด (เจ้าหน้าที่)</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Management Grids -->
        <p class="staff-section-title"><i class="bi bi-window-dock"></i> ระบบจัดการสารสนเทศ</p>
        <div class="row g-4">
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../includes/send_announcement.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="bi bi-megaphone"></i></div>
                    <div class="staff-menu-title">ส่งประกาศแจ้งเตือน</div>
                    <div class="staff-menu-desc">ส่งข่าวประกาศถึงผู้ใช้ในระบบ</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/news.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;"><i class="bi bi-newspaper"></i></div>
                    <div class="staff-menu-title">จัดการข่าวประกาศ</div>
                    <div class="staff-menu-desc">เพิ่ม/แก้ไข ข่าวประชาสัมพันธ์</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../mentors/list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;"><i class="bi bi-person-plus"></i></div>
                    <div class="staff-menu-title">จัดการครูนิเทศก์</div>
                    <div class="staff-menu-desc">เพิ่ม/แก้ไข/ลบ ครูนิเทศก์</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../companies/list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;"><i class="bi bi-building"></i></div>
                    <div class="staff-menu-title">จัดการสถานประกอบการ</div>
                    <div class="staff-menu-desc">เพิ่ม/แก้ไข สถานประกอบการ</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/manage_classrooms.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;"><i class="bi bi-door-open"></i></div>
                    <div class="staff-menu-title">จัดการห้องเรียน</div>
                    <div class="staff-menu-desc">ตั้งกลุ่มการเรียนและห้องเรียน</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../calendar/list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="bi bi-calendar-event"></i></div>
                    <div class="staff-menu-title">ปฏิทินกิจกรรม</div>
                    <div class="staff-menu-desc">ดูและจัดการกิจกรรม</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../documents_list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;"><i class="bi bi-file-earmark-arrow-down"></i></div>
                    <div class="staff-menu-title">เอกสารดาวน์โหลด</div>
                    <div class="staff-menu-desc">อัปโหลด/ดาวน์โหลด เอกสาร</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../reports/staff_report_view.php" class="staff-menu-item position-relative">
                    <div class="staff-menu-icon" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e;"><i class="bi bi-check2-square"></i></div>
                    <div class="staff-menu-title">ตรวจสอบรายงาน</div>
                    <div class="staff-menu-desc">อนุมัติ/ส่งกลับ รายงานนักเรียน</div>
                    <?php if($totalPending > 0): ?>
                    <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger" style="margin-top: 15px; margin-right: 15px;">
                        <?= $totalPending ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../certificates/certificates_list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(75, 85, 99, 0.1); color: #4b5563;"><i class="bi bi-award"></i></div>
                    <div class="staff-menu-title">ออกเกียรติบัตร</div>
                    <div class="staff-menu-desc">จัดการเกียรติบัตรนักเรียน</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/assessment_list.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;"><i class="bi bi-bar-chart"></i></div>
                    <div class="staff-menu-title">ประเมินเกรดนักเรียน</div>
                    <div class="staff-menu-desc">ลงคะแนน/ตัดเกรด (ฝั่งเจ้าหน้าที่)</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/manage_supervision.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(249, 115, 22, 0.1); color: #f97316;"><i class="bi bi-clipboard-check"></i></div>
                    <div class="staff-menu-title">จัดการใบนิเทศ</div>
                    <div class="staff-menu-desc">ตรวจสอบและเสนอผู้บริหาร</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/upload_plan_for_teacher.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div class="staff-menu-title">อัปโหลดงานแทนครู</div>
                    <div class="staff-menu-desc">อัปโหลดแผนนิเทศในนามครูนิเทศก์</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/manage_supervisors.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;"><i class="bi bi-person-workspace"></i></div>
                    <div class="staff-menu-title">จัดการ Supervisor</div>
                    <div class="staff-menu-desc">มอบหมายผู้ดูแลการฝึกงาน</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../staff/profile.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;"><i class="bi bi-person-gear"></i></div>
                    <div class="staff-menu-title">จัดการโปรไฟล์</div>
                    <div class="staff-menu-desc">แก้ไขข้อมูลส่วนตัว/รูปภาพ</div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="../contact.php" class="staff-menu-item">
                    <div class="staff-menu-icon" style="background: rgba(32, 201, 151, 0.1); color: #20c997;"><i class="bi bi-headset"></i></div>
                    <div class="staff-menu-title">ติดต่อสอบถาม</div>
                    <div class="staff-menu-desc">แจ้งปัญหาหรือสอบถามข้อมูล</div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

