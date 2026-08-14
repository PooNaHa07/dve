<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

$sql = "SELECT u.id, u.fullname, u.username, u.student_code, c.class_name,
        COUNT(dr.id) as approved_days
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        LEFT JOIN daily_reports dr ON u.id = dr.student_id AND dr.status = 'approved'
        WHERE u.role = 'student'
        GROUP BY u.id
        ORDER BY u.student_code ASC";
$result = $conn->query($sql);

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
                <i class="fas fa-certificate" style="color: #f59e0b;"></i>
                ระบบออกเกียรติบัตรการฝึกงาน
            </h2>
            <p class="text-muted small mb-0 mt-1">ตรวจสอบชั่วโมงฝึกประสบการณ์และพิมพ์เอกสารเกียรติบัตร</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <div class="admin-table-container shadow-sm">
        <div class="table-responsive bg-white">
            <table id="manageCertificatesTable" class="table admin-table table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 140px;">รหัสนักเรียน</th>
                        <th>ข้อมูลผู้ฝึกงาน / แผนก</th>
                        <th class="text-center">วันที่อนุมัติแล้ว</th>
                        <th class="text-center">สถานะเอกสาร</th>
                        <th class="text-center" style="width: 160px;">พิมพ์เอกสาร</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $qualified = ($row['approved_days'] >= 1); // Logic constant
                        ?>
                        <tr>
                            <td>
                                <span class="text-muted fw-bold font-monospace bg-light px-2 py-1 rounded border small"><?php echo htmlspecialchars($row['username']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6 mb-1"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                <div class="small text-muted"><i class="fas fa-door-open me-1 opacity-50"></i><?php echo htmlspecialchars($row['class_name'] ?? 'ไม่ระบุห้องเรียน'); ?></div>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-light border px-3 py-1 fw-bold text-dark" style="min-width: 80px;">
                                    <i class="fas fa-calendar-check text-info me-2"></i>
                                    <?php echo $row['approved_days']; ?>
                                    <span class="ms-1 small fw-normal text-muted">วัน</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if($qualified): ?>
                                    <span class="admin-badge bg-success bg-opacity-10 text-success border-success border-opacity-25">
                                        <i class="fas fa-check-circle me-1"></i>ผ่านเกณฑ์
                                    </span>
                                <?php else: ?>
                                    <span class="admin-badge bg-light text-muted">
                                        <i class="fas fa-clock me-1"></i>รอตรวจสอบ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="print_certificate.php?id=<?php echo $row['id']; ?>" target="_blank" 
                                   class="btn <?php echo $qualified ? 'btn-admin-primary' : 'btn-admin-outline disabled opacity-50'; ?> btn-sm w-100 text-decoration-none" 
                                   style="font-size: 0.85rem; padding: 0.5rem;">
                                    <i class="fas fa-print me-1"></i> พิมพ์เกียรติบัตร
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-award d-block fs-2 mb-2 opacity-25"></i>
                                ไม่พบข้อมูลนักเรียนในระบบ
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
    var tbl = document.getElementById('manageCertificatesTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_ รายการ', 
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', 
            infoEmpty: 'ไม่มีข้อมูล', 
            infoFiltered: '(กรองจาก _MAX_ รายการ)', 
            paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, 
            zeroRecords: 'ไม่พบข้อมูล' 
        };
        $(tbl).DataTable({ 
            order: [[0, 'asc']], 
            language: dtLang, 
            pageLength: 25,
            dom: '<"d-flex justify-content-between align-items-center mb-3 px-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 px-2"ip>',
            columnDefs: [{ orderable: false, targets: 4 }] 
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>