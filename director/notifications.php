<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['director', 'admin']);

include __DIR__ . '/../includes/header.php';

$u = current_user();
$uid = (int)$u['id'];

// Fetch user's own notifications after header.php has run migrations/seeding
$res = $conn->query("
    SELECT * FROM notifications
    WHERE user_id = $uid
    ORDER BY created_at DESC
");
?>
<link rel="stylesheet" href="../includes/dashboard.css">
<style>
.notifications-container {
    max-width: 900px;
    margin: 0 auto;
}
.notification-card {
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.notif-item {
    border-bottom: 1px solid #f1f5f9;
    padding: 1.5rem;
    transition: all 0.2s ease;
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}
.notif-item:last-child {
    border-bottom: none;
}
.notif-item.unread {
    background: rgba(40, 167, 69, 0.04);
    border-left: 4px solid #28a745;
}
.notif-item:hover {
    background: rgba(0, 0, 0, 0.01);
}
.notif-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.notif-icon.info {
    background: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}
.notif-icon.schedule_update {
    background: rgba(253, 126, 20, 0.1);
    color: #fd7e14;
}
.notif-icon.report_delay {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}
.notif-icon.evaluation {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}
.notif-icon.default {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}
.notif-time {
    font-size: 0.8rem;
    color: #94a3b8;
}
.notif-btn {
    border-radius: 50rem;
    padding: 0.35rem 1rem;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
}
</style>

<div class="dashboard-page">
    <div class="container py-4">
        <div class="notifications-container">
            
            <div class="dashboard-hero d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="mb-1 text-white" style="color: #ffffff !important;"><i class="bi bi-bell-fill me-2"></i>การแจ้งเตือนของฉัน</h1>
                    <p class="mb-0">ศูนย์รวมรายการอัปเดตและแจ้งเตือนระบบที่เกี่ยวข้องกับคุณโดยเฉพาะ</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="../includes/mark_all_read.php" class="btn btn-light rounded-pill px-4 fw-semibold shadow-sm text-success">
                        <i class="bi bi-check-all me-1"></i> อ่านทั้งหมดแล้ว
                    </a>
                </div>
            </div>

            <div class="card notification-card">
                <?php if ($res && $res->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($n = $res->fetch_assoc()): 
                            $is_unread = !(int)$n['is_read'];
                            $type = isset($n['type']) ? $n['type'] : 'info';
                            $target_url = !empty($n['action_url']) ? $n['action_url'] : (!empty($n['link']) ? $n['link'] : null);
                            
                            // Map icon
                            $icon_class = "bi bi-info-circle-fill";
                            if ($type === 'schedule_update') $icon_class = "bi bi-calendar-event-fill";
                            elseif ($type === 'report_delay') $icon_class = "bi bi-exclamation-triangle-fill";
                            elseif ($type === 'evaluation') $icon_class = "bi bi-star-fill";
                        ?>
                            <div class="notif-item <?= $is_unread ? 'unread' : '' ?>">
                                <div class="notif-icon <?= $type ?>">
                                    <i class="<?= $icon_class ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                        <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;"><?= e($n['title']) ?></h5>
                                        <span class="notif-time">
                                            <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i น.', strtotime($n['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary mb-2" style="font-size: 0.95rem; line-height: 1.5;"><?= e($n['message']) ?></p>
                                    
                                    <?php if ($target_url): ?>
                                        <a href="../includes/click_notification.php?id=<?= (int)$n['id'] ?>" class="btn btn-outline-success notif-btn btn-sm">
                                            <i class="bi bi-arrow-right-circle me-1"></i>ดูรายละเอียด
                                        </a>
                                    <?php elseif ($is_unread): ?>
                                        <a href="../includes/click_notification.php?id=<?= (int)$n['id'] ?>" class="btn btn-light notif-btn btn-sm text-secondary">
                                            <i class="bi bi-check-circle me-1"></i>ทำเครื่องหมายว่าอ่านแล้ว
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 3.5rem;">
                            <i class="bi bi-bell-slash"></i>
                        </div>
                        <h4 class="text-dark fw-bold">ไม่มีการแจ้งเตือน</h4>
                        <p class="text-muted">คุณได้รับการอัปเดตข้อมูลครบถ้วนทั้งหมดแล้วในขณะนี้</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
