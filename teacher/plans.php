<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
// [H-3] ตรวจสอบ role ก่อน ป้องกันนักเรียน/บุคคลอื่นเข้าถึงโดยตรงผ่าน URL
require_role(['teacher', 'admin']);
// [L-4] timezone ย้ายไปจัดการใน includes/functions.php แล้ว

// --- ลบแผนการนิเทศ (ใช้ POST เพื่อกันกด Refresh แล้วลบซ้ำ) ---
if (isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $u = current_user();
    $teacher_id = (int)$u['id'];

    // [C-2] ใช้ Prepared Statement + IDOR Check เพื่อป้องกันการลบของผู้อื่น
    $stmt_sel = $conn->prepare("SELECT filename FROM plans WHERE id = ? AND teacher_id = ?");
    if ($stmt_sel) {
        $stmt_sel->bind_param("ii", $id, $teacher_id);
        $stmt_sel->execute();
        $res_file = $stmt_sel->get_result();
        $row_file = $res_file ? $res_file->fetch_assoc() : null;
        $stmt_sel->close();
        if ($row_file && !empty($row_file['filename'])) {
            $file_to_delete = __DIR__ . '/../uploads/plans/' . basename($row_file['filename']);
            if (is_file($file_to_delete)) {
                @unlink($file_to_delete);
            }
        }
    }

    // [SECURITY]: บังคับลบเฉพาะที่เป็นเจ้าของเท่านั้น
    $stmt_del = $conn->prepare("DELETE FROM plans WHERE id = ? AND teacher_id = ?");
    if ($stmt_del) {
        $stmt_del->bind_param("ii", $id, $teacher_id);
        $stmt_del->execute();
        $stmt_del->close();
    }
    header("Location: plans.php?msg=deleted");
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
        $swal_text = isset($_GET['count']) ? 'บันทึกและกระจายแผนนิเทศสำเร็จสำหรับ ' . (int)$_GET['count'] . ' สถานประกอบการ' : 'บันทึกแผนการนิเทศเรียบร้อยแล้ว';
    } elseif ($_GET['msg'] == 'updated') {
        $swal_type = 'success';
        $swal_title = 'แก้ไขสำเร็จ';
        $swal_text = 'แก้ไขข้อมูลสำเร็จ';
    } elseif ($_GET['msg'] == 'error') {
        $swal_type = 'error';
        $swal_title = 'เกิดข้อผิดพลาด';
        $swal_text = 'อัปโหลดไฟล์ไม่สำเร็จ';
    }
}

$u = current_user();
$teacher_id = (int)$u['id'];

$sql = "SELECT p.*, c.name as company_name, COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name, u_up.role AS uploader_role
        FROM plans p 
        LEFT JOIN companies c ON p.company_id = c.id 
        LEFT JOIN users u_up ON p.uploaded_by = u_up.id
        /* [SECURITY WARNING]: ห้ามนำบรรทัด WHERE p.teacher_id ออกเด็ดขาด เพื่อไม่ให้เห็นข้อมูลของครูคนอื่น */
        WHERE p.teacher_id = $teacher_id
        ORDER BY p.plan_date DESC";
$result = $conn->query($sql);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../includes/teacher_style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container teacher-page-container">
    <form id="deletePlanForm" method="POST" action="plans.php" class="d-none">
        <input type="hidden" name="delete_id" id="deletePlanForm_id" value="">
    </form>

    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-calendar-check icon-gradient"></i></div>
            รายการแผนการนิเทศ
        </h4>
        <div class="d-flex gap-2">
            <a href="add_plan.php" class="btn btn-tch-gradient">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มแผนการนิเทศ
            </a>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header">
            <div><i class="bi bi-journal-richtext text-primary me-2"></i> รายชื่อแผนการนิเทศที่บันทึกไว้</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="plansTable" class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:120px;">วันที่</th>
                            <th>หัวข้อแผนการนิเทศ / ผู้อัปโหลด</th>
                            <th>สถานประกอบการ</th>
                            <th class="text-center">ไฟล์เอกสาร</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><span class="badge badge-custom badge-primary-soft"><?php echo date('d/m/Y', strtotime($row['plan_date'])); ?></span></td>
                            <td>
                                <strong class="text-dark d-block"><?php echo htmlspecialchars($row['title']); ?></strong>
                                <span class="badge bg-light text-dark border mt-1" style="font-size:11px;font-weight:500">
                                    <i class="bi bi-person-circle text-primary me-1"></i>อัปโดย: <?php echo htmlspecialchars($row['uploader_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted small">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo $row['company_name'] ? htmlspecialchars($row['company_name']) : 'ทั้งหมด (ส่วนกลาง)'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($row['filename'])): ?>
                                <?php
                                    // [M-2] ตรวจสอบว่าไฟล์มีอยู่จริงก่อนแสดงลิงก์
                                    $plan_file_path = __DIR__ . '/../uploads/plans/' . $row['filename'];
                                ?>
                                <a href="../uploads/plans/<?php echo htmlspecialchars($row['filename']); ?>"
                                target="_blank"
                                class="btn btn-sm btn-light border rounded-pill px-3 text-danger"
                                <?= !is_file($plan_file_path) ? 'title="ไฟล์อาจถูกลบไปแล้ว" style="opacity:.6"' : '' ?>>                               
                                    <i class="bi bi-filetype-pdf me-1"></i> เปิดไฟล์
                                </a>
                                <?php else: ?>
                                <span class="text-muted small italic"><i class="bi bi-slash-circle"></i> ไม่มีไฟล์</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="edit_plan.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmDeletePlan(<?php echo (int)$row['id']; ?>)">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-5 d-block mb-2 opacity-50"></i>
                                    ยังไม่มีรายการแผนการนิเทศ
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
        url.searchParams.delete('count');
        var newUrl = url.pathname + (url.search ? url.search : '');
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', newUrl);
    });
});
</script>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    var $t = $('#plansTable');
    if ($t.find('tbody tr').length > 0 && !$t.find('tbody tr:first td').attr('colspan')) {
        $t.DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
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
function confirmDeletePlan(id) {
    Swal.fire({
        title: 'ยืนยันการลบ',
        text: 'ต้องการลบแผนการนิเทศรายการนี้จริงหรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then(function(r) {
        if (r.isConfirmed) {
            document.getElementById('deletePlanForm_id').value = id;
            document.getElementById('deletePlanForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
