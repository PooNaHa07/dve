<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff', 'teacher']);

// Handle Excel / CSV Download
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    log_audit('EXPORT_EXCEL_REPORTS', 'ดาวน์โหลดไฟล์รายงาน Excel สรุปการฝึกงาน');

    $export_type = $_GET['type'] ?? 'student_summary'; // student_summary, daily_reports, risk_students
    $classroom_id = (int)($_GET['classroom_id'] ?? 0);
    $status = trim($_GET['status'] ?? '');
    $from_date = trim($_GET['from_date'] ?? '');
    $to_date = trim($_GET['to_date'] ?? '');

    $filename = 'dve_export_' . $export_type . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Excel Thai language rendering
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    if ($export_type === 'risk_students') {
        // Export At-Risk Students
        fputcsv($output, ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ระดับชั้น', 'ห้องเรียน', 'สถานประกอบการ', 'เบอร์โทร', 'วันที่ขาดส่ง (วัน)', 'รายงานล่าสุดเมื่อ']);

        $sql = "SELECT u.id, u.student_code, u.fullname, u.phone, u.company_name, u.student_level,
                       c.class_name,
                       MAX(dr.date_work) AS last_work_date,
                       DATEDIFF(CURRENT_DATE(), IFNULL(MAX(dr.date_work), '2000-01-01')) AS days_inactive
                FROM users u
                LEFT JOIN classrooms c ON u.classroom_id = c.id
                LEFT JOIN daily_reports dr ON u.id = dr.student_id
                WHERE u.role = 'student'";
        if ($classroom_id > 0) $sql .= " AND u.classroom_id = " . $classroom_id;
        $sql .= " GROUP BY u.id HAVING days_inactive >= 3 ORDER BY days_inactive DESC, u.fullname ASC";

        $res = $conn->query($sql);
        $i = 1;
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($output, [
                    $i++,
                    $r['student_code'] ?? '-',
                    $r['fullname'],
                    $r['student_level'] ?? '-',
                    $r['class_name'] ?? '-',
                    $r['company_name'] ?? '-',
                    $r['phone'] ?? '-',
                    $r['days_inactive'],
                    $r['last_work_date'] ?? 'ยังไม่เคยบันทึก'
                ]);
            }
        }
    } elseif ($export_type === 'daily_reports') {
        // Export Detailed Daily Reports
        fputcsv($output, ['ลำดับ', 'วันที่ปฏิบัติงาน', 'รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ห้องเรียน', 'สถานประกอบการ', 'หัวข้องานที่ทำ', 'รายละเอียดงาน', 'สถานะรายงาน', 'ความเห็นผู้ควบคุม']);

        $sql = "SELECT dr.*, u.student_code, u.fullname, u.company_name, c.class_name
                FROM daily_reports dr
                JOIN users u ON dr.student_id = u.id
                LEFT JOIN classrooms c ON u.classroom_id = c.id
                WHERE 1=1";
        if ($classroom_id > 0) $sql .= " AND u.classroom_id = " . $classroom_id;
        if (!empty($status)) $sql .= " AND dr.status = '" . $conn->real_escape_string($status) . "'";
        if (!empty($from_date)) $sql .= " AND dr.date_work >= '" . $conn->real_escape_string($from_date) . "'";
        if (!empty($to_date)) $sql .= " AND dr.date_work <= '" . $conn->real_escape_string($to_date) . "'";
        $sql .= " ORDER BY dr.date_work DESC, u.fullname ASC";

        $res = $conn->query($sql);
        $i = 1;
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($output, [
                    $i++,
                    $r['date_work'],
                    $r['student_code'] ?? '-',
                    $r['fullname'],
                    $r['class_name'] ?? '-',
                    $r['company_name'] ?? '-',
                    $r['title'] ?? '-',
                    $r['work_description'] ?? '-',
                    $r['status'] ?? 'pending',
                    $r['supervisor_comment'] ?? '-'
                ]);
            }
        }
    } else {
        // Export Student Summary (Default)
        fputcsv($output, ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ระดับชั้น', 'ห้องเรียน', 'สถานประกอบการ', 'รายงานทั้งหมด', 'อนุมัติแล้ว', 'รออนุมัติ', 'เปอร์เซ็นต์อนุมัติ']);

        $sql = "SELECT u.id, u.student_code, u.fullname, u.student_level, u.company_name, c.class_name,
                       COUNT(dr.id) AS total_reports,
                       SUM(CASE WHEN dr.status='approved' THEN 1 ELSE 0 END) AS approved_reports,
                       SUM(CASE WHEN dr.status='pending' OR dr.status IS NULL THEN 1 ELSE 0 END) AS pending_reports
                FROM users u
                LEFT JOIN classrooms c ON u.classroom_id = c.id
                LEFT JOIN daily_reports dr ON u.id = dr.student_id
                WHERE u.role = 'student'";
        if ($classroom_id > 0) $sql .= " AND u.classroom_id = " . $classroom_id;
        $sql .= " GROUP BY u.id ORDER BY c.class_name ASC, u.fullname ASC";

        $res = $conn->query($sql);
        $i = 1;
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $total = (int)$r['total_reports'];
                $approved = (int)$r['approved_reports'];
                $pct = $total > 0 ? round(($approved / $total) * 100, 1) . '%' : '0%';

                fputcsv($output, [
                    $i++,
                    $r['student_code'] ?? '-',
                    $r['fullname'],
                    $r['student_level'] ?? '-',
                    $r['class_name'] ?? '-',
                    $r['company_name'] ?? '-',
                    $total,
                    $approved,
                    (int)$r['pending_reports'],
                    $pct
                ]);
            }
        }
    }

    fclose($output);
    exit;
}

