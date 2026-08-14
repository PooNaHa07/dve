<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id']; 

date_default_timezone_set('Asia/Bangkok');

// --- 1. ดึงรายการห้องเรียนที่ครูคนนี้รับผิดชอบ ---
$room_ids = array();
$sql_my_rooms = "SELECT classroom_id FROM teacher_assignments WHERE teacher_id = '$teacher_id'";
$res_my_rooms = $conn->query($sql_my_rooms);
if ($res_my_rooms) {
    while($r = $res_my_rooms->fetch_assoc()) {
        $room_ids[] = $r['classroom_id'];
    }
}

if (!empty($room_ids)) {
    $ids_string = implode(',', $room_ids);
    $filter_where = " AND u.classroom_id IN ($ids_string)";
} else {
    $filter_where = " AND 1 = 0"; 
}

// --- 2. ดึงข้อมูลตัวเลขสถิติ (แก้ไขใหม่: สั่ง Query และ Fetch) ---

// นับบันทึกรายวันที่รอการตรวจสอบ
$sql_pending = "SELECT COUNT(*) as total FROM daily_reports dr
                JOIN users u ON dr.student_id = u.id 
                WHERE dr.status = 'pending' $filter_where";
$res_pending = $conn->query($sql_pending);
$row_pending = ($res_pending) ? $res_pending->fetch_assoc() : null;
$pending_count = ($row_pending) ? $row_pending['total'] : 0;

// นับบันทึกรายวันที่ส่งกลับให้แก้ไข
$sql_rejected = "SELECT COUNT(*) as total FROM daily_reports dr
                 JOIN users u ON dr.student_id = u.id 
                 WHERE dr.status = 'rejected' $filter_where";
$res_rejected = $conn->query($sql_rejected);
$row_rejected = ($res_rejected) ? $res_rejected->fetch_assoc() : null;
$rejected_count = ($row_rejected) ? $row_rejected['total'] : 0;

// นับนักเรียนที่ยังไม่ได้ประเมินเกรด
$sql_eval = "SELECT COUNT(*) as total FROM users u 
             LEFT JOIN evaluations e ON u.id = e.student_id 
             WHERE u.role = 'student' AND (e.grade IS NULL OR e.grade = '') $filter_where";
$res_eval = $conn->query($sql_eval);
$row_eval = ($res_eval) ? $res_eval->fetch_assoc() : null;
$waiting_eval = ($row_eval) ? $row_eval['total'] : 0;

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<style>
    /* Teacher Dashboard Hyper-Modern Refinements */
    .dashboard-page {
        background: radial-gradient(circle at 0% 0%, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%);
        min-height: 100vh;
        padding-bottom: 4rem;
    }

    .dashboard-hero-wrapper {
        background: var(--tch-gradient);
        border-radius: 24px;
        padding: 2.5rem;
        color: #fff;
        margin-bottom: 2.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(67, 97, 238, 0.35);
        animation: slideDownFade 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .dashboard-hero-wrapper::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }

    @keyframes slideDownFade {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .hero-title {
        font-weight: 800;
        letter-spacing: -1px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .dashboard-section-lbl {
        font-weight: 700;
        font-size: 1.1rem;
        color: #1e293b;
        margin-bottom: 1.25rem;
        margin-top: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.2px;
    }

    .dashboard-section-lbl i {
        color: var(--tch-primary);
        font-size: 1.25rem;
    }

    /* Modern Stat Cards */
    .tch-stat-box {
        background: #fff;
        border-radius: 18px;
        padding: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.7);
        box-shadow: 0 4px 15px -3px rgba(0,0,0,0.02);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        align-items: center;
        gap: 1.25rem;
        text-decoration: none;
        height: 100%;
    }

    .tch-stat-box:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: var(--shadow-lg);
        border-color: var(--tch-primary);
    }

    .stat-circle-icon {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }

    .stat-big-num {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        color: #1e293b;
        letter-spacing: -1px;
    }

    .stat-text-label {
        font-size: 0.88rem;
        color: #64748b;
        font-weight: 500;
        margin-top: 4px;
    }

    /* Animated Grid Menus */
    .tch-menu-tile {
        background: #ffffff;
        border-radius: 20px;
        padding: 1.5rem 1rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        text-decoration: none;
        color: #334155;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }

    .tch-menu-tile:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px -10px rgba(67, 97, 238, 0.18);
        border-color: var(--tch-primary);
        background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
    }

    .tch-menu-tile .menu-icn-wrap {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 1rem;
        transition: transform 0.3s ease;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.02);
    }

    .tch-menu-tile:hover .menu-icn-wrap {
        transform: scale(1.1) rotate(-3deg);
    }

    .tch-menu-tile .m-title {
        font-weight: 600;
        font-size: 1.05rem;
        color: #1e293b;
        margin-bottom: 0.4rem;
        line-height: 1.3;
    }

    .tch-menu-tile .m-sub {
        font-size: 0.78rem;
        color: #94a3b8;
        line-height: 1.4;
        padding: 0 10px;
    }

    /* Customizations for datatables inside card */
    .dashboard-students-card .card-header {
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
        padding: 1.25rem 1.5rem;
    }
