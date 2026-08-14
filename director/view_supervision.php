<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['director', 'admin', 'staff', 'teacher']);

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';

$company_id_filter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$teacher_id_filter = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : -1;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if ($company_id_filter > 0) {
    $where_clauses[] = "sf.company_id = ?";
    $params[] = $company_id_filter;
    $types .= "i";
}

if ($teacher_id_filter > 0) {
    $where_clauses[] = "sf.teacher_id = ?";
    $params[] = $teacher_id_filter;
    $types .= "i";
}

if ($status_filter >= 0) {
    $where_clauses[] = "sf.status = ?";
    $params[] = $status_filter;
    $types .= "i";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch summary counts (Combined into single query for speed)
$summary_row = $conn->query("SELECT 
    COUNT(*) as c_all,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as c_st0,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as c_st1,
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as c_st2
FROM supervision_files")->fetch_assoc();

$count_all = (int)($summary_row['c_all'] ?? 0);
$count_st0 = (int)($summary_row['c_st0'] ?? 0);
$count_st1 = (int)($summary_row['c_st1'] ?? 0);
$count_st2 = (int)($summary_row['c_st2'] ?? 0);

// Main Query
$sql = "SELECT sf.id, sf.uploaded_at, sf.director_signed_at, sf.status, sf.file_path, sf.signed_file, sf.staff_signed_file,
               u.fullname AS teacher_name,
               std.fullname AS student_name, std.student_code, sf.company_id,
               comp.name AS company_name,
               c.class_name
        FROM supervision_files sf
        JOIN users u ON sf.teacher_id = u.id
        LEFT JOIN users std ON sf.student_id = std.id
        LEFT JOIN companies comp ON sf.company_id = comp.id
        LEFT JOIN classrooms c ON std.classroom_id = c.id
        {$where_sql}
        ORDER BY sf.uploaded_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

// Fetch selected company info if filtered
$selected_company_name = '';
if ($company_id_filter > 0) {
    $c_stmt = $conn->prepare("SELECT name FROM companies WHERE id = ?");
    $c_stmt->bind_param("i", $company_id_filter);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    if ($c_row = $c_res->fetch_assoc()) {
        $selected_company_name = $c_row['name'];
    }
}

// Fetch selected teacher info if filtered
$selected_teacher_name = '';
if ($teacher_id_filter > 0) {
    $t_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $t_stmt->bind_param("i", $teacher_id_filter);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result();
    if ($t_row = $t_res->fetch_assoc()) {
        $selected_teacher_name = $t_row['fullname'];
    }
}
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dp-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"
         style="background: linear-gradient(135deg, #312e81 0%, #4f46e5 50%, #7c3aed 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">📊</div>
            <div>
                <h4>รายงานการนิเทศก์</h4>
                <p>
                    ภาพรวมและติดตามรายงานการนิเทศก์ทั้งหมด
                    <?= !empty($selected_company_name) ? ' • กรองโดยสถานประกอบการ: <strong>' . htmlspecialchars($selected_company_name) . '</strong>' : '' ?>
                    <?= !empty($selected_teacher_name) ? ' • กรองโดยครูนิเทศก์: <strong>' . htmlspecialchars($selected_teacher_name) . '</strong>' : '' ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($company_id_filter > 0): ?>
            <a href="../companies/list.php" class="dp-back-btn text-white bg-white bg-opacity-20">
                <i class="bi bi-building"></i> หน้ารายชื่อสถานประกอบการ
            </a>
            <?php endif; ?>
            <a href="../roles/director.php" class="dp-back-btn">
                <i class="bi bi-house-fill"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    <!-- Stat Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="p-3 bg-white rounded-4 shadow-sm border border-opacity-10 d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 fs-4"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div>
                    <div class="fs-4 fw-extrabold text-dark"><?= number_format($count_all) ?></div>
                    <div class="small text-muted fw-bold">รายงานนิเทศทั้งหมด</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 bg-white rounded-4 shadow-sm border border-opacity-10 d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 fs-4"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="fs-4 fw-extrabold text-dark"><?= number_format($count_st0) ?></div>
                    <div class="small text-muted fw-bold">รอเจ้าหน้าที่ตรวจสอบ</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 bg-white rounded-4 shadow-sm border border-opacity-10 d-flex align-items-center gap-3">
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 fs-4"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <div class="fs-4 fw-extrabold text-dark"><?= number_format($count_st1) ?></div>
                    <div class="small text-muted fw-bold">รอผู้บริหารลงนาม</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 bg-white rounded-4 shadow-sm border border-opacity-10 d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-3 fs-4"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="fs-4 fw-extrabold text-dark"><?= number_format($count_st2) ?></div>
                    <div class="small text-muted fw-bold">ลงนามสมบูรณ์แล้ว</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="dp-card mb-4 p-3">
        <form method="GET" action="view_supervision.php" class="row g-3 align-items-center">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary small mb-1"><i class="bi bi-building me-1 text-primary"></i> กรองตามสถานประกอบการ</label>
                <select name="company_id" class="form-select rounded-3" onchange="this.form.submit()">
                    <option value="0">-- แสดงสถานประกอบการทั้งหมด --</option>
                    <?php
                    $comp_list = $conn->query("SELECT id, name FROM companies ORDER BY name ASC");
                    if ($comp_list) {
                        while ($cp = $comp_list->fetch_assoc()) {
                            $sel = ($company_id_filter === (int)$cp['id']) ? 'selected' : '';
                            echo '<option value="' . $cp['id'] . '" ' . $sel . '>' . htmlspecialchars($cp['name']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary small mb-1"><i class="bi bi-person-badge me-1 text-primary"></i> กรองตามครูนิเทศก์</label>
                <select name="teacher_id" class="form-select rounded-3" onchange="this.form.submit()">
                    <option value="0">-- แสดงครูนิเทศก์ทั้งหมด --</option>
                    <?php
                    $teacher_list = $conn->query("SELECT DISTINCT u.id, u.fullname FROM supervision_files sf JOIN users u ON sf.teacher_id = u.id ORDER BY u.fullname ASC");
                    if ($teacher_list) {
                        while ($t = $teacher_list->fetch_assoc()) {
                            $sel = ($teacher_id_filter === (int)$t['id']) ? 'selected' : '';
                            echo '<option value="' . $t['id'] . '" ' . $sel . '>' . htmlspecialchars($t['fullname']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold text-secondary small mb-1"><i class="bi bi-funnel me-1 text-primary"></i> กรองตามสถานะ</label>
                <select name="status" class="form-select rounded-3" onchange="this.form.submit()">
                    <option value="-1" <?= $status_filter === -1 ? 'selected' : '' ?>>-- แสดงทุกสถานะ --</option>
                    <option value="0" <?= $status_filter === 0 ? 'selected' : '' ?>>รอเจ้าหน้าที่ตรวจ (Status 0)</option>
                    <option value="1" <?= $status_filter === 1 ? 'selected' : '' ?>>รอผู้บริหารลงนาม (Status 1)</option>
                    <option value="2" <?= $status_filter === 2 ? 'selected' : '' ?>>ลงนามสมบูรณ์แล้ว (Status 2)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2" style="margin-top: 30px;">
                <?php if ($company_id_filter > 0 || $teacher_id_filter > 0 || $status_filter >= 0): ?>
                    <a href="view_supervision.php" class="btn btn-outline-secondary rounded-3 w-100 fw-bold">
                        <i class="bi bi-x-circle me-1"></i> ล้างตัวกรอง
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="dp-card">
        <div class="dp-card-header d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-table text-primary me-2"></i>
                รายการรายงานการนิเทศก์
            </div>
        </div>
        <div class="table-responsive">
            <table id="viewSupervisionTable" class="dp-table table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start" style="width: 70px;">ลำดับ</th>
                        <th>วันที่ยื่นเรื่อง</th>
                        <th class="text-start">ครูนิเทศก์</th>
                        <th class="text-start">สถานประกอบการ</th>
                        <th class="text-center">สถานะเอกสาร</th>
                        <th class="text-center" style="width: 140px;">จัดการ / เอกสาร</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($res && $res->num_rows > 0):
                    $i = 1;
                    while ($r = $res->fetch_assoc()):
                        $st = (int)$r['status'];
                        $signed_url = !empty($r['signed_file'])
                            ? '../uploads/supervision_docs/signed/' . htmlspecialchars($r['signed_file'])
                            : (!empty($r['file_path']) ? '../uploads/supervision_docs/original/' . htmlspecialchars($r['file_path']) : '');
                ?>
                    <tr>
                        <td class="text-start text-secondary fw-bold"><?= $i++ ?></td>
                        <td class="text-nowrap text-muted small" data-order="<?= strtotime($r['uploaded_at']) ?>">
                            <?= date('d/m/Y H:i', strtotime($r['uploaded_at'])) ?>
                        </td>
                        <td class="text-start">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,0.1);color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0;">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($r['teacher_name']) ?></span>
                            </div>
                        </td>
                        <td class="text-start">
                            <?php if (!empty($r['company_name'])): ?>
                                <div class="fw-medium text-dark"><i class="bi bi-briefcase me-1 text-secondary"></i><?= htmlspecialchars($r['company_name']) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($st === 0): ?>
                                <span class="dp-badge dp-badge-warning"><i class="bi bi-hourglass-split me-1"></i>รอเจ้าหน้าที่ตรวจ</span>
                            <?php elseif ($st === 1): ?>
                                <span class="dp-badge dp-badge-info"><i class="bi bi-pencil-square me-1"></i>รอผู้บริหารลงนาม</span>
                            <?php elseif ($st === 2): ?>
                                <span class="dp-badge dp-badge-success"><i class="bi bi-check-circle-fill me-1"></i>ลงนามสมบูรณ์แล้ว</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <?php if ($st === 1 && get_current_role() === 'director'): ?>
                                    <a href="sign_supervision_form.php?id=<?= $r['id'] ?>&return_to=view_supervision" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm" style="font-size:0.78rem;">
                                        <i class="bi bi-pen-fill me-1"></i>ลงนาม
                                    </a>
                                <?php elseif ($signed_url): ?>
                                    <a href="<?= $signed_url ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size:0.78rem;">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>เปิดไฟล์
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="dp-empty py-4">
                                <i class="bi bi-folder2-open fs-1 text-muted opacity-50 d-block mb-2"></i>
                                <p class="text-muted mb-0">ไม่พบข้อมูลรายงานการนิเทศก์ตามเงื่อนไขที่เลือก</p>
                            </div>
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
    var dtLang = {
        search: 'ค้นหา:',
        lengthMenu: 'แสดง _MENU_ รายการ',
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
        infoEmpty: 'ไม่มีข้อมูล',
        infoFiltered: '(กรองจาก _MAX_ รายการ)',
        paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
        zeroRecords: 'ไม่พบข้อมูล'
    };
    var tbl = document.getElementById('viewSupervisionTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var groupColumn = 2; // Index of Teacher Name column
        var table = $(tbl).DataTable({
            columnDefs: [
                { visible: false, targets: groupColumn }
            ],
            order: [[groupColumn, 'asc']],
            language: dtLang,
            paging: false,
            drawCallback: function(settings) {
                var api = this.api();
                var rows = api.rows({ page: 'current' }).nodes();
                var last = null;
                var groupCount = {};

                // Count items per group first
                api.column(groupColumn, { page: 'current' })
                    .data()
                    .each(function(group, i) {
                        groupCount[group] = (groupCount[group] || 0) + 1;
                    });

                api.column(groupColumn, { page: 'current' })
                    .data()
                    .each(function(group, i) {
                        if (last !== group) {
                            var count = groupCount[group] || 0;
                            // Extract raw text from the group HTML to prevent nested markup layout wrapping
                            var rawName = $('<div>' + group + '</div>').text().trim();
                            
                            var groupRow = $('<tr class="group-header" style="background: #f8fafc; border-left: 5px solid #4f46e5; cursor: pointer; user-select: none;" data-group="' + encodeURIComponent(group) + '">' +
                                '<td colspan="5" class="py-3 px-3">' +
                                '<div class="d-flex align-items-center justify-content-between">' +
                                '<div class="d-flex align-items-center gap-2">' +
                                '<i class="bi bi-chevron-down text-muted transition-transform me-1" style="font-size: 0.95rem;"></i>' +
                                '<div style="width:34px;height:34px;border-radius:10px;background:rgba(99,102,241,0.1);color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">' +
                                '<i class="bi bi-person-workspace"></i>' +
                                '</div>' +
                                '<span class="fw-bold text-dark fs-5">' + rawName + '</span>' +
                                '</div>' +
                                '<span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-2" style="font-size: 0.8rem; font-weight:700;">' + count + ' รายการ</span>' +
                                '</div>' +
                                '</td>' +
                                '</tr>');
                            $(rows).eq(i).before(groupRow);
                            last = group;
                        }
                        // Tag each child row with its group
                        $(rows).eq(i).attr('data-group-child', encodeURIComponent(group));
                        
                        // Add a padding to the first cell of child rows
                        $(rows).eq(i).find('td:first-child').css('padding-left', '1.5rem');
                    });
            }
        });

        // Toggle collapse/expand on group header click
        $(tbl).on('click', 'tr.group-header', function() {
            var group = $(this).attr('data-group');
            var children = $(this).siblings('[data-group-child="' + group + '"]');
            children.toggle();
            
            var icon = $(this).find('.bi-chevron-down, .bi-chevron-right');
            if (icon.hasClass('bi-chevron-down')) {
                icon.removeClass('bi-chevron-down').addClass('bi-chevron-right');
            } else {
                icon.removeClass('bi-chevron-right').addClass('bi-chevron-down');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
