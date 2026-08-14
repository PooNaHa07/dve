<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff', 'admin', 'director']);

$u      = current_user();
$msg    = '';
$msgType = 'success';

// ดึงข้อมูลสำหรับ dropdowns
$supervisors = [];
$res_sv = $conn->query("SELECT id, fullname, email, company_name FROM users WHERE role='supervisor' ORDER BY fullname ASC");
if ($res_sv) while ($row = $res_sv->fetch_assoc()) $supervisors[] = $row;

$students = [];
$res_st = $conn->query("SELECT id, fullname, student_code, classroom_id FROM users WHERE role='student' ORDER BY fullname ASC");
if ($res_st) while ($row = $res_st->fetch_assoc()) $students[] = $row;

// ดึงการมอบหมายทั้งหมดพร้อม details
$assignments = [];
$res_as = $conn->query("
    SELECT sa.id, sa.assigned_at, sa.assigned_by_role,
           sv.fullname AS supervisor_name, sv.email AS supervisor_email, sv.company_name AS supervisor_company,
           st.fullname AS student_name, st.student_code,
           ab.fullname AS assigned_by_name
    FROM supervisor_assignments sa
    JOIN users sv ON sa.supervisor_id = sv.id
    JOIN users st ON sa.student_id    = st.id
    LEFT JOIN users ab ON sa.assigned_by = ab.id
    ORDER BY sv.fullname ASC, sa.assigned_at DESC
");
if ($res_as) while ($row = $res_as->fetch_assoc()) $assignments[] = $row;

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/staff_style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
.sv-mgmt-card {
    background: #fff;
    border-radius: 1.5rem;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
    overflow: hidden;
    margin-bottom: 2rem;
}
.sv-mgmt-card-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg,#f0f9ff,#e0f2fe);
    border-bottom: 1px solid rgba(14,165,233,.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.form-add-card {
    background: linear-gradient(135deg,#f0f9ff,#e0f2fe);
    border: 1.5px solid rgba(14,165,233,.2);
    border-radius: 1.25rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
</style>

<div class="staff-dashboard-page pt-4">
  <div class="container">
    <div class="staff-page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-workspace text-primary me-2"></i>จัดการผู้ดูแลการฝึกงาน (Supervisor)
            </h4>
            <p class="text-muted small mb-0">มอบหมายหรือลบการดูแลนักเรียนให้กับผู้ดูแลการฝึกงาน</p>
        </div>
        <a href="../roles/staff.php" class="btn btn-outline-secondary rounded-pill bg-white shadow-sm border-opacity-25 px-4">
            ← แดชบอร์ด
        </a>
    </div>

    <!-- Add Assignment Form -->
    <div class="form-add-card">
        <h5 class="fw-bold mb-3" style="color:#0369a1;">
            <i class="bi bi-plus-circle-fill me-2"></i>เพิ่มการมอบหมายใหม่
        </h5>
        <form id="addAssignForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-primary mb-1">
                        <i class="bi bi-person-workspace me-1"></i>ผู้ดูแลการฝึกงาน (Supervisor)
                    </label>
                    <select name="supervisor_id" id="sv-select" class="form-select rounded-3" required>
                        <option value="">-- เลือก Supervisor --</option>
                        <?php foreach ($supervisors as $sv): ?>
                        <option value="<?= $sv['id'] ?>">
                            <?= htmlspecialchars($sv['fullname']) ?>
                            <?= !empty($sv['company_name']) ? ' [' . htmlspecialchars($sv['company_name']) . ']' : '' ?>
                            <?= !empty($sv['email']) ? ' (' . htmlspecialchars($sv['email']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($supervisors)): ?>
                    <div class="text-warning small mt-1">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        ยังไม่มี Supervisor ในระบบ กรุณาสร้าง user role=supervisor ก่อน
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-primary mb-1">
                        <i class="bi bi-person-fill me-1"></i>นักเรียน
                    </label>
                    <select name="student_id" id="st-select" class="form-select rounded-3" required>
                        <option value="">-- เลือกนักเรียน --</option>
                        <?php foreach ($students as $st): ?>
                        <option value="<?= $st['id'] ?>">
                            <?= htmlspecialchars($st['fullname']) ?>
                            <?= !empty($st['student_code']) ? ' (' . htmlspecialchars($st['student_code']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary rounded-pill fw-bold w-100" style="height:42px;">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่ม
                    </button>
                </div>
                <div class="col-md-2">
                    <span id="add-status" class="text-muted small"></span>
                </div>
            </div>
        </form>
    </div>

    <!-- Assignments Table -->
    <div class="sv-mgmt-card">
        <div class="sv-mgmt-card-header">
            <h5 class="mb-0 fw-bold" style="color:#0369a1;">
                <i class="bi bi-table me-2"></i>รายการมอบหมายทั้งหมด
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill ms-2" style="font-size:.75rem;">
                    <?= count($assignments) ?> รายการ
                </span>
            </h5>
        </div>
        <div class="table-responsive">
            <table id="assignTable" class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase fw-bold" style="font-size:.75rem;letter-spacing:.5px;">
                    <tr>
                        <th class="ps-4 py-3" style="width:60px;">ลำดับ</th>
                        <th class="py-3">Supervisor</th>
                        <th class="py-3">นักเรียน</th>
                        <th class="py-3">มอบหมายโดย</th>
                        <th class="py-3">วันที่มอบหมาย</th>
                        <th class="text-center py-3" style="width:100px;">ลบ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($assignments)): ?>
                <?php $i = 1; foreach ($assignments as $a): ?>
                <tr id="row-<?= $a['id'] ?>">
                    <td class="ps-4 text-secondary fw-bold"><?= $i++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:34px;height:34px;color:#0ea5e9;flex-shrink:0;">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:.9rem;"><?= htmlspecialchars($a['supervisor_name']) ?></div>
                                <?php if (!empty($a['supervisor_company'])): ?>
                                <div class="text-primary fw-semibold" style="font-size:.75rem;"><i class="bi bi-building me-1"></i><?= htmlspecialchars($a['supervisor_company']) ?></div>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($a['supervisor_email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:34px;height:34px;color:#10b981;flex-shrink:0;">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:.9rem;"><?= htmlspecialchars($a['student_name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($a['student_code'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill fw-bold" style="font-size:.72rem;
                            background:<?= $a['assigned_by_role'] === 'student' ? 'rgba(99,102,241,.1)' : 'rgba(14,165,233,.1)' ?>;
                            color:<?= $a['assigned_by_role'] === 'student' ? '#6366f1' : '#0ea5e9' ?>;">
                            <?= $a['assigned_by_role'] === 'student' ? '👤 นักเรียนเลือกเอง' : '🛡️ เจ้าหน้าที่' ?>
                        </span>
                        <?php if (!empty($a['assigned_by_name'])): ?>
                        <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($a['assigned_by_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($a['assigned_at'])) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                                onclick="removeAssignment(<?= $a['id'] ?>, this)"
                                style="font-size:.78rem;width:70px;">
                            <i class="bi bi-trash3 me-1"></i>ลบ
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>ยังไม่มีการมอบหมาย
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</div>

<script>
// Add Assignment
document.getElementById('addAssignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const svId = document.getElementById('sv-select').value;
    const stId = document.getElementById('st-select').value;
    const statusEl = document.getElementById('add-status');

    if (!svId || !stId) {
        statusEl.innerHTML = '<span class="text-danger">กรุณาเลือกให้ครบ</span>';
        return;
    }

    statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>กำลังบันทึก…</span>';

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('supervisor_id', svId);
    fd.append('student_id', stId);

    fetch('/DVE_DATA_FULL/staff/save_supervisor_assignment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>เพิ่มแล้ว</span>';
                setTimeout(() => location.reload(), 800);
            } else {
                statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (data.message || 'ผิดพลาด') + '</span>';
            }
        })
        .catch(() => { statusEl.innerHTML = '<span class="text-danger">เชื่อมต่อไม่ได้</span>'; });
});

// Remove Assignment
function removeAssignment(id, btn) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'การมอบหมายนี้จะถูกลบออกจากระบบ',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก',
        borderRadius: '1rem'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('assignment_id', id);
        fetch('/DVE_DATA_FULL/staff/save_supervisor_assignment.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + id);
                    if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .3s'; setTimeout(() => row.remove(), 300); }
                    Swal.fire('ลบแล้ว!', 'ลบการมอบหมายเรียบร้อย', 'success');
                } else {
                    Swal.fire('ผิดพลาด', data.message || 'ลบไม่สำเร็จ', 'error');
                }
            });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const tbl = document.getElementById('assignTable');
    if (tbl && !tbl.querySelector('tbody tr td[colspan]')) {
        $('#assignTable').DataTable({
            order: [[1, 'asc']],
            pageLength: 25,
            language: {
                search:'ค้นหา:', lengthMenu:'แสดง _MENU_ รายการ',
                info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},
                zeroRecords:'ไม่พบข้อมูล'
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
