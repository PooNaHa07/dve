<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id'];

// Get teacher's rooms
$room_ids = [];
$res = $conn->query("SELECT classroom_id FROM teacher_assignments WHERE teacher_id=$teacher_id");
while($r=$res->fetch_assoc()) $room_ids[] = (int)$r['classroom_id'];
$in_rooms = empty($room_ids) ? '0' : implode(',', $room_ids);

// Handle POST edit_comment
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['act'] ?? '';
    $rid = (int)($_POST['rid'] ?? 0);
    if($rid > 0 && $action === 'edit_comment'){
        $comment = trim($_POST['comment'] ?? '');
        $stmt = $conn->prepare("UPDATE daily_reports SET teacher_comment=?
            WHERE id=? AND status='rejected' AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id=ta.classroom_id WHERE ta.teacher_id=?)");
        $stmt->bind_param('sii', $comment, $rid, $teacher_id);
        $stmt->execute();
        $back = $_POST['back'] ?? 'report_history.php';
        header("Location: $back");
        exit;
    }
}

// Selected date (default = today)
$sel_date = isset($_GET['date']) && ($_GET['date'] === 'all' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']))
    ? $_GET['date'] : date('Y-m-d');
$cal_date = ($sel_date === 'all') ? date('Y-m-d') : $sel_date;
$sel_ts  = strtotime($cal_date);
$cal_year  = (int)date('Y', $sel_ts);
$cal_month = (int)date('n', $sel_ts);

// Month summary (which dates have approved/rejected records)
$m_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$m_end   = date('Y-m-t', strtotime($m_start));
$month_data = [];
$mq = $conn->query("SELECT dr.date_work,
    COUNT(*) as total,
    SUM(CASE WHEN dr.status IN ('approved', 'rejected') THEN 1 ELSE 0 END) as checked
    FROM daily_reports dr JOIN users u ON dr.student_id=u.id
    WHERE dr.date_work BETWEEN '$m_start' AND '$m_end'
      AND u.classroom_id IN($in_rooms) AND u.role='student'
      AND dr.status IN ('approved', 'rejected')
    GROUP BY dr.date_work");
while($r=$mq->fetch_assoc()) $month_data[$r['date_work']] = $r;

// Total checked overall
$checked_all_time = 0;
$ct = $conn->query("SELECT COUNT(*) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.status IN ('approved', 'rejected') AND u.classroom_id IN($in_rooms) AND u.role='student'");
if($ct) $checked_all_time = (int)$ct->fetch_assoc()['c'];

// Daily checked count
$checked_daily = 0;
if($sel_date !== 'all' && isset($month_data[$sel_date])) {
    $checked_daily = (int)$month_data[$sel_date]['checked'];
}

// Total and submitted students
$total_students = 0;
$ts_q = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND classroom_id IN($in_rooms)");
if($ts_q) $total_students = (int)$ts_q->fetch_assoc()['c'];

$submitted_students = 0;
if($sel_date !== 'all') {
    $ss_q = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.date_work='$sel_date' AND u.classroom_id IN($in_rooms) AND u.role='student'");
    if($ss_q) $submitted_students = (int)$ss_q->fetch_assoc()['c'];
}
if($sel_date !== 'all' && isset($month_data[$sel_date])) {
    $checked_daily = (int)$month_data[$sel_date]['checked'];
}

// Thai helpers
$th_months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$th_days   = ['อา','จ','อ','พ','พฤ','ศ','ส'];
function thDate($ymd){ global $th_months;
    $t = strtotime($ymd);
    return (int)date('j',$t).' '.$th_months[(int)date('n',$t)].' '.(date('Y',$t)+543);
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
/* ─── Premium Mobile-First & Card Design ─── */
body {
  background: #f4f7fe;
  font-family: 'Inter', 'Prompt', sans-serif;
  color: #1e293b;
}

.teacher-page-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

/* ─── Layout & Page Animations ─── */
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

/* ─── Hide Hero Banner if any ─── */
.hero-banner, .page-header-wrapper.has-banner { display: none !important; }
.teacher-page-container { margin-top: 1.5rem; }

/* ─── Premium Glassmorphic Mini Calendar ─── */
.mini-cal {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid rgba(67, 97, 238, 0.08);
  padding: 24px;
  user-select: none;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.01);
  transition: all 0.3s ease;
}
.mini-cal:hover {
  box-shadow: 0 20px 40px -15px rgba(67, 97, 238, 0.06), 0 1px 3px rgba(0, 0, 0, 0.01);
  border-color: rgba(67, 97, 238, 0.15);
}
.mc-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.mc-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: -0.3px;
}
.mc-arrow {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  color: #64748b;
  cursor: pointer;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.mc-arrow:hover {
  background: var(--tch-soft-blue, rgba(67, 97, 238, 0.08));
  color: #4361ee;
  border-color: rgba(67, 97, 238, 0.2);
  transform: scale(1.05);
}
.mc-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}
.mc-dow {
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  padding: 6px 0;
  text-transform: uppercase;
}
.mc-day {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  margin: auto;
}
.mc-day:hover {
  background: rgba(67, 97, 238, 0.06);
  color: #4361ee;
  transform: scale(1.08);
}
.mc-day.today {
  border: 2px solid #4361ee;
  color: #4361ee;
  font-weight: 700;
}
.mc-day.selected {
  background: #4361ee !important;
  color: #ffffff !important;
  font-weight: 700;
  box-shadow: 0 8px 16px -4px rgba(67, 97, 238, 0.4);
  transform: scale(1.05);
}
.mc-day.has-pending::after {
  content: none !important;
}
.mc-day-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #10b981;
  color: #ffffff !important;
  font-size: 0.62rem;
  font-weight: 800;
  min-width: 15px;
  height: 15px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 1px solid #ffffff;
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4);
  z-index: 2;
}
.mc-day.selected .mc-day-badge {
  border-color: #4361ee;
}
.mc-day.has-record {
  color: #1e293b;
}
.mc-day.other-month {
  color: #cbd5e1;
  cursor: default;
}
.mc-day.other-month:hover {
  background: none;
  color: #cbd5e1;
  transform: none;
}
.mc-pending-info {
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px dashed #e2e8f0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ─── Search Box Styling ─── */
.search-box-wrapper {
  margin-bottom: 24px !important;
  animation: ultraSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.search-input-group {
  background: #ffffff !important;
  border: 1.5px solid #e2e8f0 !important;
  border-radius: 16px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02) !important;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
  overflow: hidden;
  display: flex;
}
.search-input-group:hover {
  border-color: #cbd5e1 !important;
}
.search-input-group:focus-within {
  border-color: #1e3a8a !important;
  box-shadow: 0 8px 24px rgba(30, 58, 138, 0.08) !important;
  transform: translateY(-1px);
}
.search-input-group .input-group-text {
  border: none !important;
  background: transparent !important;
  color: #1e3a8a !important;
  font-size: 1.15rem !important;
  padding-left: 18px !important;
  padding-right: 10px !important;
  display: flex;
  align-items: center;
}
.search-input-group .form-control {
  border: none !important;
  background: transparent !important;
  font-size: 0.95rem !important;
  color: #1e293b !important;
  padding-top: 12px !important;
  padding-bottom: 12px !important;
  padding-left: 4px !important;
  box-shadow: none !important;
  flex: 1;
  outline: none;
}
.search-input-group .form-control::placeholder {
  color: #94a3b8 !important;
}
.search-input-group .clear-search-btn {
  border: none !important;
  background: transparent !important;
  color: #94a3b8 !important;
  padding-right: 18px !important;
  padding-left: 10px !important;
  transition: all 0.2s ease !important;
  cursor: pointer;
  display: flex;
  align-items: center;
}
.search-input-group .clear-search-btn:hover {
  transform: scale(1.15) !important;
  color: #64748b !important;
}

/* Report Card List (Grid on desktop) */
.rc-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
  gap: 20px;
}
@media(max-width: 1400px) {
  .rc-list { grid-template-columns: 1fr; }
}

