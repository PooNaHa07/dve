<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director', 'admin']);
$hide_welcome = true;
include '../includes/header.php';

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$classes_sql = "SELECT cl.id, cl.class_name,
                COUNT(d.id) AS report_count
                FROM classrooms cl
                LEFT JOIN users u ON u.classroom_id = cl.id
                LEFT JOIN daily_reports d ON d.student_id = u.id
                GROUP BY cl.id, cl.class_name
                ORDER BY cl.class_name ASC";
$classes_res = $conn->query($classes_sql);

$class_name = '';
$reports = [];
if ($class_id > 0) {
    $cls = $conn->query("SELECT class_name FROM classrooms WHERE id = $class_id");
    if ($cls && $row = $cls->fetch_assoc()) $class_name = $row['class_name'];
    $res = $conn->query("SELECT d.id, d.date_work, d.details, d.status, d.created_at,
            u.fullname, u.username, c.name AS company_name
            FROM daily_reports d
            JOIN users u ON d.student_id = u.id
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.classroom_id = $class_id ORDER BY d.created_at DESC");
    if ($res) while ($r = $res->fetch_assoc()) $reports[] = $r;
}
?>
<link rel="stylesheet" href="../includes/director-pages.css">

<div class="container-fluid py-4">
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">📅</div>
            <div>
                <h4>บันทึกการฝึกงานประจำวัน<?= $class_name ? ' — '.$class_name : '' ?></h4>
                <p>เลือกห้องเรียนเพื่อดูบันทึกรายวันของนักเรียน</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <?php if ($class_id > 0): ?>
                <a href="view_daily.php" class="dp-back-btn" style="background:rgba(255,255,255,0.1);">
                    <i class="bi bi-grid-3x3-gap"></i> ทุกห้อง
                </a>
            <?php endif; ?>
            <a href="../roles/director.php" class="dp-back-btn">
                <i class="bi bi-house-fill"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    <?php if ($class_id === 0): ?>
    <!-- Class Grid -->
    <div class="row g-3">
        <?php if ($classes_res && $classes_res->num_rows > 0):
            while ($row = $classes_res->fetch_assoc()):
                $count = (int)$row['report_count'];
        ?>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <a href="view_daily.php?class_id=<?= (int)$row['id'] ?>" class="dp-class-card text-decoration-none">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($row['class_name']) ?></h6>
                    <span class="dp-badge dp-badge-info"><?= $count ?></span>
                </div>
                <div class="dp-class-count"><?= number_format($count) ?></div>
                <small class="text-muted">รายการบันทึก</small>
                <div class="mt-2 pt-2 border-top">
                    <span class="dp-badge dp-badge-primary" style="font-size:0.75rem;">
                        <i class="bi bi-chevron-right"></i> ดูรายการ
                    </span>
                </div>
            </a>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12">
            <div class="dp-card"><div class="dp-empty"><i class="bi bi-building"></i><p>ยังไม่มีข้อมูลห้องเรียน</p></div></div>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Reports Table -->
    <div class="dp-card">
        <div class="dp-card-header">
            <i class="bi bi-calendar-check-fill text-info"></i>
            บันทึกรายวันของห้อง <?= htmlspecialchars($class_name) ?>
        </div>
        <div class="table-responsive">
            <table class="dp-table table">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th class="text-start">นักเรียน</th>
                        <th>สถานที่ฝึกงาน</th>
                        <th>รายละเอียด</th>
                        <th>สถานะ</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports) > 0):
                        foreach ($reports as $r):
                            $st = $r['status'] ?? 'pending';
                    ?>
                    <tr>
                        <td class="text-nowrap fw-semibold"><?= date('d/m/Y', strtotime($r['date_work'])) ?></td>
                        <td class="text-start">
                            <div class="fw-semibold"><?= htmlspecialchars($r['fullname']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($r['username'] ?? '') ?></small>
                        </td>
                        <td class="text-muted"><?= $r['company_name'] ? htmlspecialchars($r['company_name']) : '—' ?></td>
                        <td><small class="text-secondary"><?= nl2br(e(mb_substr($r['details'] ?? '', 0, 80).(mb_strlen($r['details']??'')>80?'…':''))) ?></small></td>
                        <td class="text-center">
                            <?php if ($st==='approved') echo '<span class="dp-badge dp-badge-success"><i class="bi bi-check-circle-fill"></i>อนุมัติ</span>';
                            elseif ($st==='rejected') echo '<span class="dp-badge dp-badge-danger"><i class="bi bi-x-circle-fill"></i>ส่งกลับ</span>';
                            else echo '<span class="dp-badge dp-badge-warning"><i class="bi bi-clock-fill"></i>รอตรวจ</span>'; ?>
                        </td>
                        <td class="text-center">
                            <a href="view_daily_detail.php?id=<?= (int)$r['id'] ?>&class_id=<?= $class_id ?>" class="dp-btn dp-btn-outline">
                                <i class="bi bi-eye"></i>รายละเอียด
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6"><div class="dp-empty"><i class="bi bi-inbox"></i><p>ไม่พบบันทึกรายวันในห้องนี้</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
