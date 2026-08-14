<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['supervisor']);

$u             = current_user();
$supervisor_id = (int)$u['id'];

$student_ids = get_supervised_student_ids($supervisor_id);
$ids_sql     = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// ดึงข้อมูลนักเรียน + สถิติบันทึก
$students = [];
if (!empty($student_ids)) {
    $sql = "SELECT u.id, u.fullname, u.student_code, u.email, u.phone, u.student_level,
                   u.company_name, u.trainer_name, u.profile_image,
                   c.class_name,
                   COUNT(dr.id) AS total_reports,
                   SUM(CASE WHEN dr.status='pending'  THEN 1 ELSE 0 END) AS pending_reports,
                   SUM(CASE WHEN dr.status='approved' THEN 1 ELSE 0 END) AS approved_reports,
                   SUM(CASE WHEN dr.supervisor_comment IS NOT NULL AND dr.supervisor_comment != '' THEN 1 ELSE 0 END) AS commented_reports
            FROM users u
            LEFT JOIN classrooms c ON u.classroom_id = c.id
            LEFT JOIN daily_reports dr ON u.id = dr.student_id
            WHERE u.id IN ($ids_sql) AND u.role = 'student'
            GROUP BY u.id
            ORDER BY u.fullname ASC";
    $res = $conn->query($sql);
    if ($res) while ($row = $res->fetch_assoc()) $students[] = $row;
}

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/director-pages.css">

<style>
.sv-student-card {
    background: #fff;
    border-radius: 1.5rem;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
    padding: 1.5rem;
    transition: all .25s;
    height: 100%;
}
.sv-student-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(14,165,233,.12);
    border-color: rgba(14,165,233,.2);
}
.sv-student-avatar {
    width: 60px; height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(14,165,233,.2);
}
.sv-student-avatar-placeholder {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0ea5e9, #14b8a6);
    color: #fff;
    font-size: 1.5rem;
    display: flex; align-items: center; justify-content: center;
}
.sv-mini-stat {
    text-align: center;
    padding: .5rem;
    border-radius: .65rem;
    background: #f8fafc;
}
.sv-mini-stat-num {
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1;
}
.sv-mini-stat-lbl {
    font-size: .68rem;
    font-weight: 600;
    color: #94a3b8;
    margin-top: .2rem;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dp-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"
         style="background:linear-gradient(135deg,#0c4a6e 0%,#0ea5e9 60%,#14b8a6 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">👥</div>
            <div>
                <h4>รายชื่อนักเรียนที่ดูแล</h4>
                <p>พบ <?= count($students) ?> คน ที่อยู่ภายใต้การดูแลของคุณ</p>
            </div>
        </div>
        <a href="/DVE_DATA_FULL/roles/supervisor.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <?php if (!empty($students)): ?>
    <div class="row g-3">
    <?php foreach ($students as $st):
        $total   = (int)$st['total_reports'];
        $pending = (int)$st['pending_reports'];
        $approved = (int)$st['approved_reports'];
        $commented = (int)$st['commented_reports'];
        $uncommented = $total - $commented;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="sv-student-card">
            <!-- Student Info Row -->
            <div class="d-flex align-items-center gap-3 mb-3">
                <?php 
                    $st_avatar = !empty($st['profile_image']) ? __DIR__ . '/../uploads/avatars/' . $st['profile_image'] : '';
                    if (!empty($st['profile_image']) && file_exists($st_avatar)): 
                ?>
                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($st['profile_image']) ?>?v=<?= time() ?>"
                         class="sv-student-avatar" alt="avatar" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'sv-student-avatar-placeholder\'><i class=\'bi bi-person-fill\'></i></div>';">
                <?php else: ?>
                    <div class="sv-student-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="fw-bold text-dark" style="font-size:.95rem;"><?= htmlspecialchars($st['fullname']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($st['student_code'] ?? '-') ?></div>
                    <?php if (!empty($st['class_name'])): ?>
                    <div class="badge bg-primary bg-opacity-10 text-primary fw-bold mt-1" style="font-size:.7rem;">
                        <?= htmlspecialchars($st['class_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="badge rounded-pill fw-bold" style="background:rgba(14,165,233,.12);color:#0ea5e9;font-size:.7rem;white-space:nowrap;">
                    <?= htmlspecialchars($st['student_level'] ?? 'ปวช.') ?>
                </span>
            </div>

            <!-- Company/Trainer -->
            <?php if (!empty($st['company_name'])): ?>
            <div class="p-2 rounded-3 mb-3" style="background:#f8fafc;border:1px solid rgba(0,0,0,.05);">
                <div class="text-muted" style="font-size:.78rem;">
                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($st['company_name']) ?>
                    <?php if (!empty($st['trainer_name'])): ?>
                    <span class="ms-2"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($st['trainer_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Mini Stats -->
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="sv-mini-stat">
                        <div class="sv-mini-stat-num text-dark"><?= $total ?></div>
                        <div class="sv-mini-stat-lbl">บันทึกทั้งหมด</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="sv-mini-stat">
                        <div class="sv-mini-stat-num text-success"><?= $commented ?></div>
                        <div class="sv-mini-stat-lbl">คอมเมนต์แล้ว</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="sv-mini-stat">
                        <div class="sv-mini-stat-num <?= $uncommented > 0 ? 'text-danger' : 'text-success' ?>"><?= $uncommented ?></div>
                        <div class="sv-mini-stat-lbl">รอ feedback</div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php?student_id=<?= $st['id'] ?>"
                   class="btn btn-sm btn-primary rounded-pill fw-bold flex-grow-1" style="font-size:.8rem;">
                    <i class="bi bi-journal-text me-1"></i> ดูบันทึกงาน
                </a>
                <?php if ($uncommented > 0): ?>
                <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php?student_id=<?= $st['id'] ?>&status_filter=uncommented"
                   class="btn btn-sm btn-outline-danger rounded-pill fw-bold" style="font-size:.8rem;" title="รอ feedback <?= $uncommented ?> รายการ">
                    <i class="bi bi-chat-dots"></i>
                    <span class="badge bg-danger rounded-pill ms-1" style="font-size:.7rem;"><?= $uncommented ?></span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="text-center py-5 bg-white rounded-4 shadow-sm">
        <i class="bi bi-people fs-1 text-muted opacity-40 d-block mb-3"></i>
        <p class="text-muted fs-5 fw-semibold mb-1">ยังไม่มีนักเรียนที่ดูแล</p>
        <p class="text-muted small">กรุณาติดต่อเจ้าหน้าที่เพื่อมอบหมายนักเรียน หรือนักเรียนเลือกคุณเป็นผู้ดูแลผ่านหน้าโปรไฟล์</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
