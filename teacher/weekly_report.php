<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

// คำนวณวันที่เริ่มต้นและสิ้นสุดของสัปดาห์นี้
$monday = date("Y-m-d", strtotime("last monday"));
$sunday = date("Y-m-d", strtotime("next sunday"));
$u = current_user();
$teacher_id = $u['id'];

// 1. สรุปสถานะการส่งงานประจำสัปดาห์ (เฉพาะนักเรียนในปกครอง)
$sql_stat = "SELECT 
                SUM(CASE WHEN dr.status = '1' OR dr.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN dr.status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM daily_reports dr
             JOIN users u ON dr.student_id = u.id
             JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
             WHERE ta.teacher_id = '$teacher_id' AND dr.date_work BETWEEN '$monday' AND '$sunday'";
$res_stat = $conn->query($sql_stat);
$stats = $res_stat ? $res_stat->fetch_assoc() : ['approved'=>0, 'pending'=>0];

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-bar-chart-line icon-gradient"></i></div>
            รายงานผลการส่งบันทึกรายสัปดาห์
        </h4>
    </div>

    <div class="premium-card p-3 mb-4 bg-white border-start border-primary border-4 rounded">
        <div class="d-flex align-items-center">
            <i class="bi bi-calendar-range text-primary fs-3 me-3"></i>
            <div>
                <span class="text-muted small">ข้อมูลระหว่างสัปดาห์</span>
                <h6 class="mb-0 fw-bold"><?php echo date('d/m/Y', strtotime($monday)); ?> ถึง <?php echo date('d/m/Y', strtotime($sunday)); ?></h6>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="premium-card h-100 overflow-hidden">
                <div class="card-body text-center p-4 relative">
                    <div class="text-success-emphasis small fw-bold mb-1">อนุมัติแล้ว</div>
                    <h1 class="display-4 fw-bold text-success"><?php echo (int)($stats['approved'] ?? 0); ?></h1>
                    <div class="small text-muted">รายการบันทึกประจำวัน</div>
                </div>
                <div style="height:5px; background: linear-gradient(90deg, #28a745, #34ce57);"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="premium-card h-100 overflow-hidden">
                <div class="card-body text-center p-4 relative">
                    <div class="text-warning-dark small fw-bold mb-1" style="color:#b08000">รอการตรวจสอบ</div>
                    <h1 class="display-4 fw-bold text-warning"><?php echo (int)($stats['pending'] ?? 0); ?></h1>
                    <div class="small text-muted">รายการบันทึกประจำวัน</div>
                </div>
                <div style="height:5px; background: linear-gradient(90deg, #ffc107, #ffdb6a);"></div>
            </div>
        </div>
    </div>

    <div class="premium-card">
        <div class="premium-card-header bg-white">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i> สรุปแยกตามรายชื่อนักเรียนในปกครอง</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-tch align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">รายชื่อนักเรียน</th>
                            <th>ห้องเรียน</th>
                            <th class="text-center">จำนวนบันทึก</th>
                            <th class="text-center">สถานะสัปดาห์นี้</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ดึงเฉพาะนักเรียนที่อยู่ในความรับผิดชอบของครู (JOIN teacher_assignments)
                        $sql_list = "SELECT u.student_code, u.fullname, cl.class_name, COUNT(dr.id) as total_sent 
                                     FROM users u 
                                     JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
                                     LEFT JOIN classrooms cl ON u.classroom_id = cl.id
                                     LEFT JOIN daily_reports dr ON u.id = dr.student_id 
                                     AND dr.date_work BETWEEN '$monday' AND '$sunday'
                                     WHERE ta.teacher_id = '$teacher_id' AND u.role = 'student'
                                     GROUP BY u.id
                                     ORDER BY cl.class_name ASC, u.student_code ASC";
                        $res_list = $conn->query($sql_list);
                        if ($res_list && $res_list->num_rows > 0):
                            while($row = $res_list->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-light border text-muted"><?php echo htmlspecialchars($row['class_name'] ?? '-'); ?></span>
                            </td>
                            <td class="text-center fw-bold text-primary fs-5">
                                <?php echo (int)$row['total_sent']; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['total_sent'] >= 5): ?>
                                    <span class="badge badge-custom badge-success-soft px-3 py-2"><i class="bi bi-check2-circle me-1"></i> ครบถ้วน (5+ วัน)</span>
                                <?php elseif($row['total_sent'] > 0): ?>
                                    <span class="badge badge-custom badge-warning-soft px-3 py-2"><i class="bi bi-exclamation-circle me-1"></i> ยังไม่ครบ (<?php echo $row['total_sent']; ?>/5)</span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-danger-soft px-3 py-2"><i class="bi bi-x-octagon me-1"></i> ยังไม่เริ่มส่ง</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x d-block mb-2 fs-3"></i>
                                ไม่พบคอมูลนักเรียนในปกครอง หรือไม่มีบันทึกในสัปดาห์นี้
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>