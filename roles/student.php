<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

$u = current_user();
$student_id = (int)$u['id'];

// 1. ดึงข้อมูลนักเรียน + ระดับชั้น + ชื่อครูนิเทศก์ (จากตาราง users) + ตารางครู (จากตาราง mentors)
$sql = "SELECT u.*, t.fullname as mentor_name, m.online_schedule, m.supervision_schedule, m.schedule_json, c.class_name 
        FROM users u 
        LEFT JOIN users t ON u.mentor_id = t.id 
        LEFT JOIN mentors m ON t.id = m.user_id 
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE u.id = $student_id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();

$student_level = !empty($data['student_level']) ? $data['student_level'] : 'ปวช.'; 

// 2. ดึงค่ากำหนดการฝึกงานจากตาราง settings
$stmt_settings = $conn->prepare("SELECT * FROM internship_settings WHERE level_name = ?");
$stmt_settings->bind_param("s", $student_level);
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
$settings = $result_settings->fetch_assoc();

$start_date = isset($settings['start_date']) ? $settings['start_date'] : null;
$end_date = isset($settings['end_date']) ? $settings['end_date'] : null;

// Format Issue and Expiry Dates for student card using Buddhist Era (BE)
$issue_date_formatted = "19/05/2568";
$expire_date_formatted = "30/04/2570";
if ($start_date && $start_date != '0000-00-00') {
    $s_date = new DateTime($start_date);
    $issue_date_formatted = $s_date->format('d/m/') . ($s_date->format('Y') + 543);
}
if ($end_date && $end_date != '0000-00-00') {
    $e_date = new DateTime($end_date);
    $expire_date_formatted = $e_date->format('d/m/') . ($e_date->format('Y') + 543);
}

// Generate realistic deterministic Citizen ID from username
$citizen_id = "1-1472-00002-82-5";
if (!empty($data['username'])) {
    $seed = crc32($data['username']);
    $part1 = abs($seed) % 9 + 1;
    $part2 = sprintf("%04d", abs($seed / 7) % 10000);
    $part3 = sprintf("%05d", abs($seed / 13) % 100000);
    $part4 = sprintf("%02d", abs($seed / 17) % 100);
    $part5 = abs($seed) % 10;
    $citizen_id = "$part1-$part2-$part3-$part4-$part5";
}

// 3. คำนวณความคืบหน้า
$progress_percent = 0;
$status_text = "ยังไม่ระบุวันที่";
$status_color = "text-muted";
$days_left_text = "ติดต่อเจ้าหน้าที่";

if ($start_date && $end_date && $start_date != '0000-00-00') {
    $today = new DateTime();
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    $today->setTime(0,0);
    $start->setTime(0,0);
    $end->setTime(0,0);

    if ($today < $start) {
        $progress_percent = 0;
        $status_text = "รอเริ่มฝึกงาน";
        $status_color = "text-warning";
        $days_left_text = "อีก " . $today->diff($start)->days . " วันจะเริ่ม";
    } elseif ($today > $end) {
        $progress_percent = 100;
        $status_text = "สิ้นสุดการฝึกงาน";
        $status_color = "text-success";
        $days_left_text = "เสร็จสิ้นแล้ว";
    } else {
        $total_days = $start->diff($end)->days;
        $passed_days = $start->diff($today)->days;
        $progress_percent = ($total_days > 0) ? ($passed_days / $total_days) * 100 : 0;
        $status_text = "กำลังฝึกงาน";
        $status_color = "text-primary";
        $days_left_text = "เหลืออีก " . $today->diff($end)->days . " วัน";
    }
}

// 4. สถิติการส่งสมุดบันทึก
$sql_count = "SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count 
              FROM daily_reports WHERE student_id = $student_id";
$res_count = $conn->query($sql_count);
$row_count = $res_count ? $res_count->fetch_assoc() : null;
$total_reports  = (int)($row_count['total_reports']  ?? 0);
$rejected_count = (int)($row_count['rejected_count'] ?? 0);

// ดึงรายการงานที่โดนตีกลับ (ล่าสุด 2 รายการ) เพื่อโชว์ในกล่องแจ้งเตือน
$rejected_reports_list = [];
if ($rejected_count > 0) {
    $sql_rejected = "SELECT id, date_work, details, teacher_comment FROM daily_reports WHERE student_id = $student_id AND status = 'rejected' ORDER BY date_work DESC LIMIT 2";
    $res_rejected = $conn->query($sql_rejected);
    if ($res_rejected) {
        while ($row_rej = $res_rejected->fetch_assoc()) {
            $rejected_reports_list[] = $row_rej;
        }
    }
}

// สรุปสถิติประจำสัปดาห์ (จันทร์ - อาทิตย์) สำหรับแสดงเมื่อยังไม่มีตารางสอน/ออกนิเทศ
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$end_of_week = date('Y-m-d', strtotime('sunday this week'));
$sql_week = "SELECT 
                COUNT(*) as week_total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as week_approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as week_pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as week_rejected
             FROM daily_reports 
             WHERE student_id = $student_id 
               AND date_work >= '$start_of_week' 
               AND date_work <= '$end_of_week'";
$res_week = $conn->query($sql_week);
$row_week = $res_week ? $res_week->fetch_assoc() : null;
$week_total = (int)($row_week['week_total'] ?? 0);
$week_approved = (int)($row_week['week_approved'] ?? 0);
$week_pending = (int)($row_week['week_pending'] ?? 0);
$week_rejected = (int)($row_week['week_rejected'] ?? 0);

