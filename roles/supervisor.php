<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['supervisor']);

$u = current_user();
$supervisor_id = (int)$u['id'];

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';

// ดึง student_ids ที่ดูแล
$student_ids = get_supervised_student_ids($supervisor_id);
$total_students = count($student_ids);

// สร้าง SQL safe IN list
$ids_sql = !empty($student_ids) ? implode(',', array_map('intval', $student_ids)) : '0';

// นับบันทึกที่ยังไม่ได้ comment โดย supervisor
$total_uncommented = 0;
$total_commented   = 0;
$total_reports     = 0;

if ($total_students > 0) {
    $r = $conn->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN supervisor_comment IS NOT NULL AND supervisor_comment != '' THEN 1 ELSE 0 END) AS commented
        FROM daily_reports
        WHERE student_id IN ($ids_sql)");
    if ($r && $row = $r->fetch_assoc()) {
        $total_reports     = (int)$row['total'];
        $total_commented   = (int)$row['commented'];
        $total_uncommented = $total_reports - $total_commented;
    }
}

// ดึงบันทึกล่าสุด 5 รายการ
$recent_reports = [];
if ($total_students > 0) {
    $r2 = $conn->query("SELECT dr.id, dr.date_work, dr.details, dr.status, dr.supervisor_comment, dr.image1, dr.image2,
            u.fullname AS student_name, u.student_code, u.profile_image
        FROM daily_reports dr
        JOIN users u ON dr.student_id = u.id
        WHERE dr.student_id IN ($ids_sql)
        ORDER BY dr.created_at DESC
        LIMIT 5");
    if ($r2) {
        while ($row = $r2->fetch_assoc()) {
            $recent_reports[] = $row;
        }
    }
}
?>
<style>
:root {
    --sv-primary:   #0ea5e9;
    --sv-secondary: #6366f1;
    --sv-accent:    #14b8a6;
    --sv-gradient:  linear-gradient(135deg, #0c4a6e 0%, #0ea5e9 45%, #14b8a6 100%);
}

.sv-page {
    background: linear-gradient(160deg, #f0f9ff 0%, #e0f2fe 40%, #f0fdf4 100%);
    min-height: 100vh;
    padding-bottom: 4rem;
}

.sv-hero {
    background: var(--sv-gradient);
    border-radius: 2rem;
    padding: 2.5rem 3rem;
    color: #fff;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(14,165,233,0.3);
}
.sv-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
}
.sv-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: 35%;
    width: 180px; height: 180px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.sv-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: #ffffff !important;
    color: #000000 !important;
    border-radius: 50px;
    padding: .4rem 1.25rem;
    font-size: .85rem;
    font-weight: 800;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.sv-stat-card {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.5rem;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 4px 20px rgba(0,0,0,.04);
    transition: all .3s ease;
}
.sv-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(14,165,233,.12);
    border-color: rgba(14,165,233,.2);
}
.sv-stat-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 1rem;
}
.sv-stat-num {
    font-size: 2.2rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}
.sv-stat-lbl {
    font-size: .82rem;
    font-weight: 600;
    color: #64748b;
    margin-top: .35rem;
}

