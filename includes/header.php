<?php require_once __DIR__ . '/functions.php'; ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="manifest" href="/DVE_DATA_FULL/manifest.json">
<link rel="icon" href="/DVE_DATA_FULL/images/logo.png" type="image/png">
<title><?= isset($page_title) ? $page_title . ' | DVE PBPVC' : 'DVE | PBPVC' ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="/DVE_DATA_FULL/assets/js/scroll-to-top.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/DVE_DATA_FULL/assets/css/student-premium.css">
<link rel="stylesheet" href="/DVE_DATA_FULL/assets/css/animations.css">
<style>
  :root {
    --nav-height: 70px;
  }
  
  body {
    font-family: 'Sarabun', sans-serif;
    background: #f8fafc;
    color: #1e293b;
    padding-top: var(--nav-height);
  }

  .premium-nav {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    height: var(--nav-height);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    transition: all 0.3s ease;
  }

  .premium-nav .container {
    flex-wrap: nowrap !important;
  }

  .navbar-brand-modern {
    font-weight: 800;
    font-size: 1.3rem;
    color: var(--primary-dark) !important;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    letter-spacing: -0.5px;
  }

  .nav-user-pill {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
  }

  .nav-user-pill:hover {
    background: rgba(99, 102, 241, 0.05);
    border-color: rgba(99, 102, 241, 0.25);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05);
  }

  /* ปรับ Container หลักของเนื้อหา */
  .main-content-container {
    margin-top: 20px;
    padding: 0;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
  }

  /* ซ่อนข้อความต้อนรับแบบเก่าในหน้าที่มีการจัดการเอง */
  .hide-welcome .welcome-banner { display: none; }
  
  .welcome-banner {
    background: white;
    border-radius: 2rem;
    padding: 3rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.05);
  }

  /* Glassmorphism Toast Notifications */
  .glass-toast {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border-radius: 16px;
    padding: 16px;
    width: 320px;
    display: flex;
    align-items: center;
    gap: 12px;
    pointer-events: auto;
    cursor: pointer;
    transform: translateX(120%);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }

  .glass-toast.show {
    transform: translateX(0);
    opacity: 1;
  }

  .glass-toast:hover {
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
  }

  /* Responsive Navbar Adjustments */
  @media (max-width: 768px) {
    :root {
      --nav-height: 60px !important;
    }
    body {
      padding-top: 60px !important;
    }
    .premium-nav {
      height: 60px !important;
    }
    .navbar-brand-modern span {
      font-size: 1.1rem;
    }
    .navbar-brand-modern img {
      height: 32px !important;
    }
  }

  /* Compact styling on small mobile devices */
  @media (max-width: 575.98px) {
    .premium-nav .container {
      padding-left: 12px !important;
      padding-right: 12px !important;
    }
    .premium-nav .ms-auto {
      gap: 0.5rem !important; /* reduce gap between notification & profile icons */
    }
    .navbar-brand-modern {
      gap: 0.4rem !important;
    }
    .premium-nav .btn-sm {
      padding: 4px 10px !important;
      font-size: 0.78rem !important;
      border-radius: 6px !important;
    }
    /* Prevent notification and user dropdowns from overflowing right side on 320px screens */
    .dropdown-menu-end {
      right: 0 !important;
      left: auto !important;
      max-width: calc(100vw - 24px) !important;
    }
  }


  .dropdown-item.active, .dropdown-item:active {
    background-color: rgba(99, 102, 241, 0.1) !important;
    color: #4361ee !important;
  }
  .dropdown-item:hover {
    background-color: rgba(99, 102, 241, 0.05) !important;
    color: #4361ee !important;
  }
