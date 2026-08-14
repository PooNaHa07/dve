<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher','admin']);
$u = current_user();
$teacher_id = (int)$u['id'];

// Handle POST approve/reject
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['act'] ?? '';
    $rid = (int)($_POST['rid'] ?? 0);
    if($rid > 0){

        // ดึงข้อมูล report เพื่อส่ง notification
        $r_info_res = $conn->query("SELECT dr.student_id, dr.date_work, u.fullname AS student_name, u.mentor_id
            FROM daily_reports dr JOIN users u ON dr.student_id = u.id
            WHERE dr.id = $rid LIMIT 1");
        $r_info = $r_info_res ? $r_info_res->fetch_assoc() : null;
        $target_student_id = $r_info ? (int)$r_info['student_id'] : 0;
        $report_date       = $r_info['date_work'] ?? '';
        $teacher_name      = $u['fullname'] ?? 'ครูนิเทศก์';

        if($action === 'approve'){
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE daily_reports SET status='approved', approved_by=?, approved_at=?
                WHERE id=? AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id=ta.classroom_id WHERE ta.teacher_id=?)");
            $stmt->bind_param('isii', $teacher_id, $now, $rid, $teacher_id);
            $stmt->execute();

            // ── แจ้งเตือนนักเรียนว่ารายงานผ่านแล้ว ──
            if ($target_student_id > 0) {
                add_notification(
                    $target_student_id,
                    '✅ รายงานผ่านการตรวจแล้ว!',
                    "อาจารย์ {$teacher_name} ได้ตรวจและอนุมัติรายงานวันที่ {$report_date} เรียบร้อยแล้ว",
                    'report_approved',
                    BASE_URL . '/student/view_report.php'
                );
            }
            // ─────────────────────────────────────

        } elseif($action === 'reject'){
            $comment = trim($_POST['comment'] ?? '');
            $stmt = $conn->prepare("UPDATE daily_reports SET status='rejected', teacher_comment=?, approved_by=?, approved_at=NOW()
                WHERE id=? AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id=ta.classroom_id WHERE ta.teacher_id=?)");
            $stmt->bind_param('siii', $comment, $teacher_id, $rid, $teacher_id);
            $stmt->execute();

            // ── แจ้งเตือนนักเรียนว่ารายงานถูกส่งกลับ ──
            if ($target_student_id > 0) {
                $comment_preview = !empty($comment) ? " เหตุผล: " . mb_substr($comment, 0, 60) . (mb_strlen($comment) > 60 ? '…' : '') : '';
                add_notification(
                    $target_student_id,
                    '❗ รายงานถูกส่งกลับให้แก้ไข',
                    "อาจารย์ {$teacher_name} ขอให้แก้ไขรายงานวันที่ {$report_date}{$comment_preview}",
                    'report_rejected',
                    BASE_URL . '/student/edit_report.php?id=' . $rid
                );
            }
            // ───────────────────────────────────────────

        } elseif($action === 'edit_comment'){
            $comment = trim($_POST['comment'] ?? '');
            $stmt = $conn->prepare("UPDATE daily_reports SET teacher_comment=?
                WHERE id=? AND status='rejected' AND student_id IN (SELECT u.id FROM users u JOIN teacher_assignments ta ON u.classroom_id=ta.classroom_id WHERE ta.teacher_id=?)");
            $stmt->bind_param('sii', $comment, $rid, $teacher_id);
            $stmt->execute();
        }

        // Detect AJAX requests and send JSON response
        if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'action' => $action, 'rid' => $rid]);
            exit;
        }

        $back = $_POST['back'] ?? 'approve_reports.php';
        header("Location: $back");
        exit;
    }
}


// Get teacher's rooms
$room_ids = [];
$res = $conn->query("SELECT classroom_id FROM teacher_assignments WHERE teacher_id=$teacher_id");
while($r=$res->fetch_assoc()) $room_ids[] = (int)$r['classroom_id'];
$in_rooms = empty($room_ids) ? '0' : implode(',', $room_ids);

