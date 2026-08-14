<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director','admin']);
$hide_welcome = true;
include '../includes/header.php';

$upload_path_p1 = "../uploads/plans/";
$upload_path_p2 = "../uploads/subject_plans/";
$sql = "SELECT u.id, u.fullname,
        (SELECT filename FROM plans WHERE teacher_id = u.id ORDER BY id DESC LIMIT 1) as p1_file,
        (SELECT filename FROM subject_plans WHERE teacher_id = u.id ORDER BY id DESC LIMIT 1) as p2_file
        FROM users u WHERE u.role = 'teacher' OR u.role = 'ครู'";
$res = $conn->query($sql);
if (!$res) die("<div class='alert alert-danger'>SQL Error: ".$conn->error."</div>");
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid py-4">
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">📋</div>
            <div>
                <h4>ตรวจสอบการส่งแผนงานครู</h4>
                <p>แผนการนิเทศและแผนการฝึกวิชาชีพของครูนิเทศก์</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <div class="dp-card">
        <div class="dp-card-header">
            <i class="bi bi-file-earmark-text-fill text-warning"></i>
            สถานะการส่งแผนงานครูนิเทศก์
        </div>
        <div class="table-responsive">
            <table id="viewPlansTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start">ครูนิเทศก์</th>
                        <th>แผนการนิเทศ</th>
                        <th>แผนการฝึก (รายวิชา)</th>
                        <th>สถานะรวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                                    <?= mb_substr($r['fullname'], 0, 1) ?>
                                </div>
                                <strong><?= htmlspecialchars($r['fullname']) ?></strong>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if(!empty($r['p1_file'])): ?>
                                <div class="mb-1"><span class="dp-badge dp-badge-success"><i class="bi bi-check-circle-fill"></i>ส่งแล้ว</span></div>
                                <a href="<?= $upload_path_p1 . $r['p1_file'] ?>" target="_blank" class="dp-btn dp-btn-outline">
                                    <i class="bi bi-eye"></i>เปิดดู
                                </a>
                            <?php else: ?>
                                <span class="dp-badge dp-badge-danger"><i class="bi bi-x-circle-fill"></i>ยังไม่ส่ง</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if(!empty($r['p2_file'])): ?>
                                <div class="mb-1"><span class="dp-badge dp-badge-info"><i class="bi bi-check-circle-fill"></i>ส่งแล้ว</span></div>
                                <a href="<?= $upload_path_p2 . $r['p2_file'] ?>" target="_blank" class="dp-btn dp-btn-outline">
                                    <i class="bi bi-eye"></i>เปิดดู
                                </a>
                            <?php else: ?>
                                <span class="dp-badge dp-badge-danger"><i class="bi bi-x-circle-fill"></i>ยังไม่ส่ง</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if(!empty($r['p1_file']) && !empty($r['p2_file'])): ?>
                                <span class="dp-badge dp-badge-success"><i class="bi bi-patch-check-fill"></i>สมบูรณ์</span>
                            <?php else: ?>
                                <span class="dp-badge dp-badge-warning"><i class="bi bi-clock-fill"></i>ค้างส่ง</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang={search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl=document.getElementById('viewPlansTable');
    if(tbl && tbl.querySelector('tbody tr')) $(tbl).DataTable({order:[[0,'asc']],language:dtLang,pageLength:25});
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>