</style>
</head>
<body class="<?= isset($page_class) ? $page_class : '' ?>">
<nav class="navbar navbar-expand-lg premium-nav">
  <div class="container">
    <a class="navbar-brand-modern" href="/DVE_DATA_FULL/index.php">
        <img src="/DVE_DATA_FULL/images/logo.png" alt="PBPVC Logo" style="height: 40px; width: auto;">
        <span class="d-none d-sm-inline-block">DVE | PBPVC</span>
        <span class="d-inline-block d-sm-none">DVE</span>
    </a>

    <div class="d-none d-lg-flex align-items-center gap-3 ms-4 px-3 py-2 border-start border-opacity-10 border-secondary" style="font-size: 0.85rem;">
        <div class="d-flex align-items-center gap-2 text-secondary">
            <i class="bi bi-calendar3 text-primary"></i>
            <span id="thai-nav-date" class="fw-medium"></span>
        </div>
        <div class="vr opacity-25"></div>
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-fill text-primary"></i>
            <span id="thai-nav-time" class="fw-bold font-monospace text-dark" style="font-size: 0.9rem; min-width: 75px;"></span>
        </div>
        <div class="vr opacity-25"></div>
        <div class="d-flex align-items-center gap-2">
            <a href="/DVE_DATA_FULL/news_list.php" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold d-flex align-items-center gap-1">
                <i class="bi bi-newspaper"></i> ข่าวประชาสัมพันธ์
            </a>
            <a href="/DVE_DATA_FULL/contact.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold d-flex align-items-center gap-1">
                <i class="bi bi-chat-dots-fill"></i> ติดต่อเรา
            </a>
        </div>
    </div>
    
    <div class="ms-auto d-flex align-items-center gap-3">
        <?php if(is_logged_in()): $u = current_user(); 
          $profile_url = '#';
          $valid_roles = ['student', 'teacher', 'director', 'staff', 'admin', 'supervisor'];
          if (in_array($u['role'], $valid_roles)) {
              $profile_url = '/DVE_DATA_FULL/' . $u['role'] . '/profile.php';
          }

          
          $notif_user_id = (int)$u['id'];
          $header_avatar_file = !empty($u['profile_image']) ? __DIR__ . '/../uploads/avatars/' . $u['profile_image'] : '';
          $has_header_avatar = !empty($u['profile_image']) && file_exists($header_avatar_file);
          // Auto-create/validate notifications table (Cached in session for performance)
          if (empty($_SESSION['notif_table_checked'])) {
              $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  user_id INT NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  message TEXT NOT NULL,
                  is_read TINYINT(1) DEFAULT 0,
                  type VARCHAR(50) DEFAULT 'info',
                  action_url VARCHAR(255) DEFAULT NULL,
                  expires_at DATETIME DEFAULT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
              )");
              
              // Check and alter table to add columns if they don't exist
              $check_type = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
              if ($check_type && $check_type->num_rows == 0) {
                  $conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) DEFAULT 'info'");
              }
              $check_url = $conn->query("SHOW COLUMNS FROM notifications LIKE 'action_url'");
              if ($check_url && $check_url->num_rows == 0) {
                  $conn->query("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL");
              }
              $check_expires = $conn->query("SHOW COLUMNS FROM notifications LIKE 'expires_at'");
              if ($check_expires && $check_expires->num_rows == 0) {
                  $conn->query("ALTER TABLE notifications ADD COLUMN expires_at DATETIME DEFAULT NULL");
              }
              $_SESSION['notif_table_checked'] = true;
          }
          
          // Auto-seed some beautiful notifications if empty to showcase the feature perfectly (Disabled to prevent immortal dummy notifications)
          /*
          $check_cnt = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $notif_user_id");
          if ($check_cnt) {
              $cnt_row = $check_cnt->fetch_assoc();
              if ((int)$cnt_row['cnt'] === 0) {
                  $conn->query("INSERT INTO notifications (user_id, title, message, is_read, type) VALUES 
                      ($notif_user_id, 'ยินดีต้อนรับสู่ระบบ DVE', 'ระบบติดตามแผนการเรียนรู้แบบทวิภาคี ยินดีต้อนรับคุณเข้าสู่แดชบอร์ด!', 0, 'info'),
                      ($notif_user_id, 'อัปเดตตารางสอนออนไลน์', 'อาจารย์นิเทศก์ได้ปรับปรุงแผนการพบนิเทศประจำสัปดาห์แล้ว กรุณาเข้าตรวจสอบ', 0, 'schedule_update')
                  ");
              }
          }
          */
          
          // Get unread count
          $unread_count = 0;
          $count_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $notif_user_id AND is_read = 0");
          if ($count_res) {
              $count_row = $count_res->fetch_assoc();
              $unread_count = (int)$count_row['cnt'];
          }
        ?>
          <style>
          .text-purple { color: #6f42c1 !important; }
          .bg-purple { background-color: #6f42c1 !important; }
          .bg-purple.bg-opacity-10 { background-color: rgba(111, 66, 193, 0.1) !important; }

          /* Premium Glassmorphic Toast Notification Style */
          .glass-toast {
              background: rgba(255, 255, 255, 0.85);
              backdrop-filter: blur(16px);
              -webkit-backdrop-filter: blur(16px);
              border: 1px solid rgba(255, 255, 255, 0.4);
              box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
              border-radius: 1rem;
              padding: 1rem;
              width: 320px;
              display: flex;
              align-items: start;
              gap: 12px;
              pointer-events: auto;
              cursor: pointer;
              transform: translateX(120%);
              transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
          }
          .glass-toast.show {
              transform: translateX(0);
          }
          .glass-toast:hover {
              background: rgba(255, 255, 255, 0.95);
              transform: translateY(-2px);
              box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.2);
          }
          
          .dropdown-item {
              position: relative !important;
          }
          .dropdown-item .mark-read-dropdown-btn {
              position: absolute;
              top: 0.75rem;
              right: 0.75rem;
              background: rgba(255, 255, 255, 0.95);
              border: 1px solid rgba(0, 0, 0, 0.08);
              color: #94a3b8;
              font-size: 0.72rem;
              cursor: pointer;
              padding: 2px 6px;
              border-radius: 6px;
              transition: all 0.2s ease;
              display: none;
              z-index: 5;
          }
          .dropdown-item:hover .mark-read-dropdown-btn {
              display: block;
          }
          .dropdown-item .mark-read-dropdown-btn:hover {
              background: #f1f5f9;
              color: #6366f1;
              border-color: rgba(99, 102, 241, 0.2);
          }

          /* General Styles & connected arrow for notification dropdown */
          #notificationDropdown + .dropdown-menu {
              overflow: visible !important;
              transform: none !important; /* Disable Popper positioning to allow exact CSS alignment */
              top: 100% !important;
              right: 0 !important;
              left: auto !important;
          }
          #notificationDropdown + .dropdown-menu::before {
              content: "";
              position: absolute;
              top: -6px;
              right: 13px; /* Centered with the 38px bell icon button */
              width: 12px;
              height: 12px;
              background: rgba(255, 255, 255, 0.95);
              backdrop-filter: blur(15px);
              -webkit-backdrop-filter: blur(15px);
              transform: rotate(45deg);
              border-left: 1px solid rgba(0, 0, 0, 0.05);
              border-top: 1px solid rgba(0, 0, 0, 0.05);
              z-index: 10;
              transition: all 0.3s ease;
          }

          /* Ensure Thai text wrapping and prevent overlap with checkmark button */
          .notification-list .dropdown-item {
              white-space: normal !important;
          }
          .notification-list .dropdown-item p,
          .notification-list .dropdown-item span {
              word-break: break-word !important;
              overflow-wrap: break-word !important;
              white-space: normal !important;
          }
          .notification-list .dropdown-item .flex-grow-1 {
              padding-right: 20px !important; /* Prevent text from colliding with absolute-positioned checkmark button */
          }

          /* Responsive adjustments for Mobile/Tablet */
          @media (max-width: 575.98px) {
              /* 1. Toast Notification Container & Toasts */
              #toastNotificationContainer {
                  bottom: 16px !important;
                  right: 16px !important;
                  left: 16px !important;
                  width: auto !important;
                  max-width: 100% !important;
              }
              .glass-toast {
                  width: 100% !important;
                  max-width: 100% !important;
                  transform: translateY(40px) !important;
                  opacity: 0;
                  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.2) !important;
              }
              .glass-toast.show {
                  transform: translateY(0) !important;
                  opacity: 1;
              }
              .glass-toast:hover {
                  transform: translateY(-2px) !important;
              }

              /* 2. Notification Bell Dropdown Menu */
              .ms-auto > .dropdown.me-1 {
                  position: static !important;
              }
              #notificationDropdown + .dropdown-menu {
                  width: auto !important;
                  max-width: none !important;
                  left: 0 !important;
                  right: 0 !important;
                  margin-top: 14px !important; /* Give some breathing room from navbar */
              }
              #notificationDropdown + .dropdown-menu::before {
                  right: 115px !important; /* Perfect alignment with bell icon based on mobile header flex layout */
              }
              
              /* Improve notification items on mobile */
              .notification-list .dropdown-item {
                  padding: 12px 16px !important;
              }
              .notification-list .dropdown-item .mark-read-dropdown-btn {
                  display: block !important; /* Always show mark-as-read button on touch devices since hover is not reliable */
                  opacity: 0.8;
              }
          }
          </style>

          <!-- Notification Bell Dropdown -->
          <div class="dropdown me-1">
              <button class="btn btn-link position-relative p-0 border-0 rounded-circle bg-light d-flex align-items-center justify-content-center" id="notificationDropdown" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="width: 38px; height: 38px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); outline: none !important; box-shadow: none !important;">
                  <i class="bi bi-bell text-secondary fs-5" id="notificationBellIcon"></i>
                  <?php if ($unread_count > 0): ?>
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCountBadge" style="font-size: 0.65rem; padding: 0.25em 0.5em; z-index: 10; margin-top: 4px; margin-left: -4px;">
                          <?= $unread_count ?>
                      </span>
                  <?php endif; ?>
              </button>
              
              <!-- Dropdown Menu -->
              <div class="dropdown-menu dropdown-menu-end p-0 border-0 shadow-lg mt-3 animate-fade-in" aria-labelledby="notificationDropdown" style="width: 320px; border-radius: 1.25rem; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(0,0,0,0.05); overflow: hidden;">
                  <div class="p-3 d-flex align-items-center justify-content-between border-bottom bg-transparent" style="border-bottom-color: rgba(0,0,0,0.05) !important;">
                      <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                          <i class="bi bi-bell-fill text-primary"></i>การแจ้งเตือน
                      </h6>
                      <?php if ($unread_count > 0): ?>
                          <a href="<?= BASE_URL ?>/includes/mark_all_read.php" class="text-primary small text-decoration-none fw-bold" id="markAllReadLink">
                              <i class="bi bi-check-all me-1"></i>ทำเครื่องหมายว่าอ่านแล้วทั้งหมด
                          </a>
                      <?php endif; ?>
                  </div>
                  
                  <div class="notification-list" id="dropdownNotificationList" style="max-height: 280px; overflow-y: auto;">
                      <?php
                      // ── Notification Type → Icon / Color Map (ครบถ้วน) ──
                      $notif_type_map = [
                          'info'                 => ['bi-info-circle-fill',       'text-primary bg-primary bg-opacity-10',   'rgba(99,102,241,0.04)'],
                          'report_submitted'     => ['bi-send-fill',               'text-indigo bg-primary bg-opacity-10',    'rgba(99,102,241,0.05)'],
                          'report_approved'      => ['bi-check-circle-fill',       'text-success bg-success bg-opacity-10',   'rgba(16,185,129,0.04)'],
                          'report_rejected'      => ['bi-exclamation-octagon-fill','text-danger bg-danger bg-opacity-10',     'rgba(220,53,69,0.04)'],
                          'report_late_reminder' => ['bi-clock-fill',              'text-warning bg-warning bg-opacity-10',   'rgba(245,158,11,0.04)'],
                          'review_late'          => ['bi-hourglass-split',         'text-dark bg-secondary bg-opacity-10',    'rgba(100,116,139,0.04)'],
                          'schedule_update'      => ['bi-calendar2-week-fill',     'text-purple bg-purple bg-opacity-10',     'rgba(111,66,193,0.04)'],
                          'evaluation'           => ['bi-trophy-fill',             'text-warning bg-warning bg-opacity-10',   'rgba(255,193,7,0.04)'],
                          'announcement'         => ['bi-megaphone-fill',          'text-info bg-info bg-opacity-10',         'rgba(13,202,240,0.04)'],
                          'welcome'              => ['bi-stars',                   'text-primary bg-primary bg-opacity-10',   'rgba(99,102,241,0.04)'],
                      ];

                      $header_notifs_res = $conn->query("SELECT * FROM notifications WHERE user_id = $notif_user_id ORDER BY created_at DESC LIMIT 5");
                      if ($header_notifs_res && $header_notifs_res->num_rows > 0) {
                          while ($hn = $header_notifs_res->fetch_assoc()) {
                              $type = !empty($hn['type']) ? $hn['type'] : 'info';
                              $tm = $notif_type_map[$type] ?? $notif_type_map['info'];
                              $icon        = $tm[0];
                              $color_class = $tm[1];
                              $bg_unread   = $hn['is_read'] == 0 ? 'background-color: ' . $tm[2] . ';' : '';
                              $dot_unread  = $hn['is_read'] == 0 ? '<span class="d-inline-block bg-primary rounded-circle" style="width:6px;height:6px;min-width:6px;"></span>' : '';

                              // Relative time
                              $diff_sec = time() - strtotime($hn['created_at']);
                              if ($diff_sec < 60)       $time_str = 'เมื่อกี้';
                              elseif ($diff_sec < 3600) $time_str = floor($diff_sec/60) . ' นาทีที่แล้ว';
                              elseif ($diff_sec < 86400)$time_str = floor($diff_sec/3600) . ' ชั่วโมงที่แล้ว';
                              elseif ($diff_sec < 604800)$time_str = floor($diff_sec/86400) . ' วันที่แล้ว';
                              else                      $time_str = date('d/m/', strtotime($hn['created_at'])) . (date('Y', strtotime($hn['created_at']))+543);

                              $mark_read_btn = '';
                              if ($hn['is_read'] == 0) {
                                  $mark_read_btn = '
                                  <button class="mark-read-dropdown-btn" onclick="event.stopPropagation(); event.preventDefault(); markSingleDropdownRead(this, ' . $hn['id'] . ');" title="ทำเครื่องหมายว่าอ่านแล้ว">
                                      <i class="bi bi-check2"></i>
                                  </button>';
                              }

                              echo '
                              <a href="' . BASE_URL . '/includes/click_notification.php?id=' . $hn['id'] . '" class="text-decoration-none dropdown-item p-3 border-bottom d-flex align-items-start gap-2" style="' . $bg_unread . ' border-bottom-color: rgba(0,0,0,0.03) !important; white-space: normal; transition: background 0.2s;">
                                  <div class="' . $color_class . ' p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px;">
                                      <i class="bi ' . $icon . '" style="font-size: 0.9rem;"></i>
                                  </div>
                                  <div class="flex-grow-1" style="min-width: 0;">
                                      <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
                                          <span class="small fw-bold text-dark text-truncate">' . htmlspecialchars($hn['title']) . '</span>
                                          ' . $dot_unread . '
                                      </div>
                                      <p class="text-muted mb-0 small text-wrap" style="font-size: 0.75rem; line-height: 1.3;">' . htmlspecialchars($hn['message']) . '</p>
                                      <span class="text-muted" style="font-size: 0.65rem;">' . $time_str . '</span>
                                  </div>
                                  ' . $mark_read_btn . '
                                </a>';
                          }
                      } else {
                          echo '
                          <div class="p-4 text-center text-muted" id="emptyNotificationPlaceholder">
                              <i class="bi bi-bell-slash fs-4 d-block mb-2 text-muted"></i>
                              <span class="small">ไม่มีการแจ้งเตือนในขณะนี้</span>
                          </div>';
                      }
                      ?>
                  </div>

                  <!-- ── View All Footer ── -->
                  <div class="p-2 border-top" style="border-top-color: rgba(0,0,0,0.05) !important;">
                      <a href="<?= BASE_URL ?>/includes/notifications.php"
                         class="btn btn-sm btn-light w-100 fw-bold rounded-3 text-primary d-flex align-items-center justify-content-center gap-1"
                         style="font-size: 0.82rem; padding: 0.5rem;">
                          <i class="bi bi-list-ul"></i> ดูการแจ้งเตือนทั้งหมด
                      </a>
                  </div>
              </div>
          </div>

          <!-- User Profile Dropdown -->
          <div class="dropdown">
              <!-- Trigger for Desktop (Full Pill) -->
              <a class="nav-user-pill d-none d-md-flex text-decoration-none" href="#" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="<?= $profile_url !== '#' ? 'cursor: pointer;' : 'cursor: default;' ?>">
                 <?php
                 $has_header_avatar = !empty($u['profile_image']) && file_exists(__DIR__ . '/../uploads/avatars/' . $u['profile_image']);
                 ?>
                 <div class="avatar-sm bg-white text-primary border d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden;">
                     <?php if ($has_header_avatar): ?>
                         <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($u['profile_image']) ?>?v=<?= time() ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';">
                         <i class="bi bi-person-fill" style="display:none;"></i>
                     <?php else: ?>
                         <i class="bi bi-person-fill"></i>
                     <?php endif; ?>
                 </div>
                 <div class="text-start me-1">
                     <div class="fw-bold small line-height-1" style="font-size: 0.85rem; color: #1e293b;"><?= e($u['fullname']) ?></div>
                     <div class="text-muted fw-medium" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;"><?= e($u['role']) ?></div>
                 </div>
                 <i class="bi bi-chevron-down ms-1 text-secondary" style="font-size: 0.75rem;"></i>
              </a>

              <!-- Trigger for Mobile (Avatar with Click Indicator) -->
              <a class="d-flex d-md-none text-decoration-none rounded-circle bg-light border align-items-center justify-content-center position-relative" href="#" id="userMenuDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px; outline: none !important; border-color: rgba(99, 102, 241, 0.25) !important;">
                  <div class="rounded-circle" style="width: 100%; height: 100%; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                      <?php if ($has_header_avatar): ?>
                          <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($u['profile_image']) ?>?v=<?= time() ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';">
                          <i class="bi bi-person-fill text-secondary fs-5" style="display:none;"></i>
                      <?php else: ?>
                          <i class="bi bi-person-fill text-secondary fs-5"></i>
                      <?php endif; ?>
                  </div>
                  <!-- Premium micro-badge indicating it's a dropdown menu -->
                  <span class="position-absolute bottom-0 end-0 bg-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 13px; height: 13px; transform: translate(2px, 2px); border: 1.5px solid #ffffff; z-index: 2;">
                      <i class="bi bi-chevron-down text-white" style="font-size: 0.5rem; -webkit-text-stroke: 0.5px; font-weight: 900;"></i>
                  </span>
              </a>

              <!-- Dropdown Menu -->
              <ul class="dropdown-menu dropdown-menu-end p-0 border-0 shadow-lg mt-3" aria-labelledby="userMenuDropdown" style="width: 240px; border-radius: 1.25rem; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(0,0,0,0.05); overflow: hidden;">
                  <li class="p-3 border-bottom" style="border-bottom-color: rgba(0,0,0,0.05) !important;">
                      <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= e($u['fullname']) ?></div>
                      <div class="badge bg-primary bg-opacity-10 text-primary mt-1 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;"><?= e($u['role']) ?></div>
                  </li>
                  
                  <li>
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-secondary" href="/DVE_DATA_FULL/roles/<?= $u['role'] ?>.php" style="font-size: 0.85rem;">
                          <i class="bi bi-speedometer2 text-primary fs-5"></i> หน้าแรกแดชบอร์ด
                      </a>
                  </li>
                  <li>
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-secondary" href="<?= $profile_url ?>" style="font-size: 0.85rem;">
                          <i class="bi bi-person-bounding-box text-primary fs-5"></i> ข้อมูลส่วนตัว
                      </a>
                  </li>
                  <li id="pwaInstallItem" style="display: none !important;">
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-primary fw-bold" href="#" id="pwaInstallBtn" style="font-size: 0.85rem;">
                          <i class="bi bi-download text-primary fs-5"></i> ติดตั้งแอป DVE Hub
                      </a>
                  </li>
                  <!-- Mobile/Tablet-only menu items -->
                  <li class="d-lg-none">
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-secondary" href="/DVE_DATA_FULL/news_list.php" style="font-size: 0.85rem;">
                          <i class="bi bi-newspaper text-info fs-5"></i> ข่าวประชาสัมพันธ์
                      </a>
                  </li>
                  <li class="d-lg-none">
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-secondary" href="/DVE_DATA_FULL/contact.php" style="font-size: 0.85rem;">
                          <i class="bi bi-chat-dots-fill text-primary fs-5"></i> ติดต่อเรา
                      </a>
                  </li>
                  
                  <li><hr class="dropdown-divider my-1" style="border-color: rgba(0,0,0,0.05);"></li>
                  
                  <li>
                      <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-danger fw-bold" href="/DVE_DATA_FULL/logout.php" style="font-size: 0.85rem;">
                          <i class="bi bi-box-arrow-right fs-5"></i> ออกจากระบบ
                      </a>
                  </li>
              </ul>
          </div>

          <!-- Standalone Quick Logout Button -->
          <a href="/DVE_DATA_FULL/logout.php" class="btn btn-sm btn-outline-danger d-none d-md-inline-flex align-items-center gap-1 fw-bold px-3" style="border-radius: 8px; font-size: 0.8rem; height: 36px; transition: all 0.2s;" title="ออกจากระบบ">
              <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
          </a>
          <a href="/DVE_DATA_FULL/logout.php" class="btn btn-light text-danger d-flex d-md-none align-items-center justify-content-center rounded-circle border p-0" style="width: 38px; height: 38px; transition: all 0.2s;" title="ออกจากระบบ">
              <i class="bi bi-box-arrow-right fs-5"></i>
          </a>
        <?php else: ?>
          <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-light border px-2 px-md-4 fw-bold" style="border-radius: 6px; font-size: 0.82rem;">เข้าสู่ระบบ</a>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-primary px-2 px-md-4 fw-bold shadow-sm" style="border-radius: 6px; font-size: 0.82rem;">สมัครสมาชิก</a>
          </div>
        <?php endif; ?>
    </div>
  </div>