// Selected date (default = today)
$sel_date = isset($_GET['date']) && ($_GET['date'] === 'all' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']))
    ? $_GET['date'] : date('Y-m-d');
$cal_date = ($sel_date === 'all') ? date('Y-m-d') : $sel_date;
$sel_ts  = strtotime($cal_date);
$cal_year  = (int)date('Y', $sel_ts);
$cal_month = (int)date('n', $sel_ts);

// Status filter
$status_filter = isset($_GET['status_filter']) && in_array($_GET['status_filter'], ['pending', 'rejected', 'all']) 
    ? $_GET['status_filter'] : 'pending';

// Month summary (which dates have records)
$m_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$m_end   = date('Y-m-t', strtotime($m_start));
$month_data = [];
$mq = $conn->query("SELECT dr.date_work,
    COUNT(*) as total,
    SUM(CASE WHEN dr.status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN dr.status='rejected' THEN 1 ELSE 0 END) as rejected
    FROM daily_reports dr JOIN users u ON dr.student_id=u.id
    WHERE dr.date_work BETWEEN '$m_start' AND '$m_end'
      AND u.classroom_id IN($in_rooms) AND u.role='student'
    GROUP BY dr.date_work");
while($r=$mq->fetch_assoc()) $month_data[$r['date_work']] = $r;

// Status condition for report query
$status_cond = "";
if ($status_filter === 'pending') {
    $status_cond = "dr.status = 'pending'";
} elseif ($status_filter === 'rejected') {
    $status_cond = "dr.status = 'rejected'";
} else {
    $status_cond = "dr.status IN ('pending', 'rejected')";
}

// Reports for selected date
$reports = [];
$rq = $conn->query("SELECT dr.*, u.fullname, u.student_code, u.profile_image,
    COALESCE(comp.name, u.company_name) as company_name, cl.class_name
    FROM daily_reports dr
    JOIN users u ON dr.student_id=u.id
    LEFT JOIN classrooms cl ON u.classroom_id=cl.id
    LEFT JOIN companies comp ON u.company_id=comp.id
    WHERE " . ($sel_date === 'all' ? "$status_cond AND" : "dr.date_work='$sel_date' AND $status_cond AND") . " u.classroom_id IN($in_rooms) AND u.role='student'
    ORDER BY u.student_code ASC, dr.date_work DESC");
if($rq) {
    while($r=$rq->fetch_assoc()) $reports[] = $r;
}

// Pending count total
$pending_total = 0;
$pt = $conn->query("SELECT COUNT(*) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.status='pending' AND u.classroom_id IN($in_rooms) AND u.role='student'");
if($pt) $pending_total = (int)$pt->fetch_assoc()['c'];

// Rejected count total (waiting resubmission)
$rejected_total = 0;
$rt = $conn->query("SELECT COUNT(*) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.status='rejected' AND u.classroom_id IN($in_rooms) AND u.role='student'");
if($rt) $rejected_total = (int)$rt->fetch_assoc()['c'];

// Total and submitted students
$total_students = 0;
$ts_q = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND classroom_id IN($in_rooms)");
if($ts_q) $total_students = (int)$ts_q->fetch_assoc()['c'];

$submitted_students = 0;
if($sel_date !== 'all') {
    $ss_q = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM daily_reports dr JOIN users u ON dr.student_id=u.id WHERE dr.date_work='$sel_date' AND u.classroom_id IN($in_rooms) AND u.role='student'");
    if($ss_q) $submitted_students = (int)$ss_q->fetch_assoc()['c'];
}

// Thai helpers
$th_months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$th_days   = ['อา','จ','อ','พ','พฤ','ศ','ส'];
function thDate($ymd){ global $th_months;
    $t = strtotime($ymd);
    return (int)date('j',$t).' '.$th_months[(int)date('n',$t)].' '.(date('Y',$t)+543);
}

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
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
  background: #f59e0b;
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
  box-shadow: 0 2px 4px rgba(245, 158, 11, 0.4);
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

/* ─── Report Panel Header ─── */
.rp-header {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  letter-spacing: -0.3px;
}

/* ─── Premium Student Report Cards ─── */
.rc {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  margin-bottom: 18px;
  display: grid;
  grid-template-columns: 280px 1fr auto;
  gap: 24px;
  padding: 24px;
  align-items: center;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.01), 0 2px 4px -1px rgba(0, 0, 0, 0.005);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}
.rc:hover {
  transform: translateY(-3px);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.02), 0 10px 10px -5px rgba(0, 0, 0, 0.01);
  border-color: rgba(67, 97, 238, 0.12);
}

.rc-left {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.rc-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: -0.3px;
}
.rc-code {
  font-size: 0.82rem;
  color: #64748b;
  background: #f8fafc;
  padding: 2px 8px;
  border-radius: 6px;
  display: inline-block;
  align-self: flex-start;
  font-weight: 500;
}
.rc-badges {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
}
.rc-badge-class {
  color: #4361ee;
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(67, 97, 238, 0.06);
  padding: 4px 10px;
  border-radius: 8px;
  align-self: flex-start;
}
.rc-badge-company {
  font-size: 0.8rem;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}
.rc-badge-date {
  font-size: 0.8rem;
  color: #d97706;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  background: #fef3c7;
  padding: 4px 10px;
  border-radius: 8px;
  align-self: flex-start;
}

.rc-detail {
  font-size: 0.95rem;
  color: #334155;
  line-height: 1.6;
  border-left: 2px solid #f1f5f9;
  padding-left: 20px;
}
.rc-detail-text {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.98rem;
}

.rc-actions {
  display: flex;
  flex-direction: row;
  gap: 12px;
}
.act-btn {
  width: 72px;
  height: 72px;
  border-radius: 14px;
  border: 1px solid transparent;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 700;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}
.act-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
}
.act-btn:active {
  transform: translateY(-1px);
}
.act-btn.approve {
  background: #10b981;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}
.act-btn.approve:hover {
  background: #059669;
  box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
}
.act-btn.reject {
  background: #ffffff;
  color: #ef4444;
  border-color: #fecaca;
}
.act-btn.reject:hover {
  background: #fef2f2;
  border-color: #f87171;
}
.act-btn i {
  font-size: 1.4rem;
}

.status-done-badge {
  font-size: 0.85rem;
  font-weight: 700;
  padding: 8px 16px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  letter-spacing: -0.1px;
}
.sdb-approved {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
}
.sdb-rejected {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.empty-state {
  text-align: center;
  padding: 5rem 2rem;
  color: #94a3b8;
}
.empty-state i {
  font-size: 4rem;
  display: block;
  margin-bottom: 1.25rem;
  background: linear-gradient(135deg, #94a3b8 0%, #cbd5e1 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  opacity: 0.7;
}

/* Premium Dialog Modal */
.mo-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.3);
  backdrop-filter: blur(8px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  animation: modalFadeIn 0.25s ease-out;
}
.mo-backdrop.show {
  display: flex;
}
.mo-box {
  background: #ffffff;
  border-radius: 20px;
  padding: 32px;
  width: min(460px, 92vw);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.8);
  animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes modalSlideUp {
  from { transform: translateY(20px) scale(0.95); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
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
}
.search-input-group .form-control::placeholder {
  color: #94a3b8 !important;
}
.search-input-group .clear-search-btn {
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
.search-input-group .clear-search-btn.show {
  opacity: 1;
  visibility: visible;
  transform: scale(1);
}
.search-input-group .clear-search-btn:hover {
  background: #fee2e2 !important;
  color: #ef4444 !important;
  transform: scale(1.1) !important;
}

@media (max-width: 768px) {
  /* Hide desktop header / default elements */
  .page-header-wrapper,
  .main-content-container,
  .mini-cal,
  .rc-left,
  .desktop-footer,
  footer {
    display: none !important;
  }
  
  /* Reset body styling */
  body {
    background: #f8fafc !important; /* Premium light slate gray like mockup */
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
  }
  
  /* Mobile Days Slider */
  .mobile-cal-container {
    display: block !important;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 0;
    position: sticky;
    top: 70px; /* Below standard sticky header */
    z-index: 999;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  }
  
  .mobile-days-scroller {
    display: flex;
    overflow-x: auto;
    gap: 10px;
    padding: 4px 20px;
    scrollbar-width: none; /* Firefox */
  }
  
  .mobile-days-scroller::-webkit-scrollbar {
    display: none; /* Safari and Chrome */
  }
  
  .m-day-item {
    flex: 0 0 58px;
    height: 78px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .m-day-item.selected {
    background: #1e3a8a !important;
    color: #ffffff !important;
    border-color: #1e3a8a !important;
    box-shadow: 0 6px 14px rgba(30, 58, 138, 0.25);
    transform: scale(1.05);
  }
  
  .m-day-dow {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 3px;
  }
  
  .m-day-num {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e293b;
  }
  
  .m-day-mon {
    font-size: 0.65rem;
    font-weight: 600;
    color: #94a3b8;
  }
  
  .m-day-item.selected .m-day-dow,
  .m-day-item.selected .m-day-num,
  .m-day-item.selected .m-day-mon {
    color: #ffffff !important;
  }
  
  .m-day-item.has-pending {
    position: relative;
  }
  
  .m-day-item.has-pending::after {
    content: none !important;
  }
  
  .m-day-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f59e0b;
    color: #ffffff !important;
    font-size: 0.68rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 1.5px solid #ffffff;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
    z-index: 2;
  }
  
  /* Month Nav in Mobile Slider Header */
  .mobile-month-nav {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 8px 20px;
    background: #ffffff;
  }
  
  /* Adjust margins and layout for mobile wrapper */
  .ar-wrap {
    grid-template-columns: 1fr !important;
    gap: 0 !important;
  }
  
  .ar-wrap > div:last-child {
    padding: 20px 16px 20px 16px !important;
  }
  
  /* Report Card Styles on Mobile */
  .rc {
    grid-template-columns: 1fr !important;
    padding: 20px !important;
    gap: 16px !important;
    border-radius: 18px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important;
    background: #ffffff !important;
    margin-bottom: 16px !important;
  }
  
  .rc-mobile-profile {
    display: flex !important;
    align-items: center;
    gap: 14px;
    width: 100%;
  }
  
  .rc-mobile-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
  }
  
  .rc-mobile-text {
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  
  .rc-name {
    font-size: 1.05rem !important;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2px !important;
  }
  
  .rc-code {
    font-size: 0.82rem !important;
    color: #64748b;
    font-weight: 500;
  }
  
  .rc-left { display: none !important; } /* Hide desktop left part on mobile */
  
  .rc-detail {
    border-left: none !important;
    padding-left: 0 !important;
    font-size: 0.98rem !important;
    color: #334155;
    line-height: 1.6;
    margin: 4px 0 8px 0;
  }
  
  .rc-detail-text {
    font-weight: 500;
    color: #1e293b;
  }
  
  /* Show badges in detail section for mobile */
  .mobile-badges { display: flex !important; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
  
  /* Actions Row on Mobile */
  .rc-actions {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 12px !important;
    width: 100% !important;
    margin-top: 8px !important;
  }
  
  .act-btn {
    width: 100% !important;
    height: 46px !important;
    border-radius: 12px !important;
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    box-shadow: none !important;
    margin: 0 !important;
  }
  
  .act-btn.approve {
    background: #10b981 !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15) !important;
  }
  
  .act-btn.reject {
    background: #ef4444 !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.15) !important;
  }
  
  .status-done-badge {
    grid-column: span 2;
    width: 100%;
    justify-content: center;
    height: 46px;
    border-radius: 12px;
    font-size: 0.95rem;
  }
  
  /* Mobile Bottom Navigation Bar */
  .mobile-bottom-nav {
    display: flex !important;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    z-index: 1001;
    justify-content: space-around;
    align-items: center;
    padding-bottom: env(safe-area-inset-bottom);
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.04);
  }
  
  .mobile-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none !important;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  .mobile-nav-item.active {
    color: #1e3a8a;
  }
  
  .mobile-nav-item i {
    font-size: 1.45rem;
    margin-bottom: 2px;
  }
}
</style>

<div class="container teacher-page-container mt-4">
  <!-- Mobile Header Bar -->
  <div class="mobile-header-bar" style="display:none;">
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="mobile-header-title m-0" style="font-size: 1.35rem;">เลือกรายการปฏิบัติรายวัน</h1>
      <span class="badge bg-warning text-dark rounded-pill px-3 py-1" style="font-size:0.8rem; box-shadow:0 2px 5px rgba(0,0,0,0.2);">
        <i class="bi bi-clock-history me-1"></i>รอตรวจ <?=number_format($pending_total)?>
      </span>
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
          
          $show_badge = false;
          $badge_count = 0;
          $badge_bg = '#f59e0b';
          
          if ($status_filter === 'pending' && $info && $info['pending'] > 0) {
              $item_cls .= ' has-pending';
              $show_badge = true;
              $badge_count = $info['pending'];
              $badge_bg = '#f59e0b';
          } elseif ($status_filter === 'rejected' && $info && $info['rejected'] > 0) {
              $item_cls .= ' has-pending';
              $show_badge = true;
              $badge_count = $info['rejected'];
              $badge_bg = '#ef4444';
          } elseif ($status_filter === 'all' && $info && ($info['pending'] > 0 || $info['rejected'] > 0)) {
              $item_cls .= ' has-pending';
              $show_badge = true;
              $badge_count = $info['pending'] + $info['rejected'];
              $badge_bg = '#6b7280';
          }
          ?>
          <div class="<?=$item_cls?>" onclick="goDate('<?=$ds?>')" data-date="<?=$ds?>">
            <span class="m-day-dow"><?=$dow_text?></span>
            <span class="m-day-num"><?=$d?></span>
            <span class="m-day-mon"><?=$mon_text?></span>
            <?php if($show_badge): ?>
              <span class="m-day-badge" style="background: <?=$badge_bg?> !important;"><?=number_format($badge_count)?></span>
            <?php endif; ?>
          </div>
          <?php
      }
      ?>
    </div>
  </div>

  <div class="page-header-wrapper" style="background:transparent; padding:0; box-shadow:none; margin-bottom:1rem;">
    <h4 class="page-header-title d-flex align-items-center m-0" style="font-size:1.4rem;">
      <a href="../roles/teacher.php" class="btn btn-light rounded-circle me-3 border shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-arrow-left"></i></a>
      เลือกการปฏิบัติงานรายวัน (ตรวจบันทึก)
      <span id="headerPendingBadge" class="badge rounded-pill ms-3" style="background:#f59e0b;color:#fff;font-size:.8rem;padding:6px 14px; <?= $pending_total > 0 ? '' : 'display:none;' ?>"><?=number_format($pending_total)?> รอตรวจ</span>
      <span id="headerRejectedBadge" class="badge rounded-pill bg-danger text-white ms-2" style="font-size:.8rem;padding:6px 14px; <?= $rejected_total > 0 ? '' : 'display:none;' ?>"><?=number_format($rejected_total)?> รอนักเรียนส่งใหม่</span>
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
              
              $show_badge = false;
              $badge_count = 0;
              $badge_color = 'bg-warning';
              
              if ($status_filter === 'pending' && $info && $info['pending'] > 0) {
                  $cls .= ' has-pending';
                  $show_badge = true;
                  $badge_count = $info['pending'];
                  $badge_color = 'bg-warning';
              } elseif ($status_filter === 'rejected' && $info && $info['rejected'] > 0) {
                  $cls .= ' has-pending';
                  $show_badge = true;
                  $badge_count = $info['rejected'];
                  $badge_color = 'bg-danger';
              } elseif ($status_filter === 'all' && $info && ($info['pending'] > 0 || $info['rejected'] > 0)) {
                  $cls .= ' has-pending';
                  $show_badge = true;
                  $badge_count = $info['pending'] + $info['rejected'];
                  $badge_color = 'bg-secondary';
              }
              
              if($ds === $today) $cls .= ' today';
              if($ds === $sel_date) $cls .= ' selected';
              $count_tip = $info ? "title=\"{$info['total']} รายการ\"" : '';
              $badge = '';
              if($show_badge){
                  $badge = "<span class=\"mc-day-badge $badge_color\">" . number_format($badge_count) . "</span>";
              }
              echo "<div class=\"$cls\" data-cal-date=\"$ds\" $count_tip onclick=\"goDate('$ds')\">$d$badge</div>";
          }
          ?>
        </div>
        <div class="mt-4">
          <button class="btn w-100 rounded-3 fw-bold py-2 <?= ($sel_date === 'all') ? 'text-white' : 'text-primary' ?>" 
                  style="<?= ($sel_date === 'all') ? 'background: #4361ee; box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3); border:none;' : 'background: rgba(67, 97, 238, 0.08); border: 1px solid rgba(67, 97, 238, 0.2);' ?>" 
                  onclick="goDate('all')">
            <i class="bi bi-calendar-range me-2"></i> ดูรายการทั้งหมด (ทุกวัน)
          </button>
        </div>
        <div class="mc-pending-info flex-column align-items-start gap-1">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-warning"></i>
            รอตรวจ <span id="calendarPendingCount"><?=number_format($pending_total)?></span> รายการ
          </div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            รอนักเรียนส่งใหม่ <span id="calendarRejectedCount"><?=number_format($rejected_total)?></span> รายการ
          </div>
        </div>
      </div>
    </div>

    <!-- ══ RIGHT: Report List ══ -->
    <div>
      <!-- 🌟 Navigation Status Tabs 🌟 -->
      <div class="px-3 px-md-0 mb-3">
        <ul class="nav nav-pills gap-2 mb-0 bg-white p-2 rounded-3 shadow-sm border border-light" style="display: inline-flex;">
          <li class="nav-item">
            <a class="nav-link rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2 <?= $status_filter === 'pending' ? 'active bg-primary text-white' : 'text-primary bg-primary bg-opacity-10' ?>" href="approve_reports.php?status_filter=pending&date=<?= $sel_date ?>" style="font-size: 0.88rem;">
              <i class="bi bi-clock-history"></i>รอตรวจ 
              <span class="badge <?= $status_filter === 'pending' ? 'bg-white text-primary' : 'bg-primary text-white' ?>"><?= $pending_total ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2 <?= $status_filter === 'rejected' ? 'active bg-danger text-white' : 'text-danger bg-danger bg-opacity-10' ?>" href="approve_reports.php?status_filter=rejected&date=<?= $sel_date ?>" style="font-size: 0.88rem;">
              <i class="bi bi-exclamation-triangle-fill"></i>รอนักเรียนส่งใหม่ 
              <span class="badge <?= $status_filter === 'rejected' ? 'bg-white text-danger' : 'bg-danger text-white' ?>"><?= $rejected_total ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2 <?= $status_filter === 'all' ? 'active bg-secondary text-white' : 'text-secondary bg-secondary bg-opacity-10' ?>" href="approve_reports.php?status_filter=all&date=<?= $sel_date ?>" style="font-size: 0.88rem;">
              <i class="bi bi-list-task"></i>ทั้งหมด 
              <span class="badge <?= $status_filter === 'all' ? 'bg-white text-secondary' : 'bg-secondary text-white' ?>"><?= $pending_total + $rejected_total ?></span>
            </a>
          </li>
        </ul>
      </div>

      <div class="rp-header d-flex align-items-center justify-content-between flex-wrap gap-2 mt-md-0 mt-3 px-3 px-md-0">
        <div class="d-none d-md-block">
          <i class="bi bi-journal-text text-primary"></i>
          รายละเอียดการปฏิบัติงาน (<?= $status_filter === 'pending' ? 'รอตรวจ' : ($status_filter === 'rejected' ? 'รอนักเรียนส่งใหม่' : 'ทั้งหมด') ?>) - <span class="rp-date"><?= ($sel_date === 'all') ? 'ทั้งหมด' : thDate($sel_date) ?></span>
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

      <?php if(empty($reports)): ?>
        <div class="premium-card">
          <div class="empty-state">
            <i class="bi bi-clipboard-x"></i>
            ไม่มีบันทึกในวันที่เลือก
          </div>
        </div>
      <?php else: ?>
        <!-- Search Box Row -->
        <div class="search-box-wrapper mb-3">
          <div class="input-group search-input-group">
            <span class="input-group-text bg-white border-end-0">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" id="reportSearchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหาตามชื่อ, รหัส หรือสถานประกอบการ..." onkeyup="filterReports()">
            <button class="btn btn-outline-secondary border-start-0 clear-search-btn bg-white" type="button" id="clearSearchBtn" onclick="clearSearch()" style="display: none; border-color: #dee2e6;">
              <i class="bi bi-x-circle-fill text-muted"></i>
            </button>
          </div>
        </div>
        <?php $seq = 1; foreach($reports as $r):
          $name    = htmlspecialchars($r['fullname']);
          $code    = htmlspecialchars($r['student_code'] ?? '');
          $cls_nm  = htmlspecialchars($r['class_name'] ?? '');
          $comp    = htmlspecialchars($r['company_name'] ?? '');
          $detail  = htmlspecialchars($r['details'] ?? '');
          $problems= htmlspecialchars($r['problems'] ?? '');
          $solutions=htmlspecialchars($r['solutions'] ?? '');
          $tc_cmt  = htmlspecialchars($r['teacher_comment'] ?? '');
          $status  = $r['status'];
          $rid     = (int)$r['id'];
          $back_url= urlencode('approve_reports.php?date='.$sel_date);
        ?>
        <div class="rc status-<?=$status?>" data-rid="<?=$rid?>" data-date="<?=$r['date_work']?>">
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
              <?php if ($status === 'pending' && !empty($tc_cmt)): ?>
                <span class="badge bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 fw-bold" style="font-size: 0.72rem; align-self: flex-start; padding: 6px 10px; border-radius: 8px;">
                  <i class="bi bi-arrow-repeat me-1"></i> นักเรียนแก้ไขส่งใหม่
                </span>
              <?php endif; ?>
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
              <?php if ($status === 'pending' && !empty($tc_cmt)): ?>
                <span class="badge bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 fw-bold mb-2 w-100" style="font-size: 0.72rem; padding: 6px 10px; border-radius: 8px;">
                  <i class="bi bi-arrow-repeat me-1"></i> นักเรียนแก้ไขส่งใหม่
                </span>
              <?php endif; ?>
              <?php if($sel_date === 'all'): ?>
                <span class="rc-badge-date"><i class="bi bi-calendar3 me-1"></i><?=thDate($r['date_work'])?></span>
              <?php endif; ?>
              <?php if($comp): ?><span class="rc-badge-company"><i class="bi bi-briefcase-fill me-1"></i><?=$comp?></span><?php endif; ?>
            </div>
            <?php if($detail): ?>
              <div class="rc-detail-text"><?=nl2br($detail)?></div>
            <?php endif; ?>
            <?php if($problems): ?>
              <div class="mt-2 text-warning" style="font-size:.85rem;"><strong>ปัญหา:</strong> <?=nl2br($problems)?></div>
            <?php endif; ?>
            <?php if($solutions): ?>
              <div class="mt-1 text-success" style="font-size:.85rem;"><strong>แก้ไข:</strong> <?=nl2br($solutions)?></div>
            <?php endif; ?>
            <?php
            $imgs = [];
            if(!empty($r['image1'])) $imgs[] = $r['image1'];
            if(!empty($r['image2'])) $imgs[] = $r['image2'];
            if($imgs): ?>
              <div class="d-flex gap-2 mt-3">
                <?php foreach($imgs as $img): ?>
                  <img src="../uploads/images/<?=htmlspecialchars($img)?>" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;cursor:pointer;" onclick="viewImg(this.src)" alt="ภาพ">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            
            <!-- Comment Section with Wrapper for AJAX -->
            <div class="rc-comment-area mt-3 px-3 py-2 rounded-3" style="background:#f0f4ff;border-left:3px solid #4361ee;font-size:.85rem; <?= ($tc_cmt) ? '' : 'display:none;' ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div class="rc-comment-text">
                  <i class="bi bi-chat-square-text me-1 text-primary"></i>
                  <?php if($status === 'pending'): ?>
                    <strong class="text-purple"><i class="bi bi-info-circle-fill me-1"></i> ความเห็นเดิมที่ให้แก้ไข:</strong><br>
                  <?php endif; ?>
                  <span><?=nl2br($tc_cmt)?></span>
                </div>
                <button type="button" class="btn btn-sm btn-link text-primary p-0 ms-2 rc-comment-edit-btn" 
                        style="<?= ($status === 'rejected') ? '' : 'display:none;' ?>"
                        onclick="openEditComment(this, <?=$rid?>, '<?=addslashes($name)?>')" 
                        data-comment="<?=htmlspecialchars($r['teacher_comment'] ?? '')?>" 
                        title="แก้ไขคอมเมนท์">
                  <i class="bi bi-pencil-square"></i>
                </button>
              </div>
            </div>
          </div>

          <?php if($status === 'pending'): ?>
            <div class="rc-actions">
              <form method="POST" style="display:contents;">
                <input type="hidden" name="act" value="approve">
                <input type="hidden" name="rid" value="<?=$rid?>">
                <input type="hidden" name="back" value="approve_reports.php?status_filter=<?=$status_filter?>&date=<?=$sel_date?>">
                <button type="submit" class="act-btn approve">
                  <i class="bi bi-check-circle-fill"></i><span>ผ่าน</span>
                </button>
              </form>
              <button type="button" class="act-btn reject" onclick="openReject(<?=$rid?>, '<?=addslashes($name)?>')">
                <i class="bi bi-x-circle"></i><span>ไม่ผ่าน</span>
              </button>
            </div>
          <?php else: ?>
            <div class="rc-actions">
              <?php if($status === 'approved'): ?>
                <span class="status-done-badge sdb-approved"><i class="bi bi-check-circle-fill"></i>ผ่านแล้ว</span>
              <?php else: ?>
                <span class="status-done-badge sdb-rejected"><i class="bi bi-exclamation-triangle-fill"></i>รอนักเรียนส่งใหม่</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>



<!-- Reject Modal -->
<div class="mo-backdrop" id="rejectModal">
  <div class="mo-box">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0"><i class="bi bi-chat-left-text me-2 text-danger"></i>ส่งกลับให้แก้ไข</h5>
      <button onclick="document.getElementById('rejectModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>
    <p class="text-muted small mb-3">นักเรียน: <strong id="rj-name"></strong></p>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="act" value="reject">
      <input type="hidden" name="rid" id="rj-rid" value="">
      <input type="hidden" name="back" value="approve_reports.php?status_filter=<?=$status_filter?>&date=<?=$sel_date?>">
      <textarea name="comment" id="rj-comment" class="form-control mb-3" rows="4" placeholder="ระบุเหตุผล เช่น ข้อมูลไม่ครบถ้วน..." required></textarea>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light fw-bold" onclick="document.getElementById('rejectModal').classList.remove('show')">ยกเลิก</button>
        <button type="submit" class="btn btn-danger fw-bold px-4"><i class="bi bi-send me-1"></i>ยืนยัน</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Comment Modal -->
<div class="mo-backdrop" id="editCommentModal">
  <div class="mo-box">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>แก้ไขคอมเมนท์</h5>
      <button onclick="document.getElementById('editCommentModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>
    <p class="text-muted small mb-3">นักเรียน: <strong id="ec-name"></strong></p>
    <form method="POST" id="editCommentForm">
      <input type="hidden" name="act" value="edit_comment">
      <input type="hidden" name="rid" id="ec-rid" value="">
      <input type="hidden" name="back" value="approve_reports.php?status_filter=<?=$status_filter?>&date=<?=$sel_date?>">
      <textarea name="comment" id="ec-comment" class="form-control mb-3" rows="4" placeholder="ระบุเหตุผล เช่น ข้อมูลไม่ครบถ้วน..." required></textarea>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light fw-bold" onclick="document.getElementById('editCommentModal').classList.remove('show')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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
    const nameEl = card.querySelector('.rc-name');
    const codeEl = card.querySelector('.rc-code');
    const compEl = card.querySelector('.rc-badge-company');
    const detailEl = card.querySelector('.rc-detail-text');
    
    const nameText = nameEl ? nameEl.textContent.toLowerCase() : '';
    const codeText = codeEl ? codeEl.textContent.toLowerCase() : '';
    const compText = compEl ? compEl.textContent.toLowerCase() : '';
    const detailText = detailEl ? detailEl.textContent.toLowerCase() : '';
    
    if (nameText.includes(query) || codeText.includes(query) || compText.includes(query) || detailText.includes(query)) {
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
      emptyState.innerHTML = `
        <div class="empty-state">
          <i class="bi bi-search-heart text-muted" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mt-3 text-dark">ไม่พบข้อมูลที่ค้นหา</h5>
          <p class="text-muted small">ลองใช้คำค้นหาอื่น เช่น ชื่อนักเรียน หรือรหัสนักเรียน</p>
        </div>
      `;
      const wrapper = document.querySelector('.search-box-wrapper');
      wrapper.parentNode.appendChild(emptyState);
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

function goDate(d){ window.location.href = 'approve_reports.php?status_filter=<?=$status_filter?>&date=' + d; }
function goMonth(y,m){
  var parts = '<?=$sel_date?>'.split('-');
  var day = parts[2];
  var nd = y + '-' + String(m).padStart(2,'0') + '-01';
  window.location.href = 'approve_reports.php?status_filter=<?=$status_filter?>&date=' + nd;
}
function openReject(id, name){
  document.getElementById('rj-rid').value = id;
  document.getElementById('rj-name').textContent = name;
  document.getElementById('rj-comment').value = '';
  document.getElementById('rejectModal').classList.add('show');
}
function openEditComment(btn, id, name){
  const comment = btn.getAttribute('data-comment') || '';
  document.getElementById('ec-rid').value = id;
  document.getElementById('ec-name').textContent = name;
  document.getElementById('ec-comment').value = comment;
  document.getElementById('editCommentModal').classList.add('show');
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
  Swal.fire({imageUrl:src,showCloseButton:true,showConfirmButton:false,background:'#fff',backdrop:'rgba(0,0,0,.8)',customClass:{image:'rounded-3 shadow-lg',popup:'p-2'}});
}
document.getElementById('rejectModal').addEventListener('click', function(e){
  if(e.target === this) this.classList.remove('show');
});
document.getElementById('editCommentModal').addEventListener('click', function(e){
  if(e.target === this) this.classList.remove('show');
});

// Auto-center selected calendar item on mobile
document.addEventListener("DOMContentLoaded", function() {
  const selectedItem = document.querySelector(".m-day-item.selected");
  if (selectedItem) {
    selectedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  }
});

// --- AJAX Form Submission Interceptor ---
document.addEventListener('submit', function(e) {
  const form = e.target;
  const actInput = form.querySelector('input[name="act"]');
  
  // Intercept report actions POST forms only
  if (form.method.toLowerCase() === 'post' && actInput) {
    e.preventDefault();
    
    const formData = new FormData(form);
    formData.append('ajax', '1');
    
    const submitBtn = form.querySelector('[type="submit"]') || form.closest('.mo-box')?.querySelector('[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    
    fetch(form.action || window.location.href, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (submitBtn) submitBtn.disabled = false;
      if (data.success) {
        handleAjaxSuccess(data, formData);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'เกิดข้อผิดพลาด',
          text: data.message || 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่อีกครั้ง',
          confirmButtonText: 'ตกลง'
        });
      }
    })
    .catch(err => {
      if (submitBtn) submitBtn.disabled = false;
      console.error('Fetch error:', err);
      Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
        text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ในขณะนี้',
        confirmButtonText: 'ตกลง'
      });
    });
  }
});