// คำคมให้กำลังใจรายวัน (คำนวณตามลำดับวันในปีถัดไปทำให้เปลี่ยนไปเรื่อยๆ ทุกๆ วันตลอดปี)
$quotes = [
    "ความสำเร็จไม่ได้มาจากการนั่งรอ แต่มาจากการลงมือทำในทุกๆ วัน",
    "อุปสรรคคือโอกาสที่ทำให้เราเติบโตและเก่งขึ้นในทุกๆ ย่างก้าว",
    "ทุกความพยายามอาจไม่นำมาซึ่งความสำเร็จ แต่ทุกความสำเร็จล้วนต้องใช้ความพยายาม",
    "การฝึกงานคือการเรียนรู้ผ่านการปฏิบัติจริง จงภูมิใจในสิ่งที่เราได้ลงมือทำในวันนี้",
    "ก้าวเล็กๆ ในแต่ละวัน เมื่อรวมกันแล้วจะกลายเป็นการเดินทางที่ยิ่งใหญ่",
    "ไม่มีวันใดที่สูญเปล่า หากเราได้เรียนรู้สิ่งใหม่ๆ แม้เพียงเรื่องเดียว",
    "จงทำวันนี้ให้ดีที่สุด เพื่อวันพรุ่งนี้ที่ดีกว่าเดิม",
    "ความล้มเหลวที่แท้จริง คือการยอมแพ้ก่อนที่จะพยายามอย่างเต็มที่",
    "ความสุขที่แท้จริงของการทำงาน คือความภาคภูมิใจเมื่อเห็นผลงานสำเร็จ",
    "จงมั่นใจในศักยภาพของตัวเอง เพราะเราทำได้ดีกว่าที่คิดเสมอ",
    "ทุกความล้มเหลวคือบทเรียนชั้นยอดที่จะนำเราไปสู่ความสำเร็จในวันข้างหน้า",
    "สิ่งสำคัญที่สุดของการเรียนรู้ ไม่ใช่การจำได้ แต่คือการนำไปปรับใช้จริง",
    "โอกาสไม่ได้เกิดขึ้นเอง แต่เราเป็นผู้สร้างมันขึ้นมาจากการขยันใฝ่หาความรู้",
    "อย่าเปรียบเทียบก้าวของตัวเองกับใคร เพราะเส้นทางการเติบโตของแต่ละคนแตกต่างกัน",
    "ความอดทนและตั้งใจในวันนี้ คือกุญแจสำคัญที่จะเปิดประตูสู่อนาคตที่มั่นคง",
    "งานหนักและการเรียนรู้จะขัดเกลาเราให้เป็นคนที่พร้อมสำหรับทุกความท้าทาย",
    "ชีวิตไม่มีทางลัดสำหรับคนต้องการความสำเร็จที่ยั่งยืน มีเพียงความเพียรพยายามเท่านั้น",
    "จงเปิดใจยอมรับฟังความเห็นของผู้อื่น เพราะมันคือเครื่องมือพัฒนาตัวเราให้ดีขึ้น",
    "ความพร้อมไม่ได้ขึ้นอยู่กับอายุ แต่ขึ้นอยู่กับความรับผิดชอบและการเตรียมตัวในทุกวัน",
    "จงทำงานด้วยใจรักและทุ่มเท แล้วสิ่งดีๆ จะตามมาอย่างแน่นอน"
];
$quote_index = (int)date('z') % count($quotes);
$daily_quote = $quotes[$quote_index];

$thai_months = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
    7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];


// คำนวณคำทักทาย
$hour = date('H');
$greeting = "สวัสดี";
if ($hour < 12) $greeting = "อรุณสวัสดิ์";
elseif ($hour < 18) $greeting = "สวัสดีตอนบ่าย";
else $greeting = "สวัสดีตอนเย็น";