/* Report Card (rc) */
.rc {
  background: #ffffff;
  border-radius: 20px;
  padding: 24px;
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: 20px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 10px 30px rgba(0,0,0,0.03);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}
.rc:hover {
  transform: translateY(-4px);
  box-shadow: 0 20px 40px rgba(0,0,0,0.06);
  border-color: #e2e8f0;
}
.rc::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 5px;
  border-radius: 5px 0 0 5px;
}
.rc.status-approved::before { background: #10b981; }
.rc.status-rejected::before { background: #ef4444; }

.rc-left { display: flex; flex-direction: column; align-items: flex-start; }
.rc-name { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; line-height: 1.3; }
.rc-code { font-size: 0.85rem; color: #64748b; font-weight: 600; margin-bottom: 12px; }

.rc-badges { display: flex; flex-direction: column; gap: 8px; width: 100%; }
.rc-badge-class, .rc-badge-company, .rc-badge-date {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
}
.rc-badge-class { background: #e0e7ff; color: #4338ca; }
.rc-badge-company { background: #f1f5f9; color: #475569; }
.rc-badge-date { background: #fef3c7; color: #d97706; }

.rc-detail {
  padding-left: 20px;
  border-left: 1px dashed #e2e8f0;
  font-size: 0.95rem;
  color: #334155;
  line-height: 1.6;
}
.rc-detail-text { font-weight: 500; color: #1e293b; margin-bottom: 12px; }

.status-done-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 700;
  margin-top: 12px;
}
.sdb-approved { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.sdb-rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.empty-state { text-align: center; padding: 5rem 2rem; color: #94a3b8; }
.empty-state i {
  font-size: 4rem; display: block; margin-bottom: 1.25rem;
  background: linear-gradient(135deg, #94a3b8 0%, #cbd5e1 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; opacity: 0.7;
}

@keyframes ultraSlideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.btn-back-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  font-size: 1.25rem;
  text-decoration: none;
  transition: all 0.2s;
  box-shadow: 0 4px 6px rgba(0,0,0,0.02);
}
.btn-back-circle:hover {
  background: #f8fafc;
  color: #1e3a8a;
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.05);
}

.rp-header {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.rp-date {
  color: #4361ee;
}

/* ─── Mobile Styles ─── */
@media (max-width: 768px) {
  /* Hide desktop header / default elements */
  .page-header-wrapper,
  .main-content-container,
  .mini-cal,
  .desktop-footer,
  footer {
    display: none !important;
  }
  
  /* Reset body styling */
  body {
    background: #f8fafc !important; /* Premium light slate gray */
  }
  
  /* Reset page container */
  .teacher-page-container {
    padding: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
  }
  
  /* Hide redundant mobile header bar - standard navbar is used instead */
  .mobile-header-bar {
    display: none !important;
  }
  
  /* Adjust margins and layout for mobile wrapper */
  .ar-wrap {
    grid-template-columns: 1fr !important;
    gap: 0 !important;
  }
  
  .ar-wrap > div:last-child {
    padding: 20px 16px 20px 16px !important;
  }

  
  .mobile-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }
  
  .mobile-logo-text {
    font-size: 1.15rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 6px;
    letter-spacing: -0.5px;
    color: #ffffff;
  }
  
  .mobile-logo-text span {
    font-weight: 300;
    opacity: 0.8;
  }
  
  .mobile-header-title {
    font-size: 1.38rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.5px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .mobile-back-btn { color: #ffffff; font-size: 1.5rem; text-decoration: none; }
  
  /* Mobile Days Slider */
  .mobile-cal-container {
    display: block !important;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 0;
    position: sticky;
    top: 70px; /* Below standard sticky header */
    z-index: 999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  }
  
  .mobile-month-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px 12px 20px;
  }
  
  .mobile-days-scroller {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 0 20px 8px 20px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none; /* Firefox */
  }
  
  .mobile-days-scroller::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
  }
  
  .m-day-item {
    min-width: 62px;
    height: 72px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    scroll-snap-align: center;
    transition: all 0.2s;
    background: #ffffff;
    position: relative;
  }
  
  .m-day-item.has-pending {
    border-color: #10b981;
    background: #ecfdf5;
  }
  
  .m-day-item.today {
    border: 2px solid #4361ee;
  }
  
  .m-day-item.selected {
    background: #4361ee !important;
    border-color: #4361ee !important;
    color: #ffffff;
    box-shadow: 0 8px 16px rgba(67, 97, 238, 0.25);
    transform: translateY(-2px);
  }
  
  .m-day-dow {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 2px;
  }
  
  .m-day-item.selected .m-day-dow,
  .m-day-item.selected .m-day-mon {
    color: rgba(255, 255, 255, 0.8);
  }
  
  .m-day-num {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
  }
  
  .m-day-item.selected .m-day-num {
    color: #ffffff;
  }
  
  .m-day-mon {
    font-size: 0.65rem;
    font-weight: 600;
    color: #94a3b8;
    margin-top: 2px;
  }
  
  .m-day-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #10b981;
    color: white;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 10px;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
  }

  .rc-list { grid-template-columns: 1fr; gap: 16px; padding: 20px; }
  .search-box-wrapper { padding: 20px 20px 0 20px; margin-bottom: 0; }
  
  .rc {
    grid-template-columns: 1fr !important;
    padding: 20px !important;
    border-radius: 18px !important;
    gap: 16px !important;
  }
  .rc-mobile-profile { display: flex !important; align-items: center; gap: 14px; }
  .rc-mobile-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; background: #f1f5f9; }
  .rc-mobile-text { display: flex; flex-direction: column; }
  .rc-name { font-size: 1.05rem !important; margin-bottom: 2px !important; }
  .rc-left { display: none !important; } /* Hide desktop left part on mobile */
  .rc-detail { border-left: none !important; padding-left: 0 !important; }
  
  /* Show badges in detail section for mobile */
  .mobile-badges { display: flex !important; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
}
</style>

<div class="container teacher-page-container mt-4">
  <!-- Mobile Header Bar -->
  <div class="mobile-header-bar" style="display:none;">
    <div class="mobile-header-top">
      <div class="mobile-logo-text">
        <i class="bi bi-patch-check-fill text-warning me-1"></i>
        DVE | <span>PBPVC</span>
      </div>
      <div class="mobile-user-status">
        <span class="badge bg-success rounded-pill px-2.5 py-1" style="font-size:0.75rem;"><i class="bi bi-check-circle-fill me-1"></i>ตรวจแล้ว <?=$checked_all_time?></span>
      </div>
    </div>
    <div class="mobile-header-title">
      <a href="../roles/teacher.php" class="mobile-back-btn"><i class="bi bi-arrow-left"></i></a>
      ประวัติการตรวจบันทึก
    </div>
  </div>

  <!-- Mobile Month Navigation Bar & Days Scroller -->
  <div class="mobile-cal-container" style="display:none;">
    <div class="mobile-month-nav">
      <?php
      $prev_m = $cal_month - 1; $prev_y = $cal_year;
      if($prev_m < 1){ $prev_m = 12; $prev_y--; }
      $next_m = $cal_month + 1; $next_y = $cal_year;
      if($next_m > 12){ $next_m = 1; $next_y++; }
      ?>
      <button class="btn btn-sm btn-link text-muted text-decoration-none p-0 fw-bold" onclick="goMonth(<?=$prev_y?>,<?=$prev_m?>)">
        <i class="bi bi-chevron-left me-1"></i>เดือนก่อนหน้า
      </button>
      <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?=$th_months[$cal_month]?> <?=$cal_year+543?></span>
      <button class="btn btn-sm btn-link text-muted text-decoration-none p-0 fw-bold" onclick="goMonth(<?=$next_y?>,<?=$next_m?>)">
        เดือนถัดไป<i class="bi bi-chevron-right ms-1"></i>
      </button>
    </div>
    <div class="mobile-days-scroller">
      <div class="m-day-item <?= ($sel_date === 'all') ? 'selected' : '' ?>" onclick="goDate('all')" data-date="all" style="min-width: 68px;">
        <span class="m-day-dow"><i class="bi bi-calendar-range" style="font-size: 0.95rem;"></i></span>
        <span class="m-day-num" style="font-size: 0.92rem; font-weight: 800; margin-top: 2px; margin-bottom: 2px;">ทุกวัน</span>
        <span class="m-day-mon">ทั้งหมด</span>
      </div>
      <?php
      $days_in = (int)date('t', mktime(0,0,0,$cal_month,1,$cal_year));
      $today = date('Y-m-d');
      $short_th_days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
      $short_th_months = [
          1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
          7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
      ];
      
      for($d=1; $d<=$days_in; $d++){
          $ds = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
          $dow_num = (int)date('w', mktime(0,0,0,$cal_month, $d, $cal_year));
          $dow_text = $short_th_days[$dow_num];
          $mon_text = $short_th_months[$cal_month];
          
          $info = $month_data[$ds] ?? null;
          $is_selected = ($ds === $sel_date);
          $is_today = ($ds === $today);
          
          $item_cls = 'm-day-item';
          if($is_selected) $item_cls .= ' selected';
          if($is_today) $item_cls .= ' today';
          if($info && $info['checked'] > 0) $item_cls .= ' has-pending';
          ?>
          <div class="<?=$item_cls?>" onclick="goDate('<?=$ds?>')" data-date="<?=$ds?>">
            <span class="m-day-dow"><?=$dow_text?></span>
            <span class="m-day-num"><?=$d?></span>
            <span class="m-day-mon"><?=$mon_text?></span>
            <?php if($info && $info['checked'] > 0): ?>
              <span class="m-day-badge"><?=number_format($info['checked'])?></span>
            <?php endif; ?>
          </div>
          <?php
      }
      ?>
    </div>
  </div>

  <div class="page-header-wrapper" style="background:transparent; padding:0; box-shadow:none; margin-bottom:1rem;">
    <h4 class="page-header-title d-flex align-items-center m-0" style="font-size:1.4rem;">
      <a href="../roles/teacher.php" class="btn-back-circle me-3"><i class="bi bi-arrow-left"></i></a>
      ประวัติการตรวจสอบบันทึกรายวัน
      <?php if($checked_all_time>0): ?>
        <span class="badge rounded-pill ms-3" style="background:#10b981;color:#fff;font-size:.8rem;padding:6px 14px;"><?=number_format($checked_all_time)?> ตรวจแล้ว</span>
      <?php endif; ?>
    </h4>
  </div>

  <div class="ar-wrap">
    <!-- ══ LEFT: Mini Calendar ══ -->
    <div>
      <div class="mini-cal">
        <div class="mc-nav">
          <button class="mc-arrow" onclick="goMonth(<?=$prev_y?>,<?=$prev_m?>)"><i class="bi bi-chevron-left"></i></button>
          <span class="mc-title"><?=$th_months[$cal_month]?> <?=$cal_year+543?></span>
          <button class="mc-arrow" onclick="goMonth(<?=$next_y?>,<?=$next_m?>)"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="mc-grid">
          <?php foreach($th_days as $d): ?>
            <div class="mc-dow"><?=$d?></div>
          <?php endforeach; ?>
          <?php
          $first_dow = (int)date('w', mktime(0,0,0,$cal_month,1,$cal_year));
          $days_in   = (int)date('t', mktime(0,0,0,$cal_month,1,$cal_year));
          $today     = date('Y-m-d');
          for($i=0;$i<$first_dow;$i++) echo '<div></div>';
          for($d=1;$d<=$days_in;$d++){
              $ds = sprintf('%04d-%02d-%02d',$cal_year,$cal_month,$d);
              $cls = 'mc-day';
              $info = $month_data[$ds] ?? null;
              if($info) $cls .= ' has-record';
              if($info && $info['checked']>0) $cls .= ' has-pending';
              if($ds === $today) $cls .= ' today';
              if($ds === $sel_date) $cls .= ' selected';
              $count_tip = $info ? "title=\"{$info['total']} รายการ\"" : '';
              $badge = '';
              if($info && $info['checked'] > 0){
                  $badge = "<span class=\"mc-day-badge\">" . number_format($info['checked']) . "</span>";
              }
              echo "<div class=\"$cls\" $count_tip onclick=\"goDate('$ds')\">$d$badge</div>";
          }
          ?>
        </div>
        <div class="mt-4">
          <button class="btn w-100 rounded-3 fw-bold py-2 <?= ($sel_date === 'all') ? 'text-white' : 'text-primary' ?>" 
                  style="<?= ($sel_date === 'all') ? 'background: #4361ee; box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3); border:none;' : 'background: rgba(67, 97, 238, 0.08); border: 1px solid rgba(67, 97, 238, 0.2);' ?>" 
                  onclick="goDate('all')">
            <i class="bi bi-calendar-range me-2"></i> ดูประวัติทั้งหมด (ทุกวัน)
          </button>
        </div>
        <div class="mc-pending-info">
          <i class="bi bi-check-circle-fill text-success"></i>
          ตรวจแล้วประจำวัน <?=number_format($checked_daily)?> รายการ
        </div>
      </div>
    </div>

    <!-- ══ RIGHT: Report List ══ -->
    <div>
      <div class="rp-header d-flex align-items-center justify-content-between flex-wrap gap-2 mt-md-0 mt-3 px-3 px-md-0">
        <div class="d-none d-md-block">
          <i class="bi bi-journal-text text-primary"></i>
          ประวัติการตรวจสอบ - <span class="rp-date"><?= ($sel_date === 'all') ? 'ทั้งหมด' : thDate($sel_date) ?></span>
        </div>
        <?php if($sel_date !== 'all'): 
          $unsubmitted = $total_students - $submitted_students;
        ?>
          <div class="d-flex gap-2">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2" style="font-size: 0.82rem;">
              <i class="bi bi-check-circle-fill me-1"></i> บันทึกแล้ว <?=$submitted_students?>
            </span>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-2" style="font-size: 0.82rem;">
              <i class="bi bi-x-circle-fill me-1"></i> ยังไม่บันทึก <?=$unsubmitted?>
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2" style="font-size: 0.82rem;">
              <i class="bi bi-people-fill me-1"></i> รวม <?=$total_students?>
            </span>
          </div>
        <?php else: ?>
          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2" style="font-size: 0.85rem;">
            <i class="bi bi-people-fill me-1"></i> รวมนักศึกษาทั้งหมด <?=$total_students?> คน
          </span>
        <?php endif; ?>
      </div>

      <!-- Search Box -->
      <div class="search-box-wrapper">
        <div class="search-input-group">
          <span class="input-group-text">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="reportSearchInput" class="form-control" placeholder="ค้นหาตามชื่อ, รหัส หรือสถานประกอบการ..." onkeyup="filterReports()">
          <button class="clear-search-btn" type="button" id="clearSearchBtn" onclick="clearSearch()" style="display: none;">
            <i class="bi bi-x-circle-fill"></i>
          </button>
        </div>
      </div>

      <div class="rc-list" id="reportsList">
        <?php
        $date_cond = ($sel_date === 'all') ? "" : "AND dr.date_work='$sel_date'";
        $sql = "SELECT dr.*, u.fullname, u.student_code, u.profile_image, COALESCE(comp.name, u.company_name) as company_name, cl.class_name 
                FROM daily_reports dr 
                JOIN users u ON dr.student_id = u.id 
                LEFT JOIN classrooms cl ON u.classroom_id = cl.id
                LEFT JOIN companies comp ON u.company_id = comp.id
                WHERE dr.status IN ('approved', 'rejected') AND u.classroom_id IN ($in_rooms) $date_cond 
                ORDER BY u.student_code ASC, dr.date_work DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
            $seq = 1;
            while($r = $result->fetch_assoc()):
                $name    = htmlspecialchars($r['fullname']);
                $code    = htmlspecialchars($r['student_code'] ?? '');
                $cls_nm  = htmlspecialchars($r['class_name'] ?? '');
                $comp    = htmlspecialchars($r['company_name'] ?? '');
                $detail  = htmlspecialchars($r['details'] ?? '');
                $problems= htmlspecialchars($r['problems'] ?? '');
                $solutions=htmlspecialchars($r['solutions'] ?? '');
                $tc_cmt  = htmlspecialchars($r['teacher_comment'] ?? '');
                $status  = $r['status'];
                $date_str= date('d/m/Y', strtotime($r['date_work']));
                $has_imgs = (!empty($r['image1']) || !empty($r['image2']));
        ?>
        <div class="rc status-<?=$status?>">
          <!-- Mobile Profile Header -->
          <div class="rc-mobile-profile" style="display:none;">
            <img src="<?=$r['profile_image'] ? '../uploads/avatars/'.htmlspecialchars($r['profile_image']) : '../assets/img/default-avatar.png'?>" class="rc-mobile-avatar" alt="avatar" onerror="this.src='../assets/img/default-avatar.png';">
            <div class="rc-mobile-text">
              <span class="rc-name"><?=$seq?>. <?=$name?></span>
              <span class="rc-code">รหัส: <?=$code?></span>
            </div>
          </div>

          <div class="rc-left">
            <div class="rc-name"><?=$seq++?>. <?=$name?></div>
            <?php if($code): ?><div class="rc-code">รหัส: <?=$code?></div><?php endif; ?>
            <div class="rc-badges">
              <?php if($sel_date === 'all'): ?>
                <span class="rc-badge-date"><i class="bi bi-calendar3 me-1"></i><?=thDate($r['date_work'])?></span>
              <?php endif; ?>
              <?php if($cls_nm): ?>
                <span class="rc-badge-class"><i class="bi bi-bookmark-fill me-1"></i><?=$cls_nm?></span>
              <?php endif; ?>
              <?php if($comp): ?>
                <span class="rc-badge-company"><i class="bi bi-briefcase-fill me-1"></i><?=$comp?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="rc-detail">
            <div class="mobile-badges" style="display:none;">
              <?php if($sel_date === 'all'): ?>
                <span class="rc-badge-date"><i class="bi bi-calendar3 me-1"></i><?=thDate($r['date_work'])?></span>
              <?php endif; ?>
              <?php if($comp): ?><span class="rc-badge-company"><i class="bi bi-briefcase-fill me-1"></i><?=$comp?></span><?php endif; ?>
            </div>
            
            <?php if($detail): ?>
              <div class="rc-detail-text"><?=nl2br($detail)?></div>
            <?php endif; ?>
            
            <?php
             $imgs = [];
             if(!empty($r['image1'])) $imgs[] = $r['image1'];
             if(!empty($r['image2'])) $imgs[] = $r['image2'];
             if($imgs): ?>
               <div class="d-flex gap-2 mt-3">
                 <?php foreach($imgs as $img): 
                   $hist_img_url = get_report_image_url($img);
                 ?>
                   <img src="<?=$hist_img_url?>" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;cursor:pointer;" onclick="viewImg(this.src)" alt="ภาพ" onerror="handleReportImgError(this, '<?=htmlspecialchars($img, ENT_QUOTES)?>')">
                 <?php endforeach; ?>
               </div>
             <?php endif; ?>

            <?php if($problems): ?>
              <div class="mt-2 text-warning" style="font-size:.85rem;"><strong>ปัญหา:</strong> <?=nl2br($problems)?></div>
            <?php endif; ?>
            <?php if($solutions): ?>
              <div class="mt-1 text-success" style="font-size:.85rem;"><strong>แก้ไข:</strong> <?=nl2br($solutions)?></div>
            <?php endif; ?>
            
            <?php if($tc_cmt): ?>
              <div class="mt-3 px-3 py-2 rounded-3 d-flex justify-content-between align-items-start" style="background:#f0f4ff;border-left:3px solid #4361ee;font-size:.85rem;">
                <div><i class="bi bi-chat-square-text me-1 text-primary"></i><?=nl2br($tc_cmt)?></div>
                <?php if($status === 'rejected'): ?>
                  <button type="button" class="btn btn-sm btn-link text-primary p-0 ms-2" onclick="openEditComment(<?=$r['id']?>, '<?=addslashes($name)?>', '<?=addslashes($r['teacher_comment'] ?? '')?>')" title="แก้ไขคอมเมนท์">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <div class="mt-3">
              <?php if($status === 'approved'): ?>
                <span class="status-done-badge sdb-approved"><i class="bi bi-check-circle-fill"></i>ผ่านแล้ว</span>
              <?php else: ?>
                <span class="status-done-badge sdb-rejected"><i class="bi bi-exclamation-triangle-fill"></i>รอนักเรียนส่งใหม่</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php 
            endwhile; 
        else:
        ?>
        <div class="premium-card" style="grid-column: 1 / -1;">
          <div class="empty-state">
            <i class="bi bi-archive"></i>
            ไม่มีประวัติการประเมินบันทึกรายวันในวันที่เลือก
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Edit Comment Modal -->
<div class="mo-backdrop" id="editCommentModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; animation: modalFadeIn 0.25s ease-out;">
  <div class="mo-box" style="background: #ffffff; border-radius: 20px; padding: 32px; width: min(460px, 92vw); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>แก้ไขคอมเมนท์</h5>
      <button onclick="document.getElementById('editCommentModal').classList.remove('show'); document.getElementById('editCommentModal').style.display = 'none';" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>
    <p class="text-muted small mb-3">นักเรียน: <strong id="ec-name"></strong></p>
    <form method="POST" id="editCommentForm">
      <input type="hidden" name="act" value="edit_comment">
      <input type="hidden" name="rid" id="ec-rid" value="">
      <input type="hidden" name="back" value="report_history.php?date=<?=$sel_date?>">
      <textarea name="comment" id="ec-comment" class="form-control mb-3" rows="4" placeholder="ระบุเหตุผล เช่น ข้อมูลไม่ครบถ้วน..." required></textarea>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light fw-bold" onclick="document.getElementById('editCommentModal').classList.remove('show'); document.getElementById('editCommentModal').style.display = 'none';">ยกเลิก</button>
        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openEditComment(id, name, comment){
  document.getElementById('ec-rid').value = id;
  document.getElementById('ec-name').textContent = name;
  document.getElementById('ec-comment').value = comment;
  document.getElementById('editCommentModal').style.display = 'flex';
  document.getElementById('editCommentModal').classList.add('show');
}
document.getElementById('editCommentModal').addEventListener('click', function(e){
  if(e.target === this) {
    this.classList.remove('show');
    this.style.display = 'none';
  }
});
function filterReports() {
  const query = document.getElementById('reportSearchInput').value.toLowerCase().trim();
  const cards = document.querySelectorAll('.rc');
  const clearBtn = document.getElementById('clearSearchBtn');
  
  if (query.length > 0) {
    clearBtn.style.display = 'block';
  } else {
    clearBtn.style.display = 'none';
  }
  
  let visibleCount = 0;
  
  cards.forEach(card => {
    const textContent = card.textContent.toLowerCase();
    
    if (textContent.includes(query)) {
      card.style.setProperty('display', '', 'important');
      visibleCount++;
    } else {
      card.style.setProperty('display', 'none', 'important');
    }
  });
  
  let emptyState = document.getElementById('searchEmptyState');
  if (visibleCount === 0 && cards.length > 0) {
    if (!emptyState) {
      emptyState = document.createElement('div');
      emptyState.id = 'searchEmptyState';
      emptyState.className = 'premium-card text-center p-5 mt-3';
      emptyState.style.borderRadius = '20px';
      emptyState.style.gridColumn = '1 / -1';
      emptyState.innerHTML = `
        <div class="empty-state" style="padding: 3rem 1rem;">
          <i class="bi bi-search-heart text-muted" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mt-3 text-dark">ไม่พบข้อมูลที่ค้นหา</h5>
          <p class="text-muted small">ลองใช้คำค้นหาอื่น เช่น ชื่อนักเรียน วันที่ หรือรหัสนักเรียน</p>
        </div>
      `;
      document.getElementById('reportsList').appendChild(emptyState);
    } else {
      emptyState.style.display = 'block';
    }
  } else {
    if (emptyState) {
      emptyState.style.display = 'none';
    }
  }
}

function clearSearch() {
  const input = document.getElementById('reportSearchInput');
  input.value = '';
  filterReports();
  input.focus();
}

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

function viewImg(src){
  Swal.fire({
    imageUrl: src,
    showCloseButton: true,
    showConfirmButton: false,
    background: '#fff',
    backdrop: 'rgba(0,0,0,.8)',
    customClass: {
      image: 'rounded-3 shadow-lg',
      popup: 'p-2'
    }
  });
}

function goDate(d){
  window.location.href = '?date=' + d;
}

function goMonth(y, m){
  const newDate = y + '-' + String(m).padStart(2,'0') + '-01';
  window.location.href = '?date=' + newDate;
}

// Auto-scroll selected day on mobile
document.addEventListener('DOMContentLoaded', () => {
  const scroller = document.querySelector('.mobile-days-scroller');
  const selDay = document.querySelector('.m-day-item.selected');
  if (scroller && selDay) {
    const sRect = scroller.getBoundingClientRect();
    const dRect = selDay.getBoundingClientRect();
    const offset = dRect.left - sRect.left - (sRect.width / 2) + (dRect.width / 2);
    scroller.scrollBy({ left: offset, behavior: 'smooth' });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>