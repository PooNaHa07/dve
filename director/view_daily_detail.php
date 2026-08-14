<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);
include '../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($id <= 0) {
    header('Location: view_daily.php' . ($class_id ? '?class_id=' . $class_id : ''));
    exit;
}

$sql = "SELECT d.*, u.fullname, u.username, cl.class_name, c.name AS company_name 
        FROM daily_reports d 
        JOIN users u ON d.student_id = u.id 
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE d.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if (!$r) {
    header('Location: view_daily.php' . ($class_id ? '?class_id=' . $class_id : ''));
    exit;
}

$back_url = 'view_daily.php' . ($class_id ? '?class_id=' . $class_id : '');
$status_label = ['pending' => 'รอตรวจ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ส่งกลับ'];
$status_class = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
$st = $r['status'] ?? 'pending';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-info"><i class="fas fa-file-alt me-2"></i>รายละเอียดบันทึกรายวัน</h3>
        <a href="<?= htmlspecialchars($back_url); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> กลับรายการ</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">ข้อมูลบันทึก</span>
                    <span class="badge bg-<?= $status_class[$st]; ?>"><?= $status_label[$st]; ?></span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <th class="text-muted" style="width: 140px;">วันที่ปฏิบัติงาน</th>
                            <td><?= date('d/m/Y', strtotime($r['date_work'])); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">รายละเอียดสิ่งที่ทำ</th>
                            <td><?= nl2br(e($r['details'] ?? '-')); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">ปัญหา / อุปสรรค</th>
                            <td><?= e($r['problems'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">วิธีแก้ไข / แนวทาง</th>
                            <td><?= e($r['solutions'] ?? '-'); ?></td>
                        </tr>
                        <?php if (!empty($r['teacher_comment'])): ?>
                        <tr>
                            <th class="text-muted">ความเห็นครู</th>
                            <td><span class="text-danger"><?= nl2br(e($r['teacher_comment'])); ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light fw-bold">ข้อมูลนักเรียน</div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= e($r['fullname']); ?></strong></p>
                    <p class="mb-1 text-muted small">รหัส: <?= e($r['username'] ?? '-'); ?></p>
                    <p class="mb-1">ห้อง: <?= e($r['class_name'] ?? '-'); ?></p>
                    <p class="mb-0">สถานที่ฝึกงาน: <?= e($r['company_name'] ?? '-'); ?></p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light fw-bold">รูปภาพประกอบ</div>
                <div class="card-body">
                    <?php if (!empty($r['image1'])): ?>
                        <div class="mb-3">
                            <img src="../uploads/images/<?= e($r['image1']); ?>" class="img-fluid rounded border" alt="รูปที่ 1" style="max-height: 200px; object-fit: contain;">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($r['image2'])): ?>
                        <div>
                            <img src="../uploads/images/<?= e($r['image2']); ?>" class="img-fluid rounded border" alt="รูปที่ 2" style="max-height: 200px; object-fit: contain;">
                        </div>
                    <?php endif; ?>
                    <?php if (empty($r['image1']) && empty($r['image2'])): ?>
                        <p class="text-muted small mb-0">ไม่มีรูปภาพ</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($r['approved_at'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body small text-muted">
                    <?= $r['status'] === 'approved' ? 'อนุมัติเมื่อ' : 'ส่งกลับเมื่อ'; ?>: <?= date('d/m/Y H:i', strtotime($r['approved_at'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
