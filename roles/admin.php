<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']); 
$u = current_user();
$hide_welcome = true;

if (!function_exists('queryCount')) {
    function queryCount($conn, $sql) {
        try {
            $result = $conn->query($sql);
            if ($result) { $row = $result->fetch_row(); return (int)($row[0] ?? 0); }
        } catch (Exception $e) { error_log("Admin SQL Error: " . $e->getMessage()); }
        return 0;
    }
}

$eligible_certs    = queryCount($conn, "SELECT COUNT(DISTINCT student_id) FROM daily_reports WHERE status='approved'");
$total_plans       = queryCount($conn, "SELECT COUNT(*) FROM plans");
$total_students    = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='student'");
$total_teachers    = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='teacher'");
$total_staff       = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='staff'");
$total_supervisors = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='supervisor'");
$total_admins      = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='admin'");
$total_directors   = queryCount($conn, "SELECT COUNT(*) FROM users WHERE role='director'");
$total_reports     = queryCount($conn, "SELECT COUNT(*) FROM daily_reports");
$pending_reports   = queryCount($conn, "SELECT COUNT(*) FROM daily_reports WHERE status='pending' OR status IS NULL OR status=''");
$unread_messages   = queryCount($conn, "SELECT COUNT(*) FROM contact_messages WHERE status='unread'");
$total_companies   = queryCount($conn, "SELECT COUNT(*) FROM companies");

$risk_res = $conn->query("SELECT u.id FROM users u LEFT JOIN daily_reports dr ON u.id=dr.student_id WHERE u.role='student' GROUP BY u.id HAVING DATEDIFF(CURRENT_DATE(), IFNULL(MAX(dr.date_work),'2000-01-01')) >= 3");
$total_at_risk_students = $risk_res ? $risk_res->num_rows : 0;

$reports_chart_data = [];
$rq = $conn->query("SELECT DATE_FORMAT(created_at,'%d/%m') as day, COUNT(*) as qty FROM daily_reports GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC LIMIT 7");
if ($rq) { while ($r = $rq->fetch_assoc()) $reports_chart_data[] = $r; }
$reports_chart_data = array_reverse($reports_chart_data);

include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* 👑 Ultra-Clean Premium Admin Dashboard Styles */
.admin-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311b92 100%);
    border-radius: 1.75rem;
    padding: 2.25rem 2.5rem;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.admin-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(circle at 85% 30%, rgba(99, 102, 241, 0.35) 0%, transparent 60%);
    pointer-events: none;
}
.admin-hero::after {
    content: '';
    position: absolute;
    width: 250px; height: 250px;
    bottom: -80px; right: -50px;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, transparent 70%);
    pointer-events: none;
}

