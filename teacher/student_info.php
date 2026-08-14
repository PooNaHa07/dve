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

$filter_where = "";
if (!empty($room_ids)) {
    $ids_string = implode(',', array_map('intval', $room_ids));
    $filter_where = " AND u.classroom_id IN ($ids_string)";
} else {
    $filter_where = " AND 1 = 0";
}

// กรองตามห้องที่เลือก (แยกห้อง)
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id > 0 && in_array($class_id, $room_ids)) {
    $filter_where = " AND u.classroom_id = " . $class_id;
}

// --- 2. ดึงข้อมูลนักเรียนแบบละเอียด ---
$sql = "SELECT u.*, cl.class_name, comp.name as comp_name 
        FROM users u 
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id
        LEFT JOIN companies comp ON u.company_id = comp.id
        WHERE u.role = 'student' $filter_where 
        ORDER BY cl.class_name ASC, u.student_code ASC";
$result = $conn->query($sql);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../includes/teacher_style.css">

<style>
    .student-avatar {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .info-card-detail {
        font-size: 0.85rem;
        color: #64748b;
    }
    .info-label {
        font-weight: 600;
        color: #1e293b;
        margin-right: 5px;
    }
</style>

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-person-lines-fill icon-gradient"></i></div>
            ข้อมูลนักศึกษาในการดูแล
        </h4>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <form method="GET" class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-3 shadow-sm">
                <label class="form-label mb-0 small fw-bold text-muted">เลือกห้อง:</label>
                <select name="class_id" class="form-select form-select-sm border-0 shadow-none fw-bold" style="width: auto; min-width: 180px;" onchange="this.form.submit()">
                    <option value="">-- ทุกห้องเรียน --</option>
                    <?php foreach ($rooms_list as $room): ?>
                        <option value="<?php echo (int)$room['classroom_id']; ?>" <?php echo ($class_id === (int)$room['classroom_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($room['class_name'] ?: 'ห้อง #' . $room['classroom_id']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header bg-white">
            <div class="d-flex align-items-center text-dark">
                <i class="bi bi-person-badge me-2 text-primary"></i> รายชื่อและข้อมูลติดต่อ
            </div>
            <?php if ($class_id > 0): ?>
                <a href="student_info.php" class="btn btn-light btn-sm text-muted rounded-pill">ดูทุกห้อง</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="studentInfoTable" class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">ลำดับ</th>
                            <th>รหัสนักศึกษา</th>
                            <th>นักศึกษา</th>
                            <th>ข้อมูลติดต่อ</th>
                            <th>สถานประกอบการ / ครูฝึก</th>
                            <th class="text-center no-sort">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php 
                            $i = 1;
                            while($row = $result->fetch_assoc()): 
                                $profile_img = !empty($row['profile_image']) ? '../uploads/avatars/' . $row['profile_image'] : '../assets/img/default-avatar.png';
                            ?>
                            <tr>
                                <td class="ps-4 text-secondary fw-bold">
                                    <?php echo $i++; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?php echo htmlspecialchars($row['student_code'] ?? '-'); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo $profile_img; ?>" alt="Profile" class="student-avatar" onerror="this.src='../assets/img/default-avatar.png'">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['student_level']); ?></div>
                                            <span class="badge badge-custom badge-primary-soft mt-1">
                                                <i class="bi bi-door-open me-1"></i> <?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : 'ไม่ระบุห้อง'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="info-card-detail">
                                        <div><i class="bi bi-telephone-fill me-1 text-primary"></i> <?php echo htmlspecialchars($row['phone'] ?: '-'); ?></div>
                                        <div><i class="bi bi-envelope-fill me-1 text-danger"></i> <?php echo htmlspecialchars($row['email'] ?: '-'); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="info-card-detail">
                                        <div class="fw-bold text-dark"><i class="bi bi-briefcase me-1"></i> <?php echo htmlspecialchars($row['comp_name'] ?: ($row['company_name'] ?: '-')); ?></div>
                                        <div class="small"><i class="bi bi-person-check me-1"></i> ครูฝึก: <?php echo htmlspecialchars($row['trainer_name'] ?: '-'); ?> (<?php echo htmlspecialchars($row['trainer_phone'] ?: '-'); ?>)</div>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="bi bi-eye me-1"></i> รายละเอียด
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-person-dash display-4 d-block mb-2"></i>
                                        <p class="fw-bold mb-0">ไม่พบรายชื่อนักเรียน</p>
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

<!-- Modal รายละเอียดนักเรียน -->
<div class="modal fade" id="studentDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle-fill text-info me-2"></i>ข้อมูลนักศึกษาโดยละเอียด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- ข้อมูลจะถูกเติมโดย JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    var $t = $('#studentInfoTable');
    if ($t.find('tbody tr').length > 0 && !$t.find('tbody tr:first td').attr('colspan')) {
        $t.DataTable({
            pageLength: 15,
            order: [[1, 'asc']],
            columnDefs: [{ orderable: false, targets: 'no-sort' }],
            language: { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', paginate: { first: 'แรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' } }
        });
    }
});

function viewDetails(data) {
    let html = `
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <img src="${data.profile_image ? '../uploads/avatars/' + data.profile_image : '../assets/img/default-avatar.png'}" 
                     class="img-fluid rounded-4 shadow-sm mb-3" style="max-height: 200px; width: 100%; object-fit: cover;"
                     onerror="this.src='../assets/img/default-avatar.png'">
                <h5 class="fw-bold text-dark mb-1">${data.fullname}</h5>
                <span class="badge bg-primary-soft text-primary rounded-pill px-3">${data.student_level || '-'}</span>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">รหัสนักศึกษา</div>
                        <div class="fw-bold">${data.student_code || '-'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">ห้องเรียน</div>
                        <div class="fw-bold">${data.class_name || 'ไม่ระบุ'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">เบอร์โทรศัพท์</div>
                        <div class="fw-bold">${data.phone || '-'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">อีเมล</div>
                        <div class="fw-bold">${data.email || '-'}</div>
                    </div>
                </div>
                <hr class="my-3 opacity-10">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-briefcase-fill me-2"></i>ข้อมูลการฝึกงาน</h6>
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="info-label small text-muted">สถานประกอบการ</div>
                        <div class="fw-bold">${data.comp_name || data.company_name || '-'}</div>
                        <div class="small text-muted">${data.company_address || ''}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">ชื่อครูฝึก</div>
                        <div class="fw-bold">${data.trainer_name || '-'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">เบอร์โทรครูฝึก</div>
                        <div class="fw-bold">${data.trainer_phone || '-'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">ตำแหน่งครูฝึก</div>
                        <div class="fw-bold">${data.trainer_position || '-'}</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="info-label small text-muted">ผู้จัดการ/ผู้รับผิดชอบ</div>
                        <div class="fw-bold">${data.company_manager || '-'}</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('#modalBody').html(html);
    $('#studentDetailModal').modal('show');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
