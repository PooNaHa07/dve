<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();

// ลบแผนการฝึก (ใช้ POST เพื่อกันกด Refresh แล้วลบซ้ำ)
if (isset($_POST['del_id'])) {
    $id = (int) $_POST['del_id'];
    $u = current_user();
    $teacher_id = (int)$u['id'];

    // [SECURITY]: 1. ดึงไฟล์และตรวจสอบสิทธิ์พร้อมกัน
    $stmt_sel = $conn->prepare("SELECT filename FROM subject_plans WHERE id = ? AND teacher_id = ?");
    $stmt_sel->bind_param("ii", $id, $teacher_id);
    $stmt_sel->execute();
    $data = $stmt_sel->get_result()->fetch_assoc();
    $stmt_sel->close();

    if ($data) {
        if (!empty($data['filename'])) {
            $file_path = __DIR__ . "/../uploads/subject_plans/" . $data['filename'];
            if (is_file($file_path)) {
                @unlink($file_path);
            }
        }

        // [SECURITY]: 2. ลบรายการเฉพาะที่มีสิทธิ์
        $stmt_del = $conn->prepare("DELETE FROM subject_plans WHERE id = ? AND teacher_id = ?");
        $stmt_del->bind_param("ii", $id, $teacher_id);
        $stmt_del->execute();
        $stmt_del->close();
    }

    header("Location: subject_plans.php?msg=deleted");
    exit;
}

$swal_type = '';
$swal_title = '';
$swal_text = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') {
        $swal_type = 'success';
        $swal_title = 'ลบข้อมูลแล้ว';
        $swal_text = 'ลบข้อมูลเรียบร้อยแล้ว';
    } elseif ($_GET['msg'] == 'success') {
        $swal_type = 'success';
        $swal_title = 'บันทึกสำเร็จ';
        $swal_text = 'บันทึกแผนการฝึกสำเร็จ';
    } elseif ($_GET['msg'] == 'updated') {
        $swal_type = 'success';
        $swal_title = 'แก้ไขสำเร็จ';
        $swal_text = 'แก้ไขข้อมูลแผนการฝึกสำเร็จ';
    }
}

$u = current_user();
$teacher_id = (int)$u['id'];

$sql = "SELECT s.*, c.name as company_name 
        FROM subject_plans s 
        LEFT JOIN companies c ON s.company_id = c.id 
        /* [SECURITY WARNING]: ห้ามนำบรรทัด WHERE s.teacher_id ออกเด็ดขาด เพื่อไม่ให้เห็นข้อมูลของครูคนอื่น */
        WHERE s.teacher_id = $teacher_id
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../includes/teacher_style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container teacher-page-container">
    <form id="deleteSubjectPlanForm" method="POST" action="subject_plans.php" class="d-none">
        <input type="hidden" name="del_id" id="deleteSubjectPlanForm_id" value="">
    </form>

    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-book-half icon-gradient"></i></div>
            แผนการฝึกอาชีพรายวิชา
        </h4>
        <div>
            <a href="add_subject_plan.php" class="btn btn-tch-gradient">
                <i class="bi bi-file-earmark-plus-fill me-1"></i> เพิ่มแผนฝึกรายวิชา
            </a>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header">
            <div><i class="bi bi-list-check text-primary me-2"></i> ตารางข้อมูลแผนการฝึกอาชีพ</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="subjectPlansTable" class="table table-tch align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 140px;">รหัสวิชา</th>
                            <th>ชื่อวิชา</th>
                            <th>สถานประกอบการ</th>
                            <th class="text-center">ไฟล์ PDF</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><code class="text-muted bg-light px-2 py-1 rounded fw-bold"><?php echo htmlspecialchars($row['subject_code']); ?></code></td>
                            <td><strong class="text-dark"><?php echo htmlspecialchars($row['subject_name']); ?></strong></td>
                            <td>
                                <span class="text-muted small">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo !empty($row['company_name']) ? htmlspecialchars($row['company_name']) : 'ทุกสถานประกอบการ'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="../uploads/subject_plans/<?php echo htmlspecialchars($row['filename']); ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-light border rounded-pill px-3 text-danger">
                                   <i class="bi bi-filetype-pdf me-1"></i> เปิด
                                </a>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="edit_subject_plan.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmDeleteSubjectPlan(<?php echo (int)$row['id']; ?>)">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-files-alt display-5 d-block mb-2 opacity-50"></i>
                                    ยังไม่มีข้อมูลแผนการฝึกอาชีพ
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

<?php if ($swal_type !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: <?php echo json_encode($swal_title); ?>,
        text: <?php echo json_encode($swal_text); ?>,
        icon: <?php echo json_encode($swal_type); ?>,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#0d6efd'
    }).then(function() {
        var url = new URL(window.location.href);
        url.searchParams.delete('msg');
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
    });
});
</script>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    var $t = $('#subjectPlansTable');
    if ($t.find('tbody tr').length > 0 && !$t.find('tbody tr:first td').attr('colspan')) {
        $t.DataTable({
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: 'no-sort' }],
            language: {
                search: 'ค้นหา:',
                lengthMenu: 'แสดง _MENU_ รายการ',
                info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                paginate: { first: 'แรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }
            }
        });
    }
});
function confirmDeleteSubjectPlan(id) {
    Swal.fire({
        title: 'ยืนยันการลบ',
        text: 'ต้องการลบแผนการฝึกรายการนี้จริงหรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then(function(r) {
        if (r.isConfirmed) {
            document.getElementById('deleteSubjectPlanForm_id').value = id;
            document.getElementById('deleteSubjectPlanForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