.global-search-input {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    padding: 0.75rem 1.5rem 0.75rem 3rem;
    color: #fff; width: 100%; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.92rem;
}
.global-search-input:focus {
    background: #ffffff; color: #0f172a; border-color: #ffffff;
    box-shadow: 0 12px 30px -5px rgba(0, 0, 0, 0.25); outline: none;
}
.global-search-input::placeholder { color: rgba(255, 255, 255, 0.65); }
.global-search-input:focus::placeholder { color: #94a3b8; }
.search-wrap { position: relative; max-width: 420px; width: 100%; z-index: 10; }
.search-icon { position: absolute; left: 1.15rem; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.6); pointer-events: none; transition: color 0.3s; }
.global-search-input:focus + .search-icon { color: var(--accent); }
.search-dropdown {
    position: absolute; top: 115%; left: 0; right: 0;
    background: #ffffff; border-radius: 1.25rem;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.15);
    border: 1px solid rgba(226, 232, 240, 0.8); display: none; z-index: 1100; overflow: hidden;
}
.search-item { padding: 0.85rem 1.25rem; display: flex; align-items: center; gap: 0.85rem; text-decoration: none !important; color: #1e293b; transition: background 0.15s; border-bottom: 1px solid #f1f5f9; }
.search-item:last-child { border-bottom: none; }
.search-item:hover { background: #f8fafc; }
.search-item-ic { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, 0.08); color: var(--accent); }

.alert-bar {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: 1.25rem; padding: 1rem 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.08);
}
@keyframes pulseDot {
    0%,100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.6); }
    60% { box-shadow: 0 0 0 7px rgba(217, 119, 6, 0); }
}
.pulse-dot { width: 10px; height: 10px; background: #d97706; border-radius: 50%; animation: pulseDot 2s infinite; flex-shrink: 0; }

.stat-card {
    background: #ffffff; border-radius: 1.25rem; padding: 1.25rem 1rem;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center; height: 100%;
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 16px 32px -5px rgba(15, 23, 42, 0.08); border-color: rgba(99, 102, 241, 0.25); }

.menu-card {
    background: #ffffff; border-radius: 1.25rem; padding: 1.35rem 1.1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex; flex-direction: column; align-items: center; text-align: center;
    text-decoration: none !important; position: relative; overflow: hidden;
}
.menu-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: transparent; transition: background 0.3s;
}
.menu-card:hover { transform: translateY(-6px); box-shadow: 0 16px 35px -5px rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.4); }
.menu-card:hover::before { background: linear-gradient(90deg, #4f46e5, #a855f7); }
.menu-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.85rem; transition: transform 0.3s; }
.menu-card:hover .menu-icon { transform: scale(1.08); }

.chart-card { background: #ffffff; border-radius: 1.35rem; padding: 1.5rem; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03); height: 100%; }
.chart-title { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem; letter-spacing: -0.2px; }

.section-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 1.15rem; display: flex; align-items: center; gap: 0.6rem; letter-spacing: -0.3px; }
.section-title-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(99, 102, 241, 0.08); display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #4f46e5; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
.fade-up { animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) backwards; }
</style>

<!-- 👑 Hero Header -->
<div class="admin-hero fade-up">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3" style="position:relative;z-index:1;">
        <div>
            <span class="badge mb-2 fw-bold px-3 py-2" style="background:rgba(255,255,255,0.12);color:#fff;font-size:0.68rem;letter-spacing:0.5px;">SYSTEM INFRASTRUCTURE</span>
            <h1 class="fw-bold text-white mb-1" style="font-size:1.7rem;letter-spacing:-0.5px;">ยินดีต้อนรับ, <?= e($u['fullname'] ?? 'ผู้ดูแลระบบ') ?></h1>
            <p class="mb-0 small" style="color:rgba(255,255,255,0.75);">ควบคุมสิทธิ์ ปรับตั้งค่า และติดตามความคืบหน้าองค์กรแบบ Real-time</p>
        </div>
        <div class="search-wrap fade-up" style="animation-delay:0.1s;">
            <input type="text" id="adminGlobalSearch" class="global-search-input" placeholder="ค้นหานักเรียน, ครู, บริษัท..." autocomplete="off">
            <i class="bi bi-search search-icon"></i>
            <div class="search-dropdown" id="searchResults"></div>
        </div>
    </div>
</div>

<!-- 🔔 Alert Bar -->
<?php if ($pending_reports > 0 || $unread_messages > 0 || $total_at_risk_students > 0): ?>
<div class="alert-bar fade-up" style="animation-delay:0.15s;">
    <div class="d-flex align-items-center gap-3">
        <div class="pulse-dot"></div>
        <div>
            <strong class="text-warning-emphasis d-block" style="font-size:0.9rem;">มีรายการที่ต้องดำเนินการ</strong>
            <div class="small text-muted">
                <?php if ($total_at_risk_students > 0): ?>
                    <span class="text-danger fw-semibold">เด็กเสี่ยงขาดส่ง <?= $total_at_risk_students ?> คน</span> &bull; 
                <?php endif; ?>
                รอตรวจ <strong><?= $pending_reports ?></strong> ฉบับ &bull; ข้อความใหม่ <strong><?= $unread_messages ?></strong>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
        <?php if ($total_at_risk_students > 0): ?>
            <a href="../admin/dropout_risk.php" class="btn btn-sm btn-danger rounded-pill fw-bold px-3">ติดตามเด็ก</a>
        <?php endif; ?>
        <a href="../admin/manage_messages.php" class="btn btn-sm fw-bold px-3 rounded-pill" style="background:#d97706;color:#fff;">จัดการข้อความ</a>
    </div>
