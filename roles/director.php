<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director']);
$u = current_user();

$hide_welcome = true;
include '../includes/header.php';

$count_std  = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
$count_tea  = $conn->query("SELECT COUNT(*) c FROM users WHERE role IN ('teacher','mentor')")->fetch_assoc()['c'];
$count_plan = $conn->query("SELECT COUNT(DISTINCT teacher_id) c FROM plans")->fetch_assoc()['c'];
$count_wait_sign = $conn->query("SELECT COUNT(*) c FROM supervision_files WHERE status = 1")->fetch_assoc()['c'];

$count_pending = $conn->query("
    SELECT COUNT(DISTINCT dr.student_id) AS total
    FROM daily_reports dr
    WHERE dr.status = 'pending'
    AND dr.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

$count_checked = $conn->query("
    SELECT COUNT(*) c FROM supervision_files WHERE status IN (1,2)
")->fetch_assoc()['c'];

$plan_pct = $count_tea > 0 ? round(($count_plan / $count_tea) * 100) : 0;
?>
<style>
:root {
    --dir-primary: #4f46e5;
    --dir-secondary: #7c3aed;
    --dir-accent: #06b6d4;
    --dir-gold: #f59e0b;
    --dir-danger: #ef4444;
    --dir-success: #10b981;
}

.dir-page {
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(160deg, #eef2ff 0%, #f5f3ff 40%, #ecfdf5 100%);
    min-height: 100vh;
    padding-bottom: 4rem;
}

/* ===== HERO ===== */
.dir-hero {
    background: linear-gradient(135deg, #312e81 0%, #4f46e5 35%, #7c3aed 70%, #0ea5e9 100%);
    border-radius: 2rem;
    padding: 2.5rem 3rem;
    color: #fff;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(79,70,229,0.35);
}
.dir-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 280px; height: 280px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
}
.dir-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: 30%;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.dir-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 50px;
    padding: 0.4rem 1rem;
    font-size: 0.82rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
}
.dir-hero h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    letter-spacing: -0.5px;
    color: #fff !important;
}
.dir-hero p { opacity: 0.85; font-size: 0.95rem; margin: 0; color: #fff !important; }
.dir-hero-icon {
    width: 80px; height: 80px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.25);
    border-radius: 1.25rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem;
    flex-shrink: 0;
}

/* ===== ALERT ===== */
.dir-alert {
    background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(239,68,68,0.05));
    border: 1px solid rgba(239,68,68,0.3);
    border-left: 4px solid #ef4444;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    backdrop-filter: blur(8px);
    animation: pulseAlert 2s ease-in-out infinite;
}
@keyframes pulseAlert {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.15); }
    50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

