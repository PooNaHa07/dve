<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$user_res = $conn->query("SELECT fullname, username, (SELECT class_name FROM classrooms WHERE id = users.classroom_id) as classroom FROM users WHERE id = $student_id");
$user = $user_res->fetch_assoc();

if (!$user) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container admin-content-wrapper mt-5 text-center"><div class="alert alert-danger d-inline-block shadow-sm">ไม่พบข้อมูลนักเรียนรายนี้ในระบบ</div><br><a href="view_student_reports.php" class="btn-admin-outline text-decoration-none mt-3 d-inline-block">ย้อนกลับ</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$sql = "SELECT * FROM daily_reports WHERE student_id = $student_id ORDER BY date_work DESC";
$reports = $conn->query($sql);

$img_path = "../uploads/images/";

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
.log-entry-card {
    background: white;
    border-radius: 1rem;
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.25s ease;
}
.log-entry-card:hover {
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}
.log-photo-frame {
    height: 140px;
    width: 100%;
    object-fit: cover;
    border-radius: 0.75rem;
    transition: opacity 0.2s;
}
.log-photo-frame:hover {
    opacity: 0.85;
}
.bubble-content {
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    font-size: 0.95rem;
    color: #334155;
}
</style>

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4 flex-column flex-md-row align-items-start align-items-md-center">
        <div>
            <h2 class="admin-header-title mb-1">
                <i class="fas fa-journal-whills"></i>
                สมุดบันทึกประสบการณ์วิชาชีพ
            </h2>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="admin-badge bg-light text-primary border font-weight-bold"><i class="fas fa-user-graduate me-1"></i><?php echo htmlspecialchars($user['fullname']); ?></span>
                <span class="admin-badge bg-light text-muted border"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($user['classroom'] ?? 'ไม่ระบุห้องเรียน'); ?></span>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0 align-self-end align-self-md-center">
            <a href="view_student_reports.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-chevron-left me-1"></i> ย้อนกลับ
            </a>
            <a href="print_report.php?student_id=<?php echo $student_id; ?>" target="_blank" class="btn-admin-primary text-decoration-none">
                <i class="fas fa-print me-2"></i>พิมพ์รายงาน
            </a>
        </div>
    </div>

    <?php if ($reports && $reports->num_rows > 0): while($rp = $reports->fetch_assoc()): ?>
    <div class="log-entry-card shadow-sm mb-4 overflow-hidden">
        <div class="d-flex justify-content-between align-items-center bg-light px-4 py-3 border-bottom">
            <div class="fw-bold text-dark fs-6">
                <i class="far fa-calendar-check me-2 text-primary"></i>
                บันทึกการฝึกงาน ประจำวันที่: <?php echo date('d/m/Y', strtotime($rp['date_work'])); ?>
            </div>
            
            <div>
                <?php if($rp['status'] == 'approved'): ?>
                    <span class="admin-badge bg-success bg-opacity-10 text-success border-success border-opacity-25 fw-bold px-3">
                        <i class="fas fa-check-circle me-1 small"></i>อนุมัติแล้ว
                    </span>
                <?php elseif($rp['status'] == 'rejected'): ?>
                    <span class="admin-badge bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 fw-bold px-3">
                        <i class="fas fa-times-circle me-1 small"></i>ให้แก้ไขข้อมูล
                    </span>
                <?php else: ?>
                    <span class="admin-badge bg-warning bg-opacity-10 text-warning border-warning border-opacity-25 fw-bold px-3">
                        <i class="fas fa-hourglass-half me-1 small"></i>รอตรวจสอบ
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-4 bg-white">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="mb-3">
                        <label class="text-muted small fw-bold mb-1 text-uppercase tracking-wider">รายละเอียดภาระงาน</label>
                        <div class="bubble-content" style="border-left: 4px solid var(--admin-primary);">
                            <?php echo nl2br(htmlspecialchars($rp['details'])); ?>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-danger small fw-bold mb-1"><i class="fas fa-exclamation-circle me-1"></i> ปัญหา/อุปสรรค:</label>
                            <div class="rounded-3 p-3 small" style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; min-height: 60px;">
                                <?php echo $rp['problems'] ? nl2br(htmlspecialchars($rp['problems'])) : '<em class="opacity-50">ไม่ระบุปัญหา</em>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-success small fw-bold mb-1"><i class="fas fa-lightbulb me-1"></i> แนวทางแก้ไข:</label>
                            <div class="rounded-3 p-3 small" style="background: #f0fdf4; border: 1px solid #dcfce7; color: #166534; min-height: 60px;">
                                <?php echo $rp['solutions'] ? nl2br(htmlspecialchars($rp['solutions'])) : '<em class="opacity-50">ไม่ระบุแนวทางแก้ไข</em>'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <label class="text-muted small fw-bold mb-2 text-uppercase"><i class="far fa-image me-1"></i> รูปภาพประกอบ</label>
                    <div class="row g-2">
                        <?php 
                        $has_image = false;
                        $images = array_filter([$rp['image1'], $rp['image2']]); 
                        foreach($images as $img): 
                            $has_image = true; 
                            $adm_img_url = get_report_image_url($img);
                        ?>
                            <div class="col-6">
                                <a href="<?php echo $adm_img_url; ?>" target="_blank" class="d-block shadow-sm rounded-3 overflow-hidden border">
                                    <img src="<?php echo $adm_img_url; ?>" class="log-photo-frame" alt="Work log picture" onerror="if(!this.dataset.tried){this.dataset.tried='1'; if(this.src.includes('/uploads/images/')){this.src=this.src.replace('/uploads/images/','/uploads/reports/');}else{this.src=this.src.replace('/uploads/reports/','/uploads/images/');}}else{this.onerror=null; this.src='https://via.placeholder.com/250x250?text=No+Image';}">
                                </a>
                            </div>
                        <?php endforeach; ?>

                        <?php if(!$has_image): ?>
                            <div class="col-12">
                                <div class="bg-light d-flex flex-column align-items-center justify-content-center py-4 rounded-3 text-muted border border-dashed" style="height: 140px;">
                                    <i class="far fa-images fs-2 opacity-25 mb-2"></i>
                                    <div class="small">ไม่มีรูปภาพแนบมา</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if(!empty($rp['teacher_comment'])): ?>
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-start gap-2 bg-light p-3 rounded-3 border border-info border-opacity-25">
                    <i class="fas fa-comment-dots text-info fs-5 mt-1"></i>
                    <div>
                        <div class="small fw-bold text-info mb-1">คำแนะนำ/ข้อคิดเห็นจากครูนิเทศก์:</div>
                        <div class="text-dark small"><?php echo htmlspecialchars($rp['teacher_comment']); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; else: ?>
        <div class="bg-white text-center py-5 rounded-4 border shadow-sm">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                <i class="fas fa-calendar-times fs-2 text-muted opacity-50"></i>
            </div>
            <p class="text-muted fs-6 mb-0">ยังไม่พบประวัติการลงบันทึกการฝึกประสบการณ์ของนักเรียนคนนี้</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>