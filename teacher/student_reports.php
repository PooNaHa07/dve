<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id'];

// 1. รับ ID นักเรียนที่ต้องการดู
$student_id = isset($_GET['student_id']) ? $conn->real_escape_string($_GET['student_id']) : 0;

// 2. ดึงข้อมูลนักเรียนและตรวจสอบว่าอยู่ในห้องที่ครูดูแลหรือไม่
// [SECURITY WARNING]: ต้องมี JOIN teacher_assignments และเช็ค ta.teacher_id เสมอ!
// เพื่อป้องกันช่องโหว่ IDOR (Insecure Direct Object Reference) ที่ทำให้ครูดูรายงานเด็กข้ามห้องได้
$sql_student = "SELECT u.*, cl.class_name, COALESCE(comp.name, u.company_name) as company_name 
                FROM users u 
                LEFT JOIN classrooms cl ON u.classroom_id = cl.id 
                LEFT JOIN companies comp ON u.company_id = comp.id
                JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
                WHERE u.id = '$student_id' AND u.role = 'student'
                AND ta.teacher_id = '$teacher_id'";
$res_student = $conn->query($sql_student);
$student_data = $res_student->fetch_assoc();

if (!$student_data) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>ไม่พบข้อมูลนักเรียน หรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้</div></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="manage_students.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-person-badge icon-gradient"></i></div>
            ประวัติบันทึกการฝึกงานรายบุคคล
        </h4>
    </div>

    <div class="premium-card p-4 mb-4 bg-white">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="bg-primary-soft rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                <i class="bi bi-person-circle text-primary fs-2"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($student_data['fullname']); ?></h5>
                </div>
                <div class="d-flex flex-wrap gap-3 text-muted small">
                    <div><i class="bi bi-door-open-fill me-1"></i> <?php echo htmlspecialchars($student_data['class_name'] ?: 'ยังไม่ระบุชั้นเรียน'); ?></div>
                    <?php if(!empty($student_data['company_name'])): ?>
                        <div><i class="bi bi-briefcase-fill me-1"></i> <?php echo htmlspecialchars($student_data['company_name']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // แก้ไข status query ให้ตรงกับ standard
    $stats = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' OR status = '1' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM daily_reports WHERE student_id = '$student_id'")->fetch_assoc();
    ?>
    <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
        <div class="col">
            <div class="premium-card p-3 h-100 text-center" style="border-top: 4px solid #6c757d;">
                <div class="text-muted small mb-1">ส่งทั้งหมด</div>
                <h3 class="mb-0 fw-bold text-dark"><?php echo (int)$stats['total']; ?></h3>
            </div>
        </div>
        <div class="col">
            <div class="premium-card p-3 h-100 text-center" style="border-top: 4px solid #28a745;">
                <div class="text-success small mb-1 fw-bold">อนุมัติแล้ว</div>
                <h3 class="mb-0 fw-bold text-success"><?php echo (int)$stats['approved']; ?></h3>
            </div>
        </div>
        <div class="col">
            <div class="premium-card p-3 h-100 text-center" style="border-top: 4px solid #007bff;">
                <div class="text-primary small mb-1 fw-bold">รอตรวจ</div>
                <h3 class="mb-0 fw-bold text-primary"><?php echo (int)$stats['pending']; ?></h3>
            </div>
        </div>
        <div class="col">
            <div class="premium-card p-3 h-100 text-center" style="border-top: 4px solid #dc3545;">
                <div class="text-danger small mb-1 fw-bold">รอนักเรียนส่งใหม่</div>
                <h3 class="mb-0 fw-bold text-danger"><?php echo (int)$stats['rejected']; ?></h3>
            </div>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header bg-white">
            <div><i class="bi bi-journal-text text-primary me-2"></i> ตารางรายการบันทึกประจำวัน</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="studentReportsTable" class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 110px;">วันที่ฝึกงาน</th>
                            <th>รายละเอียดงาน</th>
                            <th>ปัญหาและการแก้ไข</th>
                            <th class="text-center" style="width: 120px;">รูปภาพ</th>
                            <th>หมายเหตุจากครู</th>
                            <th class="text-center" style="width: 130px;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_reports = "SELECT * FROM daily_reports WHERE student_id = '$student_id' ORDER BY date_work DESC";
                        $res_reports = $conn->query($sql_reports);

                        if ($res_reports && $res_reports->num_rows > 0):
                            while($row = $res_reports->fetch_assoc()):
                                $has_imgs = (!empty($row['image1']) || !empty($row['image2']));
                        ?>
                        <tr class="align-top">
                            <td class="pt-3">
                                <span class="badge badge-custom badge-primary-soft">
                                    <?php echo date('d/m/Y', strtotime($row['date_work'])); ?>
                                </span>
                            </td>
                            <td class="pt-3">
                                <div class="text-dark small lh-base" style="min-width: 160px;"><?php echo nl2br(htmlspecialchars($row['details'])); ?></div>
                            </td>
                            <td class="pt-3">
                                <?php if(!empty($row['problems'])): ?>
                                    <div class="text-danger small bg-danger-soft p-2 px-3 rounded-3 border border-danger-subtle border-dashed mb-2">
                                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> ปัญหาที่พบ:</div>
                                        <?php echo nl2br(htmlspecialchars($row['problems'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(!empty($row['solutions'])): ?>
                                    <div class="text-success small bg-success-soft p-2 px-3 rounded-3 border border-success-subtle border-dashed">
                                        <div class="fw-bold mb-1"><i class="bi bi-shield-check me-1"></i> การแก้ไขปัญหา:</div>
                                        <?php echo nl2br(htmlspecialchars($row['solutions'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(empty($row['problems']) && empty($row['solutions'])): ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pt-3">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <?php if (!empty($row['image1'])): 
                                        $sr_img1 = get_report_image_url($row['image1']);
                                    ?>
                                        <div class="img-container-mini shadow-sm rounded border" style="cursor:pointer; width: 42px; height: 42px; overflow: hidden;">
                                            <img src="<?php echo $sr_img1; ?>" class="w-100 h-100 object-fit-cover viewable-img" alt="หลักฐาน 1" onerror="handleReportImgError(this, '<?php echo htmlspecialchars($row['image1'], ENT_QUOTES); ?>')">
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['image2'])): 
                                        $sr_img2 = get_report_image_url($row['image2']);
                                    ?>
                                        <div class="img-container-mini shadow-sm rounded border" style="cursor:pointer; width: 42px; height: 42px; overflow: hidden;">
                                            <img src="<?php echo $sr_img2; ?>" class="w-100 h-100 object-fit-cover viewable-img" alt="หลักฐาน 2" onerror="handleReportImgError(this, '<?php echo htmlspecialchars($row['image2'], ENT_QUOTES); ?>')">
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!$has_imgs): ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="pt-3">
                                <?php if($row['teacher_comment']): ?>
                                    <div class="text-info small bg-info-soft p-2 rounded-3"><i class="bi bi-chat-square-text me-1"></i> <?php echo htmlspecialchars($row['teacher_comment']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted small italic">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pt-3">
                                <?php 
                                if($row['status'] == '1' || $row['status'] == 'approved') {
                                    echo '<span class="badge badge-custom badge-success-soft w-100 py-2"><i class="bi bi-check-circle-fill me-1"></i> ผ่าน</span>';
                                } elseif($row['status'] == 'rejected') {
                                    echo '<span class="badge badge-custom badge-danger-soft w-100 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> รอนักเรียนส่งใหม่</span>';
                                } elseif($row['status'] == 'pending' && !empty($row['teacher_comment'])) {
                                    echo '<span class="badge badge-custom bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 w-100 py-2 fw-bold" style="font-size:0.75rem;"><i class="bi bi-arrow-repeat me-1"></i> ส่งแก้ไขใหม่</span>';
                                } else {
                                    echo '<span class="badge badge-custom badge-warning-soft w-100 py-2"><i class="bi bi-clock-history me-1"></i> รอตรวจ</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-clipboard-x display-5 text-muted opacity-50 d-block mb-2"></i>
                                <span class="text-muted">นักเรียนคนนี้ยังไม่ได้ส่งบันทึก</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function handleReportImgError(img, filename) {
    if (!img.dataset.triedFallback) {
        img.dataset.triedFallback = '1';
        if (img.src.includes('/uploads/reports/')) {
            img.src = img.src.replace('/uploads/reports/', '/uploads/images/');
            return;
        } else if (img.src.includes('/uploads/images/')) {
            img.src = img.src.replace('/uploads/images/', '/uploads/reports/');
            return;
        }
    }
    img.onerror = null;
    img.src = 'https://via.placeholder.com/250x250?text=No+Image';
}

document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('studentReportsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_ รายการ)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
        $(tbl).DataTable({ order: [[0, 'desc']], language: dtLang, pageLength: 15, columnDefs: [{ orderable: false, targets: [3,5] }] });
    }
    
    // ✨ Image Preview Lightbox
    $('.viewable-img').on('click', function() {
        Swal.fire({
            imageUrl: $(this).attr('src'),
            imageAlt: $(this).attr('alt'),
            showCloseButton: true,
            showConfirmButton: false,
            background: '#fff',
            backdrop: 'rgba(0,0,0,0.75)',
            customClass: {
                image: 'rounded-3 shadow-lg'
            }
        });
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>