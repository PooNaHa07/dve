<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

$action_msg  = '';
$action_type = 'success';

$upload_base = realpath(__DIR__ . '/../uploads');

// Helper to safely check if file is within uploads directory
function is_safe_upload_path($filepath, $base) {
    $real = realpath($filepath);
    return ($real !== false && strpos($real, $base) === 0);
}

// Handle Single File Delete & Bulk Clean Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $action_msg  = 'คำขอไม่ถูกต้องหรือเซสชันหมดอายุ';
        $action_type = 'danger';
    } else {
        $act = $_POST['action'];

        if ($act === 'delete_single' && !empty($_POST['target_file'])) {
            $rel_file = ltrim($_POST['target_file'], '/\\');
            $full_path = __DIR__ . '/../' . $rel_file;

            if (is_safe_upload_path($full_path, $upload_base) && is_file($full_path)) {
                $fname = basename($full_path);
                $fsize = filesize($full_path);
                if (@unlink($full_path)) {
                    $mb = round($fsize / (1024 * 1024), 2);
                    $action_msg = "ลบไฟล์ '$fname' เรียบร้อยแล้ว (คืนพื้นที่ {$mb} MB)";
                    log_audit('storage_clean_single', "Deleted single file: {$rel_file}");
                } else {
                    $action_msg = "ไม่สามารถลบไฟล์ '$fname' ได้";
                    $action_type = 'danger';
                }
            } else {
                $action_msg = "ไฟล์ไม่ถูกต้องหรือไม่อนุญาตให้ลบ";
                $action_type = 'danger';
            }
        }

        if ($act === 'clean_temp') {
            $temp_dir = $upload_base . '/temp';
            $cleaned = 0;
            $freed = 0;
            if (is_dir($temp_dir)) {
                foreach (scandir($temp_dir) as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $path = $temp_dir . '/' . $file;
                    if (is_file($path)) {
                        $freed += filesize($path);
                        if (@unlink($path)) $cleaned++;
                    }
                }
            }
            $freed_mb = round($freed / (1024 * 1024), 2);
            $action_msg = "ล้างไฟล์ชั่วคราวสำเร็จ! ลบไป {$cleaned} ไฟล์ (คืนพื้นที่ {$freed_mb} MB)";
            log_audit('storage_clean_temp', "Cleaned {$cleaned} temp files ({$freed_mb} MB)");
        }

        if ($act === 'clean_orphans') {
            // 1. Fetch valid avatar names from DB
            $valid_avatars = [];
            $res = $conn->query("SELECT DISTINCT profile_image FROM users WHERE profile_image IS NOT NULL AND profile_image != ''");
            if ($res) {
                while ($r = $res->fetch_assoc()) $valid_avatars[basename($r['profile_image'])] = true;
            }

            // 2. Fetch valid report image names from DB
            $valid_report_imgs = [];
            $res2 = $conn->query("SELECT image1, image2 FROM daily_reports");
            if ($res2) {
                while ($r = $res2->fetch_assoc()) {
                    if (!empty($r['image1'])) $valid_report_imgs[basename($r['image1'])] = true;
                    if (!empty($r['image2'])) $valid_report_imgs[basename($r['image2'])] = true;
                }
            }

            $cleaned = 0;
            $freed = 0;

            // Clean orphan avatars
            $avatar_dir = $upload_base . '/avatars';
            if (is_dir($avatar_dir)) {
                foreach (scandir($avatar_dir) as $file) {
                    if ($file === '.' || $file === '..' || $file === '.gitignore' || $file === 'default-avatar.png') continue;
                    if (!isset($valid_avatars[$file]) && is_file($avatar_dir . '/' . $file)) {
                        $freed += filesize($avatar_dir . '/' . $file);
                        if (@unlink($avatar_dir . '/' . $file)) $cleaned++;
                    }
                }
            }

            // Clean orphan report images
            foreach (['images', 'reports'] as $folder) {
                $dir = $upload_base . '/' . $folder;
                if (is_dir($dir)) {
                    foreach (scandir($dir) as $file) {
                        if ($file === '.' || $file === '..' || $file === '.gitignore') continue;
                        if (!isset($valid_report_imgs[$file]) && is_file($dir . '/' . $file)) {
                            $freed += filesize($dir . '/' . $file);
                            if (@unlink($dir . '/' . $file)) $cleaned++;
                        }
                    }
                }
            }

            $freed_mb = round($freed / (1024 * 1024), 2);
            $action_msg = "ล้างไฟล์กำพร้าสำเร็จ! ลบไฟล์ที่ไม่ได้ใช้งานแล้ว {$cleaned} ไฟล์ (คืนพื้นที่ {$freed_mb} MB)";
            log_audit('storage_clean_orphans', "Cleaned {$cleaned} orphan files ({$freed_mb} MB)");
        }
    }
}

