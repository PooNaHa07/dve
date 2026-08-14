<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);
$hide_welcome = true;
include '../includes/header.php';

$res = $conn->query("
    SELECT sf.id, sf.file_path, sf.uploaded_at, sf.student_id,
           u.fullname  AS teacher_name,
           std.fullname AS student_name, std.student_code,
           COALESCE(std.classroom_id, 0) AS class_id
    FROM supervision_files sf
    JOIN users u   ON sf.teacher_id = u.id
    LEFT JOIN users std ON sf.student_id = std.id
    WHERE sf.status = 1 AND sf.director_signed_at IS NULL
    ORDER BY sf.uploaded_at DESC
");
if (!$res) die('SQL ERROR: ' . $conn->error);
$total = $res->num_rows;

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
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'success',title:'ลงนามแล้ว',text:'ส่งกลับมาหาครูนิเทศก์เรียบร้อย',confirmButtonColor:'#4f46e5'}).then(function(){var u=new URL(window.location.href);u.searchParams.delete('success');if(window.history.replaceState)window.history.replaceState({},'',u.pathname+(u.search||''));});});</script>
<?php endif; ?>
<?php if ($show_error): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',text:'ไม่สามารถดำเนินการได้ กรุณาลองใหม่',confirmButtonColor:'#ef4444'}).then(function(){var u=new URL(window.location.href);u.searchParams.delete('error');if(window.history.replaceState)window.history.replaceState({},'',u.pathname+(u.search||''));});});</script>
<?php endif; ?>

<div class="container mt-4 pb-5">
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">✍️</div>
            <div>
                <h4>เอกสารรอผู้บริหารลงนาม</h4>
                <p>กด <strong>"เปิดเอกสารและลงนาม"</strong> เพื่อลงนามบนเอกสารได้เลย • รอลงนาม <strong><?= $total ?></strong> ฉบับ</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <?php if ($total === 0): ?>
    <div class="dp-card">
        <div style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:3.5rem;margin-bottom:1rem;">✅</div>
            <h5 class="fw-bold text-success">ไม่มีเอกสารค้างลงนาม!</h5>
            <p class="text-muted">เอกสารทุกฉบับได้รับการลงนามเรียบร้อยแล้ว</p>
        </div>
    </div>
    <?php else: ?>
    <div class="dp-card">
        <div class="dp-card-header">
            <i class="bi bi-pencil-square text-primary"></i>
            รายการเอกสารรอลงนาม
        </div>
        <div class="table-responsive">
            <table id="viewWaitSignTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start" style="width: 80px;">ลำดับ</th>
                        <th>วันที่ส่ง</th>
                        <th class="text-start">ครูนิเทศก์</th>
                        <th class="text-start">รหัสนักศึกษา</th>
                        <th class="text-start">นักเรียน</th>
                        <th>เอกสาร</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $i = 1;
                while($r = $res->fetch_assoc()):
                    $class_id = (int)$r['class_id'];
                    $sign_url = 'sign_supervision_form.php?id='.(int)$r['id'].'&class_id='.$class_id.'&return_to=view_wait_sign';
                ?>
                    <tr>
                        <td class="text-start text-secondary fw-bold">
                            <?= $i++ ?>
                        </td>
                        <td class="text-nowrap text-muted small" data-order="<?= strtotime($r['uploaded_at']) ?>">
                            <?= $r['uploaded_at'] ? date('d/m/Y H:i', strtotime($r['uploaded_at'])) : '—' ?>
                        </td>
                        <td class="text-start">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.8rem;font-weight:700;flex-shrink:0;">
                                    <?= mb_substr($r['teacher_name'], 0, 1) ?>
                                </div>
                                <span class="fw-semibold"><?= htmlspecialchars($r['teacher_name']) ?></span>
                            </div>
                        </td>
                        <td class="text-start">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold" style="font-size:.8rem; letter-spacing:.5px;"><?= htmlspecialchars($r['student_code'] ?? '-') ?></span>
                        </td>
                        <td class="text-start">
                            <?php if (!empty($r['student_name'])): ?>
                                <div class="fw-semibold"><?= htmlspecialchars($r['student_name']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($r['file_path'])): ?>
                            <a href="view.php?id=<?= (int)$r['id'] ?>" target="_blank" class="dp-btn dp-btn-info">
                                <i class="bi bi-file-earmark-pdf"></i>เปิดเอกสาร
                            </a>
                            <?php else: ?>
                                <span class="text-muted small">ไม่มีไฟล์แนบ</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= htmlspecialchars($sign_url) ?>" class="dp-btn dp-btn-success">
                                <i class="bi bi-pen-fill"></i>ลงนาม
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang={search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl=document.getElementById('viewWaitSignTable');
    if(tbl) $(tbl).DataTable({order:[[3,'asc']],language:dtLang,pageLength:10,columnDefs:[{orderable:false,targets:[5,6]}]});
});
</script>
<?php include '../includes/footer.php'; ?>
