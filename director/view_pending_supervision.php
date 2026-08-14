<?php
require_once '../includes/configdb.php';
require_login();
require_role(['director']);
include '../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<?php
$sql = "
SELECT 
    u.username,
    u.fullname,
    dr.created_at
FROM daily_reports dr
JOIN users u ON dr.student_id = u.id
LEFT JOIN supervision_files sf ON dr.student_id = sf.student_id
WHERE sf.id IS NULL
AND dr.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY dr.created_at ASC
";
$res = $conn->query($sql);
?>

<div class="container py-4">
<h4 class="fw-bold mb-3">📌 รายงานค้างการนิเทศ</h4>

<div class="table-responsive">
<table id="viewPendingSupervisionTable" class="table table-bordered table-striped" style="width:100%">
<thead class="bg-light">
<tr>
    <th>รหัส</th>
    <th>ชื่อ</th>
    <th>วันที่ส่งรายงาน</th>
    <th>ค้าง (วัน)</th>
</tr>
</thead>
<tbody>
<?php while($r = $res->fetch_assoc()): ?>
<tr>
    <td><?= $r['username']; ?></td>
    <td><?= $r['fullname']; ?></td>
    <td><?= date('d/m/Y', strtotime($r['created_at'])); ?></td>
    <td class="text-danger fw-bold">
        <?= floor((time()-strtotime($r['created_at']))/86400); ?>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('viewPendingSupervisionTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_ รายการ)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
        $(tbl).DataTable({ order: [[3, 'desc']], language: dtLang, pageLength: 25 });
    }
});
</script>
<?php include '../includes/footer.php'; ?>
