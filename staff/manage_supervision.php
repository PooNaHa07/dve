<?php
require_once __DIR__ . '/../includes/functions.php';
\App\Helpers\Auth::guard(['staff', 'admin']);

include __DIR__ . '/../includes/header.php';

$sql = "SELECT f.*, s.fullname as student_name, s.student_code, t.fullname as teacher_name 
        FROM supervision_files f
        JOIN users t ON f.teacher_id = t.id
        LEFT JOIN users s ON f.student_id = s.id
        ORDER BY f.uploaded_at DESC";

$res = \App\Helpers\Database::query($sql)->get_result();
?>
<link rel="stylesheet" href="../includes/staff_style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="staff-dashboard-page pt-4">
    <div class="container">
        <?php \App\Helpers\Flash::render(); ?>

        <div class="staff-page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-primary me-2"></i>ตรวจสอบและเสนอใบนิเทศ</h4>
                <p class="text-muted small mb-0">เอกสารรอการตรวจสอบจากเจ้าหน้าที่และเตรียมส่งต่อให้ผู้บริหารลงนาม</p>
            </div>
            <a href="../roles/staff.php" class="btn btn-outline-secondary rounded-pill bg-white shadow-sm border-opacity-25 px-4">← แดชบอร์ด</a>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-secondary d-flex align-items-center gap-2"><i class="bi bi-list-ul text-primary"></i> รายการรอการตรวจสอบ</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableSupervision" style="border-collapse: separate;">
                        <thead class="bg-light text-secondary small text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="ps-4 py-3" style="width: 80px;">ลำดับ</th>
                                <th class="py-3">วันที่ส่ง</th>
                                <th class="py-3">ครูผู้ส่ง</th>
                                <th class="py-3">รหัสนักศึกษา</th>
                                <th class="py-3">นักเรียน</th>
                                <th class="py-3">ไฟล์เอกสาร</th>
                                <th class="text-center py-3">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res && $res->num_rows > 0): ?>
                                <?php 
                                $i = 1;
                                while($row = $res->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td class="ps-4 text-secondary fw-bold"><?= $i++ ?></td>
                                        <td data-order="<?= strtotime($row['uploaded_at']) ?>"><span class="text-muted small"><?= date('d/m/Y', strtotime($row['uploaded_at'])) ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px; color: var(--staff-primary);"><i class="bi bi-person-badge"></i></div>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['teacher_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?= htmlspecialchars($row['student_code'] ?? '-') ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px; color: var(--staff-primary);"><i class="bi bi-person"></i></div>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['student_name'] ?? '-') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['file_path'])): ?>
                                            <a href="../uploads/supervision_docs/original/<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill text-info fw-bold shadow-xs">
                                                <i class="bi bi-eye-fill me-1"></i> ดูไฟล์
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted small italic">ออนไลน์</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php $st = \App\Enums\SupervisionStatus::tryFrom((int)$row['status']) ?? \App\Enums\SupervisionStatus::PendingStaff; ?>
                                            <span class="badge badge-modern bg-<?= $st->color() ?> bg-opacity-10 text-<?= $st->color() ?> border-0">
                                                <?= $st->label() ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>ไม่พบรายการใบนิเทศ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


    </div>
</div>

<style>
    #tableSupervision tbody tr { transition: background-color 0.2s; }
    #tableSupervision tbody tr:hover { background-color: #f8fafc !important; }
    .shadow-xs { box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
</style>

<script>

document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('tableSupervision');
    if (table && table.querySelector('tbody tr td[colspan]') === null) {
        $('#tableSupervision').DataTable({
            order: [[1, 'desc']],
            language: {
                search: 'ค้นหา:',
                lengthMenu: 'แสดง _MENU_ รายการ',
                info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
                zeroRecords: 'ไม่พบข้อมูล'
            },
            pageLength: 15
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

