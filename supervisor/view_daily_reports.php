<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['supervisor']);

$u             = current_user();
$supervisor_id = (int)$u['id'];

// ดึง student_ids ทั้งหมดที่ supervisor คนนี้ดูแล
$student_ids = get_supervised_student_ids($supervisor_id);
$ids_sql     = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// Selected date (default = today or 'all')
$sel_date = isset($_GET['date']) && ($_GET['date'] === 'all' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']))
    ? $_GET['date'] : 'all';
$cal_date = ($sel_date === 'all') ? date('Y-m-d') : $sel_date;
$sel_ts   = strtotime($cal_date);
$cal_year  = (int)date('Y', $sel_ts);
$cal_month = (int)date('n', $sel_ts);

// Status filter for feedback (uncommented = รอ feedback, commented = คอมเมนต์แล้ว, all = ทั้งหมด)
$status_filter = isset($_GET['status_filter']) && in_array($_GET['status_filter'], ['uncommented', 'commented', 'all'])
    ? $_GET['status_filter']
    : (isset($_GET['uncommented']) ? 'uncommented' : 'all');

// Student filter if selected
$filter_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Report ID filter if selected
$filter_report  = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

// Month summary (which dates have records)
$m_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$m_end   = date('Y-m-t', strtotime($m_start));
$month_data = [];

if (!empty($student_ids)) {
    $mq = $conn->query("SELECT dr.date_work,
        COUNT(*) as total,
        SUM(CASE WHEN (dr.supervisor_comment IS NULL OR dr.supervisor_comment = '') THEN 1 ELSE 0 END) as uncommented,
        SUM(CASE WHEN (dr.supervisor_comment IS NOT NULL AND dr.supervisor_comment != '') THEN 1 ELSE 0 END) as commented
        FROM daily_reports dr
        JOIN users u ON dr.student_id = u.id
        WHERE dr.date_work BETWEEN '$m_start' AND '$m_end'
          AND dr.student_id IN ($ids_sql) AND u.role = 'student'
        GROUP BY dr.date_work");
    if ($mq) {
        while ($r = $mq->fetch_assoc()) {
            $month_data[$r['date_work']] = $r;
        }
    }
}

// WHERE conditions
$where = ["dr.student_id IN ($ids_sql)", "u.role = 'student'"];
if ($filter_report > 0) {
    $where[] = "dr.id = " . $filter_report;
} else {
    if ($sel_date !== 'all') {
        $where[] = "dr.date_work = '" . $conn->real_escape_string($sel_date) . "'";
    }
    if ($status_filter === 'uncommented') {
        $where[] = "(dr.supervisor_comment IS NULL OR dr.supervisor_comment = '')";
    } elseif ($status_filter === 'commented') {
        $where[] = "(dr.supervisor_comment IS NOT NULL AND dr.supervisor_comment != '')";
    }
    if ($filter_student > 0) {
        $where[] = "dr.student_id = " . (int)$filter_student;
    }
}
$where_sql = "WHERE " . implode(" AND ", $where);

// Fetch reports
$reports = [];
if (!empty($student_ids)) {
    $rq = $conn->query("SELECT dr.*, u.fullname AS student_name, u.student_code, u.profile_image,
        COALESCE(comp.name, u.company_name) AS company_name, cl.class_name
        FROM daily_reports dr
        JOIN users u ON dr.student_id = u.id
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id
        LEFT JOIN companies comp ON u.company_id = comp.id
        $where_sql
        ORDER BY dr.date_work DESC, u.student_code ASC, dr.created_at DESC");
    if ($rq) {
        while ($r = $rq->fetch_assoc()) {
            $reports[] = $r;
        }
    }
}

// Totals calculation
$uncommented_total = 0;
$commented_total   = 0;
$total_students    = count($student_ids);
$submitted_students = 0;

if (!empty($student_ids)) {
    $ut = $conn->query("SELECT COUNT(*) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE (dr.supervisor_comment IS NULL OR dr.supervisor_comment='') AND dr.student_id IN ($ids_sql) AND u.role='student'");
    if ($ut) $uncommented_total = (int)$ut->fetch_assoc()['c'];

    $ct = $conn->query("SELECT COUNT(*) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.supervisor_comment IS NOT NULL AND dr.supervisor_comment!='' AND dr.student_id IN ($ids_sql) AND u.role='student'");
    if ($ct) $commented_total = (int)$ct->fetch_assoc()['c'];

    if ($sel_date !== 'all') {
        $safe_sel_date = $conn->real_escape_string($sel_date);
        $ss_q = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.date_work='$safe_sel_date' AND dr.student_id IN ($ids_sql) AND u.role='student'");
        if ($ss_q) $submitted_students = (int)$ss_q->fetch_assoc()['c'];
    }
}

// Thai date helpers
$th_months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$th_days   = ['อา','จ','อ','พ','พฤ','ศ','ส'];
function thDate($ymd) {
    global $th_months;
    $t = strtotime($ymd);
    return (int)date('j', $t) . ' ' . $th_months[(int)date('n', $t)] . ' ' . (date('Y', $t) + 543);
}

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
/* ─── Custom Supervisor Theme Overrides ─── */
:root {
  --sv-primary: #0ea5e9;
  --sv-primary-dark: #0284c7;
  --sv-soft-bg: rgba(14, 165, 233, 0.08);
}

.ar-wrap {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 28px;
  align-items: start;
  animation: ultraFadeIn 0.5s ease-out;
}
@media(max-width: 992px) {
  .ar-wrap { grid-template-columns: 1fr; }
}

@keyframes ultraFadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.mini-cal {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid rgba(14, 165, 233, 0.12);
  padding: 24px;
  user-select: none;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
}
.mini-cal:hover {
  box-shadow: 0 20px 40px -15px rgba(14, 165, 233, 0.1);
  border-color: rgba(14, 165, 233, 0.25);
}

.mc-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.mc-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; letter-spacing: -0.3px; }
.mc-arrow {
  background: #f8fafc; border: 1px solid #f1f5f9; color: #64748b;
  cursor: pointer; width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; transition: all 0.25s ease;
}
.mc-arrow:hover { background: rgba(14,165,233,.1); color: #0ea5e9; border-color: rgba(14,165,233,.3); transform: scale(1.05); }

.mc-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.mc-dow { text-align: center; font-size: 0.75rem; font-weight: 700; color: #94a3b8; padding-bottom: 8px; }

.mc-day {
  height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 600; color: #334155; cursor: pointer; position: relative; transition: all 0.2s ease;
}
.mc-day:hover { background: #f1f5f9; color: #0ea5e9; }
.mc-day.today { font-weight: 800; color: #0ea5e9; }
.mc-day.today::before {
  content: ''; position: absolute; bottom: 4px; width: 4px; height: 4px; border-radius: 50%; background: #0ea5e9;
}
.mc-day.selected { background: #0ea5e9 !important; color: #ffffff !important; font-weight: 800; box-shadow: 0 4px 12px rgba(14,165,233,.3); }
.mc-day.selected::before { background: #ffffff !important; }

.mc-day-badge {
  position: absolute; top: 1px; right: 1px; min-width: 15px; height: 15px;
  border-radius: 50%; font-size: 0.6rem; font-weight: 800; color: #fff;
  display: flex; align-items: center; justify-content: center; padding: 0 3px;
}

/* ─── Search Box Styling ─── */
.search-input-group {
  background: #ffffff !important;
  border: 1.5px solid #e2e8f0 !important;
  border-radius: 16px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02) !important;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
  overflow: hidden;
  align-items: center;
}
.search-input-group:focus-within {
  border-color: #0ea5e9 !important;
  box-shadow: 0 8px 24px rgba(14, 165, 233, 0.12) !important;
}
.clear-search-btn {
  border: none !important;
  background: #f1f5f9 !important;
  color: #64748b !important;
  width: 28px !important;
  height: 28px !important;
  border-radius: 50% !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  margin-right: 12px !important;
  padding: 0 !important;
  font-size: 0.8rem !important;
  cursor: pointer !important;
  opacity: 0;
  visibility: hidden;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
  transform: scale(0.8);
}
.clear-search-btn.show {
  opacity: 1;
  visibility: visible;
  transform: scale(1);
}
.clear-search-btn:hover {
  background: #fee2e2 !important;
  color: #ef4444 !important;
  transform: scale(1.1) !important;
}

/* ─── Filter Pills ─── */
.filter-pill {
  padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 700;
  text-decoration: none; color: #64748b; background: #ffffff; border: 1px solid #e2e8f0;
  transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;
}
.filter-pill:hover { background: #f8fafc; color: #0ea5e9; border-color: rgba(14,165,233,.3); }
.filter-pill.active { background: #0ea5e9; color: #ffffff; border-color: #0ea5e9; box-shadow: 0 4px 12px rgba(14,165,233,.25); }

/* ─── Report Card Styling ─── */
.report-card-item {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  padding: 24px;
  margin-bottom: 20px;
  transition: all 0.3s ease;
}
.report-card-item:hover {
  box-shadow: 0 12px 32px rgba(14, 165, 233, 0.1);
  border-color: rgba(14, 165, 233, 0.2);
}

.sv-fb-box {
  background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
  border: 1.5px solid rgba(14, 165, 233, 0.25);
  border-radius: 16px;
  padding: 16px;
  margin-top: 16px;
}
.sv-fb-textarea {
  border: 1.5px solid rgba(14, 165, 233, 0.2);
  border-radius: 12px;
  font-size: 0.9rem;
  resize: vertical;
  min-height: 85px;
  transition: all 0.2s;
}
.sv-fb-textarea:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  outline: none;
}
</style>

<div class="container teacher-page-container mt-4">
  <!-- Page Header -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <h4 class="fw-extrabold text-dark d-flex align-items-center m-0" style="font-size:1.4rem;">
      <a href="../roles/supervisor.php" class="btn btn-light rounded-circle me-3 border shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
        <i class="bi bi-arrow-left"></i>
      </a>
      เลือกการปฏิบัติงานรายวัน (ตรวจบันทึกงานนักเรียนฝึกงาน)
      <span class="badge rounded-pill ms-3" style="background:#ef4444;color:#fff;font-size:.8rem;padding:6px 14px; <?= $uncommented_total > 0 ? '' : 'display:none;' ?>">
        <?= number_format($uncommented_total) ?> รอ feedback
      </span>
      <span class="badge rounded-pill ms-2" style="background:#10b981;color:#fff;font-size:.8rem;padding:6px 14px; <?= $commented_total > 0 ? '' : 'display:none;' ?>">
        <?= number_format($commented_total) ?> คอมเมนต์แล้ว
      </span>
    </h4>
  </div>

  <div class="ar-wrap">
    <!-- ══ LEFT: Mini Calendar & Filters ══ -->
    <div>
      <!-- Mini Calendar Card -->
      <div class="mini-cal mb-4">
        <?php
        $prev_m = $cal_month - 1; $prev_y = $cal_year;
        if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
        $next_m = $cal_month + 1; $next_y = $cal_year;
        if ($next_m > 12) { $next_m = 1; $next_y++; }
        ?>
        <div class="mc-nav">
          <button class="mc-arrow" onclick="goMonth(<?=$prev_y?>,<?=$prev_m?>)"><i class="bi bi-chevron-left"></i></button>
          <span class="mc-title"><?=$th_months[$cal_month]?> <?=$cal_year+543?></span>
          <button class="mc-arrow" onclick="goMonth(<?=$next_y?>,<?=$next_m?>)"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="mc-grid">
          <?php foreach ($th_days as $d): ?>
            <div class="mc-dow"><?=$d?></div>
          <?php endforeach; ?>
          <?php
          $first_dow = (int)date('w', mktime(0,0,0,$cal_month,1,$cal_year));
          $days_in   = (int)date('t', mktime(0,0,0,$cal_month,1,$cal_year));
          $today     = date('Y-m-d');
          for ($i=0; $i<$first_dow; $i++) echo '<div></div>';
          for ($d=1; $d<=$days_in; $d++) {
              $ds = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
              $cls = 'mc-day';
              $info = $month_data[$ds] ?? null;

              $show_badge  = false;
              $badge_count = 0;
              $badge_color = 'bg-secondary';

              if ($status_filter === 'uncommented' && $info && $info['uncommented'] > 0) {
                  $show_badge  = true;
                  $badge_count = $info['uncommented'];
                  $badge_color = 'bg-danger';
              } elseif ($status_filter === 'commented' && $info && $info['commented'] > 0) {
                  $show_badge  = true;
                  $badge_count = $info['commented'];
                  $badge_color = 'bg-success';
              } elseif ($status_filter === 'all' && $info && $info['total'] > 0) {
                  $show_badge  = true;
                  $badge_count = $info['total'];
                  $badge_color = 'bg-info';
              }

              if ($ds === $today) $cls .= ' today';
              if ($ds === $sel_date) $cls .= ' selected';
              $count_tip = $info ? "title=\"{$info['total']} รายการ\"" : '';
              $badge = $show_badge ? "<span class=\"mc-day-badge $badge_color\">" . number_format($badge_count) . "</span>" : '';
              echo "<div class=\"$cls\" data-cal-date=\"$ds\" $count_tip onclick=\"goDate('$ds')\">$d$badge</div>";
          }
          ?>
        </div>
      </div>

      <!-- Quick Date Selector Card -->
      <div class="p-3 bg-white rounded-4 border mb-4 shadow-sm" style="border-color:rgba(0,0,0,.06)!important;">
        <div class="fw-bold text-dark small mb-2"><i class="bi bi-calendar3 me-1 text-primary"></i> เลือกวันแสดงผล</div>
        <div class="d-grid gap-2">
          <button class="btn btn-sm <?= $sel_date === date('Y-m-d') ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-3 text-start fw-bold" onclick="goDate('<?= date('Y-m-d') ?>')">
            <i class="bi bi-calendar-event me-2"></i> วันนี้ (<?= thDate(date('Y-m-d')) ?>)
          </button>
          <button class="btn btn-sm <?= $sel_date === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-3 text-start fw-bold" onclick="goDate('all')">
            <i class="bi bi-calendar-range me-2"></i> แสดงทุกวันตลอดการฝึกงาน
          </button>
        </div>
      </div>

      <!-- Stat Info Card -->
      <div class="p-3 bg-white rounded-4 border shadow-sm" style="border-color:rgba(0,0,0,.06)!important;">
        <div class="fw-bold text-dark small mb-3"><i class="bi bi-pie-chart-fill me-1 text-primary"></i> สรุปการเข้าส่งงาน</div>
        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
          <span class="text-secondary small">นักเรียนในดูแลทั้งหมด</span>
          <span class="fw-bold text-dark"><?= number_format($total_students) ?> คน</span>
        </div>
        <?php if ($sel_date !== 'all'): ?>
        <div class="d-flex justify-content-between align-items-center">
          <span class="text-secondary small">ส่งแล้ววันที่ <?= date('d/m', strtotime($sel_date)) ?></span>
          <span class="fw-bold text-success"><?= number_format($submitted_students) ?> คน</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ RIGHT: Filters & Report List ══ -->
    <div>
      <!-- Filter Bar -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <!-- Status Pills -->
        <div class="d-flex gap-2 flex-wrap">
          <a href="#" class="filter-pill <?= $status_filter === 'all' ? 'active' : '' ?>" onclick="goStatus('all'); return false;">
            <i class="bi bi-layers-fill"></i> ทั้งหมด
          </a>
          <a href="#" class="filter-pill <?= $status_filter === 'uncommented' ? 'active' : '' ?>" onclick="goStatus('uncommented'); return false;">
            <i class="bi bi-chat-dots-fill text-danger"></i> รอ Feedback
            <?php if ($uncommented_total > 0): ?>
              <span class="badge bg-danger rounded-pill"><?= $uncommented_total ?></span>
            <?php endif; ?>
          </a>
          <a href="#" class="filter-pill <?= $status_filter === 'commented' ? 'active' : '' ?>" onclick="goStatus('commented'); return false;">
            <i class="bi bi-check-circle-fill text-success"></i> คอมเมนต์แล้ว
            <?php if ($commented_total > 0): ?>
              <span class="badge bg-success rounded-pill"><?= $commented_total ?></span>
            <?php endif; ?>
          </a>
        </div>

        <!-- Student Dropdown -->
        <?php if (!empty($student_ids)): ?>
        <div style="min-width: 220px;">
          <select id="studentSelect" class="form-select rounded-3 form-select-sm fw-bold border-secondary border-opacity-25" onchange="filterStudent(this.value)">
            <option value="0">-- นักเรียนทุกคน --</option>
            <?php
            $sr = $conn->query("SELECT id, fullname, student_code FROM users WHERE id IN ($ids_sql) AND role='student' ORDER BY fullname ASC");
            if ($sr) while ($st = $sr->fetch_assoc()):
                $selected = ($filter_student === (int)$st['id']) ? 'selected' : '';
            ?>
            <option value="<?= $st['id'] ?>" <?= $selected ?>>
              <?= htmlspecialchars($st['fullname']) ?> <?= !empty($st['student_code']) ? '(' . htmlspecialchars($st['student_code']) . ')' : '' ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <!-- Live Search Box -->
      <div class="search-input-group input-group mb-4">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาตามชื่อนักเรียน หรือรหัสนักศึกษา..." onkeyup="filterReports()" oninput="filterReports()">
        <button type="button" id="clearSearchBtn" class="clear-search-btn" onclick="clearSearch()" title="ล้างคำค้นหา">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- Report List -->
      <div id="reportsContainer">
        <?php if (!empty($reports)): ?>
        <?php foreach ($reports as $r):
            $has_comment = !empty($r['supervisor_comment']);
            $status_class = $r['status'] === 'approved' ? 'sdb-approved' : ($r['status'] === 'rejected' ? 'sdb-rejected' : 'bg-warning bg-opacity-10 text-warning');
            $status_text  = $r['status'] === 'approved' ? 'ผ่านแล้ว' : ($r['status'] === 'rejected' ? 'ส่งกลับแก้ไข' : 'รอครูตรวจ');
        ?>
        <div class="report-card-item report-row-item"
             data-student="<?= htmlspecialchars(mb_strtolower($r['student_name'])) ?>"
             data-code="<?= htmlspecialchars(mb_strtolower($r['student_code'] ?? '')) ?>"
             id="report-<?= $r['id'] ?>">

          <!-- Card Header Info -->
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
              <div style="width:48px;height:48px;border-radius:50%;overflow:hidden;background:#e0f2fe;display:flex;align-items:center;justify-content:center;border:2px solid rgba(14,165,233,.2);flex-shrink:0;">
                <?php if (!empty($r['profile_image']) && file_exists(__DIR__ . '/../uploads/avatars/' . $r['profile_image'])): ?>
                  <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($r['profile_image']) ?>?v=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <i class="bi bi-person-fill fs-4" style="color:#0ea5e9;"></i>
                <?php endif; ?>
              </div>
              <div>
                <h6 class="fw-bold text-dark mb-0" style="font-size:1rem;"><?= htmlspecialchars($r['student_name']) ?></h6>
                <div class="text-muted small">
                  <?= htmlspecialchars($r['student_code'] ?? '-') ?>
                  <?php if (!empty($r['class_name'])): ?>
                    <span class="ms-2 badge bg-primary bg-opacity-10 text-primary fw-bold"><?= htmlspecialchars($r['class_name']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="rc-badge-date">
                <i class="bi bi-calendar3"></i> <?= thDate($r['date_work']) ?>
              </span>
              <span class="status-done-badge <?= $status_class ?>" style="padding:4px 12px;font-size:.78rem;">
                <?= $status_text ?>
              </span>
              <?php if ($has_comment): ?>
              <span class="badge bg-success rounded-pill px-3 py-1.5 fw-bold" style="font-size:.75rem;" id="badge-status-<?= $r['id'] ?>">
                <i class="bi bi-chat-check me-1"></i>คอมเมนต์แล้ว
              </span>
              <?php else: ?>
              <span class="badge bg-danger rounded-pill px-3 py-1.5 fw-bold" style="font-size:.75rem;" id="badge-status-<?= $r['id'] ?>">
                <i class="bi bi-chat-dots me-1"></i>รอ feedback
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Report Contents -->
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="text-uppercase fw-bold text-muted small mb-1" style="font-size:.72rem;letter-spacing:.5px;">สรุปการปฏิบัติงาน</div>
              <div class="text-dark" style="font-size:.9rem;line-height:1.5;white-space:pre-line;"><?= htmlspecialchars($r['details'] ?? '-') ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-uppercase fw-bold text-muted small mb-1" style="font-size:.72rem;letter-spacing:.5px;">ปัญหาที่พบ</div>
              <div class="text-dark" style="font-size:.9rem;line-height:1.5;white-space:pre-line;"><?= htmlspecialchars($r['problems'] ?? '-') ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-uppercase fw-bold text-muted small mb-1" style="font-size:.72rem;letter-spacing:.5px;">แนวทางแก้ไข</div>
              <div class="text-dark" style="font-size:.9rem;line-height:1.5;white-space:pre-line;"><?= htmlspecialchars($r['solutions'] ?? '-') ?></div>
            </div>
          </div>

          <?php if (!empty($r['teacher_comment'])): ?>
          <div class="p-3 rounded-3 mb-3" style="background:#fefce8;border:1px solid rgba(234,179,8,.3);">
            <div class="fw-bold small mb-1" style="color:#a16207;"><i class="bi bi-person-badge me-1"></i>ความเห็นจากครูนิเทศก์</div>
            <div class="small text-dark"><?= nl2br(htmlspecialchars($r['teacher_comment'])) ?></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($r['image1']) || !empty($r['image2'])): ?>
          <div class="mb-3">
            <div class="text-uppercase fw-bold text-muted small mb-1.5" style="font-size:.72rem;letter-spacing:.5px;">
              <i class="bi bi-images text-primary me-1"></i>รูปภาพหลักฐานการปฏิบัติงาน (แตะดูรูปใหญ่)
            </div>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($r['image1'])): 
                $img1_url = get_report_image_url($r['image1']);
              ?>
              <div class="report-img-card overflow-hidden rounded-3 border shadow-sm position-relative" style="width:80px;height:80px;cursor:pointer;" onclick="viewImg('<?= $img1_url ?>', 'ภาพหลักฐาน 1 - <?= htmlspecialchars($r['student_name'], ENT_QUOTES) ?>')">
                <img src="<?= $img1_url ?>" style="width:100%;height:100%;object-fit:cover;transition:transform 0.25s ease;" alt="ภาพ 1" onerror="handleReportImgError(this, '<?= htmlspecialchars($r['image1'], ENT_QUOTES) ?>')">
                <div class="position-absolute bottom-0 end-0 bg-dark bg-opacity-60 text-white px-1.5 py-0.5 small rounded-top-start" style="font-size:.65rem;"><i class="bi bi-zoom-in"></i></div>
              </div>
              <?php endif; ?>

              <?php if (!empty($r['image2'])): 
                $img2_url = get_report_image_url($r['image2']);
              ?>
              <div class="report-img-card overflow-hidden rounded-3 border shadow-sm position-relative" style="width:80px;height:80px;cursor:pointer;" onclick="viewImg('<?= $img2_url ?>', 'ภาพหลักฐาน 2 - <?= htmlspecialchars($r['student_name'], ENT_QUOTES) ?>')">
                <img src="<?= $img2_url ?>" style="width:100%;height:100%;object-fit:cover;transition:transform 0.25s ease;" alt="ภาพ 2" onerror="handleReportImgError(this, '<?= htmlspecialchars($r['image2'], ENT_QUOTES) ?>')">
                <div class="position-absolute bottom-0 end-0 bg-dark bg-opacity-60 text-white px-1.5 py-0.5 small rounded-top-start" style="font-size:.65rem;"><i class="bi bi-zoom-in"></i></div>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Supervisor Feedback Comment Section -->
          <div class="sv-fb-box">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fw-bold small" style="color:#0369a1;">
                <i class="bi bi-chat-quote-fill me-1"></i> ความคิดเห็น/Feedback ของผู้ดูแลการฝึกงาน (คุณ)
              </div>
              <span id="saved-time-<?= $r['id'] ?>" class="text-muted small" style="font-size:.72rem;">
                <?= !empty($r['supervisor_commented_at']) ? 'บันทึกเมื่อ ' . date('d/m/Y H:i', strtotime($r['supervisor_commented_at'])) : '' ?>
              </span>
            </div>
            <textarea id="comment-<?= $r['id'] ?>" class="form-control sv-fb-textarea mb-2" placeholder="พิมพ์ข้อความความคิดเห็น หรือข้อเสนอแนะต่อนักเรียน..."><?= htmlspecialchars($r['supervisor_comment'] ?? '') ?></textarea>
            <div class="d-flex justify-content-between align-items-center">
              <span id="status-msg-<?= $r['id'] ?>" class="small"></span>
              <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="saveFeedback(<?= $r['id'] ?>, <?= $r['student_id'] ?>)">
                <i class="bi bi-save me-1"></i> บันทึก Feedback
              </button>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state bg-white rounded-4 border shadow-sm">
          <i class="bi bi-journal-x"></i>
          <h5 class="fw-bold text-dark mb-1">ไม่พบบันทึกการปฏิบัติงาน</h5>
          <p class="text-muted small mb-0">
            <?= empty($student_ids) ? 'ท่านยังไม่มีนักเรียนที่ได้รับมอบหมาย กรุณาติดต่อเจ้าหน้าที่' : 'ไม่มีรายการบันทึกงานตามเงื่อนไขที่เลือก' ?>
          </p>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

function viewImg(src, title) {
    Swal.fire({
        title: title || 'รูปภาพปฏิบัติงาน',
        imageUrl: src,
        imageAlt: title || 'Work photo',
        showCloseButton: true,
        showConfirmButton: false,
        background: '#fff',
        backdrop: 'rgba(15, 23, 42, 0.85)',
        customClass: {
            image: 'img-fluid rounded-3 shadow-lg',
            popup: 'rounded-4 p-3'
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('report_id');
    if (targetId) {
        const el = document.getElementById('report-' + targetId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.style.border = '2px solid #0ea5e9';
            el.style.boxShadow = '0 10px 25px -5px rgba(14,165,233,0.3)';
        }
    }
});

function goDate(d) {
    const url = new URL(window.location.href);
    url.searchParams.set('date', d);
    window.location.href = url.toString();
}

function goMonth(y, m) {
    const d = y + '-' + String(m).padStart(2, '0') + '-01';
    const url = new URL(window.location.href);
    url.searchParams.set('date', d);
    window.location.href = url.toString();
}

function goStatus(s) {
    const url = new URL(window.location.href);
    url.searchParams.set('status_filter', s);
    window.location.href = url.toString();
}

function filterStudent(stId) {
    const url = new URL(window.location.href);
    if (parseInt(stId) > 0) {
        url.searchParams.set('student_id', stId);
    } else {
        url.searchParams.delete('student_id');
    }
    window.location.href = url.toString();
}

function filterReports() {
    const input    = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const query    = input.value.toLowerCase().trim();
    const rows     = document.querySelectorAll('.report-row-item');

    if (query.length > 0) {
        clearBtn.classList.add('show');
    } else {
        clearBtn.classList.remove('show');
    }

    rows.forEach(row => {
        const name = row.getAttribute('data-student') || '';
        const code = row.getAttribute('data-code') || '';
        if (name.includes(query) || code.includes(query)) {
            row.style.display = 'block';
        } else {
            row.style.display = 'none';
        }
    });
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    input.focus();
    filterReports();
}

function saveFeedback(reportId, studentId) {
    const textarea = document.getElementById('comment-' + reportId);
    const statusMsg = document.getElementById('status-msg-' + reportId);
    const comment = textarea.value.trim();

    statusMsg.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>กำลังบันทึก...</span>';

    const fd = new FormData();
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fd.append('daily_report_id', reportId);
    fd.append('student_id', studentId);
    fd.append('comment', comment);

    fetch('<?= BASE_URL ?>/supervisor/save_comment.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusMsg.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>บันทึกสำเร็จ</span>';
            const badge = document.getElementById('badge-status-' + reportId);
            if (badge) {
                if (comment !== '') {
                    badge.className = 'badge bg-success rounded-pill px-3 py-1.5 fw-bold';
                    badge.innerHTML = '<i class="bi bi-chat-check me-1"></i>คอมเมนต์แล้ว';
                } else {
                    badge.className = 'badge bg-danger rounded-pill px-3 py-1.5 fw-bold';
                    badge.innerHTML = '<i class="bi bi-chat-dots me-1"></i>รอ feedback';
                }
            }
            if (data.commented_at) {
                const savedTime = document.getElementById('saved-time-' + reportId);
                if (savedTime) savedTime.innerText = 'บันทึกเมื่อ ' + data.commented_at;
            }
            setTimeout(() => { statusMsg.innerHTML = ''; }, 2500);
        } else {
            statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' + (data.message || 'เกิดข้อผิดพลาด') + '</span>';
        }
    })
    .catch(() => {
        statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>ไม่สามารถเชื่อมต่อได้</span>';
    });
}
<?php if ($filter_report > 0): ?>
document.addEventListener("DOMContentLoaded", function() {
    const targetEl = document.getElementById("report-<?= $filter_report ?>");
    if (targetEl) {
        targetEl.scrollIntoView({ behavior: "smooth", block: "center" });
        targetEl.style.outline = "2px solid #0ea5e9";
        targetEl.style.outlineOffset = "4px";
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
