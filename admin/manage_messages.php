<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM contact_messages WHERE id = $id");
    header("Location: manage_messages.php?msg=deleted");
    exit();
}

if (isset($_GET['read_id'])) {
    $id = intval($_GET['read_id']);
    $conn->query("UPDATE contact_messages SET status = 'read' WHERE id = $id");
    header("Location: manage_messages.php?msg=updated");
    exit();
}

if (isset($_POST['update_note'])) {
    $id = intval($_POST['message_id']);
    $note = mysqli_real_escape_string($conn, $_POST['staff_note']);
    $conn->query("UPDATE contact_messages SET staff_note = '$note', status = 'read' WHERE id = $id");
    
    // Send a notification to the user if they are registered
    $msg_res = $conn->query("SELECT user_id, subject FROM contact_messages WHERE id = $id");
    if ($msg_res && $msg_row = $msg_res->fetch_assoc()) {
        $msg_user_id = intval($msg_row['user_id']);
        $msg_subject = $msg_row['subject'];
        if ($msg_user_id > 0) {
            $title = mysqli_real_escape_string($conn, "ได้รับการตอบกลับข้อความ [$msg_subject]");
            $message = mysqli_real_escape_string($conn, "เจ้าหน้าที่ได้ตอบกลับข้อความติดต่อของคุณแล้ว: " . mb_substr($_POST['staff_note'], 0, 80) . (mb_strlen($_POST['staff_note']) > 80 ? '...' : ''));
            $action_url = mysqli_real_escape_string($conn, "/DVE_DATA_FULL/contact.php");
            $conn->query("INSERT INTO notifications (user_id, title, message, is_read, type, action_url) VALUES ($msg_user_id, '$title', '$message', 0, 'info', '$action_url')");
        }
    }
    
    header("Location: manage_messages.php?msg=note_updated");
    exit();
}

