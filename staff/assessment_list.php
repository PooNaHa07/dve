<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff', 'admin']);

// สร้างตาราง staff_evaluations ถ้ายังไม่มี
$check = @$conn->query("SHOW TABLES LIKE 'staff_evaluations'");
if ($check && $check->num_rows === 0) {
    $conn->query("CREATE TABLE staff_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        staff_id INT NOT NULL,
        term VARCHAR(50) DEFAULT NULL,
        score_work INT DEFAULT 0,
        score_report INT DEFAULT 0,
        score_behavior INT DEFAULT 0,
        total_score INT DEFAULT 0,
        grade VARCHAR(20) DEFAULT NULL,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$swal_type = '';
$swal_title = '';
$swal_text = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'grade_saved') {
    $swal_type = 'success';
    $swal_title = 'บันทึกข้อมูลแล้ว';
    $swal_text = 'บันทึกการประเมินและเกรดเรียบร้อย';
}

// Fetch stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN s.grade IS NOT NULL THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN s.grade IS NULL THEN 1 ELSE 0 END) as pending
    FROM users u
    LEFT JOIN staff_evaluations s ON u.id = s.student_id
    WHERE u.role = 'student'";
$stats_res = $conn->query($stats_sql);
$stats = $stats_res->fetch_assoc();

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
    }

    body {
        background: #f8fafc;
        background-attachment: fixed;
    }

    .bg-blobs {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
    }

    .blob {
        position: absolute;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.05) 100%);
        border-radius: 50%;
        filter: blur(80px);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
        transition: all 0.3s ease;
    }

    .stat-card {
        overflow: hidden;
        position: relative;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        transform: rotate(45deg);
    }

    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .bg-primary-soft { background: rgba(99, 102, 241, 0.1); }
    .bg-success-soft { background: rgba(16, 185, 129, 0.1); }
    .bg-warning-soft { background: rgba(245, 158, 11, 0.1); }

    .table thead th {
        background: rgba(241, 245, 249, 0.5);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        border: none;
        padding: 1.25rem 1rem;
    }

    .table tbody tr {
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    }

    .table tbody tr:last-child { border: none; }

    .grade-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.85rem;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.95rem;
        background: rgba(255, 255, 255, 0.8);
        color: #6366f1;
        border: 1px solid rgba(99, 102, 241, 0.2);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-badge.evaluated {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-badge.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .btn-evaluate {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 0.6rem 1.25rem;
        border-radius: 14px;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .btn-evaluate:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        color: white;
    }

    .animate-up {
        animation: slideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        opacity: 0;
    }

    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .dataTables_filter input {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 0.5rem 1rem;
        margin-left: 0.5rem;
    }
</style>

<div class="bg-blobs">
    <div class="blob" style="top: -10%; right: -10%;"></div>
    <div class="blob" style="bottom: 10%; left: -10%; width: 600px; height: 600px;"></div>
</div>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-up">
        <div>
            <h2 class="fw-bold text-dark mb-2">ประเมินผลเกรดนักเรียน</h2>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-white text-primary border rounded-pill px-3 py-2 shadow-sm">
                    <i class="bi bi-mortarboard-fill me-1"></i> ระบบงานทวิภาคี
                </span>
                <p class="text-muted small mb-0">จัดการคะแนนและสรุปผลประเมินรายภาคเรียน</p>
            </div>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="../roles/staff.php" class="btn btn-white glass-card border-0 rounded-pill px-4 py-2 text-dark shadow-sm">
                <i class="bi bi-arrow-left me-2"></i>กลับหน้าหลัก
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
                <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">นักเรียนฝึกงาน</div>
                <div class="h3 fw-bold mb-0 text-dark"><?= number_format($stats['total']) ?> ราย</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-4 stat-card border-0">
                <div class="stat-icon bg-success-soft text-success">
                    <i class="bi bi-check-all"></i>
                </div>
                <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">ประเมินผลแล้ว</div>
                <div class="h3 fw-bold mb-0 text-success"><?= number_format($stats['evaluated']) ?> ราย</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-4 stat-card border-0">
                <div class="stat-icon bg-warning-soft text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="small text-muted text-uppercase fw-bold ls-1 mb-1">รอดำเนินการ</div>
                <div class="h3 fw-bold mb-0 text-warning"><?= number_format($stats['pending']) ?> ราย</div>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="glass-card animate-up" style="animation-delay: 0.2s;">
        <div class="table-responsive p-4">
            <table id="assessmentTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th class="ps-3" style="width: 80px;">ลำดับ</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สถานประกอบการ</th>
                        <th class="text-center">เกรด (ครูนิเทศก์)</th>
                        <th class="text-center">เกรด (เจ้าหน้าที่)</th>
                        <th class="text-center">สถานะฝั่งเจ้าหน้าที่</th>
                        <th class="text-center" width="150">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT u.id, u.username, u.student_code, u.fullname, c.name as company_name,
                            e.grade AS teacher_grade,
                            s.grade AS staff_grade
                            FROM users u
                            LEFT JOIN companies c ON u.company_id = c.id
                            LEFT JOIN evaluations e ON u.id = e.student_id
                            LEFT JOIN staff_evaluations s ON u.id = s.student_id
                            WHERE u.role = 'student'
                            ORDER BY u.student_code ASC";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0):
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $has_staff_grade = !empty($row['staff_grade']);
                    ?>
                    <tr>
                        <td class="ps-3 text-secondary fw-bold">
                            <?= $i++ ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?= htmlspecialchars($row['student_code'] ?? '-') ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['fullname']) ?></div>
                        </td>
                        <td class="small text-muted"><?= $row['company_name'] ? htmlspecialchars($row['company_name']) : '-' ?></td>
                        <td class="text-center">
                            <?php if(!empty($row['teacher_grade'])): ?>
                                <div class="grade-badge"><?= htmlspecialchars($row['teacher_grade']) ?></div>
                            <?php else: ?>
                                <span class="text-muted small italic opacity-50">ไม่มีข้อมูล</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($has_staff_grade): ?>
                                <div class="grade-badge"><?= htmlspecialchars($row['staff_grade']) ?></div>
                            <?php else: ?>
                                <span class="text-muted small italic opacity-50">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($has_staff_grade): ?>
                                <span class="status-badge evaluated"><i class="bi bi-check2"></i> เรียบร้อย</span>
                            <?php else: ?>
                                <span class="status-badge pending"><i class="bi bi-exclamation-circle"></i> รอประเมิน</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" 
                                    class="btn btn-evaluate btn-sm w-100 btn-open-modal" 
                                    data-std-id="<?= (int)$row['id'] ?>">
                                <i class="bi <?= $has_staff_grade ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-1"></i>
                                <?= $has_staff_grade ? 'แก้ไขผล' : 'ประเมินเกรด' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Evaluation Modal -->
