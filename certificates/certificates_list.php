<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบสิทธิ์ (เจ้าหน้าที่หรือแอดมินเท่านั้น)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../index.php"); 
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
include __DIR__ . '/../includes/header.php';

// ดึงข้อมูลนักเรียน + จำนวนรายงานที่ผ่าน + เกรด + ชื่อสถานประกอบการ
$sql = "SELECT 
            u.id, 
            u.fullname, 
            u.student_code, 
            e.grade,
            COALESCE(c.name, u.company_name) AS company_name,
            (SELECT COUNT(*) FROM daily_reports dr WHERE dr.student_id = u.id AND dr.status = 'approved') AS total_approved
        FROM users u 
        LEFT JOIN evaluations e ON u.id = e.student_id 
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.role = 'student'
        ORDER BY u.student_code ASC";
$result = $conn->query($sql);
$rows = [];
$stats = ['total' => 0, 'evaluated' => 0, 'pending' => 0];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $stats['total']++;
        if(!empty($row['grade'])) $stats['evaluated']++;
        else $stats['pending']++;
    }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    /* Premium Dashboard Styles */
    :root {
        --primary: #6366f1;
        --primary-soft: rgba(99, 102, 241, 0.1);
        --success: #10b981;
        --success-soft: rgba(16, 185, 129, 0.1);
        --warning: #f59e0b;
        --warning-soft: rgba(245, 158, 11, 0.1);
        --danger: #ef4444;
        --danger-soft: rgba(239, 68, 68, 0.1);
    }

    .dashboard-page { min-height: 100vh; position: relative; overflow: hidden; }
    .bg-blob {
        position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
        top: -200px; right: -200px; z-index: -1; pointer-events: none;
    }
    .bg-blob.secondary { bottom: -200px; left: -200px; background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, rgba(255, 255, 255, 0) 70%); }

    .glass-card {
        background: rgba(255, 255, 255, 0.8) !important;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.7) !important;
        box-shadow: 0 10px 30px rgba(31, 38, 135, 0.04) !important;
        border-radius: 24px;
        overflow: hidden;
    }

    .stat-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 20px;
    }
    .stat-card:hover { transform: translateY(-5px); }
    
    .stat-icon {
        width: 54px; height: 54px; display: flex; align-items: center; justify-content: center;
        border-radius: 16px; font-size: 1.5rem; margin-bottom: 1rem;
    }

    .table thead th {
        background: transparent !important;
        border-bottom: 1px solid rgba(0,0,0,0.05) !important;
        padding: 1.2rem 1rem !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        color: #64748b !important;
    }
    
    .table tbody td { padding: 1.2rem 1rem !important; border-bottom: 1px solid rgba(0,0,0,0.03) !important; }

    .grade-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.85rem;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.95rem;
        background: rgba(255, 255, 255, 0.8);
        color: var(--primary);
        border: 1px solid rgba(99, 102, 241, 0.2);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        display: inline-block; padding: 0.45rem 1.2rem; border-radius: 50rem;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.3px;
    }
    .status-badge.certified { background: var(--success-soft); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
    .status-badge.pending { background: var(--warning-soft); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }

    /* DataTable Customization */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid rgba(0,0,0,0.08) !important;
        border-radius: 10px !important;
        padding: 0.4rem 1rem !important;
        background: white !important;
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid rgba(0,0,0,0.08) !important;
        border-radius: 8px !important;
    }
    .page-link { border: none !important; margin: 0 3px !important; border-radius: 8px !important; color: #64748b !important; }
    .page-item.active .page-link { background-color: var(--primary) !important; color: white !important; }

    .animate-up { animation: slideUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="dashboard-page py-5">
    <div class="container">
        <!-- Decor -->
        <div class="bg-blob"></div>
        <div class="bg-blob secondary"></div>

        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-up">
            <div>
                <h2 class="fw-bold text-dark mb-1">รายชื่อนักเรียนที่ผ่านการประเมิน</h2>
                <p class="text-muted mb-0">จัดการใบประกาศนียบัตรและเกียรติบัตรสำหรับนักเรียนนักศึกษา</p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="generate_certificate.php" class="btn btn-primary rounded-pill px-4 shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-award-fill me-2"></i> ออกเกียรติบัตรทั้งหมด
                </a>
                <a href="../roles/staff.php" class="btn btn-white rounded-pill px-4 shadow-sm border-0 border">
                    <i class="bi bi-house-door me-2"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row g-4 mb-5 animate-up" style="animation-delay: 0.1s;">
            <div class="col-md-4">
                <div class="glass-card p-4 stat-card border-0">
                    <div class="stat-icon bg-primary-soft text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">นักเรียนทั้งหมด</div>
                    <div class="h3 fw-bold mb-0 text-dark"><?= number_format($stats['total']) ?> ราย</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 stat-card border-0">
                    <div class="stat-icon bg-success-soft text-success">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">ผ่านการประเมินแล้ว</div>
                    <div class="h3 fw-bold mb-0 text-success"><?= number_format($stats['evaluated']) ?> ราย</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 stat-card border-0">
                    <div class="stat-icon bg-warning-soft text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">รอการประเมิน</div>
                    <div class="h3 fw-bold mb-0 text-warning"><?= number_format($stats['pending']) ?> ราย</div>
                </div>
            </div>
        </div>

        <!-- Main Table Container -->
        <div class="glass-card animate-up" style="animation-delay: 0.2s;">
            <div class="table-responsive p-4">
                <table id="certificatesListTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-3" width="120">รหัสนักเรียน</th>
                            <th class="ps-4">ชื่อ-นามสกุล</th>
                            <th>สถานประกอบการ</th>
                            <th class="text-center">รายงานที่ผ่าน</th>
                            <th class="text-center">เกรด</th>
                            <th class="text-center" width="150">สถานะ/ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $row): ?>
                        <tr>
                            <td class="ps-3 small fw-bold text-muted"><?= e($row['student_code']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($row['fullname']) ?></div>
                            </td>
                            <td class="small text-muted"><?= e($row['company_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark rounded-pill px-3 py-2 border">
                                    <i class="bi bi-file-earmark-check text-success me-1"></i>
                                    <?= (int)($row['total_approved'] ?? 0) ?> สัปดาห์
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if($row['grade']): ?>
                                    <div class="grade-badge"><?= e($row['grade']) ?></div>
                                <?php else: ?>
                                    <span class="x-small text-muted italic">รอประเมิน</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($row['grade'])): ?>
                                    <a href="generate_certificate.php?id=<?= (int)$row['id'] ?>" class="btn btn-success rounded-pill btn-sm px-3 shadow-sm">
                                        <i class="bi bi-printer me-1"></i> พิมพ์ใบประกาศ
                                    </a>
                                <?php else: ?>
                                    <span class="status-badge pending">รอผลประเมิน</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = $('#certificatesListTable');
    if (tbl.length && tbl.find('tbody tr').length) {
        var dtLang = { 
            search: '', 
            searchPlaceholder: 'ค้นหารายชื่อ...',
            lengthMenu: 'แสดง _MENU_ รายการ', 
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_', 
            infoEmpty: 'ไม่มีข้อมูล', 
            infoFiltered: '(กรองจาก _MAX_)', 
            paginate: { first: 'แรก', last: 'ท้าย', next: '<i class="bi bi-chevron-right"></i>', previous: '<i class="bi bi-chevron-left"></i>' }, 
            zeroRecords: 'ไม่พบข้อมูลที่ค้นหา' 
        };
        tbl.DataTable({ 
            order: [[0, 'asc']], 
            language: dtLang, 
            pageLength: 10, 
            columnDefs: [{ orderable: false, targets: [5] }] 
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>