// Analyze Storage Statistics
$folders_stats = [];
$total_system_bytes = 0;
$total_system_files = 0;

$target_folders = [
    'avatars'           => 'รูปโปรไฟล์ผู้ใช้',
    'images'            => 'รูปภาพบันทึกรายงาน',
    'reports'           => 'สำเนาภาพบันทึกรายงาน',
    'supervision_docs'  => 'เอกสารนิเทศก์ (PDF)',
    'news'              => 'ภาพ/วิดีโอข่าวประชาสัมพันธ์',
    'plans'             => 'แผนการนิเทศก์',
    'documents'         => 'เอกสารดาวน์โหลดทั่วไป',
    'subject_plans'     => 'แผนการสอนวิชา',
    'event_images'      => 'รูปภาพปฏิทินกิจกรรม',
    'temp'              => 'ไฟล์ชั่วคราว (Temp)',
];

foreach ($target_folders as $folder_key => $folder_label) {
    $dir_path = $upload_base . '/' . $folder_key;
    $count = 0;
    $bytes = 0;

    if (is_dir($dir_path)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
                $bytes += $file->getSize();
            }
        }
    }

    $total_system_bytes += $bytes;
    $total_system_files += $count;

    $folders_stats[$folder_key] = [
        'label' => $folder_label,
        'count' => $count,
        'bytes' => $bytes,
        'mb'    => round($bytes / (1024 * 1024), 2)
    ];
}

// DB Valids
$valid_avatars = [];
$res = $conn->query("SELECT DISTINCT profile_image FROM users WHERE profile_image IS NOT NULL AND profile_image != ''");
if ($res) {
    while ($r = $res->fetch_assoc()) $valid_avatars[basename($r['profile_image'])] = true;
}

$valid_report_imgs = [];
$res2 = $conn->query("SELECT image1, image2 FROM daily_reports");
if ($res2) {
    while ($r = $res2->fetch_assoc()) {
        if (!empty($r['image1'])) $valid_report_imgs[basename($r['image1'])] = true;
        if (!empty($r['image2'])) $valid_report_imgs[basename($r['image2'])] = true;
    }
}

// Detailed List of Orphan Files
$orphan_files = [];
$total_orphan_bytes = 0;

// Check Avatars
$avatar_dir = $upload_base . '/avatars';
if (is_dir($avatar_dir)) {
    foreach (scandir($avatar_dir) as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitignore' || $file === 'default-avatar.png') continue;
        $file_path = $avatar_dir . '/' . $file;
        if (!isset($valid_avatars[$file]) && is_file($file_path)) {
            $fsize = filesize($file_path);
            $total_orphan_bytes += $fsize;
            $orphan_files[] = [
                'type'       => 'รูปโปรไฟล์กำพร้า',
                'folder'     => 'uploads/avatars',
                'rel_path'   => 'uploads/avatars/' . $file,
                'filename'   => $file,
                'size_kb'    => round($fsize / 1024, 1),
                'modified'   => date('d/m/Y H:i', filemtime($file_path)),
                'url'        => BASE_URL . '/uploads/avatars/' . $file
            ];
        }
    }
}

