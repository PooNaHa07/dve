<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id'];

// --- 1. ดึงรายการห้องเรียนที่ครูรับผิดชอบ ---
$room_ids = [];
$rooms_list = [];
$res_my_rooms = $conn->query("SELECT ta.classroom_id, cl.class_name FROM teacher_assignments ta LEFT JOIN classrooms cl ON ta.classroom_id = cl.id WHERE ta.teacher_id = '$teacher_id' ORDER BY cl.class_name ASC");
while($r = $res_my_rooms->fetch_assoc()) {
    $room_ids[] = $r['classroom_id'];
    $rooms_list[] = $r;
}

if (!empty($room_ids)) {
    $ids_string = implode(',', array_map('intval', $room_ids));
    $filter_where = " AND u.classroom_id IN ($ids_string)";
} else {
    $filter_where = " AND 1 = 0";
}

// กรองตามห้องที่เลือก (แยกห้อง)
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id > 0) {
    if (in_array($class_id, $room_ids)) {
        $filter_where = " AND u.classroom_id = " . $class_id;
    }
}

// กรองตามสถานะการส่งรายงาน
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'all';
$allowed_filters = ['all', 'pending', 'rejected', 'completed'];
if (!in_array($status_filter, $allowed_filters)) {
    $status_filter = 'all';
}

$status_having = "";
if ($status_filter === 'pending') {
    $status_having = " HAVING pending_reports > 0";
} elseif ($status_filter === 'rejected') {
    $status_having = " HAVING rejected_reports > 0";
} elseif ($status_filter === 'completed') {
    $status_having = " HAVING pending_reports = 0 AND rejected_reports = 0";
}

// --- 2. ดึงข้อมูลนักเรียน ---
$sql = "SELECT u.id, u.fullname, u.username, u.student_code, u.classroom_id, cl.class_name,
        (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id) as total_reports,
        (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id AND status = 'pending') as pending_reports,
        (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id AND status = 'rejected') as rejected_reports,
        COALESCE(comp.name, u.company_name) as company_name
        FROM users u 
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id
        LEFT JOIN companies comp ON u.company_id = comp.id
        WHERE u.role = 'student' $filter_where 
        $status_having
        ORDER BY u.student_code ASC, cl.class_name ASC";
$result = $conn->query($sql);

