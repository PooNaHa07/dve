<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

$student_id = (int)$_SESSION['user_id'];

$flash_msg  = '';
$flash_type = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $flash_msg = 'ลบรายการเรียบร้อยแล้ว';
} elseif (isset($_GET['err'])) {
    $flash_type = 'danger';
    if ($_GET['err'] === 'delete_failed')  $flash_msg = 'ไม่พบรายการ หรือคุณไม่มีสิทธิ์ลบรายการนี้';
    elseif ($_GET['err'] === 'invalid_id') $flash_msg = 'รหัสรายการไม่ถูกต้อง';
}

$sql = "SELECT * FROM daily_reports WHERE student_id = $student_id ORDER BY date_work DESC";
$res = $conn->query($sql);
$all_reports = [];
if ($res) { while ($r = $res->fetch_assoc()) $all_reports[] = $r; }

$count_total    = count($all_reports);
$count_pending  = count(array_filter($all_reports, fn($r) => $r['status'] === 'pending'));
$count_approved = count(array_filter($all_reports, fn($r) => $r['status'] === 'approved'));
$count_rejected = count(array_filter($all_reports, fn($r) => $r['status'] === 'rejected'));

$allowed_filters = ['all', 'rejected', 'approved', 'pending'];
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters)) ? $_GET['filter'] : 'all';

$thai_months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
                7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/student-premium.css">
