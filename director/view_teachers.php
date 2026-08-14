<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director','admin']);
$hide_welcome = true;
include '../includes/header.php';

$sql = "SELECT u.*,
       (SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id = u.id) as group_count,
       (SELECT COUNT(*) FROM users WHERE role='student' AND classroom_id IN (SELECT classroom_id FROM teacher_assignments WHERE teacher_id = u.id)) as student_load
       FROM users u WHERE u.role = 'teacher'";
$res = $conn->query($sql);
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid py-4">
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">👨‍🏫</div>
            <div>
                <h4>รายชื่อครูนิเทศก์</h4>
                <p>ข้อมูลครูนิเทศก์พร้อมจำนวนกลุ่มและนักเรียนที่รับผิดชอบ</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <div class="dp-card">
        <div class="dp-card-header">
            <i class="bi bi-person-badge-fill text-primary"></i>
            รายชื่อครูนิเทศก์ทั้งหมด
        </div>
        <div class="table-responsive">
            <table id="viewTeachersTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start">ชื่อ-นามสกุล</th>
                        <th>อีเมล</th>
                        <th>กลุ่มเรียนในดูแล</th>
                        <th>นักเรียนที่รับผิดชอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                    <?= mb_substr($row['fullname'], 0, 1) ?>
                                </div>
                                <span class="fw-semibold"><?= htmlspecialchars($row['fullname']) ?></span>
                            </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td class="text-center">
                            <span class="dp-badge dp-badge-info">
                                <i class="bi bi-collection"></i> <?= $row['group_count'] ?> กลุ่ม
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="dp-badge dp-badge-primary">
                                <i class="bi bi-people"></i> <?= $row['student_load'] ?> คน
                            </span>
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
    $('#viewTeachersTable').DataTable({ order:[[0,'asc']], language:dtLang, pageLength:25 });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>