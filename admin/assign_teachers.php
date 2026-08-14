<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

// --- 1. จัดการบันทึกการมอบหมาย (รองรับหลายห้อง) ---
if(isset($_POST['assign'])){
    $teacher_id = intval($_POST['teacher_id']);
    // ตรวจสอบว่ามีการเลือกห้องเรียนมาหรือไม่
    $classroom_ids = isset($_POST['classroom_ids']) ? $_POST['classroom_ids'] : array(); 

    if(!empty($classroom_ids) && is_array($classroom_ids)){
        $success_count = 0;
        $db_error = false;
        $ins = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, classroom_id) VALUES (?, ?)");
        if (!$ins) {
            $db_error = true;
        } else {
            foreach($classroom_ids as $room_id){
                $room_id = intval($room_id);
                $check = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id=? AND classroom_id=?");
                if ($check) {
                    $check->bind_param("ii", $teacher_id, $room_id);
                    $check->execute();
                    $res = $check->get_result();
                    $check->close();
                    if($res && $res->num_rows == 0){
                        $ins->bind_param("ii", $teacher_id, $room_id);
                        if ($ins->execute()) {
                            $success_count++;
                        } else {
                            $errno = $conn->errno;
                            if ($errno == 1062 || strpos($conn->error, 'Duplicate') !== false) {
                                // ข้ามรายการซ้ำ
                            } else {
                                $db_error = true;
                                break;
                            }
                        }
                    }
                }
            }
            $ins->close();
        }
        if ($db_error) {
            header("Location: assign_teachers.php?msg=db_error");
        } else {
            header("Location: assign_teachers.php?msg=success&count=" . $success_count);
        }
    } else {
        header("Location: assign_teachers.php?msg=error");
    }
    exit;
}

// --- 2. จัดการลบการมอบหมาย (ใช้ POST เพื่อกันกด Refresh แล้วลบซ้ำ) ---
if(isset($_POST['del_id'])){
    $del_id = intval($_POST['del_id']);
    $conn->query("DELETE FROM teacher_assignments WHERE id='$del_id'");
    header("Location: assign_teachers.php?msg=deleted");
    exit;
}