</nav>

<?php
$is_admin_context = false;
if (is_logged_in()) {
    $current_u = current_user();
    if (isset($current_u['role']) && $current_u['role'] === 'admin') {
        $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        if (strpos($script_path, '/admin/') !== false || strpos($script_path, '/roles/admin.php') !== false || strpos($script_path, 'send_announcement.php') !== false) {
            $is_admin_context = true;
        }
    }
}

if ($is_admin_context):
    $hide_welcome = true;
    
    // 1. Calculate badges
    $total_at_risk_students = 0;
    $risk_res = $conn->query("SELECT u.id FROM users u LEFT JOIN daily_reports dr ON u.id = dr.student_id WHERE u.role = 'student' GROUP BY u.id HAVING DATEDIFF(CURRENT_DATE(), IFNULL(MAX(dr.date_work), '2000-01-01')) >= 3");
    if ($risk_res) $total_at_risk_students = $risk_res->num_rows;

    $unread_messages = 0;
    $um_res = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'");
    if ($um_res) $unread_messages = (int)($um_res->fetch_row()[0] ?? 0);

    $cur_script = basename($_SERVER['SCRIPT_NAME']);
    $cur_role_get = $_GET['role'] ?? '';

    $active_class = function($script_name, $role_arg = '') use ($cur_script, $cur_role_get) {
        if ($cur_script !== $script_name) return '';
        if ($role_arg !== '') {
            return ($cur_role_get === $role_arg) ? 'active' : '';
        }
        return 'active';
    };
