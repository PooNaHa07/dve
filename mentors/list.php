<?php
// mentors/list.php - หน้าจัดการรายการครูนิเทศก์ (Modal Version)
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']); 

$success_msg = '';
$error_msg = '';

// 1. Handle POST Requests (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $mentor_id = isset($_POST['mentor_id']) ? (int)$_POST['mentor_id'] : 0;

    if (empty($fullname) || empty($department)) {
        $error_msg = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO mentors (fullname, department, email, phone, user_id, status) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssi", $fullname, $department, $email, $phone, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "เพิ่มข้อมูลครูนิเทศก์สำเร็จแล้ว!";
                header("Location: list.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action === 'edit' && $mentor_id > 0) {
            $stmt = $conn->prepare("UPDATE mentors SET fullname = ?, department = ?, email = ?, phone = ?, user_id = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $fullname, $department, $email, $phone, $user_id, $mentor_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "แก้ไขข้อมูลสำเร็จแล้ว!";
                header("Location: list.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch potential teacher users for the dropdown
$potentialTeachers = [];
$sqlU = "SELECT u.id, u.username, u.fullname FROM users u 
         LEFT JOIN mentors m ON u.id = m.user_id 
         WHERE u.role = 'teacher' AND m.id IS NULL 
         ORDER BY u.fullname ASC";
$qUsers = $conn->query($sqlU);
if ($qUsers) {
    while($r = $qUsers->fetch_assoc()) $potentialTeachers[] = $r;
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/staff_style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="staff-dashboard-page pt-4">
    <div class="container">

        <!-- Page Header Container -->
        <div class="staff-page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>จัดการครูนิเทศก์</h4>
                <p class="text-muted mb-0 small">บริหารจัดการรายชื่อและประวัติครูนิเทศก์ที่ดูแลนักเรียน</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-staff d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addMentorModal">
                    <i class="bi bi-plus-lg fs-6"></i> เพิ่มครูนิเทศก์
                </button>
                <a href="../roles/staff.php" class="btn btn-outline-secondary border-opacity-25 bg-white rounded-pill shadow-sm">
                    กลับแดชบอร์ด
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php 
        if(isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i>'.e($_SESSION['success_message']).'</div>';
            unset($_SESSION['success_message']);
        }
        if($error_msg) {
            echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i>'.e($error_msg).'</div>';
        }
        ?>

        <div class="pastel-card p-4 shadow-sm border-0" style="border-radius: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);">
            <table id="mentorsListTable" class="table table-hover" style="width:100%">
                <thead>
                    <tr class="text-secondary">
                        <th class="border-0">ID</th>
                        <th class="border-0">ชื่อ-สกุล</th>
                        <th class="border-0">สังกัด/โรงเรียน</th>
                        <th class="border-0">อีเมล</th>
                        <th class="border-0">เบอร์โทรศัพท์</th>
                        <th class="border-0 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                <?php
                $sql = "SELECT id, fullname, department, email, phone FROM mentors ORDER BY id DESC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0): 
                    while ($mentor = $result->fetch_assoc()): ?>
                    <tr class="align-middle">
                        <td class="fw-bold text-primary">#<?= e($mentor['id']) ?></td>
                        <td class="fw-bold text-dark"><?= e($mentor['fullname']) ?></td> 
                        <td><span class="badge bg-light text-dark fw-normal border"><?= e($mentor['department']) ?></span></td> 
                        <td class="text-muted small"><?= e($mentor['email']) ?></td> 
                        <td class="text-muted small"><?= e($mentor['phone']) ?></td> 
                        <td>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 edit-mentor-btn" data-id="<?= $mentor['id'] ?>">
                                    <i class="bi bi-pencil text-primary"></i>
                                </button>
                                <a href="delete_mentor.php?id=<?= $mentor['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 btn-delete-mentor" data-id="<?= $mentor['id'] ?>" data-name="<?= e($mentor['fullname']) ?>">
                                    <i class="bi bi-trash text-danger"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Mentor -->
<div class="modal fade" id="addMentorModal" tabindex="-1" aria-labelledby="addMentorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="addMentorModalLabel"><i class="bi bi-person-plus text-primary me-2"></i>เพิ่มครูนิเทศก์ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">ผูกกับบัญชีผู้ใช้งาน (ถ้ามี)</label>
                        <select class="form-select border-0 bg-light rounded-3 py-2" name="user_id">
                            <option value="0">-- ไม่ผูกบัญชี --</option>
                            <?php foreach($potentialTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['fullname']) ?> (<?= e($t['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ชื่อ-สกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="fullname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">สังกัด/สาขาวิชา <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="department" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">อีเมล</label>
                            <input type="email" class="form-control border-0 bg-light rounded-3 py-2" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-staff rounded-pill px-5 shadow">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Mentor -->
<div class="modal fade" id="editMentorModal" tabindex="-1" aria-labelledby="editMentorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="editMentorModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลครูนิเทศก์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editMentorForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="mentor_id" id="edit_mentor_id">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">ผูกกับบัญชีผู้ใช้งาน</label>
                        <select class="form-select border-0 bg-light rounded-3 py-2" name="user_id" id="edit_user_id">
                            <option value="0">-- ไม่ผูกบัญชี --</option>
                            <?php 
                            // Fetch all teacher users for the edit modal (simplified for now)
                            $qAll = $conn->query("SELECT id, username, fullname FROM users WHERE role='teacher' ORDER BY fullname ASC");
                            while($t = $qAll->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['fullname']) ?> (<?= e($t['username']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ชื่อ-สกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="fullname" id="edit_fullname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">สังกัด/สาขาวิชา <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="department" id="edit_department" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">อีเมล</label>
                            <input type="email" class="form-control border-0 bg-light rounded-3 py-2" name="email" id="edit_email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control border-0 bg-light rounded-3 py-2" name="phone" id="edit_phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-staff rounded-pill px-5 shadow">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 1. DataTables
    var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_ รายการ)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
    $('#mentorsListTable').DataTable({ 
        order: [[0, 'desc']], 
        language: dtLang,
        pageLength: 25,
        drawCallback: function() {
            // Re-bind events after table redraw
            bindTableEvents();
        }
    });

    function bindTableEvents() {
        // Edit Button Click
        $('.edit-mentor-btn').off('click').on('click', function() {
            var id = $(this).data('id');
            // Fetch Data via AJAX
            $.get('get_mentor.php', { id: id }, function(data) {
                if (data.error) {
                    Swal.fire('Error', data.error, 'error');
                } else {
                    $('#edit_mentor_id').val(data.id);
                    $('#edit_fullname').val(data.fullname);
                    $('#edit_department').val(data.department);
                    $('#edit_email').val(data.email);
                    $('#edit_phone').val(data.phone);
                    $('#edit_user_id').val(data.user_id);
                    const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editMentorModal'));
                    editModal.show();
                }
            });
        });

        // Delete Button Click
        $('.btn-delete-mentor').off('click').on('click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            var name = $(this).data('name');
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: "ต้องการลบครูนิเทศก์ " + name + " ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    }

    bindTableEvents();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>