// ดึง Supervisor ที่ดูแลนักเรียนคนนี้
$my_supervisors = [];
$chk_sv_tbl = $conn->query("SHOW TABLES LIKE 'supervisor_assignments'");
if ($chk_sv_tbl && $chk_sv_tbl->num_rows > 0) {
    $sv_res = $conn->prepare("
        SELECT sv.id, sv.fullname, sv.email, sv.phone, sv.company_name, sa.assigned_by_role
        FROM supervisor_assignments sa
        JOIN users sv ON sa.supervisor_id = sv.id
        WHERE sa.student_id = ?
        ORDER BY sa.assigned_at ASC
    ");
    $sv_res->bind_param("i", $student_id);
    $sv_res->execute();
    $sv_result = $sv_res->get_result();
    while ($svrow = $sv_result->fetch_assoc()) $my_supervisors[] = $svrow;
    $sv_res->close();
}

// ดึงรายชื่อ Supervisor ทั้งหมดเพื่อให้นักเรียนเลือก
$all_supervisors = [];
if ($chk_sv_tbl && $chk_sv_tbl->num_rows > 0) {
    $all_sv_res = $conn->query("SELECT id, fullname, email, company_name FROM users WHERE role='supervisor' ORDER BY fullname ASC");
    if ($all_sv_res) while ($row = $all_sv_res->fetch_assoc()) $all_supervisors[] = $row;
}

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>

<!-- Link Premium CSS (Already in header, but keeping for local specificity if needed) -->
<link rel="stylesheet" href="../assets/css/student-premium.css">

<div class="dashboard-page">
    <!-- Background Blobs -->
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="container py-4">
        <!-- Premium Hero -->
        <div class="premium-hero animate-slide-up">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="small fw-bold text-white text-opacity-75 mb-2 text-uppercase letter-spacing-2">ระบบการนิเทศรายวิชาฝึกประสบการณ์สมรรถนะวิชาชีพ</div>
                    <div class="hero-greeting mb-2"><?= $greeting ?>, นักศึกษาฝึกงาน</div>
                    <div class="hero-name"><?= htmlspecialchars($data['fullname']); ?></div>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="badge rounded-pill px-3 py-2 border border-white border-opacity-20 backdrop-blur shadow-sm" style="background: rgba(0,0,0,0.15);">
                            <i class="bi bi-mortarboard-fill me-1"></i> <?= htmlspecialchars($student_level); ?>
                        </span>
                        <span class="badge rounded-pill px-3 py-2 border border-white border-opacity-20 backdrop-blur shadow-sm" style="background: rgba(0,0,0,0.15);">
                            <i class="bi bi-hash me-1"></i> ID: <?= htmlspecialchars($data['username']); ?>
                        </span>
                        <span id="connection-badge" class="badge rounded-pill px-3 py-2 border border-white border-opacity-20 backdrop-blur shadow-sm transition-all" style="background: rgba(0,0,0,0.15);">
                            <i class="bi bi-wifi me-1"></i> กำลังเชื่อมต่อ...
                        </span>
                    </div>
                    
                    <div class="progress-section mt-4">
                        <div class="d-flex justify-content-between mb-2 small fw-bold">
                            <span><i class="bi bi-activity me-1"></i> ความคืบหน้าการฝึกงาน</span>
                            <span><?= number_format($progress_percent, 0); ?>%</span>
                        </div>
                        <div class="custom-progress">
                            <div class="custom-progress-bar" style="width: <?= $progress_percent; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <div class="stat-pill bg-white bg-opacity-10 p-4 rounded-4 backdrop-blur border border-white border-opacity-20 shadow-lg">
                        <div class="fs-4 fw-extrabold text-white mb-1"><?= $status_text ?></div>
                        <div class="small text-white text-opacity-75"><?= $days_left_text ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($rejected_count > 0): ?>
        <div class="alert alert-danger pulse-red d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 border-0 rounded-4 p-4 shadow-sm animate-fade-in gap-3">
            <div class="d-flex align-items-start gap-3 flex-grow-1">
                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle flex-shrink-0">
                    <i class="bi bi-exclamation-octagon-fill fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1">รายการที่ต้องแก้ไข!</h6>
                    <span class="small opacity-75">พบงานที่ถูกส่งกลับจำนวน <?= $rejected_count ?> รายการ กรุณาตรวจสอบและส่งใหม่:</span>
                    
                    <div class="mt-3 p-3 rounded-3" style="background: rgba(220, 38, 38, 0.05); border-left: 4px solid var(--danger);">
                        <?php foreach ($rejected_reports_list as $rep): 
                            $time = strtotime($rep['date_work']);
                            $d_day = date('j', $time);
                            $formatted_date = $d_day . " " . $thai_months[(int)date('n', $time)];
                            $issue_reason = !empty($rep['teacher_comment']) ? $rep['teacher_comment'] : $rep['details'];
                            if (mb_strlen($issue_reason) > 90) {
                                $issue_reason = mb_substr($issue_reason, 0, 90) . '...';
                            }
                        ?>
                            <div class="d-flex align-items-baseline gap-2 small text-danger-emphasis <?= (end($rejected_reports_list) === $rep) ? '' : 'mb-2' ?>">
                                <i class="bi bi-dot text-danger fs-5 lh-1 flex-shrink-0"></i>
                                <span><strong>บันทึกวันที่ <?= $formatted_date ?>:</strong> <?= htmlspecialchars($issue_reason) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="text-end flex-shrink-0">
                <a href="../student/view_report.php?filter=rejected" class="btn btn-danger btn-lg rounded-pill px-4 fw-bold w-100 w-md-auto">
                    ไปแก้ไข <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="section-header d-flex align-items-center mb-4">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                <i class="bi bi-bar-chart-fill text-primary"></i>
            </div>
            <h5 class="fw-bold mb-0">สรุปข้อมูลการฝึกงาน</h5>
        </div>

        <div class="row g-4 mb-4 animate-slide-up" style="animation-delay: 0.2s;">
            <div class="col-md-4">
                <div class="stat-card-v2">
                    <div class="stat-icon-wrap text-primary">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <div class="stat-val"><?= $total_reports ?> <span class="fs-6 text-muted fw-normal">วัน</span></div>
                    <div class="stat-desc">จำนวนวันที่มีการบันทึกงาน</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-v2">
                    <div class="stat-icon-wrap text-info">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="stat-val text-truncate" style="font-size: 1.25rem;"><?= htmlspecialchars($data['mentor_name'] ?? 'ยังไม่ระบุ'); ?></div>
                    <div class="stat-desc">อาจารย์นิเทศก์ของคุณ</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-v2">
                    <div class="stat-icon-wrap text-success">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="stat-val text-truncate" style="font-size: 1.25rem;"><?= htmlspecialchars(!empty($data['company_name']) ? $data['company_name'] : 'ยังไม่ระบุ'); ?></div>
                    <div class="stat-desc">สถานที่ฝึกงาน</div>
                </div>
            </div>
        </div>

        <!-- Weekly Stats Banner -->
        <div class="glass-card border-0 p-3.5 mb-5 animate-slide-up" style="animation-delay: 0.25s; background: rgba(255, 255, 255, 0.45);">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 text-success p-2.5 rounded-3">
                        <i class="bi bi-bar-chart-line-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">สรุปสถิติการส่งบันทึกสัปดาห์นี้</div>
                        <div class="text-muted" style="font-size: 0.725rem;">ระหว่างวันที่ <?= date('d/m/Y', strtotime('monday this week')) ?> - <?= date('d/m/Y', strtotime('sunday this week')) ?></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 flex-grow-1 justify-content-md-end">
                    <div class="d-flex align-items-center bg-white px-3 py-2 rounded-4 shadow-sm border border-light" style="min-width: 120px;">
                        <i class="bi bi-send text-primary me-2"></i>
                        <div>
                            <div class="text-muted fw-bold" style="font-size: 0.65rem; line-height: 1;">บันทึกสัปดาห์นี้</div>
                            <div class="fw-extrabold text-dark" style="font-size: 0.9rem;"><?= $week_total ?> <span class="fw-normal text-muted" style="font-size: 0.75rem;">วัน</span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-white px-3 py-2 rounded-4 shadow-sm border border-light" style="min-width: 120px;">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <div>
                            <div class="text-muted fw-bold" style="font-size: 0.65rem; line-height: 1;">อนุมัติแล้ว</div>
                            <div class="fw-extrabold text-dark" style="font-size: 0.9rem;"><?= $week_approved ?> <span class="fw-normal text-muted" style="font-size: 0.75rem;">วัน</span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-white px-3 py-2 rounded-4 shadow-sm border border-light" style="min-width: 120px;">
                        <i class="bi bi-clock-fill text-warning me-2"></i>
                        <div>
                            <div class="text-muted fw-bold" style="font-size: 0.65rem; line-height: 1;">รอนิเทศตรวจ</div>
                            <div class="fw-extrabold text-dark" style="font-size: 0.9rem;"><?= $week_pending ?> <span class="fw-normal text-muted" style="font-size: 0.75rem;">วัน</span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-white px-3 py-2 rounded-4 shadow-sm border border-light" style="min-width: 120px;">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                        <div>
                            <div class="text-muted fw-bold" style="font-size: 0.65rem; line-height: 1;">ต้องแก้ไข</div>
                            <div class="fw-extrabold text-dark" style="font-size: 0.9rem;"><?= $week_rejected ?> <span class="fw-normal text-muted" style="font-size: 0.75rem;">วัน</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Menus -->
        <div class="section-header d-flex align-items-center mb-4">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                <i class="bi bi-grid-fill text-primary"></i>
            </div>
            <h5 class="fw-bold mb-0">เมนูการใช้งาน</h5>
        </div>

        <div class="row g-3 g-md-4 mb-5 animate-slide-up" style="animation-delay: 0.3s;">
            <div class="col-6 col-md-6 col-lg-3">
                <a href="../student/submit_report.php" class="menu-card-premium">
                    <div class="menu-icon-premium text-success">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">บันทึกงานวันนี้</div>
                    <div class="small text-muted mt-2">ลงเวลาฝึกงานประจำวัน</div>
                </a>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <a href="../student/view_report.php" class="menu-card-premium">
                    <div class="menu-icon-premium text-primary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">ประวัติบันทึก</div>
                    <div class="small text-muted mt-2">ตรวจสอบงานที่ส่งแล้ว</div>
                </a>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <a href="../student/profile.php" class="menu-card-premium">
                    <div class="menu-icon-premium text-warning">
                        <i class="bi bi-person-bounding-box"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">โปรไฟล์</div>
                    <div class="small text-muted mt-2">ข้อมูลส่วนตัวนักศึกษา</div>
                </a>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <a href="#" class="menu-card-premium" data-bs-toggle="modal" data-bs-target="#supervisorModal">
                    <div class="menu-icon-premium" style="color: #0ea5e9;">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">ผู้ดูแลการฝึกงาน</div>
                    <div class="small text-muted mt-2">
                        <?= !empty($my_supervisors) ? count($my_supervisors) . ' คนในดูแล (ดู/แก้ไข)' : 'เลือกผู้ดูแลการฝึกงาน' ?>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <a href="../contact.php" class="menu-card-premium">
                    <div class="menu-icon-premium text-info">
                        <i class="bi bi-headset"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">ติดต่อสอบถาม</div>
                    <div class="small text-muted mt-2">แจ้งปัญหาการใช้งาน</div>
                </a>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <?php if (!empty($data['company_name'])): ?>
                <a href="../certificates/generate_certificate.php" target="_blank" class="menu-card-premium">
                    <div class="menu-icon-premium text-danger">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">เกียรติบัตร</div>
                    <div class="small text-muted mt-2">พิมพ์ใบประกาศนียบัตร</div>
                </a>
                <?php else: ?>
                <div class="menu-card-premium opacity-60" style="cursor: not-allowed;">
                    <div class="menu-icon-premium text-secondary">
                        <i class="bi bi-lock-fill"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">เกียรติบัตร</div>
                    <div class="small text-muted mt-2">ระบุข้อมูลบริษัทก่อน</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <a href="../student/profile.php?tab=notifications" class="menu-card-premium">
                    <div class="menu-icon-premium" style="color: #6366f1;">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div class="menu-title-premium fw-bold fs-5">ตั้งค่าแจ้งเตือน</div>
                    <div class="small text-muted mt-2">เปิดรับแจ้งเตือนจากระบบ</div>
                </a>
            </div>
        </div>


        <!-- ID Card & Schedule Section -->
        <div class="row g-4 mb-5 animate-slide-up" style="animation-delay: 0.4s;">
            <div class="col-lg-5">
                <!-- Authentic Digital ID Card (Horizontal) -->
                <div class="id-card-pvc" style="position: relative; width: 100%; max-width: 460px; height: 290px; background: #eef2f6 url('../images/card-bg.png') no-repeat center 20%/cover; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.18); border: 1px solid rgba(0,0,0,0.1); margin: 0 auto; font-family: 'Sarabun', 'Prompt', sans-serif; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); transform-style: preserve-3d; perspective: 1000px;">
                    <!-- Wave Header Background SVG (Stretched Further Down) -->
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 102px; z-index: 2;">
                        <svg viewBox="0 0 460 102" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                            <defs>
                                <linearGradient id="greenGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#4caf50" />
                                    <stop offset="50%" stop-color="#2e7d32" />
                                    <stop offset="100%" stop-color="#1b5e20" />
                                </linearGradient>
                                <linearGradient id="waveHighlight" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="rgba(255,255,255,0.25)" />
                                    <stop offset="60%" stop-color="rgba(255,255,255,0.1)" />
                                    <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                                </linearGradient>
                            </defs>
                            <!-- Background wave -->
                            <path d="M 0 0 L 460 0 L 460 84 C 360 100, 180 80, 0 84 Z" fill="url(#greenGrad)" />
                            <!-- Highlight wave overlay -->
                            <path d="M 0 0 L 460 0 L 460 74 C 320 90, 140 70, 0 74 Z" fill="url(#waveHighlight)" />
                            <!-- Bottom white-yellow stroke curve -->
                            <path d="M 0 84 C 180 80, 360 100, 460 84" fill="none" stroke="#d4e157" stroke-width="2.5" />
                        </svg>
                    </div>

                    <!-- Header Content Layer -->
                    <div style="height: 96px; padding: 12px 16px 8px 16px; position: relative; z-index: 3; display: flex; align-items: flex-start; gap: 12px;">
                        <img src="../images/logo.png" style="height: 48px; width: 48px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25)); flex-shrink: 0; margin-top: 2px;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3061/3061341.png'">
                        <div style="line-height: 1.15; margin-top: 4px;">
                            <div style="font-weight: 800; font-size: 17.5px; color: #0a2e0e; text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff, 0 1px 2px rgba(0,0,0,0.25); letter-spacing: -0.2px;">วิทยาลัยอาชีวศึกษาเพชรบุรี</div>
                            <div style="font-weight: 700; font-size: 10.5px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 0.3px;">Phetchaburi Vocational College</div>
                        </div>
                        <div style="position: absolute; right: 12px; top: 12px; font-size: 6.5px; background: rgba(255,255,255,0.25); color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; border: 1px solid rgba(255,255,255,0.3);">DIGITAL ID</div>
                    </div>

                    <!-- Inner Body Background with glassmorphic frosted-glass overlay (Absolutely Positioned to eliminate any gap behind the wavy header) -->
                    <div style="position: absolute; top: 82px; bottom: 14px; left: 0; right: 0; padding: 12px 16px 6px 16px; display: flex; flex-direction: column; justify-content: space-between; z-index: 1; background: rgba(238, 242, 246, 0.84); backdrop-filter: blur(1.5px); -webkit-backdrop-filter: blur(1.5px);">

                        <div style="display: flex; gap: 14px; align-items: flex-start; position: relative; z-index: 3; margin-top: 2px;">
                            <!-- Left: Student Portrait Frame with exact Blue Background -->
                            <div style="width: 88px; height: 116px; border: 3px solid #fff; background: #0f2b5c; border-radius: 4px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.15); flex-shrink: 0; display: flex; align-items: center; justify-content: center; position: relative;">
                                <?php if (!empty($data['profile_image'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($data['profile_image']) ?>?v=<?= time() ?>" alt="Student Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="bi bi-person-fill text-white" style="font-size: 3.5rem;"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Student details in exact alignment with physical card -->
                            <div style="line-height: 1.4; color: #1e293b; display: flex; flex-direction: column; gap: 4px; margin-top: 2px;">
                                <div style="font-size: 11px; font-weight: bold; color: #475569; margin-bottom: -4px;">
                                    ID: <?= htmlspecialchars($data['student_code'] ?? '-'); ?>
                                </div>
                                <div style="font-size: 18.5px; font-weight: 800; color: #1e3a8a; text-shadow: 0 0.5px 1px rgba(0,0,0,0.05);">
                                    <?= htmlspecialchars($data['fullname']); ?>
                                </div>
                                <div style="font-size: 13.5px; font-weight: bold; color: #0f172a;">
                                    <?= htmlspecialchars($data['class_name'] ?? ($student_level . '.สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล')); ?>
                                </div>
                                <div style="font-size: 10.5px; color: #475569; font-weight: bold;">
                                    กลุ่มอาชีพธุรกิจดิจิทัลและพาณิชย์อิเล็กทรอนิกส์
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid rgba(0,0,0,0.08); padding-top: 4px; position: relative; z-index: 3; margin-top: auto; margin-bottom: 2px;">
                            <!-- Left side empty to let QR Code sit elegantly on the right -->
                            <div></div>

                            <!-- Right: Dynamic QR Code -->
                            <div style="text-align: center; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=<?= urlencode($data['username']) ?>" style="width: 46px; height: 46px; border: 2px solid #2e7d32; padding: 2px; background: white; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" alt="QR Code">
                                <div style="font-size: 6.5px; font-weight: 800; color: #2e7d32; text-transform: uppercase; letter-spacing: 0.3px;">SCAN VERIFY</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Green PVC Accent Bar exactly like card (Stretched) -->
                    <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 14px; background: linear-gradient(90deg, #1b5e20 0%, #2e7d32 50%, #4caf50 100%);"></div>
                </div>
                <!-- Inline hover style for 3D tilt effect on the card -->
                <style>
                    .id-card-pvc:hover {
                        transform: translateY(-8px) scale(1.02) rotateX(2deg) rotateY(-2deg);
                        box-shadow: 0 25px 50px rgba(0,0,0,0.22) !important;
                        border-color: #4caf50 !important;
                    }
                </style>
            </div>

            <div class="col-lg-7">
                <div class="glass-card border-0 overflow-hidden h-100">
                    <?php
                    // ดึงข้อมูลแผนการนิเทศจากตาราง plans ที่ตรงกับสถานประกอบการของนักเรียน
                    $company_id = (int)($data['company_id'] ?? 0);
                    ?>
                    <div class="card-header p-4 border-0 d-flex align-items-center justify-content-between bg-transparent">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-check-fill text-primary"></i>ตารางครูนิเทศก์ (<?= htmlspecialchars($data['mentor_name'] ?? 'ยังไม่ระบุครูนิเทศก์'); ?>)
                        </h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <?php
                        $weekly_schedule = [];
                        if (!empty($data['schedule_json'])) {
                            $weekly_schedule = json_decode($data['schedule_json'], true) ?? [];
                        }
                        
                        $day_names = [
                            1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ',
                            4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์', 7 => 'อาทิตย์'
                        ];
                        
                        $has_schedule = false;
                        if (!empty($weekly_schedule)) {
                            foreach ($weekly_schedule as $day_num => $slots) {
                                if (!empty($slots)) {
                                    $has_schedule = true;
                                    break;
                                }
                            }
                        }
                        
                        if ($has_schedule):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-0">
                                    <thead>
                                        <tr class="text-muted small" style="border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.8rem;">
                                            <th width="120" class="ps-0">วัน</th>
                                            <th>รายการกำหนดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($day_names as $d => $label): 
                                            if (empty($weekly_schedule[$d])) continue;
                                        ?>
                                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.02); font-size: 0.9rem;">
                                            <td class="fw-bold ps-0" style="color: #6366f1;">วัน<?= $label ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2 py-1">
                                                    <?php foreach ($weekly_schedule[$d] as $slot): 
                                                        $is_online = ($slot['type'] === 'online');
                                                        $badge_class = $is_online ? 'bg-info bg-opacity-10 text-info' : 'bg-success bg-opacity-10 text-success';
                                                        $icon = $is_online ? 'bi-laptop' : 'bi-geo-alt';
                                                        $type_label = $is_online ? 'สอนออนไลน์' : 'ออกนิเทศ';
                                                    ?>
                                                        <span class="badge <?= $badge_class ?> rounded-pill fw-semibold badge-schedule">
                                                            <i class="bi <?= $icon ?> me-1"></i>
                                                            <?= $type_label ?> (<?= htmlspecialchars($slot['start']) ?> - <?= htmlspecialchars($slot['end']) ?> น.)
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-schedule-widget">
                                <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-4" style="background-color: rgba(99, 102, 241, 0.05); border-left: 4px solid var(--primary);">
                                    <i class="bi bi-calendar-x text-primary fs-5 flex-shrink-0"></i>
                                    <div>
                                        <div class="fw-bold text-dark small">ยังไม่มีกำหนดการนิเทศประจำสัปดาห์</div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">อาจารย์ยังไม่ได้ระบุวันจัดคลาสออนไลน์หรือวันออกนิเทศประจำสัปดาห์นี้</div>
                                    </div>
                                </div>
                                
                                <!-- Daily Quote Card (Full Width) -->
                                <div class="quote-card p-4 rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(217,70,239,0.08) 100%); border: 1px solid rgba(99, 102, 241, 0.15);">
                                    <div style="position: absolute; right: 10px; top: -15px; font-size: 6rem; font-family: 'Georgia', serif; color: rgba(99, 102, 241, 0.08); line-height: 1; pointer-events: none;">“</div>
                                    <div class="mb-2" style="position: relative; z-index: 1;">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small mb-2 d-inline-block">
                                            <i class="bi bi-chat-quote-fill me-1"></i> คำคมให้กำลังใจวันนี้
                                        </span>
                                        <p class="text-dark-emphasis italic fw-medium mb-0" style="font-size: 0.95rem; line-height: 1.5; font-family: 'Sarabun', sans-serif;">
                                            "<?= $daily_quote ?>"
                                        </p>
                                    </div>
                                    <div class="small text-muted text-end w-100 mt-2" style="position: relative; z-index: 1;">
                                        — พลังใจเพื่ออนาคต 💡
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== Supervisor Modal ====== -->
    <div class="modal fade" id="supervisorModal" tabindex="-1" aria-labelledby="supervisorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-bottom: 1px solid rgba(14,165,233,.15);">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="supervisorModalLabel">
                        <i class="bi bi-person-workspace text-primary fs-5"></i> ผู้ดูแลการฝึกงานของฉัน
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- My Supervisors List -->
                    <?php if (!empty($my_supervisors)): ?>
                    <label class="form-label fw-bold small text-muted text-uppercase mb-2">ผู้ดูแลที่แต่งตั้งแล้ว</label>
                    <div class="d-flex flex-column gap-2 mb-4" id="mySupervisorList">
                        <?php foreach ($my_supervisors as $sv): ?>
                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3" id="sv-chip-<?= $sv['id'] ?>"
                             style="background:#f8fafc;border:1px solid rgba(14,165,233,.2);">
                            <div class="d-flex align-items-center gap-2.5">
                                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#14b8a6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                                    <i class="bi bi-person-workspace"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:.9rem;"><?= htmlspecialchars($sv['fullname']) ?></div>
                                    <?php if (!empty($sv['company_name'])): ?>
                                    <div class="text-primary fw-semibold" style="font-size:.75rem;"><i class="bi bi-building me-1"></i><?= htmlspecialchars($sv['company_name']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($sv['email'] ?? '') ?></div>
                                </div>
                            </div>
                            <?php if ($sv['assigned_by_role'] === 'student'): ?>
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:.78rem;"
                                    onclick="removeSupervisor(<?= $sv['id'] ?>, this)">
                                <i class="bi bi-trash me-1"></i> ลบ
                            </button>
                            <?php else: ?>
                            <span class="badge bg-info bg-opacity-10 text-info px-2.5 py-1 rounded-pill" style="font-size:.72rem;" title="มอบหมายโดยเจ้าหน้าที่">
                                <i class="bi bi-shield-fill me-1"></i>เจ้าหน้าที่แต่งตั้ง
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3 mb-3" id="noSupervisorMsg">
                        <i class="bi bi-info-circle text-info fs-3 d-block mb-1"></i>
                        <p class="text-muted small mb-0">ยังไม่มีผู้ดูแลการฝึกงาน สามารถเลือกเพิ่มได้ด้านล่าง</p>
                    </div>
                    <?php endif; ?>

                    <!-- Picker Box with Live Search -->
                    <div class="p-3 rounded-3 mt-3" style="background:rgba(14,165,233,.05);border:1.5px dashed rgba(14,165,233,.25);">
                        <label class="form-label fw-bold small text-primary mb-2">
                            <i class="bi bi-person-plus-fill me-1"></i>ค้นหาและแต่งตั้งผู้ดูแลการฝึกงาน
                        </label>

                        <!-- Search Input Box -->
                        <div class="input-group input-group-sm mb-3 shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-search"></i></span>
                            <input type="text" id="svSearchInput" class="form-control border-start-0 py-2" placeholder="พิมพ์ชื่อ หรืออีเมล เพื่อค้นหาผู้ดูแล..." onkeyup="filterSupervisorList(this.value)" oninput="filterSupervisorList(this.value)">
                            <button class="btn btn-light text-secondary border-start-0" type="button" onclick="clearSvSearch()"><i class="bi bi-x-lg"></i></button>
                        </div>

                        <!-- Live Supervisor Result List -->
                        <div id="svResultList" class="d-flex flex-column gap-2" style="max-height: 220px; overflow-y: auto;">
                            <?php foreach ($all_supervisors as $sv): ?>
                            <div class="sv-search-item d-flex align-items-center justify-content-between p-2.5 bg-white rounded-3 border"
                                 data-name="<?= htmlspecialchars(mb_strtolower($sv['fullname'])) ?>"
                                 data-email="<?= htmlspecialchars(mb_strtolower($sv['email'] ?? '')) ?>"
                                 data-id="<?= $sv['id'] ?>">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#e0f2fe;color:#0ea5e9;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;border:1px solid rgba(14,165,233,.2);">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size:.88rem;"><?= htmlspecialchars($sv['fullname']) ?></div>
                                        <?php if (!empty($sv['company_name'])): ?>
                                        <div class="text-primary fw-semibold" style="font-size:.73rem;"><i class="bi bi-building me-1"></i><?= htmlspecialchars($sv['company_name']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($sv['email'] ?? '-') ?></div>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" style="font-size:.78rem;" onclick="addSupervisorDirect(<?= $sv['id'] ?>, '<?= htmlspecialchars(addslashes($sv['fullname'])) ?>', '<?= htmlspecialchars(addslashes($sv['email'] ?? '')) ?>', this)">
                                    <i class="bi bi-plus-lg me-1"></i> เลือก
                                </button>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($all_supervisors)): ?>
                            <div class="text-muted text-center py-2 small">ไม่พบบัญชีผู้ดูแลการฝึกงานในระบบ</div>
                            <?php endif; ?>
                        </div>
                        <div id="svPickerStatus" class="mt-2 small"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="../student/submit_report.php" class="fab-btn animate-bounce" title="บันทึกงานวันนี้">
        <i class="bi bi-plus-lg"></i>
    </a>
</div>

<script>
function filterSupervisorList(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('#svResultList .sv-search-item');
    let found = 0;

    items.forEach(item => {
        const name  = item.getAttribute('data-name') || '';
        const email = item.getAttribute('data-email') || '';
        if (name.includes(q) || email.includes(q)) {
            item.style.setProperty('display', 'flex', 'important');
            found++;
        } else {
            item.style.setProperty('display', 'none', 'important');
        }
    });

    const emptyMsg = document.getElementById('noSvResultMsg');
    if (found === 0 && q.length > 0) {
        if (!emptyMsg) {
            const div = document.createElement('div');
            div.id = 'noSvResultMsg';
            div.className = 'text-muted text-center py-3 small';
            div.innerHTML = '<i class="bi bi-search me-1"></i> ไม่พบรายชื่อผู้ดูแลที่ตรงกับ "' + query + '"';
            document.getElementById('svResultList').appendChild(div);
        } else {
            emptyMsg.style.display = 'block';
            emptyMsg.innerHTML = '<i class="bi bi-search me-1"></i> ไม่พบรายชื่อผู้ดูแลที่ตรงกับ "' + query + '"';
        }
    } else if (emptyMsg) {
        emptyMsg.style.display = 'none';
    }
}

function clearSvSearch() {
    const input = document.getElementById('svSearchInput');
    if (input) {
        input.value = '';
        filterSupervisorList('');
        input.focus();
    }
}

function addSupervisorDirect(svId, svName, svEmail, btn) {
    const statusEl = document.getElementById('svPickerStatus');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังเพิ่ม...';

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('supervisor_id', svId);

    fetch('/DVE_DATA_FULL/student/manage_supervisor.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (data.success) {
            statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>แต่งตั้ง ' + svName + ' เรียบร้อยแล้ว</span>';
            
            // Append chip to My Supervisors list
            let list = document.getElementById('mySupervisorList');
            if (!list) {
                list = document.createElement('div');
                list.id = 'mySupervisorList';
                list.className = 'd-flex flex-column gap-2 mb-4';
                const body = document.querySelector('#supervisorModal .modal-body');
                body.insertBefore(list, body.querySelector('.p-3'));
            }
            document.getElementById('noSupervisorMsg') && document.getElementById('noSupervisorMsg').remove();
            
            if (!document.getElementById('sv-chip-' + svId)) {
                const chip = document.createElement('div');
                chip.id = 'sv-chip-' + svId;
                chip.className = 'd-flex align-items-center justify-content-between p-3 rounded-3';
                chip.style.cssText = 'background:#f8fafc;border:1px solid rgba(14,165,233,.2);';
                chip.innerHTML = `
                    <div class="d-flex align-items-center gap-2.5">
                        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#14b8a6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.9rem;">${data.supervisor_name || svName}</div>
                            <div class="text-muted" style="font-size:.78rem;">${data.supervisor_email || svEmail}</div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:.78rem;" onclick="removeSupervisor(${svId}, this)">
                        <i class="bi bi-trash me-1"></i> ลบ
                    </button>`;
                list.appendChild(chip);
            }
            setTimeout(() => { statusEl.innerHTML = ''; }, 3000);
        } else {
            statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>' + (data.message || 'ผิดพลาด') + '</span>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>ไม่สามารถเชื่อมต่อได้</span>';
    });
}

function createSupervisorList() {
    const container = document.querySelector('#supervisorPickerBox').parentElement;
    const list = document.createElement('div');
    list.id = 'mySupervisorList';
    list.className = 'd-flex flex-wrap gap-3 mb-3';
    container.insertBefore(list, document.getElementById('supervisorPickerBox'));
    return list;
}

function removeSupervisor(svId, btn) {
    if (!confirm('ต้องการลบผู้ดูแลคนนี้ออกจากรายการของคุณ?')) return;
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('supervisor_id', svId);

    fetch('/DVE_DATA_FULL/student/manage_supervisor.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const chip = document.getElementById('sv-chip-' + svId);
            if (chip) { chip.style.opacity = '0'; chip.style.transition = 'opacity .3s'; setTimeout(() => chip.remove(), 300); }
        } else {
            alert(data.message || 'ลบไม่ได้ (ลบได้เฉพาะที่เลือกเอง)');
        }
    });
}
</script>

<!-- Premium Offline Support Scripts -->
<script src="../assets/js/offline-db.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const badge = document.getElementById('connection-badge');
    
    function updateConnectionStatus() {
        if (!badge) return;
        if (navigator.onLine) {
            badge.innerHTML = '<i class="bi bi-wifi text-success me-1"></i> เชื่อมต่อออนไลน์';
            badge.className = 'badge rounded-pill px-3 py-2 border border-success border-opacity-30 backdrop-blur shadow-sm text-success transition-all';
            badge.style.background = 'rgba(5, 150, 105, 0.1)'; // var(--success) transparency
            
            // Trigger background sync when loading online
            DveDB.syncReports(true);
        } else {
            badge.innerHTML = '<i class="bi bi-wifi-off text-warning me-1 animate-pulse"></i> กำลังทำงานออฟไลน์';
            badge.className = 'badge rounded-pill px-3 py-2 border border-warning border-opacity-30 backdrop-blur shadow-sm text-warning transition-all';
            badge.style.background = 'rgba(217, 119, 6, 0.1)'; // var(--warning) transparency
        }
    }
    
    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    updateConnectionStatus();
    
    // Parse notifications from url redirects
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('offline_saved')) {
        DveDB.showToast('📶 บันทึกในเครื่องสำเร็จ!', 'รายงานประจำวันของคุณถูกบันทึกไว้ในเบราว์เซอร์ชั่วคราวแล้ว และจะซิงก์อัปโหลดโดยอัตโนมัติเมื่อตรวจพบอินเทอร์เน็ต', 'warning');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (urlParams.get('msg') === 'sync_success') {
        DveDB.showToast('✨ ซิงก์ข้อมูลสำเร็จ!', 'รายงานที่ทำค้างไว้ตอนออฟไลน์ได้รับการซิงก์เรียบร้อยแล้ว', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>