?>
<style>
/* 👑 Premium Clean Admin Sidebar Theme */
:root {
    --sidebar-w: 265px;
    --sidebar-w-collapsed: 72px;
    --nav-h: 70px;
    --sidebar-bg: linear-gradient(180deg, #0b1329 0%, #0f172a 60%, #111c38 100%);
    --sidebar-hover: rgba(255,255,255,0.07);
    --sidebar-active: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    --accent: #6366f1;
    --transition: 0.28s cubic-bezier(0.4,0,0.2,1);
}

body {
    background: #f8fafc;
    color: #0f172a;
}

/* ── Sidebar ── */
#adminSidebar {
    position: fixed;
    top: var(--nav-h);
    left: 0;
    bottom: 0;
    width: var(--sidebar-w);
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    z-index: 999;
    overflow: hidden;
    transition: width var(--transition);
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: 8px 0 32px rgba(15, 23, 42, 0.12);
}

#adminSidebar.collapsed {
    width: var(--sidebar-w-collapsed);
}

/* ── Main content shifts right ── */
#adminContent {
    margin-left: var(--sidebar-w);
    min-height: calc(100vh - var(--nav-h));
    transition: margin-left var(--transition);
    padding: 2rem;
}

#adminSidebar.collapsed ~ #adminContent {
    margin-left: var(--sidebar-w-collapsed);
}