/* ===== SECTION TITLE ===== */
.dir-section-title {
    font-weight: 700;
    color: #312e81;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
    padding-left: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.dir-section-title::before {
    content: '';
    width: 4px; height: 18px;
    background: linear-gradient(180deg, #4f46e5, #7c3aed);
    border-radius: 4px;
}

/* ===== STAT CARDS ===== */
.dir-stat {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.5rem;
    border: 1px solid rgba(99,102,241,0.08);
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    height: 100%;
    position: relative;
    overflow: hidden;
}
.dir-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 40px rgba(79,70,229,0.12);
    border-color: rgba(99,102,241,0.2);
}
.dir-stat::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 1.25rem 1.25rem 0 0;
}
.dir-stat.s-blue::after   { background: linear-gradient(90deg, #4f46e5, #818cf8); }
.dir-stat.s-green::after  { background: linear-gradient(90deg, #10b981, #34d399); }
.dir-stat.s-amber::after  { background: linear-gradient(90deg, #f59e0b, #fcd34d); }
.dir-stat.s-cyan::after   { background: linear-gradient(90deg, #06b6d4, #67e8f9); }

.dir-stat-icon {
    width: 54px; height: 54px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.dir-stat-num {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
    letter-spacing: -1px;
}
.dir-stat-label { font-size: 0.82rem; color: #64748b; margin-top: 0.35rem; font-weight: 500; }

/* Progress bar */
.dir-progress-wrap { margin-top: 1rem; }
.dir-progress-label { font-size: 0.75rem; color: #94a3b8; display: flex; justify-content: space-between; margin-bottom: 4px; }
.dir-progress { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.dir-progress-bar { height: 100%; border-radius: 99px; transition: width 1s ease; }

/* ===== MENU CARDS ===== */
.dir-menu {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.5rem 1rem;
    border: 1px solid rgba(99,102,241,0.08);
    text-decoration: none;
    color: #334155;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    height: 100%;
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
}
.dir-menu::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 1.25rem;
}
.dir-menu:hover { transform: translateY(-4px); box-shadow: 0 20px 45px rgba(79,70,229,0.2); border-color: transparent; color: #fff; }
.dir-menu:hover::before { opacity: 1; }
.dir-menu:hover .dir-menu-icon { background: rgba(255,255,255,0.2) !important; color: #fff !important; }
.dir-menu:hover .dir-menu-desc { color: rgba(255,255,255,0.75); }

.dir-menu > * { position: relative; z-index: 1; }

.dir-menu-icon {
    width: 60px; height: 60px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem;
    margin-bottom: 0.85rem;
    transition: all 0.3s;
}
.dir-menu-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.3rem; line-height: 1.3; }
.dir-menu-desc { font-size: 0.78rem; color: #94a3b8; transition: color 0.3s; }

/* Urgent card style */
.dir-menu.urgent { border-color: rgba(239,68,68,0.2); }
.dir-menu.urgent:hover { box-shadow: 0 20px 45px rgba(239,68,68,0.2); }
.dir-menu.urgent::before { background: linear-gradient(135deg, #dc2626, #ef4444); }

@media (max-width: 768px) {
    .dir-hero { padding: 1.75rem 1.5rem; border-radius: 1.25rem; }
    .dir-hero h1 { font-size: 1.5rem; }
    .dir-stat-num { font-size: 1.6rem; }
    .dir-menu { padding: 1.25rem 0.75rem; }
    .dir-menu-icon { width: 50px; height: 50px; font-size: 1.4rem; }
    .dir-menu-desc { display: none; }
}
</style>

<div class="dir-page">
<div class="container py-4">

    <!-- HERO -->
    <div class="dir-hero d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="dir-hero-badge">
                <i class="bi bi-shield-check-fill"></i> บทบาท: ผู้บริหาร
            </div>
            <h1>สวัสดีคุณ <?= e($u['fullname'] ?? 'ผู้บริหาร') ?></h1>
            <p><i class="bi bi-bar-chart-line me-1"></i>ภาพรวมระบบนิเทศและลงนามเอกสาร</p>
        </div>
        <div class="dir-hero-icon d-none d-md-flex">🏛️</div>
    </div>

    <!-- ALERT -->
    <?php if ($count_pending > 0): ?>
    <div class="dir-alert mb-4">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-exclamation-triangle text-danger fs-5"></i>
            <div>
                <strong class="text-danger">แจ้งเตือน!</strong>
                <span class="ms-1 text-danger-emphasis">มีนักเรียน <strong><?= $count_pending ?></strong> ราย ที่ครูนิเทศก์ยังไม่ตรวจรายงานเกิน 7 วัน</span>
            </div>
        </div>
        <a href="../director/view_late_reports.php" class="btn btn-danger btn-sm px-3 fw-bold flex-shrink-0" style="border-radius:8px;">
            <i class="bi bi-arrow-right-circle me-1"></i>ตรวจสอบ
        </a>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <p class="dir-section-title"><i class="bi bi-bar-chart-fill"></i>สรุปภาพรวม</p>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dir-stat s-blue">
                <div class="dir-stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="dir-stat-num"><?= number_format($count_std) ?></div>
                <div class="dir-stat-label">นักเรียนทั้งหมด</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dir-stat s-green">
                <div class="dir-stat-icon" style="background:rgba(16,185,129,0.1);color:#10b981;">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="dir-stat-num"><?= number_format($count_tea) ?></div>
                <div class="dir-stat-label">ครูนิเทศก์</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dir-stat s-amber">
                <div class="dir-stat-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="dir-stat-num"><?= $count_plan ?><small class="text-muted fw-normal" style="font-size:1rem;">/<?= $count_tea ?></small></div>
                <div class="dir-stat-label">ส่งแผนนิเทศแล้ว</div>
                <div class="dir-progress-wrap">
                    <div class="dir-progress-label"><span>ความคืบหน้า</span><span><?= $plan_pct ?>%</span></div>
                    <div class="dir-progress">
                        <div class="dir-progress-bar" style="width:<?= $plan_pct ?>%;background:linear-gradient(90deg,#f59e0b,#fcd34d);"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dir-stat s-cyan">
                <div class="dir-stat-icon" style="background:rgba(6,182,212,0.1);color:#06b6d4;">
                    <i class="bi bi-clipboard-check-fill"></i>
                </div>
                <div class="dir-stat-num"><?= number_format($count_checked) ?></div>
                <div class="dir-stat-label">ตรวจรายงานแล้ว (ฉบับ)</div>
            </div>
        </div>
    </div>

    <!-- MENU -->
    <p class="dir-section-title"><i class="bi bi-grid-3x3-gap-fill"></i>เมนูจัดการ</p>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <a href="../includes/send_announcement.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="bi bi-megaphone"></i></div>
                <span class="dir-menu-title">ส่งประกาศแจ้งเตือน</span>
                <span class="dir-menu-desc">ส่งข่าวประกาศถึงผู้ใช้ในระบบ</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_students.php" class="dir-menu">
                <div class="dir-menu-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <span class="dir-menu-title">นักเรียน</span>
                <span class="dir-menu-desc">ดูรายชื่อและสถานะ</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_teachers.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-person-badge"></i></div>
                <span class="dir-menu-title">ครูนิเทศก์</span>
                <span class="dir-menu-desc">รายชื่อครูนิเทศก์</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../companies/list.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(37,99,235,0.1);color:#2563eb;"><i class="bi bi-building"></i></div>
                <span class="dir-menu-title">สถานประกอบการ</span>
                <span class="dir-menu-desc">รายชื่อและรายงานประจำบริษัท</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_supervision.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(147,51,234,0.1);color:#9333ea;"><i class="bi bi-file-earmark-bar-graph"></i></div>
                <span class="dir-menu-title">รายงานการนิเทศก์</span>
                <span class="dir-menu-desc">ติดตามรายงานการนิเทศก์ทั้งหมด</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_plans.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="bi bi-file-earmark-text"></i></div>
                <span class="dir-menu-title">แผนงานนิเทศ</span>
                <span class="dir-menu-desc">ดูแผนการนิเทศ</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_daily.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(6,182,212,0.1);color:#06b6d4;"><i class="bi bi-calendar-check"></i></div>
                <span class="dir-menu-title">บันทึกรายวัน</span>
                <span class="dir-menu-desc">บันทึกการฝึกงานประจำวัน</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_late_reports.php" class="dir-menu urgent">
                <div class="dir-menu-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="bi bi-clock-history"></i></div>
                <span class="dir-menu-title">รายงานค้างตรวจ</span>
                <span class="dir-menu-desc">รายงานที่ครูยังไม่ตรวจเกิน 7 วัน</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_wait_sign.php" class="dir-menu urgent">
                <div class="dir-menu-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="bi bi-pencil-square"></i></div>
                <span class="dir-menu-title">
                    ใบนิเทศรอลงนาม
                    <?php if ($count_wait_sign > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?= $count_wait_sign ?></span>
                    <?php endif; ?>
                </span>
                <span class="dir-menu-desc">ลงนามเอกสารนิเทศ</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/sign.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(100,116,139,0.1);color:#475569;"><i class="bi bi-folder-check"></i></div>
                <span class="dir-menu-title">ใบนิเทศลงนามแล้ว</span>
                <span class="dir-menu-desc">เอกสารที่ลงนามแล้ว</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/view_certs.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(79,70,229,0.1);color:#4f46e5;"><i class="bi bi-award"></i></div>
                <span class="dir-menu-title">เกียรติบัตร</span>
                <span class="dir-menu-desc">จัดการเกียรติบัตร</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../director/profile.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(124,58,237,0.1);color:#7c3aed;"><i class="bi bi-person-gear"></i></div>
                <span class="dir-menu-title">จัดการโปรไฟล์</span>
                <span class="dir-menu-desc">ข้อมูลส่วนตัวและรหัสผ่าน</span>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="../contact.php" class="dir-menu">
                <div class="dir-menu-icon" style="background:rgba(32, 201, 151, 0.1);color:#20c997;"><i class="bi bi-headset"></i></div>
                <span class="dir-menu-title">ติดต่อสอบถาม</span>
                <span class="dir-menu-desc">แจ้งปัญหาหรือสอบถามข้อมูล</span>
            </a>
        </div>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