$result = $conn->query("
    SELECT cm.*, u.username, u.role AS user_role 
    FROM contact_messages cm 
    LEFT JOIN users u ON cm.user_id = u.id 
    ORDER BY cm.status ASC, cm.created_at DESC
");
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
    <?php if (isset($_GET['msg'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'success', title: 'ดำเนินการสำเร็จ', text: 'ระบบทำรายการเสร็จสิ้นแล้ว', confirmButtonText: 'ตกลง', confirmButtonColor: '#4f46e5' });
        });
        </script>
    <?php endif; ?>

    <div class="admin-header-section">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-envelope-open-text"></i>
                จัดการข้อความติดต่อ
            </h2>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <div class="admin-table-container shadow-sm">
        <div class="table-responsive bg-white">
            <table id="manageMessagesTable" class="table admin-table table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 100px;">สถานะ</th>
                        <th>รายละเอียดข้อความ</th>
                        <th>บันทึกเจ้าหน้าที่</th>
                        <th class="text-center" style="width: 220px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $modal_html = '';
                    if ($result && $result->num_rows > 0): 
                        while ($row = $result->fetch_assoc()): 
                            $isUnread = ($row['status'] == 'unread');
                            
                            $role_badge = '';
                            if (!empty($row['user_role'])) {
                                $role_label = '';
                                $role_cls = 'bg-secondary text-white';
                                if ($row['user_role'] === 'student') { $role_label = 'นักเรียน/นักศึกษา'; $role_cls = 'bg-info text-dark'; }
                                elseif ($row['user_role'] === 'teacher') { $role_label = 'ครู'; $role_cls = 'bg-primary text-white'; }
                                elseif ($row['user_role'] === 'staff') { $role_label = 'เจ้าหน้าที่'; $role_cls = 'bg-warning text-dark'; }
                                elseif ($row['user_role'] === 'admin') { $role_label = 'ผู้ดูแลระบบ'; $role_cls = 'bg-danger text-white'; }
                                elseif ($row['user_role'] === 'director') { $role_label = 'ผู้บริหาร'; $role_cls = 'bg-success text-white'; }
                                $role_badge = ' <span class="badge ' . $role_cls . ' ms-1" style="font-size: 0.75rem; font-weight:600; vertical-align: middle;">' . $role_label . '</span>';
                            }
                            
                            // 📝 Start buffering modal markup
                            $modal_html .= '
                            <!-- View Modal -->
                            <div class="modal fade admin-modal" id="viewModal'.$row['id'].'" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header">
                                            <h5 class="modal-title d-flex align-items-center text-white">
                                                <i class="fas fa-envelope-open me-2 opacity-75"></i>
                                                รายละเอียดข้อความ
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-white text-start">
                                            <div class="mb-3 border-bottom pb-2">
                                                <small class="text-muted d-block mb-1">ผู้ส่ง:</small>
                                                <div class="fw-bold text-dark fs-5">'.htmlspecialchars($row['sender_name']).$role_badge.'</div>
                                            </div>
                                            <div class="mb-3 border-bottom pb-2">
                                                <small class="text-muted d-block mb-1">ข้อมูลติดต่อ:</small>
                                                <div class="text-primary fw-medium"><i class="fas fa-paper-plane me-2"></i>'.htmlspecialchars($row['contact_info']).'</div>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-2">เนื้อหา:</small>
                                                <div class="p-3 bg-light rounded-3 text-dark border" style="min-height: 100px; white-space: pre-line;">
                                                    '.htmlspecialchars($row['message']).'
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top-0 bg-light px-4 py-3 d-flex justify-content-between">
                                            <button type="button" class="btn-admin-outline bg-white" data-bs-dismiss="modal">ปิด</button>';
                            
                            if($row['status'] == 'unread') {
                                $modal_html .= '
                                            <a href="?read_id='.$row['id'].'" class="btn-admin-primary px-4 text-decoration-none text-white">
                                                <i class="fas fa-check me-2"></i>รับทราบเรื่อง
                                            </a>';
                            }
                            
                            $modal_html .= '
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Note Modal -->
                            <div class="modal fade admin-modal" id="editModal'.$row['id'].'" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form action="" method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title d-flex align-items-center text-white">
                                                    <i class="fas fa-edit me-2 opacity-75"></i>
                                                    บันทึกเจ้าหน้าที่
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4 bg-white text-start">
                                                <input type="hidden" name="message_id" value="'.$row['id'].'">
                                                <label class="admin-form-label fw-bold mb-2 text-dark">กรอกบันทึกเพื่อติดตามการจัดการเรื่อง:</label>
                                                <textarea name="staff_note" class="form-control admin-form-control" rows="5" placeholder="พิมพ์บันทึกความคืบหน้าได้ที่นี่...">'.htmlspecialchars($row['staff_note']).'</textarea>
                                            </div>
                                            <div class="modal-footer border-top-0 bg-light px-4 py-3 d-flex justify-content-between">
                                                <button type="button" class="btn-admin-outline bg-white" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" name="update_note" class="btn-admin-primary px-4">
                                                    <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>';
                    ?>
                    <tr class="<?php echo $isUnread ? 'bg-primary bg-opacity-10' : ''; ?>">
                        <td>
                            <?php if ($isUnread): ?>
                                <span class="admin-badge bg-danger text-white border-0 fw-bold shadow-sm"><i class="fas fa-circle me-1 small pulse"></i>ใหม่</span>
                            <?php else: ?>
                                <span class="admin-badge bg-light text-muted border-secondary border-opacity-25">อ่านแล้ว</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark fs-6 mb-1"><?php echo htmlspecialchars($row['sender_name']) . $role_badge; ?></div>
                            <div class="text-muted small"><i class="fas fa-tag me-1 opacity-50"></i><?php echo htmlspecialchars($row['subject']); ?></div>
                        </td>
                        <td>
                            <div class="small <?php echo empty($row['staff_note']) ? 'text-muted font-italic' : 'text-primary fw-medium'; ?>">
                                <?php echo !empty($row['staff_note']) ? '<i class="fas fa-comment-dots me-1"></i> '.htmlspecialchars($row['staff_note']) : '<em>ยังไม่มีบันทึก</em>'; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="btn-group action-btn-group">
                                <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>" title="ดูรายละเอียด">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" title="บันทึกเจ้าหน้าที่">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                                <button class="btn btn-danger btn-delete-msg" data-url="?delete_id=<?php echo $row['id']; ?>" title="ลบข้อความ">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-inbox d-block fs-2 mb-2 opacity-50"></i>ไม่มีข้อความติดต่อในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Output Modals here, safely outside the table -->
<?php echo $modal_html; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('manageMessagesTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_ รายการ', 
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', 
            infoEmpty: 'ไม่มีข้อมูล', 
            infoFiltered: '(กรองจาก _MAX_ รายการ)', 
            paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, 
            zeroRecords: 'ไม่พบข้อมูล' 
        };
        $(tbl).DataTable({ 
            order: [[0, 'asc']], 
            language: dtLang, 
            pageLength: 25,
            dom: '<"d-flex justify-content-between align-items-center mb-3 px-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 px-2"ip>',
            columnDefs: [{ orderable: false, targets: 3 }] 
        });
    }

    $(document).on('click', '.btn-delete-msg', function(e) {
        e.preventDefault();
        var targetUrl = $(this).data('url');
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'คุณแน่ใจหรือไม่ที่จะลบข้อความติดต่อนี้อย่างถาวร?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'ลบข้อความ',
            cancelButtonText: 'ยกเลิก',
            borderRadius: '1rem'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = targetUrl;
            }
        });
    });
});
</script>
<style>
.pulse {
    animation: pulse-anim 1.5s infinite;
}
@keyframes pulse-anim {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>