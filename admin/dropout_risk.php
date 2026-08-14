<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

$msg = '';
$error = '';

// Handle quick notify post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'notify_student') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $days_inactive = (int)($_POST['days_inactive'] ?? 0);
    
    if ($student_id > 0) {
        $msg_text = "⚠️ [แจ้งเตือนระบบฝึกงาน]: คุณไม่ได้บันทึกรายงานการฝึกงานประจำวันมาแล้ว {$days_inactive} วัน กรุณาเข้าสู่ระบบเพื่อบันทึกรายงานย้อนหลังให้ครบถ้วน";
        
        $ins = $conn->prepare("INSERT INTO contact_messages (student_id, message, status, created_at) VALUES (?, ?, 'read', NOW())");
        $ins->bind_param("is", $student_id, $msg_text);
        if ($ins->execute()) {
            log_audit('NOTIFY_DROPOUT_RISK', "ส่งข้อความแจ้งเตือนนักเรียนเสี่ยงขาดส่งรายงาน ID #{$student_id} ({$days_inactive} วัน)");
            $msg = "ส่งข้อความแจ้งเตือนเตือนนักเรียนเรียบร้อยแล้ว";
        } else {
            $error = "ไม่สามารถส่งข้อความแจ้งเตือนได้";
        }
        $ins->close();
    }
}

// Fetch classrooms for dropdown filter
$classrooms = [];
$c_res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
if ($c_res) {
    while ($c = $c_res->fetch_assoc()) $classrooms[] = $c;
}

$selected_classroom = (int)($_GET['classroom_id'] ?? 0);
$selected_level = trim($_GET['level'] ?? '');

// Base SQL query to find student activity status
$sql = "SELECT u.id, u.student_code, u.fullname, u.phone, u.company_name, u.student_level,
               c.class_name,
               MAX(dr.created_at) AS last_report_time,
               MAX(dr.date_work) AS last_work_date,
               COUNT(dr.id) AS total_reports,
               DATEDIFF(CURRENT_DATE(), IFNULL(MAX(dr.date_work), '2000-01-01')) AS days_inactive
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        LEFT JOIN daily_reports dr ON u.id = dr.student_id
        WHERE u.role = 'student'";

if ($selected_classroom > 0) {
    $sql .= " AND u.classroom_id = " . $selected_classroom;
}
if (!empty($selected_level)) {
    $sql .= " AND u.student_level = '" . $conn->real_escape_string($selected_level) . "'";
}

$sql .= " GROUP BY u.id
          HAVING days_inactive >= 3
          ORDER BY days_inactive DESC, u.fullname ASC";

