<?php
// reports/staff_report_view.php - Modernized & Organized
require_once __DIR__ . '/../includes/functions.php';

// 1. Role Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ../login.php");
    exit();
}

// 2. Handle Filters & Search
$search = $_GET['search'] ?? '';
$week_filter = $_GET['week'] ?? '';

// 3. Data Fetching Logic
$sql = "SELECT d.*, s.fullname, s.student_code, s.profile_image, 
               WEEK(d.date_work, 1) AS week_no,
               d.details AS work_details
        FROM daily_reports d 
        JOIN users s ON d.student_id = s.id 
        WHERE 1=1";

if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $sql .= " AND (s.fullname LIKE '%$search_safe%' OR s.student_code LIKE '%$search_safe%' OR s.username LIKE '%$search_safe%')";
}

if (!empty($week_filter)) {
    $week_safe = (int)$week_filter;
    $sql .= " AND WEEK(d.date_work, 1) = $week_safe";
}

$sql .= " ORDER BY d.date_work DESC, s.student_code ASC, d.created_at DESC";
$result = $conn->query($sql);
$rows = [];
$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $stats['total']++;
        if($row['status'] === 'approved' || $row['status'] === '1') $stats['approved']++;
        elseif($row['status'] === 'rejected' || $row['status'] === '2') $stats['rejected']++;
        else $stats['pending']++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page py-4">
    <div class="container-fluid px-4">
        <!-- Background Decor -->
        <div class="bg-blob"></div>
        <div class="bg-blob secondary"></div>
        
        <!-- A. PRINT HEADER (Hidden on Screen) -->
        <div class="d-none d-print-block mb-4">
            <div class="text-center border-bottom pb-4 mb-4">
                <img src="../images/logo.png" style="width: 80px; height: 80px;" class="mb-3">
                <h4 class="fw-bold mb-1">สรุปรายงานการปฏิบัติงานประจำสัปดาห์</h4>
                <p class="text-muted mb-0">ระบบจัดการงานฝึกระสบการณ์ทวิภาคี (DVE SYSTEM)</p>
                <div class="small mt-2">พิมพ์เมื่อวันที่: <?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <!-- B. PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4 animate-slide-up no-print">
            <div>
                <h3 class="fw-bold text-dark mb-1">รายงานการปฏิบัติงาน</h3>
                <p class="text-muted mb-0">ตรวจสอบและติดตามการฝึกงานของนักเรียนนักศึกษา</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-white rounded-pill px-4 shadow-sm border-0">
                    <i class="bi bi-printer me-2 text-primary"></i> พิมพ์หน้านี้
                </button>
            </div>
        </div>

        <!-- C. STATS OVERVIEW (Quick View) -->
        <div class="row g-3 mb-4 animate-slide-up no-print" style="animation-delay: 0.1s;">
            <div class="col-md-3">
                <div class="glass-card p-4 border-0 position-relative overflow-hidden h-100">
                    <div class="d-flex align-items-center justify-content-between position-relative z-1">
                        <div>
                            <div class="small text-muted mb-1 text-uppercase fw-bold ls-1">รายงานทั้งหมด</div>
                            <div class="h2 fw-bold mb-0 text-primary"><?= number_format($stats['total']) ?></div>
                        </div>
                        <div class="stat-icon bg-primary-soft text-primary">
                            <i class="bi bi-files fs-3"></i>
                        </div>
                    </div>
                    <div class="stat-progress mt-3">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-4 border-0 position-relative overflow-hidden h-100">
                    <div class="d-flex align-items-center justify-content-between position-relative z-1">
                        <div>
                            <div class="small text-muted mb-1 text-uppercase fw-bold ls-1">อนุมัติแล้ว</div>
                            <div class="h2 fw-bold mb-0 text-success"><?= number_format($stats['approved']) ?></div>
                        </div>
                        <div class="stat-icon bg-success-soft text-success">
                            <i class="bi bi-check-all fs-3"></i>
                        </div>
                    </div>
                    <div class="stat-progress mt-3">
                        <?php $approved_pct = $stats['total'] > 0 ? ($stats['approved'] / $stats['total']) * 100 : 0; ?>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: <?= $approved_pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-4 border-0 position-relative overflow-hidden h-100">
                    <div class="d-flex align-items-center justify-content-between position-relative z-1">
                        <div>
                            <div class="small text-muted mb-1 text-uppercase fw-bold ls-1">รอตรวจสอบ</div>
                            <div class="h2 fw-bold mb-0 text-warning"><?= number_format($stats['pending']) ?></div>
                        </div>
                        <div class="stat-icon bg-warning-soft text-warning">
                            <i class="bi bi-clock-history fs-3"></i>
                        </div>
                    </div>
                    <div class="stat-progress mt-3">
                        <?php $pending_pct = $stats['total'] > 0 ? ($stats['pending'] / $stats['total']) * 100 : 0; ?>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: <?= $pending_pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-4 border-0 position-relative overflow-hidden h-100">
                    <div class="d-flex align-items-center justify-content-between position-relative z-1">
                        <div>
                            <div class="small text-muted mb-1 text-uppercase fw-bold ls-1">ให้แก้ไข</div>
                            <div class="h2 fw-bold mb-0 text-danger"><?= number_format($stats['rejected']) ?></div>
                        </div>
                        <div class="stat-icon bg-danger-soft text-danger">
                            <i class="bi bi-exclamation-triangle fs-3"></i>
                        </div>
                    </div>
                    <div class="stat-progress mt-3">
                        <?php $rejected_pct = $stats['total'] > 0 ? ($stats['rejected'] / $stats['total']) * 100 : 0; ?>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-danger" style="width: <?= $rejected_pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- D. SEARCH & FILTERS -->
        <div class="glass-card mb-4 p-4 animate-slide-up no-print" style="animation-delay: 0.15s;">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-5">
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="search" class="form-control form-control-premium" 
                               placeholder="ค้นหาชื่อ หรือ รหัสนักเรียน..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="week" class="form-select form-select-premium">
                        <option value="">ทุกสัปดาห์</option>
                        <?php for($i=1; $i<=20; $i++): ?>
                            <option value="<?= $i ?>" <?= $week_filter == $i ? 'selected' : '' ?>>สัปดาห์ที่ <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 flex-grow-1 shadow-sm fw-bold">
                        <i class="bi bi-filter me-2"></i> กรองข้อมูล
                    </button>
                    <?php if(!empty($search) || !empty($week_filter)): ?>
                        <a href="staff_report_view.php" class="btn btn-light rounded-pill px-4 border fw-bold text-muted">ล้าง</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- E. MAIN DATA TABLE -->
        <div class="glass-card animate-slide-up overflow-hidden mb-5" style="animation-delay: 0.2s;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th class="ps-4 border-0 text-muted x-small fw-bold text-uppercase ls-1" width="80">สัปดาห์</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1" width="180">นักเรียน</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1 text-center" width="100">วันที่</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1">รายละเอียดงาน</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1">ปัญหา/อุปสรรค</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1 text-center" width="110">สถานะ</th>
                            <th class="border-0 text-muted x-small fw-bold text-uppercase ls-1 text-end pe-4 no-print" width="100">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): foreach($rows as $row): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-primary-soft text-primary rounded-pill w-100 py-2">
                                    <?= $row['week_no'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small mb-0"><?= e($row['fullname']) ?></div>
                                <div class="x-small text-muted"><?= e($row['student_code']) ?></div>
                            </td>
                            <td class="text-center small"><?= date('d/m/y', strtotime($row['date_work'])) ?></td>
                            <td>
                                <div class="text-wrap small text-muted" style="max-width: 300px; min-width: 150px;">
                                    <?= nl2br(e($row['work_details'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-wrap x-small text-danger" style="max-width: 200px; min-width: 120px;">
                                    <?= !empty($row['problems']) ? nl2br(e($row['problems'])) : '-' ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php 
                                if($row['status'] === 'approved' || $row['status'] === '1'): 
                                    echo '<span class="status-badge approved">อนุมัติแล้ว</span>';
                                elseif($row['status'] === 'rejected' || $row['status'] === '2'): 
                                    echo '<span class="status-badge rejected">ให้แก้ไข</span>';
                                else: 
                                    echo '<span class="status-badge pending">รอตรวจ</span>';
                                endif;
                                ?>
                            </td>
                            <td class="text-end pe-4 no-print">
                                <div class="btn-group shadow-sm rounded-pill overflow-hidden border">
                                    <button class="btn btn-white btn-sm px-3" data-bs-toggle="modal" data-bs-target="#reportModal<?= $row['id'] ?>">
                                        <i class="bi bi-eye text-primary"></i>
                                    </button>
                                    <a href="../admin/print_report.php?student_id=<?= (int)$row['student_id'] ?>" target="_blank" class="btn btn-white btn-sm px-3">
                                        <i class="bi bi-printer text-secondary"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                ไม่พบข้อมูลที่ต้องการ
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- F. PRINT FOOTER (Signatures) -->
            <div class="d-none d-print-block mt-5 pt-5 pb-4">
                <div class="row text-center g-5">
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <div class="fw-bold">ผู้รับผิดชอบงานทวิภาคี</div>
                        <div class="small text-muted">ผู้จัดทำรายงาน</div>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <div class="fw-bold">ครูนิเทศก์</div>
                        <div class="small text-muted">ผู้ตรวจสอบข้อมูล</div>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <div class="fw-bold">รองผู้อำนวยการฝ่ายวิชาการ</div>
                        <div class="small text-muted">ผู้อนุมัติผล</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- G. MODALS (RENDERED AT ROOT) -->
<?php foreach($rows as $row): ?>
<div class="modal fade no-print" id="reportModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card shadow-lg border-0 rounded-4">
            <div class="modal-header border-bottom bg-light-subtle px-4 py-3 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-file-text text-primary me-2"></i> รายละเอียดรายงาน
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Info Header -->
                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-4 border shadow-sm h-100 position-relative overflow-hidden">
                            <div class="position-relative z-1">
                                <label class="x-small text-muted fw-bold d-block mb-1 text-uppercase ls-1">นักเรียน</label>
                                <div class="fw-bold text-dark h6 mb-0"><?= e($row['fullname']) ?></div>
                                <div class="small text-muted"><?= e($row['student_code']) ?></div>
                            </div>
                            <i class="bi bi-person-badge position-absolute end-0 bottom-0 mb-n2 me-n2 opacity-10" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded-4 border shadow-sm h-100 text-center">
                            <label class="x-small text-muted fw-bold d-block mb-1 text-uppercase ls-1">สัปดาห์</label>
                            <div class="h4 mb-0 fw-bold text-primary"><?= $row['week_no'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded-4 border shadow-sm h-100 text-center">
                            <label class="x-small text-muted fw-bold d-block mb-1 text-uppercase ls-1">สถานะ</label>
                            <div class="mt-1">
                                <?php if($row['status'] === 'approved' || $row['status'] === '1'): ?>
                                    <span class="status-badge approved">อนุมัติแล้ว</span>
                                <?php elseif($row['status'] === 'rejected' || $row['status'] === '2'): ?>
                                    <span class="status-badge rejected">ให้แก้ไข</span>
                                <?php else: ?>
                                    <span class="status-badge pending">รอตรวจสอบ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Details -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center">
                        <span class="bg-primary-soft text-primary p-2 rounded-3 me-2 d-flex">
                            <i class="bi bi-journal-text fs-5"></i>
                        </span>
                        รายละเอียดการปฏิบัติงาน
                    </h6>
                    <div class="p-4 bg-white border rounded-4 shadow-sm" style="min-height: 120px; line-height: 1.6;">
                        <?= nl2br(e($row['work_details'])) ?>
                    </div>
                </div>
                
                <div class="mb-2">
                    <h6 class="fw-bold mb-3 d-flex align-items-center">
                        <span class="bg-danger-soft text-danger p-2 rounded-3 me-2 d-flex">
                            <i class="bi bi-exclamation-triangle fs-5"></i>
                        </span>
                        ปัญหาและอุปสรรค
                    </h6>
                    <div class="p-4 bg-light rounded-4 border" style="min-height: 80px; line-height: 1.6;">
                        <?php if(!empty($row['problems'])): ?>
                            <?= nl2br(e($row['problems'])) ?>
                        <?php else: ?>
                            <div class="text-muted text-center py-2 italic small">
                                <i class="bi bi-check2-circle me-1"></i> ไม่มีการระบุปัญหาหรืออุปสรรค
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 border" data-bs-dismiss="modal">ปิด</button>
                <a href="../admin/print_report.php?student_id=<?= (int)$row['student_id'] ?>" target="_blank" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-printer me-2"></i> พิมพ์ใบงานนี้
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<style>
/* 1. Global Utilities */
.x-small { font-size: 0.75rem; }
.ls-1 { letter-spacing: 0.5px; }
.bg-primary-soft { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.05) 100%) !important; }
.bg-success-soft { background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%) !important; }
.bg-warning-soft { background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%) !important; }
.bg-danger-soft { background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%) !important; }
.bg-light-subtle { background-color: rgba(0, 0, 0, 0.02) !important; }