// GUI Rendering
$classrooms = [];
$c_res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
if ($c_res) {
    while ($c = $c_res->fetch_assoc()) $classrooms[] = $c;
}

$preset_type = trim($_GET['filter'] ?? '') === 'risk' ? 'risk_students' : 'student_summary';

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4" style="background: linear-gradient(135deg, #065f46 0%, #059669 50%, #10b981 100%);">
        <div>
            <h2 class="admin-header-title text-white">
                <i class="fas fa-file-excel me-2"></i>
                ระบบส่งออกรายงาน Excel (Data Export Engine)
            </h2>
            <p class="text-white text-opacity-75 mb-0 mt-1 small">ดาวน์โหลดข้อมูลสถิติการฝึกงาน รายงานประจำวัน และรายชื่อนักเรียนเสี่ยงในรูปแบบ Excel (.csv)</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none text-white border-white">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <!-- Export Form Card -->
    <div class="card border-0 shadow-sm admin-card mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-sliders-h text-success me-2"></i>ตั้งค่าเงื่อนไขการส่งออกข้อมูล</h5>
        </div>
        <div class="card-body p-4">
            <form method="GET" action="export_reports.php">
                <input type="hidden" name="action" value="download">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">รูปแบบรายงานที่ต้องการ</label>
                        <select name="type" class="form-select rounded-3 shadow-none" required>
                            <option value="student_summary" <?= $preset_type === 'student_summary' ? 'selected' : '' ?>>
                                📊 รายงานสรุปภาพรวมนักเรียน (รายบุคคล)
                            </option>
                            <option value="risk_students" <?= $preset_type === 'risk_students' ? 'selected' : '' ?>>
                                ⚠️ รายชื่อนักเรียนเสี่ยงขาดส่งรายงาน (3 วันขึ้นไป)
                            </option>
                            <option value="daily_reports">
                                📝 รายละเอียดบันทึกการทำงานประจำวันทั้งหมด
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small">เลือกห้องเรียน</label>
                        <select name="classroom_id" class="form-select rounded-3 shadow-none">
                            <option value="0">-- ทุกห้องเรียน --</option>
                            <?php foreach ($classrooms as $cr): ?>
                            <option value="<?= $cr['id'] ?>"><?= e($cr['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">สถานะรายงาน (สำหรับบันทึกประจำวัน)</label>
                        <select name="status" class="form-select rounded-3 shadow-none">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="approved">อนุมัติแล้ว (Approved)</option>
                            <option value="pending">รออนุมัติ (Pending)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">ตั้งแต่วันที่</label>
                        <input type="date" name="from_date" class="form-control rounded-3 shadow-none">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">ถึงวันที่</label>
                        <input type="date" name="to_date" class="form-control rounded-3 shadow-none">
                    </div>
                </div>

                <div class="hr my-4"></div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                        <i class="fas fa-download me-2"></i> ดาวน์โหลดไฟล์ Excel (.csv)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