<div class="modal fade" id="evaluationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="evaluationForm" class="modal-content glass-card border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-check-fill text-primary me-2"></i>
                    ประเมินผลนักเรียน: <span id="modalStudentName" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="std_id" id="modalStdId">
                <div class="modal-body p-3 p-md-4">
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">กำลังโหลดข้อมูล...</p>
                    </div>

                    <div id="modalContent" style="display:none;">
                        <div class="row g-4">
                            <!-- Left: Form -->
                            <div class="col-lg-7">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">ภาคเรียน/ปีการศึกษา</label>
                                    <input type="text" name="term" id="modalTerm" class="form-control form-control-modern" placeholder="เช่น 2/2566">
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="score-card">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="small fw-bold text-muted mb-0">คะแนนปฏิบัติ (70 คะแนน)</label>
                                                <span class="badge bg-primary-soft text-primary rounded-pill">น้ำหนัก 70%</span>
                                            </div>
                                            <input type="number" name="score_work" id="modalScoreWork" class="score-input" min="0" max="70" required placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="score-card h-100">
                                            <label class="small fw-bold text-muted d-block mb-2 text-center text-truncate" title="สมุดบันทึก (20)">สมุดบันทึก (20)</label>
                                            <input type="number" name="score_report" id="modalScoreReport" class="score-input" min="0" max="20" required placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="score-card h-100">
                                            <label class="small fw-bold text-muted d-block mb-2 text-center text-truncate" title="จิตพิสัย (10)">จิตพิสัย (10)</label>
                                            <input type="number" name="score_behavior" id="modalScoreBehavior" class="score-input" min="0" max="10" required placeholder="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label small fw-bold text-muted">หมายเหตุ / Remarks</label>
                                    <textarea name="remarks" id="modalRemarks" class="form-control form-control-modern" rows="2" placeholder="ระบุข้อมูลเพิ่มเติม..."></textarea>
                                </div>
                            </div>

                            <!-- Right: Result -->
                            <div class="col-lg-5">
                                <div class="result-display h-100 d-flex flex-column justify-content-center align-items-center text-center p-3 p-md-4" 
                                     style="background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%); border-radius: 24px; color: white; min-height: 220px;">
                                    <div class="small opacity-75 mb-1">ผลคะแนนรวม</div>
                                    <div id="modalTotalScore" class="display-4 fw-bold mb-1">0</div>
                                    <div class="small opacity-75 mb-4">จาก 100 คะแนน</div>
                                    
                                    <div class="w-100 py-3 px-4 rounded-4" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(4px);">
                                        <div class="small opacity-75 mb-0">เกรดที่ได้รับ</div>
                                        <div id="modalGradeDisplay" class="display-5 fw-bold">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="modal-footer border-0 p-3 p-md-4 d-flex flex-column flex-md-row justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4 w-100 w-md-auto order-2 order-md-1 m-0" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold w-100 w-md-auto order-1 order-md-2 m-0">
                    <i class="bi bi-save2 me-2"></i> บันทึกผลการประเมิน
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .form-control-modern {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(226, 232, 240, 1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
    }
    .form-control-modern:focus {
        background: white; border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none;
    }
    .score-card {
        background: #f8fafc; border-radius: 20px; padding: 1.2rem;
        border: 1px solid #f1f5f9; transition: all 0.3s ease;
    }
    .score-card:focus-within {
        background: white; border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05); transform: translateY(-2px);
    }
    .score-input {
        font-size: 1.8rem; font-weight: 800; text-align: center;
        border: none; width: 100%; color: #1e293b; background: transparent;
    }
    .score-input:focus { outline: none; }
    .bg-primary-soft { background: rgba(99, 102, 241, 0.1); }
    @media (max-width: 576px) {
        .score-card { padding: 1rem; }
        .score-input { font-size: 1.5rem; }
        .result-display { min-height: 200px !important; }
        .result-display .display-4 { font-size: 2.5rem; }
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // DataTable Init
    var $t = $('#assessmentTable');
    if ($t.find('tbody tr').length > 0 && !$t.find('tbody tr:first td').attr('colspan')) {
        var table = $t.DataTable({
            pageLength: 15,
            order: [[1, 'asc']],
            columnDefs: [{ orderable: false, targets: [7] }],
            language: {
                search: '',
                searchPlaceholder: 'ค้นหารายชื่อ...',
                lengthMenu: 'แสดง _MENU_ รายการ',
                info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                paginate: { first: 'แรก', last: 'ท้าย', next: '<i class="bi bi-chevron-right"></i>', previous: '<i class="bi bi-chevron-left"></i>' },
                zeroRecords: 'ไม่พบข้อมูล'
            },
            drawCallback: function() {
                $('.dataTables_paginate > .pagination').addClass('pagination-sm');
            }
        });
    }

    const evalModal = new bootstrap.Modal(document.getElementById('evaluationModal'));
    
    // Open Modal
    $(document).on('click', '.btn-open-modal', function() {
        const stdId = $(this).data('std-id');
        $('#modalStdId').val(stdId);
        $('#modalContent').hide();
        $('#loadingState').show();
        evalModal.show();

        $.get('ajax_get_evaluation.php', { std_id: stdId }, function(res) {
            if (res.success) {
                $('#modalStudentName').text(res.student.fullname);
                $('#modalTerm').val(res.evaluation ? res.evaluation.term : '');
                $('#modalScoreWork').val(res.evaluation ? res.evaluation.score_work : '');
                $('#modalScoreReport').val(res.evaluation ? res.evaluation.score_report : '');
                $('#modalScoreBehavior').val(res.evaluation ? res.evaluation.score_behavior : '');
                $('#modalRemarks').val(res.evaluation ? res.evaluation.remarks : '');
                
                calculateGrade();
                
                $('#loadingState').hide();
                $('#modalContent').fadeIn();
            } else {
                Swal.fire('ผิดพลาด', res.error, 'error');
                evalModal.hide();
            }
        });
    });

    // Real-time calculation
    $('#modalScoreWork, #modalScoreReport, #modalScoreBehavior').on('input', function() {
        calculateGrade();
    });

    function calculateGrade() {
        const work = parseInt($('#modalScoreWork').val()) || 0;
        const report = parseInt($('#modalScoreReport').val()) || 0;
        const behavior = parseInt($('#modalScoreBehavior').val()) || 0;
        const total = work + report + behavior;
        
        $('#modalTotalScore').text(total);
        
        let grade = '-';
        if (total > 0) {
            if (total >= 80) grade = '4';
            else if (total >= 75) grade = '3.5';
            else if (total >= 70) grade = '3';
            else if (total >= 65) grade = '2.5';
            else if (total >= 60) grade = '2';
            else if (total >= 55) grade = '1.5';
            else if (total >= 50) grade = '1';
            else grade = '0';
        }
        
        $('#modalGradeDisplay').text(grade);
    }

    // Save Form
    $('#evaluationForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...');

        $.post('ajax_save_evaluation.php', $(this).serialize(), function(res) {
            if (res.success) {
                evalModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('ผิดพลาด', res.error, 'error');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