<style>
.vr-page{min-height:100vh;background:#f8fafc}
.vr-hero{background:linear-gradient(135deg,#6366f1 0%,#4f46e5 40%,#7c3aed 100%);border-radius:0 0 2.5rem 2.5rem;padding:2.5rem 0 5rem;position:relative;overflow:hidden}
.vr-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23fff' fill-opacity='.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E")}
.vr-hero-blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.25}
.vr-hero-blob-1{width:300px;height:300px;background:#a78bfa;top:-80px;right:-60px}
.vr-hero-blob-2{width:200px;height:200px;background:#38bdf8;bottom:-40px;left:5%}
.hero-stat{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:50px;padding:.4rem 1rem;font-size:.8rem;font-weight:700;color:#fff;display:inline-flex;align-items:center;gap:.4rem}
.vr-toolbar{background:#fff;border-radius:1.5rem;box-shadow:0 8px 40px rgba(99,102,241,.12);padding:1.25rem 1.5rem;margin-top:-2.5rem;position:relative;z-index:10}
.vr-search{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:50px;padding:.6rem 2.5rem .6rem 3rem;width:100%;font-size:.9rem;outline:none;transition:border-color .2s,box-shadow .2s}
.vr-search:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12);background:#fff}
.vr-search-wrap{position:relative}
.vr-search-icon{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;pointer-events:none}
.vr-clear-btn{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:#e2e8f0;border:none;border-radius:50%;width:26px;height:26px;display:none;align-items:center;justify-content:center;color:#64748b;font-size:.75rem;cursor:pointer;transition:background .2s}
.vr-clear-btn:hover{background:#cbd5e1}
.vr-clear-btn.show{display:flex}
.vr-filter-tabs{display:flex;gap:.5rem;flex-wrap:wrap}
.vr-tab{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:50px;font-size:.8rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:all .2s;background:#f1f5f9;color:#64748b;white-space:nowrap}
.vr-tab:hover{background:#e2e8f0}
.vr-tab.active-all{background:#eef2ff;color:#4f46e5;border-color:#c7d2fe}
.vr-tab.active-pending{background:#fffbeb;color:#d97706;border-color:#fde68a}
.vr-tab.active-approved{background:#f0fdf4;color:#16a34a;border-color:#bbf7d0}
.vr-tab.active-rejected{background:#fef2f2;color:#dc2626;border-color:#fecaca}
.vr-tab .tab-badge{min-width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;background:rgba(0,0,0,.08)}
.vr-card{background:#fff;border-radius:1.25rem;border:1.5px solid #e2e8f0;overflow:hidden;transition:transform .3s cubic-bezier(.16,1,.3,1),box-shadow .3s,border-color .3s;position:relative;animation:cardIn .4s ease both}
.vr-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(99,102,241,.1);border-color:#c7d2fe}
.vr-card-accent{position:absolute;left:0;top:0;bottom:0;width:5px;border-radius:4px 0 0 4px}
.accent-approved{background:linear-gradient(180deg,#22c55e,#16a34a)}
.accent-pending{background:linear-gradient(180deg,#f59e0b,#d97706)}
.accent-rejected{background:linear-gradient(180deg,#ef4444,#dc2626)}
.vr-date-badge{width:70px;height:70px;border-radius:1rem;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.1)}
.badge-approved{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff}
.badge-pending{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.badge-rejected{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff}
.vr-status{display:inline-flex;align-items:center;gap:.3rem;padding:4px 12px;border-radius:50px;font-size:.72rem;font-weight:700}
.vr-status-approved{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.vr-status-pending{background:#fffbeb;color:#d97706;border:1px solid #fde68a}
.vr-status-rejected{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.vr-details-text{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.6}
.vr-details-text.expanded{-webkit-line-clamp:unset;overflow:visible}
.vr-toggle-btn{background:none;border:none;padding:0;font-size:.78rem;color:#6366f1;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;margin-top:4px}
.vr-toggle-btn:hover{text-decoration:underline}
.vr-thumb{width:64px;height:64px;border-radius:.75rem;object-fit:cover;cursor:pointer;border:2px solid #e2e8f0;transition:transform .2s,border-color .2s}
.vr-thumb:hover{transform:scale(1.08);border-color:#6366f1}
.vr-comment{border-radius:.75rem;padding:.75rem 1rem;font-size:.82rem;line-height:1.6}
.vr-comment-teacher{background:#fffbeb;border:1.5px solid #fde68a}
.vr-comment-sv{background:#f0f9ff;border:1.5px solid #bae6fd}
.vr-comment-label{font-weight:700;font-size:.75rem;margin-bottom:4px}
.vr-comment-teacher .vr-comment-label{color:#92400e}
.vr-comment-sv .vr-comment-label{color:#0369a1}
.vr-btn-edit{background:#f1f5f9;color:#475569;border:none;border-radius:50px;padding:.35rem 1rem;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;transition:background .2s,color .2s;text-decoration:none}
.vr-btn-edit:hover{background:#e2e8f0;color:#1e293b}
.vr-btn-del{background:#fef2f2;color:#dc2626;border:none;border-radius:50px;padding:.35rem .75rem;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;transition:background .2s;cursor:pointer}
.vr-btn-del:hover{background:#fee2e2}
.vr-empty{text-align:center;padding:4rem 2rem;background:#fff;border-radius:1.5rem;border:2px dashed #e2e8f0}
.vr-empty-icon{width:80px;height:80px;border-radius:50%;background:#f1f5f9;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;color:#94a3b8;margin-bottom:1rem}
.vr-no-results{display:none}
@keyframes cardIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
</style>

<div class="vr-page">
<div class="vr-hero">
    <div class="vr-hero-blob vr-hero-blob-1"></div>
    <div class="vr-hero-blob vr-hero-blob-2"></div>
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="../roles/student.php" class="text-white text-decoration-none fw-bold d-inline-flex align-items-center gap-2" style="font-size:.88rem;opacity:.8">
                <i class="bi bi-arrow-left-circle-fill fs-5"></i> กลับหน้าหลัก
            </a>
            <div class="d-flex gap-2">
                <a href="print_reports.php" target="_blank" class="btn btn-sm rounded-pill fw-bold px-3" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);font-size:.8rem">
                    <i class="bi bi-printer-fill me-1"></i> พิมพ์
                </a>
                <a href="submit_report.php" class="btn btn-sm rounded-pill fw-bold px-4" style="background:#fff;color:#4f46e5;font-size:.8rem">
                    <i class="bi bi-plus-lg me-1"></i> บันทึกงานวันนี้
                </a>
            </div>
        </div>
        <h1 class="fw-extrabold text-white mb-2" style="font-size:clamp(1.6rem,4vw,2.4rem);letter-spacing:-.5px">
            <i class="bi bi-journal-richtext me-2 opacity-75"></i>ประวัติการบันทึกงาน
        </h1>
        <p class="text-white mb-4" style="opacity:.75;font-size:.9rem">ติดตามและตรวจสอบรายการปฏิบัติงานย้อนหลังของคุณ</p>
        <div class="d-flex flex-wrap gap-2">
            <span class="hero-stat"><i class="bi bi-collection-fill"></i> <?= $count_total ?> รายการ</span>
            <?php if ($count_approved > 0): ?>
            <span class="hero-stat" style="background:rgba(34,197,94,.25);border-color:rgba(34,197,94,.4)"><i class="bi bi-check-circle-fill"></i> อนุมัติ <?= $count_approved ?></span>
            <?php endif; ?>
            <?php if ($count_pending > 0): ?>
            <span class="hero-stat" style="background:rgba(245,158,11,.25);border-color:rgba(245,158,11,.4)"><i class="bi bi-clock-fill"></i> รอตรวจ <?= $count_pending ?></span>
            <?php endif; ?>
            <?php if ($count_rejected > 0): ?>
            <span class="hero-stat" style="background:rgba(239,68,68,.25);border-color:rgba(239,68,68,.4)"><i class="bi bi-exclamation-circle-fill"></i> ต้องแก้ไข <?= $count_rejected ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if ($flash_msg): ?>
    <div class="alert alert-<?= $flash_type ?> border-0 shadow-sm d-flex align-items-center gap-3 rounded-4 mt-4 mb-0">
        <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?> fs-5"></i>
        <div class="fw-bold"><?= htmlspecialchars($flash_msg) ?></div>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="vr-toolbar mt-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="vr-search-wrap">
                    <i class="bi bi-search vr-search-icon"></i>
                    <input type="text" id="vrSearch" class="vr-search" placeholder="ค้นหา... (รายละเอียด, ปัญหา, วันที่)">
                    <button class="vr-clear-btn" id="vrClearBtn" onclick="clearSearch()" title="ล้างการค้นหา"><i class="bi bi-x"></i></button>
                </div>
            </div>
            <div class="col-md-7">
                <div class="vr-filter-tabs justify-content-md-end">
                    <button class="vr-tab active-all" data-filter="all" onclick="setFilter('all',this)">
                        <i class="bi bi-collection-fill"></i> ทั้งหมด <span class="tab-badge"><?= $count_total ?></span>
                    </button>
                    <button class="vr-tab" data-filter="pending" onclick="setFilter('pending',this)">
                        <i class="bi bi-clock-fill"></i> รอตรวจ <span class="tab-badge"><?= $count_pending ?></span>
                    </button>
                    <button class="vr-tab" data-filter="approved" onclick="setFilter('approved',this)">
                        <i class="bi bi-check-circle-fill"></i> อนุมัติ <span class="tab-badge"><?= $count_approved ?></span>
                    </button>
                    <button class="vr-tab" data-filter="rejected" onclick="setFilter('rejected',this)">
                        <i class="bi bi-exclamation-circle-fill"></i> แก้ไข <span class="tab-badge"><?= $count_rejected ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($all_reports)): ?>
    <div id="vrList" class="d-flex flex-column gap-3">
    <?php foreach ($all_reports as $idx => $row):
        $d = new DateTime($row['date_work']);
        $day = $d->format('d');
        $month = $thai_months[(int)$d->format('n')];
        $year  = (int)$d->format('Y') + 543;
        $status = $row['status'];
        $accentClass = $status === 'approved' ? 'accent-approved' : ($status === 'rejected' ? 'accent-rejected' : 'accent-pending');
        $badgeClass  = $status === 'approved' ? 'badge-approved'  : ($status === 'rejected' ? 'badge-rejected'  : 'badge-pending');
        $statusClass = $status === 'approved' ? 'vr-status-approved' : ($status === 'rejected' ? 'vr-status-rejected' : 'vr-status-pending');
        $statusIcon  = $status === 'approved' ? 'bi-check-circle-fill' : ($status === 'rejected' ? 'bi-exclamation-circle-fill' : 'bi-clock-fill');
        $statusLabel = $status === 'approved' ? 'อนุมัติแล้ว' : ($status === 'rejected' ? 'ต้องแก้ไข' : 'รอนิเทศก์ตรวจ');
        $details  = htmlspecialchars($row['details']);
        $problems = htmlspecialchars($row['problems'] ?? '');
    ?>
    <div class="vr-card"
         data-status="<?= $status ?>"
         data-search="<?= mb_strtolower($details . ' ' . $problems . ' ' . $day . '/' . $month . '/' . $year) ?>"
         style="animation-delay:<?= min($idx * 0.05, 0.5) ?>s">
        <div class="vr-card-accent <?= $accentClass ?>"></div>
        <div class="p-4 ps-5">
            <div class="row g-3 align-items-start">
                <!-- Date Badge -->
                <div class="col-auto">
                    <div class="vr-date-badge <?= $badgeClass ?>">
                        <span style="font-size:1.4rem;line-height:1"><?= $day ?></span>
                        <span style="font-size:.65rem;opacity:.9"><?= $month ?> <?= $year ?></span>
                    </div>
                </div>
                <!-- Content -->
                <div class="col">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="vr-status <?= $statusClass ?>">
                            <i class="bi <?= $statusIcon ?>"></i> <?= $statusLabel ?>
                        </span>
                        <?php if (!empty($row['supervisor_comment'])): ?>
                        <span class="vr-status" style="background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd">
                            <i class="bi bi-person-workspace"></i> มี Feedback
                        </span>
                        <?php endif; ?>
                        <span class="text-muted" style="font-size:.72rem">
                            <i class="bi bi-clock me-1"></i>ส่งเมื่อ <?= date('H:i', strtotime($row['created_at'])) ?> น.
                        </span>
                    </div>

                    <div class="vr-details-text text-secondary small mb-1" id="det-<?= $row['id'] ?>"><?= $details ?></div>
                    <?php if (mb_strlen($row['details']) > 120): ?>
                    <button class="vr-toggle-btn" id="togbtn-<?= $row['id'] ?>" onclick="toggleDetail(<?= $row['id'] ?>)">
                        <i class="bi bi-chevron-down"></i> อ่านเพิ่มเติม
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($row['problems'])): ?>
                    <div class="mt-2">
                        <span class="badge rounded-pill fw-bold px-3 py-1" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;font-size:.72rem">
                            <i class="bi bi-lightning-fill me-1"></i> ปัญหา: <?= $problems ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($row['image1']) || !empty($row['image2'])): ?>
                    <div class="d-flex gap-2 mt-3">
                        <?php if (!empty($row['image1'])): 
                            $vr_img1 = get_report_image_url($row['image1']);
                        ?>
                        <img src="<?= $vr_img1 ?>" class="vr-thumb" onclick="zoomImg(this.src,'ภาพที่ 1')" alt="ภาพที่ 1" onerror="handleReportImgError(this, '<?= htmlspecialchars($row['image1'], ENT_QUOTES) ?>')">
                        <?php endif; ?>
                        <?php if (!empty($row['image2'])): 
                            $vr_img2 = get_report_image_url($row['image2']);
                        ?>
                        <img src="<?= $vr_img2 ?>" class="vr-thumb" onclick="zoomImg(this.src,'ภาพที่ 2')" alt="ภาพที่ 2" onerror="handleReportImgError(this, '<?= htmlspecialchars($row['image2'], ENT_QUOTES) ?>')">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($row['teacher_comment'])): ?>
                    <div class="vr-comment vr-comment-teacher mt-3">
                        <div class="vr-comment-label"><i class="bi bi-person-badge-fill me-1"></i> ความคิดเห็นจากอาจารย์/ครูนิเทศก์:</div>
                        <div class="text-dark" style="white-space:pre-line"><?= htmlspecialchars($row['teacher_comment']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($row['supervisor_comment'])): ?>
                    <div class="vr-comment vr-comment-sv mt-2">
                        <div class="vr-comment-label"><i class="bi bi-person-workspace me-1"></i> Feedback จากผู้ดูแลการฝึกงาน (Supervisor):</div>
                        <div class="text-dark" style="white-space:pre-line"><?= htmlspecialchars($row['supervisor_comment']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Actions -->
                <div class="col-auto d-flex flex-column gap-2 align-items-end">
                    <a href="edit_report.php?id=<?= $row['id'] ?>" class="vr-btn-edit">
                        <i class="bi bi-pencil-square"></i> แก้ไข
                    </a>
                    <button onclick="confirmDelete(<?= $row['id'] ?>)" class="vr-btn-del">
                        <i class="bi bi-trash3"></i> ลบ
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="vr-no-results vr-empty" id="vrNoResults">
        <div class="vr-empty-icon"><i class="bi bi-search"></i></div>
        <h5 class="fw-bold mb-1">ไม่พบรายการที่ค้นหา</h5>
        <p class="text-muted small mb-3">ลองเปลี่ยนคำค้นหาหรือล้างตัวกรอง</p>
        <button onclick="clearSearch()" class="btn btn-primary rounded-pill px-4">ล้างการค้นหา</button>
    </div>

    <?php else: ?>
    <div class="vr-empty mt-4">
        <div class="vr-empty-icon"><i class="bi bi-journal-x"></i></div>
        <h4 class="fw-bold mb-2">ยังไม่มีประวัติการบันทึกงาน</h4>
        <p class="text-secondary mb-4">เริ่มต้นบันทึกการปฏิบัติงานวันนี้เพื่อเก็บเป็นประวัติของคุณ</p>
        <a href="submit_report.php" class="btn btn-primary px-5 py-2 rounded-pill shadow fw-bold">
            <i class="bi bi-plus-lg me-2"></i>บันทึกงานวันแรก
        </a>
    </div>
    <?php endif; ?>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentFilter = '<?= $filter ?>', currentQuery = '';

function setFilter(f, btn) {
    currentFilter = f;
    document.querySelectorAll('.vr-tab').forEach(t => {
        t.className = 'vr-tab' + (t.dataset.filter === f ? ' active-' + f : '');
    });
    applyFilters();
}

document.getElementById('vrSearch').addEventListener('input', function() {
    currentQuery = this.value.trim().toLowerCase();
    document.getElementById('vrClearBtn').classList.toggle('show', currentQuery.length > 0);
    applyFilters();
});

function clearSearch() {
    document.getElementById('vrSearch').value = '';
    currentQuery = '';
    document.getElementById('vrClearBtn').classList.remove('show');
    applyFilters();
    document.getElementById('vrSearch').focus();
}

function applyFilters() {
    const cards = document.querySelectorAll('#vrList .vr-card');
    let visible = 0;
    cards.forEach(card => {
        const sm = currentFilter === 'all' || card.dataset.status === currentFilter;
        const qm = !currentQuery || (card.dataset.search || '').includes(currentQuery);
        card.style.display = (sm && qm) ? '' : 'none';
        if (sm && qm) visible++;
    });
    const nr = document.getElementById('vrNoResults');
    if (nr) nr.style.display = visible === 0 && cards.length > 0 ? 'block' : 'none';
}

(function() {
    const f = new URLSearchParams(location.search).get('filter') || 'all';
    const b = document.querySelector('[data-filter="' + f + '"]');
    if (b) setFilter(f, b);
})();

function toggleDetail(id) {
    const el = document.getElementById('det-' + id);
    const btn = document.getElementById('togbtn-' + id);
    const exp = el.classList.toggle('expanded');
    btn.innerHTML = exp ? '<i class="bi bi-chevron-up"></i> ย่อ' : '<i class="bi bi-chevron-down"></i> อ่านเพิ่มเติม';
}

function confirmDelete(id) {
    Swal.fire({
        title:'ยืนยันการลบ?', text:'ข้อมูลนี้จะถูกลบออกจากระบบอย่างถาวร!', icon:'warning',
        showCancelButton:true, confirmButtonColor:'#ef4444', cancelButtonColor:'#64748b',
        confirmButtonText:'ใช่, ลบเลย', cancelButtonText:'ยกเลิก', reverseButtons:true,
        customClass:{popup:'rounded-4'}
    }).then(r => { if (r.isConfirmed) location.href = 'delete_report.php?id=' + id; });
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

function zoomImg(src, title) {
    Swal.fire({
        title, imageUrl:src, imageAlt:title,
        showCloseButton:true, showConfirmButton:false,
        background:'#fff', backdrop:'rgba(15,23,42,0.85)',
        customClass:{popup:'rounded-4 p-4', image:'img-fluid rounded-3'}
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
