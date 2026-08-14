<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/configdb.php';
require_login();

$u = current_user();
$user_id = (int)$u['id'];

// Handle mark-single-read via GET
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
}
// mark_all is now handled via AJAX (markAllInboxRead in JS)
// Legacy GET fallback kept for safety only
if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header('Location: notifications.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'unread', 'report_submitted', 'report_approved', 'report_rejected', 'schedule_update', 'announcement', 'evaluation', 'review_late'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where_extra = '';
if ($filter === 'unread') {
    $where_extra = "AND is_read = 0";
} elseif ($filter !== 'all') {
    $safe_filter = $conn->real_escape_string($filter);
    $where_extra = "AND type = '$safe_filter'";
}

$count_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id $where_extra");
$total_count = $count_res ? (int)$count_res->fetch_assoc()['cnt'] : 0;
$total_pages = max(1, ceil($total_count / $limit));

$notifs_res = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id $where_extra ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

$unread_count_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_total = $unread_count_res ? (int)$unread_count_res->fetch_assoc()['cnt'] : 0;

$type_map = [
    'info'                 => ['bi-info-circle-fill',        'text-primary bg-primary bg-opacity-10',   '#eff6ff', 'ทั่วไป'],
    'report_submitted'     => ['bi-send-fill',               'text-primary bg-primary bg-opacity-10',   '#f0f4ff', 'ส่งรายงาน'],
    'report_approved'      => ['bi-check-circle-fill',       'text-success bg-success bg-opacity-10',   '#f0fdf4', 'อนุมัติแล้ว'],
    'report_rejected'      => ['bi-exclamation-octagon-fill','text-danger bg-danger bg-opacity-10',     '#fff1f2', 'ส่งกลับ'],
    'report_late_reminder' => ['bi-clock-fill',              'text-warning bg-warning bg-opacity-10',   '#fffbeb', 'เตือนล่าช้า'],
    'review_late'          => ['bi-hourglass-split',         'text-secondary bg-secondary bg-opacity-10','#f8fafc', 'นิเทศล่าช้า'],
    'schedule_update'      => ['bi-calendar2-week-fill',     'text-purple bg-purple bg-opacity-10',     '#faf5ff', 'อัปเดตตาราง'],
    'evaluation'           => ['bi-trophy-fill',             'text-warning bg-warning bg-opacity-10',   '#fffbeb', 'ประเมินผล'],
    'announcement'         => ['bi-megaphone-fill',          'text-info bg-info bg-opacity-10',         '#f0fdff', 'ประกาศ'],
    'welcome'              => ['bi-stars',                   'text-primary bg-primary bg-opacity-10',   '#eff6ff', 'ยินดีต้อนรับ'],
];

$hide_welcome = true;
include __DIR__ . '/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .notif-inbox-wrap {
        max-width: 780px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }
    .notif-header-glass {
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(99,102,241,0.1);
        border-radius: 1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 32px rgba(99,102,241,0.07);
    }
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }
    .filter-tab {
        padding: 0.4rem 1rem;
        border-radius: 2rem;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        background: rgba(0,0,0,0.04);
        color: #64748b;
        transition: all 0.2s ease;
        border: 1.5px solid transparent;
    }
    .filter-tab:hover {
        background: rgba(99,102,241,0.08);
        color: var(--primary, #6366f1);
        border-color: rgba(99,102,241,0.2);
    }
    .filter-tab.active {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }
    .notif-item {
        background: white;
        border-radius: 1rem;
        border: 1px solid #f1f5f9;
        padding: 1rem 1.25rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }
    .notif-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.07);
        border-color: rgba(99,102,241,0.15);
        text-decoration: none;
        color: inherit;
    }
    .notif-item.unread {
        border-left: 3px solid #6366f1;
    }
    .notif-item.fade-out {
        opacity: 0;
        transform: translateX(30px);
    }
    .notif-item.collapse-item {
        max-height: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        border-width: 0 !important;
        opacity: 0;
        overflow: hidden;
    }
    .notif-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .notif-body { flex-grow: 1; min-width: 0; }
    .notif-title {
        font-weight: 700;
        font-size: 0.93rem;
        color: #1e293b;
        margin-bottom: 0.2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .notif-msg {
        font-size: 0.83rem;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 0.3rem;
    }
    .notif-time {
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 600;
    }
    .unread-dot {
        width: 8px;
        height: 8px;
        background: #6366f1;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 4px;
    }
    
    .notif-actions-wrap {
        position: absolute;
        bottom: 0.75rem;
        right: 0.75rem;
        display: flex;
        gap: 0.35rem;
        opacity: 0;
        transform: translateY(4px);
        transition: all 0.2s ease;
        z-index: 10;
    }
    .notif-item:hover .notif-actions-wrap {
        opacity: 1;
        transform: translateY(0);
    }
    .notif-action-btn {
        background: white;
        border: 1px solid rgba(0, 0, 0, 0.05);
        color: #64748b;
        font-size: 0.85rem;
        cursor: pointer;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .notif-action-btn:hover {
        transform: scale(1.1);
    }
    .notif-action-btn.btn-read:hover {
        background: #ecfdf5;
        color: #10b981;
        border-color: rgba(16,185,129,0.2);
    }
    .notif-action-btn.btn-delete:hover {
        background: #fff1f2;
        color: #f43f5e;
        border-color: rgba(244,63,94,0.2);
    }

    .empty-inbox {
        text-align: center;
        padding: 4rem 2rem;
        color: #94a3b8;
    }
    .pagination-wrap {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }
    .page-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none;
        background: white;
        color: #64748b;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    .page-btn.active {
        background: #6366f1;
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }
    .page-btn:hover:not(.active) {
        background: #f8fafc;
        color: #6366f1;
        border-color: rgba(99,102,241,0.2);
    }
</style>

<div class="notif-inbox-wrap animate-fade-in">

    <!-- Header -->
    <div class="notif-header-glass">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-bell-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">การแจ้งเตือนทั้งหมด</h5>
                    <span class="small text-muted">
                        <?php if ($unread_total > 0): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill fw-bold"><?= $unread_total ?> ยังไม่ได้อ่าน</span>
                        <?php else: ?>
                            อ่านทั้งหมดแล้ว ✓
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php if ($unread_total > 0): ?>
                <button type="button" onclick="markAllInboxRead(this);" class="btn btn-sm btn-light fw-bold rounded-pill px-3 text-primary" id="inboxMarkAllBtn">
                    <i class="bi bi-check-all me-1"></i>อ่านทั้งหมด
                </button>
            <?php endif; ?>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="notifications.php?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i>ทั้งหมด
                <span class="ms-1">(<?= $total_count ?>)</span>
            </a>
            <a href="notifications.php?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>ยังไม่อ่าน
            </a>
            <a href="notifications.php?filter=report_approved" class="filter-tab <?= $filter === 'report_approved' ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill me-1 text-success"></i>ผ่านแล้ว
            </a>
            <a href="notifications.php?filter=report_rejected" class="filter-tab <?= $filter === 'report_rejected' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-octagon-fill me-1 text-danger"></i>ส่งกลับ
            </a>
            <a href="notifications.php?filter=schedule_update" class="filter-tab <?= $filter === 'schedule_update' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week-fill me-1" style="color:#7c3aed;"></i>ตาราง
            </a>
            <a href="notifications.php?filter=announcement" class="filter-tab <?= $filter === 'announcement' ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill me-1 text-info"></i>ประกาศ
            </a>
        </div>
    </div>

    <!-- Notification List -->
    <?php if ($notifs_res && $notifs_res->num_rows > 0): ?>
        <?php while ($n = $notifs_res->fetch_assoc()):
            $type = !empty($n['type']) ? $n['type'] : 'info';
            $tm = $type_map[$type] ?? $type_map['info'];
            $icon        = $tm[0];
            $color_class = $tm[1];
            $bg_color    = $tm[2];
            $is_unread   = $n['is_read'] == 0;

            $diff_sec = time() - strtotime($n['created_at']);
            if ($diff_sec < 60)         $time_str = 'เมื่อกี้';
            elseif ($diff_sec < 3600)   $time_str = floor($diff_sec/60) . ' นาทีที่แล้ว';
            elseif ($diff_sec < 86400)  $time_str = floor($diff_sec/3600) . ' ชั่วโมงที่แล้ว';
            elseif ($diff_sec < 604800) $time_str = floor($diff_sec/86400) . ' วันที่แล้ว';
            else                        $time_str = date('d/m/', strtotime($n['created_at'])) . (date('Y', strtotime($n['created_at']))+543);

            $link_url = BASE_URL . '/includes/click_notification.php?id=' . $n['id'];
        ?>
            <div class="notif-item <?= $is_unread ? 'unread' : '' ?>" data-id="<?= $n['id'] ?>"
                 style="<?= $is_unread ? 'background: ' . $bg_color . ';' : '' ?>"
                 onclick="window.location.href='<?= $link_url ?>'">
                <div class="notif-icon-wrap <?= $color_class ?>">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-title">
                        <?php if ($is_unread): ?><span class="unread-dot"></span><?php endif; ?>
                        <?= htmlspecialchars($n['title']) ?>
                    </div>
                    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-time"><i class="bi bi-clock me-1"></i><?= $time_str ?></div>
                </div>

                <div class="notif-actions-wrap">
                    <?php if ($is_unread): ?>
                        <button type="button" class="notif-action-btn btn-read" 
                                onclick="event.stopPropagation(); event.preventDefault(); markInboxRead(this, <?= $n['id'] ?>);" 
                                title="ทำเครื่องหมายว่าอ่านแล้ว">
                            <i class="bi bi-check2"></i>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="notif-action-btn btn-delete" 
                            onclick="event.stopPropagation(); event.preventDefault(); deleteInboxNotif(this, <?= $n['id'] ?>);" 
                            title="ลบการแจ้งเตือน">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        <?php endwhile; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="notifications.php?filter=<?= $filter ?>&page=<?= $p ?>"
                   class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-inbox animate-fade-in">
            <i class="bi bi-bell-slash" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.4;"></i>
            <h6 class="fw-bold text-secondary">ไม่มีการแจ้งเตือนในขณะนี้</h6>
            <p class="small text-muted">เมื่อมีการเคลื่อนไหวจากระบบ จะปรากฏที่นี่</p>
        </div>
    <?php endif; ?>

</div>

<script>
function refreshInboxPage() {
    window.location.reload();
}

function checkEmptyInbox() {
    const list = document.querySelector('.notif-inbox-wrap');
    const items = list.querySelectorAll('.notif-item');
    if (items.length === 0) {
        const pagination = list.querySelector('.pagination-wrap');
        if (pagination) pagination.remove();
        
        let emptyDiv = list.querySelector('.empty-inbox');
        if (!emptyDiv) {
            emptyDiv = document.createElement('div');
            emptyDiv.className = 'empty-inbox animate-fade-in';
            emptyDiv.innerHTML = `
                <i class="bi bi-bell-slash" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.4;"></i>
                <h6 class="fw-bold text-secondary">ไม่มีการแจ้งเตือนในขณะนี้</h6>
                <p class="small text-muted">เมื่อมีการเคลื่อนไหวจากระบบ จะปรากฏที่นี่</p>
            `;
            const header = list.querySelector('.notif-header-glass');
            header.parentNode.insertBefore(emptyDiv, header.nextSibling);
        }
    }
}

function markInboxRead(btn, id) {
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
            // Update header bell unread count badge
            const badge = document.getElementById('notificationCountBadge');
            if (data.unread_count > 0) {
                if (badge) badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
            } else {
                if (badge) badge.remove();
                const markAllBtn = document.getElementById('markAllReadLink');
                if (markAllBtn) markAllBtn.style.display = 'none';
            }

            // Sync with header dropdown list
            const dropdownItems = document.querySelectorAll('#dropdownNotificationList .dropdown-item');
            dropdownItems.forEach(item => {
                if (item.getAttribute('href').includes('id=' + id)) {
                    item.style.backgroundColor = '';
                    const dot = item.querySelector('.bg-primary.rounded-circle');
                    if (dot) dot.remove();
                    const markBtn = item.querySelector('.mark-read-dropdown-btn');
                    if (markBtn) markBtn.remove();
                }
            });

            // Update page count and badge
            const unreadBadge = document.querySelector('.notif-header-glass .badge');
            if (unreadBadge) {
                if (data.unread_count > 0) {
                    unreadBadge.textContent = data.unread_count + ' ยังไม่ได้อ่าน';
                } else {
                    unreadBadge.parentElement.innerHTML = 'อ่านทั้งหมดแล้ว ✓';
                    const inboxMarkAll = document.getElementById('inboxMarkAllBtn');
                    if (inboxMarkAll) inboxMarkAll.remove();
                }
            }

            // Animate transition on this item
            const item = btn.closest('.notif-item');
            if (item) {
                const filter = '<?= $filter ?>';
                if (filter === 'unread') {
                    item.classList.add('fade-out');
                    setTimeout(() => {
                        item.classList.add('collapse-item');
                        setTimeout(() => {
                            item.remove();
                            checkEmptyInbox();
                        }, 300);
                    }, 200);
                } else {
                    item.classList.remove('unread');
                    item.style.backgroundColor = '';
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    btn.remove();
                }
            }
        }
    })
    .catch(err => console.error('Error marking inbox read:', err));
}

