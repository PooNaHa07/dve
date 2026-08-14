<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$teacher_sql = "SELECT fullname FROM users WHERE id = $teacher_id";
$teacher_res = $conn->query($teacher_sql);
$teacher = $teacher_res->fetch_assoc();

if (!$teacher) { die("<div class='container mt-5 text-center'><h4>ไม่พบข้อมูลครู</h4><a href='manage_supervision.php' class='btn btn-secondary'>ย้อนกลับ</a></div>"); }

$sql = "SELECT p.*, c.name AS company_name 
        FROM plans p 
        LEFT JOIN companies c ON p.company_id = c.id 
        WHERE p.teacher_id = $teacher_id 
        ORDER BY p.uploaded_at DESC";
$result = $conn->query($sql);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
    @media print {
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .table td, .table th { border: 1px solid #000 !important; color: #000 !important; }
        .admin-header-section, footer { display: none !important; }
    }
    .report-stamp-zone { margin-top: 4rem; display: flex; justify-content: space-around; text-align: center; }
    .print-doc-header { display: none; }
    @media print {
        .print-doc-header { display: block; margin-bottom: 2rem; text-align: center; }
    }
</style>

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4 no-print">
        <div>
            <h2 class="admin-header-title mb-1">
                <i class="fas fa-file-medical-alt"></i>
                สรุปผลแผนการนิเทศ
            </h2>
            <div class="text-muted ps-5 ms-2">
                <span class="admin-badge bg-light text-primary fs-6 border">
                    <i class="fas fa-chalkboard-teacher me-2"></i><?php echo htmlspecialchars($teacher['fullname']); ?>
                </span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_supervision.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-chevron-left me-1"></i> ย้อนกลับ
            </a>
            <button onclick="window.print()" class="btn btn-dark shadow-sm rounded-3 px-4">
                <i class="fas fa-print me-2"></i> พิมพ์รายงาน
            </button>
        </div>
    </div>

    <div id="printArea">
        <div class="print-doc-header">
            <h2 class="fw-bold" style="font-size: 24px; color: #000;">รายงานสรุปผลการส่งแผนการนิเทศ</h2>
            <h4 style="font-size: 18px; color: #000;">ผู้จัดทำ / ครูผู้สอน: <?php echo htmlspecialchars($teacher['fullname']); ?></h4>
            <p style="color: #000; margin-top: 5px;">ข้อมูลรายงานฉบับสมบูรณ์ ณ วันที่: <?php echo date('d/m/Y H:i'); ?> น.</p>
            <hr style="border: 1px solid #000; opacity: 1; margin: 1rem 0;">
        </div>

        <div class="admin-table-container bg-white shadow-sm p-3">
            <div class="table-responsive">
                <table id="teacherDetailsTable" class="table admin-table table-bordered align-middle mb-0" style="width:100%">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 35%;">รายละเอียดแผน / หัวข้อ</th>
                            <th>หน่วยงาน / สถานประกอบการ</th>
                            <th class="text-center">วันที่กำหนดนิเทศ</th>
                            <th class="text-end no-print" style="width: 140px;">สถานะส่ง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark fs-6 mb-1"><i class="far fa-file-alt text-muted me-2 opacity-50"></i><?php echo htmlspecialchars($row['title']); ?></div>
                                    <div class="text-muted small"><i class="fas fa-calendar-plus me-1 small opacity-50"></i> ส่งเมื่อ: <?php echo date('d/m/Y', strtotime($row['uploaded_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars(!empty($row['company_name']) ? $row['company_name'] : 'ID: '.$row['company_id']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><i class="far fa-clock me-1 text-primary"></i><?php echo date('d/m/Y', strtotime($row['plan_date'])); ?></span>
                                </td>
                                <td class="text-end no-print">
                                    <span class="admin-badge bg-success bg-opacity-10 text-success border-success border-opacity-25">
                                        <i class="fas fa-check me-1 small"></i>เรียบร้อย
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-file-excel d-block fs-2 mb-2 opacity-25"></i>ไม่พบประวัติการส่งแผนการนิเทศ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-stamp-zone">
            <div style="width: 260px;">
                <div class="mb-2" style="border-bottom: 1px dotted #000; height: 30px;"></div>
                <div class="mb-2">(..........................................................)</div>
                <div class="fw-bold" style="color:#000;">เจ้าหน้าที่ / ผู้รวบรวมข้อมูล</div>
            </div>
            <div style="width: 260px;">
                <div class="mb-2" style="border-bottom: 1px dotted #000; height: 30px;"></div>
                <div class="mb-2">(..........................................................)</div>
                <div class="fw-bold" style="color:#000;">หัวหน้างานอาชีวศึกษาระบบทวิภาคี</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('teacherDetailsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_', 
            info: 'รวม _TOTAL_ รายการ', 
            infoEmpty: 'ไม่มีข้อมูล', 
            paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' } 
        };
        $(tbl).DataTable({ 
            order: [[2, 'desc']], 
            language: dtLang, 
            pageLength: 25, 
            dom: '<"d-flex justify-content-between align-items-center mb-3 no-print"lf>rt<"d-flex justify-content-between mt-3 no-print"ip>',
            columnDefs: [{ orderable: false, targets: 3 }] 
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>