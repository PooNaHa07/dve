<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);
$hide_welcome = true;
include '../includes/header.php';

$res = $conn->query("
    SELECT sf.id, sf.file_path, sf.signed_file, sf.director_signed_at,
           u.fullname as teacher_name,
           s.fullname as student_name, s.student_code
    FROM supervision_files sf
    JOIN users u ON sf.teacher_id = u.id
    JOIN users s ON sf.student_id = s.id
    WHERE sf.status = 2
    ORDER BY sf.director_signed_at DESC
");
$total      = $res ? $res->num_rows : 0;
$has_rows   = $total > 0;
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid py-4">

    <!-- Header — green/teal gradient for "signed/completed" tone -->
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4"
         style="background:linear-gradient(135deg,#064e3b 0%,#059669 50%,#10b981 100%);box-shadow:0 20px 50px rgba(16,185,129,0.3);">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">📁</div>
            <div>
                <h4>ใบนิเทศที่ลงนามแล้ว</h4>
                <p>เอกสารที่ผู้บริหารลงนามเรียบร้อยแล้ว • ทั้งหมด <strong><?= number_format($total) ?></strong> ฉบับ</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <!-- Summary strip -->
    <?php if ($has_rows): ?>
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.25rem;padding:0.75rem 1.25rem;background:#fff;border-radius:1rem;border:1px solid rgba(16,185,129,0.15);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <i class="bi bi-patch-check-fill text-success fs-5"></i>
        <span class="fw-semibold" style="color:#065f46;">เอกสารลงนามครบ <?= number_format($total) ?> ฉบับ</span>
        <span class="ms-auto dp-badge dp-badge-success"><i class="bi bi-check-circle-fill"></i>ลงนามแล้ว</span>
    </div>
    <?php endif; ?>

    <!-- Table card -->
    <div class="dp-card">
        <div class="dp-card-header" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);color:#065f46;">
            <i class="bi bi-folder-check text-success"></i>
            รายการใบนิเทศที่ลงนามแล้ว
        </div>
        <div class="table-responsive">
            <table id="signListTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start" style="width: 80px;">ลำดับ</th>
                        <th class="text-start">ครูนิเทศก์</th>
                        <th class="text-start">รหัสนักศึกษา</th>
                        <th class="text-start">นักเรียน</th>
                        <th>วันที่ลงนาม</th>
                        <th>สถานะ</th>
                        <th>ดาวน์โหลด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($has_rows):
                        $i = 1;
                        while ($r = $res->fetch_assoc()):
                            $download_url = !empty($r['signed_file'])
                                ? '../uploads/supervision_docs/signed/' . htmlspecialchars($r['signed_file'])
                                : (!empty($r['file_path']) ? '../uploads/supervision_docs/original/' . htmlspecialchars($r['file_path']) : '');
                    ?>
                    <tr>
                        <td class="text-start text-secondary fw-bold">
                            <?= $i++ ?>
                        </td>
                        <td class="text-start">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#059669,#10b981);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.85rem;font-weight:700;flex-shrink:0;">
                                    <?= mb_substr($r['teacher_name'], 0, 1) ?>
                                </div>
                                <span class="fw-semibold"><?= htmlspecialchars($r['teacher_name']) ?></span>
                            </div>
                        </td>
                        <td class="text-start">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?= htmlspecialchars($r['student_code'] ?? '-') ?></span>
                        </td>
                        <td class="text-start">
                            <div><?= htmlspecialchars($r['student_name']) ?></div>
                        </td>
                        <td class="text-center text-nowrap text-muted small">
                            <i class="bi bi-calendar-check me-1 text-success"></i>
                            <?= $r['director_signed_at'] ? date('d/m/Y H:i', strtotime($r['director_signed_at'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <span class="dp-badge dp-badge-success">
                                <i class="bi bi-check-circle-fill"></i>ลงนามแล้ว
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($download_url): ?>
                            <a href="<?= $download_url ?>" target="_blank" class="dp-btn dp-btn-success">
                                <i class="bi bi-download"></i>ดาวน์โหลด
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="dp-empty">
                                <i class="bi bi-folder2-open"></i>
                                <p>ยังไม่มีใบนิเทศที่ลงนามแล้ว</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang = {search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl = document.getElementById('signListTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        $(tbl).DataTable({ order:[[3,'desc']], language:dtLang, pageLength:25 });
    }
});
</script>
<?php include '../includes/footer.php'; ?>