</div>
<?php endif; ?>

<!-- 📊 Charts Row -->
<div class="row g-3 mb-4 fade-up" style="animation-delay:0.2s;">
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-graph-up text-primary"></i> แนวโน้มการส่งสมุดบันทึกรายวัน (ย้อนหลัง 7 วัน)</div>
            <div id="chartActivity" style="min-height:240px;"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-title"><i class="bi bi-pie-chart-fill" style="color:#6366f1;"></i> สัดส่วนผู้ใช้งาน</div>
            <div id="chartUsers" style="min-height:240px;"></div>
        </div>
    </div>
</div>

<!-- 📈 Stats Grid -->
<div class="section-title fade-up" style="animation-delay:0.25s;">
    <div class="section-title-icon"><i class="bi bi-bar-chart-fill"></i></div> สรุปข้อมูล
</div>
<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['val'=>$total_students,    'lbl'=>'นักเรียน',            'icon'=>'bi-people-fill',       'bg'=>'#eef2ff','color'=>'#4f46e5'],
        ['val'=>$total_teachers,    'lbl'=>'ครูที่ปรึกษา',        'icon'=>'bi-person-badge',      'bg'=>'#ecfdf5','color'=>'#059669'],
        ['val'=>$total_staff,       'lbl'=>'เจ้าหน้าที่',         'icon'=>'bi-person-gear',       'bg'=>'#f5f3ff','color'=>'#7c3aed'],
        ['val'=>$total_supervisors, 'lbl'=>'Supervisor',           'icon'=>'bi-person-heart',      'bg'=>'#eff6ff','color'=>'#2563eb'],
        ['val'=>$total_directors,   'lbl'=>'ผู้บริหาร',           'icon'=>'bi-person-workspace',  'bg'=>'#fff7ed','color'=>'#c2410c'],
        ['val'=>$total_reports,     'lbl'=>'บันทึกรวม',           'icon'=>'bi-journal-text',      'bg'=>'#ecfeff','color'=>'#0891b2'],
        ['val'=>$pending_reports,   'lbl'=>'รอตรวจค้าง',          'icon'=>'bi-clipboard-x',       'bg'=>'#fff1f2','color'=>'#e11d48'],
        ['val'=>$unread_messages,   'lbl'=>'ข้อความใหม่',         'icon'=>'bi-envelope',          'bg'=>'#fff1f2','color'=>'#dc2626'],
        ['val'=>$eligible_certs,    'lbl'=>'พร้อมออกเกียรติบัตร', 'icon'=>'bi-award',             'bg'=>'#fefce8','color'=>'#ca8a04'],
        ['val'=>$total_plans,       'lbl'=>'แผนนิเทศ',            'icon'=>'bi-file-earmark-text', 'bg'=>'#ecfdf5','color'=>'#16a34a'],
        ['val'=>$total_companies,   'lbl'=>'สถานประกอบการ',       'icon'=>'bi-building',          'bg'=>'#eff6ff','color'=>'#1d4ed8'],
        ['val'=>$total_at_risk_students,'lbl'=>'เสี่ยงขาดส่ง',   'icon'=>'bi-exclamation-octagon-fill','bg'=>'#fff1f2','color'=>'#dc2626'],
    ];
    $d = 0.28;
    foreach($stats as $s):
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-xl-2 fade-up" style="animation-delay:<?= $d ?>s;">
        <div class="stat-card">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:46px;height:46px;background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
                <i class="bi <?= $s['icon'] ?> fs-5"></i>
            </div>
            <div class="fw-bold" style="font-size:1.6rem;color:#0f172a;line-height:1;"><?= number_format($s['val']) ?></div>
            <div class="text-muted mt-1" style="font-size:0.72rem;"><?= $s['lbl'] ?></div>
        </div>
    </div>
    <?php $d += 0.02; endforeach; ?>
</div>

<!-- 👥 User Management -->
<div class="section-title fade-up" style="animation-delay:0.45s;">
    <div class="section-title-icon"><i class="bi bi-people-fill"></i></div> จัดการผู้ใช้งาน