function escapeHtml(string) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(string).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function handleAjaxSuccess(data, formData) {
  const rid = data.rid;
  const action = data.action;
  const card = document.querySelector(`.rc[data-rid="${rid}"]`);
  
  if (!card) {
    window.location.reload();
    return;
  }
  
  // Close modals
  document.getElementById('rejectModal').classList.remove('show');
  document.getElementById('editCommentModal').classList.remove('show');
  
  const comment = formData.get('comment') || '';
  const wasPending = card.classList.contains('status-pending');
  const date = card.getAttribute('data-date');
  const studentName = card.querySelector('.rc-name')?.textContent || 'นักเรียน';
  
  if (action === 'approve') {
    card.className = card.className.replace(/\bstatus-\S+/g, '') + ' status-approved';
    
    const actionsContainer = card.querySelector('.rc-actions');
    if (actionsContainer) {
      actionsContainer.innerHTML = `
        <span class="status-done-badge sdb-approved">
          <i class="bi bi-check-circle-fill"></i>ผ่านแล้ว
        </span>
      `;
    }
    
    const commentArea = card.querySelector('.rc-comment-area');
    if (commentArea) {
      commentArea.style.display = 'none';
    }
    
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'success',
      title: 'อนุมัติรายงานเรียบร้อยแล้ว'
    });
    
    if (wasPending) {
      updatePendingBadge(date);
    }
    
  } else if (action === 'reject') {
    card.className = card.className.replace(/\bstatus-\S+/g, '') + ' status-rejected';
    
    const actionsContainer = card.querySelector('.rc-actions');
    if (actionsContainer) {
      actionsContainer.innerHTML = `
        <span class="status-done-badge sdb-rejected">
          <i class="bi bi-exclamation-triangle-fill"></i>รอนักเรียนส่งใหม่
        </span>
      `;
    }
    
    const commentArea = card.querySelector('.rc-comment-area');
    if (commentArea) {
      commentArea.style.display = 'block';
      const textSpan = commentArea.querySelector('.rc-comment-text span');
      if (textSpan) {
        textSpan.innerHTML = escapeHtml(comment).replace(/\n/g, '<br>');
      }
      
      const editBtn = commentArea.querySelector('.rc-comment-edit-btn');
      if (editBtn) {
        editBtn.style.display = 'inline-block';
        editBtn.setAttribute('data-comment', comment);
      }
    }
    
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'warning',
      title: 'ส่งคืนรายงานให้แก้ไขเรียบร้อยแล้ว'
    });
    
    if (wasPending) {
      updatePendingBadge(date);
      updateRejectedBadge(date);
    }
    
  } else if (action === 'edit_comment') {
    const commentArea = card.querySelector('.rc-comment-area');
    if (commentArea) {
      const textSpan = commentArea.querySelector('.rc-comment-text span');
      if (textSpan) {
        textSpan.innerHTML = escapeHtml(comment).replace(/\n/g, '<br>');
      }
      
      const editBtn = commentArea.querySelector('.rc-comment-edit-btn');
      if (editBtn) {
        editBtn.setAttribute('data-comment', comment);
      }
    }
    
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'success',
      title: 'แก้ไขคอมเมนท์เรียบร้อยแล้ว'
    });
  }
}