$res = $conn->query($sql);
$students_at_risk = [];
$count_critical = 0; // >= 7 days
$count_high = 0;     // 4-6 days
$count_moderate = 0; // 3 days

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $days = (int)$row['days_inactive'];
        if ($days >= 7) {
            $count_critical++;
        } elseif ($days >= 4) {
            $count_high++;
        } else {
            $count_moderate++;
        }
        $students_at_risk[] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.stat-risk-card {
    background: #fff;
    border-radius: 1.25rem;
    border: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 1.25rem;
    height: 100%;
}
.stat-risk-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    color: white;
}
.badge-risk-critical { background: #ef4444; color: white; }
.badge-risk-high { background: #f97316; color: white; }
.badge-risk-mod { background: #eab308; color: white; }
</style>

<div class="container admin-content-wrapper pb-5">
    <!-- Header Section -->
    <div class="admin-header-section mb-4" style="background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 50%, #dc2626 100%);">
        <div>
            <h2 class="admin-header-title text-white">
                <i class="fas fa-user-slash me-2"></i>
                ระบบติดตามและเตือนนักเรียนเสี่ยงขาดส่งรายงาน
            </h2>
            <p class="text-white text-opacity-75 mb-0 mt-1 small">ตรวจจับนักเรียนที่ไม่ส่งรายงานฝึกงานเกิน 3 วันขึ้นไป เพื่อป้องกันการตกหล่นของการฝึกงาน</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export_reports.php?filter=risk" class="btn btn-light fw-bold rounded-pill shadow-sm px-3 text-danger">
                <i class="fas fa-file-excel me-1"></i> ส่งออก Excel
            </a>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none text-white border-white">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" style="border-radius:0.85rem;">
        <i class="fas fa-check-circle fs-4 me-3"></i>
        <div><?= e($msg) ?></div>
    </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-risk-card border-start border-4 border-danger">
                <div class="stat-risk-icon bg-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span class="fs-3 fw-bold text-danger d-block"><?= $count_critical ?> คน</span>
                    <span class="text-muted small fw-semibold">เสี่ยงสูงมาก (ไม่ส่ง 7 วันขึ้นไป)</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-risk-card border-start border-4 border-warning">
                <div class="stat-risk-icon bg-warning text-dark">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <span class="fs-3 fw-bold text-warning text-darken d-block"><?= $count_high ?> คน</span>
                    <span class="text-muted small fw-semibold">เตือนระดับสูง (ไม่ส่ง 4-6 วัน)</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-risk-card border-start border-4 border-info">
                <div class="stat-risk-icon bg-info">
                    <i class="fas fa-eye"></i>
                </div>
                <div>
                    <span class="fs-3 fw-bold text-info d-block"><?= $count_moderate ?> คน</span>
                    <span class="text-muted small fw-semibold">ต้องเฝ้าระวัง (ไม่ส่ง 3 วัน)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm admin-card mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="classroom_id" class="form-select rounded-pill shadow-none" onchange="this.form.submit()">
                        <option value="0">-- เลือกห้องเรียนทั้งหมด --</option>
                        <?php foreach ($classrooms as $cr): ?>
                        <option value="<?= $cr['id'] ?>" <?= $selected_classroom == $cr['id'] ? 'selected' : '' ?>>
                            <?= e($cr['class_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="level" class="form-select rounded-pill shadow-none" onchange="this.form.submit()">
                        <option value="">-- เลือกระดับชั้นทั้งหมด --</option>
                        <option value="ปวช." <?= $selected_level === 'ปวช.' ? 'selected' : '' ?>>ปวช.</option>
                        <option value="ปวส." <?= $selected_level === 'ปวส.' ? 'selected' : '' ?>>ปวส.</option>
                    </select>
                </div>
                <div class="col-md-5 text-end">
                    <span class="text-muted small">พบรายชื่อนักเรียนเสี่ยงทั้งหมด <strong class="text-dark"><?= count($students_at_risk) ?></strong> ราย</span>
                </div>
            </form>
        </div>
    </div>

    <!-- At Risk Students Table -->
    <div class="card border-0 shadow-sm admin-card">
        <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list-ul text-danger me-2"></i>รายชื่อนักเรียนที่ต้องติดตามด่วน</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4">รหัสนักศึกษา / ชื่อ-นามสกุล</th>
                            <th>ห้องเรียน</th>
                            <th>สถานประกอบการ</th>
                            <th>รายงานล่าสุดเมื่อ</th>
                            <th class="text-center">จำนวนวันที่ขาดส่ง</th>
                            <th class="text-end pe-4">ส่งการแจ้งเตือน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students_at_risk)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fs-1 text-success opacity-50 mb-2 d-block"></i>
                                <span class="fw-bold">ยอดเยี่ยม! ไม่พบนักเรียนที่ขาดส่งรายงานในขณะนี้</span>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($students_at_risk as $st): 
                            $days = (int)$st['days_inactive'];
                            $badgeClass = 'badge-risk-mod';
                            $riskLabel = "ขาดส่ง {$days} วัน";
                            if ($days >= 7) {
                                $badgeClass = 'badge-risk-critical';
                                $riskLabel = "วิกฤต (ขาดส่ง {$days} วัน)";
                            } elseif ($days >= 4) {
                                $badgeClass = 'badge-risk-high';
                                $riskLabel = "ขาดส่ง {$days} วัน";
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= e($st['fullname']) ?></div>
                                <div class="text-muted small"><?= e($st['student_code'] ?? 'ไม่มีรหัส') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark fw-bold border">
                                    <?= e($st['class_name'] ?? 'ยังไม่ระบุห้อง') ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($st['company_name']) ? '<i class="fas fa-building me-1"></i>' . e($st['company_name']) : '-' ?>
                            </td>
                            <td class="small">
                                <?= !empty($st['last_work_date']) ? date('d/m/Y', strtotime($st['last_work_date'])) : '<span class="text-danger">ยังไม่เคยส่ง</span>' ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2 fw-bold">
                                    <i class="fas fa-exclamation-circle me-1"></i> <?= $riskLabel ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันส่งข้อความเตือนไปยังนักเรียนคนนี้?')">
                                    <input type="hidden" name="action" value="notify_student">
                                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                    <input type="hidden" name="days_inactive" value="<?= $days ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                        <i class="fas fa-paper-plane me-1"></i> เตือนนักเรียน
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