$swal_type = '';
$swal_title = '';
$swal_text = '';
if(isset($_GET['msg'])){
    if($_GET['msg'] == 'success') {
        $swal_type = 'success';
        $swal_title = 'บันทึกสำเร็จ';
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
        $swal_text = 'มอบหมายเพิ่มทั้งหมด ' . $count . ' รายการ';
    } elseif($_GET['msg'] == 'deleted') {
        $swal_type = 'success';
        $swal_title = 'ยกเลิกการมอบหมายแล้ว';
        $swal_text = 'ยกเลิกการมอบหมายเรียบร้อยแล้ว';
    } elseif($_GET['msg'] == 'error') {
        $swal_type = 'warning';
        $swal_title = 'กรุณาตรวจสอบ';
        $swal_text = 'กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง';
    } elseif($_GET['msg'] == 'db_error') {
        $swal_type = 'error';
        $swal_title = 'เกิดข้อผิดพลาด';
        $swal_text = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container admin-content-wrapper">
    <!-- ฟอร์มซ่อนสำหรับลบ -->
    <form id="deleteAssignForm" method="POST" action="assign_teachers.php" class="d-none">
        <input type="hidden" name="del_id" id="deleteAssignForm_id" value="">
    </form>

    <div class="admin-header-section">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-chalkboard-teacher"></i>
                จัดการการมอบหมายครู
            </h2>
            <p class="text-white text-opacity-75 mb-0 mt-1 small">มอบหมายครูให้ดูแลหลายห้องเรียนแบบรวมศูนย์</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 1rem;">
                <div class="card-header bg-dark text-white py-3">
                    <h6 class="mb-0 fw-bold text-white"><i class="fas fa-plus-circle me-2"></i>สร้างรายการมอบหมาย</h6>
                </div>
                <div class="card-body bg-white p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="admin-form-label mb-2 d-block"><i class="fas fa-user-tie me-2 text-muted"></i>1. ครูผู้ดูแล</label>
                            <select name="teacher_id" class="form-select admin-form-control" required>
                                <option value="">-- เลือกรายชื่อครู --</option>
                                <?php
                                $teachers = $conn->query("SELECT id, fullname FROM users WHERE role='teacher' ORDER BY fullname ASC");
                                if($teachers){
                                    while($t = $teachers->fetch_assoc()) echo "<option value='{$t['id']}'>👨‍🏫 {$t['fullname']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="admin-form-label mb-2 d-block"><i class="fas fa-school me-2 text-muted"></i>2. เลือกห้องเรียน</label>
                            <small class="text-muted d-block mb-2"><i class="fas fa-info-circle me-1"></i> กด Ctrl ค้างไว้เพื่อเลือกหลายห้อง</small>
                            <select name="classroom_ids[]" class="form-select admin-form-control" multiple style="min-height: 180px;" required>
                                <?php
                                $rooms = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
                                if($rooms){
                                    while($r = $rooms->fetch_assoc()) echo "<option value='{$r['id']}'>🏫 {$r['class_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="assign" class="btn-admin-primary w-100 py-2">
                            <i class="fas fa-save me-2"></i>บันทึกการมอบหมาย
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="admin-table-container shadow-sm m-0 h-100">
                <div class="table-responsive bg-white h-100">
                    <table id="assignTeachersTable" class="table admin-table table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ครูผู้ดูแล</th>
                                <th class="text-center">ห้องเรียนที่ดูแล</th>
                                <th class="text-center" style="width: 100px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT ta.id, u.fullname, cl.class_name 
                                    FROM teacher_assignments ta
                                    JOIN users u ON ta.teacher_id = u.id
                                    JOIN classrooms cl ON ta.classroom_id = cl.id
                                    ORDER BY u.fullname ASC";
                            $res = $conn->query($sql);
                            
                            if($res && $res->num_rows > 0):
                                while($row = $res->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><i class="fas fa-user-circle me-2 opacity-50"></i><?php echo $row['fullname']; ?></td>
                                <td class="text-center">
                                    <span class="admin-badge bg-light text-primary border-primary border-opacity-25 fw-bold" style="font-size: 0.9rem;">
                                        <i class="fas fa-door-open me-1 opacity-75"></i><?php echo $row['class_name']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmDeleteAssign(<?php echo (int)$row['id']; ?>)" style="font-size: 0.75rem;">
                                        <i class="fas fa-times me-1"></i>ยกเลิก
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted"><i class="fas fa-user-slash d-block fs-3 mb-2 opacity-50"></i>ยังไม่มีข้อมูลการมอบหมาย</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($swal_type !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: <?php echo json_encode($swal_title); ?>,
        text: <?php echo json_encode($swal_text); ?>,
        icon: <?php echo json_encode($swal_type); ?>,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#4f46e5',
        borderRadius: '1rem'
    }).then(function() {
        var url = new URL(window.location.href);
        url.searchParams.delete('msg');
        url.searchParams.delete('count');
        var newUrl = url.pathname + (url.search ? url.search : '');
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', newUrl);
        }
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('assignTeachersTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
        $(tbl).DataTable({ 
            order: [[0, 'asc']], 
            language: dtLang, 
            pageLength: 15,
            dom: '<"d-flex justify-content-between align-items-center mb-3 px-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 px-2"ip>'
        });
    }
});

function confirmDeleteAssign(id) {
    Swal.fire({
        title: 'ยืนยันการยกเลิก?',
        text: 'ต้องการยกเลิกการมอบหมายให้ดูแลห้องเรียนนี้จริงหรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ยกเลิกเลย',
        cancelButtonText: 'ไม่ใช่',
        borderRadius: '1rem'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('deleteAssignForm_id').value = id;
            document.getElementById('deleteAssignForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>