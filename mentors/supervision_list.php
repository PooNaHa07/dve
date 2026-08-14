<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['mentor', 'teacher', 'staff', 'admin']);

$u = current_user();
$teacher_id = (int)$u['id'];

$sql = "SELECT sf.id, sf.uploaded_at, sf.director_signed_at, sf.status, sf.file_path, sf.signed_file,
        u.fullname as student_name, u.student_code, COALESCE(comp.name, u.company_name) as company_name,
        c.class_name
        FROM supervision_files sf
        JOIN users u ON u.id = sf.student_id
        LEFT JOIN companies comp ON u.company_id = comp.id
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE sf.teacher_id = ?
        ORDER BY sf.status ASC, sf.uploaded_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();

$by_status = [0 => [], 1 => [], 2 => []];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $s = (int)$row['status'];
        if (isset($by_status[$s])) $by_status[$s][] = $row;
    }
}

$sections = [
    0 => ['title' => 'รอเจ้าหน้าที่ตรวจสอบ', 'icon' => 'bi-hourglass-split', 'badge' => 'warning', 'desc' => 'ส่งแล้ว รอเจ้าหน้าที่ตรวจสอบและเสนอผู้บริหาร'],
    1 => ['title' => 'รอผู้บริหารลงนาม', 'icon' => 'bi-person-badge-fill', 'badge' => 'info', 'desc' => 'ส่งแล้ว รอผู้บริหารลงนามออนไลน์'],
    2 => ['title' => 'ลงนามแล้ว', 'icon' => 'bi-check-circle-fill', 'badge' => 'success', 'desc' => 'ผู้บริหารลงนามสมบูรณ์ พร้อมดาวน์โหลดใช้งาน'],
];

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
    body { background-color: #f0f2f5; }
    .supervision-section-title { font-size: 1.1rem; font-weight: 700; color: #2d3748; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .status-icon-badge { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
</style>

<div class="container py-4 mb-5">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb bg-white p-3 rounded shadow-sm small">
            <li class="breadcrumb-item"><a href="../roles/teacher.php" class="text-decoration-none"><i class="bi bi-house-door"></i> หน้าหลัก</a></li>
            <li class="breadcrumb-item active" aria-current="page">ใบนิเทศแยกสถานะ</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-check-fill text-primary me-2"></i> ประวัติใบนิเทศ (แยกตามสถานะ)</h3>
            <p class="text-muted mb-0">ติดตามสถานะเอกสารที่ส่งเข้าสู่กระบวนการลงนามออนไลน์</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../roles/teacher.php" class="btn btn-outline-secondary px-4 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-arrow-left me-2"></i> กลับหน้าหลัก
            </a>
            <a href="upload_supervision.php" class="btn btn-primary px-4 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-plus-circle-fill me-2"></i> อัปโหลดใบนิเทศใหม่
            </a>
        </div>
    </div>

    <?php foreach ($sections as $status => $section): ?>
    <div class="supervision-section-title mt-4">
        <div class="status-icon-badge bg-<?= $section['badge'] ?> bg-opacity-10 text-<?= $section['badge'] ?>"><i class="bi <?= $section['icon'] ?>"></i></div>
        <?= $section['title'] ?>
        <span class="badge rounded-pill bg-<?= $section['badge'] ?>-soft text-<?= $section['badge'] ?> shadow-none fw-bold" style="font-size: 0.85rem; border:1px solid rgba(0,0,0,0.05);"><?= count($by_status[$status]) ?> รายการ</span>
    </div>
    
    <div class="premium-card mb-5 border-0">
        <div class="premium-card-header bg-white py-3 border-bottom border-opacity-10">
            <div class="d-flex align-items-center gap-2">
                <div style="width:4px; height:18px; border-radius:2px;" class="bg-<?= $section['badge'] ?>"></div>
                <h6 class="mb-0 text-muted small fw-bold"><?= $section['desc'] ?></h6>
            </div>
        </div>
        <div class="p-0">
            <?php if (!empty($by_status[$status])): ?>
            <div class="table-responsive">
                <table class="table table-tch table-hover align-middle mb-0 table-supervision" data-status="<?= $status ?>">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">ลำดับ</th>
                            <th>วันที่ส่ง</th>
                            <th>รหัสนักศึกษา</th>
                            <th>รายชื่อนักเรียน</th>
                            <th>ห้องเรียน</th>
                            <th>สถานประกอบการ</th>
                            <?php if ($status === 2): ?>
                            <th>วันที่ผู้อนุมัติลงนาม</th>
                            <th class="text-center no-sort" style="width: 130px;">ดาวน์โหลด</th>
                            <?php else: ?>
                            <th class="text-center no-sort" style="width: 130px;">สถานะ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($by_status[$status] as $row):
                            if ($status === 2) {
                                $signed_url = !empty($row['signed_file'])
                                    ? '../uploads/supervision_docs/signed/' . htmlspecialchars($row['signed_file'])
                                    : (!empty($row['file_path']) ? '../uploads/supervision_docs/original/' . htmlspecialchars($row['file_path']) : '');
                            }
                        ?>
                            <tr>
                                <td class="ps-4 text-secondary fw-bold">
                                    <?= $i++ ?>
                                </td>
                                <td data-order="<?= strtotime($row['uploaded_at']) ?>">
                                    <div class="fw-medium text-dark small"><i class="bi bi-calendar3 text-muted me-1"></i> <?= date('d/m/Y', strtotime($row['uploaded_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= date('H:i', strtotime($row['uploaded_at'])) ?> น.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?= htmlspecialchars($row['student_code'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-light rounded-circle p-2 text-primary" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-person-fill small"></i></div>
                                        <strong class="text-dark"><?= htmlspecialchars($row['student_name']) ?></strong>
                                    </div>
                                </td>
                            <td>
                                <?php if(!empty($row['class_name'])): ?>
                                    <span class="badge bg-primary-soft text-primary border-0 fw-bold small px-2 py-1"><i class="bi bi-building-fill me-1"></i> <?= htmlspecialchars($row['class_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(!empty($row['company_name'])): ?>
                                    <i class="bi bi-briefcase-fill text-muted me-1"></i> <?= htmlspecialchars($row['company_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($status === 2): ?>
                            <td>
                                <span class="badge bg-success-soft text-success border-0 fw-semibold px-2 py-1"><i class="bi bi-check2-all me-1"></i> <?= $row['director_signed_at'] ? date('d/m/Y H:i', strtotime($row['director_signed_at'])) : '-' ?></span>
                            </td>
                            <td class="text-center pe-3">
                                <?php if (!empty($signed_url)): ?>
                                <a href="<?= $signed_url ?>" target="_blank" class="btn btn-sm btn-success px-3 rounded-pill fw-bold shadow-sm border-0" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); font-size: 0.8rem;">
                                    <i class="bi bi-download me-1"></i> โหลด
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <?php else: ?>
                            <td class="text-center pe-3">
                                <?php if ($status === 0): ?>
                                <span class="badge badge-custom badge-warning-soft text-warning w-100 border border-warning border-opacity-25" style="font-size: 0.75rem;"><i class="bi bi-hourglass-split me-1"></i> รอตรวจ</span>
                                <?php else: ?>
                                <span class="badge badge-custom badge-info-soft text-info w-100 border border-info border-opacity-25" style="font-size: 0.75rem;"><i class="bi bi-person-badge me-1"></i> รอ ผบ.</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder2-open d-block fs-1 opacity-25 mb-3"></i>
                <p class="mb-0 small fw-semibold">ไม่มีประวัติการยื่นเอกสารในหมวดหมู่นี้</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var lang = {
        search: 'ค้นหา:',
        lengthMenu: 'แสดง _MENU_ รายการ',
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
        infoEmpty: 'ไม่มีข้อมูล',
        infoFiltered: '(กรองจาก _MAX_ รายการ)',
        paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
        zeroRecords: 'ไม่พบข้อมูล'
    };
    document.querySelectorAll('.table-supervision').forEach(function(tbl) {
        if (tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
            $(tbl).DataTable({
                order: [[1, 'desc']],
                language: lang,
                pageLength: 5
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
