<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff', 'teacher']);

$sql = "SELECT u.id, u.fullname, u.username, u.student_code, c.class_name,
               COUNT(dr.id) as total_days 
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        LEFT JOIN daily_reports dr ON u.id = dr.student_id 
        WHERE u.role = 'student' 
        GROUP BY u.id, u.fullname, u.username, u.student_code, c.class_name
        ORDER BY u.student_code ASC"; 

$result = $conn->query($sql);

if (!$result) {
    die("<div class='alert alert-danger'>SQL Error: " . $conn->error . "</div>");
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-book-reader"></i>
                ตรวจสอบสมุดบันทึกนักเรียน
            </h2>
            <p class="text-muted small mb-0 mt-1">ติดตามการลงบันทึกการฝึกงานรายวันของนักเรียนทั้งหมด</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <div class="admin-table-container shadow-sm">
        <div class="table-responsive bg-white">
            <table id="studentReportsTable" class="table admin-table table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 80px;">ลำดับ</th>
                        <th style="width: 150px;">รหัสนักศึกษา</th>
                        <th>ข้อมูลผู้ฝึกงาน / ชื่อผู้ใช้</th>
                        <th>สังกัดห้องเรียน</th>
                        <th class="text-center">จำนวนบันทึกสะสม</th>
                        <th class="text-center" style="width: 180px;">จัดการข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php 
                        $i = 1;
                        while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="text-center text-secondary fw-bold">
                                <?php echo $i++; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.85rem; letter-spacing:.5px;"><?php echo htmlspecialchars($row['student_code'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6 mb-1"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                <div class="text-muted small font-monospace bg-light d-inline-block px-1 rounded border border-opacity-10">@<?php echo htmlspecialchars($row['username']); ?></div>
                            </td>
                            <td>
                                <span class="fw-medium text-muted"><i class="fas fa-graduation-cap me-1 opacity-50"></i><?php echo htmlspecialchars($row['class_name'] ?? 'ไม่ระบุ'); ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center rounded-3 bg-light px-3 py-1 fw-bold text-dark border">
                                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                                    <?php echo $row['total_days']; ?>
                                    <span class="ms-1 small fw-normal text-muted">วัน</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="report_details.php?student_id=<?php echo $row['id']; ?>" class="btn btn-admin-primary btn-sm w-100 text-decoration-none d-flex align-items-center justify-content-center gap-2" style="padding: 0.5rem;">
                                    <span>เรียกดูสมุดบันทึก</span>
                                    <i class="fas fa-chevron-right small"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open d-block fs-2 mb-2 opacity-25"></i>
                                ยังไม่มีข้อมูลนักเรียนในระบบ
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
    var tbl = document.getElementById('studentReportsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_ แถว', 
            info: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ คน', 
            paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
            zeroRecords: 'ไม่พบรายชื่อที่ค้นหา'
        };
        $(tbl).DataTable({ 
            order: [[1, 'asc']], 
            language: dtLang, 
            pageLength: 25,
            dom: '<"d-flex justify-content-between align-items-center mb-3 px-2"lf>rt<"d-flex justify-content-between mt-3 px-2"ip>',
            columnDefs: [{ orderable: false, targets: 5 }] 
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>