.sv-quick-btn {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #fff;
    border: 1.5px solid rgba(14,165,233,.15);
    border-radius: 1rem;
    padding: 1rem 1.25rem;
    text-decoration: none;
    color: #1e293b;
    font-weight: 700;
    font-size: .9rem;
    transition: all .25s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.sv-quick-btn:hover {
    background: rgba(14,165,233,.05);
    border-color: rgba(14,165,233,.4);
    color: var(--sv-primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(14,165,233,.12);
}
.sv-quick-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.sv-section-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #94a3b8;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.sv-profile-card {
    background: #fff;
    border-radius: 1.5rem;
    padding: 1.75rem;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 4px 20px rgba(0,0,0,.04);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.sv-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(14,165,233,.25);
    box-shadow: 0 8px 20px rgba(14,165,233,.15);
    margin-bottom: 1rem;
}
.sv-avatar-placeholder {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0ea5e9, #14b8a6);
    color: #fff;
    font-size: 2rem;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1rem;
    box-shadow: 0 8px 20px rgba(14,165,233,.25);
}

.sv-recent-card {
    background: #ffffff;
    border-radius: 1.25rem;
    border: 1px solid rgba(0,0,0,.06);
    padding: 1.25rem;
    margin-bottom: 0.85rem;
    box-shadow: 0 4px 16px rgba(0,0,0,.02);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.sv-recent-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: linear-gradient(180deg, #0ea5e9, #14b8a6);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.sv-recent-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(14, 165, 233, 0.12);
    border-color: rgba(14, 165, 233, 0.2);
}
.sv-recent-card:hover::before {
    opacity: 1;
}
.sv-recent-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0f2fe;
    background: linear-gradient(135deg, #0ea5e9, #14b8a6);
    color: #ffffff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.sv-details-box {
    background: rgba(248, 250, 252, 0.9);
    border-left: 3px solid #0ea5e9;
    border-radius: 0 10px 10px 0;
    padding: 10px 14px;
    font-size: 0.88rem;
    color: #334155;
    line-height: 1.5;
}
.sv-action-circle {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    transition: all 0.25s ease;
    flex-shrink: 0;
}
.sv-recent-card:hover .sv-action-circle {
    background: #0ea5e9;
    color: #ffffff;
    transform: translateX(3px);
}
</style>

<div class="sv-page">
  <div class="container py-4">

    <!-- Hero -->
    <div class="sv-hero animate-fade-up animated-gradient-bg">
      <div class="sv-hero-badge">
        <i class="bi bi-person-workspace"></i> ผู้ดูแลการฝึกงาน
      </div>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index:2;">
        <div>
          <h1 class="fw-extrabold text-white mb-1" style="font-size:2rem; letter-spacing:-1px; color:#ffffff !important;">
            สวัสดีครับ <?= htmlspecialchars($u['fullname'] ?? 'ผู้ดูแล') ?>
          </h1>
          <p class="mb-1" style="color:rgba(255,255,255,.88);">
            ระบบดูแลการฝึกงาน | ติดตามบันทึกงานและให้ feedback นักเรียนที่ท่านดูแล
          </p>
          <?php if (!empty($u['company_name'])): ?>
          <div class="mt-2 mb-1">
            <span class="badge rounded-pill bg-white px-3.5 py-2 fw-bold shadow-sm animate-fade-in" style="font-size:.88rem; color:#000000 !important; background:#ffffff !important;">
              <i class="bi bi-building text-primary me-1"></i> <?= htmlspecialchars($u['company_name']) ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="<?= BASE_URL ?>/supervisor/view_students.php" class="btn btn-light fw-bold rounded-pill px-4 shadow-sm text-dark btn-shine hover-lift">
            <i class="bi bi-people-fill me-1"></i> รายชื่อนักเรียน
          </a>
          <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php" class="btn fw-bold rounded-pill px-4 btn-shine hover-lift" style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.4);">
            <i class="bi bi-journal-text me-1"></i> ดูบันทึกงาน
          </a>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- LEFT: Stats + Quick Links -->
      <div class="col-lg-8">

        <!-- Stat Cards -->
        <p class="sv-section-title"><i class="bi bi-bar-chart-fill text-primary"></i> สรุปข้อมูลภาพรวม</p>
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div class="sv-stat-card text-center h-100 hover-lift hover-icon-bounce animate-fade-up delay-1">
              <div class="sv-stat-icon mx-auto" style="background:rgba(14,165,233,.1);color:var(--sv-primary);">
                <i class="bi bi-people-fill"></i>
              </div>
              <div class="sv-stat-num"><?= $total_students ?></div>
              <div class="sv-stat-lbl">นักเรียนที่ดูแล</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="sv-stat-card text-center h-100 hover-lift hover-icon-bounce animate-fade-up delay-2">
              <div class="sv-stat-icon mx-auto" style="background:rgba(245,158,11,.1);color:#f59e0b;">
                <i class="bi bi-journal-text"></i>
              </div>
              <div class="sv-stat-num"><?= $total_reports ?></div>
              <div class="sv-stat-lbl">บันทึกงานทั้งหมด</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="sv-stat-card text-center h-100 hover-lift hover-icon-bounce animate-fade-up delay-3">
              <div class="sv-stat-icon mx-auto" style="background:rgba(239,68,68,.1);color:#ef4444;">
                <i class="bi bi-chat-dots"></i>
              </div>
              <div class="sv-stat-num"><?= $total_uncommented ?></div>
              <div class="sv-stat-lbl">ยังไม่ได้คอมเมนต์</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="sv-stat-card text-center h-100 hover-lift hover-icon-bounce animate-fade-up delay-4">
              <div class="sv-stat-icon mx-auto" style="background:rgba(16,185,129,.1);color:#10b981;">
                <i class="bi bi-check-circle-fill"></i>
              </div>
              <div class="sv-stat-num"><?= $total_commented ?></div>
              <div class="sv-stat-lbl">คอมเมนต์แล้ว</div>
            </div>
          </div>
        </div>

        <!-- Quick Links -->
        <p class="sv-section-title"><i class="bi bi-grid-fill text-primary"></i> เมนูด่วน</p>
        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php" class="sv-quick-btn hover-lift hover-icon-rotate btn-shine animate-fade-up delay-1">
              <div class="sv-quick-icon" style="background:rgba(14,165,233,.1);color:var(--sv-primary);">
                <i class="bi bi-journal-check"></i>
              </div>
              <div>
                <div>ดูบันทึกงานรายวัน</div>
                <div class="text-muted fw-normal" style="font-size:.78rem;">ตรวจสอบและเขียน feedback</div>
              </div>
              <i class="bi bi-chevron-right ms-auto text-muted" style="font-size:.8rem;"></i>
            </a>
          </div>
          <div class="col-sm-6">
            <a href="<?= BASE_URL ?>/supervisor/view_students.php" class="sv-quick-btn hover-lift hover-icon-rotate btn-shine animate-fade-up delay-2">
              <div class="sv-quick-icon" style="background:rgba(99,102,241,.1);color:#6366f1;">
                <i class="bi bi-people"></i>
              </div>
              <div>
                <div>รายชื่อนักเรียน</div>
                <div class="text-muted fw-normal" style="font-size:.78rem;">ดูรายชื่อนักเรียนที่ดูแล</div>
              </div>
              <i class="bi bi-chevron-right ms-auto text-muted" style="font-size:.8rem;"></i>
            </a>
          </div>
          <div class="col-sm-6">
            <a href="<?= BASE_URL ?>/supervisor/profile.php" class="sv-quick-btn hover-lift hover-icon-rotate btn-shine animate-fade-up delay-3">
              <div class="sv-quick-icon" style="background:rgba(20,184,166,.1);color:var(--sv-accent);">
                <i class="bi bi-person-bounding-box"></i>
              </div>
              <div>
                <div>ข้อมูลส่วนตัว</div>
                <div class="text-muted fw-normal" style="font-size:.78rem;">แก้ไขโปรไฟล์ของคุณ</div>
              </div>
              <i class="bi bi-chevron-right ms-auto text-muted" style="font-size:.8rem;"></i>
            </a>
          </div>
          <div class="col-sm-6">
            <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php?status_filter=uncommented" class="sv-quick-btn hover-lift hover-icon-rotate btn-shine animate-fade-up delay-4">
              <div class="sv-quick-icon" style="background:rgba(239,68,68,.1);color:#ef4444;">
                <i class="bi bi-bell-fill"></i>
              </div>
              <div>
                <div>รอ feedback</div>
                <div class="text-muted fw-normal" style="font-size:.78rem;"><?= $total_uncommented ?> รายการรอคอมเมนต์</div>
              </div>
              <?php if ($total_uncommented > 0): ?>
              <span class="badge bg-danger rounded-pill ms-auto pulse-badge" style="font-size:.75rem;"><?= $total_uncommented ?></span>
              <?php else: ?>
              <i class="bi bi-chevron-right ms-auto text-muted" style="font-size:.8rem;"></i>
              <?php endif; ?>
            </a>
          </div>
        </div>

        <!-- Recent Reports -->
        <div class="d-flex align-items-center justify-content-between mb-3">
          <p class="sv-section-title mb-0"><i class="bi bi-clock-history text-primary"></i> บันทึกงานล่าสุด</p>
          <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php" class="text-decoration-none small fw-bold text-primary">
            ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>

        <?php if (!empty($recent_reports)): ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($recent_reports as $rpt):
            $has_comment = !empty($rpt['supervisor_comment']);
          ?>
          <a href="<?= BASE_URL ?>/supervisor/view_daily_reports.php?report_id=<?= $rpt['id'] ?>" class="text-decoration-none">
            <div class="sv-recent-card hover-lift">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2.5">
                  <div class="sv-recent-avatar">
                    <?php if (!empty($rpt['profile_image']) && file_exists(__DIR__ . '/../uploads/avatars/' . $rpt['profile_image'])): ?>
                      <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($rpt['profile_image']) ?>?v=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                      <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="fw-bold text-dark" style="font-size:.92rem;"><?= htmlspecialchars($rpt['student_name']) ?></div>
                    <div class="text-muted small" style="font-size:.75rem;"><?= htmlspecialchars($rpt['student_code'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill bg-light text-secondary border fw-medium px-2.5 py-1" style="font-size:.73rem;">
                    <i class="bi bi-calendar3 text-primary me-1"></i><?= date('d/m/Y', strtotime($rpt['date_work'])) ?>
                  </span>
                  <div class="sv-action-circle"><i class="bi bi-arrow-right"></i></div>
                </div>
              </div>
              
              <!-- Details preview box -->
              <div class="sv-details-box mb-2">
                <?= htmlspecialchars(mb_substr($rpt['details'] ?? '-', 0, 90)) ?><?= mb_strlen($rpt['details'] ?? '') > 90 ? '…' : '' ?>
              </div>

              <!-- Status & Comment Badges -->
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <?php if ($has_comment): ?>
                    <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-3 py-1.5 fw-bold" style="font-size:.73rem;">
                      <i class="bi bi-check-circle-fill me-1"></i>คอมเมนต์แล้ว
                    </span>
                  <?php else: ?>
                    <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 px-3 py-1.5 fw-bold pulse-badge" style="font-size:.73rem;">
                      <i class="bi bi-chat-dots-fill me-1"></i>รอ feedback จากคุณ
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($rpt['image1']) || !empty($rpt['image2'])): ?>
                  <div class="d-flex align-items-center gap-1.5 ms-auto" onclick="event.stopPropagation();">
                    <?php if (!empty($rpt['image1'])): 
                      $rpt_img1 = get_report_image_url($rpt['image1']);
                    ?>
                      <img src="<?= $rpt_img1 ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;" onclick="viewImg('<?= $rpt_img1 ?>', 'ภาพ 1 - <?= htmlspecialchars($rpt['student_name'], ENT_QUOTES) ?>')" alt="ภาพ 1" onerror="handleReportImgError(this, '<?= htmlspecialchars($rpt['image1'], ENT_QUOTES) ?>')">
                    <?php endif; ?>
                    <?php if (!empty($rpt['image2'])): 
                      $rpt_img2 = get_report_image_url($rpt['image2']);
                    ?>
                      <img src="<?= $rpt_img2 ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;" onclick="viewImg('<?= $rpt_img2 ?>', 'ภาพ 2 - <?= htmlspecialchars($rpt['student_name'], ENT_QUOTES) ?>')" alt="ภาพ 2" onerror="handleReportImgError(this, '<?= htmlspecialchars($rpt['image2'], ENT_QUOTES) ?>')">
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5 bg-white rounded-4 border shadow-sm" style="border-color:rgba(0,0,0,.06)!important;">
          <i class="bi bi-journal-x fs-1 text-muted opacity-50 d-block mb-2"></i>
          <h6 class="fw-bold text-dark mb-1">ยังไม่มีรายการบันทึกงาน</h6>
          <p class="text-muted small mb-0">
            <?= $total_students === 0 ? 'ยังไม่มีนักเรียนที่ได้รับมอบหมาย กรุณาติดต่อเจ้าหน้าที่' : 'นักเรียนในดูแลยังไม่ได้ส่งบันทึกงานประจำวัน' ?>
          </p>
        </div>
        <?php endif; ?>

      </div>

      <!-- RIGHT: Profile + Info -->
      <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="sv-profile-card mb-4 hover-lift animate-fade-in delay-2">
          <?php 
            $avatar_path = !empty($u['profile_image']) ? __DIR__ . '/../uploads/avatars/' . $u['profile_image'] : '';
            if (!empty($u['profile_image']) && file_exists($avatar_path)): 
          ?>
            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($u['profile_image']) ?>?v=<?= time() ?>" alt="avatar" class="sv-avatar" onerror="this.style.display='none';">
          <?php else: ?>
            <div class="sv-avatar-placeholder">
              <i class="bi bi-person-fill"></i>
            </div>
          <?php endif; ?>
          <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($u['fullname']) ?></h5>
          <span class="badge rounded-pill fw-bold mb-3" style="background:rgba(14,165,233,.12);color:var(--sv-primary);font-size:.75rem;letter-spacing:.5px;">
            ผู้ดูแลการฝึกงาน
          </span>
          <div class="w-100 text-start">
            <?php if (!empty($u['company_name'])): ?>
            <div class="p-2.5 rounded-3 mb-2" style="background:#f0f9ff;border:1px solid rgba(14,165,233,.2);">
              <div class="fw-bold text-primary small mb-1" style="font-size:.78rem;">
                <i class="bi bi-building-gear me-1"></i>สถานที่ฝึกงาน / สถานประกอบการ:
              </div>
              <div class="fw-bold text-dark" style="font-size:.88rem;"><?= htmlspecialchars($u['company_name']) ?></div>
              <?php if (!empty($u['company_address'])): ?>
              <div class="text-muted small mt-0.5" style="font-size:.78rem;"><?= htmlspecialchars($u['company_address']) ?></div>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="p-2 rounded-3 mb-2 text-center" style="background:#fff7ed;border:1px dashed #fdba74;">
              <a href="/DVE_DATA_FULL/supervisor/profile.php" class="text-decoration-none text-warning fw-bold small" style="font-size:.78rem;">
                <i class="bi bi-exclamation-circle me-1"></i>ยังไม่ได้เลือกสถานที่ฝึกงาน (ตั้งค่า)
              </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($u['email'])): ?>
            <div class="d-flex align-items-center gap-2 mb-2 text-secondary" style="font-size:.85rem;">
              <i class="bi bi-envelope-fill text-primary"></i>
              <span><?= htmlspecialchars($u['email']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($u['phone'])): ?>
            <div class="d-flex align-items-center gap-2 mb-2 text-secondary" style="font-size:.85rem;">
              <i class="bi bi-phone-fill text-success"></i>
              <span><?= htmlspecialchars($u['phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($u['affiliation'])): ?>
            <div class="d-flex align-items-center gap-2 text-secondary" style="font-size:.85rem;">
              <i class="bi bi-building-fill text-info"></i>
              <span><?= htmlspecialchars($u['affiliation']) ?></span>
            </div>
            <?php endif; ?>
          </div>
          <a href="/DVE_DATA_FULL/supervisor/profile.php" class="btn btn-outline-primary rounded-pill mt-3 w-100 fw-bold" style="font-size:.85rem;">
            <i class="bi bi-pencil-square me-1"></i> แก้ไขโปรไฟล์
          </a>
        </div>

        <!-- Progress Summary -->
        <div class="sv-stat-card">
          <h6 class="fw-bold text-dark mb-3" style="font-size:.9rem;">
            <i class="bi bi-pie-chart-fill text-primary me-2"></i>ความคืบหน้า Feedback
          </h6>
          <?php if ($total_reports > 0):
            $pct = round(($total_commented / $total_reports) * 100);
          ?>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted small fw-bold">คอมเมนต์แล้ว</span>
            <span class="fw-bold small"><?= $pct ?>%</span>
          </div>
          <div class="progress rounded-pill mb-3" style="height:10px;">
            <div class="progress-bar rounded-pill" role="progressbar"
                 style="width:<?= $pct ?>%;background:linear-gradient(90deg,#0ea5e9,#14b8a6);"
                 aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <div class="d-flex justify-content-between text-muted small">
            <span><span class="fw-bold text-success"><?= $total_commented ?></span> คอมเมนต์แล้ว</span>
            <span><span class="fw-bold text-danger"><?= $total_uncommented ?></span> รอ feedback</span>
          </div>
          <?php else: ?>
          <p class="text-muted text-center small mb-0 py-2">ยังไม่มีข้อมูลบันทึกงาน</p>
          <?php endif; ?>
        </div>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