function updatePendingBadge(date) {
  // 1. Decrement header badge
  const headerBadge = document.getElementById('headerPendingBadge');
  if (headerBadge) {
    let val = parseInt(headerBadge.textContent) || 0;
    if (val > 0) {
      val--;
      headerBadge.textContent = val + ' รอตรวจ';
      if (val === 0) {
        headerBadge.style.display = 'none';
      }
    }
  }
  
  // 2. Decrement tab badge
  const pendingTabBadge = document.querySelector('.nav-link[href*="status_filter=pending"] .badge');
  if (pendingTabBadge) {
    let val = parseInt(pendingTabBadge.textContent) || 0;
    if (val > 0) pendingTabBadge.textContent = val - 1;
  }
  
  // 3. Decrement calendar total count
  const calCount = document.getElementById('calendarPendingCount');
  if (calCount) {
    let val = parseInt(calCount.textContent) || 0;
    if (val > 0) {
      val--;
      calCount.textContent = val;
    }
  }
  
  // 4. Decrement individual calendar day pending count badge
  if (date) {
    const dayEl = document.querySelector(`.mc-day[data-cal-date="${date}"]`);
    if (dayEl) {
      const dayBadge = dayEl.querySelector('.mc-day-badge');
      if (dayBadge) {
        let val = parseInt(dayBadge.textContent) || 0;
        if (val > 0) {
          val--;
          dayBadge.textContent = val;
          if (val === 0) {
            dayBadge.remove();
            dayEl.classList.remove('has-pending');
          }
        }
      }
    }
  }
}