</style>

<div class="dashboard-page">
    <div class="container py-4">
        <div class="dashboard-hero-wrapper d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="mb-1 text-white hero-title">ยินดีต้อนรับคุณ <?php echo e($u['fullname']); ?></h1>
                <p class="mb-0" style="color: rgba(255,255,255,0.9);">
                    <?php if(empty($room_ids)): ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle-fill me-1"></i> กรุณาเลือกห้องเรียนที่รับผิดชอบ</span>
                    <?php else: ?>
                        <i class="bi bi-shield-check me-1"></i> กำลังติดตามข้อมูล <strong><?php echo count($room_ids); ?></strong> ห้องเรียน
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-white text-primary px-4 py-2 rounded-pill fw-bold shadow-sm" style="font-size: 0.85rem;">
                    <i class="bi bi-person-badge-fill me-1"></i> ครูนิเทศก์
                </span>
            </div>
        </div>

        <p class="dashboard-section-lbl"><i class="bi bi-activity"></i> ความเคลื่อนไหว & สรุปข้อมูล</p>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-4">
                <a href="../teacher/approve_reports.php" class="tch-stat-box">
                    <div class="stat-circle-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-journal-check"></i></div>
                    <div>
                        <div class="stat-big-num text-danger"><?php echo (int)$pending_count; ?></div>
                        <div class="stat-text-label">บันทึกรายวันรออนุมัติ</div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="../teacher/approve_reports.php?status_filter=rejected&date=all" class="tch-stat-box">
                    <div class="stat-circle-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div>
                        <div class="stat-big-num text-danger"><?php echo (int)$rejected_count; ?></div>
                        <div class="stat-text-label">รอนักเรียนส่งใหม่ (ตีกลับ)</div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="../teacher/assessment_list.php" class="tch-stat-box">
                    <div class="stat-circle-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <div class="stat-big-num text-success"><?php echo (int)$waiting_eval; ?></div>
                        <div class="stat-text-label">นักเรียนรอประเมินเกรด</div>
                    </div>
                </a>
            </div>
        </div>

        <p class="dashboard-section-lbl"><i class="bi bi-grid-1x2-fill"></i> เมนูเข้าถึงด่วน</p>
        <div class="row g-3 mb-5">
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/select_classrooms.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-primary bg-opacity-10 text-primary"><i class="bi bi-building"></i></div>
                    <span class="m-title">เลือกห้องที่รับผิดชอบ</span>
                    <span class="m-sub">กำหนดห้องเรียนที่รับผิดชอบ</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/manage_students.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-info bg-opacity-10 text-info"><i class="bi bi-people"></i></div>
                    <span class="m-title">จัดการนักเรียนในการดูแล</span>
                    <span class="m-sub">ดูและจัดการรายชื่อนักเรียน</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/student_info.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-lines-fill"></i></div>
                    <span class="m-title">ข้อมูลนักศึกษา</span>
                    <span class="m-sub">ดูข้อมูลส่วนตัวนักศึกษา</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/approve_reports.php" class="tch-menu-tile position-relative">
                    <div class="menu-icn-wrap bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                    <span class="m-title">ตรวจบันทึกรายวัน</span>
                    <span class="m-sub">อนุมัติ/ส่งกลับ บันทึกรายวัน</span>
                    <?php if($pending_count > 0): ?>
                        <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger border border-light p-2" style="margin-top: 15px; margin-right: 15px;">
                            <?php echo (int)$pending_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/report_history.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-clock-history"></i></div>
                    <span class="m-title">ดูบันทึกทั้งหมด/ประวัติ</span>
                    <span class="m-sub">ประวัติการส่งรายงาน</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/plans.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-event"></i></div>
                    <span class="m-title">จัดการแผนนิเทศ</span>
                    <span class="m-sub">แผนการนิเทศ</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/subject_plans.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-primary bg-opacity-10 text-primary"><i class="bi bi-book"></i></div>
                    <span class="m-title">จัดการแผนฝึกอาชีพ</span>
                    <span class="m-sub">แผนฝึกอาชีพ</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/assessment_list.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-warning bg-opacity-10 text-warning"><i class="bi bi-star"></i></div>
                    <span class="m-title">ประเมินเกรดนักเรียน</span>
                    <span class="m-sub">ให้เกรดนักเรียน</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../mentors/upload_supervision.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-info bg-opacity-10 text-info"><i class="bi bi-clipboard-check"></i></div>
                    <span class="m-title">ใบนิเทศก์</span>
                    <span class="m-sub">อัปโหลด/ลงนาม ใบนิเทศ</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../mentors/supervision_list.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-success bg-opacity-10 text-success"><i class="bi bi-file-earmark-check"></i></div>
                    <span class="m-title">ใบนิเทศที่ลงนามแล้ว</span>
                    <span class="m-sub">เอกสารที่ลงนามแล้ว</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../mentors/schedule.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-dark bg-opacity-10 text-dark"><i class="bi bi-calendar-week"></i></div>
                    <span class="m-title">ตารางสอนออนไลน์/ออกนิเทศ</span>
                    <span class="m-sub">ลงวันสอนออนไลน์-วันออกนิเทศ (นักเรียนดูได้)</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../teacher/profile.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-gear"></i></div>
                    <span class="m-title">จัดการโปรไฟล์</span>
                    <span class="m-sub">แก้ไขข้อมูลส่วนตัว/รูปภาพ</span>
                </a>
            </div>
            <div class="col-6 col-sm-4 col-lg-3">
                <a href="../contact.php" class="tch-menu-tile">
                    <div class="menu-icn-wrap bg-success bg-opacity-10 text-success"><i class="bi bi-headset"></i></div>
                    <span class="m-title">ติดต่อสอบถาม</span>
                    <span class="m-sub">แจ้งปัญหาหรือสอบถามข้อมูล</span>
                </a>
            </div>
        </div>

        <div class="premium-card">
            <div class="premium-card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> รายชื่อนักเรียนล่าสุด</h6>
                    <a href="../teacher/manage_students.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 border-opacity-50">ดูทั้งหมด <i class="bi bi-arrow-right small"></i></a>
                </div>
            </div>
            <div class="table-responsive">
                <table id="teacherStudentsTable" class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ลำดับ</th>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ห้องเรียน</th>
                            <th class="text-center">ผลการประเมิน</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_list = "SELECT u.id, u.fullname, u.username, u.student_code, e.grade, cl.class_name 
                                     FROM users u 
                                     LEFT JOIN evaluations e ON u.id = e.student_id 
                                     LEFT JOIN classrooms cl ON u.classroom_id = cl.id
                                     WHERE u.role = 'student' $filter_where 
                                     ORDER BY u.student_code ASC
                                     LIMIT 10";
                        $res_list = $conn->query($sql_list);
                        if ($res_list && $res_list->num_rows > 0):
                            $i = 1;
                            while($row = $res_list->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="ps-4 text-secondary fw-bold" style="width: 80px;">
                                <?php echo $i++; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?php echo htmlspecialchars($row['student_code'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light rounded-circle p-2 d-inline-flex"><i class="bi bi-person text-secondary"></i></div>
                                    <strong class="text-dark"><?php echo e($row['fullname']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <?php if(!empty($row['class_name'])): ?>
                                    <span class="badge badge-custom badge-primary-soft"><?php echo e($row['class_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small italic">ยังไม่มีห้อง</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if(!empty($row['grade'])): ?>
                                    <span class="badge badge-custom badge-success-soft fw-bold px-3 py-2"><i class="bi bi-star-fill me-1"></i> เกรด <?php echo e($row['grade']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-warning-soft text-warning">รอดำเนินการ</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="../teacher/student_reports.php?student_id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-light border shadow-sm px-3 rounded-pill text-primary fw-bold">
                                    <i class="bi bi-search me-1 small"></i> ดูบันทึก
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox d-block fs-1 opacity-50 mb-2"></i> ไม่พบข้อมูลนักเรียน</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_ รายการ)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
    var tbl = document.getElementById('teacherStudentsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) $(tbl).DataTable({ order: [[1, 'asc']], language: dtLang, pageLength: 10 });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>