/* 2. Stat Cards */
.stat-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 8px 16px rgba(0,0,0,0.03);
}
.glass-card {
    border: 1px solid rgba(255, 255, 255, 0.7) !important;
    box-shadow: 0 10px 30px rgba(31, 38, 135, 0.04) !important;
}
.glass-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(31, 38, 135, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.3) !important;
}
.glass-card:hover .stat-icon {
    transform: scale(1.1) rotate(8deg);
}
.progress {
    background-color: rgba(0, 0, 0, 0.04);
    border-radius: 10px;
    overflow: hidden;
    height: 6px !important;
}

/* 3. Status Badges */
.status-badge {
    display: inline-block;
    padding: 0.45rem 1.2rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
}
.status-badge.approved { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
.status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
.status-badge.rejected { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }

/* 4. Search & Filters */
.form-select-premium, .form-control-premium {
    background: white !important;
    border: 1px solid rgba(0,0,0,0.08) !important;
    border-radius: 12px !important;
    padding: 0.6rem 1rem !important;
    font-size: 0.9rem !important;
    transition: all 0.3s ease !important;
}
.form-select-premium:focus, .form-control-premium:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1) !important;
    transform: translateY(-1px);
}
.search-box { position: relative; }
.search-icon { position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: #64748b; z-index: 5; }
.search-box .form-control-premium { padding-left: 3rem !important; }

/* 5. Glassmorphism Fixes */
.btn-white { background: white; transition: all 0.2s; border: 1px solid rgba(0,0,0,0.05); }
.btn-white:hover { background: #f8fafc; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

/* 6. Print Layout */
.signature-line { border-bottom: 1px solid #000; width: 180px; margin: 40px auto 10px; }
@media print {
    body { background: white !important; padding: 0 !important; }
    .no-print, .premium-nav, .bg-blob, .welcome-banner, .landing-waves, .landing-footer { display: none !important; }
    .main-content-container { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; border: none !important; }
    .glass-card { border: none !important; box-shadow: none !important; background: transparent !important; border: 1px solid #eee !important; border-radius: 0 !important; }
    .table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 10px 8px !important; color: black !important; font-size: 9pt !important; vertical-align: top !important; word-break: break-word !important; }
    .table th { background-color: #f8fafc !important; -webkit-print-color-adjust: exact !important; }
    .text-wrap { max-width: none !important; width: auto !important; }
    .status-badge { border: 1px solid #ccc !important; padding: 2px 8px !important; display: inline-block !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>