// --- 3. ดึงรายการห้องเรียนทั้งหมดในระบบ เพื่อย้ายห้องเรียน ---
$all_classrooms = [];
$res_all_rooms = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
while($c = $res_all_rooms->fetch_assoc()) {
    $all_classrooms[] = $c;
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
.table-tch .btn {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.table-tch .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.15) !important;
}
.table-tch .btn:active {
    transform: translateY(0);
}
/* Ensure clean flexbox alignment on compact buttons */
.table-tch td .d-flex .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
</style>

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-people-fill icon-gradient"></i></div>
            รายชื่อนักเรียนในการดูแล
        </h4>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3 bg-white px-3 py-2 rounded-3 shadow-sm">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small fw-bold text-muted" style="white-space: nowrap;"><i class="bi bi-door-open-fill text-primary"></i> เลือกห้อง:</label>
                    <select name="class_id" class="form-select form-select-sm border-0 shadow-none fw-bold" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                        <option value="">-- ทุกห้องเรียน --</option>
                        <?php foreach ($rooms_list as $room): ?>
                            <option value="<?php echo (int)$room['classroom_id']; ?>" <?php echo ($class_id === (int)$room['classroom_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['class_name'] ?: 'ห้อง #' . $room['classroom_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vr opacity-25 d-none d-sm-block"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small fw-bold text-muted" style="white-space: nowrap;"><i class="bi bi-funnel-fill text-primary"></i> กรองสถานะ:</label>
                    <select name="status_filter" class="form-select form-select-sm border-0 shadow-none fw-bold" style="width: auto; min-width: 170px;" onchange="this.form.submit()">
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>-- ทุกสถานะ --</option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>ค้างตรวจ (รอตรวจ)</option>
                        <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>รอนักเรียนส่งใหม่ (ตีกลับ)</option>
                        <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>เรียบร้อย (ผ่านหมดแล้ว)</option>
                    </select>
                </div>
            </form>
            <button type="button" class="btn btn-outline-success shadow-sm rounded-pill animate-hover" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-person-plus-fill me-1"></i> เพิ่มนักเรียนเข้าห้องเรียน
            </button>
            <a href="select_classrooms.php" class="btn btn-tch-gradient rounded-pill">
                <i class="bi bi-gear-fill"></i> จัดการห้องเรียนของคุณ
            </a>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header bg-white">
            <div class="d-flex align-items-center text-dark">
                <i class="bi bi-card-list me-2 text-primary"></i> ตารางรายชื่อนักเรียน
            </div>
            <?php if ($class_id > 0): ?>
                <a href="manage_students.php" class="btn btn-light btn-sm text-muted rounded-pill">ดูทุกห้อง</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="manageStudentsTable" class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ลำดับ</th>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>สถานประกอบการ / ห้องเรียน</th>
                            <th class="text-center">ส่งบันทึกรวม</th>
                            <th class="text-center">สถานะการส่ง</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php 
                            $i = 1;
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="text-secondary fw-bold">
                                    <?php echo $i++; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?php echo htmlspecialchars($row['student_code'] ?? '-'); ?></span>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?php echo htmlspecialchars($row['fullname']); ?></strong>
                                </td>
                                <td>
                                    <div class="small text-secondary mb-1">
                                        <i class="bi bi-briefcase me-1"></i> <?php echo !empty($row['company_name']) ? htmlspecialchars($row['company_name']) : '-'; ?>
                                    </div>
                                    <span class="badge badge-custom badge-primary-soft">
                                        <i class="bi bi-door-open me-1"></i>
                                        <?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : 'ไม่ระบุห้อง'; ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold"><?php echo (int)$row['total_reports']; ?></td>
                                <td class="text-center">
                                    <?php if($row['pending_reports'] > 0): ?>
                                        <span class="badge badge-custom badge-warning-soft w-100 mb-1">
                                            <i class="bi bi-exclamation-circle me-1"></i> ค้างตรวจ <?php echo $row['pending_reports']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if($row['rejected_reports'] > 0): ?>
                                        <span class="badge badge-custom badge-danger-soft w-100">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> รอนักเรียนส่งใหม่ <?php echo $row['rejected_reports']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if($row['pending_reports'] == 0 && $row['rejected_reports'] == 0): ?>
                                        <span class="badge badge-custom badge-success-soft w-100">
                                            <i class="bi bi-check-circle-fill me-1"></i> เรียบร้อย
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <a href="student_reports.php?student_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm"
                                           data-bs-toggle="tooltip"
                                           title="ตรวจบันทึกการฝึกงาน">
                                            <i class="bi bi-journal-check"></i><span class="d-none d-lg-inline ms-1">ตรวจบันทึก</span>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-classroom rounded-pill px-3 shadow-sm" 
                                                data-id="<?php echo $row['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($row['fullname']); ?>" 
                                                data-class-id="<?php echo $row['classroom_id'] ?: 0; ?>"
                                                data-bs-toggle="tooltip"
                                                title="ย้ายห้องเรียน">
                                            <i class="bi bi-arrow-left-right"></i><span class="d-none d-lg-inline ms-1">ย้ายห้อง</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-classroom rounded-pill px-3 shadow-sm" 
                                                data-id="<?php echo $row['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($row['fullname']); ?>"
                                                data-bs-toggle="tooltip"
                                                title="นำออกจากห้องเรียน">
                                            <i class="bi bi-x-circle"></i><span class="d-none d-lg-inline ms-1">นำออก</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-person-dash display-4 d-block mb-2"></i>
                                        <p class="fw-bold mb-0">ไม่พบรายชื่อนักเรียน</p>
                                        <small>กรุณาตั้งค่าห้องเรียนที่รับผิดชอบก่อน</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: เพิ่มนักเรียนเข้าห้องเรียน -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>เพิ่มนักเรียนเข้าห้องเรียนในการดูแล
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addStudentForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">1. เลือกห้องเรียนปลายทางของคุณ <span class="text-danger">*</span></label>
                        <select name="classroom_id" id="add_dest_classroom_id" class="form-select border-2 shadow-none animate-input" required>
                            <option value="">-- เลือกห้องเรียน --</option>
                            <?php foreach ($rooms_list as $room): ?>
                                <option value="<?php echo (int)$room['classroom_id']; ?>">
                                    <?php echo htmlspecialchars($room['class_name'] ?: 'ห้อง #' . $room['classroom_id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">2. ค้นหารายชื่อนักเรียน <span class="text-danger">*</span></label>
                        <div class="input-group border-2 rounded-3" style="border: 2px solid #ced4da;">
                            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="student_search_input" class="form-control border-0 shadow-none ps-0" placeholder="พิมพ์ชื่อ-นามสกุล, รหัสนักศึกษา หรือ Username...">
                        </div>
                        <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อเริ่มค้นหา</small>
                    </div>
                    
                    <!-- ส่วนแสดงผลการค้นหาแบบ Live Search -->
                    <div class="mb-3">
                        <div id="search_results_container" class="list-group overflow-auto border rounded-3 p-1 bg-light shadow-inner" style="max-height: 220px; display: none;">
                            <!-- ผลการค้นหาจะแสดงที่นี่ -->
                        </div>
                    </div>
                    
                    <input type="hidden" name="student_id" id="selected_student_id" value="">
                    
                    <div id="selected_student_preview" class="alert alert-success border-0 shadow-sm p-3 mb-3 align-items-center justify-content-between" style="display: none;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-person-check-fill fs-4 me-3 text-success"></i>
                            <div>
                                <div class="fw-bold text-dark" id="preview_fullname">ชื่อนักเรียน</div>
                                <small class="text-secondary" id="preview_code">รหัส: - | Username: -</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" id="btn_clear_selection" aria-label="Clear"></button>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="btn_submit_add" disabled>
                            <i class="bi bi-plus-circle me-1"></i> เพิ่มนักเรียนเข้าห้อง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: ย้ายห้องเรียนของนักเรียน -->
<div class="modal fade" id="editClassroomModal" tabindex="-1" aria-labelledby="editClassroomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="editClassroomModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>ย้ายห้องเรียนของนักเรียน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editClassroomForm">
                    <input type="hidden" name="student_id" id="edit_student_id" value="">
                    
                    <div class="mb-3 p-3 bg-light rounded-3 border-start border-primary border-4 shadow-sm mb-4">
                        <div class="small text-muted mb-1">นักเรียนที่ต้องการย้าย:</div>
                        <h6 class="fw-bold text-dark mb-0" id="edit_student_name">ชื่อนักเรียน</h6>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">เลือกห้องเรียนใหม่ <span class="text-danger">*</span></label>
                        <select name="new_classroom_id" id="edit_new_classroom_id" class="form-select border-2 shadow-none animate-input" required>
                            <option value="">-- เลือกห้องเรียน --</option>
                            <option value="0">-- ไม่มีห้องเรียน (ไม่ระบุห้อง) --</option>
                            <?php foreach ($all_classrooms as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกการย้ายห้อง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    var $t = $('#manageStudentsTable');
    if ($t.find('tbody tr').length > 0 && !$t.find('tbody tr:first td').attr('colspan')) {
        let table = $t.DataTable({
            pageLength: 15,
            order: [[1, 'asc']],
            columnDefs: [{ orderable: false, targets: 'no-sort' }],
            language: { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', paginate: { first: 'แรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' } }
        });

        // Initialize tooltips dynamically on initial draw and every page change/draw event
        const initTooltips = () => {
            $('[data-bs-toggle="tooltip"]').each(function() {
                let tooltipInstance = bootstrap.Tooltip.getInstance(this);
                if (!tooltipInstance) {
                    new bootstrap.Tooltip(this);
                }
            });
        };

        initTooltips();
        table.on('draw', initTooltips);
    }

    // --- Live Search ใน Modal เพิ่มนักเรียน ---
    let searchTimeout = null;
    
    $('#student_search_input').on('input', function() {
        clearTimeout(searchTimeout);
        let q = $(this).val().trim();
        
        if (q.length < 2) {
            $('#search_results_container').hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: 'ajax_manage_students.php',
                type: 'GET',
                data: { action: 'search_students', q: q },
                dataType: 'json',
                success: function(data) {
                    let $container = $('#search_results_container');
                    $container.empty().show();
                    
                    if (data.length === 0) {
                        $container.append('<div class="list-group-item text-muted text-center py-3"><i class="bi bi-emoji-frown me-1"></i> ไม่พบรายชื่อนักเรียน</div>');
                        return;
                    }
                    
                    data.forEach(function(student) {
                        let sub = (student.student_code ? 'รหัส: ' + student.student_code : 'Username: ' + student.username);
                        let roomText = 'ห้องเรียนปัจจุบัน: ' + student.class_name;
                        
                        let item = $('<button type="button" class="list-group-item list-group-item-action border-0 mb-1 rounded-2 py-2 px-3 d-flex justify-content-between align-items-center bg-white shadow-sm">' +
                            '<div>' +
                                '<div class="fw-bold text-dark text-start">' + student.fullname + '</div>' +
                                '<div class="small text-secondary text-start">' + sub + '</div>' +
                                '<div class="small text-primary text-start"><i class="bi bi-door-open me-1"></i>' + roomText + '</div>' +
                            '</div>' +
                            '<span class="btn btn-sm btn-outline-success rounded-pill px-3 py-1"><i class="bi bi-plus-circle me-1"></i>เลือก</span>' +
                        '</button>');
                        
                        item.on('click', function() {
                            // เลือกนักเรียน
                            $('#selected_student_id').val(student.id);
                            $('#preview_fullname').text(student.fullname);
                            $('#preview_code').text(sub + ' | ' + roomText);
                            
                            $('#student_search_input').val('');
                            $container.hide().empty();
                            $('#selected_student_preview').addClass('d-flex').show();
                            
                            validateAddForm();
                        });
                        
                        $container.append(item);
                    });
                },
                error: function() {
                    console.error('Error fetching students');
                }
            });
        }, 300);
    });
    
    // เคลียร์การเลือกนักเรียน
    $('#btn_clear_selection').on('click', function() {
        $('#selected_student_id').val('');
        $('#selected_student_preview').removeClass('d-flex').hide();
        validateAddForm();
    });
    
    // ตรวจสอบความถูกต้องของแบบฟอร์มเพิ่มนักเรียน เพื่อเปิดปิดปุ่ม submit
    $('#add_dest_classroom_id').on('change', function() {
        validateAddForm();
    });
    
    function validateAddForm() {
        let hasStudent = $('#selected_student_id').val() !== '';
        let hasClassroom = $('#add_dest_classroom_id').val() !== '';
        $('#btn_submit_add').prop('disabled', !(hasStudent && hasClassroom));
    }
    
    // ส่งแบบฟอร์มเพิ่มนักเรียนเข้าห้อง
    $('#addStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        let studentId = $('#selected_student_id').val();
        let classroomId = $('#add_dest_classroom_id').val();
        
        Swal.fire({
            title: 'กำลังเพิ่มนักเรียน...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'ajax_manage_students.php',
            type: 'POST',
            data: {
                action: 'add_to_classroom',
                student_id: studentId,
                classroom_id: classroomId
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: res.message,
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', res.error || 'เกิดข้อผิดพลาดในการดำเนินการ', 'error');
                }
            },
            error: function() {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            }
        });
    });
    
    // --- จัดการการย้ายห้องเรียน (Edit Classroom) ---
    $('#manageStudentsTable').on('click', '.btn-edit-classroom', function() {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let classId = $(this).data('class-id');
        
        $('#edit_student_id').val(id);
        $('#edit_student_name').text(name);
        $('#edit_new_classroom_id').val(classId);
        
        $('#editClassroomModal').modal('show');
    });
    
    $('#editClassroomForm').on('submit', function(e) {
        e.preventDefault();
        
        let studentId = $('#edit_student_id').val();
        let newClassroomId = $('#edit_new_classroom_id').val();
        
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'ajax_manage_students.php',
            type: 'POST',
            data: {
                action: 'change_classroom',
                student_id: studentId,
                new_classroom_id: newClassroomId
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#editClassroomModal').modal('hide');
                    Swal.fire({
                        title: 'ย้ายห้องเรียนสำเร็จ!',
                        text: res.message,
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', res.error || 'เกิดข้อผิดพลาดในการย้ายห้องเรียน', 'error');
                }
            },
            error: function() {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            }
        });
    });
    
    // --- จัดการนำนักเรียนออกจากห้องเรียน (Remove from Classroom) ---
    $('#manageStudentsTable').on('click', '.btn-remove-classroom', function() {
        let studentId = $(this).data('id');
        let name = $(this).data('name');
        
        Swal.fire({
            title: 'ยืนยันการนำนักเรียนออก?',
            html: 'คุณแน่ใจที่จะนำ <strong>' + name + '</strong> ออกจากห้องเรียนในการดูแลของคุณหรือไม่?<br><small class="text-danger">*การดำเนินการนี้จะทำให้ไม่สามารถตรวจหรือเข้าถึงรายงานประจำวันของนักเรียนคนนี้ได้จนกว่าจะเพิ่มเขากลับเข้ามาใหม่</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, นำออกจากห้อง',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังดำเนินการ...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'ajax_manage_students.php',
                    type: 'POST',
                    data: {
                        action: 'remove_from_classroom',
                        student_id: studentId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'ตกลง'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', res.error || 'เกิดข้อผิดพลาดในการนำนักเรียนออก', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>