</div>
<div class="row g-3 mb-4">
    <?php
    $users = [
        ['url'=>'../admin/manage_users.php?role=student',  'icon'=>'bi-person-video2', 'color'=>'#2563eb','bg'=>'#eff6ff','title'=>'จัดการนักเรียน','desc'=>'เพิ่ม / แก้ไข / ลบ'],
        ['url'=>'../admin/manage_users.php?role=teacher',  'icon'=>'bi-person-badge',  'color'=>'#059669','bg'=>'#ecfdf5','title'=>'จัดการครู',       'desc'=>'ครูที่ปรึกษา'],
        ['url'=>'../admin/manage_users.php?role=staff',    'icon'=>'bi-person-gear',   'color'=>'#7c3aed','bg'=>'#f5f3ff','title'=>'จัดการเจ้าหน้าที่','desc'=>'บัญชีเจ้าหน้าที่'],
        ['url'=>'../admin/manage_users.php?role=supervisor','icon'=>'bi-person-heart', 'color'=>'#db2777','bg'=>'#fdf2f8','title'=>'Supervisor',       'desc'=>'ผู้ดูแลการฝึกงาน'],
        ['url'=>'../admin/manage_supervisors.php',         'icon'=>'bi-person-check-fill','color'=>'#0d9488','bg'=>'#f0fdfa','title'=>'มอบหมาย Supervisor','desc'=>'จับคู่กับนักเรียน'],
        ['url'=>'../admin/manage_users.php?role=director', 'icon'=>'bi-person-workspace','color'=>'#ea580c','bg'=>'#fff7ed','title'=>'ผู้บริหาร',     'desc'=>'บัญชีผู้บริหาร'],
        ['url'=>'../admin/manage_users.php?role=admin',    'icon'=>'bi-shield-lock',   'color'=>'#64748b','bg'=>'#f8fafc','title'=>'Admin',           'desc'=>'ผู้ดูแลระบบ'],
    ];
    foreach($users as $item):
    ?>
    <div class="col-6 col-md-3">
        <a href="<?= $item['url'] ?>" class="menu-card">
            <div class="menu-icon" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;"><i class="bi <?= $item['icon'] ?>"></i></div>
            <span class="fw-bold text-dark" style="font-size:0.9rem;"><?= $item['title'] ?></span>
            <span class="text-muted" style="font-size:0.72rem;"><?= $item['desc'] ?></span>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- 🗂 System & Reports -->
<div class="section-title fade-up">
    <div class="section-title-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div> ระบบและรายงาน