// Check Report Images
foreach (['images', 'reports'] as $folder) {
    $dir = $upload_base . '/' . $folder;
    if (is_dir($dir)) {
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitignore') continue;
            $file_path = $dir . '/' . $file;
            if (!isset($valid_report_imgs[$file]) && is_file($file_path)) {
                $fsize = filesize($file_path);
                $total_orphan_bytes += $fsize;
                $orphan_files[] = [
                    'type'       => 'รูปภาพรายงานกำพร้า',
                    'folder'     => 'uploads/' . $folder,
                    'rel_path'   => 'uploads/' . $folder . '/' . $file,
                    'filename'   => $file,
                    'size_kb'    => round($fsize / 1024, 1),
                    'modified'   => date('d/m/Y H:i', filemtime($file_path)),
                    'url'        => BASE_URL . '/uploads/' . $folder . '/' . $file
                ];
            }
        }
    }
}

// Detailed List of Temp Files
$temp_files = [];
$total_temp_bytes = 0;
$temp_dir = $upload_base . '/temp';
if (is_dir($temp_dir)) {
    foreach (scandir($temp_dir) as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitignore') continue;
        $file_path = $temp_dir . '/' . $file;
        if (is_file($file_path)) {
            $fsize = filesize($file_path);
            $total_temp_bytes += $fsize;
            $temp_files[] = [
                'type'       => 'ไฟล์ชั่วคราว (Temp)',
                'folder'     => 'uploads/temp',
                'rel_path'   => 'uploads/temp/' . $file,
                'filename'   => $file,
                'size_kb'    => round($fsize / 1024, 1),
                'modified'   => date('d/m/Y H:i', filemtime($file_path)),
                'url'        => BASE_URL . '/uploads/temp/' . $file
            ];
        }
    }
}

$total_orphan_count = count($orphan_files);
$total_orphan_mb    = round($total_orphan_bytes / (1024 * 1024), 2);

$total_temp_count   = count($temp_files);
$total_temp_mb      = round($total_temp_bytes / (1024 * 1024), 2);

$total_system_mb    = round($total_system_bytes / (1024 * 1024), 2);

