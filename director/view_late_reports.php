<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director','admin']);
$hide_welcome = true;
include '../includes/header.php';

$res = $conn->query("
    SELECT dr.id, dr.created_at, dr.status,
           u.fullname, u.username, c.class_name
    FROM daily_reports dr
    JOIN users u ON dr.student_id = u.id
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE dr.status = 'pending'
    AND dr.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY dr.created_at ASC
");
$total = $res ? $res->num_rows : 0;
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container py-4">
    <!-- Header with danger gradient override -->
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4"
         style="background:linear-gradient(135deg,#7f1d1d 0%,#dc2626 50%,#ef4444 100%);box-shadow:0 20px 50px rgba(239,68,68,0.3);">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <h4>รายงานค้างตรวจเกิน 7 วัน</h4>
                <p>พบ <strong><?= $total ?></strong> รายการที่ครูนิเทศก์ยังไม่ตรวจ</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <?php if ($total === 0): ?>
    <div class="dp-card">
        <div style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:3.5rem;margin-bottom:1rem;">🎉</div>
            <h5 class="fw-bold text-success">ไม่มีรายงานค้างการตรวจ!</h5>
            <p class="text-muted">ครูนิเทศก์ทุกคนได้ตรวจรายงานครบเรียบร้อยแล้ว</p>
        </div>
    </div>
    <?php else: ?>
    <div class="dp-card">
        <div class="dp-card-header" style="background:linear-gradient(135deg,#fff5f5,#fef2f2);color:#dc2626;">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            รายการรอครูนิเทศก์ตรวจ (เกิน 7 วัน)
        </div>
        <div class="table-responsive">
            <table id="viewLateReportsTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th>รหัสนักเรียน</th>
                        <th class="text-start">ชื่อ-นามสกุล</th>
                        <th>ห้องเรียน</th>
                        <th>วันที่ส่งรายงาน</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td><span class="dp-badge dp-badge-neutral"><?= htmlspecialchars($r['username']) ?></span></td>
                        <td class="text-start fw-semibold"><?= htmlspecialchars($r['fullname']) ?></td>
                        <td><?= htmlspecialchars($r['class_name'] ?: '—') ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                        <td>
                            <span class="dp-badge dp-badge-danger">
                                <i class="bi bi-clock-fill"></i>รอครูตรวจ
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang={search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl=document.getElementById('viewLateReportsTable');
    if(tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) $(tbl).DataTable({order:[[3,'asc']],language:dtLang,pageLength:25});
});
</script>
<?php include '../includes/footer.php'; ?>