function markAllInboxRead(btn) {
    const formData = new FormData();
    formData.append('action', 'mark_all_read');
    
    fetch('<?= BASE_URL ?>/includes/ajax_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update nav bell badge
            const badge = document.getElementById('notificationCountBadge');
            if (badge) badge.remove();
            
            // Remove unread indicators from dropdown
            const dropdownItems = document.querySelectorAll('#dropdownNotificationList .dropdown-item');
            dropdownItems.forEach(item => {
                item.style.backgroundColor = '';
                const dot = item.querySelector('.bg-primary.rounded-circle');
                if (dot) dot.remove();
                const markBtn = item.querySelector('.mark-read-dropdown-btn');
                if (markBtn) markBtn.remove();
            });
            const markAllBtn = document.getElementById('markAllReadLink');
            if (markAllBtn) markAllBtn.style.display = 'none';

            // Update Inbox page elements
            const unreadBadge = document.querySelector('.notif-header-glass .badge');
            if (unreadBadge) {
                unreadBadge.parentElement.innerHTML = 'อ่านทั้งหมดแล้ว ✓';
            }
            if (btn) btn.remove();

            const filter = '<?= $filter ?>';
            const items = document.querySelectorAll('.notif-item');
            
            if (filter === 'unread') {
                items.forEach((item, idx) => {
                    setTimeout(() => {
                        item.classList.add('fade-out');
                        setTimeout(() => {
                            item.classList.add('collapse-item');
                            setTimeout(() => {
                                item.remove();
                                checkEmptyInbox();
                            }, 300);
                        }, 200);
                    }, idx * 50);
                });
            } else {
                items.forEach(item => {
                    item.classList.remove('unread');
                    item.style.backgroundColor = '';
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    const readBtn = item.querySelector('.btn-read');
                    if (readBtn) readBtn.remove();
                });
            }
        }
    })
    .catch(err => console.error('Error marking all inbox read:', err));
}