$hide_welcome = true;
$page_title   = 'ระบบจัดการพื้นที่จัดเก็บข้อมูล (Storage Manager)';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-4 text-white rounded-4 shadow-sm"
         style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #0f172a 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-white bg-opacity-10 rounded-3 fs-2">
                <i class="bi bi-hdd-network-fill text-warning"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1">จัดการพื้นที่จัดเก็บไฟล์ (Storage Management)</h3>
                <p class="text-white-50 mb-0">วิเคราะห์การใช้งานดิสก์ สแกนและทำความสะอาดไฟล์กำพร้าในระบบ</p>
            </div>
        </div>
        <a href="system_tools.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้า เครื่องมือระบบ
        </a>
    </div>

    <?php if (!empty($action_msg)): ?>
    <div class="alert alert-<?= $action_type ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($action_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:#f8fafc; border-left: 5px solid #0ea5e9 !important;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-semibold small">พื้นที่ใช้ไปทั้งหมด</span>
                    <i class="bi bi-hdd-fill fs-4 text-info"></i>
                </div>
                <div class="fs-2 fw-bold text-dark mb-1"><?= number_format($total_system_mb, 2) ?> MB</div>
                <div class="text-muted small">รวมทั้งหมด <?= number_format($total_system_files) ?> ไฟล์</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:#fffbeb; border-left: 5px solid #f59e0b !important;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-warning-emphasis fw-semibold small">ไฟล์กำพร้า (Orphan Files)</span>
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                </div>
                <div class="fs-2 fw-bold text-warning-emphasis mb-1"><?= number_format($total_orphan_mb, 2) ?> MB</div>
                <div class="text-muted small">พบ <?= number_format($total_orphan_count) ?> ไฟล์ที่ไม่ได้อ้างอิงกับ DB</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:#f0fdf4; border-left: 5px solid #22c55e !important;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-success fw-semibold small">ไฟล์ชั่วคราว (Temp Folder)</span>
                    <i class="bi bi-folder-symlink-fill fs-4 text-success"></i>
                </div>
                <div class="fs-2 fw-bold text-success mb-1"><?= number_format($total_temp_mb, 2) ?> MB</div>
                <div class="text-muted small">พบ <?= number_format($total_temp_count) ?> ไฟล์รอการล้าง</div>
            </div>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-4 bg-white">
        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>เครื่องมือทำความสะอาดดิสก์อัตโนมัติ</h5>
        <div class="d-flex flex-wrap gap-3">
            <form method="POST" onsubmit="return confirm('ยืนยันลบไฟล์กำพร้าทั้งหมด <?= number_format($total_orphan_count) ?> รายการ?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clean_orphans">
                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm" <?= $total_orphan_count === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-trash3-fill me-1"></i> ล้างไฟล์กำพร้าทั้งหมด (คืนพื้นที่ <?= number_format($total_orphan_mb, 2) ?> MB)
                </button>
            </form>

            <form method="POST" onsubmit="return confirm('ยืนยันล้างไฟล์ในโฟลเดอร์ Temp ทั้งหมด <?= number_format($total_temp_count) ?> รายการ?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clean_temp">
                <button type="submit" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm" <?= $total_temp_count === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-folder-x me-1"></i> ล้างไฟล์ Temp ทั้งหมด (<?= number_format($total_temp_count) ?> ไฟล์ / <?= number_format($total_temp_mb, 2) ?> MB)
                </button>
            </form>
        </div>
    </div>

    <!-- Orphan Files List Preview -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-list-check text-warning me-2"></i>รายชื่อไฟล์กำพร้าที่จะถูกลบ (Orphan Files List)
                </h5>
                <span class="text-muted small">ไฟล์ที่ตรวจพบในระบบดิสก์แต่ไม่มีในฐานข้อมูล สามารถเลือกลบทีละไฟล์ หรือกดลบทั้งหมดได้</span>
            </div>
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6 rounded-pill">
                <?= number_format($total_orphan_count) ?> รายการ (<?= number_format($total_orphan_mb, 2) ?> MB)
            </span>
        </div>

        <?php if (!empty($orphan_files)): ?>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4" style="width: 80px;">ตัวอย่าง</th>
                        <th>ประเภท</th>
                        <th>ที่อยู่ไฟล์ (Path)</th>
                        <th>ชื่อไฟล์</th>
                        <th>ขนาดไฟล์</th>
                        <th>วันที่แก้ไขล่าสุด</th>
                        <th class="pe-4 text-end">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphan_files as $of): ?>
                    <tr>
                        <td class="ps-4">
                            <?php 
                                $ext = strtolower(pathinfo($of['filename'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): 
                            ?>
                            <a href="<?= $of['url'] ?>" target="_blank">
                                <img src="<?= $of['url'] ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                            </a>
                            <?php else: ?>
                            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-file-earmark-code fs-5 text-secondary"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-warning bg-opacity-15 text-warning-emphasis fw-bold px-2.5 py-1 rounded-pill" style="font-size: .75rem;">
                                <?= htmlspecialchars($of['type']) ?>
                            </span>
                        </td>
                        <td class="text-muted small font-monospace"><?= htmlspecialchars($of['folder']) ?>/</td>
                        <td class="fw-bold text-dark small font-monospace">
                            <a href="<?= $of['url'] ?>" target="_blank" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($of['filename']) ?> <i class="bi bi-box-arrow-up-right ms-1 text-muted" style="font-size: .7rem;"></i>
                            </a>
                        </td>
                        <td class="small fw-bold text-secondary"><?= number_format($of['size_kb'], 1) ?> KB</td>
                        <td class="small text-muted"><?= htmlspecialchars($of['modified']) ?></td>
                        <td class="pe-4 text-end">
                            <form method="POST" onsubmit="return confirm('ยืนยันลบไฟล์นี้จากดิสก์?');" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_single">
                                <input type="hidden" name="target_file" value="<?= htmlspecialchars($of['rel_path']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-bold" style="font-size: .78rem;">
                                    <i class="bi bi-trash me-1"></i> ลบไฟล์นี้
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="p-4 text-center bg-white">
            <i class="bi bi-check-circle-fill text-success display-5 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">ไม่พบไฟล์กำพร้าในระบบ</h6>
            <p class="text-muted small mb-0">ไฟล์รูปภาพและเอกสารทั้งหมดถูกใช้งานและอ้างอิงกับฐานข้อมูลเรียบร้อยแล้ว</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Temp Files List Preview -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-folder-symlink-fill text-danger me-2"></i>รายชื่อไฟล์ชั่วคราวที่จะถูกลบ (Temp Files List)
                </h5>
                <span class="text-muted small">ไฟล์ขยะชั่วคราวที่สร้างขึ้นระหว่างการส่งออก PDF หรือทำงานชั่วคราว อยู่ในโฟลเดอร์ uploads/temp/</span>
            </div>
            <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-2 fs-6 rounded-pill">
                <?= number_format($total_temp_count) ?> รายการ (<?= number_format($total_temp_mb, 2) ?> MB)
            </span>
        </div>

        <?php if (!empty($temp_files)): ?>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4" style="width: 80px;">ตัวอย่าง</th>
                        <th>ประเภท</th>
                        <th>ที่อยู่ไฟล์ (Path)</th>
                        <th>ชื่อไฟล์</th>
                        <th>ขนาดไฟล์</th>
                        <th>วันที่แก้ไขล่าสุด</th>
                        <th class="pe-4 text-end">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($temp_files as $tf): ?>
                    <tr>
                        <td class="ps-4">
                            <?php 
                                $ext = strtolower(pathinfo($tf['filename'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): 
                            ?>
                            <a href="<?= $tf['url'] ?>" target="_blank">
                                <img src="<?= $tf['url'] ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                            </a>
                            <?php elseif ($ext === 'pdf'): ?>
                            <div class="bg-danger bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-danger" style="width: 42px; height: 42px;">
                                <i class="bi bi-file-earmark-pdf-fill fs-5"></i>
                            </div>
                            <?php else: ?>
                            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-secondary" style="width: 42px; height: 42px;">
                                <i class="bi bi-file-earmark fs-5"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-2.5 py-1 rounded-pill" style="font-size: .75rem;">
                                <?= htmlspecialchars($tf['type']) ?>
                            </span>
                        </td>
                        <td class="text-muted small font-monospace"><?= htmlspecialchars($tf['folder']) ?>/</td>
                        <td class="fw-bold text-dark small font-monospace">
                            <a href="<?= $tf['url'] ?>" target="_blank" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($tf['filename']) ?> <i class="bi bi-box-arrow-up-right ms-1 text-muted" style="font-size: .7rem;"></i>
                            </a>
                        </td>
                        <td class="small fw-bold text-secondary"><?= number_format($tf['size_kb'], 1) ?> KB</td>
                        <td class="small text-muted"><?= htmlspecialchars($tf['modified']) ?></td>
                        <td class="pe-4 text-end">
                            <form method="POST" onsubmit="return confirm('ยืนยันลบไฟล์ Temp นี้จากดิสก์?');" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_single">
                                <input type="hidden" name="target_file" value="<?= htmlspecialchars($tf['rel_path']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-bold" style="font-size: .78rem;">
                                    <i class="bi bi-trash me-1"></i> ลบไฟล์นี้
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="p-4 text-center bg-white">
            <i class="bi bi-check-circle-fill text-success display-5 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">ไม่พบไฟล์ Temp ค้างในระบบ</h6>
            <p class="text-muted small mb-0">โฟลเดอร์ uploads/temp/ สะอาด ไม่มีไฟล์ขยะชั่วคราวค้างอยู่</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Breakdown Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>สถิติการใช้งานดิสก์แยกตามโฟลเดอร์</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ชื่อโฟลเดอร์</th>
                        <th>คำอธิบาย</th>
                        <th>จำนวนไฟล์</th>
                        <th>ขนาดพื้นที่ (MB)</th>
                        <th class="pe-4" style="width: 30%;">สัดส่วนพื้นที่</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($folders_stats as $key => $st):
                        $percent = $total_system_bytes > 0 ? round(($st['bytes'] / $total_system_bytes) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark">
                            <i class="bi bi-folder2-open text-primary me-2"></i>uploads/<?= htmlspecialchars($key) ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($st['label']) ?></td>
                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1"><?= number_format($st['count']) ?> ไฟล์</span></td>
                        <td class="fw-bold text-dark"><?= number_format($st['mb'], 2) ?> MB</td>
                        <td class="pe-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percent ?>%;"></div>
                                </div>
                                <span class="small text-muted fw-bold" style="min-width: 45px; text-align: right;"><?= $percent ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
