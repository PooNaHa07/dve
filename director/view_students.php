<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);
$hide_welcome = true;
include '../includes/header.php';

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$show_success = isset($_GET['success']) && (int)$_GET['success'] === 1;
$show_error   = isset($_GET['error']);
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<?php if ($show_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon:'success', title:'ลงนามแล้ว', text:'ส่งกลับมาหาครูนิเทศก์เรียบร้อย', confirmButtonColor:'#4f46e5' }).then(function() {
        var u=new URL(window.location.href); u.searchParams.delete('success'); if(window.history.replaceState) window.history.replaceState({},'',(u.pathname+(u.search||'')));
    });
});
</script>
<?php endif; ?>
<?php if ($show_error): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:'ไม่สามารถดำเนินการได้ กรุณาลองใหม่', confirmButtonColor:'#ef4444' }).then(function() {
        var u=new URL(window.location.href); u.searchParams.delete('error'); if(window.history.replaceState) window.history.replaceState({},'',(u.pathname+(u.search||'')));
    });
});
</script>
<?php endif; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">👨‍🎓</div>
            <div>
                <h4>ตรวจสอบและลงนามใบนิเทศ</h4>
                <p>รายชื่อนักเรียนแยกตามห้องเรียน พร้อมสถานะใบนิเทศ</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <!-- Filter -->
    <div class="dp-filter">
        <label class="fw-semibold text-secondary small mb-2 d-block">
            <i class="bi bi-funnel me-1 text-primary"></i>เลือกห้องเรียน
        </label>
        <form method="get">
            <select name="class_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- กรุณาเลือกห้องเรียน --</option>
                <?php
                $rooms = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
                while ($r = $rooms->fetch_assoc()) {
                    $sel = ($class_id === (int)$r['id']) ? 'selected' : '';
                    echo '<option value="'.$r['id'].'" '.$sel.'>'.$r['class_name'].'</option>';
                }
                ?>
            </select>
        </form>
    </div>

    <!-- Table -->
    <div class="dp-card">
        <div class="table-responsive">
            <table id="viewStudentsTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th>รหัสนักเรียน</th>
                        <th class="text-start">ชื่อ-นามสกุล</th>
                        <th>สถานประกอบการ</th>
                        <th>ใบนิเทศ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
<?php
if ($class_id > 0) {
    $sql = "SELECT u.id as std_id, u.username, u.fullname, u.company_name,
                   f.id as sup_id, f.file_path, f.status as sup_status
            FROM users u
            LEFT JOIN supervision_files f ON u.id = f.student_id AND f.status IN (1, 2)
            WHERE u.classroom_id = ? AND u.role = 'student'
            ORDER BY u.username ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo '<tr>';
            echo '<td><span class="dp-badge dp-badge-primary">'.$row['username'].'</span></td>';
            echo '<td class="text-start fw-semibold">'.$row['fullname'].'</td>';
            echo '<td class="text-muted">'.($row['company_name'] ?: '—').'</td>';
            echo '<td>';
            if (!empty($row['file_path'])) {
                echo '<a href="../uploads/supervision_docs/original/'.htmlspecialchars($row['file_path']).'" target="_blank" class="dp-btn dp-btn-info me-1"><i class="bi bi-eye"></i>ดูไฟล์</a>';
                if ($row['sup_status'] == 1) {
                    echo '<a href="sign_supervision_form.php?id='.$row['sup_id'].'&class_id='.$class_id.'" class="dp-btn dp-btn-success"><i class="bi bi-pen"></i>ลงนาม</a>';
                } elseif ($row['sup_status'] == 2) {
                    echo '<span class="dp-badge dp-badge-success"><i class="bi bi-check-circle-fill"></i>ลงนามแล้ว</span>';
                }
            } else {
                echo '<span class="text-muted small">ยังไม่ได้ส่งใบนิเทศ</span>';
            }
            echo '</td>';
            echo '<td><span class="dp-badge dp-badge-success"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i>ปกติ</span></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5"><div class="dp-empty"><i class="bi bi-inbox"></i><p>ไม่พบข้อมูลนักเรียนในห้องนี้</p></div></td></tr>';
    }
} else {
    echo '<tr><td colspan="5"><div class="dp-empty"><i class="bi bi-arrow-up-circle"></i><p>กรุณาเลือกห้องเรียนด้านบน</p></div></td></tr>';
}
?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang = {search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl = document.getElementById('viewStudentsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        $(tbl).DataTable({ order:[[0,'asc']], language:dtLang, pageLength:25 });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>