</div>
<div class="row g-3 mb-5">
    <?php
    $sys = [
        ['url'=>'../admin/dropout_risk.php',           'icon'=>'bi-exclamation-octagon-fill','color'=>'#dc2626','bg'=>'#fff1f2','title'=>'เด็กเสี่ยงขาดส่ง',       'badge'=>$total_at_risk_students],
        ['url'=>'../admin/export_reports.php',         'icon'=>'bi-file-earmark-excel-fill', 'color'=>'#16a34a','bg'=>'#ecfdf5','title'=>'ส่งออก Excel'],
        ['url'=>'../includes/send_announcement.php',   'icon'=>'bi-megaphone',              'color'=>'#7c3aed','bg'=>'#f5f3ff','title'=>'ส่งประกาศระบบ'],
        ['url'=>'../admin/view_student_reports.php',   'icon'=>'bi-journal-text',           'color'=>'#2563eb','bg'=>'#eff6ff','title'=>'สมุดบันทึกการฝึกงาน'],
        ['url'=>'../admin/manage_messages.php',        'icon'=>'bi-envelope-paper',         'color'=>'#dc3545','bg'=>'#fff1f2','title'=>'กล่องข้อความ',             'badge'=>$unread_messages],
        ['url'=>'../admin/assign_teachers.php',        'icon'=>'bi-link-45deg',             'color'=>'#0891b2','bg'=>'#ecfeff','title'=>'มอบหมายครู'],
        ['url'=>'../admin/manage_classrooms.php',      'icon'=>'bi-door-open',              'color'=>'#64748b','bg'=>'#f8fafc','title'=>'ห้องเรียน'],
        ['url'=>'../admin/companies.php',              'icon'=>'bi-building',               'color'=>'#059669','bg'=>'#ecfdf5','title'=>'สถานประกอบการ'],
        ['url'=>'../admin/manage_certificates.php',    'icon'=>'bi-award',                  'color'=>'#ca8a04','bg'=>'#fefce8','title'=>'เกียรติบัตร'],
        ['url'=>'../admin/manage_supervision.php',     'icon'=>'bi-clipboard-check',        'color'=>'#d97706','bg'=>'#fffbeb','title'=>'แผนการนิเทศ'],
        ['url'=>'../admin/internship_settings.php',    'icon'=>'bi-calendar-check',         'color'=>'#2563eb','bg'=>'#eff6ff','title'=>'กำหนดเวลาฝึกงาน'],
        ['url'=>'../admin/site_settings.php',          'icon'=>'bi-gear-wide-connected',    'color'=>'#6d28d9','bg'=>'#f5f3ff','title'=>'ตั้งค่าระบบ'],
        ['url'=>'../admin/system_tools.php',           'icon'=>'bi-wrench-adjustable-circle','color'=>'#c2410c','bg'=>'#fff7ed','title'=>'เครื่องมือ & Backup'],
        ['url'=>'../admin/profile.php',                'icon'=>'bi-person-gear',            'color'=>'#4f46e5','bg'=>'#eef2ff','title'=>'จัดการโปรไฟล์'],
    ];
    foreach($sys as $item):
    ?>
    <div class="col-6 col-md-3">
        <a href="<?= $item['url'] ?>" class="menu-card position-relative">
            <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                <span class="position-absolute badge bg-danger rounded-circle border border-white" style="top:8px;right:8px;font-size:0.68rem;"><?= $item['badge'] ?></span>
            <?php endif; ?>
            <div class="menu-icon" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;"><i class="bi <?= $item['icon'] ?>"></i></div>
            <span class="fw-bold text-dark" style="font-size:0.9rem;"><?= $item['title'] ?></span>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ── ApexChart: Activity ── */
    new ApexCharts(document.querySelector('#chartActivity'), {
        series: [{ name: 'บันทึก', data: [<?php echo empty($reports_chart_data) ? '0,0,0,0,0' : implode(',', array_column($reports_chart_data,'qty')); ?>] }],
        chart: { type: 'area', height: 240, toolbar: { show: false }, fontFamily: 'inherit' },
        colors: ['#4f46e5'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.02 } },
        xaxis: { categories: [<?php echo empty($reports_chart_data) ? "'N/A'" : "'" . implode("','", array_column($reports_chart_data,'day')) . "'"; ?>], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        tooltip: { theme: 'light' }
    }).render();

    /* ── ApexChart: Users Donut ── */
    new ApexCharts(document.querySelector('#chartUsers'), {
        series: [<?= $total_students ?>, <?= $total_teachers ?>, <?= $total_staff + $total_admins + $total_directors ?>],
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        labels: ['นักเรียน', 'ครูที่ปรึกษา', 'บุคลากร'],
        colors: ['#4f46e5', '#10b981', '#64748b'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'ผู้ใช้รวม', color: '#64748b', formatter: w => w.globals.seriesTotals.reduce((a,b)=>a+b,0) } } } } }
    }).render();

    /* ── Live Global Search ── */
    const searchInput = document.getElementById('adminGlobalSearch');
    const results     = document.getElementById('searchResults');
    let timer;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            timer = setTimeout(() => {
                fetch('../admin/ajax_global_search.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.length) {
                            results.innerHTML = '<div class="p-3 text-center text-muted small"><i class="bi bi-search d-block fs-4 mb-1"></i>ไม่พบข้อมูล</div>';
                        } else {
                            results.innerHTML = data.map(i => `
                                <a href="${i.link}" class="search-item">
                                    <div class="search-item-ic"><i class="bi ${i.icon}"></i></div>
                                    <div><div class="fw-bold small">${i.label}</div><div class="text-muted" style="font-size:0.7rem;">${i.category}</div></div>
                                </a>`).join('');
                        }
                        results.style.display = 'block';
                    }).catch(()=>{});
            }, 280);
        });
        document.addEventListener('click', e => {
            if (!searchInput.contains(e.target) && !results.contains(e.target)) results.style.display = 'none';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>