/* ── Sidebar Header ── */
.sb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    height: 68px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
    background: rgba(255,255,255,0.02);
}

.sb-brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    overflow: hidden;
    white-space: nowrap;
}

.sb-logo {
    width: 40px; height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: white;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
    flex-shrink: 0;
}

.sb-brand-text { overflow: hidden; }
.sb-brand-title { font-size: 0.98rem; font-weight: 800; color: #fff; letter-spacing: 0.5px; white-space: nowrap; }
.sb-brand-sub   { font-size: 0.68rem; color: #64748b; font-weight: 500; white-space: nowrap; }

.sb-toggle {
    width: 32px; height: 32px;
    border: none; border-radius: 9px;
    background: rgba(255,255,255,0.06);
    color: #94a3b8; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; flex-shrink: 0;
}
.sb-toggle:hover { background: rgba(255,255,255,0.15); color: #fff; transform: scale(1.05); }
.sb-toggle i { font-size: 0.85rem; transition: transform var(--transition); }
#adminSidebar.collapsed .sb-toggle i { transform: rotate(180deg); }

/* ── Menu ── */
.sb-menu { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 1rem 0.75rem; }
.sb-menu::-webkit-scrollbar { width: 4px; }
.sb-menu::-webkit-scrollbar-track { background: transparent; }
.sb-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }

.sb-section {
    font-size: 0.65rem; font-weight: 800; letter-spacing: 1.2px;
    color: #475569; text-transform: uppercase;
    padding: 1.2rem 0.6rem 0.4rem; white-space: nowrap;
    overflow: hidden; transition: opacity var(--transition);
}

#adminSidebar.collapsed .sb-section { opacity: 0; }

.sb-link {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 0.68rem 0.85rem;
    color: #94a3b8; text-decoration: none !important;
    border-radius: 12px; font-size: 0.9rem; font-weight: 500;
    margin-bottom: 4px;
    transition: all 0.2s ease;
    position: relative; white-space: nowrap; overflow: hidden;
}
.sb-link i {
    font-size: 1.15rem; flex-shrink: 0; width: 24px; text-align: center;
    transition: transform 0.2s, color 0.2s;
}
.sb-link span { overflow: hidden; white-space: nowrap; transition: opacity var(--transition), max-width var(--transition); max-width: 200px; }
.sb-link .sb-badge { flex-shrink: 0; margin-left: auto; }

.sb-link:hover { background: var(--sidebar-hover); color: #fff; transform: translateX(4px); }
.sb-link:hover i { transform: scale(1.15); }

.sb-link.active {
    background: var(--sidebar-active);
    color: #fff; font-weight: 700;
    box-shadow: 0 6px 20px -4px rgba(79,70,229,0.45);
}
.sb-link.active i { color: #fff !important; }

/* Collapsed state: hide label text */
#adminSidebar.collapsed .sb-link span { opacity: 0; max-width: 0; }
#adminSidebar.collapsed .sb-link { justify-content: center; padding: 0.68rem; }
#adminSidebar.collapsed .sb-link i { width: auto; }
#adminSidebar.collapsed .sb-badge { display: none !important; }

/* Tooltip on collapsed */
#adminSidebar.collapsed .sb-link::after {
    content: attr(data-tip);
    position: absolute; left: calc(100% + 12px);
    background: #1e293b; color: #f8fafc;
    padding: 6px 12px; border-radius: 8px;
    font-size: 0.78rem; font-weight: 600;
    white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity 0.18s;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 9999;
}
#adminSidebar.collapsed .sb-link:hover::after { opacity: 1; }

/* ── Sidebar Footer ── */
.sb-footer {
    padding: 1rem 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    overflow: hidden;
}
.sb-user { display: flex; align-items: center; gap: 0.75rem; }
.sb-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #a855f7);
    color: #fff; font-weight: 700; font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(79,70,229,0.3);
}
.sb-user-info { overflow: hidden; flex: 1; }
.sb-user-name { font-size: 0.85rem; font-weight: 700; color: #f1f5f9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role { font-size: 0.68rem; color: #64748b; font-weight: 500; }
.sb-logout {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 9px;
    color: #64748b; text-decoration: none; transition: all 0.2s; flex-shrink: 0;
}
.sb-logout:hover { background: rgba(239,68,68,0.18); color: #ef4444; transform: scale(1.05); }

#adminSidebar.collapsed .sb-user-info,
#adminSidebar.collapsed .sb-logout { display: none; }
#adminSidebar.collapsed .sb-user { justify-content: center; }

/* ── Mobile overlay backdrop ── */
.sb-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
    z-index: 998;
}
.sb-overlay.active { display: block; }

/* ── Mobile: sidebar slides in from left ── */
@media (max-width: 991.98px) {
    #adminSidebar {
        left: calc(-1 * var(--sidebar-w));
        width: var(--sidebar-w) !important;
    }
    #adminSidebar.mobile-open { left: 0; }
    #adminContent { margin-left: 0 !important; padding: 1.25rem !important; }
    .sb-section { opacity: 1 !important; }
    #adminSidebar .sb-link span { opacity: 1 !important; max-width: 200px !important; }
    #adminSidebar .sb-link { justify-content: flex-start !important; padding: 0.68rem 0.85rem !important; }
    #adminSidebar .sb-link i { width: 24px !important; }
}

/* Mobile toggle button */
.sb-mobile-toggle {
    display: none;
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 997;
    width: 54px; height: 54px;
    border-radius: 50%; border: none;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff; font-size: 1.35rem;
    box-shadow: 0 10px 28px rgba(79,70,229,0.45);
    cursor: pointer; transition: transform 0.2s;
}
.sb-mobile-toggle:hover { transform: scale(1.1); }

@media (max-width: 991.98px) {
    .sb-mobile-toggle { display: flex; align-items: center; justify-content: center; }
}
</style>

<!-- ☰ Mobile Sidebar Overlay -->
<div class="sb-overlay" id="sbOverlay"></div>

<!-- 🛡️ GLOBAL ADMIN SIDEBAR NAV -->
<aside id="adminSidebar">
    <!-- Header -->
    <div class="sb-header">
        <div class="sb-brand">
            <div class="sb-logo"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="sb-brand-text">
                <div class="sb-brand-title">ADMIN</div>
                <div class="sb-brand-sub">DVE Vocational System</div>
            </div>
        </div>
        <button class="sb-toggle d-none d-lg-flex" id="sbToggle" title="ย่อ/ขยาย Sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <!-- Menu -->
    <div class="sb-menu">
        <div class="sb-section">หน้าหลัก</div>
        <a href="/DVE_DATA_FULL/roles/admin.php" class="sb-link <?= $active_class('admin.php') ?>" data-tip="Dashboard">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>

        <div class="sb-section">จัดการผู้ใช้งาน</div>
        <a href="/DVE_DATA_FULL/admin/manage_users.php?role=student" class="sb-link <?= $active_class('manage_users.php', 'student') ?>" data-tip="จัดการนักเรียน">
            <i class="bi bi-person-video2" style="color:#38bdf8;"></i><span>จัดการนักเรียน</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_users.php?role=teacher" class="sb-link <?= $active_class('manage_users.php', 'teacher') ?>" data-tip="จัดการครูที่ปรึกษา">
            <i class="bi bi-person-badge" style="color:#34d399;"></i><span>จัดการครูที่ปรึกษา</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_users.php?role=staff" class="sb-link <?= $active_class('manage_users.php', 'staff') ?>" data-tip="จัดการเจ้าหน้าที่">
            <i class="bi bi-person-gear" style="color:#fbbf24;"></i><span>จัดการเจ้าหน้าที่</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_users.php?role=supervisor" class="sb-link <?= $active_class('manage_users.php', 'supervisor') ?>" data-tip="จัดการ Supervisor">
            <i class="bi bi-person-heart" style="color:#f87171;"></i><span>จัดการ Supervisor</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_supervisors.php" class="sb-link <?= $active_class('manage_supervisors.php') ?>" data-tip="มอบหมาย Supervisor">
            <i class="bi bi-person-check-fill" style="color:#2dd4bf;"></i><span>มอบหมาย Supervisor</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_users.php?role=director" class="sb-link <?= $active_class('manage_users.php', 'director') ?>" data-tip="จัดการผู้บริหาร">
            <i class="bi bi-person-workspace" style="color:#fb923c;"></i><span>จัดการผู้บริหาร</span>
        </a>

        <div class="sb-section">การติดตาม & รายงาน</div>
        <a href="/DVE_DATA_FULL/admin/dropout_risk.php" class="sb-link <?= $active_class('dropout_risk.php') ?>" data-tip="นักเรียนเสี่ยงขาดส่ง">
            <i class="bi bi-exclamation-octagon-fill" style="color:#ef4444;"></i>
            <span>เด็กเสี่ยงขาดส่ง</span>
            <?php if ($total_at_risk_students > 0): ?>
                <span class="badge bg-danger rounded-pill sb-badge" style="font-size:0.65rem;"><?= $total_at_risk_students ?></span>
            <?php endif; ?>
        </a>
        <a href="/DVE_DATA_FULL/admin/view_student_reports.php" class="sb-link <?= $active_class('view_student_reports.php') ?>" data-tip="สมุดบันทึกรายงาน">
            <i class="bi bi-journal-text" style="color:#818cf8;"></i><span>สมุดบันทึกรายงาน</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/export_reports.php" class="sb-link <?= $active_class('export_reports.php') ?>" data-tip="ส่งออก Excel">
            <i class="bi bi-file-earmark-excel-fill" style="color:#22c55e;"></i><span>ส่งออก Excel</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_certificates.php" class="sb-link <?= $active_class('manage_certificates.php') ?>" data-tip="ออกเกียรติบัตร">
            <i class="bi bi-award" style="color:#eab308;"></i><span>ออกเกียรติบัตร</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/manage_messages.php" class="sb-link <?= $active_class('manage_messages.php') ?>" data-tip="กล่องข้อความ">
            <i class="bi bi-envelope-paper" style="color:#f43f5e;"></i>
            <span>กล่องข้อความ</span>
            <?php if ($unread_messages > 0): ?>
                <span class="badge bg-danger rounded-pill sb-badge" style="font-size:0.65rem;"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>

        <div class="sb-section">การตั้งค่าระบบ</div>
        <a href="/DVE_DATA_FULL/admin/manage_classrooms.php" class="sb-link <?= $active_class('manage_classrooms.php') ?>" data-tip="จัดการห้องเรียน">
            <i class="bi bi-door-open" style="color:#94a3b8;"></i><span>จัดการห้องเรียน</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/companies.php" class="sb-link <?= $active_class('companies.php') ?>" data-tip="สถานประกอบการ">
            <i class="bi bi-building" style="color:#38bdf8;"></i><span>สถานประกอบการ</span>
        </a>
        <a href="/DVE_DATA_FULL/includes/send_announcement.php" class="sb-link <?= $active_class('send_announcement.php') ?>" data-tip="ส่งประกาศระบบ">
            <i class="bi bi-megaphone" style="color:#a78bfa;"></i><span>ส่งประกาศระบบ</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/system_tools.php" class="sb-link <?= $active_class('system_tools.php') ?>" data-tip="เครื่องมือระบบ">
            <i class="bi bi-wrench-adjustable-circle" style="color:#fb923c;"></i><span>เครื่องมือระบบ</span>
        </a>
        <a href="/DVE_DATA_FULL/admin/site_settings.php" class="sb-link <?= $active_class('site_settings.php') ?>" data-tip="ตั้งค่าการแสดงผล">
            <i class="bi bi-gear-wide-connected" style="color:#818cf8;"></i><span>ตั้งค่าระบบ</span>
        </a>
    </div>

    <!-- Footer -->
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= mb_substr($current_u['fullname'] ?? 'A', 0, 1, 'UTF-8') ?></div>
            <div class="sb-user-info">
                <div class="sb-user-name"><?= e($current_u['fullname'] ?? 'Admin') ?></div>
                <div class="sb-user-role">ผู้ดูแลระบบ</div>
            </div>
            <a href="/DVE_DATA_FULL/logout.php" class="sb-logout" title="ออกจากระบบ">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- OPEN ADMIN CONTENT WRAPPER -->
<div id="adminContent">
<?php else: ?>
<div class="container main-content-container">
  <?php if(!isset($hide_welcome) || !$hide_welcome): ?>
    <!-- Welcome Banner แบบใหม่ (จะถูกซ่อนถ้ามีการกำหนด $hide_welcome = true) -->
    <div class="welcome-banner animate-fade-in">
        <div class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-2 mb-3 fw-bold" style="letter-spacing: 0.5px;">
            DVE | PBPVC
        </div>
        <h1 class="fw-extrabold mb-2" style="font-size: 2.25rem;">ระบบการนิเทศรายวิชาฝึกประสบการณ์สมรรถนะวิชาชีพ</h1>
        <p class="text-secondary lead mb-0">วิทยาลัยอาชีวศึกษาเพชรบุรี (PBPVC)</p>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

  <?php if(is_logged_in()): 
    $latest_id_res = $conn->query("SELECT MAX(id) as max_id FROM notifications WHERE user_id = " . (int)$u['id']);
    $latest_id_val = 0;
    if ($latest_id_res) {
        $latest_id_row = $latest_id_res->fetch_assoc();
        $latest_id_val = (int)$latest_id_row['max_id'];
    }
  ?>
  <!-- Toast Container -->
  
  <style>
      .fab-home {
          position: fixed;
          bottom: 24px;
          left: 24px;
          z-index: 1040;
          background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
          color: white;
          width: 56px;
          height: 56px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.5rem;
          box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
          transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
          text-decoration: none;
      }
      .fab-home:hover {
          transform: translateY(-5px) scale(1.1);
          box-shadow: 0 15px 35px rgba(217, 70, 239, 0.5);
          color: white;
      }
      .fab-home .tooltip-text {
          position: absolute;
          left: 70px;
          background: rgba(15, 23, 42, 0.85);
          backdrop-filter: blur(8px);
          color: white;
          padding: 6px 12px;
          border-radius: 8px;
          font-size: 0.85rem;
          font-weight: 600;
          white-space: nowrap;
          opacity: 0;
          visibility: hidden;
          transition: all 0.3s ease;
          transform: translateX(-10px);
          pointer-events: none;
      }
      .fab-home:hover .tooltip-text {
          opacity: 1;
          visibility: visible;
          transform: translateX(0);
      }
      
      @media (max-width: 768px) {
          .fab-home {
              bottom: 20px;
              left: 20px;
              width: 50px;
              height: 50px;
              font-size: 1.25rem;
          }
      }
  </style>

  <?php
  // แสดงปุ่มกลับหน้าหลักเมื่อล็อกอินแล้ว และไม่ได้อยู่บนหน้าแดชบอร์ดหลักของ Role นั้นๆ
  $current_script = basename($_SERVER['SCRIPT_NAME']);
  $is_main_dashboard = in_array($current_script, ['student.php', 'teacher.php', 'mentor.php', 'director.php', 'admin.php', 'supervisor.php', 'login.php', 'register.php', 'index.php']);
  
  if (is_logged_in() && !$is_main_dashboard):
      $home_url = BASE_URL . "/roles/" . htmlspecialchars($u['role']) . ".php";
  ?>
  <a href="<?= $home_url ?>" class="fab-home">
      <i class="bi bi-house-door-fill"></i>
      <span class="tooltip-text">กลับหน้าหลัก</span>
  </a>
  <?php endif; ?>

  <div id="toastNotificationContainer" style="position: fixed; bottom: 24px; right: 24px; z-index: 1050; display: flex; flex-direction: column; gap: 12px; pointer-events: none;"></div>

  <script>
  let lastNotificationId = <?= $latest_id_val ?>;

  // ── Notification Type → Icon / Color (JS mirror of PHP map) ──
  const NOTIF_TYPE_MAP = {
      'info':                 { icon: 'bi-info-circle-fill',        color: 'text-primary bg-primary bg-opacity-10' },
      'report_submitted':     { icon: 'bi-send-fill',               color: 'text-primary bg-primary bg-opacity-10' },
      'report_approved':      { icon: 'bi-check-circle-fill',       color: 'text-success bg-success bg-opacity-10' },
      'report_rejected':      { icon: 'bi-exclamation-octagon-fill',color: 'text-danger bg-danger bg-opacity-10' },
      'report_late_reminder': { icon: 'bi-clock-fill',              color: 'text-warning bg-warning bg-opacity-10' },
      'review_late':          { icon: 'bi-hourglass-split',         color: 'text-dark bg-secondary bg-opacity-10' },
      'schedule_update':      { icon: 'bi-calendar2-week-fill',     color: 'text-purple bg-purple bg-opacity-10' },
      'evaluation':           { icon: 'bi-trophy-fill',             color: 'text-warning bg-warning bg-opacity-10' },
      'announcement':         { icon: 'bi-megaphone-fill',          color: 'text-info bg-info bg-opacity-10' },
      'welcome':              { icon: 'bi-stars',                   color: 'text-primary bg-primary bg-opacity-10' },
  };
  function getNotifStyle(type) {
      return NOTIF_TYPE_MAP[type] || NOTIF_TYPE_MAP['info'];
  }

  function checkNewNotifications() {
      fetch('<?= BASE_URL ?>/includes/check_new_notifications.php?last_id=' + lastNotificationId)
          .then(res => res.json())
          .then(data => {
              if (data.success) {
                  if (data.new_notifications && data.new_notifications.length > 0) {
                      const container    = document.getElementById('toastNotificationContainer');
                      const dropdownList = document.getElementById('dropdownNotificationList');
                      const placeholder  = document.getElementById('emptyNotificationPlaceholder');
                      if (placeholder) placeholder.remove();

                      data.new_notifications.forEach(n => {
                          const { icon, color: colorClass } = getNotifStyle(n.type);

                          // 1. Show Toast
                          const toast = document.createElement('div');
                          toast.className = 'glass-toast';
                          toast.innerHTML = `
                              <div class="${colorClass} p-2 rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;min-width:38px;">
                                  <i class="bi ${icon}" style="font-size:1.1rem;"></i>
                              </div>
                              <div style="flex-grow:1;min-width:0;">
                                  <strong class="d-block text-dark small" style="margin-bottom:2px;">${n.title}</strong>
                                  <span class="text-muted d-block" style="font-size:0.75rem;line-height:1.3;">${n.message}</span>
                              </div>`;
                          toast.addEventListener('click', () => {
                              window.location.href = '<?= BASE_URL ?>/includes/click_notification.php?id=' + n.id;
                          });
                          container.appendChild(toast);
                          setTimeout(() => toast.classList.add('show'), 50);
                          setTimeout(() => {
                              toast.classList.remove('show');
                              setTimeout(() => toast.remove(), 500);
                          }, 6000);

                          // 2. Prepend to dropdown list
                          const item = document.createElement('a');
                          item.href = '<?= BASE_URL ?>/includes/click_notification.php?id=' + n.id;
                          item.className = 'text-decoration-none dropdown-item p-3 border-bottom d-flex align-items-start gap-2';
                          item.style.cssText = 'background-color:rgba(99,102,241,0.04);border-bottom-color:rgba(0,0,0,0.03)!important;white-space:normal;transition:background 0.2s;';
                          item.innerHTML = `
                              <div class="${colorClass} p-2 rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;min-width:32px;">
                                  <i class="bi ${icon}" style="font-size:0.9rem;"></i>
                              </div>
                              <div class="flex-grow-1" style="min-width:0;">
                                  <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
                                      <span class="small fw-bold text-dark text-truncate">${n.title}</span>
                                      <span class="d-inline-block bg-primary rounded-circle" style="width:6px;height:6px;min-width:6px;"></span>
                                  </div>
                                  <p class="text-muted mb-0 small text-wrap" style="font-size:0.75rem;line-height:1.3;">${n.message}</p>
                                  <span class="text-muted" style="font-size:0.65rem;">${n.time}</span>
                              </div>
                              <button class="mark-read-dropdown-btn" onclick="event.stopPropagation(); event.preventDefault(); markSingleDropdownRead(this, ${n.id});" title="ทำเครื่องหมายว่าอ่านแล้ว">
                                  <i class="bi bi-check2"></i>
                              </button>`;
                          if (dropdownList) dropdownList.insertBefore(item, dropdownList.firstChild);
                      });
                      lastNotificationId = data.latest_id;
                  }

                  // Update unread count badge
                  let badge = document.getElementById('notificationCountBadge');
                  const dropdownBtn = document.getElementById('notificationDropdown');
                  if (data.unread_count > 0) {
                      if (!badge && dropdownBtn) {
                          badge = document.createElement('span');
                          badge.id = 'notificationCountBadge';
                          badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                          badge.style.cssText = 'font-size:0.65rem;padding:0.25em 0.5em;z-index:10;margin-top:4px;margin-left:-4px;';
                          dropdownBtn.appendChild(badge);
                      }
                      if (badge) badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                  } else {
                      if (badge) badge.remove();
                  }
              }
          })
          .catch(err => console.error('DVE Notify poll error:', err));
  }

  function markSingleDropdownRead(btnEl, id) {
      const formData = new FormData();
      formData.append('action', 'mark_read');
      formData.append('id', id);
      
      fetch('<?= BASE_URL ?>/includes/ajax_notifications.php', {
          method: 'POST',
          body: formData
      })
      .then(res => res.json())
      .then(data => {
          if (data.success) {
              const item = btnEl.closest('.dropdown-item');
              if (item) {
                  item.style.backgroundColor = '';
                  const dot = item.querySelector('.bg-primary.rounded-circle');
                  if (dot) dot.remove();
                  btnEl.remove();
              }
              
              const badge = document.getElementById('notificationCountBadge');
              if (data.unread_count > 0) {
                  if (badge) badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
              } else {
                  if (badge) badge.remove();
                  const markAllBtn = document.getElementById('markAllReadLink');
                  if (markAllBtn) markAllBtn.style.display = 'none';
              }
              
              if (typeof refreshInboxPage === 'function') {
                  refreshInboxPage();
              }
          }
      })
      .catch(err => console.error('Error marking single dropdown read:', err));
  }

  document.addEventListener('DOMContentLoaded', () => {
      const markAllBtn = document.getElementById('markAllReadLink');
      if (markAllBtn) {
          markAllBtn.addEventListener('click', function(e) {
              e.preventDefault();
              const formData = new FormData();
              formData.append('action', 'mark_all_read');
              
              fetch('<?= BASE_URL ?>/includes/ajax_notifications.php', {
                  method: 'POST',
                  body: formData
              })
              .then(res => res.json())
              .then(data => {
                  if (data.success) {
                      const badge = document.getElementById('notificationCountBadge');
                      if (badge) badge.remove();
                      
                      const dropdownItems = document.querySelectorAll('#dropdownNotificationList .dropdown-item');
                      dropdownItems.forEach(item => {
                          item.style.backgroundColor = '';
                          const dot = item.querySelector('.bg-primary.rounded-circle');
                          if (dot) dot.remove();
                          const btn = item.querySelector('.mark-read-dropdown-btn');
                          if (btn) btn.remove();
                      });
                      
                      markAllBtn.style.display = 'none';
                      
                      if (typeof refreshInboxPage === 'function') {
                          refreshInboxPage();
                      }
                  }
              })
              .catch(err => console.error('Error marking all as read:', err));
          });
      }
  });

  // ── Visibility API: หยุด Polling เมื่อ Tab ไม่ Active (ประหยัด Resource) ──
  let _notifInterval = null;
  function startNotifPolling() {
      if (!_notifInterval) {
          checkNewNotifications();
          _notifInterval = setInterval(checkNewNotifications, 15000); // 15 วินาที
      }
  }
  function stopNotifPolling() {
      if (_notifInterval) { clearInterval(_notifInterval); _notifInterval = null; }
  }
  document.addEventListener('visibilitychange', () => {
      document.hidden ? stopNotifPolling() : startNotifPolling();
  });
  startNotifPolling(); // เริ่มทันที
  </script>
  <?php endif; ?>

  <script>
  function updateThaiClock() {
      const now = new Date();
      
      // Locale formatting options for Thai environment
      const dateOpts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
      const timeOpts = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
      
      const dateEl = document.getElementById('thai-nav-date');
      const timeEl = document.getElementById('thai-nav-time');
      
      if (dateEl && timeEl) {
          dateEl.textContent = now.toLocaleDateString('th-TH', dateOpts);
          timeEl.textContent = now.toLocaleTimeString('th-TH', timeOpts) + ' น.';
      }
  }
  
  // Trigger immediate load and begin 1s interval
  updateThaiClock();
  setInterval(updateThaiClock, 1000);
  </script>