function updateRejectedBadge(date) {
  // 1. Increment header badge
  const headerBadge = document.getElementById('headerRejectedBadge');
  if (headerBadge) {
    let val = parseInt(headerBadge.textContent) || 0;
    val++;
    headerBadge.textContent = val + ' รอนักเรียนส่งใหม่';
    headerBadge.style.display = '';
  }
  
  // 2. Increment tab badge
  const rejectedTabBadge = document.querySelector('.nav-link[href*="status_filter=rejected"] .badge');
  if (rejectedTabBadge) {
    let val = parseInt(rejectedTabBadge.textContent) || 0;
    rejectedTabBadge.textContent = val + 1;
  }
  
  // 3. Increment calendar total count
  const calCount = document.getElementById('calendarRejectedCount');
  if (calCount) {
    let val = parseInt(calCount.textContent) || 0;
    val++;
    calCount.textContent = val;
  }
}

// --- ระบบบันทึกและคืนค่าตำแหน่งการเลื่อนหน้าจอ (Scroll Position Restoration) ---
// บันทึกตำแหน่งการเลื่อนเมื่อส่งฟอร์ม (กรณี Non-AJAX fallback)
window.addEventListener('submit', function(e) {
  if (!e.defaultPrevented) {
    sessionStorage.setItem('approve_reports_scroll_pos', window.scrollY);
  }
});

// คืนค่าตำแหน่งเมื่อหน้าเว็บโหลดเสร็จ
(function() {
  const savedScroll = sessionStorage.getItem('approve_reports_scroll_pos');
  if (savedScroll !== null) {
    window.scrollTo(0, parseInt(savedScroll));
    document.addEventListener("DOMContentLoaded", function() {
      window.scrollTo(0, parseInt(savedScroll));
      setTimeout(function() {
        window.scrollTo(0, parseInt(savedScroll));
        sessionStorage.removeItem('approve_reports_scroll_pos');
      }, 50);
    });
  }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>