function deleteInboxNotif(btn, id) {
    const performDelete = () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        fetch('<?= BASE_URL ?>/includes/ajax_notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update header bell unread count badge
                const badge = document.getElementById('notificationCountBadge');
                if (data.unread_count > 0) {
                    if (badge) badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                } else {
                    if (badge) badge.remove();
                    const markAllBtn = document.getElementById('markAllReadLink');
                    if (markAllBtn) markAllBtn.style.display = 'none';
                }

                // Sync with header dropdown list
                const dropdownItems = document.querySelectorAll('#dropdownNotificationList .dropdown-item');
                dropdownItems.forEach(item => {
                    if (item.getAttribute('href').includes('id=' + id)) {
                        item.remove();
                    }
                });
                
                const dropdownList = document.getElementById('dropdownNotificationList');
                if (dropdownList && dropdownList.children.length === 0) {
                    dropdownList.innerHTML = `
                        <div class="p-4 text-center text-muted" id="emptyNotificationPlaceholder">
                            <i class="bi bi-bell-slash fs-4 d-block mb-2 text-muted"></i>
                            <span class="small">ไม่มีการแจ้งเตือนในขณะนี้</span>
                        </div>`;
                }

                // Update page count and badge
                const unreadBadge = document.querySelector('.notif-header-glass .badge');
                if (unreadBadge) {
                    if (data.unread_count > 0) {
                        unreadBadge.textContent = data.unread_count + ' ยังไม่ได้อ่าน';
                    } else {
                        unreadBadge.parentElement.innerHTML = 'อ่านทั้งหมดแล้ว ✓';
                        const inboxMarkAll = document.getElementById('inboxMarkAllBtn');
                        if (inboxMarkAll) inboxMarkAll.remove();
                    }
                }

                // Animate transition on this item
                const item = btn.closest('.notif-item');
                if (item) {
                    item.classList.add('fade-out');
                    setTimeout(() => {
                        item.classList.add('collapse-item');
                        setTimeout(() => {
                            item.remove();
                            checkEmptyInbox();
                        }, 300);
                    }, 200);
                }

                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'ลบการแจ้งเตือนเรียบร้อยแล้ว'
                    });
                }
            }
        })
        .catch(err => console.error('Error deleting notification:', err));
    };

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'ต้องการลบการแจ้งเตือนนี้หรือไม่?',
            text: "เมื่อลบแล้วจะไม่สามารถกู้คืนได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยันการลบ',
            cancelButtonText: 'ยกเลิก',
            background: 'rgba(255, 255, 255, 0.95)',
            backdrop: 'rgba(99, 102, 241, 0.1)',
            customClass: {
                popup: 'rounded-4 shadow'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                performDelete();
            }
        });
    } else {
        if (confirm('คุณต้องการลบการแจ้งเตือนนี้หรือไม่?')) {
            performDelete();
        }
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
